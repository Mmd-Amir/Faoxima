<?php

require_once __DIR__ . '/tonpay_templates.php';

const ATLASPAY_CUSTOM_DEADLINE_SECONDS = 1200;
const ATLASPAY_CUSTOM_DEADLINE_GRACE_SECONDS = 600;
const ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS = 172800;
const ATLASPAY_CUSTOM_POLL_THROTTLE_SECONDS = 8;
const ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS = 10800;
const ATLASPAY_CUSTOM_RECHECK_SECONDS = 900;
const ATLASPAY_CUSTOM_CRON_INTERVAL_SECONDS = 30;
const ATLASPAY_CUSTOM_PENDING_VISIBLE_SECONDS = 7200;
const ATLASPAY_CUSTOM_DEFAULT_TITLE = 'پرداخت کارت‌به‌کارت (اطلس‌پی)';

function atlaspayPaymentMode()
{
    $row = select("PaySetting", "ValuePay", "NamePay", "atlaspay_payment_mode", "select");
    $mode = is_array($row) ? strtolower(trim((string) ($row['ValuePay'] ?? ''))) : '';
    return $mode === 'custom' ? 'custom' : 'official';
}

function atlaspayCustomSaveSetting($name, $value)
{
    global $pdo;
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
        if (function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('PaySetting', 'NamePay', (string) $name);
        }
        return true;
    } catch (Throwable $e) {
        error_log('AtlasPay setting save failed: ' . $e->getMessage());
        return false;
    }
}

function atlaspayCustomWebhookSecret()
{
    $row = select("PaySetting", "ValuePay", "NamePay", "atlaspay_webhook_secret", "select");
    return is_array($row) ? trim((string) ($row['ValuePay'] ?? '')) : '';
}

function atlaspayCustomWebhookUrl()
{
    global $domainhosts;
    return 'https://' . $domainhosts . '/payment/atlaspay.php';
}

function atlaspayCustomRegisterWebhook()
{
    $apiKey = atlaspayApiKey();
    if ($apiKey === '') {
        return ['ok' => false, 'message' => 'کلید API اطلس‌پی ثبت نشده است.'];
    }
    $url = atlaspayCustomWebhookUrl();
    $current = atlaspayCurlJson('GET', ATLASPAY_BASE_URL . '/webhook', [], $apiKey);
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
    if ($existing === $url && atlaspayCustomWebhookSecret() !== '') {
        return ['ok' => true, 'message' => 'Webhook از قبل ثبت شده است.'];
    }
    $registered = atlaspayCurlJson('POST', ATLASPAY_BASE_URL . '/webhook', ['webhookUrl' => $url], $apiKey);
    if (!is_array($registered)) {
        return ['ok' => false, 'message' => 'ثبت Webhook در اطلس‌پی ناموفق بود.'];
    }
    $registeredCode = (int) ($registered['status_code'] ?? 0);
    $registeredData = isset($registered['data']) && is_array($registered['data']) ? $registered['data'] : $registered;
    $secret = isset($registeredData['webhookSecret']) && is_scalar($registeredData['webhookSecret']) ? trim((string) $registeredData['webhookSecret']) : '';
    if ($registeredCode < 200 || $registeredCode >= 300 || $secret === '') {
        return ['ok' => false, 'message' => 'ثبت Webhook در اطلس‌پی ناموفق بود.'];
    }
    if (!atlaspayCustomSaveSetting('atlaspay_webhook_secret', $secret)) {
        return ['ok' => false, 'message' => 'ذخیره کلید امضای Webhook ناموفق بود.'];
    }
    return ['ok' => true, 'message' => 'Webhook با موفقیت ثبت شد.'];
}

