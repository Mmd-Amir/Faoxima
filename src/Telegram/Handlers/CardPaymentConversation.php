<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $_rx_payrcpt_styles, $admin_ids, $backuser, $callback_query_id, $connect, $dataget, $datain, $datatextbot, $from_id, $keyboard, $message_id, $pdo, $photo, $photoid, $sendresidcart, $setting, $telegramRoute, $text, $textcart, $update, $user.
 * Inherited or exported by reference: $data, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CardPaymentConversation
{
    public static function handle(
        $_rx_payrcpt_styles,
        $admin_ids,
        $backuser,
        $callback_query_id,
        $connect,
        &$data,
        $dataget,
        $datain,
        $datatextbot,
        $from_id,
        $keyboard,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $sendresidcart,
        $setting,
        &$stmt,
        $telegramRoute,
        $text,
        $textcart,
        $update,
        $user
    ) {
        switch ($telegramRoute) {
        case 'card_start':
            $timefivemin = time() - 120;
            $timefivemin = date('Y/m/d H:i:s', intval($timefivemin));
            $sql = "SELECT * FROM Payment_report WHERE id_user = :u AND Payment_Method = 'cart to cart' AND at_updated > :t AND COALESCE(payment_Status, '') NOT IN ('cancelled', 'expire')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':u' => (string) $from_id, ':t' => $timefivemin]);
            $paymentcount = $stmt->rowCount();
            if ($paymentcount != 0 and !in_array($from_id, $admin_ids)) {
                sendmessage($from_id, "❗ شما در ۲ دقیقه اخیر رسید ارسال کرده اید لطفا ۲ دقیقه دیگر رسید جدید را ارسال نمایید.", null, 'HTML');
                return;
            }
            $payemntcheck = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $dataget[1], "select", ['cache' => false]);
            if (!is_array($payemntcheck) || (string) ($payemntcheck['id_user'] ?? '') !== (string) $from_id) {
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), null, 'HTML');
                return;
            }
            if ($payemntcheck['payment_Status'] == "paid") {
                sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
                return;
            }
            if ($payemntcheck['payment_Status'] == "expire" || $payemntcheck['payment_Status'] == "cancelled") {
                sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
                return;
            }
            if (is_callable([\Faoxima\Payments\Receipts\ReceiptOrders::class, 'receiptGate'])) {
                $_sbv_gate = \Faoxima\Payments\Receipts\ReceiptOrders::receiptGate($pdo, (string) $payemntcheck['id_order']);
                if (empty($_sbv_gate['ok'])) {
                    $_sbv_alert = telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => $_sbv_gate['message'],
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                    if (!is_array($_sbv_alert) || empty($_sbv_alert['ok'])) {
                        sendmessage($from_id, $_sbv_gate['message'], null, 'HTML');
                    }
                    return;
                }
            }
            deletemessage($from_id, $message_id);
            sendmessage($from_id, "🖼 تصویر رسید خود را ارسال نمایید", $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('cart_to_cart_user', $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $dataget[1], "id", $from_id);
            break;
        case 'card_select':
            if (!isset($update['callback_query'])) { return; }
            $orderId = (string)($user['Processing_value'] ?? '');
            if ($orderId === '' || $orderId === '0') {
                sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($datain === 'cv_new') {
                \Faoxima\Telegram\Navigation\Steps::step('card_photo_step', $from_id);
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $datatextbot['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است

📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.

🔘 نکته مهم: برای تایید تراکنش، الزامی است که ۴ رقم آخر شماره کارت و نام شما در تصویر کاملاً واضح و خوانا باشد. (سایر اطلاعات را می‌توانید بپوشانید).", (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_cancel_keyboard') && \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_keyboard((string) $orderId) !== null) ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_keyboard((string) $orderId) : $backuser, 'HTML');
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
                \Faoxima\Core\Database\Db::update("Payment_report", "card_last4", $_cv_last4, "id_order", $orderId);
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);

                $_cv_note = "<blockquote>ℹ️ کاربر <code>{$from_id}</code> از کارت تاییدشده **** **** **** {$_cv_last4} استفاده کرده است.</blockquote>\n<blockquote>🛒 کد پیگیری: {$orderId}</blockquote>\nلطفاً ۴ رقم آخر رسید را بررسی کنید.";
                $_cv_receipt_route = \Faoxima\Services\Delivery\ServiceDelivery::rxReceiptDeliveryRoute('paymentreport');
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

                $payRecord = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $orderId);
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
                $_sbv_bot = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_bot_payment_view') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_bot_payment_view((string) $orderId) : null;
                if ($_sbv_bot !== null) {
                    $valueprice = $_sbv_bot['valueprice'];
                    $price_copy = $_sbv_bot['price_copy'];
                }
                if ($_cmode == 'direct' && $_card2) {
                    $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
                } else {
                    $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
                    $textcart = strtr($datatextbot['text_cart'] ?? "💳 مبلغ {price} تومان ({price}0 ریال) به کارت {card_number} به نام {name_card} واریز کنید.", $replacements);
                }
                if ($_sbv_bot !== null) {
                    $textcart .= $_sbv_bot['block'];
                }
                $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
                if ($setting['statuscopycart'] == "1" || $_sbv_bot !== null) {
                    $_kb_rows = [[
                        ...($setting['statuscopycart'] == "1" ? [['text' => "کپی کارت اول", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num']] : []),
                        ['text' => $_sbv_bot !== null ? $_sbv_bot['copy_label'] : "کپی مبلغ", 'copy_text' => ["text" => (string) $price_copy], '_rxck' => 'card_amount'],
                    ]];
                    if ($setting['statuscopycart'] == "1" && $_cmode == 'direct' && !empty($_card2['cardnumber'])) {
                        $_kb_rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
                    }
                    $_kb_rows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)];
                    $_sbv_cancel_row = ($_sbv_bot !== null && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_cancel_row')) ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_row((string) $orderId) : null;
                    if ($_sbv_cancel_row !== null) {
                        $_kb_rows[] = $_sbv_cancel_row;
                    }
                    $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
                } else {
                    $sendresidcart = json_encode([
                        'inline_keyboard' => [
                            [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)],
                        ]
                    ]);
                }
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'] ?? null;
                if ($gethelp && $gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    if (is_array($data)) {
                        if ($data['type'] == "text") { sendmessage($from_id, $data['text'], null, 'HTML'); }
                        elseif ($data['type'] == "photo") { sendphoto($from_id, $data['photoid'], $data['text']); }
                        elseif ($data['type'] == "video") { sendvideo($from_id, $data['videoid'], $data['text']); }
                    }
                }
                $cartMsgResult = \Faoxima\Telegram\Media\SectionMedia::rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, 'html');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($cartMsgResult, $orderId);
                return;
            }
            break;
        case 'card_photo':
            if (!isset($update['message']) && empty($text)) { return; }
            if (!$photo || isset($update['message']['media_group_id'])) {
                sendmessage($from_id, "❌ لطفاً یک تصویر تکی از کارت خود ارسال کنید.", null, 'HTML');
                return;
            }
            $orderId = (string)($user['Processing_value'] ?? '');
            if ($orderId === '' || $orderId === '0') {
                sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $payRecord = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $orderId);
            if ($payRecord === false || (string)($payRecord['id_user'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, "❌ رکورد پرداخت یافت نشد. مراحل را از ابتدا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("Payment_report", "card_photo_file_id", $photoid, "id_order", $orderId);
            \Faoxima\Telegram\Navigation\Steps::step('card_last4_step', $from_id);
            sendmessage($from_id, "✅ عکس کارت دریافت شد.\n\n🔢 لطفاً ۴ رقم آخر شماره کارت خود را وارد کنید:", null, 'HTML');
            break;
        case 'card_last_four':
            if (!isset($update['message']) && empty($text)) { return; }
            $_cl4 = trim((string)($text ?? ''));
            if (!preg_match('/^\d{4}$/', $_cl4)) {
                sendmessage($from_id, "❌ لطفاً دقیقاً ۴ رقم آخر کارت را وارد کنید (مثال: 1234).", null, 'HTML');
                return;
            }
            $orderId = (string)($user['Processing_value'] ?? '');
            if ($orderId === '' || $orderId === '0') {
                sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $payRecord = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $orderId);
            if ($payRecord === false || (string)($payRecord['id_user'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, "❌ رکورد پرداخت یافت نشد. مراحل را از ابتدا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("Payment_report", "card_last4", $_cl4, "id_order", $orderId);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
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
            $_sbv_bot = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_bot_payment_view') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_bot_payment_view((string) $orderId) : null;
            if ($_sbv_bot !== null) {
                $valueprice = $_sbv_bot['valueprice'];
                $price_copy = $_sbv_bot['price_copy'];
            }
            if ($_cmode == 'direct' && $_card2) {
                $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
            } else {
                $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
                $textcart = strtr($datatextbot['text_cart'] ?? "💳 مبلغ {price} تومان ({price}0 ریال) به کارت {card_number} به نام {name_card} واریز کنید.", $replacements);
            }
            if ($_sbv_bot !== null) {
                $textcart .= $_sbv_bot['block'];
            }
            $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
            if ($setting['statuscopycart'] == "1" || $_sbv_bot !== null) {
                $_kb_rows = [[
                    ...($setting['statuscopycart'] == "1" ? [['text' => "کپی شماره کارت", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num']] : []),
                    ['text' => $_sbv_bot !== null ? $_sbv_bot['copy_label'] : "کپی مبلغ", 'copy_text' => ["text" => (string) $price_copy], '_rxck' => 'card_amount'],
                ]];
                if ($setting['statuscopycart'] == "1" && $_cmode == 'direct' && !empty($_card2['cardnumber'])) {
                    $_kb_rows[] = [['text' => "کپی شماره کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
                }
                $_kb_rows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)];
                $_sbv_cancel_row = ($_sbv_bot !== null && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_cancel_row')) ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_row((string) $orderId) : null;
                if ($_sbv_cancel_row !== null) {
                    $_kb_rows[] = $_sbv_cancel_row;
                }
                $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
            } else {
                $sendresidcart = json_encode([
                    'inline_keyboard' => [
                        [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)],
                    ]
                ]);
            }
            $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'] ?? null;
            if ($gethelp && $gethelp != 2) {
                $data = json_decode($gethelp, true);
                if (is_array($data)) {
                    if ($data['type'] == "text") { sendmessage($from_id, $data['text'], null, 'HTML'); }
                    elseif ($data['type'] == "photo") { sendphoto($from_id, $data['photoid'], $data['text']); }
                    elseif ($data['type'] == "video") { sendvideo($from_id, $data['videoid'], $data['text']); }
                }
            }
            $cartMsgResult = \Faoxima\Telegram\Media\SectionMedia::rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, 'html');
            \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($cartMsgResult, $orderId);
            break;
        }

        return true;
    }
}
