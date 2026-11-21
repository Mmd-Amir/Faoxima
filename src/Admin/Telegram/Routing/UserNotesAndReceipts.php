<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminChannelMenu, $adminExecution, $adminPanelsMenu, $adminUsersMenu, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $message_id, $pdo, $rxConfirmAlert, $setting_panel, $shopkeyboard, $supportcenter, $text, $textbotlang, $update.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $datagetr, $keyboardadmin, $randomString, $stmt, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class UserNotesAndReceipts
{
    public static function handle(
        &$adminAction,
        $adminChannelMenu,
        $adminExecution,
        $adminPanelsMenu,
        &$adminRouteMatched,
        $adminUsersMenu,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        &$dataget,
        &$datagetr,
        $datain,
        $from_id,
        &$keyboardadmin,
        $message_id,
        $pdo,
        &$randomString,
        $rxConfirmAlert,
        $setting_panel,
        $shopkeyboard,
        &$stmt,
        $supportcenter,
        $text,
        $textbotlang,
        $update,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "getnote") {
            $adminAction = 1;
        } elseif ($user['step'] == "endstep") {
            $adminAction = 2;
        } elseif (($text == "👨‍🔧 بخش ادمین" || $datain == "admin_mgr") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($text == "📁 مدیریت پنل‌ها و سرورها" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif (($text == "📢 کانال و اطلاع‌رسانی" || (isset($datain) && $datain === 'admin_channelhub')) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($text == "👥 مدیریت کاربران" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($text == "⚙️ تنظیمات فنی و ربات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($text == "🤙 بخش پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 9;
        } elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 10;
        } elseif (preg_match('/reject_pay_cancel_(\w+)/', $datain, $datagetc) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 11;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\UserNotesAndReceipts::handle(
            adminAction: $adminAction ?? null,
            adminChannelMenu: $adminChannelMenu ?? null,
            adminExecution: $adminExecution ?? null,
            adminPanelsMenu: $adminPanelsMenu ?? null,
            adminUsersMenu: $adminUsersMenu ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget ?? null,
            datagetc: $datagetc ?? null,
            datagetr: $datagetr ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            randomString: $randomString,
            rxConfirmAlert: $rxConfirmAlert ?? null,
            setting_panel: $setting_panel ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            supportcenter: $supportcenter ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user
        );
    }
}
