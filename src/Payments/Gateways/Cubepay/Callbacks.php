<?php

namespace Faoxima\Payments\Gateways\Cubepay;

use ManagePanel;

final class Callbacks
{
    public static function cubepay_log_event($type, $message, array $context = [])
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

    public static function cubepay_finalize_paid_order($orderId, $Payment_report, $methodLabel)
    {
        global $connect, $pdo;

        if (!\Faoxima\Payments\Settlement\CallbackClaims::claimResumableOnline($connect, $orderId, $Payment_report)) {
            self::cubepay_log_event('CUBEPAY_DUPLICATE', 'Duplicate or already-paid callback ignored', [
                'order_id' => $orderId,
            ]);
            exit('Already processed');
        }

        echo "پرداخت با موفقیت انجام شد";

        $setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
        $price = $Payment_report['price'];

        $datatextbotget = \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll");
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
            if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
                $datatextbot[$item['id_text']] = $item['text'];
            }
        }
        $GLOBALS['textbotlang'] = \Faoxima\Telegram\Conversation\ConversationData::languagechange('../text.json');
        // DirectPayment() این آرایه را با global می‌خواند، پس باید در دامنه‌ی
        // سراسری باشد؛ اینجا داخل تابع ساخته می‌شود و بدون این خط، متنِ
        // «سرویس با موفقیت ایجاد شد» خالی می‌ماند و تلگرام پیام را رد می‌کند.
        $GLOBALS['datatextbot'] = $datatextbot;

        $settled = \Faoxima\Payments\Settlement\CallbackClaims::settleDelivery(
            $pdo, (string) $orderId, $Payment_report, [\Faoxima\Payments\Gateways\GatewayRuntime::class, 'DirectPayment'], '../images.jpg', 'chashbackcubepay', 'هدیه بازگشت وجه کیوب‌پی',
            static fn ($key) => \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $key, 'select')['ValuePay'] ?? null
        );
        $Balance_id = $settled['customer'];
        if (!is_array($Balance_id)) {
            self::cubepay_log_event('CUBEPAY_USER_MISSING', 'Linked user row not found after paid update', [
                'order_id' => $orderId,
                'id_user' => $Payment_report['id_user'] ?? null,
            ]);
            $Balance_id = ['id' => $Payment_report['id_user'] ?? '', 'username' => '—', 'Balance' => 0];
        }
        $Balance_id ??= ['id' => $Payment_report['id_user'] ?? '', 'username' => '—', 'Balance' => 0];
        $result = $settled['cashback'];
        if ($result !== null) {
            $text_report = "🎁 کاربر عزیز مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($result) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
            sendmessage($Balance_id['id'], $text_report, null, 'HTML');
        }

        $paymentreports = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
        $usernameEsc = htmlspecialchars((string) $Balance_id['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $userIdEsc = htmlspecialchars((string) $Balance_id['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rlm = "\xE2\x80\x8F";
        $rxFmtPrice = \Faoxima\Core\Support\Formatting::rxFormatToman($price);
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

    public static function cubepay_lookup_by_order($orderId)
    {
        global $connect;
        $stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'cubepay' LIMIT 1");
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) ? $row : null;
    }

    public static function cubepay_lookup_by_authority($authority)
    {
        global $connect;
        $stmt = $connect->prepare("SELECT * FROM Payment_report WHERE cubepay_authority = ? AND Payment_Method = 'cubepay' LIMIT 1");
        $stmt->bind_param('s', $authority);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) ? $row : null;
    }

    public static function cubepay_process_card_callback($authority, $orderIdHint)
    {
        global $ManagePanel;
        $ManagePanel = new ManagePanel();

        if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $authority)) {
            self::cubepay_log_event('CUBEPAY_BAD_AUTHORITY', 'authority failed format check', [
                'authority_excerpt' => substr($authority, 0, 40),
            ]);
            http_response_code(400);
            exit('Invalid authority');
        }

        $Payment_report = self::cubepay_lookup_by_authority($authority);
        $backfillAuthority = false;
        if ($Payment_report === null && $orderIdHint !== null && $orderIdHint !== '') {
            $byOrder = self::cubepay_lookup_by_order($orderIdHint);
            if ($byOrder !== null && trim((string) ($byOrder['cubepay_authority'] ?? '')) === '') {
                $Payment_report = $byOrder;
                $backfillAuthority = true;
            }
        }
        if ($Payment_report === null) {
            self::cubepay_log_event('CUBEPAY_UNKNOWN_AUTHORITY', 'Payment_report row not found', [
                'authority' => $authority,
                'order_id_hint' => $orderIdHint,
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            http_response_code(404);
            exit('Order not found');
        }
        if ($Payment_report['payment_Status'] == "expire" || $Payment_report['payment_Status'] == "cancelled") {
            return;
        }
        if ($orderIdHint !== null && (string) $orderIdHint !== (string) $Payment_report['id_order']) {
            self::cubepay_log_event('CUBEPAY_ORDER_MISMATCH', 'order_id does not match stored value for this authority', [
                'authority' => $authority,
                'expected' => $Payment_report['id_order'],
                'received' => $orderIdHint,
            ]);
            http_response_code(409);
            exit('Order mismatch');
        }
        $orderId = (string) $Payment_report['id_order'];

        if ($Payment_report['payment_Status'] == "paid" && (int)($Payment_report['direct_payment_done'] ?? 0) === 1) {
            exit('Already processed');
        }

        $verify = is_callable([\Faoxima\Payments\Gateways\Cubepay\Client::class, 'verifyPayment']) ? \Faoxima\Payments\Gateways\Cubepay\Client::verifyPayment($authority) : null;
        if (!is_array($verify) || (string) ($verify['order_id'] ?? '') !== $orderId) {
            self::cubepay_log_event('CUBEPAY_VERIFY_MISMATCH', 'Server-side verify did not confirm this order', [
                'order_id' => $orderId,
                'authority' => $authority,
                'verify' => $verify,
            ]);
            http_response_code(409);
            exit('Verification failed');
        }
        if (empty($verify['success'])) {
            $verifyStatusCode = (int) ($verify['status_code'] ?? 0);
            self::cubepay_log_event('CUBEPAY_NOT_PAID', 'Server-side verify reports payment not confirmed', [
                'order_id' => $orderId,
                'authority' => $authority,
                'message' => $verify['message'] ?? null,
                'status_code' => $verifyStatusCode,
            ]);
            if ($verifyStatusCode === 410) {
                $reasonFa = (string) ($verify['message'] ?? '') !== ''
                    ? (string) $verify['message']
                    : 'مهلت تراکنش کارت‌به‌کارت کیوب‌پی تمام شده یا ناموفق بوده است';
                \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_notify_user_failed($orderId, $reasonFa);
            }
            exit('Not paid');
        }

        if (!is_numeric($verify['amount'] ?? null) || (float) $verify['amount'] < (float) $Payment_report['price']) {
            http_response_code(409);
            exit('Amount mismatch');
        }
        if ($backfillAuthority) {
            global $connect;
            \Faoxima\Payments\Gateways\Cubepay\Client::backfillAuthority($connect, $orderId, $authority);
        }
        self::cubepay_finalize_paid_order($orderId, $Payment_report, 'کارت به کارت');
    }

    public static function cubepay_process_crypto_callback($orderId, $status, $amount, $sig, $paymentId, $payCurrency)
    {
        global $ManagePanel;
        $ManagePanel = new ManagePanel();

        if (!is_callable([\Faoxima\Payments\Gateways\Cubepay\Client::class, 'verifyCryptoCallbackSignature']) || !\Faoxima\Payments\Gateways\Cubepay\Client::verifyCryptoCallbackSignature($orderId, $status, $amount, $sig)) {
            self::cubepay_log_event('CUBEPAY_CRYPTO_BAD_SIG', 'Crypto callback signature mismatch', [
                'order_id' => $orderId,
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            http_response_code(403);
            exit('Invalid signature');
        }

        $Payment_report = self::cubepay_lookup_by_order($orderId);
        if ($Payment_report === null) {
            self::cubepay_log_event('CUBEPAY_UNKNOWN_ORDER', 'Payment_report row not found for crypto callback', [
                'order_id' => $orderId,
            ]);
            http_response_code(404);
            exit('Order not found');
        }
        if ($Payment_report['payment_Status'] == "expire" || $Payment_report['payment_Status'] == "cancelled") {
            return;
        }
        if ($Payment_report['payment_Status'] == "paid" && (int)($Payment_report['direct_payment_done'] ?? 0) === 1) {
            exit('Already processed');
        }

        if ($status !== 'paid') {
            self::cubepay_log_event('CUBEPAY_CRYPTO_NOT_PAID', 'Crypto callback reports non-paid status', [
                'order_id' => $orderId,
                'status' => $status,
            ]);
            if ($status === 'expired') {
                \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_mark_expired($orderId);
            } elseif ($status === 'failed') {
                \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_notify_user_failed($orderId, 'پرداخت ارز دیجیتال توسط کیوب‌پی ناموفق بود');
            }
            exit('Not paid');
        }

        self::cubepay_finalize_paid_order($orderId, $Payment_report, 'ارز دیجیتال' . ($payCurrency !== '' ? " ({$payCurrency})" : ''));
    }

    public static function cubepay_process_webhook()
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
            self::cubepay_process_card_callback((string) $authority, $orderId !== null ? (string) $orderId : null);
            return;
        }

        if ($sig !== null && $sig !== '') {
            $orderIdStr = trim((string) ($orderId ?? ''));
            $status = trim((string) ($bodyData['status'] ?? ($_REQUEST['status'] ?? '')));
            $amount = trim((string) ($bodyData['amount'] ?? ($_REQUEST['amount'] ?? '')));
            $paymentId = trim((string) ($bodyData['payment_id'] ?? ($_REQUEST['payment_id'] ?? '')));
            $payCurrency = trim((string) ($bodyData['pay_currency'] ?? ($_REQUEST['pay_currency'] ?? '')));

            if ($orderIdStr === '' || $status === '') {
                self::cubepay_log_event('CUBEPAY_CRYPTO_INCOMPLETE', 'Crypto callback missing order_id or status', [
                    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                http_response_code(400);
                exit('Incomplete crypto callback');
            }

            self::cubepay_process_crypto_callback($orderIdStr, $status, $amount, (string) $sig, $paymentId, $payCurrency);
            return;
        }

        self::cubepay_log_event('CUBEPAY_NO_AUTHORITY', 'Callback missing authority and sig', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        http_response_code(400);
        exit('Missing authority');
    }
}