function atlaspayCustomVerifyWebhookSignature($rawBody, $signature)
{
    $secret = atlaspayCustomWebhookSecret();
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

function atlaspayCustomError($message, $code = '')
{
    return ['success' => false, 'error' => (string) $message, 'error_code' => (string) $code];
}

function atlaspayCustomParseTime($value)
{
    if (!is_string($value) || trim($value) === '') {
        return 0;
    }
    $ts = strtotime($value);
    return $ts === false ? 0 : (int) $ts;
}

function atlaspayCustomValidReceiptUrl($url)
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

function atlaspayReceiptBotUrl($url)
{
    $url = atlaspayCustomValidReceiptUrl($url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    parse_str((string) ($parts['query'] ?? ''), $query);
    if (strtolower((string) ($parts['path'] ?? '')) !== '/atlaspaybot'
        || isset($query['startapp'])
        || !is_string($query['start'] ?? null)
        || preg_match('/^r_[A-Za-z0-9_-]+$/D', $query['start']) !== 1) {
        return '';
    }
    return 'https://t.me/atlaspaybot?start=' . rawurlencode($query['start']);
}

function atlaspayReceiptInstructions()
{
    return '📎 پس از واریز، در صورتی که پرداخت ظرف ۲ دقیقه به‌صورت خودکار تأیید نشد، از دکمه «ارسال رسید» وارد ربات اطلس‌پی شوید و تصویر رسید را ارسال نمایید. برای هر سفارش تنها یک رسید قابل ثبت است.';
}

function atlaspayBotReceiptButtonText()
{
    return tonpayTemplateRender('dyn_atlaspay_bot_receipt_button', [], false);
}

function atlaspayBotReceiptInstructions()
{
    return tonpayTemplateRender('dyn_atlaspay_bot_receipt_instructions', ['receipt_button' => atlaspayBotReceiptButtonText()]);
}

function atlaspayCustomCardIsComplete($cardNumber)
{
    $raw = (string) $cardNumber;
    if (preg_match('/^[\d\s\-]{16,23}$/', $raw) !== 1) {
        return false;
    }
    return strlen(preg_replace('/\D/', '', $raw)) === 16;
}

function atlaspayCustomCreateOrder($orderId, $amount, $telegramId)
{
    $amount = (int) $amount;
    $telegramId = (int) $telegramId;
    $decoded = atlaspayCreateOrder($orderId, $amount, $telegramId > 0 ? $telegramId : null);
    if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
        return atlaspayCustomError(is_array($decoded) ? (string) ($decoded['error'] ?? 'پاسخ نامعتبر از سرویس اطلس‌پی') : 'پاسخ نامعتبر از سرویس اطلس‌پی');
    }
    $remoteId = trim((string) ($decoded['orderId'] ?? ''));
    $discard = static function () use ($remoteId) {
        if ($remoteId !== '' && function_exists('atlaspayCancelOrder')) {
            atlaspayCancelOrder($remoteId);
        }
    };
    $total = $decoded['totalAmountToman'] ?? null;
    if (!is_numeric($total) || (float) $total != floor((float) $total) || (int) $total < $amount) {
        $discard();
        error_log('AtlasPay custom create order amount mismatch: ' . json_encode(['order_id' => (string) $orderId, 'requested' => $amount, 'total' => $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return atlaspayCustomError('مبلغ سفارش اطلس‌پی با مبلغ درخواستی مطابقت ندارد', 'AMOUNT_MISMATCH');
    }
    if (isset($decoded['baseAmountToman']) && is_numeric($decoded['baseAmountToman']) && (int) $decoded['baseAmountToman'] !== $amount) {
        $discard();
        error_log('AtlasPay custom create order base mismatch: ' . json_encode(['order_id' => (string) $orderId, 'requested' => $amount, 'base' => $decoded['baseAmountToman']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return atlaspayCustomError('مبلغ سفارش اطلس‌پی با مبلغ درخواستی مطابقت ندارد', 'AMOUNT_MISMATCH');
    }
    $link = atlaspayCustomValidReceiptUrl($decoded['customerStartLink'] ?? '');
    if ($link === '') {
        $discard();
        return atlaspayCustomError('لینک رسمی اطلس‌پی معتبر نیست', 'INVALID_LINK');
    }
    $receiptLink = atlaspayReceiptBotUrl($decoded['receiptBotLink'] ?? '');
    $card = isset($decoded['cardNumber']) && is_scalar($decoded['cardNumber']) ? substr(trim((string) $decoded['cardNumber']), 0, 40) : '';
    if (!atlaspayCustomCardIsComplete($card)) {
        $card = '';
    }
    $deadline = atlaspayCustomParseTime($decoded['paymentDeadlineAt'] ?? '');
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

function atlaspayCustomFetchReport($orderId)
{
    global $pdo;
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

function atlaspayCustomStoreOrder($orderId, array $order)
{
    global $pdo;
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

function atlaspayCustomUpdateColumns($orderId, array $columns, $onlyOpen = true)
{
    global $pdo;
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
        if (function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
        }
        if (function_exists('rx_redis_del')) {
            $owner = select('Payment_report', 'id_user', 'id_order', (string) $orderId, 'select');
            if (is_array($owner)) {
                rx_redis_del('faoxima:paystatus:' . (string) $orderId . ':' . (string) ($owner['id_user'] ?? ''));
            }
        }
        return $stmt->rowCount();
    } catch (Throwable $e) {
        error_log('AtlasPay custom update failed: ' . json_encode(['order_id' => (string) $orderId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}

function atlaspayCustomTransition($orderId, array $fromStatuses, $toStatus, $localState, array $extra = [])
{
    global $pdo;
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
            if (function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            if (function_exists('rx_redis_del')) {
                $owner = select('Payment_report', 'id_user', 'id_order', (string) $orderId, 'select');
                if (is_array($owner)) {
                    rx_redis_del('faoxima:paystatus:' . (string) $orderId . ':' . (string) ($owner['id_user'] ?? ''));
                }
            }
        }
        return $changed;
    } catch (Throwable $e) {
        error_log('AtlasPay custom transition failed: ' . json_encode(['order_id' => (string) $orderId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return false;
    }
}

function atlaspayCustomClaimLocalState($orderId, $state, array $statuses)
{
    global $pdo;
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
        if ($changed && function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
        }
        return $changed;
    } catch (Throwable $e) {
        error_log('AtlasPay custom claim state failed: ' . json_encode(['order_id' => (string) $orderId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return false;
    }
}

function atlaspayCustomPurchaseKind($invoiceField)
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

function atlaspayCustomGatewayTitle()
{
    $stored = function_exists('faoxima_textbot_get') ? (string) faoxima_textbot_get('atlaspay', '') : '';
    $name = preg_replace('/^[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+|[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+$/u', '', $stored);
    if (!is_string($name) || $name === '' || $name === '🌐 اطلس‌پی') {
        return ATLASPAY_CUSTOM_DEFAULT_TITLE;
    }
    $stripped = preg_replace('/^(?:💠|🌐)\s*/u', '', $name);
    $name = is_string($stripped) && $stripped !== '' ? $stripped : $name;
    return mb_substr($name, 0, 120);
}

function atlaspayCustomOpenPhases()
{
    return ['awaiting_payment', 'deadline_check', 'under_review', 'underpaid_review', 'underpaid_remainder'];
}

function atlaspayCustomPhaseLabel($phase)
{
    $labels = [
        'awaiting_payment' => 'در انتظار پرداخت',
        'deadline_check' => 'پایان مهلت پرداخت؛ در حال بررسی وضعیت نهایی',
        'under_review' => 'رسید در حال بررسی اطلس‌پی',
        'underpaid_review' => 'بررسی مغایرت مبلغ واریزی',
        'underpaid_remainder' => 'در انتظار تکمیل مبلغ باقی‌مانده',
        'confirming' => 'تأیید شده و در حال نهایی‌سازی',
        'manual_review' => 'تأیید با کسری مبلغ؛ منتظر تصمیم مدیر',
        'paid' => 'پرداخت تأییدشده',
        'rejected' => 'ردشده',
        'expired' => 'منقضی‌شده',
        'canceled' => 'لغو‌شده',
    ];
    return $labels[$phase] ?? '';
}

function atlaspayCustomPhaseDetail($phase, $reason = '')
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

function atlaspayCustomBuildState(array $report, $now = null)
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

    $open = in_array($phase, atlaspayCustomOpenPhases(), true);
    $paymentLink = atlaspayCustomValidReceiptUrl($report['atlaspay_payment_url'] ?? '');
    $receiptLink = atlaspayReceiptBotUrl($report['atlaspay_receipt_url'] ?? '');
    $receiptAvailable = $open && $phase === 'awaiting_payment';
    $cardVisible = $phase === 'awaiting_payment';

    return [
        'order_id' => (string) ($report['id_order'] ?? ''),
        'tracking_code' => (string) (($report['atlaspay_tracking_code'] ?? '') !== '' ? $report['atlaspay_tracking_code'] : ($report['id_order'] ?? '')),
        'purchase_kind' => atlaspayCustomPurchaseKind((string) ($report['id_invoice'] ?? '')),
        'phase' => $phase,
        'phase_label' => atlaspayCustomPhaseLabel($phase),
        'remote_status' => $remote,
        'now' => $now,
        'request_amount' => $price,
        'total_amount' => $total,
        'payable_rial' => $total * 10,
        'card_number' => $cardVisible ? $card : '',
        'card_name' => $cardVisible ? (string) ($report['atlaspay_card_name'] ?? '') : '',
        'bank_name' => $cardVisible ? (string) ($report['atlaspay_bank_name'] ?? '') : '',
        'card_complete' => $cardVisible && atlaspayCustomCardIsComplete($card),
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

function atlaspayCustomPublicView(array $report)
{
    $state = atlaspayCustomBuildState($report);
    $digits = preg_replace('/\D/', '', $state['card_number']);
    $state['card_number_display'] = $state['card_complete'] ? trim(chunk_split($digits, 4, ' ')) : '';
    $state['card_copy_value'] = $state['card_complete'] ? $digits : '';
    $state['amount_copy_value'] = (string) $state['payable_rial'];
    $state['wallet_credit'] = $state['request_amount'];
    $reason = $state['phase'] === 'rejected' ? $state['reason'] : '';
    $state['messages'] = [
        'status' => atlaspayCustomPhaseDetail($state['phase'], $reason),
        'amount_warning' => sprintf(atlaspayCustomAmountWarningTemplate(), 'کپی مبلغ'),
        'card_only_warning' => atlaspayCustomCardOnlyWarning(),
        'receipt' => $state['receipt_url'] !== '' ? atlaspayReceiptInstructions() : '',
    ];
    $state['button_styles'] = tonpayButtonStyleMap();
    $state['gateway_title'] = atlaspayCustomGatewayTitle();
    unset($state['reason']);
    return $state;
}

function atlaspayCustomAmountWarningTemplate()
{
    return '⚠️ لطفاً مبلغ پرداخت را حتماً با استفاده از دکمه «%s» کپی کرده و دقیقاً همان مبلغ را واریز کنید. از گرد کردن یا تغییر مبلغ خودداری کنید.';
}

function atlaspayCustomCardOnlyWarning()
{
    return '🚫 پرداخت فقط از طریق کارت‌به‌کارت مجاز است. از واریز با شماره شبا، پایا، ساتنا یا سایر روش‌های انتقال بانکی خودداری کنید.';
}

function atlaspayCustomFormatClock($seconds)
{
    $seconds = max(0, (int) $seconds);
    if ($seconds >= 3600) {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
    return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
}

function atlaspayCustomFormatDateTime($timestamp)
{
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return '—';
    }
    return function_exists('jdate') ? jdate('Y/m/d H:i', $timestamp) : date('Y/m/d H:i', $timestamp);
}

function atlaspayCustomEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function atlaspayCustomBotText(array $state)
{
    $e = 'atlaspayCustomEscape';
    $lines = ['💠 ' . $e(atlaspayCustomGatewayTitle()), ''];
    $lines[] = '💰 مبلغ شارژ / خرید: ' . number_format($state['request_amount']) . ' تومان';
    $lines[] = '💳 مبلغ قابل پرداخت: ' . number_format($state['payable_rial']) . ' ریال';
    $lines[] = '';
    if ($state['phase'] === 'awaiting_payment') {
        $lines[] = $e(sprintf(atlaspayCustomAmountWarningTemplate(), 'کپی مبلغ پرداخت'));
        $lines[] = '';
        $lines[] = $e(atlaspayCustomCardOnlyWarning());
        $lines[] = '';
        if ($state['card_complete']) {
            $lines[] = '🏦 شماره کارت: <code>' . $e(preg_replace('/\D/', '', $state['card_number'])) . '</code>';
            $lines[] = '👤 به نام: ' . $e($state['card_name'] !== '' ? $state['card_name'] : '—');
            $lines[] = '🏦 بانک: ' . $e($state['bank_name'] !== '' ? $state['bank_name'] : '—');
        } else {
            $lines[] = $state['payment_url'] !== ''
                ? '⚠️ شماره کارت به‌صورت کامل دریافت نشد؛ برای مشاهده اطلاعات کارت و پرداخت، از دکمه «مشاهده اطلاعات پرداخت» استفاده کنید.'
                : '⚠️ شماره کارت به‌صورت کامل دریافت نشد؛ برای دریافت اطلاعات پرداخت با پشتیبانی فروشگاه در ارتباط باشید.';
        }
        $lines[] = '';
    }
    $lines[] = '🧾 شناسه پیگیری: <code>' . $e($state['tracking_code']) . '</code>';
    $lines[] = '';
    $reason = $state['phase'] === 'rejected' ? $e($state['reason']) : '';
    $lines[] = $e(atlaspayCustomPhaseDetail($state['phase'], '')) . ($reason !== '' ? "\n" . $reason : '');
    if ($state['phase'] === 'awaiting_payment') {
        if ($state['receipt_url'] !== '') {
            $lines[] = '';
            $lines[] = atlaspayBotReceiptInstructions();
        }
        $lines[] = '';
        $lines[] = '⏱ زمان باقی‌مانده تا پایان مهلت پرداخت: <b>' . atlaspayCustomFormatClock($state['remaining']) . '</b>';
    }
    return implode("\n", $lines);
}

function atlaspayCustomSupportUrl()
{
    global $usernamebot;
    $row = function_exists('select') ? select('setting', 'support_username', null, null, 'select') : null;
    $handle = is_array($row) ? ltrim(trim((string) ($row['support_username'] ?? '')), '@') : '';
    if ($handle !== '') {
        return 'https://t.me/' . $handle;
    }
    $bot = isset($usernamebot) ? ltrim(trim((string) $usernamebot), '@') : '';
    return $bot !== '' ? 'https://t.me/' . $bot : '';
}

function atlaspayCustomFollowUpKeyboard(array $state)
{
    $rows = [[rx_payment_button('atlaspay', 'view_details', ['text' => '🔍 مشاهده جزئیات و وضعیت', 'callback_data' => 'apc_view_' . $state['order_id']])]];
    $supportUrl = atlaspayCustomSupportUrl();
    if ($supportUrl !== '') {
        $rows[] = [rx_payment_button('atlaspay', 'support', ['text' => '📞 پشتیبانی', 'url' => $supportUrl])];
    }
    $rows[] = [rx_payment_button('atlaspay', 'pending_list', ['text' => '⏳ تراکنش‌های در انتظار', 'callback_data' => 'tpc_list'])];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function atlaspayCustomBotKeyboard(array $state)
{
    $orderId = $state['order_id'];
    $rows = [];
    if ($state['phase'] === 'awaiting_payment') {
        if (!$state['card_complete'] && $state['payment_url'] !== '') {
            $rows[] = [tonpayStyledButton(['text' => '💳 مشاهده اطلاعات پرداخت', 'url' => $state['payment_url']], 'pay_link', 'atlaspay')];
        }
        $copyRow = [];
        if ($state['card_complete']) {
            $copyRow[] = tonpayStyledButton(['text' => '📋 کپی شماره کارت', 'copy_text' => ['text' => preg_replace('/\D/', '', $state['card_number'])], '_rxck' => 'card_num'], 'copy_card', 'atlaspay');
        }
        $copyRow[] = tonpayStyledButton(['text' => '📋 کپی مبلغ پرداخت', 'copy_text' => ['text' => (string) $state['payable_rial']], '_rxck' => 'card_amount'], 'copy_amount', 'atlaspay');
        $rows[] = $copyRow;
    }
    $actionRow = [];
    if (!$state['terminal'] && $state['phase'] !== 'confirming') {
        $actionRow[] = tonpayStyledButton(['text' => '🔄 بررسی وضعیت پرداخت', 'callback_data' => 'apc_chk_' . $orderId], 'check_status', 'atlaspay');
    }
    if ($state['receipt_url'] !== '') {
        $actionRow[] = tonpayStyledButton(['text' => atlaspayBotReceiptButtonText(), 'url' => $state['receipt_url']], 'send_receipt', 'atlaspay');
    }
    if (!empty($actionRow)) {
        $rows[] = $actionRow;
    }
    if ($state['can_cancel']) {
        $rows[] = [tonpayStyledButton(['text' => '❌ لغو تراکنش', 'callback_data' => 'apc_can_' . $orderId], 'cancel_transaction', 'atlaspay')];
    }
    $rows[] = [tonpayStyledButton(['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser'], 'back_main', 'atlaspay')];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function atlaspayCustomBotCancelConfirmView($orderId, $backData)
{
    $text = "آیا از لغو این تراکنش اطمینان دارید؟\n\n⚠️ لغو فاکتور به‌معنی بازگشت وجه واریزشده نیست. اطلس‌پی فقط تا زمانی که هیچ پرداخت یا رسیدی برای این سفارش ثبت نشده باشد اجازه لغو می‌دهد.";
    $keyboard = json_encode(['inline_keyboard' => [
        [tonpayStyledButton(['text' => '✅ بله، لغو تراکنش', 'callback_data' => 'apc_cny_' . $orderId], 'cancel_confirm_approve', 'atlaspay')],
        [tonpayStyledButton(['text' => '🔙 بازگشت', 'callback_data' => $backData], 'cancel_confirm_back', 'atlaspay')],
    ]], JSON_UNESCAPED_UNICODE);
    return ['text' => $text, 'keyboard' => $keyboard];
}

function atlaspayCustomBotSend($chatId, $orderId)
{
    $report = atlaspayCustomFetchReport($orderId);
    if ($report === null) {
        return false;
    }
    $state = atlaspayCustomBuildState($report);
    $response = sendmessage($chatId, atlaspayCustomBotText($state), atlaspayCustomBotKeyboard($state), 'HTML');
    if (function_exists('updatePaymentMessageId')) {
        updatePaymentMessageId($response, $orderId);
    }
    return true;
}

function atlaspayCustomBotRender($chatId, $messageId, $orderId, ?array $report = null)
{
    $report = $report ?? atlaspayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
        return false;
    }
    $state = atlaspayCustomBuildState($report);
    Editmessagetext($chatId, $messageId, atlaspayCustomBotText($state), atlaspayCustomBotKeyboard($state), 'HTML');
    return true;
}

function atlaspayCustomBotRefreshOriginal($chatId, $orderId, $currentMessageId)
{
    $report = atlaspayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
        return false;
    }
    $originalId = (int) ($report['message_id'] ?? 0);
    if ($originalId > 0 && $originalId !== (int) $currentMessageId) {
        atlaspayCustomBotRender($chatId, $originalId, $orderId, $report);
        if ((int) $currentMessageId > 0) {
            deletemessage($chatId, $currentMessageId);
        }
        return true;
    }
    return atlaspayCustomBotRender($chatId, $currentMessageId, $orderId, $report);
}

function atlaspayCustomBotAnswer($callbackQueryId, $text = '', $alert = false)
{
    if ($callbackQueryId === '' || $callbackQueryId === 0 || $callbackQueryId === null) {
        return;
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => $alert,
        'cache_time' => 0,
    ]);
}

function atlaspayCustomLocalStatusIsOpen(array $report)
{
    return in_array((string) ($report['payment_Status'] ?? ''), ['Unpaid', 'pending', 'waiting'], true);
}

function atlaspayCustomIsLateReconcilable(array $report)
{
    return in_array((string) ($report['payment_Status'] ?? ''), ['expire', 'reject'], true)
        && (string) ($report['atlaspay_local_state'] ?? '') !== 'closed'
        && (int) ($report['atlaspay_created_at'] ?? 0) >= time() - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS;
}

function atlaspayCustomNotifyUser(array $report, $phaseOverride = null)
{
    if (!function_exists('sendmessage')) {
        return;
    }
    $fresh = atlaspayCustomFetchReport($report['id_order']) ?? $report;
    $state = atlaspayCustomBuildState($fresh);
    if ($phaseOverride !== null) {
        $state['phase'] = $phaseOverride;
        $state['phase_label'] = atlaspayCustomPhaseLabel($phaseOverride);
    }
    $reason = $state['phase'] === 'rejected' ? $state['reason'] : '';
    $text = atlaspayCustomEscape(atlaspayCustomPhaseDetail($state['phase'], $reason))
        . "\n\n🧾 شناسه پیگیری: <code>" . atlaspayCustomEscape($state['tracking_code']) . '</code>';
    sendmessage($fresh['id_user'], $text, atlaspayCustomFollowUpKeyboard($state), 'HTML');
}

function atlaspayCustomReportToAdmin($text)
{
    global $setting;
    $channel = is_array($setting ?? null) ? (string) ($setting['Channel_Report'] ?? '') : '';
    if ($channel === '') {
        $row = function_exists('select') ? select('setting', 'Channel_Report', null, null, 'select') : null;
        $channel = is_array($row) ? (string) ($row['Channel_Report'] ?? '') : '';
    }
    if ($channel === '' || !function_exists('telegram')) {
        return;
    }
    $threadRow = select("topicid", "idreport", "report", "paymentreport", "select");
    $payload = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML'];
    if (is_array($threadRow) && !empty($threadRow['idreport'])) {
        $payload['message_thread_id'] = $threadRow['idreport'];
    }
    telegram('sendmessage', $payload);
}

function atlaspayCustomNotifyManualDelivery(array $report, $actualReceived)
{
    $fresh = atlaspayCustomFetchReport($report['id_order']) ?? $report;
    $text = "⚠️ سفارش اطلس‌پی با کسری واریز تأیید شد — نیاز به بررسی دستی.\n"
        . "🛒 کد فاکتور: <code>" . atlaspayCustomEscape($fresh['id_order']) . "</code>\n"
        . "🧾 شناسه پیگیری اطلس‌پی: <code>" . atlaspayCustomEscape($fresh['atlaspay_tracking_code'] ?? '') . "</code>\n"
        . "👤 آیدی کاربر: <code>" . atlaspayCustomEscape($fresh['id_user']) . "</code>\n"
        . "💰 مبلغ درخواستی (اعتبار/خرید): " . number_format((int) $fresh['price']) . " تومان\n"
        . "💳 مبلغ سفارش اطلس‌پی: " . number_format((int) ($fresh['atlaspay_total_amount'] ?? 0)) . " تومان\n"
        . ($actualReceived !== null ? '💸 مبلغ واریزی واقعی: ' . number_format($actualReceived) . ' تومان' : '💸 مبلغ واریزی واقعی: نامشخص');
    atlaspayCustomReportToAdmin($text);
    atlaspayCustomNotifyUser($fresh, 'manual_review');
}

function atlaspayCustomFulfill(array $report, array $check)
{
    $orderId = (string) $report['id_order'];
    $status = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
    if (!in_array($status, ['confirmed', 'settled'], true)) {
        return ['ok' => false, 'reason' => 'not confirmed'];
    }
    if (array_key_exists('paid', $check) && empty($check['paid'])) {
        return ['ok' => false, 'reason' => 'not paid'];
    }
    if (isset($check['baseAmountToman']) && is_numeric($check['baseAmountToman']) && (int) $check['baseAmountToman'] !== (int) $report['price']) {
        error_log('AtlasPay custom fulfill base mismatch: ' . json_encode(['order_id' => $orderId, 'expected' => (int) $report['price'], 'received' => $check['baseAmountToman']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['ok' => false, 'reason' => 'base amount mismatch'];
    }
    if ((string) ($report['payment_Status'] ?? '') === 'cancelled') {
        return ['ok' => false, 'reason' => 'cancelled'];
    }
    if (!function_exists('payment_confirm_paid')) {
        require_once __DIR__ . '/../../../lib/PaymentConfirm.php';
    }
    $previousStatus = (string) ($report['payment_Status'] ?? '');
    $actual = isset($check['actualReceivedAmountToman']) && is_numeric($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
    $lines = [
        '🧾 شناسه پیگیری اطلس‌پی: ' . ($report['atlaspay_tracking_code'] ?? ''),
        '💰 مبلغ اعتباردهی: ' . number_format((int) $report['price']) . ' تومان',
        '💸 مبلغ سفارش اطلس‌پی: ' . number_format((int) ($report['atlaspay_total_amount'] ?? 0)) . ' تومان',
    ];
    if ($actual !== null) {
        $lines[] = '💸 مبلغ نهایی تأییدشده توسط اطلس‌پی: ' . number_format($actual) . ' تومان';
    }
    $lines[] = '🔐 درگاه داخلی اطلس‌پی';
    if (in_array($previousStatus, ['expire', 'reject'], true)) {
        $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($previousStatus === 'reject' ? 'رد اولیه' : 'پایان مهلت محلی');
    }
    $threadRow = select("topicid", "idreport", "report", "paymentreport", "select");
    $result = payment_confirm_paid($orderId, 'chashbackatlaspay', [
        'method' => 'atlaspay',
        'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
        'extra_lines' => $lines,
    ]);
    if (!empty($result['ok'])) {
        atlaspayCustomUpdateColumns($orderId, ['atlaspay_remote_status' => $status, 'atlaspay_local_state' => 'completed'], false);
    }
    return $result;
}

function atlaspayCustomProcessCheck(array $report, $check, $fulfill = true)
{
    $remoteId = trim((string) ($report['atlaspay_order_id'] ?? ''));
    if (!is_array($check)) {
        return ['state' => 'error'];
    }
    $statusCode = (int) ($check['status_code'] ?? 0);
    if ($statusCode === 429) {
        return ['state' => 'rate_limited'];
    }
    if (($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) || (array_key_exists('success', $check) && $check['success'] === false)) {
        return ['state' => 'error'];
    }
    $checkId = $check['id'] ?? ($check['orderId'] ?? null);
    if ($remoteId === '' || !is_numeric($checkId) || (int) $checkId !== (int) $remoteId) {
        error_log('AtlasPay custom check identity mismatch: ' . json_encode(['order_id' => (string) $report['id_order']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['state' => 'error'];
    }
    if (isset($check['merchantOrderRef']) && is_scalar($check['merchantOrderRef']) && (string) $check['merchantOrderRef'] !== (string) $report['id_order']) {
        error_log('AtlasPay custom check reference mismatch: ' . json_encode(['order_id' => (string) $report['id_order']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['state' => 'error'];
    }
    $storedTotal = (int) ($report['atlaspay_total_amount'] ?? 0);
    if (isset($check['totalAmountToman']) && is_numeric($check['totalAmountToman']) && $storedTotal > 0 && (int) $check['totalAmountToman'] !== $storedTotal) {
        error_log('AtlasPay custom check total mismatch: ' . json_encode(['order_id' => (string) $report['id_order'], 'expected' => $storedTotal, 'received' => $check['totalAmountToman']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['state' => 'error'];
    }

    $orderId = (string) $report['id_order'];
    $remote = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
    $actual = isset($check['actualReceivedAmountToman']) && is_numeric($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
    $columns = ['atlaspay_last_checked_at' => time()];
    if ($remote !== '') {
        $columns['atlaspay_remote_status'] = $remote;
    }
    if ($actual !== null) {
        $columns['atlaspay_actual_received'] = $actual;
    }
    atlaspayCustomUpdateColumns($orderId, $columns, false);

    $localStatus = (string) ($report['payment_Status'] ?? '');
    $openStatuses = ['Unpaid', 'pending', 'waiting'];

    if (in_array($remote, ['confirmed', 'settled'], true)) {
        if (array_key_exists('paid', $check) && empty($check['paid'])) {
            return ['state' => 'open', 'status' => $remote];
        }
        $manual = !empty($check['requiresManualDelivery']) || ($actual !== null && $storedTotal > 0 && $actual < $storedTotal);
        if ($manual) {
            atlaspayCustomUpdateColumns($orderId, ['atlaspay_manual_delivery' => 1], false);
            if (atlaspayCustomClaimLocalState($orderId, 'manual_review', ['Unpaid', 'pending', 'waiting', 'expire', 'reject'])) {
                atlaspayCustomNotifyManualDelivery($report, $actual);
            }
            return ['state' => 'manual', 'status' => $remote];
        }
        if (!$fulfill) {
            return ['state' => 'completed', 'status' => $remote];
        }
        $result = atlaspayCustomFulfill($report, $check);
        return ['state' => 'completed', 'status' => $remote, 'fulfilled' => !empty($result['ok'])];
    }

    if ($remote === 'rejected') {
        $reason = '';
        foreach (['reason', 'rejectReason', 'rejectionReason'] as $key) {
            if (isset($check[$key]) && is_scalar($check[$key]) && trim((string) $check[$key]) !== '') {
                $reason = mb_substr(trim((string) $check[$key]), 0, 250);
                break;
            }
        }
        $extra = $reason !== '' ? ['dec_not_confirmed' => $reason] : [];
        if (atlaspayCustomTransition($orderId, $openStatuses, 'reject', 'rejected', $extra)) {
            atlaspayCustomNotifyUser($report, 'rejected');
        }
        return ['state' => 'terminal', 'status' => $remote];
    }

    if ($remote === 'expired') {
        if (atlaspayCustomTransition($orderId, $openStatuses, 'expire', 'expired')) {
            atlaspayCustomNotifyUser($report, 'expired');
        }
        return ['state' => 'terminal', 'status' => $remote];
    }

    if ($remote === 'cancelled') {
        atlaspayCustomTransition($orderId, array_merge($openStatuses, ['expire', 'reject']), 'cancelled', 'closed');
        return ['state' => 'terminal', 'status' => $remote];
    }

    if (in_array($remote, ['admin_review', 'underpaid_review', 'underpaid_awaiting_remainder'], true)) {
        if (in_array($localStatus, ['expire', 'reject'], true)) {
            atlaspayCustomTransition($orderId, ['expire', 'reject'], 'Unpaid', $remote);
        } else {
            atlaspayCustomClaimLocalState($orderId, $remote, $openStatuses);
        }
        return ['state' => 'open', 'status' => $remote];
    }

    $deadlineAt = (int) ($report['atlaspay_deadline_at'] ?? 0);
    if ($deadlineAt > 0 && time() > $deadlineAt + ATLASPAY_CUSTOM_DEADLINE_GRACE_SECONDS && in_array($localStatus, $openStatuses, true)) {
        if (atlaspayCustomTransition($orderId, $openStatuses, 'expire', 'unpaid_timeout')) {
            atlaspayCustomNotifyUser($report, 'expired');
        }
        return ['state' => 'terminal', 'status' => $remote];
    }
    return ['state' => 'open', 'status' => $remote];
}

function atlaspayCustomPollOrder(array $report, $fulfill = true, $verify = false)
{
    $remoteId = trim((string) ($report['atlaspay_order_id'] ?? ''));
    if ($remoteId === '') {
        return ['state' => 'error'];
    }
    $check = $verify ? atlaspayVerifyOrder($remoteId) : atlaspayCheckOrder($remoteId);
    return atlaspayCustomProcessCheck($report, $check, $fulfill);
}

function atlaspayCustomPollThrottled($orderId, $userId)
{
    $report = atlaspayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $userId) {
        return null;
    }
    $pollable = atlaspayCustomLocalStatusIsOpen($report) || atlaspayCustomIsLateReconcilable($report);
    if ($pollable && time() - (int) ($report['atlaspay_last_checked_at'] ?? 0) >= ATLASPAY_CUSTOM_POLL_THROTTLE_SECONDS) {
        atlaspayCustomUpdateColumns($orderId, ['atlaspay_last_checked_at' => time()], false);
        atlaspayCustomPollOrder($report, true, true);
        $report = atlaspayCustomFetchReport($orderId) ?? $report;
    }
    return $report;
}

function atlaspayCustomCancelBlockedMessage(array $state)
{
    if (in_array($state['phase'], ['paid', 'confirming', 'manual_review'], true)) {
        return 'پرداخت شما در اطلس‌پی تأیید شده است و فاکتور قابل لغو نیست.';
    }
    if (in_array($state['phase'], ['under_review', 'underpaid_review', 'underpaid_remainder'], true)) {
        return 'برای این سفارش پرداخت یا رسید ثبت شده و اطلس‌پی اجازه لغو نمی‌دهد.';
    }
    return 'این فاکتور دیگر قابل لغو نیست.';
}

function atlaspayCustomCancel($orderId, $userId)
{
    $report = atlaspayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $userId) {
        return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
    }
    $state = atlaspayCustomBuildState($report);
    if (!$state['can_cancel']) {
        return ['ok' => false, 'message' => atlaspayCustomCancelBlockedMessage($state)];
    }
    $remoteId = trim((string) ($report['atlaspay_order_id'] ?? ''));
    $poll = atlaspayCustomPollOrder($report, true, false);
    if (in_array($poll['state'], ['error', 'rate_limited'], true)) {
        return ['ok' => false, 'message' => 'در حال حاضر امکان بررسی وضعیت پرداخت در اطلس‌پی وجود ندارد؛ کمی بعد دوباره تلاش کنید.'];
    }
    $report = atlaspayCustomFetchReport($orderId);
    if ($report === null) {
        return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
    }
    $state = atlaspayCustomBuildState($report);
    if (!$state['can_cancel']) {
        return ['ok' => false, 'message' => atlaspayCustomCancelBlockedMessage($state)];
    }
    $response = atlaspayCancelOrder($remoteId);
    $accepted = is_array($response)
        && !empty($response['success'])
        && strtolower((string) ($response['status'] ?? 'cancelled')) === 'cancelled'
        && ((int) ($response['status_code'] ?? 200) >= 200 && (int) ($response['status_code'] ?? 200) < 300);
    if (!$accepted) {
        $message = is_array($response) && isset($response['message']) && is_scalar($response['message']) && trim((string) $response['message']) !== ''
            ? trim((string) $response['message'])
            : 'اطلس‌پی اجازه لغو این سفارش را نداد.';
        $fresh = atlaspayCustomFetchReport($orderId);
        if ($fresh !== null) {
            atlaspayCustomPollOrder($fresh, true, false);
        }
        return ['ok' => false, 'message' => $message];
    }
    $changed = atlaspayCustomUpdateColumns($orderId, [
        'payment_Status' => 'cancelled',
        'atlaspay_remote_status' => 'cancelled',
        'atlaspay_local_state' => 'closed',
    ]);
    return $changed > 0 ? ['ok' => true] : ['ok' => false, 'message' => 'این فاکتور دیگر قابل لغو نیست.'];
}

function atlaspayCustomPurgeProtected($orderId)
{
    $report = atlaspayCustomFetchReport($orderId);
    if ($report === null) {
        return false;
    }
    $state = atlaspayCustomBuildState($report);
    return in_array($state['phase'], array_merge(atlaspayCustomOpenPhases(), ['confirming', 'manual_review']), true);
}

function atlaspayCustomPurgeCards()
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            "UPDATE Payment_report
                SET atlaspay_card_number = NULL, atlaspay_card_name = NULL
              WHERE atlaspay_gateway_mode = 'custom'
                AND atlaspay_created_at < ?
                AND atlaspay_card_number IS NOT NULL"
        );
        $stmt->execute([time() - ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS]);
    } catch (Throwable $e) {
        error_log('[atlaspaycheck] card purge failed: ' . $e->getMessage());
    }
}

function atlaspayCustomUserPending($userId, $limit = 10)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return [];
    }
    $now = time();
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE id_user = ?
                AND Payment_Method = 'atlaspay'
                AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND (
                    payment_Status IN ('Unpaid','pending','waiting')
                    OR (
                        payment_Status IN ('expire','reject')
                        AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'closed')
                        AND atlaspay_created_at >= ?
                    )
                )
              ORDER BY id DESC
              LIMIT " . max(1, (int) $limit)
        );
        $stmt->execute([(string) $userId, $now - ATLASPAY_CUSTOM_PENDING_VISIBLE_SECONDS]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('AtlasPay custom user pending failed: ' . $e->getMessage());
        return [];
    }
}

function atlaspayCustomBotListEntries($userId)
{
    $lines = [];
    $rows = [];
    foreach (atlaspayCustomUserPending($userId) as $report) {
        $state = atlaspayCustomBuildState($report);
        $lines[] = '🧾 شناسه پیگیری: <code>' . atlaspayCustomEscape($state['tracking_code']) . '</code>';
        $lines[] = '🏷 درگاه: ' . atlaspayCustomEscape(atlaspayCustomGatewayTitle());
        $lines[] = '🛍 نوع تراکنش: ' . atlaspayCustomEscape($state['purchase_kind']);
        $lines[] = '💰 مبلغ: <b>' . number_format($state['request_amount']) . '</b> تومان';
        $lines[] = '📌 وضعیت: ' . atlaspayCustomEscape($state['phase_label']);
        $lines[] = '📅 تاریخ ایجاد: ' . atlaspayCustomEscape(atlaspayCustomFormatDateTime($state['created_at']));
        if ($state['phase'] === 'awaiting_payment') {
            $lines[] = '⏱ زمان باقی‌مانده تا پایان مهلت پرداخت: <b>' . atlaspayCustomFormatClock($state['remaining']) . '</b>';
        }
        $lines[] = '';
        $buttons = [rx_payment_button('atlaspay', 'list_view', ['text' => '🔍 مشاهده جزئیات و وضعیت', 'callback_data' => 'apc_view_' . $state['order_id']])];
        if ($state['receipt_url'] !== '') {
            $buttons[] = rx_payment_button('atlaspay', 'list_receipt', ['text' => atlaspayBotReceiptButtonText(), 'url' => $state['receipt_url']]);
        }
        $rows[] = $buttons;
        if ($state['can_cancel']) {
            $rows[] = [rx_payment_button('atlaspay', 'list_cancel', ['text' => '❌ لغو تراکنش', 'callback_data' => 'apc_can_' . $state['order_id']])];
        }
    }
    return ['lines' => $lines, 'rows' => $rows];
}

function atlaspayCustomBotListMerge($userId, array $view)
{
    $entries = atlaspayCustomBotListEntries($userId);
    if (empty($entries['lines'])) {
        return $view;
    }
    $keyboard = json_decode((string) $view['keyboard'], true);
    $rows = is_array($keyboard) && isset($keyboard['inline_keyboard']) ? $keyboard['inline_keyboard'] : [];
    $back = empty($rows) ? [rx_payment_button('pending', 'back_account', ['text' => '🔙 بازگشت', 'callback_data' => 'account'])] : array_pop($rows);
    if (function_exists('tonpayCustomUserPending') && !empty(tonpayCustomUserPending($userId, 1))) {
        $text = rtrim((string) $view['text']) . "\n\n" . implode("\n", $entries['lines']);
    } else {
        $text = "⏳ <b>تراکنش‌های در انتظار</b>\n\n" . implode("\n", $entries['lines']);
    }
    foreach ($entries['rows'] as $row) {
        $rows[] = $row;
    }
    $rows[] = $back;
    return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function atlaspayCustomRunCron()
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    atlaspayCustomPurgeCards();
    $now = time();
    $batches = [
        [
            "SELECT * FROM Payment_report
              WHERE Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND payment_Status = 'Unpaid'
                AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'manual_review')
                AND atlaspay_created_at >= ?
                AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
              ORDER BY atlaspay_last_checked_at ASC, id ASC
              LIMIT 30",
            [$now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - ATLASPAY_CUSTOM_CRON_INTERVAL_SECONDS],
        ],
        [
            "SELECT * FROM Payment_report
              WHERE Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND payment_Status IN ('Unpaid','expire','reject')
                AND atlaspay_local_state = 'manual_review'
                AND atlaspay_created_at >= ?
                AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
              ORDER BY atlaspay_last_checked_at ASC, id ASC
              LIMIT 10",
            [$now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - ATLASPAY_CUSTOM_RECHECK_SECONDS],
        ],
        [
            "SELECT * FROM Payment_report
              WHERE Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND payment_Status IN ('expire','reject')
                AND (atlaspay_local_state IS NULL OR atlaspay_local_state NOT IN ('closed','manual_review'))
                AND atlaspay_created_at >= ?
                AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
              ORDER BY atlaspay_last_checked_at ASC, id ASC
              LIMIT 10",
            [$now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - ATLASPAY_CUSTOM_RECHECK_SECONDS],
        ],
    ];
    foreach ($batches as [$sql, $params]) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[atlaspaycheck] custom select failed: ' . $e->getMessage());
            continue;
        }
        foreach ($rows as $row) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                return;
            }
            atlaspayCustomUpdateColumns($row['id_order'], ['atlaspay_last_checked_at' => time()], false);
            try {
                $result = atlaspayCustomPollOrder($row, true, false);
            } catch (Throwable $e) {
                error_log('[atlaspaycheck] custom poll threw for order ' . $row['id_order'] . ': ' . $e->getMessage());
                continue;
            }
            if ($result['state'] === 'rate_limited') {
                return;
            }
        }
    }
}

function atlaspayCustomUpdateOfficialChecked($orderId)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $stmt = $pdo->prepare("UPDATE Payment_report SET atlaspay_last_checked_at = ? WHERE id_order = ? AND payment_processing_version >= 2");
        $stmt->execute([time(), (string) $orderId]);
    } catch (Throwable $e) {
        error_log('[atlaspaycheck] mark checked failed: ' . $e->getMessage());
    }
}

function atlaspayOfficialV2Process(array $row, $check)
{
    global $pdo;
    $orderId = (string) $row['id_order'];
    $remoteId = trim((string) ($row['atlaspay_order_id'] ?? ''));
    if (!is_array($check) || !($pdo instanceof PDO)) {
        return 'error';
    }
    $statusCode = (int) ($check['status_code'] ?? 0);
    if (($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) || (array_key_exists('success', $check) && $check['success'] === false)) {
        return 'error';
    }
    $checkId = $check['id'] ?? ($check['orderId'] ?? null);
    if ($remoteId === '' || !is_numeric($checkId) || (int) $checkId !== (int) $remoteId) {
        return 'error';
    }
    if (isset($check['merchantOrderRef']) && is_scalar($check['merchantOrderRef']) && (string) $check['merchantOrderRef'] !== $orderId) {
        error_log('[atlaspaycheck] official reference mismatch for order ' . $orderId);
        return 'error';
    }
    $storedTotal = (int) ($row['atlaspay_total_amount'] ?? 0);
    if (isset($check['totalAmountToman']) && is_numeric($check['totalAmountToman']) && $storedTotal > 0 && (int) $check['totalAmountToman'] !== $storedTotal) {
        error_log('[atlaspaycheck] official total mismatch for order ' . $orderId);
        return 'error';
    }
    if (isset($check['baseAmountToman']) && is_numeric($check['baseAmountToman']) && (int) $check['baseAmountToman'] !== (int) $row['price']) {
        error_log('[atlaspaycheck] official base mismatch for order ' . $orderId);
        return 'error';
    }
    $status = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
    $late = (string) ($row['payment_Status'] ?? '') === 'expire';
    if (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
        if (!$late) {
            if ($status === 'rejected') {
                payment_notify_user_failed($orderId, 'پرداخت توسط اطلس‌پی رد شد');
            } else {
                payment_mark_expired($orderId);
            }
        }
        return 'terminal';
    }
    if (!in_array($status, ['confirmed', 'settled'], true)) {
        return 'open';
    }
    if (array_key_exists('paid', $check) && empty($check['paid'])) {
        return 'open';
    }
    $actual = isset($check['actualReceivedAmountToman']) && is_numeric($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
    $manual = !empty($check['requiresManualDelivery']) || $storedTotal <= 0 || ($actual !== null && $actual < $storedTotal);
    if ($manual) {
        try {
            $claim = $pdo->prepare(
                "UPDATE Payment_report
                    SET atlaspay_local_state = 'manual_review', atlaspay_manual_delivery = 1, atlaspay_actual_received = ?
                  WHERE id_order = ?
                    AND payment_processing_version >= 2
                    AND payment_Status IN ('Unpaid','expire')
                    AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'manual_review')"
            );
            $claim->execute([$actual, $orderId]);
            $claimed = $claim->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[atlaspaycheck] manual claim failed: ' . $e->getMessage());
            return 'error';
        }
        if ($claimed) {
            atlaspayCustomReportToAdmin(
                "⚠️ سفارش اطلس‌پی با کسری واریز تایید شد — نیاز به بررسی دستی.\n"
                . "🛒 کد فاکتور: <code>" . atlaspayCustomEscape($orderId) . "</code>\n"
                . "👤 آیدی کاربر: <code>" . atlaspayCustomEscape($row['id_user'] ?? '') . "</code>\n"
                . "💰 مبلغ درخواستی (اعتبار/خرید): " . number_format((int) $row['price']) . " تومان\n"
                . "💳 مبلغ سفارش اطلس‌پی: " . number_format($storedTotal) . " تومان\n"
                . ($actual !== null ? '💸 مبلغ واریزی واقعی: ' . number_format($actual) . ' تومان' : '💸 مبلغ واریزی واقعی: نامشخص')
            );
        }
        return 'manual';
    }
    if (!function_exists('payment_confirm_paid')) {
        require_once __DIR__ . '/../../../lib/PaymentConfirm.php';
    }
    $threadRow = select("topicid", "idreport", "report", "paymentreport", "select");
    $lines = [
        '💰 مبلغ اعتباردهی: ' . number_format((int) $row['price']) . ' تومان',
        '💸 مبلغ سفارش اطلس‌پی: ' . number_format($storedTotal) . ' تومان',
    ];
    if ($actual !== null) {
        $lines[] = '💸 مبلغ نهایی تأییدشده توسط اطلس‌پی: ' . number_format($actual) . ' تومان';
    }
    $lines[] = '🔁 تایید از طریق پولر';
    if ($late) {
        $lines[] = '⏰ تأیید دیرهنگام پس از پایان مهلت محلی';
    }
    payment_confirm_paid($orderId, 'chashbackatlaspay', [
        'method' => 'atlaspay',
        'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
        'extra_lines' => $lines,
    ]);
    return 'completed';
}
