<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $activeUsers, $active_users, $adminExecution, $from_id, $json_list_marzban_panel, $optionGuard, $optionJovad, $optionManualsale, $optionMarzban, $optionPasarGuard, $optionRebecca, $optionX_ui_single, $option_remnawave, $optionwg, $pasarguardCredsMissing, $pasarguardTestRes, $pdo, $rebeccaServiceLine, $serverStatusBlock, $textbotlang, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $marzban_list_get, $text, $totalUsers.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderAdministrationCompletion
{
    public static function handle(
        $activeUsers,
        $active_users,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $from_id,
        $json_list_marzban_panel,
        &$marzban_list_get,
        $optionGuard,
        $optionJovad,
        $optionManualsale,
        $optionMarzban,
        $optionPasarGuard,
        $optionRebecca,
        $optionX_ui_single,
        $option_remnawave,
        $optionwg,
        $pasarguardCredsMissing,
        $pasarguardTestRes,
        $pdo,
        $rebeccaServiceLine,
        $serverStatusBlock,
        &$text,
        $textbotlang,
        &$totalUsers,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "GetLocationEdit") {
            $adminAction = 1;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ProviderAdministrationCompletion::handle(
            activeUsers: $activeUsers ?? null,
            active_users: $active_users ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            from_id: $from_id ?? null,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            marzban_list_get: $marzban_list_get,
            optionGuard: $optionGuard ?? null,
            optionJovad: $optionJovad ?? null,
            optionManualsale: $optionManualsale ?? null,
            optionMarzban: $optionMarzban ?? null,
            optionPasarGuard: $optionPasarGuard ?? null,
            optionRebecca: $optionRebecca ?? null,
            optionX_ui_single: $optionX_ui_single ?? null,
            option_remnawave: $option_remnawave ?? null,
            optionwg: $optionwg ?? null,
            pasarguardCredsMissing: $pasarguardCredsMissing ?? null,
            pasarguardTestRes: $pasarguardTestRes ?? null,
            pdo: $pdo ?? null,
            rebeccaServiceLine: $rebeccaServiceLine ?? null,
            serverStatusBlock: $serverStatusBlock ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            totalUsers: $totalUsers
        );
    }
}
