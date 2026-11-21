<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminPanelsMenu, $backadmin, $callback_query_id, $datain, $from_id, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $connectionResult, $dataget.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderTypeSelection
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        $adminPanelsMenu,
        &$adminRouteMatched,
        $backadmin,
        $callback_query_id,
        &$connectionResult,
        &$dataget,
        $datain,
        $from_id,
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

        if ($user['step'] == "switchtype_pick" && preg_match('/^switchpanelcat#(root|public|subscription|menu)$/', (string) $datain, $rxSwitchPanelCat)) {
            $adminAction = 1;
        } elseif ($user['step'] == "switchtype_pick" && preg_match('/^switchtype#(marzban|rebecca|pasarguard|pasargard|remnawave|guard|x-ui_single|jovadpanel|Manualsale)$/', (string) $datain, $dataget)) {
            $adminAction = 2;
        } elseif ($user['step'] == "switchtype_link_panel") {
            $adminAction = 3;
        } elseif ($user['step'] == "switchtype_xui_api_mode" && preg_match('/switchxuimode#(legacy|token)/', $datain, $dataget)) {
            $adminAction = 4;
        } elseif ($user['step'] == "switchtype_xui_token") {
            $adminAction = 5;
        } elseif ($user['step'] == "switchtype_remna_token") {
            $adminAction = 6;
        } elseif ($user['step'] == "switchtype_username_panel") {
            $adminAction = 7;
        } elseif ($user['step'] == "switchtype_password_panel") {
            $adminAction = 8;
        } elseif ($user['step'] == "switchtype_guard_version" && preg_match('/switchguardversion#(v1|v2)/', $datain, $dataget)) {
            $adminAction = 9;
        } elseif ($user['step'] == "switchtype_guard_api_key") {
            $adminAction = 10;
        } elseif ($user['step'] == "switchtype_rebecca_api_key") {
            $adminAction = 11;
        } elseif ($user['step'] == "switchtype_jovad_api_key") {
            $adminAction = 12;
        } elseif ($user['step'] == "switchtype_pasarguard_auth_method" && preg_match('/switchpasarguardauth#(api_key|password)/', $datain, $dataget)) {
            $adminAction = 13;
        } elseif ($user['step'] == "switchtype_pasarguard_username") {
            $adminAction = 14;
        } elseif ($user['step'] == "switchtype_pasarguard_password") {
            $adminAction = 15;
        } elseif ($user['step'] == "switchtype_pasarguard_api_key") {
            $adminAction = 16;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ProviderTypeSelection::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            adminPanelsMenu: $adminPanelsMenu ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            connectionResult: $connectionResult,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            rxSwitchPanelCat: $rxSwitchPanelCat ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
