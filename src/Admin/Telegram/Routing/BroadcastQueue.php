<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $keyboardadmin, $pdo, $textday, $user, $userslist.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data, $message_id, $panel, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BroadcastQueue
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $callback_query_id,
        &$data,
        $datain,
        $from_id,
        $keyboardadmin,
        &$message_id,
        &$panel,
        $pdo,
        &$stmt,
        &$text,
        $textday,
        $user,
        $userslist
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "gettextSystemMessage") {
            $adminAction = 1;
        } elseif ($datain == "startaction") {
            $adminAction = 2;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\BroadcastQueue::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            data: $data,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id,
            panel: $panel,
            pdo: $pdo ?? null,
            stmt: $stmt,
            text: $text,
            textday: $textday ?? null,
            user: $user ?? null,
            userslist: $userslist ?? null
        );
    }
}
