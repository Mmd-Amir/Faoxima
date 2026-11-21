<?php

namespace Faoxima\Payments\Gateways\Tonpay;

use ManagePanel;

final class Callbacks
{
    public static function tonpay_log_event($type, $message, array $context = [])
    {
        if (function_exists('rx_log_event')) {
            rx_log_event($type, $message, $context);
            return;
        }
        $line = $type . ': ' . $message;
        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log('[tonpay] ' . $line);
    }

    public static function tonpay_process_webhook()
    {
        global $connect, $pdo, $ManagePanel, $textbotlang, $datatextbot;

        $ManagePanel = new ManagePanel();
        $rawBody = file_get_contents("php://input");

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            self::tonpay_log_event('TONPAY_BAD_BODY', 'Callback body was not valid JSON', [
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'body_excerpt' => substr((string) $rawBody, 0, 200),
            ]);
            http_response_code(400);
            exit('Invalid body');
        }

        $providedApiKey = isset($_SERVER['HTTP_X_API_KEY']) ? trim((string) $_SERVER['HTTP_X_API_KEY']) : null;
        $matchesStandardKey = false;
        $matchesCustomKey = false;
        if ($providedApiKey !== null) {
            $configuredApiKey = is_callable([\Faoxima\Payments\Gateways\Tonpay\Client::class, 'apiKey']) ? \Faoxima\Payments\Gateways\Tonpay\Client::apiKey() : '';
            $configuredCustomKey = is_callable([\Faoxima\Payments\Gateways\Tonpay\CustomClient::class, 'apiKey']) ? \Faoxima\Payments\Gateways\Tonpay\CustomClient::apiKey() : '';
            $matchesStandardKey = $configuredApiKey !== '' && hash_equals($configuredApiKey, $providedApiKey);
            $matchesCustomKey = $configuredCustomKey !== '' && hash_equals($configuredCustomKey, $providedApiKey);
            if (!$matchesStandardKey && !$matchesCustomKey) {
                self::tonpay_log_event('TONPAY_BAD_API_KEY', 'Callback X-API-Key header did not match the configured key', [
                    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                http_response_code(401);
                exit('Unauthorized');
            }
        }

        $orderId = $data['order_id'] ?? null;
        if ($orderId === null || (!is_string($orderId) && !is_numeric($orderId))) {
            self::tonpay_log_event('TONPAY_NO_ID', 'Callback missing order_id', [
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'keys' => array_keys($data),
            ]);
            http_response_code(400);
            exit('Missing order id');
        }
        $orderId = (string) $orderId;
        if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderId)) {
            self::tonpay_log_event('TONPAY_BAD_ID_FORMAT', 'order_id failed format check', [
                'order_id_excerpt' => substr($orderId, 0, 40),
            ]);
            http_response_code(400);
            exit('Invalid order id');
        }

        $invoiceId = $data['invoice_id'] ?? null;
        if ($invoiceId === null || !is_string($invoiceId) || trim($invoiceId) === '') {
            self::tonpay_log_event('TONPAY_NO_INVOICE_ID', 'Callback missing invoice_id', [
                'order_id' => $orderId,
            ]);
            http_response_code(400);
            exit('Missing invoice id');
        }
        $invoiceId = trim($invoiceId);

