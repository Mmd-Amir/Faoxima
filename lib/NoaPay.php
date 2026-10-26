<?php

// This module has no bootstrap side effects; callers supply the existing DB connection.
function noapaySetting(string $name, string $default = ''): string
{
    $row = select('PaySetting', 'ValuePay', 'NamePay', $name, 'select');
    return is_array($row) ? (string) ($row['ValuePay'] ?? $default) : $default;
}

function noapayIsReady(): bool
{
    return noapaySetting('statusnoapay') === 'onnoapay'
        && !in_array(trim(noapaySetting('apinoapay')), ['', '0'], true)
        && !in_array(trim(noapaySetting('noapay_webhook_secret')), ['', '0'], true);
}

function noapayFeeOptions(string $mode): array
{
    return ['fee_on_user' => $mode === 'buyer', 'split_fee' => $mode === 'split'];
}

function noapayQuantity(int $amount): int
{
    if ($amount <= 0) {
        throw new InvalidArgumentException('Invalid NoaPay amount');
    }
    return intdiv($amount, 1000) + ($amount % 1000 === 0 ? 0 : 1);
}

function noapayPaymentUrl(array $invoice): string
{
    $field = ['miniapp' => 'miniapp_url', 'web' => 'web_url'][$invoice['payment_channel'] ?? 'bot'] ?? 'payment_url';
    $url = $invoice[$field] ?? $invoice['payment_url'] ?? '';
    return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
        && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : '';
}

function noapayValidSignature(string $body, string $signature, string $secret): bool
{
    return $secret !== '' && $secret !== '0' && preg_match('/^[a-f0-9]{64}$/D', $signature) === 1
        && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
}

function noapayRequest(string $method, string $path, ?array $payload = null): array
{
    $base = rtrim(trim(noapaySetting('urlnoapay', 'http://noapay.mirname.xyz')), '/');
    $key = trim(noapaySetting('apinoapay'));
    if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
        || $key === '' || $key === '0' || preg_match('/[\r\n]/', $key)) {
        return ['success' => false, 'message' => 'تنظیمات اتصال NoaPay کامل نیست.'];
    }
    $curl = curl_init($base . '/api/v1/' . ltrim($path, '/'));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
    $raw = curl_exec($curl);
    $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $response = is_string($raw) ? json_decode($raw, true) : null;
    if ($http < 200 || $http >= 300 || !is_array($response) || ($response['success'] ?? null) !== true || !is_array($response['data'] ?? null)) {
        // Do not expose provider messages or bodies, which may contain secrets or HTML.
        return ['success' => false, 'message' => 'ساخت یا بررسی فاکتور NoaPay ناموفق بود؛ کمی بعد دوباره تلاش کنید.'];
    }
    return ['success' => true, 'data' => $response['data']];
}

function noapayCreatePayment($orderId, int $amount, $userId): array
{
    global $pdo, $domainhosts;
    if (!noapayIsReady() || !($pdo instanceof PDO) || !preg_match('/^[1-9][0-9]{0,18}$/D', (string) $userId)
        || $amount <= 0 || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', (string) $orderId)) {
        return ['success' => false, 'message' => 'تنظیمات یا اطلاعات فاکتور NoaPay معتبر نیست.'];
    }
    $quantity = noapayQuantity($amount);
    $payload = array_merge([
        'quantity' => $quantity,
        'user_telegram_id' => (int) $userId,
        'callback_url' => 'https://' . $domainhosts . '/payment/noapay_webhook.php',
        'metadata' => (string) $orderId,
        // A fresh token prevents a previous order from being credited for this purchase.
        'reuse' => false,
    ], noapayFeeOptions(noapaySetting('noapay_fee_mode', 'seller')));
    $result = noapayRequest('POST', 'invoice/create', $payload);
    if (empty($result['success'])) {
        return $result;
    }
    $data = $result['data'];
    $token = $data['invoice_token'] ?? '';
    $url = noapayPaymentUrl($data);
    $total = filter_var($data['total_toman'] ?? null, FILTER_VALIDATE_INT);
    $expires = is_string($data['expires_at'] ?? null) ? strtotime($data['expires_at']) : false;
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token) || $url === '' || $total === false
        || $total < $quantity * 1000 || $expires === false || $expires <= time()
        || (int) ($data['quantity'] ?? 0) !== $quantity || ($data['status'] ?? '') !== 'pending') {
        return ['success' => false, 'message' => 'اطلاعات فاکتور دریافتی از NoaPay معتبر نیست.'];
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO noapay_invoice (id_order, invoice_token, quantity, total_toman, payment_url, expires_at, gateway_status, checked_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([(string) $orderId, $token, $quantity, $total, $url, $expires, 'pending', time()]);
    } catch (Throwable $e) {
        // invoice_token is UNIQUE: an accidentally reused provider token must never bind to another order.
        error_log('[noapay] Could not store invoice for order ' . (string) $orderId);
        return ['success' => false, 'message' => 'ذخیره فاکتور NoaPay ناموفق بود.'];
    }
    update('Payment_report', 'dec_not_confirmed', $token, 'id_order', (string) $orderId);
    return ['success' => true, 'authority' => $token, 'pay_url' => $url, 'total_toman' => $total, 'expires_at' => $expires];
}

