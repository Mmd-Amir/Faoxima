<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminExecution, $admin_ids, $callback_query_id, $datain, $from_id, $pdo, $text, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $id_invoice, $stmt, $usernameconfig.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalAdminAndDeletion
{
    public static function handle(
        $ManagePanel,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $admin_ids,
        $callback_query_id,
        &$dataget,
        $datain,
        $from_id,
        &$id_invoice,
        $pdo,
        &$stmt,
        $text,
        $update,
        $user,
        &$usernameconfig
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (is_string($datain) && strpos($datain, 'rxar:') === 0) {
            $adminAction = 1;
        } elseif (preg_match('/^extendadmin_(\w+)/', (string) $datain, $dataget) || strpos((string) $text, "/extend ") === 0) {
            $adminAction = 2;
        } elseif ($user['step'] == "rxar_vol" || $user['step'] == "rxar_days") {
            $adminAction = 3;
        } elseif (is_string($datain) && strpos($datain, 'confirmserivceadmin-') === 0) {
            $adminAction = 4;
        } elseif (preg_match('/removeresid_(\w+)/', $datain, $dataget)) {
            $adminAction = 5;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\RenewalAdminAndDeletion::handle(
            ManagePanel: $ManagePanel ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            admin_ids: $admin_ids ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            id_invoice: $id_invoice,
            pdo: $pdo ?? null,
            stmt: $stmt,
            text: $text ?? null,
            update: $update ?? null,
            usernameconfig: $usernameconfig
        );
    }
}
