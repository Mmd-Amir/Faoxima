<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $SellDiscount, $adminExecution, $adminrulecheck, $affiliates, $backadmin, $callback_query_id, $datain, $datatextbot, $from_id, $json_list_marzban_panel, $json_list_product_list_admin, $keyboardadmin, $message_id, $optionMarzban, $paymentreports, $pdo, $photo, $photoid, $setting, $shopkeyboard, $text, $text_inline, $textbotlang, $update, $user, $username, $usernamebot.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $code_product, $dataget, $datagetr, $panel, $product, $stmt, $textcart, $values.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DiscountsAndReferralConfiguration
{
    public static function handle(
        $CartManage,
        $SellDiscount,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $affiliates,
        $backadmin,
        $callback_query_id,
        &$code_product,
        &$dataget,
        &$datagetr,
        $datain,
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

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/discounttype_(\w+)/', $datain, $dataget)) {
            $adminAction = 1;
        } elseif ($user['step'] == "getuseuser") {
            $adminAction = 2;
        } elseif ($user['step'] == "getlocdiscount") {
            $adminAction = 3;
        } elseif ($user['step'] == "getproductdiscount") {
            $adminAction = 4;
        } elseif ($text == "❌ حذف کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "remove-Discountsell") {
            $adminAction = 6;
        } elseif ($text == "/end") {
            $adminAction = 7;
        } elseif ($text == "🧮 تنظیم درصد زیرمجموعه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "setpercentage") {
            $adminAction = 9;
        } elseif (($text == "🏞 تنظیم بنر زیرمجموعه گیری" || $text == "🏞 تنظیم عکس بنر" || $datain == "affiliates_edit_banner_image") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "setbannerimage") {
            $adminAction = 11;
        } elseif (($text == "✏️ ویرایش متن بنر" || $datain == "affiliates_edit_banner_text") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($user['step'] == "setbannertext") {
            $adminAction = 13;
        } elseif (($text == "🗑 حذف بنر" || $datain == "affiliates_delete_banner") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif (($text == "♻️ بازگردانی بنر پیش‌فرض" || $datain == "affiliates_reset_banner") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        } elseif (($text == "🖼 مشاهده بنر فعال" || $datain == "affiliates_view_banner") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 16;
        } elseif ($text == "👤 آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 17;
        } elseif ($user['step'] == "CartDirect") {
            $adminAction = 18;
        } elseif ($text == "💳 آفلاین در پیوی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 19;
        } elseif ($datain == "oncardpv" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 20;
        } elseif ($datain == "offcardpv" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 21;
        } elseif (preg_match('/addbalamceuser_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 22;
        } elseif ($user['step'] == "addbalancemanual") {
            $adminAction = 23;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\DiscountsAndReferralConfiguration::handle(
            CartManage: $CartManage ?? null,
            SellDiscount: $SellDiscount ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            affiliates: $affiliates ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            code_product: $code_product,
            dataget: $dataget ?? null,
            datagetr: $datagetr ?? null,
            datatextbot: $datatextbot ?? null,
            from_id: $from_id ?? null,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            json_list_product_list_admin: $json_list_product_list_admin ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            optionMarzban: $optionMarzban ?? null,
            panel: $panel,
            paymentreports: $paymentreports ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            product: $product,
            setting: $setting ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            text: $text ?? null,
            text_inline: $text_inline ?? null,
            textbotlang: $textbotlang ?? null,
            textcart: $textcart,
            update: $update ?? null,
            user: $user ?? null,
            username: $username ?? null,
            usernamebot: $usernamebot ?? null,
            values: $values
        );
    }
}