function noapayInvoice(string $orderId): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM noapay_invoice WHERE id_order = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function noapayMatchesInvoice(array $data, array $invoice, array $payment): bool
{
    return ($payment['Payment_Method'] ?? '') === 'noapay'
        && (string) ($payment['id_order'] ?? '') === (string) $invoice['id_order']
        && (string) ($data['metadata'] ?? '') === (string) $invoice['id_order']
        && is_string($data['invoice_token'] ?? null)
        && hash_equals((string) $invoice['invoice_token'], $data['invoice_token'])
        && filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT) === (int) $invoice['quantity']
        && filter_var($data['total_toman'] ?? null, FILTER_VALIDATE_INT) === (int) $invoice['total_toman']
        && (int) ($payment['price'] ?? 0) > 0
        && (int) $invoice['quantity'] === noapayQuantity((int) ($payment['price'] ?? 0))
        && (int) $invoice['total_toman'] >= (int) $payment['price'];
}

// Only call with an authenticated API response or a signature-verified webhook.
function noapayApplyStatus(array $data): array
{
    global $pdo;
    $orderId = is_string($data['metadata'] ?? null) ? $data['metadata'] : '';
    if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $orderId)) {
        return ['ok' => false, 'reason' => 'invalid metadata'];
    }
    $invoice = noapayInvoice($orderId);
    $stmt = $pdo->prepare('SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($invoice === null || !is_array($payment)) {
        return ['ok' => false, 'reason' => 'invoice not ready'];
    }
    if (!noapayMatchesInvoice($data, $invoice, $payment)) {
        return ['ok' => false, 'reason' => 'invoice mismatch'];
    }
    $status = $data['status'] ?? '';
    if (!in_array($status, ['pending', 'opened', 'paid', 'receipt_sent', 'confirmed', 'completed', 'rejected', 'expired', 'partial_payment'], true)) {
        return ['ok' => false, 'reason' => 'unknown status'];
    }
    if ($status === 'completed') {
        if (($payment['payment_Status'] ?? '') === 'paid') {
            return ['ok' => true, 'reason' => 'already paid'];
        }
        if (!function_exists('payment_confirm_paid')) {
            require_once __DIR__ . '/PaymentConfirm.php';
        }
        $result = payment_confirm_paid($orderId, 'chashbacknoapay', ['method' => 'NoaPay 💳']);
        if (empty($result['ok']) && ($payment['payment_Status'] ?? '') !== 'cancelled') {
            return ['ok' => false, 'reason' => 'settlement failed'];
        }
    }
    // Intermediate callbacks never credit the wallet or change the local payment status.
    $stmt = $pdo->prepare("UPDATE noapay_invoice SET gateway_status = ?, checked_at = ? WHERE id_order = ? AND gateway_status <> 'completed'");
    $stmt->execute([$status, time(), $orderId]);
    if (in_array($status, ['rejected', 'expired'], true)) {
        $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = ? WHERE id_order = ? AND payment_Status = 'Unpaid'");
        $stmt->execute([$status === 'expired' ? 'expire' : 'reject', $orderId]);
    }
    if (function_exists('clearSelectCacheRow')) {
        clearSelectCacheRow('Payment_report', 'id_order', $orderId);
    }
    return ['ok' => true, 'reason' => $status];
}

function noapayRefresh(string $orderId): array
{
    global $pdo;
    $invoice = noapayInvoice($orderId);
    if ($invoice === null) {
        return ['ok' => false, 'reason' => 'invoice not ready'];
    }
    // Throttle concurrent status checks from the bot, Mini App and cron.
    $stmt = $pdo->prepare('UPDATE noapay_invoice SET checked_at = ? WHERE id_order = ? AND checked_at <= ?');
    $stmt->execute([time(), $orderId, time() - 30]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => true, 'reason' => 'pending'];
    }
    $result = noapayRequest('GET', 'invoice/' . rawurlencode($invoice['invoice_token']));
    return !empty($result['success']) ? noapayApplyStatus($result['data']) : ['ok' => false, 'reason' => 'provider unavailable'];
}
