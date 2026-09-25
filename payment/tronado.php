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
require __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;


function tronado_log_event($type, $message, array $context = [])
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    $line = $type . ': ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log('[tronado] ' . $line);
}

$ManagePanel = new ManagePanel();
$rawBody = file_get_contents("php://input");

$ipnSigningKey = trim((string) select("PaySetting", "*", "NamePay", "ipnsigningkeytronado", "select")['ValuePay']);
$signatureHeader = $_SERVER['HTTP_X_TRONADO_SIG'] ?? '';
if ($ipnSigningKey === '') {
    tronado_log_event('TRONADO_SIGNING_KEY_MISSING', 'IpnSigningKey is not configured', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(500);
    exit('Signing key not configured');
}
$expectedSignature = hash_hmac('sha512', (string) $rawBody, $ipnSigningKey);
$signatureValid = is_string($signatureHeader) && $signatureHeader !== '' && hash_equals($expectedSignature, strtolower($signatureHeader));
if (!$signatureValid) {
    tronado_log_event('TRONADO_BAD_SIGNATURE', 'X-Tronado-Sig verification failed', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(401);
    exit('Invalid signature');
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    tronado_log_event('TRONADO_BAD_BODY', 'Callback body was not valid JSON', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'body_excerpt' => substr((string) $rawBody, 0, 200),
    ]);
    http_response_code(400);
    exit('Invalid body');
}

$paymentId = $data['PaymentId'] ?? ($data['PaymentID'] ?? null);
if ($paymentId === null) {
    tronado_log_event('TRONADO_NO_ID', 'Callback missing payment identifier', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'keys' => array_keys($data),
    ]);
    http_response_code(400);
    exit('Missing payment identifier');
}


if (!is_string($paymentId) && !is_numeric($paymentId)) {
    tronado_log_event('TRONADO_BAD_ID_TYPE', 'PaymentID had unexpected type', [
        'type' => gettype($paymentId),
    ]);
    http_response_code(400);
    exit('Invalid payment identifier');
}
$paymentId = (string) $paymentId;
if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $paymentId)) {
    tronado_log_event('TRONADO_BAD_ID_FORMAT', 'PaymentID failed format check', [
        'payment_id_excerpt' => substr($paymentId, 0, 40),
    ]);
    http_response_code(400);
    exit('Invalid payment identifier');
}

$lookupStmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
$lookupStmt->bind_param('s', $paymentId);
$lookupStmt->execute();
$Payment_report = $lookupStmt->get_result()->fetch_assoc();
$lookupStmt->close();
if (!is_array($Payment_report)) {
    tronado_log_event('TRONADO_UNKNOWN_ORDER', 'Payment_report row not found', [
        'payment_id' => $paymentId,
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(404);
    exit('Order not found');
}
if ($Payment_report['payment_Status'] == "expire") {
    // The order expired on this side, but Tronado only sends a paid callback once the buyer
    // actually paid - so the money exists and this bot is about to credit nothing. A bare
    // return answers HTTP 200, which Tronado records as a delivered callback: it never retries
    // and never reports, so the payment is lost silently. 409 is a permanent client error on
    // their side, so they stop retrying at once AND raise the failure to the shop, which can
    // then settle the order by hand.
    tronado_log_event('TRONADO_PAID_AFTER_EXPIRE', 'Paid callback arrived for an expired order', [
        'payment_id' => $paymentId,
    ]);
    http_response_code(409);
    exit('Order already expired');
}
$setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
$price = $Payment_report['price'];
    $datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
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
    $orderStatusId = isset($data['OrderStatusID']) ? (int) $data['OrderStatusID'] : null;
    $isPaid = ($orderStatusId === 30) || ($data['IsPaid'] ?? false);

    $dedupeKey = $paymentId . '|' . ($orderStatusId ?? 'null');
    $dedupeLookup = $connect->prepare("SELECT dec_not_confirmed FROM Payment_report WHERE id_order = ? LIMIT 1");
    $dedupeLookup->bind_param('s', $paymentId);
    $dedupeLookup->execute();
    $dedupeRow = $dedupeLookup->get_result()->fetch_assoc();
    $dedupeLookup->close();
    if (is_array($dedupeRow) && ($dedupeRow['dec_not_confirmed'] ?? null) === $dedupeKey) {
        tronado_log_event('TRONADO_DUPLICATE', 'Duplicate (PaymentId, OrderStatusID) callback ignored', [
            'payment_id' => $paymentId,
            'order_status_id' => $orderStatusId,
        ]);
        exit('Already processed');
    }

    if($Payment_report['payment_Status'] != "paid"){
        if($isPaid){

            $userPaidTomanAmount = isset($data['UserPaidTomanAmount']) ? (float) $data['UserPaidTomanAmount'] : null;
            $tomanAmountWithoutWage = isset($data['TomanAmountWithoutWage']) ? (float) $data['TomanAmountWithoutWage'] : null;
            if ($userPaidTomanAmount === null || $tomanAmountWithoutWage === null) {
                tronado_log_event('TRONADO_AMOUNT_MISSING', 'Callback lacks v5 Toman amount fields', [
                    'payment_id' => $paymentId,
                ]);
                http_response_code(400);
                exit('Amount missing');
            }

            $wageFromBusinessPercentage = (int) select("PaySetting", "*", "NamePay", "wageFromBusinessPercentageTronado", "select")['ValuePay'];
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

            $atomic = $connect->prepare(
                "UPDATE Payment_report SET payment_Status = ?, dec_not_confirmed = ? "
                . "WHERE id_order = ? AND payment_Status <> 'paid'"
            );
            $statusPaid = 'paid';
            $atomic->bind_param('sss', $statusPaid, $dedupeKey, $paymentId);
            $atomic->execute();
            $affected = $atomic->affected_rows;
            $atomic->close();
            if ($affected < 1) {


                tronado_log_event('TRONADO_DUPLICATE', 'Duplicate or already-paid callback ignored', [
                    'payment_id' => $paymentId,
                ]);
                exit('Already processed');
            }

            echo "پرداخت با موفقیت انجام  شد";
    $textbotlang = languagechange('../text.json');
    DirectPayment($paymentId,"../images.jpg");
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackiranpay2","select")['ValuePay'];
    $balanceLookup = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
    $balanceLookup->bind_param('s', $Payment_report['id_user']);
    $balanceLookup->execute();
    $Balance_id = $balanceLookup->get_result()->fetch_assoc();
    $balanceLookup->close();
    if (!is_array($Balance_id)) {
        tronado_log_event('TRONADO_USER_MISSING', 'Linked user row not found after paid update', [
            'payment_id' => $paymentId,
            'id_user' => $Payment_report['id_user'] ?? null,
        ]);
        $Balance_id = ['id' => $Payment_report['id_user'] ?? '', 'username' => '—', 'Balance' => 0];
    }
    $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
        || rx_cashbackEligibleForKey("chashbackiranpay2", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
    if($cashbackEligible && $pricecashback != "0"){
       $result = (int) floor(($Payment_report['price'] * $pricecashback) / 100);
        if (rx_cashback_credit_once($Payment_report['id_order'], $Balance_id['id'], $result, 'chashbackiranpay2', 'هدیه بازگشت وجه ترنادو') === 'credited') {
            $pricecashback =  number_format($pricecashback);
            $text_report = "🎁 کاربر عزیز مبلغ " . rxFormatToman($result) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
            sendmessage($Balance_id['id'], $text_report, null, 'HTML');
        }
    }
    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
    $balancelow = '';
    if(isset($data['TronAmount'], $data['ActualTronAmount']) && (float) $data['TronAmount'] < (float) $data['ActualTronAmount']){
        $balancelow = "❌ کاربر کمتر از مبلغ تعیین شده واریز کرده است.";
    }
$rxFmtPrice = rxFormatToman($price);
$rxFmtCreditAmount = rxFormatToman($creditAmount);
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
            $dedupeUpd = $connect->prepare("UPDATE Payment_report SET dec_not_confirmed = ? WHERE id_order = ?");
            $dedupeUpd->bind_param('ss', $dedupeKey, $paymentId);
            $dedupeUpd->execute();
            $dedupeUpd->close();
            payment_notify_user_failed($paymentId, 'رسید پرداخت شما توسط ترونادو رد شد');
        } elseif ($orderStatusId === 200) {
            $dedupeUpd = $connect->prepare("UPDATE Payment_report SET dec_not_confirmed = ? WHERE id_order = ?");
            $dedupeUpd->bind_param('ss', $dedupeKey, $paymentId);
            $dedupeUpd->execute();
            $dedupeUpd->close();
            payment_mark_expired($paymentId);
        }
    }
