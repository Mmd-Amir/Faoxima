<?php
ini_set('error_log', 'error.log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
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
$_cvSetting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT card_verify_status FROM setting LIMIT 1"));
if (is_array($_cvSetting) && (($_cvSetting['card_verify_status'] ?? 'offcardverify') === 'oncardverify')) {
    return;
}
if($PaySetting == "onautoconfirm"){
$name_post = array_keys($_POST);
$name_post = array_map('htmlspecialchars', $name_post);
$name_post = preg_split("/_+/", $name_post[0] ?? '', 2);
if (count($name_post) !== 2 || $name_post[0] === '' || $name_post[1] === '') return;
$secret_key = \Faoxima\Core\Database\Db::select("admin", "*", "password", base64_decode($name_post[0]), "count");
if($secret_key == 0)return;
$name_bank = $name_post[1];
$valuepost = $_POST["{$name_post[0]}_$name_bank"] ?? null;
if (!is_string($valuepost)) return;
$amountInteger = null;
$setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
$admin_ids = array_column(mysqli_fetch_all(mysqli_query($connect, "SELECT (id_admin) FROM admin"), MYSQLI_ASSOC), 'id_admin');
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
if(preg_match('/مبلغ:(\d+(?:,\d+)*)ريال/u', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}
elseif($name_bank  == "maskan"){
if(preg_match('/انتقال اينترنت:\D*([\d,]+)/u', $valuepost, $matches)) {
    $amountInteger = str_replace(',', '', $matches[1])*0.1;
}}elseif($name_bank  == "parsian"){
if(preg_match('/مبلغ:(\d{1,3}(?:,\d{3})*)\+/', $valuepost, $matches)) {
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
    $_sbvExcl = (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_has_schema') && \Faoxima\Payments\Receipts\Settings::hasSchema($pdo)) ? " AND NOT EXISTS (SELECT 1 FROM card_sms_payment csp WHERE csp.id_order = Payment_report.id_order)" : "";
    $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE price = ? AND Payment_Method = 'cart to cart' AND (payment_Status = 'Unpaid' OR payment_Status = 'waiting')" . $_sbvExcl);
    $_amtFloat = (float)$amountInteger;
    $_stmt->bind_param("d", $_amtFloat);
    $_stmt->execute();
    $datauser = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    if (!$datauser) return;
    $order_id = $datauser['id_order'];
    $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
    $_stmt->bind_param("s", $order_id);
    $_stmt->execute();
    $Payment_report = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    if(!isset($Payment_report['price']) || $Payment_report['price'] == null)return;
    if (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_is_verify_order') && \Faoxima\Payments\Receipts\ReceiptOrders::isVerifyOrder($pdo, (string) $order_id)) return;
    $_uid = $Payment_report['id_user'];
    $_stmt = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
    $_stmt->bind_param("s", $_uid);
    $_stmt->execute();
    $Balance_id = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange('../text.json');

    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;}
        try {
            $token = \Faoxima\Payments\Settlement\CallbackClaims::claimCard(
                $pdo, $Payment_report, $_amtFloat, hash('sha256', $name_bank . "\n" . trim($valuepost))
            );
        } catch (Throwable $error) {
            error_log('[card] verified SMS claim failed: ' . $error->getMessage());
            return;
        }
        if ($token === null) return;
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
        $settled = \Faoxima\Payments\Settlement\CallbackClaims::settleDelivery(
            $pdo, (string) $order_id, $Payment_report,
            static fn ($order, $image) => \Faoxima\Services\Fulfillment\Delivery::runClaimed($pdo, $order, $token, $image, 'manual_review')['result'],
            '../images.jpg'
        );
    $balanceformatsell = number_format($settled['customer']['Balance'] ?? 0, 0);
    $paymentreports = \Faoxima\Core\Database\Db::select("topicid","idreport","report","paymentreport","select")['idreport'];
    $text_report = "یک رسید توسط ربات  تایید شد

اطلاعات :
<blockquote>💰 مبلغ پرداخت : " . \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']) . "</blockquote>
<blockquote>👤  آیدی عددی کاربر : {$Balance_id['id']}</blockquote>
<blockquote>👤 نام کاربری کاربر : @{$Balance_id['username']}</blockquote>
<blockquote>موجودی کاربر : $balanceformatsell تومان</blockquote>
<blockquote>کد پیگیری پرداخت : $order_id</blockquote>";
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
