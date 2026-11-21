<?php

namespace Faoxima\Payments\Settlement;

use PDO;
use Throwable;
use RuntimeException;

final class Confirmation
{
    public static function recordPaid($runtimePdo, PDO $pdo, string $orderId): array
    {
        if (!$pdo->inTransaction()) {
            if (!\Faoxima\Payments\Fulfillment\Schema::ensureSchema($runtimePdo)) {
                return ['ok' => false, 'reason' => 'schema_unavailable'];
            }
        }
        $report = null;
        $transitioned = false;
        $wasDelivered = false;

        $own = !$pdo->inTransaction();
        $savepoint = 'rx_pc_' . bin2hex(random_bytes(4));
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }

            if (is_callable([\Faoxima\Payments\Fulfillment\Store::class, 'lockPayment'])) {
                $report = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $stmt->execute([$orderId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $report = $row === false ? null : $row;
            }
            if (!is_array($report)) {
                throw new RuntimeException('report_missing');
            }
            if (defined('RX_CRON_INIT_LOADED') && (!is_callable([\Faoxima\Payments\Fulfillment\PaymentPolicy::class, 'cronPaymentAllowed']) || !\Faoxima\Payments\Fulfillment\PaymentPolicy::cronPaymentAllowed($report))) {
                throw new RuntimeException('cron_window_elapsed');
            }
            $currentStatus = strtolower(trim((string)($report['payment_Status'] ?? '')));
            if ($currentStatus === 'cancelled') {
                throw new RuntimeException('cancelled');
            }

            $wasDelivered = (int)($report['direct_payment_done'] ?? 0) === 1;
            if ($wasDelivered && $currentStatus !== 'paid') {
                $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND COALESCE(direct_payment_done, 0) = 1")
                    ->execute([$orderId]);
            }
            if (!$wasDelivered) {
                if (defined('RX_CRON_INIT_LOADED') && $currentStatus === 'paid') {
                    $existing = $pdo->prepare('SELECT id FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE');
                    $existing->execute([$orderId]);
                    if ($existing->fetchColumn() === false) {
                        throw new RuntimeException('legacy_payment_requires_review');
                    }
                }
                $now = time();
                $atomic = $pdo->prepare(
                    "UPDATE Payment_report SET payment_Status = 'paid' "
                    . "WHERE id_order = ? AND LOWER(COALESCE(payment_Status, '')) <> 'cancelled' AND COALESCE(direct_payment_done, 0) = 0"
                );
                if (!$atomic->execute([$orderId])) {
                    throw new RuntimeException('paid_status_not_persisted');
                }
                $transitioned = $atomic->rowCount() > 0;

                $record = $pdo->prepare("SELECT id FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $record->execute([$orderId]);
                if ($record->fetchColumn() === false) {
                    $operationType = \Faoxima\Payments\Fulfillment\Claims::operationType($report['id_invoice'] ?? '');
                    $paymentId = isset($report['id']) && is_numeric($report['id']) ? (int)$report['id'] : null;
                    $ins = $pdo->prepare(
                        "INSERT INTO payment_fulfillment
                                (id_order, payment_report_id, operation_type, state, processing_token, lease_expires_at, attempt_count, created_at, updated_at)
                             VALUES (?, ?, ?, 'claimed', NULL, NULL, 0, ?, ?)"
                    );
                    if (!$ins->execute([$orderId, $paymentId, $operationType, $now, $now]) || $ins->rowCount() !== 1) {
                        throw new RuntimeException('delivery_claim_not_persisted');
                    }
                }
            }

            if ($own) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
        } catch (Throwable $e) {
            if ($own) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $rollbackError) {
                    }
                }
            } else {
                try {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                } catch (Throwable $rollbackError) {
                }
            }
            $reason = $e->getMessage();
            if ($reason === 'cancelled' || $reason === 'report_missing' || $reason === 'cron_window_elapsed' || $reason === 'legacy_payment_requires_review') {
                return ['ok' => false, 'reason' => $reason];
            }
            return ['ok' => false, 'reason' => 'db_error'];
        }

        return ['ok' => true, 'report' => $report, 'transitioned' => $transitioned, 'was_delivered' => $wasDelivered];
    }

    public static function attemptDelivery(string $orderId, array $report, bool $wasDelivered): array
    {
        $deliveryStatus = $wasDelivered ? 'already_completed' : 'unknown';
        $delivered = $wasDelivered;
        if (!$wasDelivered && method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'DirectPayment')) {
            $imagePath = (dirname(__DIR__, 3) . '/lib') . '/../images.jpeg';
            if (!is_file($imagePath)) {
                $imagePath = (dirname(__DIR__, 3) . '/lib') . '/../images.jpg';
            }
            try {
                $delivery = \Faoxima\Payments\Gateways\GatewayRuntime::DirectPayment($report['id_order'], $imagePath);
                if (is_array($delivery)) {
                    $deliveryStatus = (string)($delivery['status'] ?? 'unknown');
                    $delivered = is_callable([\Faoxima\Services\Fulfillment\Delivery::class, 'deliveryCompleted'])
                        ? \Faoxima\Services\Fulfillment\Delivery::deliveryCompleted($deliveryStatus)
                        : ($deliveryStatus === 'completed' || $deliveryStatus === 'already_completed');
                }
            } catch (Throwable $e) {
                $deliveryStatus = 'requires_reconciliation';
                if (function_exists('rx_log_event')) {
                    rx_log_event('PAYMENT_DELIVERY_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                }
            }
        }
        return [$deliveryStatus, $delivered];
    }

    public static function confirmPaid($runtimePdo, $runtimeSetting, string $orderId, string $cashbackKey, array $reportData = []): array
    {
        $pdo = $runtimePdo; $setting = $runtimeSetting;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo unavailable'];
        }

        $recorded = self::recordPaid($runtimePdo, $pdo, $orderId);
        if (!$recorded['ok']) {
            return $recorded;
        }
        $report = $recorded['report'];
        $transitioned = $recorded['transitioned'];
        $wasDelivered = $recorded['was_delivered'];

        if (!is_array($report)) {
            return ['ok' => false, 'reason' => 'report missing'];
        }
        if (is_callable([\Faoxima\Payments\Fulfillment\Store::class, 'clearCache'])) {
            \Faoxima\Payments\Fulfillment\Store::clearCache(['Payment_report']);
        } elseif (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
        }
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del') && isset($report['id_user'])) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $report['id_user']));
        }

        if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_record_verified_hash')) {
            $methodLower = strtolower((string)($report['Payment_Method'] ?? ''));
            if (in_array($methodLower, ['plisio', 'nowpayment', 'digitaltron', 'arze digital offline'], true)) {
                $srcMap = [
                    'plisio' => 'plisio_ipn',
                    'nowpayment' => 'nowpayment_ipn',
                    'digitaltron' => 'nowpayment_ipn',
                    'arze digital offline' => 'manual_admin',
                ];
                \Faoxima\Payments\Crypto\ManualCrypto::crypto_record_verified_hash($orderId, $srcMap[$methodLower] ?? 'gateway_ipn');
            }
        }

        [$deliveryStatus, $delivered] = self::attemptDelivery($orderId, $report, $wasDelivered);

        $userId = (string)($report['id_user'] ?? '');
        $balanceUser = \Faoxima\Core\Database\Db::select('user', '*', 'id', $userId, 'select');
        if (!is_array($balanceUser)) {
            $balanceUser = ['id' => $userId, 'username' => '—', 'Balance' => 0];
        }

        $cashbackAmount = $delivered
            ? \Faoxima\Payments\Settlement\Cashback::applyCashback($runtimePdo, $report, $balanceUser, $cashbackKey)
            : 0;
        if ($cashbackAmount > 0) {
            if (function_exists('sendmessage')) {
                sendmessage(
                    $balanceUser['id'],
                    "🎁 کاربر عزیز مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($cashbackAmount) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.",
                    null,
                    'HTML'
                );
            }
        }




        $method = (string)($reportData['method'] ?? ($report['Payment_Method'] ?? 'gateway'));
        $extraLines = $reportData['extra_lines'] ?? [];
        $linkLabel  = (string)($reportData['link_label'] ?? '');
        $linkUrl    = (string)($reportData['link_url']   ?? '');
        $rlm = "\xE2\x80\x8F";
        $usernameEsc = htmlspecialchars((string)($balanceUser['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $userIdEsc = htmlspecialchars((string)($balanceUser['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $textReport = "💵 پرداخت جدید\n" .
            "<blockquote>- 👤 نام کاربری کاربر : @{$usernameEsc}</blockquote>\n" .
            "<blockquote>- 👤 آیدی عددی کاربر : {$rlm}<code>{$userIdEsc}</code></blockquote>\n" .
            "<blockquote>- 💸 مبلغ تراکنش " . \Faoxima\Core\Support\Formatting::rxFormatToman($report['price'] ?? 0) . "</blockquote>\n";
        if ($linkLabel !== '' && $linkUrl !== '') {
            $textReport .= "<blockquote>- 🔗 <a href=\"{$linkUrl}\">{$linkLabel}</a></blockquote>\n";
        }
        foreach ((array)$extraLines as $line) {
            $textReport .= "<blockquote>- {$line}</blockquote>\n";
        }
        $textReport .= "<blockquote>- 💳 روش پرداخت : {$method}</blockquote>";

        $channelReport = is_array($setting ?? null) ? (string)($setting['Channel_Report'] ?? '') : '';
        if ($transitioned && $channelReport !== '' && function_exists('telegram')) {
            $payload = [
                'chat_id'    => $channelReport,
                'text'       => $textReport,
                'parse_mode' => 'HTML',
            ];
            if (!empty($reportData['thread_id'])) {
                $payload['message_thread_id'] = $reportData['thread_id'];
            }
            telegram('sendmessage', $payload);
        }

        return [
            'ok'              => $delivered,
            'verified'        => true,
            'delivered'       => $delivered,
            'delivery_status' => $deliveryStatus,
            'reason'          => $delivered ? '' : ('delivery_' . $deliveryStatus),
            'order_id'        => $report['id_order'],
            'user_id'         => $balanceUser['id'],
            'amount'          => (int)($report['price'] ?? 0),
            'cashback_amount' => $cashbackAmount,
        ];
    }

    public static function markUnsettledTerminal($pdo, string $orderId, string $target): ?array
    {
        $report = \Faoxima\Core\Database\Db::select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!is_array($report)) return null;
        $status = strtolower((string)($report['payment_Status'] ?? ''));
        if ($status === 'paid' || $status === 'cancelled' || !($pdo instanceof PDO)) return null;

        $stmt = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = ? "
            . "WHERE id_order = ? AND COALESCE(direct_payment_done, 0) = 0 "
            . "AND LOWER(COALESCE(payment_Status, '')) NOT IN ('paid', 'cancelled')"
        );
        $stmt->execute([$target, $orderId]);
        if ($stmt->rowCount() < 1) {
            return null;
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
        }
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del') && isset($report['id_user'])) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $report['id_user']));
        }
        return $report;
    }

    public static function markExpired($runtimePdo, string $orderId, ?string $textExpire = null): bool
    {
        $pdo = $runtimePdo;
        $report = self::markUnsettledTerminal($pdo, $orderId, 'expire');
        if ($report === null) return false;

        if (!function_exists('telegram')) return true;

        $priceFmt = number_format((int)($report['price'] ?? 0));
        $defaultText = "⏰ <b>پرداخت بعد از ۳۰ دقیقه تأیید نشد</b>\n\n"
                     . "🛒 کد فاکتور: <code>" . htmlspecialchars($orderId) . "</code>\n"
                     . "💸 مبلغ: <b>{$priceFmt}</b> تومان\n\n"
                     . "💡 اگر پرداخت کرده‌اید، ممکنه شبکه‌ی کریپتو هنوز confirm نکرده باشه. "
                     . "چند دقیقه دیگر صبر کنید (تا ۲۰ دقیقه برای TRX/TON معمولاً).\n"
                     . "اگر هنوز تأیید نشد، با پشتیبانی تماس بگیرید.";

        $text = ($textExpire !== null && trim($textExpire) !== '') ? $textExpire : $defaultText;
        $kb   = \Faoxima\Payments\Settlement\ConfirmationFunctions::rx_payment_retry_kb();
        telegram('sendmessage', array_filter([
            'chat_id'      => (string)$report['id_user'],
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $kb,
        ]));
        return true;
    }
}
