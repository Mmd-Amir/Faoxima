<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $CartManage, $SellDiscount, $adminAction, $adminExecution, $affiliates, $backadmin, $callback_query_id, $dataget, $datagetr, $datatextbot, $from_id, $json_list_marzban_panel, $json_list_product_list_admin, $keyboardadmin, $message_id, $optionMarzban, $paymentreports, $pdo, $photo, $photoid, $setting, $shopkeyboard, $text, $text_inline, $textbotlang, $update, $user, $username, $usernamebot.
 * Inherited or exported by reference: $code_product, $panel, $product, $stmt, $textcart, $values.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DiscountsAndReferralConfiguration
{
    public static function handle(
        $CartManage,
        $SellDiscount,
        $adminAction,
        $adminExecution,
        $affiliates,
        $backadmin,
        $callback_query_id,
        &$code_product,
        $dataget,
        $datagetr,
        $datatextbot,
        $from_id,
        $json_list_marzban_panel,
        $json_list_product_list_admin,
        $keyboardadmin,
        $message_id,
        $optionMarzban,
        &$panel,
        $paymentreports,
        $pdo,
        $photo,
        $photoid,
        &$product,
        $setting,
        $shopkeyboard,
        &$stmt,
        $text,
        $text_inline,
        $textbotlang,
        &$textcart,
        $update,
        $user,
        $username,
        $usernamebot,
        &$values
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $discountbuytype = $dataget[1];
            Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "typediscount", $discountbuytype);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getuseuser', $from_id);
            break;
        case 2:
            $userdata = json_decode($user['Processing_value'], true);
            $numberlimit = $userdata['limitDiscount'];
            if (intval($text) > intval($userdata['limitDiscount'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 تعداد استفاده برای یک کاربر باید کوچیک تر از محدودیت کل باشد", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('getlocdiscount', $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "useuser", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 برای تنظیم  کد تخفیف مخصوص یک محصول ابتدا موقعیت محصول راانتخاب نمایید.
توجه : برای انتخاب تمام پنل ها کلمه<code>/all</code> را ارسال کنید", $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlocdiscount', $from_id);
            break;
        case 3:
            if ($text == "/all") {
                $panel = ['code_panel' => "/all", 'name_panel' => '/all'];
            } else {
                $panel = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $text, "select");
            }
            if ($panel == false || empty($panel))
                return;
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "code_panel", $panel['code_panel']);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "name_panel", $panel['name_panel']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌  میخواهید کد تخفیف برای کدام محصول باشد. توجه داشتید درصورتی که میخواهید کد تخفیف برای تمامی محصولات باشد کلمه all را ارسال کنید", $json_list_product_list_admin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getproductdiscount', $from_id);
            break;
        case 4:
            if ($text != "all") {
                $product = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $text, "select");
            } else {
                $product['code_product'] = "all";
            }
            if ($product == false) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محصول انتخابی وجود ندارد", $keyboardadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $stmt = $pdo->prepare("INSERT INTO DiscountSell (codeDiscount, usedDiscount, price, limitDiscount, agent, usefirst, useuser, code_panel, code_product, time,type) VALUES (:codeDiscount, :usedDiscount, :price, :limitDiscount, :agent, :usefirst, :useuser, :code_panel, :code_product, :time,:type)");
            $values = "0";
            $values1 = "1";
            $code_product = "0";
            $stmt->bindParam(':codeDiscount', $userdata['code'], PDO::PARAM_STR);
            $stmt->bindParam(':usedDiscount', $values, PDO::PARAM_STR);
            $stmt->bindParam(':price', $userdata['price'], PDO::PARAM_STR);
            $stmt->bindParam(':limitDiscount', $userdata['limitDiscount'], PDO::PARAM_STR);
            $stmt->bindParam(':agent', $userdata['agent'], PDO::PARAM_STR);
            $stmt->bindParam(':usefirst', $userdata['usefirst'], PDO::PARAM_STR);
            $stmt->bindParam(':useuser', $userdata['useuser'], PDO::PARAM_STR);
            $stmt->bindParam(':code_panel', $userdata['code_panel'], PDO::PARAM_STR);
            $stmt->bindParam(':code_product', $product['code_product'], PDO::PARAM_STR);
            $stmt->bindParam(':time', $userdata['time'], PDO::PARAM_STR);
            $stmt->bindParam(':type', $userdata['typediscount'], PDO::PARAM_STR);
            $stmt->execute();
            $textdiscount = "
🎁 کد تخفیف شما با موفقیت ساخته شد.

📩 نام کد تخفیف: <code>{$userdata['code']}</code>
🧮 درصد کد تخفیف: {$userdata['price']}
🎛 پنل :  {$userdata['name_panel']}
📌  محصول : $text
♻️ نوع کاربری :‌ {$userdata['agent']}
🔴 محدودیت استفاده :‌ {$userdata['limitDiscount']}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textdiscount, $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 6:
            if (!in_array($text, $SellDiscount)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM Giftcodeconsumed WHERE code = :code");
            $stmt->bindParam(':code', $text, PDO::PARAM_STR);
            $stmt->execute();
            $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE codeDiscount = :codeDiscount");
            $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 7:
            $userdata = json_decode($user['Processing_value'], true);
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdata['name_panel'], "select");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionMarzban, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            return;
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setpercentage', $from_id);
            break;
        case 9:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || (int) $text < 0 || (int) $text > 100) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_percentage'] ?? "❌ درصد نامعتبر است. لطفاً عددی بین 0 تا 100 وارد کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpercentage'], $affiliates, 'HTML');
            \Faoxima\Core\Database\Db::update("setting", "affiliatespercentage", $text);
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_image_prompt'] ?? "🏞 عکس جدید بنر زیرمجموعه‌گیری را ارسال کنید.\n\nپس از دریافت عکس، متن بنر را نیز از شما می‌پرسیم.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setbannerimage', $from_id);
            break;
        case 11:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!$photo) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['invalidbanner'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("affiliates", "id_media", $photoid);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_text_prompt'] ?? "📝 اکنون متن بنر زیرمجموعه‌گیری را ارسال کنید.\n\nمتغیرهای قابل استفاده:\n<code>{link}</code> لینک اختصاصی دعوت\n<code>{name}</code> نام کاربر\n<code>{code}</code> کد دعوت کاربر", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setbannertext', $from_id);
            break;
        case 12:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_text_prompt'] ?? "📝 متن جدید بنر زیرمجموعه‌گیری را ارسال کنید.\n\nمتغیرهای قابل استفاده:\n<code>{link}</code> لینک اختصاصی دعوت\n<code>{name}</code> نام کاربر\n<code>{code}</code> کد دعوت کاربر", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setbannertext', $from_id);
            break;
        case 13:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Core\Database\Db::update("affiliates", "banner_text", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_text_saved'] ?? "✅ متن بنر با موفقیت ذخیره شد.", $affiliates, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            break;
        case 14:
            \Faoxima\Core\Database\Db::update("affiliates", "id_media", "none");
            \Faoxima\Core\Database\Db::update("affiliates", "description", "none");
            \Faoxima\Core\Database\Db::update("affiliates", "banner_text", null);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_deleted'] ?? "✅ بنر به‌طور کامل حذف شد (عکس و متن).", $affiliates, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            break;
        case 15:
            \Faoxima\Core\Database\Db::update("affiliates", "id_media", "none");
            \Faoxima\Core\Database\Db::update("affiliates", "description", "none");
            \Faoxima\Core\Database\Db::update("affiliates", "banner_text", null);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_banner_reset'] ?? "✅ بنر به حالت پیش‌فرض بازگردانی شد.", $affiliates, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            break;
        case 16:
            $rxBannerPreview = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $rxBannerPreviewText = \Faoxima\Telegram\Support\Texts::faoxima_render_text(
                !empty($rxBannerPreview['banner_text']) ? $rxBannerPreview['banner_text'] : ($rxBannerPreview['description'] ?? ''),
                [
                    'link' => "https://t.me/$usernamebot?start=$from_id",
                    'name' => $user['first_name'] ?? $username,
                    'code' => $user['codeInvitation'] ?? $from_id,
                ]
            );
            if (!empty($rxBannerPreview['id_media']) && strlen($rxBannerPreview['id_media']) >= 5 && $rxBannerPreview['id_media'] !== 'none') {
                telegram('sendphoto', [
                    'chat_id' => $from_id,
                    'photo' => $rxBannerPreview['id_media'],
                    'caption' => $rxBannerPreviewText !== '' ? $rxBannerPreviewText : ($datatextbot['dyn_affiliates_extra_banner_empty'] ?? "بنری تنظیم نشده است."),
                    'parse_mode' => 'HTML',
                ]);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxBannerPreviewText !== '' ? $rxBannerPreviewText : ($datatextbot['dyn_affiliates_extra_banner_empty'] ?? "بنری تنظیم نشده است."), $affiliates, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            break;
        case 17:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "CartDirect");
            $textcart = "📌 نام کاربری خود را بدون @ برای دریافت شماره کارت ارسال کنید\n\n{$PaySetting['ValuePay']}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('CartDirect', $from_id);
            break;
        case 18:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $CartManage, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "CartDirect");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 19:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Cartstatuspv")['ValuePay'];
            $card_Statuspv = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $PaySetting, 'callback_data' => $PaySetting],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['cardTitlepv'], $card_Statuspv, 'HTML');
            break;
        case 20:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "offcardpv", "NamePay", "Cartstatuspv");
            $card_Statuspv = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "offcardpv", 'callback_data' => "offcardpv"],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffpv'], $card_Statuspv);
            break;
        case 21:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "oncardpv", "NamePay", "Cartstatuspv");
            $card_Statuspv = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "oncardpv", 'callback_data' => "oncardpv"],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonpv'], $card_Statuspv);
            break;
        case 22:
            $id_order = $datagetr[1];
            $Payment_report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $id_order, "select");
            $_addbal_chat_id = !empty($Payment_report['report_chat_id']) ? $Payment_report['report_chat_id'] : ($update['callback_query']['message']['chat']['id'] ?? $from_id);
            $_addbal_msg_id = !empty($Payment_report['report_message_id']) ? (int) $Payment_report['report_message_id'] : (int) $message_id;
            $_addbal_thread_id = !empty($Payment_report['report_thread_id']) ? (int) $Payment_report['report_thread_id'] : (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_order, "id", $from_id);
            if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
                $ff = telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $_addbal_chat_id . ':' . $_addbal_msg_id . ':' . $_addbal_thread_id, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
            \Faoxima\Telegram\Navigation\Steps::step('addbalancemanual', $from_id);
            break;
        case 23:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || intval($text) <= 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
                return;
            }
            $Payment_report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $user['Processing_value'], "select", ['cache' => false]);
            if (!is_array($Payment_report) || empty($Payment_report['id_user'])) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فاکتور پرداخت یافت نشد.", $keyboardadmin, 'HTML');
                return;
            }
            $Balance_user = \Faoxima\Core\Database\Db::select("user", "*", "id", $Payment_report['id_user'], "select", ['cache' => false]);
            $balanceusers = number_format($text, 0);
            $rxManualOk = false;
            $rxManualReviewed = false;
            try {
                $pdo->beginTransaction();
                $rxManualStep = $pdo->prepare("UPDATE user SET step = 'home' WHERE id = :admin AND step = 'addbalancemanual'");
                $rxManualStep->execute([':admin' => $from_id]);
                if ($rxManualStep->rowCount() < 1) {
                    $pdo->rollBack();
                    return;
                }
                $rxClaimAddBal = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid', at_updated = :at WHERE id_order = :o AND payment_Status NOT IN ('paid', 'reject', 'processing')");
                $rxClaimAddBal->execute([':o' => $Payment_report['id_order'], ':at' => date('Y/m/d H:i:s')]);
                if ($rxClaimAddBal->rowCount() < 1) {
                    $rxManualReviewed = true;
                    throw new RuntimeException('payment already reviewed');
                }
                if (!\Faoxima\Wallet\Balance\Balances::credit($pdo, $Payment_report['id_user'], (int) $text)) {
                    throw new RuntimeException('balance_atomic_credit returned false');
                }
                if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $Payment_report['id_user'], 'credit', $text, 'topup_card', 'تایید رسید کارت به کارت توسط ادمین', $Payment_report['id_order']);
                }
                $pdo->commit();
                $rxManualOk = true;
            } catch (Throwable $rxManualErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (!$rxManualReviewed) {
                    error_log('[card_manual_add_balance] ' . $rxManualErr->getMessage());
                    if (function_exists('rx_log_event')) {
                        rx_log_event('CARD_MANUAL_CREDIT_FAILED', $rxManualErr->getMessage(), [
                            'admin_id' => $from_id,
                            'id_order' => $Payment_report['id_order'],
                            'id_user' => $Payment_report['id_user'],
                            'amount' => $text,
                        ]);
                    }
                }
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            if ($rxManualReviewed) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Payment']['reviewedpayment'], $keyboardadmin, 'HTML');
                return;
            }
            if (!$rxManualOk) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ افزایش موجودی انجام نشد. دوباره تلاش کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUser'], $keyboardadmin, 'HTML');
            $textadd = "💎 کاربر عزیز مبلغ $balanceusers تومان به موجودی کیف پول تان اضافه گردید.";
            sendmessage($Payment_report['id_user'], $textadd, null, 'HTML');
            if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
                \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('wallet_deposit', [
                    'user_id' => $Payment_report['id_user'],
                    'amount'  => $balanceusers,
                    'price'   => $balanceusers,
                ], $setting);
            }
            $text_report = "✅ رسید کارت به کارت با افزایش دستی موجودی تایید شد.

