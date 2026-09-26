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
if (!function_exists('rx_pf_ensure_schema') || !rx_pf_ensure_schema()) {
    error_log('[croncard] payment_fulfillment is unavailable; skipping run without touching payments');
    return;
}
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
    if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
list($rxW, $rxN) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$rxShard = ($rxN > 1) ? " AND MOD(id, $rxN) = $rxW " : "";
$rxCroncardQueue = [];
$recoverStmt = $pdo->prepare("SELECT id_order FROM Payment_report WHERE payment_Status IN ('processing', 'reconciling') AND (Payment_Method = 'cart to cart' OR Payment_Method = 'arze digital offline') AND bottype IS NULL$rxShard ORDER BY id ASC LIMIT 20");
$recoverStmt->execute();
foreach ($recoverStmt->fetchAll(PDO::FETCH_COLUMN) as $recoverOrderId) {
    $rxCroncardQueue[] = ['id_order' => (string) $recoverOrderId, 'mode' => 'recover'];
}
$timecheck = $setting['timeauto_not_verify']*60;
$paymentverify = select("PaySetting","ValuePay","NamePay","autoconfirmcart","select")['ValuePay'];
$rxAutoConfirmEnabled = $paymentverify != "offauto" && ($setting['card_verify_status'] ?? 'offcardverify') !== 'oncardverify';
if ($rxAutoConfirmEnabled) {
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE payment_Status = 'waiting' AND COALESCE(direct_payment_done, 0) = 0 AND (Payment_Method = 'cart to cart' OR Payment_Method = 'arze digital offline') AND bottype IS NULL AND NOT EXISTS (SELECT 1 FROM payment_fulfillment pf WHERE pf.id_order = Payment_report.id_order AND pf.state IN ('completed', 'manual_review'))$rxShard ORDER BY id ASC LIMIT 50");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
        if($row['at_updated'] == null)continue;
        $since_start = time() - strtotime($row['at_updated']);
        if ($since_start >= 3600)continue;
        $Balance_id = select("user","*","id",$row['id_user'],"select");
        $userId = (string)$Balance_id['id'];
        if ($trustModeOn) {
            if (!in_array($userId, array_map('strval', (array)$list_Trusted)))continue;
        } else {
            if (in_array($userId, array_map('strval', (array)$list_Exceptions)))continue;
            if ($since_start <= $timecheck)continue;
        }
        $rxCroncardQueue[] = ['id_order' => (string) $row['id_order'], 'mode' => 'claim', 'balance_before' => $Balance_id['Balance'] ?? 0];
    }
}
foreach ($rxCroncardQueue as $rxJob) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
    $rxToken = null;
    if ($rxJob['mode'] === 'recover') {
        $recovered = rx_pf_recover($rxJob['id_order']);
        $recoveredStatus = (string) ($recovered['status'] ?? '');
        if ($recoveredStatus === 'claimed' || $recoveredStatus === 'reconcile') {
            $rxToken = (string) $recovered['token'];
        } elseif ($recoveredStatus !== 'finalized') {
            continue;
        }
    } else {
        $claim = rx_pf_claim($rxJob['id_order'], [
            'from' => ['waiting'],
            'to' => 'processing',
            'dec_not_confirmed' => 'تایید توسط ربات بدون بررسی',
            'min_age' => $trustModeOn ? null : $timecheck,
            'max_age' => 3600,
        ]);
        if (($claim['status'] ?? '') !== 'claimed') {
            continue;
        }
        $rxToken = (string) $claim['token'];
    }
    $Payment_report = select("Payment_report", "*", "id_order", $rxJob['id_order'], "select", ['cache' => false]);
    if (!is_array($Payment_report)) {
        continue;
    }
    $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");
    $balanceBeforeAuto = $rxJob['balance_before'] ?? ($Balance_id['Balance'] ?? 0);
    $textbotlang =languagechange('../text.json');
        if (function_exists('rx_redis_del') && isset($Payment_report['id_user'])) {
            rx_redis_del('faoxima:paystatus:' . $Payment_report['id_order'] . ':' . (string)$Payment_report['id_user']);
        }
        $reportChatId = trim((string)($Payment_report['report_chat_id'] ?? ''));
        $reportMessageId = (int)($Payment_report['report_message_id'] ?? 0);
        $reportThreadId = (int)($Payment_report['report_thread_id'] ?? 0);
        $privateReceiptTargetsRaw = (string)($Payment_report['private_receipt_targets'] ?? '');
        $privateReceiptTargets = $privateReceiptTargetsRaw !== '' ? json_decode($privateReceiptTargetsRaw, true) : [];
        if (!is_array($privateReceiptTargets)) {
            $privateReceiptTargets = [];
        }
        if ($rxToken !== null) {
            $rxRun = rx_pf_run_claimed($rxJob['id_order'], $rxToken, "../images.jpg", 'reject');
            if (empty($rxRun['finalized'])) {
                continue;
            }
        }
        $Payment_report = select("Payment_report", "*", "id_order", $rxJob['id_order'], "select", ['cache' => false]);
        $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");
        $format_price_auto = number_format($Payment_report['price']);
        $rxFmtBalanceBeforeAuto = rxFormatToman($balanceBeforeAuto);
        $rxFmtBalanceAfterAuto = rxFormatToman($Balance_id['Balance']);
        $text_financereport = "📣 پرداخت به‌صورت خودکار تایید شد.

