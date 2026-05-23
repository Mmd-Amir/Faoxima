<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
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
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    tronado_log_event('TRONADO_BAD_BODY', 'Callback body was not valid JSON', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'body_excerpt' => substr((string) $rawBody, 0, 200),
    ]);
    http_response_code(400);
    exit('Invalid body');
}

$paymentId = $data['PaymentID'] ?? ($data['OrderId'] ?? ($data['Metadata']['PaymentID'] ?? null));
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
    return;
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
    'text_wgdashboard' => '',
    'textafterpayibsng' => ''
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
    if($Payment_report['payment_Status'] != "paid"){
        if($data['IsPaid']){


            $reportedTronAmount = isset($data['TronAmount']) ? (float) $data['TronAmount'] : null;
            $actualTronAmount = isset($data['ActualTronAmount']) ? (float) $data['ActualTronAmount'] : null;
            if ($reportedTronAmount === null || $actualTronAmount === null) {
                tronado_log_event('TRONADO_AMOUNT_MISSING', 'Callback lacks TRX amount fields', [
                    'payment_id' => $paymentId,
                ]);
                http_response_code(400);
                exit('Amount missing');
            }
            if ($actualTronAmount + 1e-6 < $reportedTronAmount) {
                tronado_log_event('TRONADO_SHORT_PAYMENT', 'ActualTronAmount below registered TronAmount', [
                    'payment_id' => $paymentId,
                    'reported' => $reportedTronAmount,
                    'actual' => $actualTronAmount,
                ]);


                exit('Short payment');
            }


            $atomic = $connect->prepare(
                "UPDATE Payment_report SET payment_Status = ?, dec_not_confirmed = ? "
                . "WHERE id_order = ? AND payment_Status <> 'paid'"
            );
            $statusPaid = 'paid';
            $callbackJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $atomic->bind_param('sss', $statusPaid, $callbackJson, $paymentId);
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
    if($pricecashback != "0"){
       $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) +$result ;
        update("user","Balance",$Balance_confrim, "id",$Balance_id['id']);
        $pricecashback =  number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ $result تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
    $balancelow = '';
    if($data['TronAmount'] < $data['ActualTronAmount']){
        $balancelow = "❌ کاربر کمتر از مبلغ تعیین شده واریز کرده است.";
    }
$text_reportpayment = "💵 پرداخت جدید
$balancelow
- 👤 نام کاربری کاربر : @{$Balance_id['username']}
- 🆔آیدی عددی کاربر : {$Balance_id['id']}
- 💸 مبلغ تراکنش $price
- 🔗 <a href = \"https://tronscan.org/#/transaction/{$data['Hash']}\">لینک پرداخت </a>
- 📥 مبلغ واریز شده ترون. : {$data['TronAmount']}
- 💳 روش پرداخت :  ترونادو";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_reportpayment,
        'parse_mode' => "HTML"
        ]);
    }
        }
    }
