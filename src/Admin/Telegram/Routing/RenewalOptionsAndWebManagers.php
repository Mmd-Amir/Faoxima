<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $Methodextend, $adminExecution, $adminnumber, $adminrulecheck, $datain, $domainhosts, $from_id, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $token.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalOptionsAndWebManagers
{
    public static function handle(
        $Methodextend,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminnumber,
        $adminrulecheck,
        $datain,
        $domainhosts,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        $text,
        $textbotlang,
        &$token,
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

        if ($text == "🔋 روش تمدید سرویس" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "updateextendmethod") {
            $adminAction = 2;
        } elseif ($text == "/token") {
            $adminAction = 3;
        } elseif ($text == "/token2") {
            $adminAction = 4;
        } elseif (($text == "✅ پنل تحت وب" || $datain == "webpanel_mgr_list") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif (preg_match('/^webpanel_mgr_view_(\w+)$/', $datain, $webpanelMgrViewMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif (preg_match('/^webpanel_mgr_creds_(\w+)$/', $datain, $webpanelMgrCredsMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif (preg_match('/^webpanel_mgr_uname_(\w+)$/', $datain, $webpanelMgrUnameMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "webpanel_mgr_get_uname" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif (preg_match('/^webpanel_mgr_resetpass_(\w+)$/', $datain, $webpanelMgrResetMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif (preg_match('/^webpanel_mgr_ip_(\w+)$/', $datain, $webpanelMgrIpMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\RenewalOptionsAndWebManagers::handle(
            Methodextend: $Methodextend ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            adminnumber: $adminnumber ?? null,
            domainhosts: $domainhosts ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            token: $token,
            update: $update ?? null,
            user: $user ?? null,
            webpanelMgrCredsMatch: $webpanelMgrCredsMatch ?? null,
            webpanelMgrIpMatch: $webpanelMgrIpMatch ?? null,
            webpanelMgrResetMatch: $webpanelMgrResetMatch ?? null,
            webpanelMgrUnameMatch: $webpanelMgrUnameMatch ?? null,
            webpanelMgrViewMatch: $webpanelMgrViewMatch ?? null
        );
    }
}
