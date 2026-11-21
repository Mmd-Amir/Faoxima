<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $keyboardprotocol, $message_id, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $baseUrl, $connectionResult, $panel.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class GuardProviderManagement
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$baseUrl,
        $callback_query_id,
        &$connectionResult,
        $datain,
        $from_id,
        $keyboardprotocol,
        $message_id,
        &$panel,
        $text,
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

        if ($user['step'] == "guard_edit_api_key") {
            $adminAction = 1;
        } elseif ($text == "⁉️ اتصال به پنل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($text == "⚙️ سرویس پیش‌فرض" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($datain === 'rebeccaservicenoop') {
            $adminAction = 4;
        } elseif (preg_match('/^rebeccaservicepage#(\d+)$/', $datain, $rebeccaServiceMatch)) {
            $adminAction = 5;
        } elseif (preg_match('/^rebeccasetservice#(\d+)(?:#(\d+))?$/', $datain, $rebeccaServiceMatch)) {
            $adminAction = 6;
        } elseif ($text == "⚙️ تنظیم سرویس ها" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($text == "👤 ویرایش نام" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "GetusernameNew") {
            $adminAction = 9;
        } elseif ($text == "⚙️ تنظیم پروتکل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "getprotocolx_ui") {
            $adminAction = 11;
        } elseif ($text == "🔄 تغییر حالت API" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\GuardProviderManagement::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            baseUrl: $baseUrl,
            callback_query_id: $callback_query_id ?? null,
            connectionResult: $connectionResult,
            from_id: $from_id ?? null,
            keyboardprotocol: $keyboardprotocol ?? null,
            message_id: $message_id ?? null,
            panel: $panel,
            rebeccaServiceMatch: $rebeccaServiceMatch ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
