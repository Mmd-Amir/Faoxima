<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;

final class Approval
{
    public static function afterExternalConfirm($runtimePdo, string $orderId, string $actor, string $reason): void
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if ($csp === null) {
            return;
        }
        $resolution = 'admin_approved';
        if ($csp['received_rial'] === null) {
            $resolution = 'admin_unverified';
        } elseif ((int) $csp['received_rial'] < (int) $csp['payable_rial']) {
            $resolution = 'admin_shortfall';
        }
        $outcome = \Faoxima\Payments\Receipts\Recovery::fulfillmentOutcome($runtimePdo, $orderId);
        $special = is_array($outcome) && in_array($outcome['outcome'], ['refunded', 'wallet_credit'], true) ? (string) $outcome['outcome'] : null;
        if ($special !== null) {
            $resolution .= $special === 'refunded' ? '_refund' : '_wallet_credit';
        }
        \Faoxima\Payments\Receipts\Recovery::finalize($runtimePdo, $orderId, $special !== null ? 'credited' : 'approved', $resolution, $actor !== '' ? $actor : null, $reason !== '' ? $reason : null);
        \Faoxima\Payments\Receipts\ReceiptOrders::audit($runtimePdo, $orderId, $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null, $actor !== '' ? $actor : null, 'admin_approve', [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'expected_rial' => (int) $csp['payable_rial'],
            'received_rial' => $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null,
            'credit_toman' => $special !== null ? (int) ($outcome['amount'] ?? 0) : (int) $csp['principal_toman'],
            'outcome' => $resolution,
            'reason' => $reason !== '' ? $reason : null,
        ]);
    }

    public static function orderSummary($runtimePdo, string $orderId): ?array
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if ($csp === null) {
            return null;
        }
        $pr = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        $dep = $csp['deposit_id'] !== null ? \Faoxima\Payments\Receipts\ReceiptOrders::deposit($runtimePdo, (int) $csp['deposit_id']) : null;
        $received = $csp['received_rial'] !== null ? (int) $csp['received_rial'] : ($dep !== null && $dep['amount_rial'] !== null ? (int) $dep['amount_rial'] : null);
        return [
            'csp' => $csp,
            'report' => $pr,
            'deposit' => $dep,
            'received_rial' => $received,
            'expected_rial' => (int) $csp['payable_rial'],
            'diff_rial' => $received === null ? null : $received - (int) $csp['payable_rial'],
        ];
    }

    public static function adminApprove($runtimePdo, $runtimeSetting, string $orderId, string $actor, string $reason, string $image = 'images.jpg'): array
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if ($csp === null) {
            return ['ok' => false, 'reason' => 'not_verify'];
        }
        if (in_array((string) $csp['state'], ['approved', 'credited'], true)) {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        $guard = \Faoxima\Payments\Receipts\ReviewQueries::confirmGuard($runtimePdo, $orderId, $reason);
        if ($guard !== null) {
            return ['ok' => false, 'reason' => $guard];
        }
        $before = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        if ($before === null) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        $expiredPayment = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $before);
        if ((string) $csp['state'] === 'closed' && !$expiredPayment) {
            return ['ok' => false, 'reason' => 'not_eligible'];
        }
        $status = (string) $before['payment_Status'];
        if ($status === 'waiting' && method_exists(\Faoxima\Payments\Receipts\ReceiptActions::class, 'rxReceiptConfirm')) {
            $res = \Faoxima\Payments\Receipts\ManualReview::receiptConfirm($runtimePdo, $runtimeSetting, $orderId, ['actor_id' => $actor, 'actor_label' => $actor, 'override_reason' => $reason]);
            if (empty($res['ok'])) {
                return ['ok' => false, 'reason' => (string) ($res['reason'] ?? 'error')];
            }
            $outcome = \Faoxima\Payments\Receipts\Recovery::fulfillmentOutcome($runtimePdo, $orderId);
            return ['ok' => true, 'outcome' => is_array($outcome) ? (string) ($outcome['outcome'] ?? '') : ''];
        }
        if (!in_array($status, ['Unpaid', 'expire'], true)) {
            return ['ok' => false, 'reason' => $status === 'paid' ? 'already_paid' : 'not_eligible'];
        }
        $claim = \Faoxima\Payments\Fulfillment\Claims::claim($runtimePdo, $orderId, ['from' => ['Unpaid', 'expire'], 'to' => 'processing']);
        $claimStatus = (string) ($claim['status'] ?? 'error');
        if ($claimStatus === 'already_completed') {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        if ($claimStatus !== 'claimed') {
            return ['ok' => false, 'reason' => 'claim_' . $claimStatus];
        }
        $run = \Faoxima\Payments\Receipts\Recovery::runClaimed($runtimePdo, $orderId, (string) $claim['token'], $image, \Faoxima\Payments\Receipts\Recovery::releaseStatus($claim['report'] ?? $before, $status));
        if (empty($run['finalized'])) {
            return ['ok' => false, 'reason' => 'fulfillment_' . (string) $run['settle']];
        }
        $resolution = $csp['received_rial'] === null ? 'admin_unverified' : ((int) $csp['received_rial'] < (int) $csp['payable_rial'] ? 'admin_shortfall' : 'admin_approved');
        \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_after_approval($orderId, $resolution, $actor, $reason !== '' ? $reason : null, true);
        $outcome = \Faoxima\Payments\Receipts\Recovery::fulfillmentOutcome($runtimePdo, $orderId);
        return ['ok' => true, 'outcome' => is_array($outcome) ? (string) ($outcome['outcome'] ?? '') : ''];
    }

    public static function adminSetReceived($runtimePdo, string $orderId, string $actor, int $receivedToman, string $reason = ''): array
    {
        if ($receivedToman < 0 || $receivedToman > 100000000000) {
            return ['ok' => false, 'reason' => 'invalid_amount'];
        }
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        try {
            $out = \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($orderId, $actor, $receivedToman, $reason) {
                $now = time();
                $c = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $c->execute([$orderId]);
                $csp = $c->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    return ['ok' => false, 'reason' => 'not_verify'];
                }
                $p = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $p->execute([$orderId]);
                $report = $p->fetch(PDO::FETCH_ASSOC);
                if ($report === false) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $status = (string) ($report['payment_Status'] ?? '');
                if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1 || in_array((string) $csp['state'], ['approved', 'credited'], true)) {
                    return ['ok' => false, 'reason' => 'already_paid'];
                }
                if (in_array($status, ['processing', 'reconciling'], true)) {
                    return ['ok' => false, 'reason' => 'processing'];
                }
                if (!in_array($status, ['Unpaid', 'waiting', 'expire'], true)) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                if (in_array((string) $csp['state'], ['matched', 'void'], true)) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                $upd = $pdo->prepare("UPDATE card_sms_payment SET received_rial = ?, updated_at = ? WHERE id = ? AND state NOT IN ('approved', 'credited', 'matched', 'void')");
                $upd->execute([$receivedToman * 10, $now, (int) $csp['id']]);
                if ($upd->rowCount() !== 1) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, 'manual_received_correction', ?, ?, ?, ?, NULL, 'corrected', ?, ?)")
                    ->execute([
                        $orderId,
                        $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null,
                        $actor,
                        (int) $csp['principal_toman'],
                        (int) $csp['fee_toman'],
                        (int) $csp['payable_rial'],
                        $receivedToman * 10,
                        $reason,
                        $now,
                    ]);
                return ['ok' => true, 'previous_rial' => $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null, 'received_rial' => $receivedToman * 10];
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_RECEIVED_CORRECTION_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        return $out;
    }

    public static function adminCredit($runtimePdo, string $orderId, string $actor, string $reason): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        $summary = self::orderSummary($runtimePdo, $orderId);
        if ($summary === null) {
            return ['ok' => false, 'reason' => 'not_verify'];
        }
        $csp = $summary['csp'];
        if (in_array((string) $csp['state'], ['approved', 'credited'], true)) {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        if ($summary['received_rial'] === null) {
            return ['ok' => false, 'reason' => 'received_required'];
        }
        $receivedToman = intdiv((int) $summary['received_rial'], 10);
        $credit = $receivedToman;
        if ($credit <= 0) {
            return ['ok' => false, 'reason' => 'nothing_to_credit'];
        }
        $report = $summary['report'];
        if (!is_array($report)) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        $status = (string) $report['payment_Status'];
        $from = ['Unpaid', 'waiting', 'expire', 'cancelled', 'reject'];
        if (!in_array($status, $from, true)) {
            return ['ok' => false, 'reason' => $status === 'paid' ? 'already_paid' : 'not_eligible'];
        }
        $claim = \Faoxima\Payments\Fulfillment\Claims::claim($runtimePdo, $orderId, ['from' => $from, 'to' => 'processing']);
        $claimStatus = (string) ($claim['status'] ?? 'error');
        if ($claimStatus === 'already_completed') {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        if ($claimStatus !== 'claimed') {
            return ['ok' => false, 'reason' => 'claim_' . $claimStatus];
        }
        $token = (string) $claim['token'];
        $outcome = \Faoxima\Wallet\Ledger\FulfillmentCredit::walletCredit($runtimePdo, $orderId, $token, (string) $report['id_user'], $credit, 'topup_card', 'تأیید دستی کارت‌به‌کارت با بررسی (' . $orderId . ')');
        if ($outcome !== 'credited') {
            \Faoxima\Payments\Fulfillment\Settlement::settle($runtimePdo, $orderId, $token, \Faoxima\Payments\Fulfillment\Claims::result('retryable', 'sbv_credit_' . $outcome), \Faoxima\Payments\Receipts\Recovery::releaseStatus($report, $status), \Faoxima\Payments\Receipts\Recovery::releaseStatus($report, $status));
            return ['ok' => false, 'reason' => 'credit_' . $outcome];
        }
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        try {
            if ($pdo->query("SHOW TABLES LIKE 'checkout_order'")->fetchColumn() !== false) {
                $pdo->prepare("UPDATE checkout_order SET status = 'cancelled', fulfillment_state = 'sbv_credit', updated_at = ? WHERE payment_order_id = ? AND status IN ('awaiting_payment', 'confirmed', 'awaiting_choice') AND (settle_order_id IS NULL OR settle_order_id = '')")
                    ->execute([time(), $orderId]);
            }
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_CHECKOUT_RELEASE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
        \Faoxima\Payments\Receipts\Recovery::finalize($runtimePdo, $orderId, 'credited', 'admin_credit', $actor, $reason);
        \Faoxima\Payments\Receipts\ReceiptOrders::audit($runtimePdo, $orderId, $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null, $actor, 'admin_credit', [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'expected_rial' => (int) $csp['payable_rial'],
            'received_rial' => (int) $summary['received_rial'],
            'credit_toman' => $credit,
            'outcome' => 'wallet_credit',
            'reason' => $reason,
        ]);
        if (function_exists('sendmessage')) {
            sendmessage($report['id_user'], \Faoxima\Telegram\Support\MessageTemplates::get('payment_card_manual_credited_full', ['order_id' => $orderId, 'amount' => \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($credit)]), null, 'HTML');
        }
        $text = "✅ پرداخت با بررسی به‌صورت شارژ کیف پول تسویه شد.\n\n"
            . "<blockquote>کد پیگیری : <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
            . "<blockquote>👤 کاربر : <code>" . htmlspecialchars((string) $report['id_user'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
            . "<blockquote>📥 مبلغ دریافتی : " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($receivedToman) . " تومان</blockquote>\n"
            . "<blockquote>🏷 کارمزد شناسایی : " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['fee_toman']) . " تومان (در این تصمیم دستی کسر نشد)</blockquote>\n"
            . "<blockquote>💼 واریز کامل به کیف پول : " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($credit) . " تومان</blockquote>\n"
            . "<blockquote>✍️ دلیل : " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
            . "<blockquote>👤 ادمین : <code>" . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . "</code></blockquote>";
        $fresh = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        $delivered = is_array($fresh) && ((int) ($fresh['report_message_id'] ?? 0) > 0 || trim((string) ($fresh['private_receipt_targets'] ?? '')) !== '');
        if ($delivered && method_exists(\Faoxima\Payments\Receipts\ReceiptActions::class, 'rxReceiptSyncTelegram')) {
            \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptSyncTelegram($fresh, $text, json_encode(['inline_keyboard' => [[['text' => '⚙️ مدیریت کاربر', 'callback_data' => 'manageuser_' . $report['id_user']]]]], JSON_UNESCAPED_UNICODE));
        } else {
            \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_report($text);
        }
        return ['ok' => true, 'credit' => $credit];
    }
}
