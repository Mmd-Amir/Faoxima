<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $keyboardadmin, $message_id, $otherreport, $pdo, $setting, $text, $textbotlang, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceRefunds
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
        $text,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/^mafurefauto-([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (preg_match('/^mafurefmanu-([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif (($user['step'] ?? '') == "mafurefamount" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif (preg_match('/^nmrefokdef_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ServiceRefunds::handle(
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
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null
        );
    }
}
