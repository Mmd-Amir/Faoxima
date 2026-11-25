<?php

namespace Faoxima\Payments\Settlement;

use PDO;
use Throwable;

final class PendingInvoices
{
    public static function formatPaymentReportNote($rawNote)
    {
        if ($rawNote === null) {
            return '';
        }

        if (is_array($rawNote)) {
            return json_encode($rawNote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!is_scalar($rawNote)) {
            return '';
        }

        $rawNote = trim((string) $rawNote);
        if ($rawNote === '') {
            return '';
        }

        $decoded = json_decode($rawNote, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (($decoded['gateway'] ?? '') === 'zarinpay') {
                $lines = ['زرین‌پی'];
                $fieldMap = [
                    'payment_id' => 'شناسه پرداخت',
                    'reference_id' => 'شماره پیگیری',
                    'authority' => 'کد اعتبار',
                    'order_id' => 'کد سفارش',
                    'code' => 'کد تأیید',
                ];

                foreach ($fieldMap as $key => $label) {
                    $value = $decoded[$key] ?? null;
                    if ($value !== null && $value !== '') {
                        $lines[] = sprintf('%s: %s', $label, $value);
                    }
                }

                if (!empty($decoded['amount'])) {
                    $lines[] = 'مبلغ تراکنش (ریال): ' . number_format((int) $decoded['amount']);
                }

                if (!empty($decoded['card_pan'])) {
                    $lines[] = 'کارت پرداخت‌کننده: ' . $decoded['card_pan'];
                }

                if (!empty($decoded['paid_at'])) {
                    $lines[] = 'زمان پرداخت: ' . $decoded['paid_at'];
                }

                return implode("\n", array_filter($lines));
            }

            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $rawNote;
    }

    public static function deleteInvoiceFromList($invoiceId, $userId = null)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $sql = 'DELETE FROM invoice WHERE id_invoice = :invoice_id';
        $params = [':invoice_id' => $invoiceId];
        if ($userId !== null) {
            $sql .= ' AND id_user = :user_id';
            $params[':user_id'] = $userId;
        }
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        \Faoxima\Core\Database\SelectCache::clearSelectCache('invoice');
        return $result;
    }

    public static function rx_gateway_payment_method_map()
    {
        return [
            'zarinpal'    => 'zarinpal',
            'plisio'      => 'plisio',
            'nowpayment'  => 'nowpayment',
            'iranpay2'    => 'Currency Rial 2',
            'tonpay'      => 'tonpay',
            'rialit'      => 'rialit',
            'tetraminator' => 'tetraminator',
            'blupal'      => 'blupal',
            'atlaspay'    => 'atlaspay',
            'cubepay'     => 'cubepay',
            'variza'      => 'variza',
            'abangateway' => 'abangateway',
        ];
    }

    public static function rx_gateway_stale_minutes($paymentMethod)
    {
        if ($paymentMethod === 'tonpay') {
            return 1440;
        }
        if ($paymentMethod === 'rialit') {
            return 1440;
        }
        if ($paymentMethod === 'tetraminator') {
            return 1440;
        }
        return 30;
    }

    public static function rx_purge_stale_gateway_invoices($userId, $paymentMethod)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        if ($paymentMethod === 'rialit' || $paymentMethod === 'tetraminator') {
            return;
        }
        try {
            $rows = $pdo->prepare(
                "SELECT id_order, time FROM Payment_report
                  WHERE id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')"
            );
            $rows->execute([':u' => (string) $userId, ':m' => $paymentMethod]);
            $result = $rows->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[rx_purge_stale_gateway_invoices] fetch failed: ' . $e->getMessage());
            return;
        }
        if (empty($result)) {
            return;
        }

        $cutoff = time() - (self::rx_gateway_stale_minutes($paymentMethod) * 60);
        $stale = [];
        foreach ($result as $row) {
            $ts = \Faoxima\Core\Support\Formatting::isValidDate((string) ($row['time'] ?? '')) ? strtotime(str_replace('/', '-', (string) $row['time'])) : false;
            if ($ts === false || $ts <= $cutoff) {
                $stale[] = (string) $row['id_order'];
            }
        }
        if ($paymentMethod === 'tonpay' && method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomPurgeProtected')) {
            $stale = array_values(array_filter($stale, static function ($staleOrderId) {
                return !\Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomPurgeProtected($staleOrderId);
            }));
        }
        if ($paymentMethod === 'atlaspay' && method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayCustomPurgeProtected')) {
            $stale = array_values(array_filter($stale, static function ($staleOrderId) {
                return !\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomPurgeProtected($staleOrderId);
            }));
        }
        if (empty($stale)) {
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($stale), '?'));
            $sql = "UPDATE Payment_report
                       SET payment_Status = 'expire'
                     WHERE id_user = ?
                       AND Payment_Method = ?
                       AND payment_Status IN ('Unpaid','pending','waiting')
                       AND id_order IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([(string) $userId, $paymentMethod], $stale));
        } catch (Throwable $e) {
            error_log('[rx_purge_stale_gateway_invoices] update failed: ' . $e->getMessage());
        }
    }

    public static function findPendingGatewayInvoice($userId, $paymentMethod)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }

        self::rx_purge_stale_gateway_invoices($userId, $paymentMethod);

        try {
            $stmt = $pdo->prepare(
                "SELECT id_order, Payment_Method, payment_Status, price, time
                   FROM Payment_report
                  WHERE id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')
                  ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([':u' => (string) $userId, ':m' => $paymentMethod]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[findPendingGatewayInvoice] query failed: ' . $e->getMessage());
            return null;
        }

        return is_array($row) ? $row : null;
    }

    public static function cancelPendingGatewayInvoice($userId, $paymentMethod, $orderId)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }

        if ($paymentMethod === 'rialit' || $paymentMethod === 'tetraminator') {
            return false;
        }

        try {
            $report = $pdo->prepare(
                "SELECT id_order, Payment_Method, payment_Status, atlaspay_order_id
                   FROM Payment_report
                  WHERE id_order = :o
                    AND id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')
                  LIMIT 1"
            );
            $report->execute([':o' => $orderId, ':u' => (string) $userId, ':m' => $paymentMethod]);
            $row = $report->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[cancelPendingGatewayInvoice] lookup failed: ' . $e->getMessage());
            return false;
        }
        if (!is_array($row)) {
            return false;
        }

        if ($paymentMethod === 'tonpay' && method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomFetchReport')) {
            $customReport = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomFetchReport($orderId);
            if ($customReport !== null) {
                $customResult = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancel($orderId, (string) $userId);
                return !empty($customResult['ok']);
            }
        }

        if ($paymentMethod === 'atlaspay' && method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayCustomFetchReport')) {
            $customReport = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomFetchReport($orderId);
            if ($customReport !== null) {
                $customResult = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCancel($orderId, (string) $userId);
                return !empty($customResult['ok']);
            }
        }

        if ($paymentMethod === 'atlaspay' && is_callable([\Faoxima\Payments\Gateways\Atlaspay\Client::class, 'cancelOrder'])) {
            $atlaspayOrderId = trim((string) ($row['atlaspay_order_id'] ?? ''));
            if ($atlaspayOrderId !== '') {
                try {
                    \Faoxima\Payments\Gateways\Atlaspay\Client::cancelOrder($atlaspayOrderId);
                } catch (Throwable $e) {
                    error_log('[cancelPendingGatewayInvoice] atlaspayCancelOrder failed: ' . $e->getMessage());
                }
            }
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'cancelled'
                  WHERE id_order = :o
                    AND id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')"
            );
            $stmt->execute([':o' => $orderId, ':u' => (string) $userId, ':m' => $paymentMethod]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[cancelPendingGatewayInvoice] update failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function rx_gateway_display_name($gatewayKey)
    {
        $labels = [
            'zarinpal'    => 'زرین‌پال',
            'plisio'      => 'Plisio',
            'nowpayment'  => 'NowPayments',
            'iranpay2'    => 'ایران‌پی',
            'tonpay'      => 'تون‌پی',
            'rialit'      => '💚 ریالیت',
            'tetraminator' => '💎 تترامیناتور',
            'blupal'      => 'بلوپال',
            'atlaspay'    => 'اطلس‌پی',
            'cubepay'     => 'کیوب‌پی',
            'variza'      => 'واریزا',
            'abangateway' => 'آبان گیت‌وی',
        ];
        return $labels[$gatewayKey] ?? $gatewayKey;
    }

    public static function rx_send_pending_gateway_invoice_notice($from_id, $message_id, array $pending, $gatewayKey)
    {
        global $datatextbot;

        $orderId = (string) ($pending['id_order'] ?? '');
        $priceFmt = number_format((int) ($pending['price'] ?? 0));
        $text = sprintf(
            $datatextbot['dyn_pending_gateway_invoice_notice'] ?? "⏳ شما یک فاکتور پرداخت‌نشده در همین درگاه دارید.\n\n🛒 کد فاکتور: <code>%s</code>\n💰 مبلغ: %s تومان\n\nمی‌توانید منتظر بررسی همین فاکتور بمانید یا آن را لغو کرده و فاکتور جدید بسازید.",
            htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8'),
            $priceFmt
        );
        $kb = json_encode([
            'inline_keyboard' => [
                [rx_payment_button((string) $gatewayKey, 'pending_cancel', ['text' => '♻️ لغو و دریافت فاکتور جدید', 'callback_data' => 'rxpgw_cancel_' . $gatewayKey . '_' . $orderId])],
                [rx_payment_button((string) $gatewayKey, 'back_main', ['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser'])],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if (intval($message_id) > 0) {
            Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
        } else {
            sendmessage($from_id, $text, $kb, 'HTML');
        }
    }

    public static function updatePaymentMessageId($response, $orderId)
    {
        if (!is_array($response)) {
            error_log("Failed to send payment message for order {$orderId}: unexpected response");
            return false;
        }

        if (empty($response['ok'])) {
            error_log("Failed to send payment message for order {$orderId}: " . json_encode($response));
            return false;
        }

        if (!isset($response['result']['message_id'])) {
            error_log("Missing message_id for order {$orderId}: " . json_encode($response));
            return false;
        }

        \Faoxima\Core\Database\Db::update("Payment_report", "message_id", intval($response['result']['message_id']), "id_order", $orderId);
        return true;
    }
}
