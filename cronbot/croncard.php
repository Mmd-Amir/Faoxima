<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('croncard', 180);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('croncard', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
    __DIR__ . '/../keyboard.php',
    __DIR__ . '/../jdf.php',
])) {
    return;
}
if (!rx_cron_db_ready('croncard')) {
    return;
}
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}
$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
$datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
$paymentverify = select("PaySetting","ValuePay","NamePay","autoconfirmcart","select")['ValuePay'];
if ($paymentverify == "offauto") return;
if (($setting['card_verify_status'] ?? 'offcardverify') === 'oncardverify') return;
$trustModeActive = select("PaySetting","ValuePay","NamePay","trust_mode_active","select")['ValuePay'];
$trustModeOn = ($trustModeActive == "on");
$list_Exceptions_raw = select("PaySetting","ValuePay","NamePay","Exception_auto_cart","select")['ValuePay'];
$list_Exceptions = is_string($list_Exceptions_raw) ? json_decode($list_Exceptions_raw, true) : [];
$list_Trusted = [];
if ($trustModeOn) {
    $list_Trusted_raw = select("PaySetting","ValuePay","NamePay","Trust_auto_cart","select")['ValuePay'];
    $list_Trusted = is_string($list_Trusted_raw) ? json_decode($list_Trusted_raw, true) : [];
}
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
list($rxW, $rxN) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$rxShard = ($rxN > 1) ? " AND MOD(id, $rxN) = $rxW " : "";
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE payment_Status = 'waiting' AND (Payment_Method = 'cart to cart' OR Payment_Method = 'arze digital offline') AND bottype IS NULL$rxShard ORDER BY id ASC LIMIT 50");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
    $timecheck = $setting['timeauto_not_verify']*60;
    if($row['at_updated'] == null)continue;
    $since_start = time() - strtotime($row['at_updated']);
    if ($since_start >= 3600)continue;
    $Payment_report = $row;
    $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");
    $userId = (string)$Balance_id['id'];
    if ($trustModeOn) {
        if (!in_array($userId, array_map('strval', (array)$list_Trusted)))continue;
    } else {
        if (in_array($userId, array_map('strval', (array)$list_Exceptions)))continue;
        if ($since_start <= $timecheck)continue;
    }
    $textbotlang =languagechange('../text.json');
    if ($Payment_report['payment_Status'] == "paid") {
        continue;
    }


        $atomicCard = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid', "
            . "dec_not_confirmed = 'تایید توسط ربات بدون بررسی' "
            . "WHERE id_order = :id AND payment_Status = 'waiting'"
        );
        $atomicCard->bindValue(':id', $Payment_report['id_order'], PDO::PARAM_STR);
        $atomicCard->execute();
        if ($atomicCard->rowCount() < 1) {
            continue;
        }
        if (function_exists('rx_redis_del') && isset($Payment_report['id_user'])) {
            rx_redis_del('faoxima:paystatus:' . $Payment_report['id_order'] . ':' . (string)$Payment_report['id_user']);
        }
        DirectPayment($Payment_report['id_order'],"../images.jpg");
        $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackcart","select")['ValuePay'];
    $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");
    $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
        || rx_cashbackEligibleForKey("chashbackcart", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
    if($cashbackEligible && $pricecashback != "0"){
        $result = intval(($Payment_report['price'] * $pricecashback) / 100);
        $stmtCashback = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
        $stmtCashback->bindValue(':delta', $result, PDO::PARAM_INT);
        $stmtCashback->bindValue(':uid', $Balance_id['id'], PDO::PARAM_STR);
        $stmtCashback->execute();
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($Balance_id['id'], 'credit', $result, 'cashback', 'هدیه بازگشت وجه کارت به کارت (تایید خودکار)', $Payment_report['id_order']);
        }
        $pricecashback =  number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ $result تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    $reportChatId = trim((string)($Payment_report['report_chat_id'] ?? ''));
    $reportMessageId = (int)($Payment_report['report_message_id'] ?? 0);
    if ($reportChatId === '' || $reportMessageId <= 0) {
        $text_reportpayment = "✅ تایید شده

<blockquote>آیدی عددی کاربر : {$Balance_id['id']}</blockquote>
<blockquote>مبلغ تراکنش {$Payment_report['price']}</blockquote>
<blockquote>روش پرداخت :  تایید خودکار بدون بررسی</blockquote>
<blockquote>{$Payment_report['Payment_Method']}</blockquote>";
        $_cron_confirm_kb = json_encode([
            'inline_keyboard' => [
                [['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $Payment_report['id_user']]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_reportpayment,
            'reply_markup' => $_cron_confirm_kb,
            'parse_mode' => "HTML"
            ]);
        }
    }
}
