<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BulkRenewalAndProviderCapacity
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
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

        if ($text == "🎁 تمدید گروهی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "get_remna_bulkextend" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($text == "🔗 ویرایش آدرس پنل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == "GeturlNew") {
            $adminAction = 4;
        } elseif ($text == "📍 تغییر گروه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "getagentpanel") {
            $adminAction = 6;
        } elseif ($text == "🔗 دامنه لینک ساب" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "GeturlNewx") {
            $adminAction = 8;
        } elseif ($text == "🔗 uuid admin" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "getuuidadmin") {
            $adminAction = 10;
        } elseif ($text == "🚨 محدودیت اکانت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($datain == "panellimit_menu" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($datain == "panellimit_change" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($user['step'] == "panellimit_getnew") {
            $adminAction = 14;
        } elseif ($datain == "panellimit_reset" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        } elseif ($datain == "panellimit_unlimited" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 16;
        } elseif ($datain == "panellimit_back" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 17;
        } elseif (($text == "🧪 تنظیمات تست" || $text == "⏳ زمان سرویس تست" || $text == "💾 حجم اکانت تست") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 18;
        } elseif (is_string($datain) && strpos($datain, 'tset_') === 0 && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 19;
        } elseif (in_array($user['step'], ['tset_limit', 'updatetime', 'val_usertest'], true) && empty($datain) && isset($update['message']) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 20;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\BulkRenewalAndProviderCapacity::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
