<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $keyboardadmin, $message_id, $otherreport, $pdo, $setting, $setting_panel, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RefundCompletionAndTimeouts
{
    public static function handle(
        $ManagePanel,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        &$dataget,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        $otherreport,
        $pdo,
        $setting,
        $setting_panel,
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

        if (preg_match('/^nmrefcustom_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (($user['step'] ?? '') == "nmrefamount" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif (preg_match('/^nmrefreject_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($datain == "settimecornremovevolume" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($user['step'] == "getcronvolumere") {
            $adminAction = 5;
        } elseif ($datain == "setting_on_holdcron" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "on_hold_day") {
            $adminAction = 7;
        } elseif ($datain == "set_panel_timeout" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "get_panel_timeout") {
            $adminAction = 9;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\RefundCompletionAndTimeouts::handle(
            ManagePanel: $ManagePanel ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            otherreport: $otherreport ?? null,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            setting_panel: $setting_panel ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
