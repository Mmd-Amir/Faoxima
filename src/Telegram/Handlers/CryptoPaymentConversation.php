<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $admin_ids, $backuser, $caption, $connect, $dataget, $datain, $datatextbot, $first_name, $from_id, $keyboard, $message_id, $pdo, $photo, $photoid, $receiptreport, $setting, $telegramRoute, $text, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $adminrulecheck, $codeproduct, $marzban_list_get, $nameloc, $prodcut, $r, $stmt, $textsendrasid.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CryptoPaymentConversation
{
    public static function handle(
        $admin_ids,
        &$adminrulecheck,
        $backuser,
        $caption,
        &$codeproduct,
        $connect,
        $dataget,
        $datain,
        $datatextbot,
        $first_name,
        $from_id,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        $pdo,
        $photo,
        $photoid,
        &$prodcut,
        &$r,
        $receiptreport,
        $setting,
        &$stmt,
        $telegramRoute,
        $text,
        $textbotlang,
        &$textsendrasid,
        $update,
        $user,
        $username
    ) {
        switch ($telegramRoute) {
        case 'crypto_start':
            $payemntcheck = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $dataget[1], "select");
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
            \Faoxima\Telegram\Navigation\Steps::step('getresidcurrency', $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $dataget[1], "id", $from_id);
            break;
        case 'crypto_hash':


            if (isset($datain) && $datain === "cancel_hash_input") {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
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
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);


                if ($trimmed !== '') {
                    sendmessage($from_id, "🏠 از حالت ارسال هش خارج شدید. اکنون می‌توانید از منو استفاده کنید.", $keyboard, 'HTML');
                }
                return;
            }
            $orderId = (string) ($user['Processing_value_four'] ?? '');
            if ($orderId === '' || $orderId === '0') {
                sendmessage($from_id, "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_attach_hash')) {
                sendmessage($from_id, "❌ ماژول هش‌چکر بارگذاری نشده است.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_extract_hash') || \Faoxima\Payments\Crypto\ManualCrypto::crypto_extract_hash($text) === null) {
                sendmessage($from_id, "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را ارسال کنید — نه آدرس کیف‌پول.", null, 'HTML');
                return;
            }
            $r = \Faoxima\Payments\Crypto\ManualCrypto::crypto_attach_hash($orderId, (string) $text);
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
            $hashOrderRow = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $orderId, "select");
            $hashOrderCur = (string) ($hashOrderRow['crypto_currency'] ?? '');
            $manualCurListHash = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
            $isManualHashCur = isset($manualCurListHash[$hashOrderCur]);

            if ($isManualHashCur) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $orderId, "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('digitaltron_receipt_photo', $from_id);
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
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
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
            break;
        case 'crypto_photo':

            if (isset($datain) && $datain === "cancel_hash_input") {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
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
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $receiptRow = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $orderId, "select");
            if (!$receiptRow || (string) ($receiptRow['id_user'] ?? '') !== (string) $from_id) {
                sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_found'] ?? "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("Payment_report", "card_photo_file_id", (string) $photoid, "id_order", $orderId);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);

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
            $receiptAdminIds = \Faoxima\Core\Database\Db::runtimeReady() ? (\Faoxima\Core\Database\Db::select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
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
            break;
        case 'crypto_receipt':
            if (!isset($update['message']) && empty($text)) { return; }
            $format_balance = number_format($user['Balance'], 0);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $PaymentReport = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
            $Paymentusercount = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_user", $PaymentReport['id_user'], "count");
            if ($PaymentReport == false) {
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
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
                $get_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $user['Processing_value_one'], "select");
                if ($get_invoice == false) {
                    sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
                    return;
                }
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\n\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\nحجم محصول : {volume} گیگ\nزمان محصول : {service_time} روز\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
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
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
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
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\nنام کاربری سرویس : {service_username}\nحجم خریداری شده  : {volumes}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
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
                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\nنام کاربری سرویس : {service_username}\nتعداد روز خریداری شده  : {days}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
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

                $textsendrasid = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_admin_notify_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
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
                    $adminrulecheck = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $id_admin, "select");
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
                    if (\Faoxima\Core\Database\Db::runtimeReady()) {
                        \Faoxima\Core\Database\Db::update("Payment_report", "private_receipt_targets", json_encode($_arze_private_targets, JSON_UNESCAPED_UNICODE), "id_order", $PaymentReport['id_order']);
                    }
                }
            }
            $dateacc = date('Y/m/d H:i:s');
            $rxReceiptStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting', dec_not_confirmed = ?, at_updated = ? WHERE id_order = ? AND COALESCE(payment_Status, '') NOT IN ('paid', 'processing', 'reconciling', 'manual_review') AND COALESCE(direct_payment_done, 0) = 0");
            $rxReceiptStmt->execute(["$text $caption", $dateacc, $PaymentReport['id_order']]);
            if ($rxReceiptStmt->rowCount() !== 1) {
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
                return;
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            }
            if ($user['Processing_value_tow'] == "getconfigafterpay") {
                sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
            } else {
                sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
            }
            break;
        }

        return true;
    }
}