        $lookupStmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'tonpay' LIMIT 1");
        $lookupStmt->bind_param('s', $orderId);
        $lookupStmt->execute();
        $Payment_report = $lookupStmt->get_result()->fetch_assoc();
        $lookupStmt->close();
        if (!is_array($Payment_report)) {
            self::tonpay_log_event('TONPAY_UNKNOWN_ORDER', 'Payment_report row not found', [
                'order_id' => $orderId,
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            http_response_code(404);
            exit('Order not found');
        }
        $isCustomInvoice = (string) ($Payment_report['tonpay_gateway_mode'] ?? '') === 'custom';
        if (($Payment_report['payment_Status'] == "cancelled" && !$isCustomInvoice) || ($Payment_report['payment_Status'] == "expire" && !$isCustomInvoice)) {
            return;
        }
        if ($providedApiKey !== null && ($isCustomInvoice ? !$matchesCustomKey : !$matchesStandardKey)) {
            self::tonpay_log_event('TONPAY_KEY_KIND_MISMATCH', 'Callback X-API-Key does not belong to the gateway mode of this invoice', [
                'order_id' => $orderId,
                'custom_invoice' => $isCustomInvoice,
            ]);
            http_response_code(401);
            exit('Unauthorized');
        }

        $storedInvoiceId = trim((string) ($Payment_report['tonpay_invoice_id'] ?? ''));
        if ($storedInvoiceId !== '' && $storedInvoiceId !== $invoiceId) {
            self::tonpay_log_event('TONPAY_INVOICE_MISMATCH', 'invoice_id does not match stored value for this order', [
                'order_id' => $orderId,
                'expected' => $storedInvoiceId,
                'received' => $invoiceId,
            ]);
            http_response_code(409);
            exit('Invoice mismatch');
        }

        if ($Payment_report['payment_Status'] == "paid" && (int)($Payment_report['direct_payment_done'] ?? 0) === 1) {
            exit('Already processed');
        }

        if ($isCustomInvoice) {
            $check = is_callable([\Faoxima\Payments\Gateways\Tonpay\CustomClient::class, 'checkInvoice']) ? \Faoxima\Payments\Gateways\Tonpay\CustomClient::checkInvoice($invoiceId) : null;
        } else {
            $check = is_callable([\Faoxima\Payments\Gateways\Tonpay\Client::class, 'checkInvoice']) ? \Faoxima\Payments\Gateways\Tonpay\Client::checkInvoice($invoiceId) : null;
        }
        if (is_callable([\Faoxima\Payments\Gateways\Tonpay\RateLimit::class, 'isRateLimited']) && \Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($check)) {
            self::tonpay_log_event('TONPAY_CHECK_RATE_LIMITED', 'Server-side check deferred by rate limit; payment not confirmed', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
            ]);
            http_response_code(503);
            exit('Retry later');
        }
        if (!is_array($check) || (string) ($check['order_id'] ?? '') !== $orderId) {
            self::tonpay_log_event('TONPAY_CHECK_MISMATCH', 'Server-side check did not confirm this order', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'check' => $check,
            ]);
            http_response_code(409);
            exit('Verification failed');
        }
        if ($isCustomInvoice) {
            \Faoxima\Payments\Gateways\Tonpay\Settlement::applyRemote($pdo, $Payment_report, $check);
        }
        $remoteStatusLower = strtolower((string) ($check['status'] ?? ''));
        if ($isCustomInvoice && in_array($remoteStatusLower, ['action_required_less', 'action_required_more'], true)) {
            self::tonpay_log_event('TONPAY_ACTION_REQUIRED', 'Invoice requires buyer action', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'status' => $remoteStatusLower,
                'need_action_kind' => $check['need_action_kind'] ?? null,
            ]);
            exit('Action required');
        }
        if (empty($check['paid']) || (string) ($check['status'] ?? '') !== 'completed') {
            self::tonpay_log_event('TONPAY_NOT_PAID', 'Server-side check reports order not paid', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'status' => $check['status'] ?? null,
            ]);
            $keepReviewOpen = $isCustomInvoice && strtolower((string) ($check['status'] ?? '')) === 'rejected';
            if (is_callable([\Faoxima\Payments\Gateways\Tonpay\Client::class, 'syncTerminalStatus']) && !$keepReviewOpen) {
                \Faoxima\Payments\Gateways\Tonpay\Client::syncTerminalStatus($pdo, $orderId, $check['status'] ?? '');
            }
            exit('Not paid');
        }

        if (!\Faoxima\Payments\Gateways\Tonpay\Client::amountsMatch($check['request_amount'] ?? null, $Payment_report['price'] ?? null)) {
            \Faoxima\Payments\Gateways\Tonpay\Client::logAmountMismatch('callback', $orderId, $invoiceId, $Payment_report['price'] ?? null, $check['request_amount'] ?? null);
            http_response_code(409);
            exit('Amount mismatch');
        }

        $finalAmount = isset($check['final_amount']) ? (float) $check['final_amount'] : null;
        if ($finalAmount === null) {
            self::tonpay_log_event('TONPAY_AMOUNT_MISSING', 'Check response lacks final_amount', [
                'order_id' => $orderId,
            ]);
            http_response_code(400);
            exit('Amount missing');
        }

        $creditAmount = $finalAmount;

        if ($isCustomInvoice && $Payment_report['payment_Status'] == "cancelled") {
            \Faoxima\Payments\Gateways\Tonpay\Settlement::reopenCancelledForConfirmation($pdo, $orderId);
        }

        if (!\Faoxima\Payments\Settlement\CallbackClaims::claimResumableOnline($connect, $orderId, $Payment_report)) {
            self::tonpay_log_event('TONPAY_DUPLICATE', 'Duplicate or already-paid callback ignored', [
                'order_id' => $orderId,
            ]);
            exit('Already processed');
        }

        if ($isCustomInvoice) {
            \Faoxima\Payments\Gateways\Tonpay\Settlement::updateColumns($pdo, $orderId, ['tonpay_remote_status' => 'completed', 'tonpay_local_state' => 'completed'], false);
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
        $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange('../text.json');

        $settled = \Faoxima\Payments\Settlement\CallbackClaims::settleDelivery(
            $pdo, (string) $orderId, $Payment_report, [\Faoxima\Payments\Gateways\GatewayRuntime::class, 'DirectPayment'], '../images.jpg', 'chashbacktonpay', 'هدیه بازگشت وجه تون‌پی',
            static fn ($key) => \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $key, 'select')['ValuePay'] ?? null
        );
        $Balance_id = $settled['customer'];
        if (!is_array($Balance_id)) {
            self::tonpay_log_event('TONPAY_USER_MISSING', 'Linked user row not found after paid update', [
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
        $rxFmtCreditAmount = \Faoxima\Core\Support\Formatting::rxFormatToman($creditAmount);
        $rxFeeLine = $isCustomInvoice
            ? "
<blockquote>- 🧾 کارمزد تون‌پی : " . \Faoxima\Core\Support\Formatting::rxFormatToman(max(0, $creditAmount - (float) $price)) . " تومان (به کیف پول اضافه نشد)</blockquote>"
            : '';
        $text_reportpayment = "💵 پرداخت جدید
<blockquote>- 👤 نام کاربری کاربر : @{$usernameEsc}</blockquote>
<blockquote>- 👤 آیدی عددی کاربر : {$rlm}<code>{$userIdEsc}</code></blockquote>
<blockquote>- 💰 مبلغ اعتباردهی : {$rxFmtPrice} تومان</blockquote>
<blockquote>- 💸 مبلغ نهایی تأییدشده توسط تون‌پی : {$rxFmtCreditAmount} تومان</blockquote>{$rxFeeLine}
<blockquote>- 💳 روش پرداخت : تون‌پی</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => $text_reportpayment,
                'parse_mode' => "HTML"
            ]);
        }
    }
}
