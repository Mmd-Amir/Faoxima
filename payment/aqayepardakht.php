<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
require __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

$ManagePanel = new ManagePanel();


$rawInvoiceId = isset($_POST['invoice_id']) ? (string) $_POST['invoice_id'] : '';
$rawTransid   = isset($_POST['transid']) ? (string) $_POST['transid'] : '';
if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $rawInvoiceId)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('AQAYE_BAD_INVOICE', 'invoice_id failed format validation', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'invoice_excerpt' => substr($rawInvoiceId, 0, 64),
        ]);
    } else {
        error_log('[aqayepardakht] bad invoice_id format');
    }
    http_response_code(400);
    exit('Invalid invoice_id');
}
$invoice_id = htmlspecialchars($rawInvoiceId, ENT_QUOTES, 'UTF-8');
$setting = select("setting", "*");
$PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht","select")['ValuePay'];
$Payment_report = select("Payment_report", "price", "id_order", $invoice_id,"select")['price'];
$price = $Payment_report;
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


$data = [
'pin'    => $PaySetting,
'amount'    => $Payment_report,
'transid' => $rawTransid,
];
$data = json_encode($data);
$ch = curl_init('https://panel.aqayepardakht.ir/api/v2/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Content-Type: application/json',
'Content-Length: ' . strlen($data))
);
$result = curl_exec($ch);
curl_close($ch);
$result = json_decode($result);
if ($result->code == "1") {
    $payment_status = "پرداخت موفق";
    $price = $Payment_report;
    $dec_payment_status = "از انجام تراکنش متشکریم!";

    $atomic = $pdo->prepare(
        "UPDATE Payment_report SET payment_Status = 'paid' "
        . "WHERE id_order = :id AND payment_Status <> 'paid'"
    );
    $atomic->bindValue(':id', $invoice_id, PDO::PARAM_STR);
    $atomic->execute();
    $alreadyPaid = $atomic->rowCount() < 1;
    $Payment_report = select("Payment_report", "*", "id_order", $invoice_id,"select");
    if(!$alreadyPaid && is_array($Payment_report)){
    $textbotlang = languagechange('../text.json');
    DirectPayment($invoice_id,"../images.jpg");
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackaqaypardokht","select")['ValuePay'];

    $userStmt = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
    $userStmt->bind_param('s', $Payment_report['id_user']);
    $userStmt->execute();
    $Balance_id = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    if (!is_array($Balance_id)) {
        $Balance_id = ['id' => $Payment_report['id_user'] ?? '', 'username' => '—', 'Balance' => 0];
    }
    if($pricecashback != "0"){
        $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) +$result;
        update("user","Balance",$Balance_confrim, "id",$Balance_id['id']);
        $pricecashback =  number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ $result تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];

$text_report = "💵 پرداخت جدید

آیدی عددی کاربر : {$Payment_report['id_user']}
نام کاربری کاربر : {$Balance_id['username']}
مبلغ تراکنش $price
روش پرداخت :  درگاه آقای پرداخت";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_report,
        'parse_mode' => "HTML"
        ]);
    }
}
}else {
        $payment_status = [
        '0' => "پرداخت انجام نشد",
        '2' => "تراکنش قبلا وریفای و پرداخت شده است",

    ][$result->code];
     $dec_payment_status = "";
}
?>
<html>
<head>
    <title>فاکتور پرداخت</title>
    <style>
    @font-face {
    font-family: 'vazir';
    src: url('/Vazir.eot');
    src: local('☺'), url('../fonts/Vazir.woff') format('woff'), url('../fonts/Vazir.ttf') format('truetype');
}

        body {
            font-family:vazir;
            background-color:
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .confirmation-box {
            background-color:
            border-radius: 8px;
            width:25%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }

        h1 {
            color:
            margin-bottom: 20px;
        }

        p {
            color:
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="confirmation-box">
        <h1><?php echo $payment_status ?></h1>
        <p>شماره تراکنش:<span><?php echo $invoice_id ?></span></p>
        <p>مبلغ پرداختی:  <span><?php echo  $price; ?></span>تومان</p>
        <p>تاریخ: <span>  <?php echo jdate('Y/m/d')  ?>  </span></p>
        <p><?php echo $dec_payment_status ?></p>
    </div>
</body>
</html>


