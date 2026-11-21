<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $datain, $from_id, $keyboard_stat, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReportsAndTicketDepartments
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $datain,
        $from_id,
        $keyboard_stat,
        $keyboardadmin,
        $message_id,
        $pdo,
        &$stmt,
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

        if ($datain == "close_stat") {
            $adminAction = 1;
        } elseif ($datain == "hoursago_stat") {
            $adminAction = 2;
        } elseif ($datain == "yesterday_stat") {
            $adminAction = 3;
        } elseif ($datain == "today_stat") {
            $adminAction = 4;
        } elseif ($datain == "month_old_stat") {
            $adminAction = 5;
        } elseif ($datain == "month_current_stat") {
            $adminAction = 6;
        } elseif ($datain == "view_stat_time") {
            $adminAction = 7;
        } elseif ($user['step'] == "get_time_start") {
            $adminAction = 8;
        } elseif ($user['step'] == "get_time_end") {
            $adminAction = 9;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ReportsAndTicketDepartments::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            from_id: $from_id ?? null,
            keyboard_stat: $keyboard_stat ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