<blockquote>آیدی عددی کاربر : {$Payment_report['id_user']}</blockquote>
<blockquote>نام کاربری کاربر : {$Balance_user['username']}</blockquote>
<blockquote>مبلغ تراکنش در فاکتور :  " . \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']) . "</blockquote>
<blockquote>مبلغ تراکنش واریزی توسط ادمین : $text</blockquote>
<blockquote>👤آیدی عددی ادمین تایید کننده : $from_id</blockquote>
<blockquote>نام کاربری ادمین تایید کننده : @$username</blockquote>";
            $_addbal_origin = explode(':', (string) ($user['Processing_value_four'] ?? ''), 3);
            $_addbal_chat_id = $_addbal_origin[0] ?? '';
            $_addbal_message_id = $_addbal_origin[1] ?? '';
            $_addbal_thread_id = (int) ($_addbal_origin[2] ?? 0);
            if ($_addbal_chat_id === '' || $_addbal_message_id === '') {
                $_addbal_chat_id = (string) ($Payment_report['report_chat_id'] ?? '');
                $_addbal_message_id = (string) ($Payment_report['report_message_id'] ?? '');
                $_addbal_thread_id = (int) ($Payment_report['report_thread_id'] ?? 0);
            }
            if ($_addbal_chat_id !== '' && $_addbal_message_id !== '') {
                Editmessagetext($_addbal_chat_id, $_addbal_message_id, $text_report, null, 'HTML', $_addbal_thread_id > 0 ? $_addbal_thread_id : null);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $from_id);
            } elseif (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $paymentreports,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
