<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;




$ManagePanel = new ManagePanel();
$rawBody = file_get_contents("php://input");

$ipnSigningKey = trim((string) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "ipnsigningkeytronado", "select")['ValuePay']);
$signatureHeader = $_SERVER['HTTP_X_TRONADO_SIG'] ?? '';
if ($ipnSigningKey === '') {
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_SIGNING_KEY_MISSING', 'IpnSigningKey is not configured', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(500);
    exit('Signing key not configured');
}
$expectedSignature = hash_hmac('sha512', (string) $rawBody, $ipnSigningKey);
$signatureValid = is_string($signatureHeader) && $signatureHeader !== '' && hash_equals($expectedSignature, strtolower($signatureHeader));
if (!$signatureValid) {
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_BAD_SIGNATURE', 'X-Tronado-Sig verification failed', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(401);
    exit('Invalid signature');
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_BAD_BODY', 'Callback body was not valid JSON', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'body_excerpt' => substr((string) $rawBody, 0, 200),
    ]);
    http_response_code(400);
    exit('Invalid body');
}

$paymentId = $data['PaymentId'] ?? ($data['PaymentID'] ?? null);
if ($paymentId === null) {
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_NO_ID', 'Callback missing payment identifier', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'keys' => array_keys($data),
    ]);
    http_response_code(400);
    exit('Missing payment identifier');
}


if (!is_string($paymentId) && !is_numeric($paymentId)) {
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_BAD_ID_TYPE', 'PaymentID had unexpected type', [
        'type' => gettype($paymentId),
    ]);
    http_response_code(400);
    exit('Invalid payment identifier');
}
$paymentId = (string) $paymentId;
if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $paymentId)) {
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_BAD_ID_FORMAT', 'PaymentID failed format check', [
        'payment_id_excerpt' => substr($paymentId, 0, 40),
    ]);
    http_response_code(400);
    exit('Invalid payment identifier');
}

$lookupStmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'iranpay2' LIMIT 1");
$lookupStmt->bind_param('s', $paymentId);
$lookupStmt->execute();
$Payment_report = $lookupStmt->get_result()->fetch_assoc();
$lookupStmt->close();
if (!is_array($Payment_report)) {
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_UNKNOWN_ORDER', 'Payment_report row not found', [
        'payment_id' => $paymentId,
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(404);
    exit('Order not found');
}
$rxTronadoV2 = is_callable([\Faoxima\Payments\Settlement\GatewayFlows::class, 'paymentV2Is']) && \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Is($Payment_report);
if ($Payment_report['payment_Status'] == "expire" && !$rxTronadoV2) {
    // The order expired on this side, but Tronado only sends a paid callback once the buyer
    // actually paid - so the money exists and this bot is about to credit nothing. A bare
    // return answers HTTP 200, which Tronado records as a delivered callback: it never retries
    // and never reports, so the payment is lost silently. 409 is a permanent client error on
    // their side, so they stop retrying at once AND raise the failure to the shop, which can
    // then settle the order by hand.
    \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_PAID_AFTER_EXPIRE', 'Paid callback arrived for an expired order', [
        'payment_id' => $paymentId,
    ]);
    http_response_code(409);
    exit('Order already expired');
}
$setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
$price = $Payment_report['price'];
    $datatextbotget = \Faoxima\Core\Database\Db::select("textbot", "*",null ,null ,"fetchAll");
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
    if ($rxTronadoV2 && is_callable([\Faoxima\Payments\Gateways\Tronado\Settlement::class, 'handleCallback'])) {
        $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange('../text.json');
        [$rxTronadoV2Code, $rxTronadoV2Body] = \Faoxima\Payments\Gateways\Tronado\Settlement::handleCallback($pdo, $setting, $data, $Payment_report);
        http_response_code($rxTronadoV2Code);
        exit($rxTronadoV2Body);
    }
    $orderStatusId = isset($data['OrderStatusID']) ? (int) $data['OrderStatusID'] : null;
    $isPaid = ($orderStatusId === 30) || ($data['IsPaid'] ?? false);

    $dedupeKey = $paymentId . '|' . ($orderStatusId ?? 'null');
    if (\Faoxima\Payments\Gateways\Tronado\Settlement::isCompletedLegacyEvent($connect, (string) $paymentId, $dedupeKey)) {
        \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_DUPLICATE', 'Duplicate (PaymentId, OrderStatusID) callback ignored', [
            'payment_id' => $paymentId,
            'order_status_id' => $orderStatusId,
        ]);
        exit('Already processed');
    }

    if($Payment_report['payment_Status'] != "paid" || ($isPaid && (int)($Payment_report['direct_payment_done'] ?? 0) === 0)){
        if($isPaid){

            $userPaidTomanAmount = isset($data['UserPaidTomanAmount']) ? (float) $data['UserPaidTomanAmount'] : null;
            $tomanAmountWithoutWage = isset($data['TomanAmountWithoutWage']) ? (float) $data['TomanAmountWithoutWage'] : null;
            if ($userPaidTomanAmount === null || $tomanAmountWithoutWage === null) {
                \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_AMOUNT_MISSING', 'Callback lacks v5 Toman amount fields', [
                    'payment_id' => $paymentId,
                ]);
                http_response_code(400);
                exit('Amount missing');
            }

            $wageFromBusinessPercentage = (int) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "wageFromBusinessPercentageTronado", "select")['ValuePay'];
            $tronAmountDelivered = isset($data['TronAmount']) ? (float) $data['TronAmount'] : null;
            $actualTronAmount = isset($data['ActualTronAmount']) ? (float) $data['ActualTronAmount'] : null;

            if ($wageFromBusinessPercentage <= 0) {
                $creditAmount = $tomanAmountWithoutWage;
            } elseif ($wageFromBusinessPercentage >= 100) {
                $creditAmount = $userPaidTomanAmount;
            } elseif ($actualTronAmount !== null && $actualTronAmount > 0 && $tronAmountDelivered !== null && $tronAmountDelivered > 0) {
                $tronPriceToman = $tomanAmountWithoutWage / $tronAmountDelivered;
                $creditAmount = $actualTronAmount * $tronPriceToman;
            } else {
                $creditAmount = $tomanAmountWithoutWage;
            }

            if (!\Faoxima\Payments\Gateways\Tronado\Settlement::claimLegacyPaid($connect, (string) $paymentId, $dedupeKey)) {
                \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_DUPLICATE', 'Duplicate or already-paid callback ignored', [
                    'payment_id' => $paymentId,
                ]);
                exit('Already processed');
            }

            echo "پرداخت با موفقیت انجام  شد";
    $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange('../text.json');
    $settled = \Faoxima\Payments\Settlement\CallbackClaims::settleDelivery(
        $pdo, (string) $paymentId, $Payment_report, [\Faoxima\Payments\Gateways\GatewayRuntime::class, 'DirectPayment'], '../images.jpg', 'chashbackiranpay2', 'هدیه بازگشت وجه ترنادو',
        static fn ($key) => \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $key, 'select')['ValuePay'] ?? null
    );
    $Balance_id = $settled['customer'];
    if (!is_array($Balance_id)) {
        \Faoxima\Payments\Gateways\Tronado\Callbacks::tronado_log_event('TRONADO_USER_MISSING', 'Linked user row not found after paid update', [
            'payment_id' => $paymentId,
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
    $paymentreports = \Faoxima\Core\Database\Db::select("topicid","idreport","report","paymentreport","select")['idreport'];
    $balancelow = '';
    if(isset($data['TronAmount'], $data['ActualTronAmount']) && (float) $data['TronAmount'] < (float) $data['ActualTronAmount']){
        $balancelow = "❌ کاربر کمتر از مبلغ تعیین شده واریز کرده است.";
    }
$rxFmtPrice = \Faoxima\Core\Support\Formatting::rxFormatToman($price);
$rxFmtCreditAmount = \Faoxima\Core\Support\Formatting::rxFormatToman($creditAmount);
$text_reportpayment = "💵 پرداخت جدید
$balancelow
<blockquote>- 👤 نام کاربری کاربر : @{$Balance_id['username']}</blockquote>
<blockquote>- 👤 آیدی عددی کاربر : <code>{$Balance_id['id']}</code></blockquote>
<blockquote>- 💸 مبلغ تراکنش {$rxFmtPrice}</blockquote>
<blockquote>- 💰 مبلغ اعتباردهی : {$rxFmtCreditAmount} تومان</blockquote>
<blockquote>- 💳 روش پرداخت :  ترونادو</blockquote>";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_reportpayment,
        'parse_mode' => "HTML"
        ]);
    }
        } elseif ($orderStatusId === 40) {
            \Faoxima\Payments\Gateways\Tronado\Settlement::recordLegacyEvent($connect, (string) $paymentId, $dedupeKey);
            \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_notify_user_failed($paymentId, 'رسید پرداخت شما توسط ترونادو رد شد');
        } elseif ($orderStatusId === 200) {
            \Faoxima\Payments\Gateways\Tronado\Settlement::recordLegacyEvent($connect, (string) $paymentId, $dedupeKey);
            \Faoxima\Payments\Settlement\Confirmation::markExpired($pdo, $paymentId);
        }
    }
