<?php
ini_set('error_log', 'error.log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
$ManagePanel = new ManagePanel();
require __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

$PaySetting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'statuscardautoconfirm'"))['ValuePay'];
if($PaySetting == "onautoconfirm"){
$name_post = array_keys($_POST);
$name_post = array_map('htmlspecialchars', $name_post);
$name_post = preg_split("/_+/", $name_post[0], -1);
$secret_key = select("admin", "*", "password", base64_decode($name_post[0]), "count");
if($secret_key == 0)return;
$name_bank = $name_post[1];
$valuepost = $_POST["{$name_post[0]}_$name_bank"];
$setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
$admin_ids = array_column(mysqli_fetch_all(mysqli_query($connect, "SELECT (id_admin) FROM admin"), MYSQLI_ASSOC), 'id_admin');
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
if($name_bank == 'blu'){
$pattern = "/(\d[\d,]+) ریال به حساب شما نشست\./u";
preg_match($pattern, $valuepost, $matches);
if (isset($matches[1])) {
    $amountString = str_replace(',', '', $matches[1]);
    $amount = intval($amountString);
    $amountInteger = intval($amount) * 0.1;
}}
elseif($name_bank == "meli"){
$pattern = '/انتقال:(.*?)[+\-]/u';
preg_match($pattern, $valuepost, $matches);
if (isset($matches[1])) {
    $amount = str_replace([',', '-'], '', $matches[1]);
    $amountInteger = intval($amount) * 0.1;
}}
elseif($name_bank == "grdsh"){
preg_match('/مبلغ: ([0-9,]+)/u',$valuepost, $matches);
if (isset($matches[1])) {
    $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
}}
elseif($name_bank == "sadhrat"){
preg_match('/انتقال: ([\d,]+)/', $valuepost, $matches);
if (isset($matches[1])) {
    $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
}}
elseif($name_bank == "melet"){
preg_match('/واریز(\d{1,3}(?:,\d{3})*)/u', $valuepost, $matches);
if (isset($matches[1])) {
    $amountInteger = str_replace(',', '', $matches[1])* 0.1;
}}
elseif($name_bank  == "terjart"){
if(preg_match('/واریز\s*:\s*([\d,]+)/u', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
}}
elseif($name_bank  == "keshavarsi"){
if(preg_match('/واريز(\d+(?:,\d+)*)/', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}
elseif($name_bank  == "resalet"){
if(preg_match('/\+([\d,]+)/', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}
elseif($name_bank  == "sheahr"){
if(preg_match('/مبلغ:(\d+(?:,\d+)*)ريال//', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}
elseif($name_bank  == "maskan"){
if(preg_match('/انتقال اينترنت:\D*([\d,]+)/u', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}elseif($name_bank  == "parsian"){
if(preg_match('/مبلغ:(\d{1,3}(?:,\d{3})*)\+/', $valuepost, $matches)) {
    file_put_contents('ss',json_encode($matches));
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}elseif($name_bank  == "sphe"){
if(preg_match('/مبلغ:\s*([\d,]+)\s*ريال/', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}elseif($name_bank  == "paselc"){
if(preg_match('/\+([0-9,]+)/', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}elseif($name_bank  == "gharz"){
if(preg_match('/(\d{1,3}(?:,\d{3})*\+)/', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}


if (is_numeric($amountInteger) && substr($amountInteger, -3) === '000')return;
if(isset($amountInteger) && $amountInteger !== NULL){
    $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE price = ? AND (payment_Status = 'Unpaid' OR payment_Status = 'waiting')");
    $_amtFloat = (float)$amountInteger;
    $_stmt->bind_param("d", $_amtFloat);
    $_stmt->execute();
    $datauser = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    $order_id = $datauser['id_order'];
    $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
    $_stmt->bind_param("s", $order_id);
    $_stmt->execute();
    $Payment_report = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    if(!isset($Payment_report['price']) || $Payment_report['price'] == null)return;
    $_uid = $Payment_report['id_user'];
    $_stmt = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
    $_stmt->bind_param("s", $_uid);
    $_stmt->execute();
    $Balance_id = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    $textbotlang = languagechange('../text.json');

    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;}
        DirectPayment($order_id,"../images.jpg");
        update("Payment_report","payment_Status","paid",'id_order',$order_id);
    $_uid2 = $Payment_report['id_user'];
    $_stmt = $connect->prepare("SELECT Balance FROM user WHERE id = ? LIMIT 1");
    $_stmt->bind_param("s", $_uid2);
    $_stmt->execute();
    $balanceformatsell = number_format($_stmt->get_result()->fetch_assoc()['Balance'], 0);
    $_stmt->close();
    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
    $text_report = "یک رسید توسط ربات  تایید شد

اطلاعات :
💰 مبلغ پرداخت : {$Payment_report['price']}
👤  آیدی عددی کاربر : {$Balance_id['id']}
👤 نام کاربری کاربر : @{$Balance_id['username']}
موجودی کاربر : $balanceformatsell تومان
کد پیگیری پرداخت : $order_id";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_report,
        'parse_mode' => "HTML"
        ]);
    }
}
}
