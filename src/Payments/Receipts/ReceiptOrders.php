<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use PDOException;
use Throwable;

final class ReceiptOrders
{
    public static function audit($runtimePdo, ?string $orderId, ?int $depositId, ?string $actor, string $action, array $data = []): void
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return;
        }
        try {
            $pdo->prepare(
                "INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $orderId,
                $depositId,
                $actor,
                $action,
                isset($data['principal_toman']) ? (int) $data['principal_toman'] : null,
                isset($data['fee_toman']) ? (int) $data['fee_toman'] : null,
                isset($data['expected_rial']) ? (int) $data['expected_rial'] : null,
                isset($data['received_rial']) ? (int) $data['received_rial'] : null,
                isset($data['credit_toman']) ? (int) $data['credit_toman'] : null,
                isset($data['outcome']) ? (string) $data['outcome'] : null,
                isset($data['reason']) ? (string) $data['reason'] : null,
                time(),
            ]);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_AUDIT_FAILED', $e->getMessage(), ['id_order' => $orderId, 'action' => $action]);
        }
    }

    public static function get($runtimePdo, string $orderId, bool $lock = false): ?array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || $orderId === '' || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1" . ($lock ? " FOR UPDATE" : ""));
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function isVerifyOrder($runtimePdo, string $orderId): bool
    {
        return self::get($runtimePdo, $orderId) !== null;
    }

    public static function deposit($runtimePdo, int $depositId, bool $lock = false): ?array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || $depositId <= 0 || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1" . ($lock ? " FOR UPDATE" : ""));
            $stmt->execute([$depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function paymentReport($runtimePdo, string $orderId): ?array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function parsePrincipal($value): int
    {
        if (is_int($value)) {
            return $value;
        }
        $s = trim((string) $value);
        if ($s === '' || !ctype_digit($s) || strlen($s) > 15) {
            return 0;
        }
        return (int) $s;
    }

    public static function allocate(PDO $pdo, string $orderId, int $principal, int $now): ?array
    {
        $scope = RX_SBV_SCOPE;
        $lo = ($principal + RX_SBV_FEE_MIN) * 10;
        $hi = ($principal + RX_SBV_FEE_MAX) * 10;
        $taken = [];
        $stmt = $pdo->prepare("SELECT payable_rial FROM card_sms_amount WHERE scope = ? AND payable_rial BETWEEN ? AND ? AND (reusable_after IS NULL OR reusable_after > ?)");
        $stmt->execute([$scope, $lo, $hi, $now]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rial) {
            $taken[(string) $rial] = true;
        }
        $fees = [];
        for ($fee = RX_SBV_FEE_MIN; $fee <= RX_SBV_FEE_MAX; $fee++) {
            if (!isset($taken[(string) (($principal + $fee) * 10)])) {
                $fees[] = $fee;
            }
        }
        for ($i = count($fees) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $fees[$i];
            $fees[$i] = $fees[$j];
            $fees[$j] = $tmp;
        }
        $insert = $pdo->prepare("INSERT INTO card_sms_amount (scope, payable_rial, id_order, reserved_at, reusable_after) VALUES (?, ?, ?, ?, NULL)");
        $reclaim = $pdo->prepare("UPDATE card_sms_amount SET id_order = ?, reserved_at = ?, reusable_after = NULL WHERE scope = ? AND payable_rial = ? AND reusable_after IS NOT NULL AND reusable_after <= ?");
        foreach ($fees as $fee) {
            $rial = ($principal + $fee) * 10;
            try {
                $insert->execute([$scope, $rial, $orderId, $now]);
                if ($insert->rowCount() === 1) {
                    return ['fee' => $fee, 'payable_rial' => $rial, 'scope' => $scope];
                }
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
            $reclaim->execute([$orderId, $now, $scope, $rial, $now]);
            if ($reclaim->rowCount() === 1) {
                return ['fee' => $fee, 'payable_rial' => $rial, 'scope' => $scope];
            }
        }
        return null;
    }

    public static function releaseAmount(PDO $pdo, array $csp, bool $immediate = false): void
    {
        $now = time();
        $after = $immediate ? $now : max($now, (int) $csp['expires_at']) + RX_SBV_REUSE_COOLDOWN;
        $pdo->prepare("UPDATE card_sms_amount SET reusable_after = ? WHERE scope = ? AND payable_rial = ? AND id_order = ? AND (reusable_after IS NULL OR reusable_after < ?)")
            ->execute([$after, (string) $csp['scope'], (int) $csp['payable_rial'], (string) $csp['id_order'], $after]);
    }

    public static function prepare($runtimePdo, string $orderId, $userId, $principalRaw): array
    {
        $principal = self::parsePrincipal($principalRaw);
        $userId = trim((string) $userId);
        if ($orderId === '' || strlen($orderId) > 191 || $userId === '' || $principal <= 0) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        if (!\Faoxima\Payments\Receipts\Settings::ensureSchema($runtimePdo)) {
            return ['ok' => false, 'reason' => 'schema_unavailable'];
        }
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        \Faoxima\Payments\Receipts\Recovery::sweep($runtimePdo);
        if (!self::userLock($runtimePdo, $userId)) {
            return ['ok' => false, 'reason' => 'busy'];
        }
        try {
            return self::prepareLocked($runtimePdo, $pdo, $orderId, $userId, $principal);
        } finally {
            self::userUnlock($runtimePdo, $userId);
        }
    }

    public static function prepareLocked($runtimePdo, PDO $pdo, string $orderId, string $userId, int $principal): array
    {
        $active = self::activePayment($runtimePdo, $userId);
        if ($active !== null) {
            return ['ok' => false, 'reason' => 'active_exists', 'active' => $active];
        }
        $now = time();
        try {
            $alloc = self::allocate($pdo, $orderId, $principal, $now);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_ALLOCATE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if ($alloc === null) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_AMOUNT_RANGE_EXHAUSTED', 'No free identification fee for principal', ['principal' => $principal]);
            return ['ok' => false, 'reason' => 'exhausted'];
        }
        $lifetime = \Faoxima\Payments\Receipts\Settings::lifetimeMinutes($runtimePdo) * 60;
        $fee = (int) $alloc['fee'];
        $row = [
            'id_order' => $orderId,
            'id_user' => $userId,
            'scope' => (string) $alloc['scope'],
            'principal_toman' => $principal,
            'fee_toman' => $fee,
            'payable_toman' => $principal + $fee,
            'payable_rial' => (int) $alloc['payable_rial'],
            'lifetime_sec' => $lifetime,
            'receipt_delay_sec' => RX_SBV_RECEIPT_DELAY,
            'created_at' => $now,
            'receipt_after' => $now + RX_SBV_RECEIPT_DELAY,
            'expires_at' => $now + $lifetime,
            'state' => 'open',
            'updated_at' => $now,
        ];
        try {
            $pdo->prepare(
                "INSERT INTO card_sms_payment (id_order, id_user, scope, principal_toman, fee_toman, payable_toman, payable_rial, lifetime_sec, receipt_delay_sec, created_at, receipt_after, expires_at, state, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute(array_values($row));
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_PREPARE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            try {
                $pdo->prepare("UPDATE card_sms_amount SET reusable_after = ? WHERE scope = ? AND payable_rial = ? AND id_order = ?")
                    ->execute([$now, (string) $alloc['scope'], (int) $alloc['payable_rial'], $orderId]);
            } catch (Throwable $e2) {
            }
            return ['ok' => false, 'reason' => 'db_error'];
        }
        return ['ok' => true, 'payment' => $row];
    }

    public static function abandon($runtimePdo, string $orderId): void
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || $orderId === '' || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return;
        }
        try {
            $csp = self::get($runtimePdo, $orderId);
            if ($csp === null) {
                return;
            }
            $pdo->prepare("UPDATE card_sms_payment SET state = 'void', resolution = 'not_created', updated_at = ? WHERE id_order = ? AND state = 'open' AND deposit_id IS NULL")
                ->execute([time(), $orderId]);
            self::releaseAmount($pdo, $csp, true);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_ABANDON_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }

    public static function findReusable($runtimePdo, $userId, string $invoiceKey, $principalRaw): ?array
    {
        $principal = self::parsePrincipal($principalRaw);
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || $principal <= 0 || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT csp.*, pr.source AS pr_source FROM card_sms_payment csp
                   JOIN Payment_report pr ON pr.id_order = csp.id_order
                  WHERE csp.id_user = ? AND csp.state = 'open' AND csp.deposit_id IS NULL AND csp.expires_at > ?
                    AND csp.principal_toman = ? AND pr.id_invoice = ? AND pr.payment_Status = 'Unpaid'
                    AND (pr.dec_not_confirmed IS NULL OR pr.dec_not_confirmed = '')
                    AND COALESCE(pr.direct_payment_done, 0) = 0
                    AND pr.Payment_Method = 'cart to cart'
                  ORDER BY csp.id DESC LIMIT 1"
            );
            $stmt->execute([trim((string) $userId), time() + 60, $principal, $invoiceKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REUSE_LOOKUP_FAILED', $e->getMessage(), ['id_user' => (string) $userId]);
            return null;
        }
    }

    public static function userLock($runtimePdo, string $userId, int $timeout = 5): bool
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT GET_LOCK(CONCAT('sbvu_', SHA1(CONCAT(DATABASE(), '|', ?))), ?)");
            $stmt->execute([$userId, $timeout]);
            return (int) $stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function userUnlock($runtimePdo, string $userId): void
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return;
        }
        try {
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(CONCAT('sbvu_', SHA1(CONCAT(DATABASE(), '|', ?))))");
            $stmt->execute([$userId]);
        } catch (Throwable $e) {
        }
    }

    public static function activePayment($runtimePdo, $userId): ?array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        $userId = trim((string) $userId);
        if ($pdo === null || $userId === '' || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT csp.*, pr.id AS pr_id, pr.payment_Status AS pr_status, pr.dec_not_confirmed AS pr_dec, pr.source AS pr_source,
                        pr.id_invoice AS pr_invoice, pr.report_message_id AS pr_report_msg, pr.private_receipt_targets AS pr_private,
                        pr.direct_payment_done AS pr_done
                   FROM card_sms_payment csp
                   LEFT JOIN Payment_report pr ON pr.id_order = csp.id_order
                  WHERE csp.id_user = ? AND csp.state IN ('open', 'matched', 'review')
                  ORDER BY csp.id DESC LIMIT 20"
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_ACTIVE_LOOKUP_FAILED', $e->getMessage(), ['id_user' => $userId]);
            return null;
        }
        $now = time();
        foreach ($rows as $row) {
            if ($row['pr_id'] === null) {
                if ((int) $row['created_at'] > $now - 120) {
                    $row['cancellable'] = false;
                    $row['resumable'] = false;
                    return $row;
                }
                continue;
            }
            $status = (string) $row['pr_status'];
            if ((int) ($row['pr_done'] ?? 0) === 1 || in_array($status, ['paid', 'reject', 'expire', 'cancelled'], true)) {
                continue;
            }
            $state = (string) $row['state'];
            $isActive = false;
            if ($state === 'matched' || $state === 'review') {
                $isActive = true;
            } elseif ($status === 'Unpaid') {
                $isActive = $now < (int) $row['expires_at'];
            } elseif (in_array($status, ['pending', 'waiting', 'processing', 'reconciling'], true)) {
                $isActive = true;
            }
            if (!$isActive) {
                continue;
            }
            $delivered = (int) ($row['pr_report_msg'] ?? 0) > 0 || trim((string) ($row['pr_private'] ?? '')) !== '';
            $row['resumable'] = $state === 'open' && $status === 'Unpaid' && $now < (int) $row['expires_at'];
            $row['cancellable'] = $state === 'open' && $row['deposit_id'] === null && $status === 'Unpaid'
                && trim((string) ($row['pr_dec'] ?? '')) === '' && !$delivered;
            return $row;
        }
        return null;
    }

    public static function pendingSummary(array $active): array
    {
        $status = (string) ($active['pr_status'] ?? '');
        $state = (string) ($active['state'] ?? '');
        if ($state === 'matched' || $state === 'review' || in_array($status, ['processing', 'reconciling'], true)) {
            $label = 'واریز شناسایی شده و در حال تأیید است';
        } elseif ($status === 'waiting' || $status === 'pending') {
            $label = 'رسید ارسال شده و در انتظار بررسی است';
        } else {
            $label = 'در انتظار واریز';
        }
        return [
            'order_id' => (string) $active['id_order'],
            'principal_toman' => (int) $active['principal_toman'],
            'fee_toman' => (int) $active['fee_toman'],
            'payable_toman' => (int) $active['payable_toman'],
            'payable_rial' => (int) $active['payable_rial'],
            'expires_at' => (int) $active['expires_at'],
            'receipt_after' => (int) $active['receipt_after'],
            'server_now' => time(),
            'status' => $status,
            'status_label' => $label,
            'resumable' => !empty($active['resumable']),
            'cancellable' => !empty($active['cancellable']),
        ];
    }

    public static function hasLivePayment($runtimePdo, $userId): bool
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM card_sms_payment WHERE id_user = ? AND state = 'open' AND deposit_id IS NULL AND expires_at > ?");
            $stmt->execute([trim((string) $userId), time()]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function cancelGuard($runtimePdo, PDO $pdo, string $orderId, string $reason, string $targetStatus): ?string
    {
        if (!\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$orderId]);
        $csp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($csp === false) {
            return null;
        }
        $now = time();
        if ((string) $csp['state'] !== 'open' || $csp['deposit_id'] !== null) {
            return 'verify_locked';
        }
        if (!in_array($reason, ['user_reset', 'user_cancel'], true) && $now < (int) $csp['expires_at'] + ($reason === 'timeout' ? RX_SBV_EXPIRE_GRACE : 0)) {
            return 'verify_window_active';
        }
        $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = ?, resolved_at = ?, updated_at = ? WHERE id = ? AND state = 'open'")
            ->execute([$targetStatus === 'expire' ? 'expired' : 'cancelled', $now, $now, (int) $csp['id']]);
        self::releaseAmount($pdo, $csp, false);
        return null;
    }

    public static function publicView($runtimePdo, string $orderId, ?array $csp = null): ?array
    {
        $csp = $csp ?? self::get($runtimePdo, $orderId);
        if ($csp === null) {
            return null;
        }
        $now = time();
        $created = (int) $csp['created_at'];
        $receiptAfter = (int) $csp['receipt_after'];
        $expires = (int) $csp['expires_at'];
        $state = (string) $csp['state'];
        $live = in_array($state, ['open', 'matched'], true);
        $receiptOpen = $state === 'open' && $now >= $receiptAfter && $now < $expires;
        if ($state === 'matched' || $state === 'review') {
            $verifyStatus = 'detected';
        } elseif (in_array($state, ['approved', 'credited'], true)) {
            $verifyStatus = 'approved';
        } elseif (!$live) {
            $verifyStatus = 'closed';
        } elseif ($now >= $expires) {
            $verifyStatus = 'expired';
        } else {
            $verifyStatus = 'waiting';
        }
        return [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'payable_toman' => (int) $csp['payable_toman'],
            'payable_rial' => (int) $csp['payable_rial'],
            'created_at' => $created,
            'receipt_after' => $receiptAfter,
            'expires_at' => $expires,
            'server_now' => $now,
            'remaining_sec' => max(0, $expires - $now),
            'receipt_wait_sec' => max(0, $receiptAfter - $now),
            'receipt_available' => $receiptOpen,
            'state' => $state,
            'verify_status' => $verifyStatus,
            'receipt_after_clock' => \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock($receiptAfter),
            'expires_at_clock' => \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock($expires),
        ];
    }

    public static function expiredFromValues(string $state, string $status, string $resolution, int $expiresAt, int $directDone = 0): bool
    {
        if ($status === 'paid' || $directDone === 1 || in_array($state, ['approved', 'credited'], true)) {
            return false;
        }
        if (in_array($status, ['reject', 'cancelled'], true)) {
            return false;
        }
        if ($state === 'closed' && in_array($resolution, ['rejected', 'rejected_blocked', 'cancelled', 'void', 'admin_credit', 'refunded'], true)) {
            return false;
        }
        if ($status === 'expire') {
            return true;
        }
        if ($state === 'closed') {
            return $resolution === 'expired';
        }
        if (in_array($state, ['open', 'review', 'matched'], true) && $expiresAt > 0 && $expiresAt < time()) {
            return in_array($status, ['', 'Unpaid', 'waiting', 'pending'], true);
        }
        return false;
    }

    public static function paymentIsExpired(array $csp, ?array $report = null): bool
    {
        $status = is_array($report) ? (string) ($report['payment_Status'] ?? '') : (string) ($csp['payment_Status'] ?? '');
        $directDone = (int) ($csp['direct_payment_done'] ?? (is_array($report) ? ($report['direct_payment_done'] ?? 0) : 0));
        return self::expiredFromValues(
            (string) ($csp['state'] ?? ''),
            $status,
            (string) ($csp['resolution'] ?? ''),
            (int) ($csp['expires_at'] ?? 0),
            $directDone
        );
    }

    public static function receiptGate($runtimePdo, string $orderId): array
    {
        $csp = self::get($runtimePdo, $orderId);
        if ($csp === null) {
            return ['ok' => true, 'verify' => false];
        }
        $now = time();
        $state = (string) $csp['state'];
        if (in_array($state, ['approved', 'credited'], true)) {
            return ['ok' => false, 'verify' => true, 'code' => 'already_paid', 'message' => '✅ این پرداخت قبلاً تأیید شده است.'];
        }
        if ($state === 'matched' || $state === 'review') {
            return ['ok' => false, 'verify' => true, 'code' => 'verifying', 'message' => '🔎 واریز شما شناسایی شده و در حال تأیید است؛ نیازی به ارسال رسید نیست.'];
        }
        if ($state !== 'open') {
            return ['ok' => false, 'verify' => true, 'code' => 'closed', 'message' => '❌ این پرداخت دیگر فعال نیست و امکان ارسال رسید برای آن وجود ندارد.'];
        }
        if ($now >= (int) $csp['expires_at']) {
            return ['ok' => false, 'verify' => true, 'code' => 'expired', 'message' => '⏳ مهلت این پرداخت به پایان رسیده است و امکان ارسال رسید وجود ندارد.'];
        }
        if ($now < (int) $csp['receipt_after']) {
            $wait = (int) $csp['receipt_after'] - $now;
            return [
                'ok' => false,
                'verify' => true,
                'code' => 'too_early',
                'available_at' => (int) $csp['receipt_after'],
                'wait_sec' => $wait,
                'message' => '⏳ تأیید خودکار واریز در حال انجام است. ارسال رسید از ساعت ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['receipt_after']) . ' (حدود ' . (int) ceil($wait / 60) . ' دقیقه دیگر) امکان‌پذیر است.',
            ];
        }
        return ['ok' => true, 'verify' => true];
    }
}
