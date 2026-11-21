<?php

namespace Faoxima\Payments\Gateways\Tonpay;

use PDO;
use Throwable;
use CURLFile;

final class CustomClient
{
    public static function apiKey()
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "tonpay_custom_api_key", "select");
        return is_array($row) ? trim((string) ($row['ValuePay'] ?? '')) : '';
        }

    public static function saveApiKey($runtimePdo, $apiKey)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $exists = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?");
            $exists->execute(['tonpay_custom_api_key']);
            if ((int) $exists->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?");
            } else {
                $stmt = $pdo->prepare("INSERT INTO PaySetting (ValuePay, NamePay) VALUES (?, ?)");
            }
            $stmt->execute([trim((string) $apiKey), 'tonpay_custom_api_key']);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('PaySetting', 'NamePay', 'tonpay_custom_api_key');
            }
            return true;
        } catch (Throwable $e) {
            error_log('TonPay custom api key save failed: ' . $e->getMessage());
            return false;
        }
        }

    public static function error($code, $message, $statusCode = 0)
    {
        return [
            'success' => false,
            'error_code' => (string) $code,
            'error' => (string) $message,
            'status_code' => (int) $statusCode,
            'rate_limited' => (string) $code === 'RATE_LIMIT_EXCEEDED',
        ];
        }

    public static function normalizeInvoice($decoded)
    {
        if (!is_array($decoded)) {
            return null;
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
            return null;
        }
        $invoiceId = is_scalar($decoded['invoice_id'] ?? null) ? trim((string) $decoded['invoice_id']) : '';
        if ($invoiceId === '' || !is_numeric($decoded['request_amount'] ?? null) || !is_numeric($decoded['final_amount'] ?? null)) {
            return null;
        }
        $decoded['invoice_id'] = $invoiceId;
        $decoded['request_amount'] = (int) $decoded['request_amount'];
        $decoded['final_amount'] = (int) $decoded['final_amount'];
        $decoded['status'] = strtolower(trim(is_scalar($decoded['status'] ?? null) ? (string) $decoded['status'] : ''));
        $decoded['card_number'] = isset($decoded['card_number']) && is_scalar($decoded['card_number']) ? substr(trim((string) $decoded['card_number']), 0, 40) : '';
        $decoded['card_name'] = isset($decoded['card_name']) && is_scalar($decoded['card_name']) ? mb_substr(trim((string) $decoded['card_name']), 0, 120) : '';
        $decoded['paid'] = !empty($decoded['paid']);
        $decoded['receipt_received'] = !empty($decoded['receipt_received']);
        $decoded['remaining_amount'] = isset($decoded['remaining_amount']) && is_numeric($decoded['remaining_amount']) ? (int) $decoded['remaining_amount'] : null;
        $decoded['remaining_invoice_id'] = isset($decoded['remaining_invoice_id']) && is_scalar($decoded['remaining_invoice_id']) ? trim((string) $decoded['remaining_invoice_id']) : '';
        $decoded['remaining_card_number'] = isset($decoded['remaining_card_number']) && is_scalar($decoded['remaining_card_number']) ? substr(trim((string) $decoded['remaining_card_number']), 0, 40) : '';
        $decoded['remaining_card_holder'] = isset($decoded['remaining_card_holder']) && is_scalar($decoded['remaining_card_holder']) ? mb_substr(trim((string) $decoded['remaining_card_holder']), 0, 120) : '';
        $decoded['remaining_receipt_received'] = !empty($decoded['remaining_receipt_received']);
        $decoded['refund_amount'] = isset($decoded['refund_amount']) && is_numeric($decoded['refund_amount']) ? (int) $decoded['refund_amount'] : null;
        $decoded['refund_pending'] = !empty($decoded['refund_pending']);
        $decoded['show_change_card'] = array_key_exists('show_change_card', $decoded) && $decoded['show_change_card'] !== null ? (int) (bool) $decoded['show_change_card'] : null;
        $decoded['change_card_cooldown_seconds'] = isset($decoded['change_card_cooldown_seconds']) && is_numeric($decoded['change_card_cooldown_seconds']) ? max(0, (int) $decoded['change_card_cooldown_seconds']) : null;
        $decoded['change_card_exhausted'] = !empty($decoded['change_card_exhausted']);
        return $decoded;
        }

    public static function createInvoice($runtimeDomainhosts, $orderId, $amount, $buyerChatId)
    {
        $domainhosts = $runtimeDomainhosts;

        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return self::error('MISSING_API_KEY', 'کلید API درگاه کاستوم تون‌پی تنظیم نشده است');
        }
        $buyer = \Faoxima\Payments\Gateways\Tonpay\Client::normalizeBuyerChatId($buyerChatId);
        if ($buyer === null) {
            return self::error('INVALID_BUYER', 'شناسه تلگرام خریدار نامعتبر است');
        }
        $amount = (int) $amount;
        $payload = [
            'amount' => $amount,
            'order_id' => (string) $orderId,
            'buyer_chat_id' => $buyer,
            'callback_url' => 'https://' . $domainhosts . '/payment/tonpay.php',
        ];
        $decoded = \Faoxima\Payments\Gateways\Tonpay\Client::curlJson('POST', TONPAY_CUSTOM_BASE_URL . '/invoices/telegram/create', $payload, $apiKey);
        $invoice = self::normalizeInvoice($decoded);
        if ($invoice === null) {
            $error = \Faoxima\Payments\Gateways\Tonpay\Client::normalizeError($decoded, 'پاسخ نامعتبر از سرویس تون‌پی');
            error_log('TonPay custom create invoice failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'status_code' => $error['status_code'],
                'error_code' => $error['error_code'],
                'error' => $error['error'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $error;
        }
        if ($invoice['request_amount'] !== $amount || $invoice['final_amount'] < $amount) {
            $cancelResult = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::cancelConfirmedPending($invoice['invoice_id']);
            error_log('TonPay custom create invoice amount mismatch: ' . json_encode([
                'order_id' => (string) $orderId,
                'invoice_id' => $invoice['invoice_id'],
                'requested' => $amount,
                'request_amount' => $invoice['request_amount'],
                'final_amount' => $invoice['final_amount'],
                'remote_cancelled' => !empty($cancelResult['confirmed']),
                'remote_status' => $cancelResult['status'] ?? '',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $error = self::error('AMOUNT_MISMATCH', 'مبلغ فاکتور تون‌پی با مبلغ درخواستی مطابقت ندارد', (int) ($decoded['status_code'] ?? 0));
            $error['invoice_id'] = $invoice['invoice_id'];
            return $error;
        }
        return $invoice;
        }

    public static function checkInvoice($invoiceId)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }
        return \Faoxima\Payments\Gateways\Tonpay\Client::curlJson('GET', TONPAY_CUSTOM_BASE_URL . '/invoices/check/' . rawurlencode((string) $invoiceId), [], $apiKey);
        }

    public static function changeCardRequest($invoiceId)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }
        return \Faoxima\Payments\Gateways\Tonpay\Client::curlJson('POST', TONPAY_CUSTOM_BASE_URL . '/invoices/' . rawurlencode((string) $invoiceId) . '/change-card', [], $apiKey);
        }

    public static function cancelRequest($invoiceId)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }
        return \Faoxima\Payments\Gateways\Tonpay\Client::curlJson('POST', TONPAY_CUSTOM_BASE_URL . '/invoices/' . rawurlencode((string) $invoiceId) . '/cancel', [], $apiKey, false, true);
        }

    public static function refundRequest($invoiceId, $cardNumber, $cardHolder)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }
        return \Faoxima\Payments\Gateways\Tonpay\Client::curlJson(
            'POST',
            TONPAY_CUSTOM_BASE_URL . '/invoices/' . rawurlencode((string) $invoiceId) . '/refund',
            [
                'invoice_id' => (string) $invoiceId,
                'card_number' => (string) $cardNumber,
                'card_holder' => (string) $cardHolder,
            ],
            $apiKey
        );
        }

    public static function receiptRequest($invoiceId, $filePath, $mime, $fileName)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }
        return \Faoxima\Payments\Gateways\Tonpay\Client::curlJson(
            'POST',
            TONPAY_CUSTOM_BASE_URL . '/invoices/' . rawurlencode((string) $invoiceId) . '/receipt',
            ['file' => new CURLFile($filePath, $mime, $fileName)],
            $apiKey,
            true
        );
        }
}
