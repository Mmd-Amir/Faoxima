<?php

if (preg_match('/Confirmpay_user_(\w+)_(\w+)/', $datain, $dataget)) {
    $id_payment = $dataget[1];
    $id_order = $dataget[2];


    $stmtPay = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :id_order LIMIT 1");
    $stmtPay->bindValue(':id_order', $id_order, PDO::PARAM_STR);
    $stmtPay->execute();
    $Payment_report = $stmtPay->fetch(PDO::FETCH_ASSOC);
    if (!is_array($Payment_report) || (string)($Payment_report['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('PAYMENT_CONFIRM_FORBIDDEN', 'Non-owner attempted Confirmpay_user', [
                'from_id' => $from_id, 'id_order' => $id_order, 'id_payment' => $id_payment,
            ]);
        }
        return;
    }
    if ($Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Confirmpayadmin'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $StatusPayment = StatusPayment($id_payment);
    if ($StatusPayment['payment_status'] == "finished") {
        $rxClaimPaid = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = :o AND payment_Status <> 'paid'");
        $rxClaimPaid->execute([':o' => $Payment_report['id_order']]);
        if ($rxClaimPaid->rowCount() < 1) {
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['Admin']['Payment']['reviewedpayment'] ?? 'این پرداخت قبلاً بررسی شده است',
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['finished'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        DirectPayment($Payment_report['id_order']);
        $_uid = $Payment_report['id_user'];
        $_stmt = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
        $_stmt->bind_param("s", $_uid); $_stmt->execute();
        $Balance_id = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        $priceFormatted = rxFormatToman($Payment_report['price']);
        $text_report = "💵 پرداخت جدید

<blockquote>آیدی عددی کاربر : $from_id</blockquote>
<blockquote>مبلغ تراکنش : {$priceFormatted}</blockquote>
<blockquote>روش پرداخت : درگاه ارزی ریالی اول</blockquote>";
        $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackiranpay2", "select")['ValuePay'];
        $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
            || rx_cashbackEligibleForKey("chashbackiranpay2", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
        if ($cashbackEligible && $pricecashback != "0") {
            $result = (int) floor(($Payment_report['price'] * $pricecashback) / 100);
            if (rx_cashback_credit_once($Payment_report['id_order'], $Payment_report['id_user'], $result, 'chashbackiranpay2', 'هدیه بازگشت وجه درگاه ارزی ریالی') === 'credited') {
                $pricecashback = number_format($pricecashback);
                $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], rxFormatToman($result));
                sendmessage($Payment_report['id_user'], $text_report, null, 'HTML');
            }
        }
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        update("user", "Processing_value_one", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_tow", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_four", "none", "id", $Payment_report['id_user']);
    } elseif ($StatusPayment['payment_status'] == "expired") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['expired'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "refunded") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['refunded'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "waiting") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['waiting'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "sending") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['sending'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } else {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Failed'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    }
}
if (preg_match('/^sendresidcart-(.*)/', $datain, $dataget)) {
    $timefivemin = time() - 120;
    $timefivemin = date('Y/m/d H:i:s', intval($timefivemin));
    $sql = "SELECT * FROM Payment_report WHERE id_user = '$from_id' AND Payment_Method = 'cart to cart' AND at_updated > '$timefivemin'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $paymentcount = $stmt->rowCount();
    if ($paymentcount != 0 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "❗ شما در ۲ دقیقه اخیر رسید ارسال کرده اید لطفا ۲ دقیقه دیگر رسید جدید را ارسال نمایید.", null, 'HTML');
        return;
    }
    $payemntcheck = select("Payment_report", "*", "id_order", $dataget[1], "select");
    if ($payemntcheck['payment_Status'] == "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if ($payemntcheck['payment_Status'] == "expire") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "🖼 تصویر رسید خود را ارسال نمایید", $backuser, 'HTML');
    step('cart_to_cart_user', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif ($user['step'] == 'card_select_step') {
    if (!isset($update['callback_query'])) { return; }
    $orderId = (string)($user['Processing_value'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($datain === 'cv_new') {
        step('card_photo_step', $from_id);
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $datatextbot['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است

📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.

🔘 نکته مهم: برای تایید تراکنش، الزامی است که ۴ رقم آخر شماره کارت و نام شما در تصویر کاملاً واضح و خوانا باشد. (سایر اطلاعات را می‌توانید بپوشانید).", $backuser, 'HTML');
        return;
    }
    if (preg_match('/^cv_use_(\d{4})$/', $datain, $_cv_m)) {
        $_cv_last4 = $_cv_m[1];
        $_cv_check = $connect->prepare("SELECT id FROM verified_cards WHERE user_id = ? AND last4 = ? LIMIT 1");
        $_cv_check->bind_param("ss", $from_id, $_cv_last4);
        $_cv_check->execute();
        $_cv_check_res = $_cv_check->get_result();
        if ($_cv_check_res->num_rows === 0) {
            sendmessage($from_id, "❌ کارت انتخاب‌شده معتبر نیست. لطفاً دوباره تلاش کنید.", null, 'HTML');
            $_cv_check->close();
            return;
        }
        $_cv_check->close();
        update("Payment_report", "card_last4", $_cv_last4, "id_order", $orderId);
        deletemessage($from_id, $message_id);
        step('home', $from_id);

        $_cv_note = "<blockquote>ℹ️ کاربر <code>{$from_id}</code> از کارت تاییدشده **** **** **** {$_cv_last4} استفاده کرده است.</blockquote>\n<blockquote>🛒 کد پیگیری: {$orderId}</blockquote>\nلطفاً ۴ رقم آخر رسید را بررسی کنید.";
        $_cv_receipt_route = rxReceiptDeliveryRoute('paymentreport');
        $_cv_report_group = trim((string)($_cv_receipt_route['chat_id'] ?? ''));
        if (!empty($_cv_receipt_route['topic_enabled']) && $_cv_report_group !== '') {
            $_cv_topic_payload = ['chat_id' => $_cv_report_group, 'text' => $_cv_note, 'parse_mode' => 'HTML'];
            if (!empty($_cv_receipt_route['thread_id'])) {
                $_cv_topic_payload['message_thread_id'] = (int)$_cv_receipt_route['thread_id'];
            }
            telegram('sendmessage', $_cv_topic_payload);
        } elseif (empty($_cv_receipt_route['topic_enabled'])) {
            foreach ($admin_ids as $_cv_adm_id) {
                sendmessage($_cv_adm_id, $_cv_note, null, 'HTML');
            }
        }

        $payRecord = select("Payment_report", "*", "id_order", $orderId);
        $_uid = intval($from_id);
        $_access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = {$_uid})";
        $_cmode_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
        $_cmode_row = $_cmode_res ? mysqli_fetch_assoc($_cmode_res) : null;
        $_cmode = is_array($_cmode_row) ? ($_cmode_row['ValuePay'] ?? 'random') : 'random';

        $card_info = null;
        $_card2 = null;

        if ($_cmode == 'direct') {
            $_dq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY cn.created_at ASC LIMIT 2");
            $_dcards = [];
            if ($_dq) { while ($_r = mysqli_fetch_assoc($_dq)) { $_dcards[] = $_r; } }
            $card_info = $_dcards[0] ?? null;
            $_card2 = $_dcards[1] ?? null;
        } else {
            $_ck = "card_cycle_{$_uid}";
            $_ck_esc = mysqli_real_escape_string($connect, $_ck);
            $_seen_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = '{$_ck_esc}' LIMIT 1");
            $_seen_row = $_seen_res ? mysqli_fetch_assoc($_seen_res) : null;
            $_seen_str = is_array($_seen_row) ? ($_seen_row['ValuePay'] ?? '') : '';
            $_seen = array_values(array_filter(array_map('intval', explode(',', $_seen_str))));
            $_excl = !empty($_seen) ? "AND cn.id NOT IN (" . implode(',', $_seen) . ")" : "";

            $_rq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} {$_excl} ORDER BY RAND() LIMIT 1");
            $card_info = $_rq ? mysqli_fetch_assoc($_rq) : null;

            if (!$card_info) {
                $_rq2 = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY RAND() LIMIT 1");
                $card_info = $_rq2 ? mysqli_fetch_assoc($_rq2) : null;
                $_seen = [];
            }

            if ($card_info) {
                $_new_seen = implode(',', array_unique(array_merge($_seen, [intval($card_info['id'])])));
                $_nv_esc = mysqli_real_escape_string($connect, $_new_seen);
                mysqli_query($connect, "UPDATE PaySetting SET ValuePay = '{$_nv_esc}' WHERE NamePay = '{$_ck_esc}'");
                if (mysqli_affected_rows($connect) === 0) {
                    mysqli_query($connect, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$_ck_esc}', '{$_nv_esc}')");
                }
            }
        }

        if (!$card_info || empty($card_info['cardnumber'])) {
            sendmessage($from_id, "❌ کارت بانکی فعالی یافت نشد. با پشتیبانی تماس بگیرید.", $keyboard, 'HTML');
            return;
        }
        $card_number = $card_info['cardnumber'];
        $PaySettingname = $card_info['namecard'];
        $valueprice = number_format($payRecord['price'] ?? 0);
        $price_copy = intval(($payRecord['price'] ?? 0) . "0");
        if ($_cmode == 'direct' && $_card2) {
            $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
        } else {
            $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
            $textcart = strtr($datatextbot['text_cart'] ?? "💳 مبلغ {price} تومان ({price}0 ریال) به کارت {card_number} به نام {name_card} واریز کنید.", $replacements);
        }
        $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
        if ($setting['statuscopycart'] == "1") {
            $_kb_rows = [[
                ['text' => "کپی کارت اول", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num'],
                ['text' => "کپی مبلغ", 'copy_text' => ["text" => $price_copy], '_rxck' => 'card_amount'],
            ]];
            if ($_cmode == 'direct' && !empty($_card2['cardnumber'])) {
                $_kb_rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
            }
            $_kb_rows[] = [rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)];
            $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
        } else {
            $sendresidcart = json_encode([
                'inline_keyboard' => [
                    [rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)],
                ]
            ]);
        }
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'] ?? null;
        if ($gethelp && $gethelp != 2) {
            $data = json_decode($gethelp, true);
            if (is_array($data)) {
                if ($data['type'] == "text") { sendmessage($from_id, $data['text'], null, 'HTML'); }
                elseif ($data['type'] == "photo") { sendphoto($from_id, $data['photoid'], $data['text']); }
                elseif ($data['type'] == "video") { sendvideo($from_id, $data['videoid'], $data['text']); }
            }
        }
        $cartMsgResult = rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, 'html');
        updatePaymentMessageId($cartMsgResult, $orderId);
        return;
    }
} elseif ($user['step'] == 'card_photo_step') {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!$photo || isset($update['message']['media_group_id'])) {
        sendmessage($from_id, "❌ لطفاً یک تصویر تکی از کارت خود ارسال کنید.", null, 'HTML');
        return;
    }
    $orderId = (string)($user['Processing_value'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $payRecord = select("Payment_report", "*", "id_order", $orderId);
    if ($payRecord === false || (string)($payRecord['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, "❌ رکورد پرداخت یافت نشد. مراحل را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "card_photo_file_id", $photoid, "id_order", $orderId);
    step('card_last4_step', $from_id);
    sendmessage($from_id, "✅ عکس کارت دریافت شد.\n\n🔢 لطفاً ۴ رقم آخر شماره کارت خود را وارد کنید:", null, 'HTML');
} elseif ($user['step'] == 'card_last4_step') {
    if (!isset($update['message']) && empty($text)) { return; }
    $_cl4 = trim((string)($text ?? ''));
    if (!preg_match('/^\d{4}$/', $_cl4)) {
        sendmessage($from_id, "❌ لطفاً دقیقاً ۴ رقم آخر کارت را وارد کنید (مثال: 1234).", null, 'HTML');
        return;
    }
    $orderId = (string)($user['Processing_value'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $payRecord = select("Payment_report", "*", "id_order", $orderId);
    if ($payRecord === false || (string)($payRecord['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, "❌ رکورد پرداخت یافت نشد. مراحل را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "card_last4", $_cl4, "id_order", $orderId);
    step('home', $from_id);
    $_uid = intval($from_id);
    $_access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid})))";
    $_cmode_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
    $_cmode_row = $_cmode_res ? mysqli_fetch_assoc($_cmode_res) : null;
    $_cmode = is_array($_cmode_row) ? ($_cmode_row['ValuePay'] ?? 'random') : 'random';

    $card_info = null;
    $_card2 = null;

    if ($_cmode == 'direct') {
        $_dq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY cn.created_at ASC LIMIT 2");
        $_dcards = [];
        if ($_dq) { while ($_r = mysqli_fetch_assoc($_dq)) { $_dcards[] = $_r; } }
        $card_info = $_dcards[0] ?? null;
        $_card2 = $_dcards[1] ?? null;
    } else {
        $_ck = "card_cycle_{$_uid}";
        $_ck_esc = mysqli_real_escape_string($connect, $_ck);
        $_seen_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = '{$_ck_esc}' LIMIT 1");
        $_seen_row = $_seen_res ? mysqli_fetch_assoc($_seen_res) : null;
        $_seen_str = is_array($_seen_row) ? ($_seen_row['ValuePay'] ?? '') : '';
        $_seen = array_values(array_filter(array_map('intval', explode(',', $_seen_str))));
        $_excl = !empty($_seen) ? "AND cn.id NOT IN (" . implode(',', $_seen) . ")" : "";

        $_rq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} {$_excl} ORDER BY RAND() LIMIT 1");
        $card_info = $_rq ? mysqli_fetch_assoc($_rq) : null;

        if (!$card_info) {
            $_rq2 = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY RAND() LIMIT 1");
            $card_info = $_rq2 ? mysqli_fetch_assoc($_rq2) : null;
            $_seen = [];
        }

        if ($card_info) {
            $_new_seen = implode(',', array_unique(array_merge($_seen, [intval($card_info['id'])])));
            $_nv_esc = mysqli_real_escape_string($connect, $_new_seen);
            mysqli_query($connect, "UPDATE PaySetting SET ValuePay = '{$_nv_esc}' WHERE NamePay = '{$_ck_esc}'");
            if (mysqli_affected_rows($connect) === 0) {
                mysqli_query($connect, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$_ck_esc}', '{$_nv_esc}')");
            }
        }
    }

    if (!$card_info || empty($card_info['cardnumber'])) {
        sendmessage($from_id, "❌ کارت بانکی فعالی یافت نشد. با پشتیبانی تماس بگیرید.", $keyboard, 'HTML');
        return;
    }
    $card_number = $card_info['cardnumber'];
    $PaySettingname = $card_info['namecard'];
    $valueprice = number_format($payRecord['price'] ?? 0);
    $price_copy = intval(($payRecord['price'] ?? 0) . "0");
    if ($_cmode == 'direct' && $_card2) {
        $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
    } else {
        $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
        $textcart = strtr($datatextbot['text_cart'] ?? "💳 مبلغ {price} تومان ({price}0 ریال) به کارت {card_number} به نام {name_card} واریز کنید.", $replacements);
    }
    $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
    if ($setting['statuscopycart'] == "1") {
        $_kb_rows = [[
            ['text' => "کپی شماره کارت", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num'],
            ['text' => "کپی مبلغ", 'copy_text' => ["text" => $price_copy], '_rxck' => 'card_amount'],
        ]];
        if ($_cmode == 'direct' && !empty($_card2['cardnumber'])) {
            $_kb_rows[] = [['text' => "کپی شماره کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
        }
        $_kb_rows[] = [rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)];
        $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
    } else {
        $sendresidcart = json_encode([
            'inline_keyboard' => [
                [rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)],
            ]
        ]);
    }
    $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'] ?? null;
    if ($gethelp && $gethelp != 2) {
        $data = json_decode($gethelp, true);
        if (is_array($data)) {
            if ($data['type'] == "text") { sendmessage($from_id, $data['text'], null, 'HTML'); }
            elseif ($data['type'] == "photo") { sendphoto($from_id, $data['photoid'], $data['text']); }
            elseif ($data['type'] == "video") { sendvideo($from_id, $data['videoid'], $data['text']); }
        }
    }
    $cartMsgResult = rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, 'html');
    updatePaymentMessageId($cartMsgResult, $orderId);
} elseif (preg_match('/^sendresidarze-(.*)/', $datain, $dataget) and $text_inline != null) {
    $payemntcheck = select("Payment_report", "*", "id_order", $dataget[1], "select");
    if ($payemntcheck['payment_Status'] == "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if ($payemntcheck['payment_Status'] == "expire") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 تصویر واریزی خود یا لینک تراکنش ترون را ارسال نمایید.", $backuser, 'HTML');
    step('getresidcurrency', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif ($user['step'] == "digitaltron_hash_input") {


    if (isset($datain) && $datain === "cancel_hash_input") {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        if (!empty($message_id)) {
            @deletemessage($from_id, $message_id);
        }
        sendmessage(
            $from_id,
            "🏠 از حالت ارسال هش خارج شدید. هر زمان خواستید می‌توانید روی دکمه «📨 ارسال هش تراکنش» در فاکتور خودتان کلیک کنید.",
            $keyboard,
            'HTML'
        );
        return;
    }
    if (!isset($update['message']) && empty($text)) { return; }

    $trimmed = trim((string) $text);
    $looksLikeNav = (
        $trimmed === ''
        || mb_strlen($trimmed) < 40
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $trimmed)
        || strpos($trimmed, ' ') !== false
    );
    if ($looksLikeNav) {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);


        if ($trimmed !== '') {
            sendmessage($from_id, "🏠 از حالت ارسال هش خارج شدید. اکنون می‌توانید از منو استفاده کنید.", $keyboard, 'HTML');
        }
        return;
    }
    $orderId = (string) ($user['Processing_value_four'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_attach_hash')) {
        sendmessage($from_id, "❌ ماژول هش‌چکر بارگذاری نشده است.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_extract_hash') || crypto_extract_hash($text) === null) {
        sendmessage($from_id, "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را ارسال کنید — نه آدرس کیف‌پول.", null, 'HTML');
        return;
    }
    $r = crypto_attach_hash($orderId, (string) $text);
    if (empty($r['ok'])) {
        $faMap = [
            'invalid-hash'      => 'هش وارد شده معتبر نیست.',
            'hash-already-used' => 'این هش قبلاً برای فاکتور دیگری ثبت شده است.',
            'order-not-pending' => 'این فاکتور در وضعیت قابل پرداخت نیست.',
            'no-db'             => 'خطای داخلی در پایگاه داده.',
            'db-update-failed'  => 'خطای داخلی در ذخیره‌سازی.',
        ];
        $msg = $faMap[$r['error'] ?? ''] ?? ($r['error'] ?? 'خطای ناشناخته');
        sendmessage($from_id, "❌ {$msg}", null, 'HTML');
        return;
    }
    $hashOrderRow = select("Payment_report", "*", "id_order", $orderId, "select");
    $hashOrderCur = (string) ($hashOrderRow['crypto_currency'] ?? '');
    $manualCurListHash = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
    $isManualHashCur = isset($manualCurListHash[$hashOrderCur]);

    if ($isManualHashCur) {
        update("user", "Processing_value_four", $orderId, "id", $from_id);
        step('digitaltron_receipt_photo', $from_id);
        sendmessage(
            $from_id,
            "✅ هش تراکنش شما ثبت شد.\n\n"
            . "⚠️ این شبکه به‌صورت خودکار بررسی نمی‌شود. برای ثبت درخواست بررسی توسط ادمین، حالا <b>عکس رسید تراکنش</b> را ارسال کنید (اجباری).\n\n"
            . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
            . "🔗 هش ثبت‌شده: <code>" . htmlspecialchars($r['hash']) . "</code>",
            null,
            'HTML'
        );
    } else {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        sendmessage(
            $from_id,
            "✅ هش تراکنش شما ثبت شد.\n\n"
            . "ربات هر <b>۱ دقیقه</b> یک بار به‌صورت خودکار شبکه را بررسی می‌کند:\n\n"
            . "✅ اگر همه چیز درست باشد → معمولاً <b>۱ تا ۲ دقیقه</b> طول می‌کشد و موجودی شارژ می‌شود.\n"
            . "❌ اگر مبلغ یا آدرس مقصد اشتباه باشد → <b>در همان دقیقه‌ی اول</b> پیام رد دریافت می‌کنید.\n"
            . "⏰ اگر هش روی شبکه پیدا نشود → پس از <b>۳۰ دقیقه</b> فاکتور لغو می‌شود.\n\n"
            . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
            . "🔗 هش ثبت‌شده: <code>" . htmlspecialchars($r['hash']) . "</code>",
            $keyboard,
            'HTML'
        );
    }
} elseif ($user['step'] == "digitaltron_receipt_photo") {

    if (isset($datain) && $datain === "cancel_hash_input") {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        sendmessage($from_id, "🏠 از حالت ارسال رسید خارج شدید.", $keyboard, 'HTML');
        return;
    }
    if (empty($photoid)) {
        $cancelKb = json_encode([
            'inline_keyboard' => [
                [['text' => '❌ انصراف', 'callback_data' => 'cancel_hash_input']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage($from_id, "❌ عکس دریافت نشد. لطفاً <b>عکس رسید تراکنش</b> را ارسال کنید (اجباری — فاکتور بدون عکس رسید برای ادمین ثبت نمی‌شود).", $cancelKb, 'HTML');
        return;
    }
    $orderId = (string) ($user['Processing_value_four'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $receiptRow = select("Payment_report", "*", "id_order", $orderId, "select");
    if (!$receiptRow || (string) ($receiptRow['id_user'] ?? '') !== (string) $from_id) {
        sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_found'] ?? "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "card_photo_file_id", (string) $photoid, "id_order", $orderId);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);

    $receiptCur = (string) ($receiptRow['crypto_currency'] ?? '');
    $receiptHash = (string) ($receiptRow['crypto_tx_hash'] ?? '');
    sendmessage(
        $from_id,
        "✅ عکس رسید ثبت شد.\n\n"
        . "درخواست شما برای ادمین ارسال شد. نتیجه از طریق همین چت اعلام خواهد شد.\n\n"
        . "🛒 کد فاکتور: <code>{$orderId}</code>",
        $keyboard,
        'HTML'
    );

    $receiptUserTagLine = '@' . ($user['username'] ?? 'none');
    $receiptAdminCaption = "🔁 <b>هش + رسید پرداخت کریپتو ثبت شد (شبکه دستی)</b>\n\n"
        . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$receiptUserTagLine})\n"
        . "💎 ارز: <b>{$receiptCur}</b>\n"
        . "💸 مبلغ تومانی: " . number_format((int) ($receiptRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars($receiptHash) . "</code>";
    $receiptAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $orderId],
                ['text' => '❌ رد درخواست',   'callback_data' => 'rejectcryptomanual_' . $orderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $receiptAdminIds = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($receiptAdminIds)) $receiptAdminIds = [];
    foreach ($receiptAdminIds as $receiptAdminId) {
        if (!is_numeric($receiptAdminId)) continue;
        telegram('sendphoto', [
            'chat_id' => $receiptAdminId,
            'photo' => (string) $photoid,
            'caption' => $receiptAdminCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => $receiptAdminKb,
        ]);
    }
} elseif ($user['step'] == "getresidcurrency") {
    if (!isset($update['message']) && empty($text)) { return; }
    $format_balance = number_format($user['Balance'], 0);
    step('home', $from_id);
    $PaymentReport = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Paymentusercount = select("Payment_report", "*", "id_user", $PaymentReport['id_user'], "count");
    if ($PaymentReport == false) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
        return;
    }
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $textdiscount = "";
    $format_price_cart = number_format($PaymentReport['price'], 0);
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $user['Processing_value_one'], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\n\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\nحجم محصول : {volume} گیگ\nزمان محصول : {service_time} روز\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $get_invoice['username'],
            'product_name' => $get_invoice['name_product'],
            'volume' => $get_invoice['Volume'],
            'service_time' => $get_invoice['Service_time'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } elseif ($user['Processing_value_tow'] == "getextenduser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($nameloc['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (FIND_IN_SET(?, Location) > 0 OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'product_name' => $prodcut['name_product'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } elseif ($user['Processing_value_tow'] == "getextravolumeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\nنام کاربری سرویس : {service_username}\nحجم خریداری شده  : {volumes}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'volumes' => $volumes,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } elseif ($user['Processing_value_tow'] == "getextratimeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\nنام کاربری سرویس : {service_username}\nتعداد روز خریداری شده  : {days}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'days' => $time,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } else {

        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    }
    $_arze_report_group = trim((string)($setting['Channel_Report'] ?? ''));
    if ($_arze_report_group !== '' && $_arze_report_group !== '0') {
        $_arze_thread = !empty($receiptreport) ? $receiptreport : null;
        if ($photo) {
            telegram('sendphoto', [
                'chat_id' => $_arze_report_group,
                'message_thread_id' => $_arze_thread,
                'photo' => $photoid,
                'caption' => $textbotlang['users']['Balance']['receiptimage'],
                'parse_mode' => "HTML",
            ]);
        }
        $_arze_report_result = telegram('sendmessage', [
            'chat_id' => $_arze_report_group,
            'message_thread_id' => $_arze_thread,
            'text' => $textsendrasid,
            'reply_markup' => $Confirm_pay,
            'parse_mode' => "HTML",
        ]);
        $_arze_report_msg_id = is_array($_arze_report_result) ? (int) ($_arze_report_result['result']['message_id'] ?? 0) : 0;
        if ($_arze_report_msg_id > 0) {
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ?, report_thread_id = ? WHERE id_order = ?")
                ->execute([$_arze_report_group, $_arze_report_msg_id, $_arze_thread, $PaymentReport['id_order']]);
        }
    } else {
        $_arze_private_targets = [];
        foreach ($admin_ids as $id_admin) {
            $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
            if ($adminrulecheck['rule'] == "support")
                continue;
            if ($photo) {
                telegram('sendphoto', [
                    'chat_id' => $id_admin,
                    'photo' => $photoid,
                    'caption' => $textbotlang['users']['Balance']['receiptimage'],
                    'parse_mode' => "HTML",
                ]);
            }
            $_arze_private_result = sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
            $_arze_private_msg_id = is_array($_arze_private_result) ? (int) ($_arze_private_result['result']['message_id'] ?? 0) : 0;
            if ($_arze_private_msg_id > 0) {
                $_arze_private_targets[] = ['admin_id' => $id_admin, 'chat_id' => $id_admin, 'message_id' => $_arze_private_msg_id];
            }
        }
        if (!empty($_arze_private_targets)) {
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ? WHERE id_order = ?")
                ->execute([$_arze_private_targets[0]['chat_id'], $_arze_private_targets[0]['message_id'], $PaymentReport['id_order']]);
            if (function_exists('update')) {
                update("Payment_report", "private_receipt_targets", json_encode($_arze_private_targets, JSON_UNESCAPED_UNICODE), "id_order", $PaymentReport['id_order']);
            }
        }
    }
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $PaymentReport['id_order']);
    update("Payment_report", "dec_not_confirmed", "$text $caption", "id_order", $PaymentReport['id_order']);
    $dateacc = date('Y/m/d H:i:s');
    update("Payment_report", "at_updated", $dateacc, "id_order", $PaymentReport['id_order']);
} elseif ($user['step'] == "cart_to_cart_user") {
    $format_balance = number_format($user['Balance'], 0);
    if (!$photo or isset($update['message']['media_group_id'])) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_single_photo_only', '❌  فقط مجاز به ارسال یک تصویر هستید'), null, 'HTML');
        return;
    }
    step('home', $from_id);
    $PaymentReport = select("Payment_report", "*", "id_order", $user['Processing_value']);
    if ($PaymentReport == false) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
        return;
    }
    $receiptReserveStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'pending', dec_not_confirmed = 'receipt-uploading', at_updated = :at_updated WHERE id_order = :id_order AND id_user = :id_user AND payment_Status = 'Unpaid'");
    $receiptReserveStmt->execute([
        ':at_updated' => date('Y/m/d H:i:s'),
        ':id_order' => $PaymentReport['id_order'],
        ':id_user' => $from_id,
    ]);
    if ($receiptReserveStmt->rowCount() !== 1) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
        return;
    }
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $format_price_cart = number_format($PaymentReport['price'], 0);
    $split_data = explode('|', $PaymentReport['id_invoice']);
    if ($split_data[0] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $split_data[1], "select");
        if ($get_invoice == false) {
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'")
                ->execute([':id_order' => $PaymentReport['id_order']]);
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $textdiscount = "";
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\nنام کاربری سرویس  : {service_username}\nنام محصول : {product_name}\nحجم محصول : {volume} گیگ\nزمان محصول : {service_time} روز\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $get_invoice['username'],
            'product_name' => $get_invoice['name_product'],
            'volume' => $get_invoice['Volume'],
            'service_time' => $get_invoice['Service_time'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextenduser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'")
                ->execute([':id_order' => $PaymentReport['id_order']]);
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($service_other['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (FIND_IN_SET(?, Location) > 0 OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'product_name' => $prodcut['name_product'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        sendmessage($from_id, faoxima_textbot_get('dyn_cart_receipt_extend_sent', '🚀 رسید شما ارسال و پس از بررسی سرویس شما تمدید خواهد شد'), $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextravolumeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\nنام کاربری سرویس : {service_username}\nحجم خریداری شده  : {volumes}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'volumes' => $volumes,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        sendmessage($from_id, faoxima_textbot_get('dyn_cart_receipt_extra_volume_sent', '🚀 رسید شما ارسال و پس از بررسی  به سرویس شما حجم اضافه خواهد شد.'), $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextratimeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\nنام کاربری سرویس : {service_username}\nتعداد روز خریداری شده  : {days}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'days' => $time,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        sendmessage($from_id, faoxima_textbot_get('dyn_cart_receipt_extra_time_sent', '🚀 رسید شما ارسال و پس از بررسی به سرویس شما زمان اضافه خواهد شد'), $keyboard, 'HTML');
    } else {

        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
    $_card_fid = (string)($PaymentReport['card_photo_file_id'] ?? '');
    $_receipt_route = rxReceiptDeliveryRoute();
    $_receipt_report_group = trim((string)($_receipt_route['chat_id'] ?? ''));
    if (!empty($_receipt_route['topic_enabled']) && $_receipt_report_group !== '') {
        $_receipt_thread = !empty($_receipt_route['thread_id']) ? (int)$_receipt_route['thread_id'] : null;
        if ($_card_fid !== '') {
            telegram('sendMediaGroup', [
                'chat_id' => $_receipt_report_group,
                'message_thread_id' => $_receipt_thread,
                'media'   => [
                    ['type' => 'photo', 'media' => $_card_fid, 'caption' => faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر')],
                    ['type' => 'photo', 'media' => $photoid, 'caption' => faoxima_textbot_get('dyn_receipt_payment_receipt_caption', '🧾 رسید پرداخت')],
                ],
            ]);
        } else {
            telegram('sendphoto', [
                'chat_id'    => $_receipt_report_group,
                'message_thread_id' => $_receipt_thread,
                'photo'      => $photoid,
                'caption'    => $caption,
                'parse_mode' => "HTML",
            ]);
        }
        $_receipt_report_result = telegram('sendmessage', [
            'chat_id' => $_receipt_report_group,
            'message_thread_id' => $_receipt_thread,
            'text' => $textsendrasid,
            'reply_markup' => $Confirm_pay,
            'parse_mode' => "HTML",
        ]);
        $_receipt_report_msg_id = is_array($_receipt_report_result) ? (int) ($_receipt_report_result['result']['message_id'] ?? 0) : 0;
        if ($_receipt_report_msg_id > 0) {
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ?, report_thread_id = ? WHERE id_order = ?")
                ->execute([$_receipt_report_group, $_receipt_report_msg_id, $_receipt_thread, $PaymentReport['id_order']]);
        }
    } elseif (empty($_receipt_route['topic_enabled'])) {
        $_receipt_private_targets = [];
        foreach ($admin_ids as $id_admin) {
            $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
            if ($adminrulecheck['rule'] == "support")
                continue;
            if ($_card_fid !== '') {
                telegram('sendMediaGroup', [
                    'chat_id' => $id_admin,
                    'media'   => [
                        ['type' => 'photo', 'media' => $_card_fid, 'caption' => faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر')],
                        ['type' => 'photo', 'media' => $photoid, 'caption' => faoxima_textbot_get('dyn_receipt_payment_receipt_caption', '🧾 رسید پرداخت')],
                    ],
                ]);
            } else {
                telegram('sendphoto', [
                    'chat_id'    => $id_admin,
                    'photo'      => $photoid,
                    'caption'    => $caption,
                    'parse_mode' => "HTML",
                ]);
            }
            $_receipt_private_result = sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
            $_receipt_private_msg_id = is_array($_receipt_private_result) ? (int) ($_receipt_private_result['result']['message_id'] ?? 0) : 0;
            if ($_receipt_private_msg_id > 0) {
                $_receipt_private_targets[] = ['admin_id' => $id_admin, 'chat_id' => $id_admin, 'message_id' => $_receipt_private_msg_id];
            }
        }
        if (!empty($_receipt_private_targets)) {
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ? WHERE id_order = ?")
                ->execute([$_receipt_private_targets[0]['chat_id'], $_receipt_private_targets[0]['message_id'], $PaymentReport['id_order']]);
            if (function_exists('update')) {
                update("Payment_report", "private_receipt_targets", json_encode($_receipt_private_targets, JSON_UNESCAPED_UNICODE), "id_order", $PaymentReport['id_order']);
            }
        }
    }
    $dateacc = date('Y/m/d H:i:s');
    $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting', dec_not_confirmed = 'receipt-submitted', card_photo_file_id = :card_photo_file_id, at_updated = :at_updated WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'");
    $stmt->execute([
        ':card_photo_file_id' => $photoid,
        ':at_updated' => $dateacc,
        ':id_order' => $PaymentReport['id_order'],
    ]);
    if ($stmt->rowCount() !== 1) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
        return;
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('Payment_report');
    }
    $_verifyReceiptCard = select('Payment_report', 'card_photo_file_id', 'id_order', $PaymentReport['id_order'], 'select', ['cache' => false]);
    if (empty($_verifyReceiptCard['card_photo_file_id']) && function_exists('rx_log_event')) {
        rx_log_event('RECEIPT_PHOTO_SAVE_VERIFY_FAILED', 'card_photo_file_id still empty right after commit', [
            'id_order' => $PaymentReport['id_order'],
        ]);
    }
} elseif ($datain == "Discount") {
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Discount']['getcode'], $bakinfos);
    step('get_code_user', $from_id);
} elseif ($user['step'] == "get_code_user") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!rxTableValueExists('Discount', 'code', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], null, 'HTML');
        return;
    }
    $checklimit = select("Discount", "*", "code", $text, "select");
    $__gstatus = strtolower(trim((string)($checklimit['status'] ?? '')));
    if ($__gstatus !== '' && $__gstatus !== 'active') {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gtarget = trim((string)($checklimit['target_user'] ?? ''));
    if ($__gtarget !== '' && $__gtarget !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gexp = intval($checklimit['expire_at'] ?? 0);
    if ($__gexp !== 0 && time() >= $__gexp) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    if (intval($checklimit['limituse']) > 0 && intval($checklimit['limitused']) >= intval($checklimit['limituse'])) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :code LIMIT 1");
    $stmt->bindParam(':code', $text);
    $stmt->execute();
    $get_codesql = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_user = $user['Balance'] + $get_codesql['price'];
    update("user", "Balance", $balance_user, "id", $from_id);
    $discountlimitadd = intval($checklimit['limitused']) + 1;
    update("Discount", "limitused", $discountlimitadd, "code", $text);
    step('home', $from_id);
    $text_balance_code = sprintf($textbotlang['users']['Discount']['giftcodesuccess'], rxFormatToman($get_codesql['price']));
    sendmessage($from_id, $text_balance_code, $keyboard, 'HTML');
    $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (:id_user, :code)");
    $stmt->execute([
        ':id_user' => $from_id,
        ':code' => $text,
    ]);
    $text_report = sprintf($textbotlang['users']['Discount']['giftcodeused'], $username, $from_id, $text);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (($text !== '' && $text == $datatextbot['text_Tariff_list']) || $datain == "Tariff_list") {
    sendmessage($from_id, $datatextbot['text_dec_Tariff_list'], null, 'HTML');
} elseif ($datain == "colselist") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['text_affiliates']) || $datain == "affiliatesbtn") {
    if (!check_active_btn($setting['keyboardmain'], "text_affiliates")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['affiliatesstatus'] == "offaffiliates") {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], null, 'HTML');
        return;
    }
    $affiliates = select("affiliates", "*", null, null, "select");
    $rxAffiliateLink = "https://t.me/$usernamebot?start=$from_id";
    $rxAffiliateBannerTpl = !empty($affiliates['banner_text']) ? $affiliates['banner_text'] : ($affiliates['description'] ?? '');
    $rxAffiliateBannerText = faoxima_render_text($rxAffiliateBannerTpl, [
        'link' => $rxAffiliateLink,
        'name' => $user['first_name'] ?? $username,
        'code' => $user['codeInvitation'] ?? $from_id,
    ]);
    $textaffiliates = "{$rxAffiliateBannerText}\n\n🔗 {$rxAffiliateLink}";
    if (strlen($affiliates['id_media']) >= 5 && $affiliates['id_media'] !== 'none') {
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => $affiliates['id_media'],
            'caption' => $textaffiliates,
            'parse_mode' => "HTML",
        ]);
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $sqlPanel = "SELECT COUNT(*) AS orders, COALESCE(SUM(price_product), 0) AS total_price
                 FROM invoice
                 WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')
                 AND refral = :refral
                 AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sqlPanel);
    $stmt->execute([':refral' => $from_id]);
    $inforefral = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'total_price' => 0];
    $orders_count = (int)($inforefral['orders'] ?? 0);
    $total_purchase = (float)($inforefral['total_price'] ?? 0);
    $keyboard_share = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🎁 هدیه عضویت", 'callback_data' => "get_gift_start"],
                ['text' => "🔗 اشتراک لینک", 'url' => "https://t.me/share/url?url=https://t.me/$usernamebot?start=$from_id"],
            ],
        ]
    ]);
    $text_start = "";
    $text_porsant = "";
    $Percent_porsant = $setting['affiliatespercentage'];
    $sum_order = number_format($total_purchase, 0);
    $rxCommissionEarnedStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE id_user = :id_user AND category = 'affiliate_commission' AND direction = 'credit'");
    $rxCommissionEarnedStmt->execute([':id_user' => $from_id]);
    $rxCommissionEarned = number_format((float) $rxCommissionEarnedStmt->fetchColumn());
    $rxPendingGiftStmt = $pdo->prepare("SELECT COUNT(*) FROM reagent_report WHERE reagent = :reagent AND get_gift = 0");
    $rxPendingGiftStmt->execute([':reagent' => $from_id]);
    $rxPendingGiftCount = (int) $rxPendingGiftStmt->fetchColumn();
    if ($affiliatescommission['Discount'] == "onDiscountaffiliates") {
        $rxFmtAffiliateDiscount = rxFormatToman($affiliatescommission['price_Discount'] ?? 0);
        $text_start = "<b>🎁 هدیه عضویت:</b>
• 🎉 مجموع هدیه: {$rxFmtAffiliateDiscount} تومان
• 🔻 ۵۰٪ برای شما (معرف)
• 🔻 ۵۰٪ برای زیرمجموعه (کاربر جدید)
";
    }
    if ($affiliatescommission['status_commission'] == "oncommission") {
        $text_porsant = "<b>💸 پورسانت خرید:</b>
•  $Percent_porsant درصد از مبلغ خرید زیرمجموعه به شما تعلق می‌گیره";
    }
    $textaffiliates = "<b>💼 زیرمجموعه‌گیری و هدیه خوش‌آمد</b>

با دعوت دوستان از طریق <b>لینک اختصاصی</b>، بدون پرداخت حتی ۱ ریال کیف پولت شارژ میشه و از خدمات ربات استفاده می‌کنی!

$text_start
$text_porsant

<b>📊 آمار شما:</b>
• 👥 زیرمجموعه‌ها: {$user['affiliatescount']} نفر
• 🛒 خریدها: $orders_count عدد
• 💵 مجموع خرید: $sum_order تومان
• 💰 مجموع پورسانت دریافتی: $rxCommissionEarned تومان
• ⏳ هدیه‌های در انتظار دریافت: $rxPendingGiftCount نفر

<b>📢 دعوت کن، هدیه بگیر، رشد کن!</b>
";
    $rxAffiliateInfoTpl = (string) ($textbotlang['users']['affiliates']['affiliateinfo'] ?? '');
    if (trim($rxAffiliateInfoTpl) !== '') {
        try {
            $rxAffiliateInfoText = vsprintf($rxAffiliateInfoTpl, [
                rxFormatToman($affiliatescommission['price_Discount'] ?? 0),
                $Percent_porsant,
                $user['affiliatescount'],
                $orders_count,
                $sum_order,
                $rxCommissionEarned,
                $rxPendingGiftCount,
            ]);
        } catch (\Throwable $rxAffiliateInfoErr) {
            $rxAffiliateInfoText = false;
        }
        if (is_string($rxAffiliateInfoText) && trim($rxAffiliateInfoText) !== '') {
            $textaffiliates = $rxAffiliateInfoText;
        }
    }

    sendmessage($from_id, $textaffiliates, $keyboard_share, 'HTML');
} elseif ($datain == "get_gift_start") {
    $gift_status = select("affiliates", "*", null, null, "select");
    if ($gift_status['Discount'] == "offDiscountaffiliates") {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_section_disabled'] ?? "📛 این بخش درحال حاضر غیرفعال می باشد", $keyboard, 'HTML');
        return;
    }
    if (!userExists($user['affiliates'])) {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_not_affiliate_of_anyone'] ?? "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
        return;
    }
    $reagent = select("reagent_report", "*", "user_id", $from_id, "select", ['cache' => false]);
    if (!$reagent) {
        $affiliateId = intval($user['affiliates']);
        if ($affiliateId && userExists($affiliateId)) {
            $stmt = $pdo->prepare("INSERT INTO reagent_report (user_id, get_gift, time, reagent)
                                   VALUES (:user_id, :get_gift, :time, :reagent)
                                   ON DUPLICATE KEY UPDATE reagent = VALUES(reagent), get_gift = VALUES(get_gift), time = VALUES(time)");
            $stmt->execute([
                ':user_id' => $from_id,
                ':get_gift' => 0,
                ':time' => date('Y/m/d H:i:s'),
                ':reagent' => $affiliateId,
            ]);
            if (function_exists('clearSelectCache')) {
                clearSelectCache('reagent_report');
            }
            $reagent = select("reagent_report", "*", "user_id", $from_id, "select", ['cache' => false]);
        }
        if (!$reagent) {
            sendmessage($from_id, $datatextbot['dyn_affiliates_extra_not_affiliate_of_anyone'] ?? "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
            return;
        }
    }
    if (!empty($reagent['get_gift'])) {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_gift_already_received'] ?? "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>\nاین هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است.", $keyboard, 'HTML');
        return;
    }
    $affiliatesGate = select("affiliates", "*", null, null, "select");
    if (($affiliatesGate['require_phone_verified'] ?? '') === 'on_require_phone' && ($user['number'] ?? 'none') !== 'confrim number by admin') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_phone_required'] ?? "📞 برای دریافت هدیه عضویت، ابتدا باید شماره موبایل خود را تایید کنید.", $keyboard, 'HTML');
        return;
    }
    $minAgeHours = (int) ($affiliatesGate['min_account_age_hours'] ?? 0);
    if ($minAgeHours > 0 && !empty($user['register']) && $user['register'] !== 'none') {
        $accountAgeHours = (time() - (int) $user['register']) / 3600;
        if ($accountAgeHours < $minAgeHours) {
            sendmessage($from_id, $datatextbot['dyn_affiliates_extra_account_too_new'] ?? "⏳ حساب شما هنوز به اندازه کافی قدیمی نیست، کمی بعد دوباره تلاش کنید.", $keyboard, 'HTML');
            return;
        }
    }
    $referrerIdForCap = $reagent['reagent'];
    $dailyCap = (int) ($affiliatesGate['daily_referral_cap'] ?? 0);
    $monthlyCap = (int) ($affiliatesGate['monthly_referral_cap'] ?? 0);
    if ($dailyCap > 0 || $monthlyCap > 0) {
        if ($dailyCap > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_referral_claims WHERE reagent = :reagent AND claim_date = :today");
            $stmt->execute([':reagent' => $referrerIdForCap, ':today' => date('Y-m-d')]);
            if ((int) $stmt->fetchColumn() >= $dailyCap) {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_cap_reached'] ?? "📛 سقف روزانه هدیه معرفی برای این معرف پر شده است.", $keyboard, 'HTML');
                return;
            }
        }
        if ($monthlyCap > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_referral_claims WHERE reagent = :reagent AND claim_date >= :monthstart");
            $stmt->execute([':reagent' => $referrerIdForCap, ':monthstart' => date('Y-m-01')]);
            if ((int) $stmt->fetchColumn() >= $monthlyCap) {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_cap_reached'] ?? "📛 سقف ماهانه هدیه معرفی برای این معرف پر شده است.", $keyboard, 'HTML');
                return;
            }
        }
    }
    $stmt = $pdo->prepare("UPDATE reagent_report SET get_gift = 1 WHERE user_id = :user_id AND get_gift = 0");
    $stmt->execute([':user_id' => $from_id]);
    if ($stmt->rowCount() === 0) {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_gift_already_received'] ?? "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>\nاین هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است.", $keyboard, 'HTML');
        return;
    }
    $claimStmt = $pdo->prepare("INSERT IGNORE INTO affiliate_referral_claims (reagent, invitee_id, claim_type, claim_date, time) VALUES (:reagent, :invitee_id, 'start_gift', :claim_date, :time)");
    $claimStmt->execute([
        ':reagent' => $referrerIdForCap,
        ':invitee_id' => $from_id,
        ':claim_date' => date('Y-m-d'),
        ':time' => date('Y/m/d H:i:s'),
    ]);
    $reagent['get_gift'] = true;
    $price_gift_Start = select("affiliates", "*", null, null, "select");
    $price_gift_Start = intval($price_gift_Start['price_Discount']) / 2;
    $useraffiliates = select("user", "*", 'id', $reagent['reagent'], "select");
    if (function_exists('balance_atomic_credit') && $price_gift_Start > 0) {
        $__giftOkRef = balance_atomic_credit($reagent['reagent'], $price_gift_Start);
        if ($__giftOkRef && function_exists('wallet_ledger_record')) {
            wallet_ledger_record($reagent['reagent'], 'credit', $price_gift_Start, 'referral_gift', 'هدیه عضویت زیرمجموعه', null, 'user', (string)$from_id);
        }
        $__giftOkUser = balance_atomic_credit($from_id, $price_gift_Start);
        if ($__giftOkUser && function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'credit', $price_gift_Start, 'referral_gift', 'هدیه عضویت', null, 'user', (string)$reagent['reagent']);
        }
    } else {
        $Balance_add_regent = $useraffiliates['Balance'] + $price_gift_Start;
        update("user", "Balance", $Balance_add_regent, "id", $reagent['reagent']);
        $Balance_add_user = $user['Balance'] + $price_gift_Start;
        update("user", "Balance", $Balance_add_user, "id", $from_id);
    }
    $Balance_add_regent = select("user", "Balance", "id", $reagent['reagent'], "select", ['cache' => false])['Balance'] ?? ($useraffiliates['Balance'] + $price_gift_Start);
    $Balance_add_user = select("user", "Balance", "id", $from_id, "select", ['cache' => false])['Balance'] ?? ($user['Balance'] + $price_gift_Start);
    $addbalancediscount = number_format($price_gift_Start, 0);
    sendmessage($reagent['reagent'], $datatextbot['dyn_affiliates_extra_gift_credited_inviter'] ?? "🎉 یک نفر با معرفی شما وارد شد! هدیه به حساب شما واریز شد.", null, 'html');
    sendmessage($from_id, $datatextbot['dyn_affiliates_extra_gift_activated'] ?? "🎉 هدیه عضویت برای شما فعال شد!", null, 'html');
    $rxFmtUserBalanceBefore = rxFormatToman($user['Balance']);
    $rxFmtUserBalanceAfter = rxFormatToman($Balance_add_user);
    $rxFmtReagentBalanceBefore = rxFormatToman($useraffiliates['Balance']);
    $rxFmtReagentBalanceAfter = rxFormatToman($Balance_add_regent);
    $report_join_gift = "🎁 پرداخت هدیه عضویت
<blockquote> -آیدی عددی : $from_id</blockquote>
<blockquote> - نام کاربری : @$username</blockquote>
<blockquote> - آیدی عددی معرف : {$reagent['reagent']}</blockquote>
<blockquote> - موجودی زیرمجموعه قبل از هدیه : {$rxFmtUserBalanceBefore}</blockquote>
<blockquote> - موجودی زیرمجموعه بعد از هدیه : {$rxFmtUserBalanceAfter}</blockquote>
<blockquote>  - موجودی معرف قبل از هدیه : {$rxFmtReagentBalanceBefore}</blockquote>
<blockquote> - موجودی معرف بعد از هدیه : {$rxFmtReagentBalanceAfter}</blockquote>
 ";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $porsantreport,
            'text' => $report_join_gift,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/Extra_volumes_(\w+)_(.*)/', $datain, $dataget)) {
    $usernamepanel = $dataget[1];
    $locations = select("marzban_panel", "*", "code_panel", $dataget[2], "select");
    $location = $locations['name_panel'];
    $eextraprice = json_decode($locations['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $usernamepanel, "id", $from_id);
    update("user", "Processing_value_one", $location, "id", $from_id);

    $textextra = sprintf($textbotlang['users']['Extra_volume']['enterextravolume'], $extrapricevalue);
    sendmessage($from_id, $textextra, $backuser, 'HTML');
    step('getvolumeextras', $from_id);
} elseif ($user['step'] == "getvolumeextras") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextra = $extrapricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextras_' . $priceextra],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = sprintf($textbotlang['users']['Extra_volume']['extravolumeinvoice'], $extrapricevalues, $priceextra, $text);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextras_(\w+)/', $datain, $dataget)) {
    $volume = $dataget[1];
    if ($user['Balance'] < $volume && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($user['Processing_value_one'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($volume * $user['pricediscount']) / 100;
                $volume = $volume - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $volume - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volume) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0) {
        $result = ($volume * $user['pricediscount']) / 100;
        $volume = $volume - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }

    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_one'], $user['Processing_value']);
    $data_limit = $DataUserOut['data_limit'] + (intval($volume) / intval($extrapricevalue) * pow(1024, 3));
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price) VALUES (:id_user, :username, :value, :type, :time, :price)");
    $value = $data_limit;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $user['Processing_value'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volume,
    ]);
    $data_limit_new = (intval($volume) / intval($extrapricevalue));
    $extra_volume = $ManagePanel->extra_volume($user['Processing_value'], $marzban_list_get['code_panel'], $data_limit_new);
    if ($extra_volume['status'] == false) {
        $extra_volume['msg'] = rx_panel_error_text($extra_volume['msg'] ?? null, $extra_volume['detail'] ?? null);
        $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$user['Processing_value_one']}</blockquote>
<blockquote>نام کاربری سرویس : {$user['Processing_value']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if (function_exists('balance_atomic_charge')) {
        $__allowNegEv = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__chargeEv = balance_atomic_charge($from_id, (float)$volume, $__allowNegEv);
        if (empty($__chargeEv['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEv['new_balance'];
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $volume, 'service_action', 'خرید حجم اضافه', null, 'invoice', (string)($user['Processing_value'] ?? ''));
        }
    } else {
        $Balance_Low_user = $user['Balance'] - $volume;
        update("user", "Balance", $Balance_Low_user, "id", $from_id);
    }
    $back = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], $back, 'HTML');
    $volumes = $volume / $extrapricevalue;
    $volumes = number_format($volumes, 0);
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['volumepurchase'], $from_id, $volumes, $volume, $user['Balance'], $user['Processing_value']);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('volume_topup', [
            'user_id' => $from_id,
            'amount'  => $volumes,
            'price'   => number_format((float)$volume),
        ], $setting);
    }
} elseif ($datain == "searchservice") {
    sendmessage($from_id, $textbotlang['users']['search']['usernamgeget'], $backuser, 'HTML');
    step('getuseragnetservice', $from_id);
} elseif ($datain == "Responseuser") {
    step('getmessageAsuser', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backuser, 'HTML');
} elseif ($user['step'] == "getmessageAsuser") {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $textbotlang['users']['support']['sendmessageadmin'], $keyboard, 'HTML');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        if ($text) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['usermessage'], $from_id, $username, $caption . $text);
            sendmessage($id_admin, $textsendadmin, $Response, 'HTML');
        }
        if ($photo) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['userresponse'], $from_id, $username, $caption);
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'reply_markup' => $Response,
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        }
    }
    step('home', $from_id);
} elseif ((($text !== '' && $text == $datatextbot['textpanelagent']) || $datain == "agentpanel") && $user['agent'] != "f") {
    if ($setting['inlinebtnmain'] == "oninline") {
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    }
} elseif ($text == $textbotlang['users']['agenttext']['customnameusername'] || $datain == "selectname") {
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
    step('selectusernamecustom', $from_id);
} elseif ($user['step'] == "selectusernamecustom") {
    if (!isset($update['message']) && empty($text)) { return; }
    $text = str_replace('_', '-', $text);
    if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
        sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['agent']['submitusername'], $keyboardagent, 'html');
    update("user", "namecustom", $text, "id", $from_id);
    step("home", $from_id);
} elseif (($text !== '' && $text == $datatextbot['textrequestagent']) || $datain == "requestagent") {
    if ($user['Balance'] < $setting['agentreqprice']) {
        $priceagent = number_format($setting['agentreqprice']);
        sendmessage($from_id, sprintf($textbotlang['users']['agenttext']['insufficientbalanceagent'], $priceagent), $backuser, 'HTML');
        return;
    }
    $existingAgentRequest = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($existingAgentRequest) {
        // فقط درخواستی که هنوز در حال بررسی (waiting) است مانع ثبت درخواست جدید می‌شود.
        if ($existingAgentRequest['status'] == "waiting") {
            sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], null, 'html');
            return;
        }
        // اگر درخواست قبلی رد شده بود، رکورد قدیمی پاک می‌شود تا کاربر بتواند مجدداً درخواست دهد.
        if ($existingAgentRequest['status'] == "reject") {
            $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id AND status = 'reject'");
            $stmtDelOld->execute([':id' => $from_id]);
        }
    }
    if ($user['agent'] != "f") {
        sendmessage($from_id, $textbotlang['users']['agenttext']['isagent'], null, 'html');
        return;
    }
    if ($datain == "requestagent") {
        Editmessagetext($from_id, $message_id, $datatextbot['text_request_agent_dec'], $backuser);
    } else {
        sendmessage($from_id, $datatextbot['text_request_agent_dec'], $backuser, 'html');
    }
    step("getagentrequest", $from_id);
} elseif ($user['step'] == "getagentrequest" && $text) {
    if (!isset($update['message']) && empty($text)) { return; }
    // اطمینان از نبود درخواست قدیمیِ رد/تاییدشده تا INSERT جدید با خطای کلید تکراری مواجه نشود.
    $oldReq = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($oldReq && $oldReq['status'] == "waiting") {
        // اگر همزمان درخواست در حال بررسی ثبت شده، از ثبت دوباره جلوگیری می‌کنیم.
        sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    if ($oldReq) {
        $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id");
        $stmtDelOld->execute([':id' => $from_id]);
    }
    $balancelow = $user['Balance'] - $setting['agentreqprice'];
    update("user", "Balance", $balancelow, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['agenttext']['endrequest'], $keyboard, 'html');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO Requestagent (id, username, time, Description, status, type) VALUES (:id, :username, :time, :description, :status, :type)");
    $status = "waiting";
    $type = "None";
    $current_time = time();
    $description = $text;
    $requestAgentInserted = false;
    try {
        $stmt->execute([
            ':id' => $from_id,
            ':username' => $username,
            ':time' => $current_time,
            ':description' => $description,
            ':status' => $status,
            ':type' => $type,
        ]);
        $requestAgentInserted = true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            $tableConverted = ensureTableUtf8mb4('Requestagent');
            if ($tableConverted) {
                try {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $description,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . $retryException->getMessage());
                }
            }

            if (!$requestAgentInserted) {
                $sanitisedDescription = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $description);
                if ($sanitisedDescription !== $description) {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $sanitisedDescription,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } else {
                    throw $e;
                }
            }
        } else {
            throw $e;
        }
    }

    if (!$requestAgentInserted) {
        throw new RuntimeException('Failed to persist agent request description.');
    }
    $textrequestagent = sprintf($textbotlang['users']['agenttext']['agent-request'], $from_id, $username, $first_name, $text);
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $from_id], ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $from_id]],
            [
                ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textrequestagent, $keyboardmanage, 'HTML');
    }
} elseif ($text == "/privacy") {
    sendmessage($from_id, $datatextbot['text_roll'], null, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['text_wheel_luck']) || $datain == "wheel_luck" || $text == "/gift") {
    if (!check_active_btn($setting['keyboardmain'], "text_wheel_luck")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['wheelagent'] == "0" and $user['agent'] != "f") {
        sendmessage($from_id, $datatextbot['dyn_wheel_button_disabled_you'] ?? "❌ این دکمه برای شما غیرفعال می باشد", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if (intval($setting['statusfirstwheel']) == 1 and $countinvoice != 0) {
        sendmessage($from_id, $datatextbot['dyn_wheel_no_purchase_users_only'] ?? "❌ متاسفانه این آپشن فقط برای کاربرانی فعال است که از ربات خریدی نداشته باشند.", null, 'HTML');
        return;
    }
    if ($setting['wheelـluck'] == "0" or ($setting['wheelagent'] == "0" and $users['agent'] != "f")) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['feature-disabled'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = '$from_id' ORDER BY time DESC LIMIT 1");
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    if (intval($setting['Dice']) == 1) {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎲",
        ]);
        sleep(4.5);
    } else {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎰",
        ]);
        sleep(4);
    }
    if (!is_array($diceResponse) || empty($diceResponse['ok']) || !isset($diceResponse['result']['dice']['value'])) {
        $errorContext = is_array($diceResponse) ? json_encode($diceResponse) : (is_string($diceResponse) ? $diceResponse : 'empty response');
        error_log('Failed to receive dice value for wheel_luck: ' . $errorContext);
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['error'] ?? '❌ خطایی در دریافت نتیجه بازی رخ داد. لطفاً بعداً مجدداً تلاش کنید.', null, 'HTML');
        return;
    }
    $diceValue = (int) $diceResponse['result']['dice']['value'];
    $dateacc = date('Y/m/d H:i:s');
    $wheelDay = date('Y-m-d');
    $status = false;
    if (intval($setting['Dice']) == 1) {
        if ($diceValue === 6) {
            $status = true;
        }
    } else {
        if (in_array($diceValue, [1, 43, 64, 22], true)) {
            $status = true;
        }
    }
    $pricelast = $status ? $setting['wheelـluck_price'] : 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO wheel_list (id_user,first_name,wheel_code,time,price,wheel_day) VALUES (:id_user,:first_name,:wheel_code,:time,:price,:wheel_day)");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':wheel_code', $diceValue);
    $stmt->bindParam(':time', $dateacc);
    $stmt->bindParam(':price', $pricelast);
    $stmt->bindParam(':wheel_day', $wheelDay);
    $stmt->execute();
    if ($stmt->rowCount() !== 1) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    if ($status) {
        $wheelPrize = intval($setting['wheelـluck_price']);
        if (function_exists('balance_atomic_credit') && $wheelPrize > 0) {
            $__wheelOk = balance_atomic_credit($from_id, $wheelPrize);
            if ($__wheelOk && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $wheelPrize, 'lottery', 'جایزه گردونه شانس', null, 'wheel_list', (string)$from_id);
            }
        } else {
            $balance_last = $wheelPrize + $user['Balance'];
            update("user", "Balance", $balance_last, "id", $from_id);
        }
        $price = number_format($setting['wheelـluck_price']);
        sendmessage($from_id, sprintf($textbotlang['users']['wheel_luck']['winner-congratulations'], $price), null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport,
                'text' => sprintf($textbotlang['users']['wheel_luck']['wheel-winner'], $username, $from_id),
                'parse_mode' => "HTML"
            ]);
        }
    } else {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['notWinner'], null, 'HTML');
    }
} elseif ($text == "/tron") {
    $rates = requireTronRates(['TRX']);
    if ($rates === null) {
        sendmessage($from_id, $datatextbot['dyn_wheel_price_unavailable'] ?? "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['TRX'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tron-price'], $price), null, 'HTML');
} elseif ($text == "/usd") {
    $rates = requireTronRates(['USD']);
    if ($rates === null) {
        sendmessage($from_id, $datatextbot['dyn_wheel_price_unavailable'] ?? "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['USD'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tether-price'], $price), null, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['text_extend']) || $datain == "extendbtn") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0) {
        sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
        return;
    }
    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $_start_index_i = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_start_index_i, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json, 'html');
    }
} elseif ($datain == 'next_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_si2 = (int)$start_index; $_p2 = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_si2, $_p2);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == 'previous_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $_pp = (int)$previous_page; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_pp, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif (preg_match('/changenote_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $rxNoteCheck = select("invoice", "id_user", "id_invoice", $id_invoice, "select");
    if (!is_array($rxNoteCheck) || (string)($rxNoteCheck['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changenote on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changenote',
            ]);
        }
        return;
    }
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['note']['SendNote'], $backinfoss);
    step("getnotedit", $from_id);
} elseif ($user['step'] == "getnotedit") {
    if (!isset($update['message']) && empty($text)) { return; }
    $invoice = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (strlen($text) > 150) {
        sendmessage($from_id, $textbotlang['users']['note']['ErrorLongNote'], $keyboard, "html");
        return;
    }
    $text = sanitizeUserName($text);
    $id_invoice = $user['Processing_value'];
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    update("invoice", "note", $text, "id_invoice", $id_invoice);
    sendmessage($from_id, $textbotlang['users']['note']['changednote'], $backinfoss, "html");
    step("home", $from_id);
    $timejalali = jdate('Y/m/d H:i:s');
    $textreport = "📌  یک کاربر یادداشت سرویس خود را تغییر داد.

<blockquote>▫️ نام کاربری سرویس : {$invoice['username']}</blockquote>
<blockquote>▫️ یاداشت قبلی :‌ {$invoice['note']}</blockquote>
<blockquote>▫️ یاداشت جدید :‌  $text</blockquote>

<blockquote>زمان تغییر یادداشت : $timejalali </blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textreport,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
