<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $MethodUsername, $MethodUsernameRemna, $adminExecution, $adminrulecheck, $backadmin, $datain, $from_id, $getNameCustomKb, $keyboardadmin, $keyboardprotocollist, $optionJovad, $optionMarzban, $pdo, $protocoldata, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $status, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class GiftCodesAndProviderSettings
{
    public static function handle(
        $CartManage,
        $MethodUsername,
        $MethodUsernameRemna,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $datain,
        $from_id,
        $getNameCustomKb,
        $keyboardadmin,
        $keyboardprotocollist,
        $optionJovad,
        $optionMarzban,
        $pdo,
        $protocoldata,
        $shopkeyboard,
        &$status,
        &$stmt,
        &$text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($text == "🎁 ساخت کد هدیه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "get_code") {
            $adminAction = 2;
        } elseif ($user['step'] == "get_price_code") {
            $adminAction = 3;
        } elseif ($user['step'] == "getlimitcodedis") {
            $adminAction = 4;
        } elseif ($text == "❌ حذف کد هدیه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "remove-Discount") {
            $adminAction = 6;
        } elseif ($text == "🗑 حذف پروتکل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "removeprotocol") {
            $adminAction = 8;
        } elseif ($text == "💡 ساخت نام کاربری" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "updatemethodusername") {
            $adminAction = 10;
        } elseif ($user['step'] == "getnamecustom") {
            $adminAction = 11;
        } elseif ($text == "📡 کانال‌های پیش‌فرض" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($user['step'] == "jovad_channels" && !isset($update['callback_query'])) {
            $adminAction = 13;
        } elseif ($text == "👥 تعداد دستگاه اشتراک نامحدود" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($user['step'] == "jovad_devices" && !isset($update['callback_query'])) {
            $adminAction = 15;
        } elseif (($datain == "cartsetting" || $text == "▶️ بازگشت به منوی تظنیمات کارت") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 16;
        } elseif ($datain == "cart_manage_inline" || $text == "💳 مدیریت شماره کارت" || preg_match('/^card_list_page_(\d+)$/', $datain, $_clpg_m)) {
            $adminAction = 17;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\GiftCodesAndProviderSettings::handle(
            CartManage: $CartManage ?? null,
            MethodUsername: $MethodUsername ?? null,
            MethodUsernameRemna: $MethodUsernameRemna ?? null,
            _clpg_m: $_clpg_m ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            adminrulecheck: $adminrulecheck ?? null,
            backadmin: $backadmin ?? null,
            from_id: $from_id ?? null,
            getNameCustomKb: $getNameCustomKb ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardprotocollist: $keyboardprotocollist ?? null,
            optionJovad: $optionJovad ?? null,
            optionMarzban: $optionMarzban ?? null,
            pdo: $pdo ?? null,
            protocoldata: $protocoldata ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            status: $status,
            stmt: $stmt,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