اطلاعات :
<blockquote>💸 روش پرداخت : {$Payment_report['Payment_Method']}</blockquote>
<blockquote>🤖 نوع تایید : تایید خودکار بدون بررسی</blockquote>
<blockquote>👤 آیدی عددی کاربر : <code>{$Payment_report['id_user']}</code></blockquote>
<blockquote>👤 نام کاربری کاربر : @{$Balance_id['username']}</blockquote>
<blockquote>💰 مبلغ پرداخت : $format_price_auto</blockquote>
<blockquote>کد پیگیری پرداخت : {$Payment_report['id_order']}</blockquote>
<blockquote>💎 موجودی قبل : {$rxFmtBalanceBeforeAuto}</blockquote>
<blockquote>💎 موجودی بعد : {$rxFmtBalanceAfterAuto}</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => $text_financereport,
                'parse_mode' => "HTML"
            ]);
        }
        $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackcart","select")['ValuePay'];
    $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
        || rx_cashbackEligibleForKey("chashbackcart", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
    if($cashbackEligible && $pricecashback != "0"){
        $result = intval(($Payment_report['price'] * $pricecashback) / 100);
        if (rx_cashback_credit_once($Payment_report['id_order'], $Balance_id['id'], $result, 'chashbackcart', 'هدیه بازگشت وجه کارت به کارت (تایید خودکار)') === 'credited') {
            $pricecashback =  number_format($pricecashback);
            $text_report = "🎁 کاربر عزیز مبلغ " . rxFormatToman($result) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
            sendmessage($Balance_id['id'], $text_report, null, 'HTML');
        }
    }
    $rxFmtCroncardPrice = rxFormatToman($Payment_report['price']);
    $text_reportpayment = "✅ تایید شده (تایید خودکار بدون بررسی)

<blockquote>آیدی عددی کاربر : {$Balance_id['id']}</blockquote>
<blockquote>مبلغ تراکنش {$rxFmtCroncardPrice}</blockquote>
<blockquote>روش پرداخت :  تایید خودکار بدون بررسی</blockquote>
<blockquote>{$Payment_report['Payment_Method']}</blockquote>";
    $_cron_confirm_kb = json_encode([
        'inline_keyboard' => [
            [['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $Payment_report['id_user']]],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $_receiptTargets = [];
    if ($reportChatId !== '' && $reportMessageId > 0) {
        $_receiptTargets[] = ['chat_id' => $reportChatId, 'message_id' => $reportMessageId, 'thread_id' => $reportThreadId > 0 ? $reportThreadId : null];
    }
    foreach ($privateReceiptTargets as $_target) {
        $_targetChatId = (string)($_target['chat_id'] ?? '');
        $_targetMsgId = (int)($_target['message_id'] ?? 0);
        if ($_targetChatId === '' || $_targetMsgId <= 0) {
            continue;
        }
        $_receiptTargets[] = ['chat_id' => $_targetChatId, 'message_id' => $_targetMsgId, 'thread_id' => null];
    }
    $_seenReceiptTargets = [];
    $_editedAnyReceipt = false;
    foreach ($_receiptTargets as $_target) {
        $_dedupKey = $_target['chat_id'] . ':' . $_target['message_id'];
        if (isset($_seenReceiptTargets[$_dedupKey])) {
            continue;
        }
        $_seenReceiptTargets[$_dedupKey] = true;
        try {
            $_editResult = Editmessagetext($_target['chat_id'], $_target['message_id'], $text_reportpayment, $_cron_confirm_kb, 'HTML', $_target['thread_id']);
            if (is_array($_editResult) && !empty($_editResult['ok'])) {
                $_editedAnyReceipt = true;
            } elseif (function_exists('rx_log_event')) {
                rx_log_event('AUTO_CONFIRM_RECEIPT_EDIT_FAILED', 'Editmessagetext returned failure for a receipt target', [
                    'id_order' => $Payment_report['id_order'],
                    'chat_id' => $_target['chat_id'],
                    'message_id' => $_target['message_id'],
                    'desc' => is_array($_editResult) ? (string)($_editResult['description'] ?? '') : '',
                ]);
            }
        } catch (Throwable $_e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('AUTO_CONFIRM_RECEIPT_EDIT_FAILED', 'Editmessagetext failed for a receipt target', [
                    'id_order' => $Payment_report['id_order'],
                    'chat_id' => $_target['chat_id'],
                    'message_id' => $_target['message_id'],
                    'err' => $_e->getMessage(),
                ]);
            }
        }
    }
    if (!$_editedAnyReceipt && strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_reportpayment,
        'reply_markup' => $_cron_confirm_kb,
        'parse_mode' => "HTML"
        ]);
    }
}
