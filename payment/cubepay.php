<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../lib/PaymentConfirm.php';

function cubepay_log_event($type, $message, array $context = [])
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    $line = $type . ': ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log('[cubepay] ' . $line);
}

function cubepay_finalize_paid_order($orderId, $Payment_report, $methodLabel)
{
    global $connect;

    $atomic = $connect->prepare(
        "UPDATE Payment_report SET payment_Status = ? WHERE id_order = ? AND payment_Status <> 'paid'"
    );
    $statusPaid = 'paid';
    $atomic->bind_param('ss', $statusPaid, $orderId);
    $atomic->execute();
    $affected = $atomic->affected_rows;
    $atomic->close();
    if ($affected < 1) {
        cubepay_log_event('CUBEPAY_DUPLICATE', 'Duplicate or already-paid callback ignored', [
            'order_id' => $orderId,
        ]);
        exit('Already processed');
    }

    echo "پرداخت با موفقیت انجام شد";

    $setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
    $price = $Payment_report['price'];

    $datatextbotget = select("textbot", "*", null, null, "fetchAll");
    $datatxtbot = array();
    foreach ($datatextbotget as $row) {
        $datatxtbot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    $datatextbot = array(
        'textafterpay' => '',
        'textaftertext' => '',
        'textmanual' => '',
        'textselectlocation' => '',
        'text_wgdashboard' => ''
    );
    foreach ($datatxtbot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
    $GLOBALS['textbotlang'] = languagechange('../text.json');
    // DirectPayment() این آرایه را با global می‌خواند، پس باید در دامنه‌ی
    // سراسری باشد؛ اینجا داخل تابع ساخته می‌شود و بدون این خط، متنِ
    // «سرویس با موفقیت ایجاد شد» خالی می‌ماند و تلگرام پیام را رد می‌کند.
    $GLOBALS['datatextbot'] = $datatextbot;

    DirectPayment($orderId, "../images.jpg");

    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackcubepay", "select")['ValuePay'];
    $balanceLookup = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
    $balanceLookup->bind_param('s', $Payment_report['id_user']);
    $balanceLookup->execute();
    $Balance_id = $balanceLookup->get_result()->fetch_assoc();
    $balanceLookup->close();
    if (!is_array($Balance_id)) {
        cubepay_log_event('CUBEPAY_USER_MISSING', 'Linked user row not found after paid update', [
            'order_id' => $orderId,
            'id_user' => $Payment_report['id_user'] ?? null,
        ]);
        $Balance_id = ['id' => $Payment_report['id_user'] ?? '', 'username' => '—', 'Balance' => 0];
    }
    $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
        || rx_cashbackEligibleForKey("chashbackcubepay", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
    if ($cashbackEligible && $pricecashback != "0") {
        $result = round(($Payment_report['price'] * $pricecashback) / 100);
        $Balance_confrim = intval($Balance_id['Balance']) + $result;
        update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
        $pricecashback = number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ " . rxFormatToman($result) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }

    $paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
    $usernameEsc = htmlspecialchars((string) $Balance_id['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $userIdEsc = htmlspecialchars((string) $Balance_id['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rlm = "\xE2\x80\x8F";
    $rxFmtPrice = rxFormatToman($price);
    $text_reportpayment = "💵 پرداخت جدید
<blockquote>- 👤 نام کاربری کاربر : @{$usernameEsc}</blockquote>
<blockquote>- 👤 آیدی عددی کاربر : {$rlm}<code>{$userIdEsc}</code></blockquote>
<blockquote>- 💰 مبلغ اعتباردهی : {$rxFmtPrice} تومان</blockquote>
<blockquote>- 💳 روش پرداخت : کیوب‌پی ({$methodLabel})</blockquote>";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_reportpayment,
            'parse_mode' => "HTML"
        ]);
    }
}

function cubepay_lookup_by_order($orderId)
{
    global $connect;
    $stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'cubepay' LIMIT 1");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? $row : null;
}

function cubepay_lookup_by_authority($authority)
{
    global $connect;
    $stmt = $connect->prepare("SELECT * FROM Payment_report WHERE cubepay_authority = ? LIMIT 1");
    $stmt->bind_param('s', $authority);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? $row : null;
}

function cubepay_process_card_callback($authority, $orderIdHint)
{
    global $ManagePanel;
    $ManagePanel = new ManagePanel();

    if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $authority)) {
        cubepay_log_event('CUBEPAY_BAD_AUTHORITY', 'authority failed format check', [
            'authority_excerpt' => substr($authority, 0, 40),
        ]);
        http_response_code(400);
        exit('Invalid authority');
    }

    $Payment_report = cubepay_lookup_by_authority($authority);
    if ($Payment_report === null && $orderIdHint !== null && $orderIdHint !== '') {
        $byOrder = cubepay_lookup_by_order($orderIdHint);
        if ($byOrder !== null && trim((string) ($byOrder['cubepay_authority'] ?? '')) === '') {
            $Payment_report = $byOrder;
            global $connect;
            $backfill = $connect->prepare("UPDATE Payment_report SET cubepay_authority = ? WHERE id_order = ? AND (cubepay_authority IS NULL OR cubepay_authority = '')");
            $backfill->bind_param('ss', $authority, $orderIdHint);
            $backfill->execute();
            $backfill->close();
        }
    }
    if ($Payment_report === null) {
        cubepay_log_event('CUBEPAY_UNKNOWN_AUTHORITY', 'Payment_report row not found', [
            'authority' => $authority,
            'order_id_hint' => $orderIdHint,
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        http_response_code(404);
        exit('Order not found');
    }
    if ($Payment_report['payment_Status'] == "expire") {
        return;
    }
    if ($orderIdHint !== null && (string) $orderIdHint !== (string) $Payment_report['id_order']) {
        cubepay_log_event('CUBEPAY_ORDER_MISMATCH', 'order_id does not match stored value for this authority', [
            'authority' => $authority,
            'expected' => $Payment_report['id_order'],
            'received' => $orderIdHint,
        ]);
        http_response_code(409);
        exit('Order mismatch');
    }
    $orderId = (string) $Payment_report['id_order'];

    if ($Payment_report['payment_Status'] == "paid") {
        exit('Already processed');
    }

    $verify = function_exists('cubepayVerifyPayment') ? cubepayVerifyPayment($authority) : null;
    if (!is_array($verify) || (string) ($verify['order_id'] ?? '') !== $orderId) {
        cubepay_log_event('CUBEPAY_VERIFY_MISMATCH', 'Server-side verify did not confirm this order', [
            'order_id' => $orderId,
            'authority' => $authority,
            'verify' => $verify,
        ]);
        http_response_code(409);
        exit('Verification failed');
    }
    if (empty($verify['success'])) {
        $verifyStatusCode = (int) ($verify['status_code'] ?? 0);
        cubepay_log_event('CUBEPAY_NOT_PAID', 'Server-side verify reports payment not confirmed', [
            'order_id' => $orderId,
            'authority' => $authority,
            'message' => $verify['message'] ?? null,
            'status_code' => $verifyStatusCode,
        ]);
        if ($verifyStatusCode === 410) {
            $reasonFa = (string) ($verify['message'] ?? '') !== ''
                ? (string) $verify['message']
                : 'مهلت تراکنش کارت‌به‌کارت کیوب‌پی تمام شده یا ناموفق بوده است';
            payment_notify_user_failed($orderId, $reasonFa);
        }
        exit('Not paid');
    }

    cubepay_finalize_paid_order($orderId, $Payment_report, 'کارت به کارت');
}

function cubepay_process_crypto_callback($orderId, $status, $amount, $sig, $paymentId, $payCurrency)
{
    global $ManagePanel;
    $ManagePanel = new ManagePanel();

    if (!function_exists('cubepayVerifyCryptoCallbackSignature') || !cubepayVerifyCryptoCallbackSignature($orderId, $status, $amount, $sig)) {
        cubepay_log_event('CUBEPAY_CRYPTO_BAD_SIG', 'Crypto callback signature mismatch', [
            'order_id' => $orderId,
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        http_response_code(403);
        exit('Invalid signature');
    }

    $Payment_report = cubepay_lookup_by_order($orderId);
    if ($Payment_report === null) {
        cubepay_log_event('CUBEPAY_UNKNOWN_ORDER', 'Payment_report row not found for crypto callback', [
            'order_id' => $orderId,
        ]);
        http_response_code(404);
        exit('Order not found');
    }
    if ($Payment_report['payment_Status'] == "expire") {
        return;
    }
    if ($Payment_report['payment_Status'] == "paid") {
        exit('Already processed');
    }

    if ($status !== 'paid') {
        cubepay_log_event('CUBEPAY_CRYPTO_NOT_PAID', 'Crypto callback reports non-paid status', [
            'order_id' => $orderId,
            'status' => $status,
        ]);
        if ($status === 'expired') {
            payment_mark_expired($orderId);
        } elseif ($status === 'failed') {
            payment_notify_user_failed($orderId, 'پرداخت ارز دیجیتال توسط کیوب‌پی ناموفق بود');
        }
        exit('Not paid');
    }

    cubepay_finalize_paid_order($orderId, $Payment_report, 'ارز دیجیتال' . ($payCurrency !== '' ? " ({$payCurrency})" : ''));
}

function cubepay_process_webhook()
{
    $rawBody = file_get_contents("php://input");
    $bodyData = json_decode($rawBody, true);
    if (!is_array($bodyData)) {
        $bodyData = [];
    }

    $authority = $bodyData['authority'] ?? ($_REQUEST['authority'] ?? null);
    $sig = $bodyData['sig'] ?? ($_REQUEST['sig'] ?? null);
    $orderId = $bodyData['order_id'] ?? ($_REQUEST['order_id'] ?? null);

    if ($authority !== null && ($authority !== '')) {
        cubepay_process_card_callback((string) $authority, $orderId !== null ? (string) $orderId : null);
        return;
    }

    if ($sig !== null && $sig !== '') {
        $orderIdStr = trim((string) ($orderId ?? ''));
        $status = trim((string) ($bodyData['status'] ?? ($_REQUEST['status'] ?? '')));
        $amount = trim((string) ($bodyData['amount'] ?? ($_REQUEST['amount'] ?? '')));
        $paymentId = trim((string) ($bodyData['payment_id'] ?? ($_REQUEST['payment_id'] ?? '')));
        $payCurrency = trim((string) ($bodyData['pay_currency'] ?? ($_REQUEST['pay_currency'] ?? '')));

        if ($orderIdStr === '' || $status === '') {
            cubepay_log_event('CUBEPAY_CRYPTO_INCOMPLETE', 'Crypto callback missing order_id or status', [
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            http_response_code(400);
            exit('Incomplete crypto callback');
        }

        cubepay_process_crypto_callback($orderIdStr, $status, $amount, (string) $sig, $paymentId, $payCurrency);
        return;
    }

    cubepay_log_event('CUBEPAY_NO_AUTHORITY', 'Callback missing authority and sig', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(400);
    exit('Missing authority');
}

cubepay_process_webhook();
