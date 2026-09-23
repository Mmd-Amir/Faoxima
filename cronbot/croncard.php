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
    if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
list($rxW, $rxN) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$rxShard = ($rxN > 1) ? " AND MOD(id, $rxN) = $rxW " : "";
$staleProcessingCutoff = date('Y/m/d H:i:s', time() - 300);
$staleRecover = $pdo->prepare(
    "UPDATE Payment_report SET payment_Status = 'waiting' "
    . "WHERE payment_Status = 'processing' AND (Payment_Method = 'cart to cart' OR Payment_Method = 'arze digital offline') "
    . "AND (at_updated IS NULL OR at_updated < :cutoff)"
);
$staleRecover->bindValue(':cutoff', $staleProcessingCutoff, PDO::PARAM_STR);
$staleRecover->execute();
if ($staleRecover->rowCount() > 0 && function_exists('clearSelectCache')) clearSelectCache('Payment_report');
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
    $balanceBeforeAuto = $Balance_id['Balance'];
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
            "UPDATE Payment_report SET payment_Status = 'processing', "
            . "dec_not_confirmed = 'تایید توسط ربات بدون بررسی', "
            . "at_updated = :at_updated "
            . "WHERE id_order = :id AND payment_Status = 'waiting'"
        );
        $atomicCard->bindValue(':id', $Payment_report['id_order'], PDO::PARAM_STR);
        $atomicCard->bindValue(':at_updated', date('Y/m/d H:i:s'), PDO::PARAM_STR);
        $atomicCard->execute();
        if ($atomicCard->rowCount() < 1) {
            continue;
        }
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
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
        $directPaymentResult = DirectPayment($Payment_report['id_order'],"../images.jpg");
        $Payment_report_after = select("Payment_report", "*", "id_order", $Payment_report['id_order'], "select", ['cache' => false]);
        $directPaymentDone = is_array($Payment_report_after) && intval($Payment_report_after['direct_payment_done'] ?? 0) === 1;
        $alreadyPaid = is_array($Payment_report_after) && $Payment_report_after['payment_Status'] === 'paid';
        if (!$alreadyPaid && $directPaymentDone) {
            $finalizeCard = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = :id AND payment_Status = 'processing'");
            $finalizeCard->bindValue(':id', $Payment_report['id_order'], PDO::PARAM_STR);
            $finalizeCard->execute();
            $alreadyPaid = $finalizeCard->rowCount() > 0;
            if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
        }
        if (!$alreadyPaid && !$directPaymentDone && is_array($directPaymentResult) && ($directPaymentResult['retryable'] ?? true) === false) {
            $terminalCard = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject', dec_not_confirmed = :reason WHERE id_order = :id AND payment_Status = 'processing'");
            $terminalCard->bindValue(':reason', (string)($directPaymentResult['reason'] ?? 'invoice_not_found'), PDO::PARAM_STR);
            $terminalCard->bindValue(':id', $Payment_report['id_order'], PDO::PARAM_STR);
            $terminalCard->execute();
            if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
            if ($terminalCard->rowCount() > 0) {
                continue;
            }
        }
        if (!$alreadyPaid && !$directPaymentDone) {
            $rollbackCard = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting' WHERE id_order = :id AND payment_Status = 'processing'");
            $rollbackCard->bindValue(':id', $Payment_report['id_order'], PDO::PARAM_STR);
            $rollbackCard->execute();
            if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
            continue;
        }
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
        $stmtCashback = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
        $stmtCashback->bindValue(':delta', $result, PDO::PARAM_INT);
        $stmtCashback->bindValue(':uid', $Balance_id['id'], PDO::PARAM_STR);
        $stmtCashback->execute();
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($Balance_id['id'], 'credit', $result, 'cashback', 'هدیه بازگشت وجه کارت به کارت (تایید خودکار)', $Payment_report['id_order']);
        }
        $pricecashback =  number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ " . rxFormatToman($result) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
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
