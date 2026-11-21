<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $callback_query_id, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $textbotlang, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderTypeConfirmation
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $callback_query_id,
        &$dataget,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        &$stmt,
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

        if ($user['step'] == "switchtype_confirm") {
            $adminAction = 1;
        } elseif ($datain == "systemsms") {
            $adminAction = 2;
        } elseif ($datain == "broadcast_status_refresh") {
            $adminAction = 3;
        } elseif (preg_match('/^broadcast_resume_([0-9a-f]{12})$/', $datain, $rxResumeMatch)) {
            $adminAction = 4;
        } elseif (preg_match('/^typeservice-(\w+)/', $datain, $dataget)) {
            $adminAction = 5;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ProviderTypeConfirmation::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            rxResumeMatch: $rxResumeMatch ?? null,
            stmt: $stmt,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null
        );
    }
}
