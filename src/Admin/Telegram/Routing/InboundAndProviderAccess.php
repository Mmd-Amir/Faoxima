<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $from_id, $optionX_ui_single, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $baseUrl, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class InboundAndProviderAccess
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$baseUrl,
        $from_id,
        $optionX_ui_single,
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

        if ($text == "💎 شناسه اینباند" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "getinboundiid") {
            $adminAction = 2;
        } elseif ($text == "🔐 ویرایش کلید" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == "pasarguard_edit_api_key") {
            $adminAction = 4;
        } elseif ($user['step'] == "pasarguard_edit_username") {
            $adminAction = 5;
        } elseif ($user['step'] == "pasarguard_edit_password") {
            $adminAction = 6;
        } elseif ($user['step'] == "rebecca_edit_api_key") {
            $adminAction = 7;
        } elseif ($user['step'] == "jovad_edit_api_key") {
            $adminAction = 8;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\InboundAndProviderAccess::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            baseUrl: $baseUrl,
            from_id: $from_id ?? null,
            optionX_ui_single: $optionX_ui_single ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
