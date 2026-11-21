<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $callback_query_id, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $setting, $textbotlang.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $datagetr, $id_user, $label, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReceiptDecisionAndUserManagement
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $callback_query_id,
        &$datagetr,
        $datain,
        $from_id,
        &$id_user,
        $keyboardadmin,
        &$label,
        $message_id,
        $pdo,
        $setting,
        &$stmt,
        $textbotlang
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/^cfmreja_(\w+)/', $datain, $datagetr)) {
            $adminAction = 1;
        } elseif (preg_match('/^addagentrequest_(\w+)/', $datain, $datagetr)) {
            $adminAction = 2;
        } elseif (preg_match('/^cnclagentreq_(\w+)/', $datain, $datagetr)) {
            $adminAction = 3;
        } elseif (preg_match('/^cfmacea_(\w+)/', $datain, $datagetr)) {
            $adminAction = 4;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ReceiptDecisionAndUserManagement::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            callback_query_id: $callback_query_id ?? null,
            datagetr: $datagetr ?? null,
            from_id: $from_id ?? null,
            id_user: $id_user,
            keyboardadmin: $keyboardadmin ?? null,
            label: $label,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            textbotlang: $textbotlang ?? null
        );
    }
}
