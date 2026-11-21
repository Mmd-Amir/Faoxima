<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $admin_ids, $backuser, $caption, $connect, $first_name, $from_id, $keyboard, $pdo, $photo, $photoid, $telegramRoute, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $adminrulecheck, $codeproduct, $marzban_list_get, $nameloc, $prodcut, $stmt, $textsendrasid.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class LegacyCardPayment
{
    public static function handle(
        $admin_ids,
        &$adminrulecheck,
        $backuser,
        $caption,
        &$codeproduct,
        $connect,
        $first_name,
        $from_id,
        $keyboard,
        &$marzban_list_get,
        &$nameloc,
        $pdo,
        $photo,
        $photoid,
        &$prodcut,
        &$stmt,
        $telegramRoute,
        $textbotlang,
        &$textsendrasid,
        $update,
        $user,
        $username
    ) {
        switch ($telegramRoute) {
        case 'legacy_card':
            $format_balance = number_format($user['Balance'], 0);
            if (!$photo or isset($update['message']['media_group_id'])) {
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_single_photo_only', '❌  فقط مجاز به ارسال یک تصویر هستید'), null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $PaymentReport = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $user['Processing_value']);
            if ($PaymentReport == false) {
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
                return;
            }
            if (is_callable([\Faoxima\Payments\Receipts\ReceiptOrders::class, 'receiptGate'])) {
                $_sbv_gate = \Faoxima\Payments\Receipts\ReceiptOrders::receiptGate($pdo, (string) $PaymentReport['id_order']);
                if (empty($_sbv_gate['ok'])) {
                    sendmessage($from_id, $_sbv_gate['message'], $keyboard, 'HTML');
                    return;
                }
            }
            $receiptReserveStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'pending', dec_not_confirmed = 'receipt-uploading', at_updated = :at_updated WHERE id_order = :id_order AND id_user = :id_user AND payment_Status = 'Unpaid'");
            $receiptReserveStmt->execute([
                ':at_updated' => date('Y/m/d H:i:s'),
                ':id_order' => $PaymentReport['id_order'],
                ':id_user' => $from_id,
            ]);
            if ($receiptReserveStmt->rowCount() !== 1) {
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
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
            $rxReceiptUserText = null;
            $split_data = explode('|', $PaymentReport['id_invoice']);
            if ($split_data[0] == "getconfigafterpay") {
                $get_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $split_data[1], "select");
                if ($get_invoice == false) {
                    $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'")
                        ->execute([':id_order' => $PaymentReport['id_order']]);
                    sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
                    return;
                }
                $textdiscount = "";
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_cart_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\nنام کاربری سرویس  : {service_username}\nنام محصول : {product_name}\nحجم محصول : {volume} گیگ\nزمان محصول : {service_time} روز\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
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
                $rxReceiptUserText = $textbotlang['users']['Balance']['Send-receiptadnsendconfig'];
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
                    sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
                    return;
                }
                $rxPendingRenewPrice = $service_other['price'] ?? null;
                $service_other = json_decode($service_other['value'], true);
                $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $usernamepanel, "select");
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
                $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
                $custompricevalue = $eextraprice[$user['agent']];
                $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
                $customtimevalueprice = $eextraprice[$user['agent']];
                $codeproduct = $service_other['code_product'];
                if ($codeproduct == "custom_volume") {
                    $prodcut['code_product'] = "custom_volume";
                    $prodcut['name_product'] = $nameloc['name_product'];
                    $prodcut['price_product'] = $rxPendingRenewPrice;
                    $prodcut['Service_time'] = $service_other['Service_time'];
                    $prodcut['Volume_constraint'] = $service_other['volumebuy'];
                } else {
                    $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $usernamepanel, "select");
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
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_cart_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
                    'service_username' => $usernamepanel,
                    'product_name' => $prodcut['name_product'],
                    'first_name' => $first_name,
                    'from_id' => $from_id,
                    'format_balance' => $format_balance,
                    'order_id' => $PaymentReport['id_order'],
                    'username' => $username,
                    'format_price_cart' => $format_price_cart,
                ]);
                $rxReceiptUserText = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cart_receipt_extend_sent', '🚀 رسید شما ارسال و پس از بررسی سرویس شما تمدید خواهد شد');
            } elseif ($split_data[0] == "getextravolumeuser") {
                $partsdic = explode("%", $split_data[1]);
                $usernamepanel = $partsdic[0];
                $volumes = $partsdic[1];
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_cart_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\nنام کاربری سرویس : {service_username}\nحجم خریداری شده  : {volumes}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
                    'service_username' => $usernamepanel,
                    'volumes' => $volumes,
                    'first_name' => $first_name,
                    'from_id' => $from_id,
                    'format_balance' => $format_balance,
                    'order_id' => $PaymentReport['id_order'],
                    'username' => $username,
                    'format_price_cart' => $format_price_cart,
                ]);
                $rxReceiptUserText = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cart_receipt_extra_volume_sent', '🚀 رسید شما ارسال و پس از بررسی  به سرویس شما حجم اضافه خواهد شد.');
            } elseif ($split_data[0] == "getextratimeuser") {
                $partsdic = explode("%", $split_data[1]);
                $usernamepanel = $partsdic[0];
                $time = $partsdic[1];
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_cart_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\nنام کاربری سرویس : {service_username}\nتعداد روز خریداری شده  : {days}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
                    'service_username' => $usernamepanel,
                    'days' => $time,
                    'first_name' => $first_name,
                    'from_id' => $from_id,
                    'format_balance' => $format_balance,
                    'order_id' => $PaymentReport['id_order'],
                    'username' => $username,
                    'format_price_cart' => $format_price_cart,
                ]);
                $rxReceiptUserText = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_cart_receipt_extra_time_sent', '🚀 رسید شما ارسال و پس از بررسی به سرویس شما زمان اضافه خواهد شد');
            } else {

                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_cart_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
                    'first_name' => $first_name,
                    'from_id' => $from_id,
                    'format_balance' => $format_balance,
                    'order_id' => $PaymentReport['id_order'],
                    'username' => $username,
                    'format_price_cart' => $format_price_cart,
                ]);
                $rxReceiptUserText = $textbotlang['users']['Balance']['Send-receipt'];
            }
            if (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_receipt_admin_note')) {
                $textsendrasid .= \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_receipt_admin_note((string) $PaymentReport['id_order']);
                $Confirm_pay = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_receipt_keyboard($Confirm_pay, (string) $PaymentReport['id_order']);
            }
            $_card_fid = (string)($PaymentReport['card_photo_file_id'] ?? '');
            $_receipt_delivered = false;
            $_receipt_route = \Faoxima\Services\Delivery\ServiceDelivery::rxReceiptDeliveryRoute();
            $_receipt_report_group = trim((string)($_receipt_route['chat_id'] ?? ''));
            if (!empty($_receipt_route['topic_enabled']) && $_receipt_report_group !== '') {
                $_receipt_thread = !empty($_receipt_route['thread_id']) ? (int)$_receipt_route['thread_id'] : null;
                if ($_card_fid !== '') {
                    telegram('sendMediaGroup', [
                        'chat_id' => $_receipt_report_group,
                        'message_thread_id' => $_receipt_thread,
                        'media'   => [
                            ['type' => 'photo', 'media' => $_card_fid, 'caption' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر')],
                            ['type' => 'photo', 'media' => $photoid, 'caption' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_receipt_payment_receipt_caption', '🧾 رسید پرداخت')],
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
                    $_receipt_delivered = true;
                    $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ?, report_thread_id = ? WHERE id_order = ?")
                        ->execute([$_receipt_report_group, $_receipt_report_msg_id, $_receipt_thread, $PaymentReport['id_order']]);
                }
            } elseif (empty($_receipt_route['topic_enabled'])) {
                $_receipt_private_targets = [];
                foreach ($admin_ids as $id_admin) {
                    $adminrulecheck = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $id_admin, "select");
                    if ($adminrulecheck['rule'] == "support")
                        continue;
                    if ($_card_fid !== '') {
                        telegram('sendMediaGroup', [
                            'chat_id' => $id_admin,
                            'media'   => [
                                ['type' => 'photo', 'media' => $_card_fid, 'caption' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر')],
                                ['type' => 'photo', 'media' => $photoid, 'caption' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_receipt_payment_receipt_caption', '🧾 رسید پرداخت')],
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
                    $_receipt_delivered = true;
                    $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ? WHERE id_order = ?")
                        ->execute([$_receipt_private_targets[0]['chat_id'], $_receipt_private_targets[0]['message_id'], $PaymentReport['id_order']]);
                    if (\Faoxima\Core\Database\Db::runtimeReady()) {
                        \Faoxima\Core\Database\Db::update("Payment_report", "private_receipt_targets", json_encode($_receipt_private_targets, JSON_UNESCAPED_UNICODE), "id_order", $PaymentReport['id_order']);
                    }
                }
            }
            if (!$_receipt_delivered) {
                $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'")
                    ->execute([':id_order' => $PaymentReport['id_order']]);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
                }
                if (function_exists('rx_log_event')) {
                    rx_log_event('RECEIPT_ADMIN_DELIVERY_FAILED', 'No administrator delivery reference for card receipt', [
                        'id_order' => $PaymentReport['id_order'],
                        'id_user' => $from_id,
                    ]);
                }
                \Faoxima\Telegram\Navigation\Steps::step('cart_to_cart_user', $from_id);
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_receipt_admin_delivery_failed', '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'), $backuser, 'HTML');
                return;
            }
            $dateacc = date('Y/m/d H:i:s');
            $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting', dec_not_confirmed = 'receipt-submitted', card_photo_file_id = :card_photo_file_id, at_updated = :at_updated WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'");
            $stmt->execute([
                ':card_photo_file_id' => $photoid,
                ':at_updated' => $dateacc,
                ':id_order' => $PaymentReport['id_order'],
            ]);
            if ($stmt->rowCount() !== 1) {
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
                return;
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            }
            if ($rxReceiptUserText !== null) {
                sendmessage($from_id, $rxReceiptUserText, $keyboard, 'HTML');
            }
            $_verifyReceiptCard = \Faoxima\Core\Database\Db::select('Payment_report', 'card_photo_file_id', 'id_order', $PaymentReport['id_order'], 'select', ['cache' => false]);
            if (empty($_verifyReceiptCard['card_photo_file_id']) && function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_PHOTO_SAVE_VERIFY_FAILED', 'card_photo_file_id still empty right after commit', [
                    'id_order' => $PaymentReport['id_order'],
                ]);
            }
            break;
        }

        return true;
    }
}
