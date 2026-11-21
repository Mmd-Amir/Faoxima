<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;

final class Recovery
{
    public static function finalize($runtimePdo, string $orderId, string $state, string $resolution, ?string $actor, ?string $note): void
    {
        try {
            \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($orderId, $state, $resolution, $actor, $note) {
                $now = time();
                $stmt = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$orderId]);
                $csp = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    return;
                }
                if (in_array((string) $csp['state'], ['approved', 'credited'], true)) {
                    return;
                }
                $pdo->prepare("UPDATE card_sms_payment SET state = ?, resolution = ?, resolved_by = ?, resolved_at = ?, resolution_note = ?, updated_at = ? WHERE id = ?")
                    ->execute([$state, $resolution, $actor, $now, $note, $now, (int) $csp['id']]);
                if ($csp['deposit_id'] !== null) {
                    $pdo->prepare("UPDATE card_sms_deposit SET state = 'applied', id_order = ?, updated_at = ? WHERE id = ? AND state IN ('matched', 'linked')")
                        ->execute([$orderId, $now, (int) $csp['deposit_id']]);
                }
                \Faoxima\Payments\Receipts\ReceiptOrders::releaseAmount($pdo, $csp, false);
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_FINALIZE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
            $pr = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
            if (is_array($pr)) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $pr['id_user']));
            }
        }
    }

    public static function runClaimed($runtimePdo, string $orderId, string $token, string $image, string $releaseTo): array
    {
        $result = \Faoxima\Services\Fulfillment\Delivery::attemptClaimedDelivery($orderId, $token, $image, static function (Throwable $e) use ($orderId) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_DIRECT_PAYMENT_THROWABLE', $e->getMessage(), ['id_order' => $orderId, 'file' => $e->getFile(), 'line' => $e->getLine()]);
        });
        $settle = \Faoxima\Payments\Fulfillment\Settlement::settle($runtimePdo, $orderId, $token, $result, $releaseTo, 'waiting');
        return [
            'result' => $result,
            'settle' => $settle,
            'finalized' => !empty($result['completed']) || $settle === 'finalized' || !empty($result['already_completed']) || $settle === 'paid',
        ];
    }

    public static function releaseStatus(array $report, string $original): string
    {
        if (trim((string) ($report['dec_not_confirmed'] ?? '')) === 'receipt-submitted' || $original === 'waiting') {
            return 'waiting';
        }
        return in_array($original, ['Unpaid', 'expire', 'cancelled', 'reject'], true) ? $original : 'waiting';
    }

    public static function applyMatched($runtimePdo, string $orderId, string $image): string
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if ($csp === null || (string) $csp['state'] !== 'matched') {
            return 'skip';
        }
        $before = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        if ($before === null) {
            return 'skip';
        }
        $status = (string) $before['payment_Status'];
        if ((int) ($before['direct_payment_done'] ?? 0) === 1) {
            \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
            return 'finalized';
        }
        if ($status === 'paid') {
            $outcome = self::fulfillmentOutcome($runtimePdo, $orderId);
            if (($outcome['outcome'] ?? '') === 'processing') {
                $recovery = self::recoverOrder($runtimePdo, $orderId, $image);
                return ($recovery['status'] ?? '') === 'completed' ? 'finalized' : 'skip';
            }
            $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
            if ($pdo !== null) {
                $review = $pdo->prepare("UPDATE card_sms_payment SET state = 'review', resolution_note = ?, updated_at = ?
                    WHERE id_order = ? AND state = 'matched' AND NOT EXISTS (
                        SELECT 1 FROM payment_fulfillment WHERE id_order = ?
                    )");
                $review->execute(['paid_without_delivery_evidence', time(), $orderId, $orderId]);
            }
            return 'skip';
        }
        if (!in_array($status, ['Unpaid', 'waiting'], true)) {
            return 'skip';
        }
        $claim = \Faoxima\Payments\Fulfillment\Claims::claim($runtimePdo, $orderId, ['from' => ['Unpaid', 'waiting'], 'to' => 'processing']);
        $claimStatus = (string) ($claim['status'] ?? 'error');
        if ($claimStatus === 'already_completed') {
            self::finalize($runtimePdo, $orderId, 'approved', 'auto', 'system', null);
            return 'finalized';
        }
        if ($claimStatus !== 'claimed') {
            return 'claim_' . $claimStatus;
        }
        $report = is_array($claim['report'] ?? null) ? $claim['report'] : $before;
        $releaseTo = self::releaseStatus($report, $status);
        $run = self::runClaimed($runtimePdo, $orderId, (string) $claim['token'], $image, $releaseTo);
        if (empty($run['finalized'])) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_AUTO_APPLY_NOT_FINAL', 'Matched deposit claim did not finalize', [
                'id_order' => $orderId,
                'settle' => (string) $run['settle'],
                'result' => (string) ($run['result']['status'] ?? ''),
                'reason' => (string) ($run['result']['reason'] ?? ''),
            ]);
            self::holdIfTerminal($runtimePdo, $orderId, $run);
            return 'pending_' . (string) $run['settle'];
        }
        \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
        return 'finalized';
    }

    public static function holdIfTerminal($runtimePdo, string $orderId, array $run): void
    {
        $result = (array) ($run['result'] ?? []);
        $settle = (string) ($run['settle'] ?? '');
        $retryable = !empty($result['retryable']) || !empty($result['requires_reconciliation']) || in_array($settle, ['reconciling', 'error', 'not_owner', 'missing'], true);
        if ($retryable) {
            return;
        }
        try {
            \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($orderId, $result, $settle) {
                $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $s->execute([$orderId]);
                $csp = $s->fetch(PDO::FETCH_ASSOC);
                if ($csp === false || (string) $csp['state'] !== 'matched') {
                    return;
                }
                $now = time();
                $pdo->prepare("UPDATE card_sms_payment SET state = 'review', resolution_note = ?, updated_at = ? WHERE id = ?")
                    ->execute([substr('auto_apply_failed:' . (string) ($result['status'] ?? '') . ':' . (string) ($result['reason'] ?? '') . ':' . $settle, 0, 500), $now, (int) $csp['id']]);
                if ($csp['deposit_id'] !== null) {
                    $pdo->prepare("UPDATE card_sms_deposit SET state = 'linked', note = 'auto_apply_failed', updated_at = ? WHERE id = ? AND state = 'matched'")
                        ->execute([$now, (int) $csp['deposit_id']]);
                }
            });
            \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_report("⚠️ <b>واریز شناسایی شد اما تکمیل خودکار سفارش ناموفق بود</b>\n\n<blockquote>کد پیگیری: <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>\nواریز به این پرداخت متصل ماند و برای تصمیم ادمین نگه داشته شد. از دکمه «🔎 بررسی تطبیق» روی رسید یا بخش «🔐 تأیید رسید با بررسی» اقدام کنید.");
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_HOLD_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }

    public static function fulfillmentOutcome($runtimePdo, string $orderId): ?array
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($csp === null || $pdo === null) {
            return null;
        }
        $pr = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        $status = is_array($pr) ? (string) $pr['payment_Status'] : '';
        $done = is_array($pr) && (int) ($pr['direct_payment_done'] ?? 0) === 1;
        try {
            $st = $pdo->prepare("SELECT amount FROM wallet_ledger WHERE id_order = ? AND category = 'refund' AND direction = 'credit' ORDER BY id DESC LIMIT 1");
            $st->execute([$orderId]);
            $refund = $st->fetchColumn();
            if ($refund !== false) {
                return ['outcome' => 'refunded', 'amount' => (int) $refund];
            }
        } catch (Throwable $e) {
        }
        try {
            $st = $pdo->prepare("SELECT resolution FROM checkout_order WHERE (settle_order_id = ? OR payment_order_id = ?) AND resolution = 'wallet_credit' LIMIT 1");
            $st->execute([$orderId, $orderId]);
            if ($st->fetchColumn() !== false) {
                $c = $pdo->prepare("SELECT amount FROM wallet_ledger WHERE fulfillment_key = ? LIMIT 1");
                $c->execute(['ckcredit:' . $orderId]);
                $amt = $c->fetchColumn();
                return ['outcome' => 'wallet_credit', 'amount' => $amt === false ? (int) ($pr['price'] ?? 0) : (int) $amt];
            }
        } catch (Throwable $e) {
        }
        if ((string) $csp['state'] === 'credited' && in_array((string) $csp['resolution'], ['admin_credit'], true)) {
            $a = $pdo->prepare("SELECT credit_toman FROM card_sms_audit WHERE id_order = ? AND action = 'admin_credit' ORDER BY id DESC LIMIT 1");
            $a->execute([$orderId]);
            $amt = $a->fetchColumn();
            return ['outcome' => 'wallet_credit', 'amount' => $amt === false ? 0 : (int) $amt];
        }
        $pfState = '';
        try {
            $st = $pdo->prepare("SELECT state FROM payment_fulfillment WHERE id_order = ? LIMIT 1");
            $st->execute([$orderId]);
            $pfState = (string) $st->fetchColumn();
        } catch (Throwable $e) {
        }
        if ($done) {
            return ['outcome' => 'completed'];
        }
        if (in_array($status, ['processing', 'reconciling'], true) || in_array($pfState, ['claimed', 'applying', 'reconciling'], true)) {
            return ['outcome' => 'processing'];
        }
        if ($status === 'manual_review' || $pfState === 'manual_review' || $pfState === 'failed' || (string) $csp['state'] === 'review') {
            return ['outcome' => 'failed'];
        }
        if ($status === 'paid' && $pfState === '') {
            return ['outcome' => 'failed'];
        }
        if ((string) $csp['state'] === 'matched') {
            return ['outcome' => 'processing'];
        }
        if (in_array($status, ['reject', 'expire', 'cancelled'], true)) {
            return ['outcome' => 'closed'];
        }
        return ['outcome' => 'pending'];
    }

    public static function recoverOrder($runtimePdo, string $orderId, string $image): array
    {
        $out = ['status' => 'skip', 'order_id' => $orderId];
        if ($orderId === '') {
            return $out;
        }
        try {
            $recovered = \Faoxima\Payments\Fulfillment\Recovery::recover($runtimePdo, $orderId);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_RECOVER_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            $out['status'] = 'error';
            return $out;
        }
        $status = (string) ($recovered['status'] ?? 'skip');
        $out['recovery'] = $status;
        if ($status === 'finalized') {
            $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
            if ($csp !== null && (string) $csp['state'] === 'matched') {
                \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
            }
            $out['status'] = 'completed';
            return $out;
        }
        if ($status !== 'claimed' && $status !== 'reconcile') {
            if ($status === 'rejected' || $status === 'manual_review') {
                $out['status'] = 'failed';
            } elseif ($status === 'error') {
                $out['status'] = 'error';
            }
            return $out;
        }
        $report = is_array($recovered['report'] ?? null) ? $recovered['report'] : (\Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId) ?? []);
        $run = self::runClaimed($runtimePdo, $orderId, (string) ($recovered['token'] ?? ''), $image, self::releaseStatus($report, 'waiting'));
        $out['settle'] = (string) ($run['settle'] ?? '');
        if (!empty($run['finalized'])) {
            $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
            if ($csp !== null && (string) $csp['state'] === 'matched') {
                \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
            }
            $out['status'] = 'completed';
            return $out;
        }
        self::holdIfTerminal($runtimePdo, $orderId, $run);
        $out['status'] = 'processing';
        return $out;
    }

    public static function processDeposit($runtimePdo, int $depositId, string $image): array
    {
        $out = ['status' => 'skipped', 'deposit_id' => $depositId, 'deposit_state' => null, 'order_id' => null];
        if ($depositId <= 0) {
            $out['status'] = 'missing';
            return $out;
        }
        $deposit = \Faoxima\Payments\Receipts\ReceiptOrders::deposit($runtimePdo, $depositId);
        if (!is_array($deposit)) {
            $out['status'] = 'missing';
            return $out;
        }
        $state = (string) ($deposit['state'] ?? '');
        if ($state === 'pending') {
            $match = \Faoxima\Payments\Receipts\DepositMatching::matchDeposit($runtimePdo, $depositId);
            $out['match'] = $match;
            $deposit = \Faoxima\Payments\Receipts\ReceiptOrders::deposit($runtimePdo, $depositId);
            if (!is_array($deposit)) {
                $out['status'] = 'error';
                return $out;
            }
            $state = (string) ($deposit['state'] ?? '');
        }
        $out['deposit_state'] = $state;
        $orderId = trim((string) ($deposit['id_order'] ?? ''));
        if ($orderId !== '') {
            $out['order_id'] = $orderId;
        }
        if ($state === 'pending') {
            $out['status'] = ($out['match'] ?? '') === 'error' ? 'error' : 'skipped';
            return $out;
        }
        if (in_array($state, ['test', 'ignored'], true)) {
            $out['status'] = 'ignored';
            return $out;
        }
        if (in_array($state, ['unmatched', 'ambiguous', 'late'], true)) {
            $out['status'] = $state;
            try {
                \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_report_unmatched_deposit($depositId);
            } catch (Throwable $e) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_REVIEW_REPORT_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            }
            return $out;
        }
        if ($state === 'applied' || $state === 'reviewed_rejected' || $state === 'dismissed') {
            $out['status'] = 'already_processed';
            return $out;
        }
        if ($state !== 'matched') {
            $out['status'] = 'skipped';
            return $out;
        }
        if ($orderId === '' || !\Faoxima\Payments\Receipts\ReceiptOrders::isVerifyOrder($runtimePdo, $orderId)) {
            $out['status'] = 'skipped';
            return $out;
        }
        try {
            $applied = self::applyMatched($runtimePdo, $orderId, $image);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_AUTO_APPLY_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $depositId]);
            $applied = 'error';
        }
        $out['fulfillment'] = $applied;
        $outcome = self::fulfillmentOutcome($runtimePdo, $orderId);
        $kind = is_array($outcome) ? (string) ($outcome['outcome'] ?? '') : '';
        if ($applied === 'finalized' || $kind === 'completed') {
            $out['status'] = 'completed';
            return $out;
        }
        if ($kind === 'failed') {
            $out['status'] = 'failed';
            return $out;
        }
        if ($kind === 'closed') {
            $out['status'] = 'closed';
            return $out;
        }
        if ($kind === 'refunded' || $kind === 'wallet_credit') {
            $out['status'] = 'resolved';
            return $out;
        }
        if (in_array($applied, ['claim_busy', 'claim_manual_review', 'claim_not_eligible'], true) && $kind !== 'processing') {
            $out['status'] = $kind === '' ? 'error' : 'skipped';
            return $out;
        }
        if ($kind === 'processing' || $applied === 'error' || strpos($applied, 'pending_') === 0 || $applied === 'claim_requires_reconciliation') {
            try {
                $recovery = self::recoverOrder($runtimePdo, $orderId, $image);
            } catch (Throwable $e) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_RECOVER_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                $recovery = ['status' => 'error'];
            }
            $out['recovery'] = $recovery;
            if (($recovery['status'] ?? '') === 'completed') {
                $out['status'] = 'completed';
                return $out;
            }
            if (($recovery['status'] ?? '') === 'failed') {
                $out['status'] = 'failed';
                return $out;
            }
            $out['status'] = ($recovery['status'] ?? '') === 'error' ? 'error' : 'processing';
            return $out;
        }
        $out['status'] = $kind === 'pending' ? 'pending' : 'skipped';
        return $out;
    }

    public static function sweep($runtimePdo, int $limit = 200): int
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return 0;
        }
        $count = 0;
        try {
            $stmt = $pdo->prepare(
                "SELECT csp.id_order, csp.state, csp.created_at, pr.payment_Status, pr.direct_payment_done, pr.id AS pr_id
                   FROM card_sms_payment csp
                   LEFT JOIN Payment_report pr ON pr.id_order = csp.id_order
                  WHERE csp.state IN ('open', 'matched', 'review')
                    AND (pr.id IS NULL OR pr.payment_Status IN ('paid', 'reject', 'expire', 'cancelled') OR COALESCE(pr.direct_payment_done, 0) = 1)
                  ORDER BY csp.id ASC LIMIT " . max(1, $limit)
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_SWEEP_FAILED', $e->getMessage());
            return 0;
        }
        foreach ($rows as $row) {
            $orderId = (string) $row['id_order'];
            $status = (string) ($row['payment_Status'] ?? '');
            if ($row['pr_id'] === null) {
                if ((int) $row['created_at'] < time() - 600) {
                    try {
                        \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($orderId) {
                            $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                            $s->execute([$orderId]);
                            $csp = $s->fetch(PDO::FETCH_ASSOC);
                            if ($csp === false || !in_array((string) $csp['state'], ['open', 'matched', 'review'], true)) {
                                return;
                            }
                            $now = time();
                            if ($csp['deposit_id'] !== null) {
                                $pdo->prepare("UPDATE card_sms_deposit SET state = 'unmatched', note = 'order_missing', id_order = NULL, updated_at = ? WHERE id = ? AND state IN ('matched', 'linked')")
                                    ->execute([$now, (int) $csp['deposit_id']]);
                            }
                            $pdo->prepare("UPDATE card_sms_payment SET state = 'void', resolution = 'order_missing', deposit_id = NULL, updated_at = ? WHERE id = ?")
                                ->execute([$now, (int) $csp['id']]);
                            \Faoxima\Payments\Receipts\ReceiptOrders::releaseAmount($pdo, $csp, $csp['deposit_id'] === null);
                        });
                        $count++;
                    } catch (Throwable $e) {
                        \Faoxima\Payments\Receipts\Settings::log('SBV_SWEEP_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                    }
                }
                continue;
            }
            if ($status === 'paid' || (int) ($row['direct_payment_done'] ?? 0) === 1) {
                self::finalize($runtimePdo, $orderId, 'approved', 'external', null, null);
                $count++;
                continue;
            }
            try {
                \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($orderId, $status) {
                    $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $s->execute([$orderId]);
                    $csp = $s->fetch(PDO::FETCH_ASSOC);
                    if ($csp === false || !in_array((string) $csp['state'], ['open', 'matched', 'review'], true)) {
                        return;
                    }
                    $now = time();
                    $keepDeposit = $csp['deposit_id'];
                    if ($csp['deposit_id'] !== null) {
                        $d = $pdo->prepare("UPDATE card_sms_deposit SET state = 'unmatched', note = ?, id_order = NULL, updated_at = ? WHERE id = ? AND state = 'matched'");
                        $d->execute(['order_closed:' . $status . ':' . $orderId, $now, (int) $csp['deposit_id']]);
                        if ($d->rowCount() === 1) {
                            $keepDeposit = null;
                        }
                    }
                    $resolution = $status === 'expire' ? 'expired' : ($status === 'reject' ? 'rejected' : 'cancelled');
                    $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = COALESCE(resolution, ?), resolved_at = ?, deposit_id = ?, updated_at = ? WHERE id = ?")
                        ->execute([$resolution, $now, $keepDeposit, $now, (int) $csp['id']]);
                    \Faoxima\Payments\Receipts\ReceiptOrders::releaseAmount($pdo, $csp, false);
                });
                $count++;
            } catch (Throwable $e) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_SWEEP_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            }
        }
        return $count;
    }

    public static function recover($runtimePdo, string $image, int $limit = 20): void
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT pr.id_order FROM Payment_report pr JOIN card_sms_payment csp ON csp.id_order = pr.id_order
                  WHERE pr.payment_Status IN ('processing', 'reconciling') AND pr.Payment_Method = 'cart to cart'
                  ORDER BY pr.id ASC LIMIT " . max(1, $limit)
            );
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_RECOVER_SCAN_FAILED', $e->getMessage());
            return;
        }
        foreach ($orders as $orderId) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $orderId = (string) $orderId;
            $recovered = \Faoxima\Payments\Fulfillment\Recovery::recover($runtimePdo, $orderId);
            $status = (string) ($recovered['status'] ?? '');
            if ($status === 'finalized') {
                $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
                if ($csp !== null && (string) $csp['state'] === 'matched') {
                    \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
                }
                continue;
            }
            if ($status !== 'claimed' && $status !== 'reconcile') {
                continue;
            }
            $report = is_array($recovered['report'] ?? null) ? $recovered['report'] : (\Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId) ?? []);
            $run = self::runClaimed($runtimePdo, $orderId, (string) $recovered['token'], $image, self::releaseStatus($report, 'waiting'));
            if (!empty($run['finalized'])) {
                $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
                if ($csp !== null && (string) $csp['state'] === 'matched') {
                    \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
                }
            } else {
                self::holdIfTerminal($runtimePdo, $orderId, $run);
            }
        }
    }

    public static function cronRun($runtimePdo, string $image = '../images.jpg'): array
    {
        $out = ['matched' => 0, 'unmatched' => 0, 'applied' => 0, 'swept' => 0];
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo) || !method_exists(\Faoxima\Payments\Fulfillment\FulfillmentRuntime::class, 'rx_pf_claim')) {
            return $out;
        }
        try {
            $lock = $pdo->query("SELECT GET_LOCK('rx_sbv_cron', 0)")->fetchColumn();
        } catch (Throwable $e) {
            $lock = 0;
        }
        if ((int) $lock !== 1) {
            return $out;
        }
        try {
            self::recover($runtimePdo, $image);
            $pending = $pdo->query("SELECT id FROM card_sms_deposit WHERE state = 'pending' ORDER BY id ASC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($pending as $depositId) {
                if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                    break;
                }
                $r = \Faoxima\Payments\Receipts\DepositMatching::matchDeposit($runtimePdo, (int) $depositId);
                if ($r === 'matched') {
                    $out['matched']++;
                } elseif ($r !== 'skip' && $r !== 'error') {
                    $out['unmatched']++;
                }
            }
            $matched = $pdo->query("SELECT id_order FROM card_sms_payment WHERE state = 'matched' ORDER BY updated_at ASC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($matched as $orderId) {
                if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                    break;
                }
                if (self::applyMatched($runtimePdo, (string) $orderId, $image) === 'finalized') {
                    $out['applied']++;
                }
            }
            $out['swept'] = self::sweep($runtimePdo);
            if ($out['unmatched'] > 0) {
                $total = (int) $pdo->query("SELECT COUNT(*) FROM card_sms_deposit WHERE state IN ('unmatched', 'ambiguous', 'late')")->fetchColumn();
                \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_report("⚠️ <b>واریز تطبیق‌نیافته</b>\n\n" . (int) $out['unmatched'] . " واریز جدید با هیچ پرداخت فعالی تطبیق داده نشد و برای بررسی ادمین نگه داشته شد.\n📥 تعداد کل در صف بررسی: " . $total . "\n\nمسیر: مالی و گزارشات ← کارت به کارت ← 🔐 تأیید رسید با بررسی ← واریزهای تطبیق‌نیافته");
            }
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_CRON_FAILED', $e->getMessage());
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK('rx_sbv_cron')");
            } catch (Throwable $e) {
            }
        }
        return $out;
    }
}
