<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $keyboardadmin, $otherreport, $pdo, $setting, $text, $textbotlang, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $id_invoice, $invoice, $marzban_list_get, $nameloc, $pricelast, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AgentRequestsAndServiceAdministration
{
    public static function handle(
        $ManagePanel,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $callback_query_id,
        &$dataget,
        $datain,
        $from_id,
        &$id_invoice,
        &$invoice,
        $keyboardadmin,
        &$marzban_list_get,
        &$nameloc,
        $otherreport,
        $pdo,
        &$pricelast,
        $setting,
        &$stmt,
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

        if ($user['step'] == "descriptionsrequsts") {
            $adminAction = 1;
        } elseif (preg_match('/remoceserviceadmin-(\w+)/', $datain, $dataget)) {
            $adminAction = 2;
        } elseif (preg_match('/remoceserviceadminmanual-(\w+)/', $datain, $dataget)) {
            $adminAction = 3;
        } elseif ($user['step'] == "getpricebackremove") {
            $adminAction = 4;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\AgentRequestsAndServiceAdministration::handle(
            ManagePanel: $ManagePanel ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            id_invoice: $id_invoice,
            invoice: $invoice,
            keyboardadmin: $keyboardadmin ?? null,
            marzban_list_get: $marzban_list_get,
            nameloc: $nameloc,
            otherreport: $otherreport ?? null,
            pdo: $pdo ?? null,
            pricelast: $pricelast,
            setting: $setting ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null
        );
    }
}
