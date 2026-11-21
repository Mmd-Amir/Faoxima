<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $affiliates, $backadmin, $callback_query_id, $datain, $from_id, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $connectionResult, $dataget.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReferralAndPanelSetup
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $affiliates,
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

        if ($datain == "settingaffiliatesf" || $text == "🎁 زیرمجموعه") {
            $adminAction = 1;
        } elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif (preg_match('/^panelcat#(root|public|subscription)$/', (string) $datain, $rxPanelCat)) {
            $adminAction = 3;
        } elseif (preg_match('/^typepanel#(marzban|rebecca|pasarguard|pasargard|remnawave|guard|x-ui_single|jovadpanel|Manualsale)$/', (string) $datain, $dataget)) {
            $adminAction = 4;
        } elseif ($user['step'] == "add_name_panel") {
            $adminAction = 5;
        } elseif ($user['step'] == "add_link_panel") {
            $adminAction = 6;
        } elseif ($user['step'] == "add_guard_version" && preg_match('/guardversion#(v1|v2)/', $datain, $dataget)) {
            $adminAction = 7;
        } elseif ($user['step'] == "add_xui_api_mode" && preg_match('/xuimode#(legacy|token)/', $datain, $dataget)) {
            $adminAction = 8;
        } elseif ($user['step'] == "add_xui_api_token") {
            $adminAction = 9;
        } elseif ($user['step'] == "add_remna_token_setup") {
            $adminAction = 10;
        } elseif ($user['step'] == "add_rebecca_api_key") {
            $adminAction = 11;
        } elseif ($user['step'] == "add_jovad_api_key") {
            $adminAction = 12;
        } elseif ($user['step'] == "add_username_panel") {
            $adminAction = 13;
        } elseif ($user['step'] == "add_guard_api_key") {
            $adminAction = 14;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ReferralAndPanelSetup::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            affiliates: $affiliates ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            connectionResult: $connectionResult,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            rxPanelCat: $rxPanelCat ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
