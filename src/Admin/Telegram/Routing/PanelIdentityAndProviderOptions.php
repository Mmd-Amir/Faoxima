<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $marzban_list, $message_id, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data, $list.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PanelIdentityAndProviderOptions
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        &$data,
        $datain,
        $from_id,
        &$list,
        $marzban_list,
        $message_id,
        $pdo,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminRouteMatched = false;
        $adminAction = null;

        if (false) {
            $adminAction = 1;
        } elseif ($text == "✍️ نام پنل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == "GetNameNew") {
            $adminAction = 3;
        } elseif (($text == "⁉️ تست اتصال به پنل" || $text == "🔄 تست اتصال مجدد") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($text == "🔑 تنظیم توکن API" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "get_remna_token" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($text == "🛡 تنظیم شناسه Squad" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif (preg_match('/^remnatogglesquad#([0-9a-fA-F-]{36})$/', $datain, $remnaSquadMatch)) {
            $adminAction = 8;
        } elseif ($text == "🔄 تغییر لینک کاربر" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($text == "🔑 ریست رمز عبور کاربر" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "get_remna_resetcreds" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($text == "🖥 داشبورد نودها" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($text == "🔎 جستجو با آیدی تلگرام" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($user['step'] == "get_remna_tglookup" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($text == "🛡 انتساب Squad کاربر" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        } elseif ($user['step'] == "get_remna_setsquad" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 16;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\PanelIdentityAndProviderOptions::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            data: $data,
            from_id: $from_id ?? null,
            list: $list,
            marzban_list: $marzban_list ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            remnaSquadMatch: $remnaSquadMatch ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
