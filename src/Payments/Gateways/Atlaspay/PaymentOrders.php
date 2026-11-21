<?php

namespace Faoxima\Payments\Gateways\Atlaspay;

use PDO;
use Throwable;

final class PaymentOrders
{
    public static function fetchReport($runtimePdo, $orderId)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
              WHERE id_order = ? AND Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'
              LIMIT 1"
            );
            $stmt->execute([(string) $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            error_log('AtlasPay custom fetch report failed: ' . $e->getMessage());
            return null;
        }
        }

    public static function storeOrder($runtimePdo, $orderId, array $order)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $now = time();
        $deadline = (int) $order['paymentDeadlineAt'];
        if ($deadline <= $now) {
            $deadline = $now + ATLASPAY_CUSTOM_DEADLINE_SECONDS;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET atlaspay_gateway_mode = 'custom',
                    atlaspay_order_id = ?,
                    atlaspay_tracking_code = ?,
                    atlaspay_payment_url = ?,
                    atlaspay_receipt_url = ?,
                    atlaspay_total_amount = ?,
                    atlaspay_card_number = ?,
                    atlaspay_card_name = ?,
                    atlaspay_bank_name = ?,
                    atlaspay_remote_status = 'awaiting_payment',
                    atlaspay_created_at = ?,
                    atlaspay_deadline_at = ?,
                    atlaspay_local_state = 'awaiting_payment',
                    atlaspay_last_checked_at = ?
              WHERE id_order = ?"
            );
            $stmt->execute([
                $order['orderId'],
                $order['trackingCode'],
                $order['customerStartLink'],
                $order['receiptBotLink'] ?? '',
                $order['totalAmountToman'],
                $order['cardNumber'],
                $order['cardHolderName'],
                $order['bankName'],
                $now,
                $deadline,
                $now,
                (string) $orderId,
            ]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('AtlasPay custom store order failed: ' . json_encode(['order_id' => (string) $orderId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function updateColumns($runtimePdo, $orderId, array $columns, $onlyOpen = true)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || empty($columns)) {
            return 0;
        }
        $sets = [];
        $params = [];
        foreach ($columns as $name => $value) {
            $sets[] = $name . ' = ?';
            $params[] = $value;
        }
        $params[] = (string) $orderId;
        $sql = "UPDATE Payment_report SET " . implode(', ', $sets) . " WHERE id_order = ? AND atlaspay_gateway_mode = 'custom'";
        if ($onlyOpen) {
            $sql .= " AND payment_Status IN ('Unpaid','pending','waiting')";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
                $owner = \Faoxima\Core\Database\Db::select('Payment_report', 'id_user', 'id_order', (string) $orderId, 'select');
                if (is_array($owner)) {
                    \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, ($owner['id_user'] ?? '')));
                }
            }
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('AtlasPay custom update failed: ' . json_encode(['order_id' => (string) $orderId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return 0;
        }
        }

    public static function transition($runtimePdo, $orderId, array $fromStatuses, $toStatus, $localState, array $extra = [])
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || empty($fromStatuses)) {
            return false;
        }
        $sets = ['payment_Status = ?', 'atlaspay_local_state = ?'];
        $params = [(string) $toStatus, (string) $localState];
        foreach ($extra as $name => $value) {
            $sets[] = $name . ' = ?';
            $params[] = $value;
        }
        $params[] = (string) $orderId;
        foreach ($fromStatuses as $status) {
            $params[] = (string) $status;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report SET " . implode(', ', $sets)
                . " WHERE id_order = ? AND atlaspay_gateway_mode = 'custom' AND payment_Status IN (" . implode(',', array_fill(0, count($fromStatuses), '?')) . ")"
            );
            $stmt->execute($params);
            $changed = $stmt->rowCount() > 0;
            if ($changed) {
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
                }
                if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
                    $owner = \Faoxima\Core\Database\Db::select('Payment_report', 'id_user', 'id_order', (string) $orderId, 'select');
                    if (is_array($owner)) {
                        \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, ($owner['id_user'] ?? '')));
                    }
                }
            }
            return $changed;
        } catch (Throwable $e) {
            error_log('AtlasPay custom transition failed: ' . json_encode(['order_id' => (string) $orderId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function claimLocalState($runtimePdo, $orderId, $state, array $statuses)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || empty($statuses)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET atlaspay_local_state = ?
              WHERE id_order = ?
                AND atlaspay_gateway_mode = 'custom'
                AND payment_Status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ")
                AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> ?)"
            );
            $stmt->execute(array_merge([(string) $state, (string) $orderId], array_map('strval', $statuses), [(string) $state]));
            $changed = $stmt->rowCount() > 0;
            if ($changed && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $changed;
        } catch (Throwable $e) {
            error_log('AtlasPay custom claim state failed: ' . json_encode(['order_id' => (string) $orderId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function purchaseKind($invoiceField)
    {
        $tag = strpos((string) $invoiceField, '|') !== false ? trim((string) explode('|', (string) $invoiceField, 2)[0]) : '';
        $kinds = [
            'getconfigafterpay' => 'خرید سرویس',
            'getextenduser' => 'تمدید سرویس',
            'getextratimeuser' => 'خرید زمان اضافه',
            'getextravolumeuser' => 'خرید حجم اضافه',
        ];
        return $kinds[$tag] ?? 'شارژ کیف پول';
        }

    public static function gatewayTitle()
    {
        $stored = method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get') ? (string) \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('atlaspay', '') : '';
        $name = preg_replace('/^[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+|[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+$/u', '', $stored);
        if (!is_string($name) || $name === '' || $name === '🌐 اطلس‌پی') {
            return ATLASPAY_CUSTOM_DEFAULT_TITLE;
        }
        $stripped = preg_replace('/^(?:💠|🌐)\s*/u', '', $name);
        $name = is_string($stripped) && $stripped !== '' ? $stripped : $name;
        return mb_substr($name, 0, 120);
        }

    public static function openPhases()
    {
        return ['awaiting_payment', 'deadline_check', 'under_review', 'underpaid_review', 'underpaid_remainder'];
        }

    public static function phaseDetail($phase, $reason = '')
    {
        $details = [
            'awaiting_payment' => '📌 پرداخت شما به‌صورت خودکار بررسی می‌شود.',
            'deadline_check' => '⏱ مهلت پرداخت به پایان رسید و وضعیت نهایی سفارش در اطلس‌پی در حال بررسی است. سفارش منقضی‌شده رسید جدید نمی‌پذیرد؛ اگر مبلغ را واریز کرده‌اید، با پشتیبانی فروشگاه تماس بگیرید.',
            'under_review' => '🔎 رسید شما در اطلس‌پی در حال بررسی است. پس از تأیید، پرداخت به‌صورت خودکار ثبت می‌شود.',
            'underpaid_review' => '⚠️ مبلغ واریزی کمتر از مبلغ سفارش تشخیص داده شده و مغایرت در حال بررسی است.',
            'underpaid_remainder' => '⚠️ مبلغ واریزی کمتر از مبلغ سفارش بوده است. برای تکمیل مبلغ باقی‌مانده یا تصمیم نهایی، با پشتیبانی فروشگاه در ارتباط باشید.',
            'confirming' => '✅ پرداخت شما تأیید شد و در حال نهایی‌سازی است.',
            'manual_review' => '⚠️ پرداخت شما با مبلغ کمتر از مبلغ کامل تأیید شد و برای تصمیم نهایی به مدیریت ارجاع شد. لطفاً با پشتیبانی در ارتباط باشید.',
            'paid' => '✅ پرداخت شما با موفقیت تأیید شد.',
            'rejected' => '❌ پرداخت شما توسط اطلس‌پی رد شد.' . ($reason !== '' ? "\n" . $reason : '') . "\nاگر مبلغ را واریز کرده‌اید، با پشتیبانی تماس بگیرید.",
            'expired' => '⏰ مهلت پرداخت این فاکتور به پایان رسید و سفارش منقضی شده است؛ برای سفارش منقضی‌شده رسید پذیرفته نمی‌شود. اگر مبلغ را واریز کرده‌اید، با پشتیبانی فروشگاه تماس بگیرید تا از گزارش واریز دیرهنگام استفاده شود.',
            'canceled' => '🚫 این تراکنش لغو شد. لغو فاکتور به‌معنی بازگشت وجه واریزشده نیست.',
        ];
        return $details[$phase] ?? '';
        }

    public static function buildState(array $report, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $price = (int) ($report['price'] ?? 0);
        $total = (int) ($report['atlaspay_total_amount'] ?? 0);
        if ($total < $price) {
            $total = $price;
        }
        $createdAt = (int) ($report['atlaspay_created_at'] ?? 0);
        $deadlineAt = (int) ($report['atlaspay_deadline_at'] ?? 0);
        if ($deadlineAt <= 0 && $createdAt > 0) {
            $deadlineAt = $createdAt + ATLASPAY_CUSTOM_DEADLINE_SECONDS;
        }
        $remote = strtolower(trim((string) ($report['atlaspay_remote_status'] ?? '')));
        $localStatus = (string) ($report['payment_Status'] ?? '');
        $manual = !empty($report['atlaspay_manual_delivery']);
        $card = (string) ($report['atlaspay_card_number'] ?? '');

        if ($localStatus === 'paid') {
            $phase = 'paid';
        } elseif ($localStatus === 'cancelled' || $remote === 'cancelled') {
            $phase = 'canceled';
        } elseif (in_array($remote, ['confirmed', 'settled'], true)) {
            $phase = $manual ? 'manual_review' : 'confirming';
        } elseif ($remote === 'rejected') {
            $phase = 'rejected';
        } elseif ($remote === 'expired') {
            $phase = 'expired';
        } elseif ($remote === 'admin_review') {
            $phase = 'under_review';
        } elseif ($remote === 'underpaid_review') {
            $phase = 'underpaid_review';
        } elseif ($remote === 'underpaid_awaiting_remainder') {
            $phase = 'underpaid_remainder';
        } elseif ($localStatus === 'reject') {
            $phase = 'rejected';
        } elseif ($localStatus === 'expire') {
            $phase = 'expired';
        } elseif ($deadlineAt > 0 && $deadlineAt <= $now) {
            $phase = 'deadline_check';
        } else {
            $phase = 'awaiting_payment';
        }

        $open = in_array($phase, self::openPhases(), true);
        $paymentLink = \Faoxima\Payments\Gateways\Atlaspay\CustomClient::validReceiptUrl($report['atlaspay_payment_url'] ?? '');
        $receiptLink = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayReceiptBotUrl($report['atlaspay_receipt_url'] ?? '');
        $receiptAvailable = $open && $phase === 'awaiting_payment';
        $cardVisible = $phase === 'awaiting_payment';

        return [
            'order_id' => (string) ($report['id_order'] ?? ''),
            'tracking_code' => (string) (($report['atlaspay_tracking_code'] ?? '') !== '' ? $report['atlaspay_tracking_code'] : ($report['id_order'] ?? '')),
            'purchase_kind' => self::purchaseKind((string) ($report['id_invoice'] ?? '')),
            'phase' => $phase,
            'phase_label' => \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomPhaseLabel($phase),
            'remote_status' => $remote,
            'now' => $now,
            'request_amount' => $price,
            'total_amount' => $total,
            'payable_rial' => $total * 10,
            'card_number' => $cardVisible ? $card : '',
            'card_name' => $cardVisible ? (string) ($report['atlaspay_card_name'] ?? '') : '',
            'bank_name' => $cardVisible ? (string) ($report['atlaspay_bank_name'] ?? '') : '',
            'card_complete' => $cardVisible && \Faoxima\Payments\Gateways\Atlaspay\CustomClient::cardIsComplete($card),
            'created_at' => $createdAt,
            'deadline_at' => $deadlineAt,
            'remaining' => $deadlineAt > 0 ? max(0, $deadlineAt - $now) : 0,
            'reason' => trim((string) ($report['dec_not_confirmed'] ?? '')),
            'payment_url' => $paymentLink,
            'receipt_url' => $receiptAvailable ? $receiptLink : '',
            'can_cancel' => in_array($phase, ['awaiting_payment', 'deadline_check'], true),
            'terminal' => in_array($phase, ['paid', 'rejected', 'canceled', 'expired'], true),
            'open' => $open,
        ];
        }

    public static function publicView(array $report)
    {
        $state = self::buildState($report);
        $digits = preg_replace('/\D/', '', $state['card_number']);
        $state['card_number_display'] = $state['card_complete'] ? trim(chunk_split($digits, 4, ' ')) : '';
        $state['card_copy_value'] = $state['card_complete'] ? $digits : '';
        $state['amount_copy_value'] = (string) $state['payable_rial'];
        $state['wallet_credit'] = $state['request_amount'];
        $reason = $state['phase'] === 'rejected' ? $state['reason'] : '';
        $state['messages'] = [
            'status' => self::phaseDetail($state['phase'], $reason),
            'amount_warning' => sprintf(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomAmountWarningTemplate(), 'کپی مبلغ'),
            'card_only_warning' => \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCardOnlyWarning(),
            'receipt' => $state['receipt_url'] !== '' ? \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayReceiptInstructions() : '',
        ];
        $state['button_styles'] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayButtonStyleMap();
        $state['gateway_title'] = self::gatewayTitle();
        unset($state['reason']);
        return $state;
        }

    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
}
