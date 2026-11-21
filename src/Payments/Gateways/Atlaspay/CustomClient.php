<?php

namespace Faoxima\Payments\Gateways\Atlaspay;

use PDO;
use Throwable;

final class CustomClient
{
    public static function paymentMode()
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "atlaspay_payment_mode", "select");
        $mode = is_array($row) ? strtolower(trim((string) ($row['ValuePay'] ?? ''))) : '';
        return $mode === 'custom' ? 'custom' : 'official';
        }

    public static function saveSetting($runtimePdo, $name, $value)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $exists = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?");
            $exists->execute([(string) $name]);
            if ((int) $exists->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?");
            } else {
                $stmt = $pdo->prepare("INSERT INTO PaySetting (ValuePay, NamePay) VALUES (?, ?)");
            }
            $stmt->execute([(string) $value, (string) $name]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('PaySetting', 'NamePay', (string) $name);
            }
            return true;
        } catch (Throwable $e) {
            error_log('AtlasPay setting save failed: ' . $e->getMessage());
            return false;
        }
        }

    public static function webhookSecret()
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "atlaspay_webhook_secret", "select");
        return is_array($row) ? trim((string) ($row['ValuePay'] ?? '')) : '';
        }

    public static function webhookUrl($runtimeDomainhosts)
    {
        $domainhosts = $runtimeDomainhosts;
        return 'https://' . $domainhosts . '/payment/atlaspay.php';
        }

    public static function registerWebhook($runtimeDomainhosts, $runtimePdo)
    {
        $apiKey = \Faoxima\Payments\Gateways\Atlaspay\Client::apiKey();
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'کلید API اطلس‌پی ثبت نشده است.'];
        }
        $url = self::webhookUrl($runtimeDomainhosts);
        $current = \Faoxima\Payments\Gateways\Atlaspay\Client::curlJson('GET', ATLASPAY_BASE_URL . '/webhook', [], $apiKey);
        if (!is_array($current)) {
            return ['ok' => false, 'message' => 'ارتباط با اطلس‌پی برقرار نشد.'];
        }
        $statusCode = (int) ($current['status_code'] ?? 0);
        if ($statusCode < 200 || $statusCode >= 300) {
            return ['ok' => false, 'message' => 'دریافت وضعیت Webhook از اطلس‌پی ناموفق بود.'];
        }
        $data = isset($current['data']) && is_array($current['data']) ? $current['data'] : $current;
        $existing = isset($data['webhookUrl']) && is_scalar($data['webhookUrl']) ? trim((string) $data['webhookUrl']) : '';
        if ($existing !== '' && $existing !== $url) {
            return ['ok' => false, 'code' => 'foreign', 'message' => 'یک آدرس Webhook دیگر روی حساب اطلس‌پی ثبت شده و بازنویسی نشد؛ تأیید پرداخت‌ها فقط با استعلام دوره‌ای انجام می‌شود.'];
        }
        if ($existing === $url && self::webhookSecret() !== '') {
            return ['ok' => true, 'message' => 'Webhook از قبل ثبت شده است.'];
        }
        $registered = \Faoxima\Payments\Gateways\Atlaspay\Client::curlJson('POST', ATLASPAY_BASE_URL . '/webhook', ['webhookUrl' => $url], $apiKey);
        if (!is_array($registered)) {
            return ['ok' => false, 'message' => 'ثبت Webhook در اطلس‌پی ناموفق بود.'];
        }
        $registeredCode = (int) ($registered['status_code'] ?? 0);
        $registeredData = isset($registered['data']) && is_array($registered['data']) ? $registered['data'] : $registered;
        $secret = isset($registeredData['webhookSecret']) && is_scalar($registeredData['webhookSecret']) ? trim((string) $registeredData['webhookSecret']) : '';
        if ($registeredCode < 200 || $registeredCode >= 300 || $secret === '') {
            return ['ok' => false, 'message' => 'ثبت Webhook در اطلس‌پی ناموفق بود.'];
        }
        if (!self::saveSetting($runtimePdo, 'atlaspay_webhook_secret', $secret)) {
            return ['ok' => false, 'message' => 'ذخیره کلید امضای Webhook ناموفق بود.'];
        }
        return ['ok' => true, 'message' => 'Webhook با موفقیت ثبت شد.'];
        }

    public static function verifyWebhookSignature($rawBody, $signature)
    {
        $secret = self::webhookSecret();
        $signature = strtolower(trim((string) $signature));
        if (strpos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }
        if ($secret === '' || $signature === '') {
            return false;
        }
        $candidates = [hash_hmac('sha256', (string) $rawBody, $secret)];
        $decoded = json_decode((string) $rawBody, true);
        if (is_array($decoded)) {
            $candidates[] = hash_hmac('sha256', json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $secret);
        }
        foreach ($candidates as $candidate) {
            if (hash_equals($candidate, $signature)) {
                return true;
            }
        }
        return false;
        }

    public static function error($message, $code = '')
    {
        return ['success' => false, 'error' => (string) $message, 'error_code' => (string) $code];
        }

    public static function parseTime($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return 0;
        }
        $ts = strtotime($value);
        return $ts === false ? 0 : (int) $ts;
        }

    public static function validReceiptUrl($url)
    {
        $url = is_scalar($url) ? trim((string) $url) : '';
        if ($url === '' || strlen($url) > 500) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return '';
        }
        if (!in_array(strtolower((string) ($parts['host'] ?? '')), ['t.me', 'telegram.me'], true)) {
            return '';
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return '';
        }
        return $url;
        }

    public static function cardIsComplete($cardNumber)
    {
        $raw = (string) $cardNumber;
        if (preg_match('/^[\d\s\-]{16,23}$/', $raw) !== 1) {
            return false;
        }
        return strlen(preg_replace('/\D/', '', $raw)) === 16;
        }

    public static function createOrder($runtimePdo, $orderId, $amount, $telegramId)
    {
        $amount = (int) $amount;
        $telegramId = (int) $telegramId;
        $decoded = \Faoxima\Payments\Gateways\Atlaspay\Client::createOrder($runtimePdo, $orderId, $amount, $telegramId > 0 ? $telegramId : null);
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            return self::error(is_array($decoded) ? (string) ($decoded['error'] ?? 'پاسخ نامعتبر از سرویس اطلس‌پی') : 'پاسخ نامعتبر از سرویس اطلس‌پی');
        }
        $remoteId = trim((string) ($decoded['orderId'] ?? ''));
        $discard = static function () use ($remoteId) {
            if ($remoteId !== '' && is_callable([\Faoxima\Payments\Gateways\Atlaspay\Client::class, 'cancelOrder'])) {
                \Faoxima\Payments\Gateways\Atlaspay\Client::cancelOrder($remoteId);
            }
        };
        $total = $decoded['totalAmountToman'] ?? null;
        if (!is_numeric($total) || (float) $total != floor((float) $total) || (int) $total < $amount) {
            $discard();
            error_log('AtlasPay custom create order amount mismatch: ' . json_encode(['order_id' => (string) $orderId, 'requested' => $amount, 'total' => $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::error('مبلغ سفارش اطلس‌پی با مبلغ درخواستی مطابقت ندارد', 'AMOUNT_MISMATCH');
        }
        if (isset($decoded['baseAmountToman']) && is_numeric($decoded['baseAmountToman']) && (int) $decoded['baseAmountToman'] !== $amount) {
            $discard();
            error_log('AtlasPay custom create order base mismatch: ' . json_encode(['order_id' => (string) $orderId, 'requested' => $amount, 'base' => $decoded['baseAmountToman']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::error('مبلغ سفارش اطلس‌پی با مبلغ درخواستی مطابقت ندارد', 'AMOUNT_MISMATCH');
        }
        $link = self::validReceiptUrl($decoded['customerStartLink'] ?? '');
        if ($link === '') {
            $discard();
            return self::error('لینک رسمی اطلس‌پی معتبر نیست', 'INVALID_LINK');
        }
        $receiptLink = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayReceiptBotUrl($decoded['receiptBotLink'] ?? '');
        $card = isset($decoded['cardNumber']) && is_scalar($decoded['cardNumber']) ? substr(trim((string) $decoded['cardNumber']), 0, 40) : '';
        if (!self::cardIsComplete($card)) {
            $card = '';
        }
        $deadline = self::parseTime($decoded['paymentDeadlineAt'] ?? '');
        return [
            'orderId' => $remoteId,
            'trackingCode' => isset($decoded['trackingCode']) && is_scalar($decoded['trackingCode']) ? substr(trim((string) $decoded['trackingCode']), 0, 64) : '',
            'customerStartLink' => $link,
            'receiptBotLink' => $receiptLink,
            'totalAmountToman' => (int) $total,
            'baseAmountToman' => $amount,
            'cardNumber' => $card,
            'cardHolderName' => isset($decoded['cardHolderName']) && is_scalar($decoded['cardHolderName']) ? mb_substr(trim((string) $decoded['cardHolderName']), 0, 120) : '',
            'bankName' => isset($decoded['bankName']) && is_scalar($decoded['bankName']) ? mb_substr(trim((string) $decoded['bankName']), 0, 80) : '',
            'paymentDeadlineAt' => $deadline,
        ];
        }
}
