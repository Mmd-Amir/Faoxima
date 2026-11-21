<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $NowPaymentsManage, $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $json_list_marzban_panel, $keyboardadmin, $keyboardzarinpal, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $row, $stmt, $textcart.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CardEditingAndPanelOptions
{
    public static function handle(
        $NowPaymentsManage,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $json_list_marzban_panel,
        $keyboardadmin,
        $keyboardzarinpal,
        $pdo,
        &$row,
        &$stmt,
        $text,
        $textbotlang,
        &$textcart,
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

        if ($user['step'] == "card_edit_number" && !isset($update['callback_query'])) {
            $adminAction = 1;
        } elseif ($user['step'] == "card_edit_name" && !isset($update['callback_query'])) {
            $adminAction = 2;
        } elseif ($datain == "card_display_mode_menu" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif (($datain == "card_mode_set_random" || $datain == "card_mode_set_direct") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($datain == "plisiosetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($text == "🧩 api plisio" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "api_plisio" || $user['step'] == "apinowpayment") {
            $adminAction = 7;
        } elseif ($text == "API NOWPAYMENT") {
            $adminAction = 8;
        } elseif ($user['step'] == "marchent_tronseller") {
            $adminAction = 9;
        } elseif ($text == "🔐 IPN Secret nowpayment" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "nowpayment_ipn_secret") {
            $adminAction = 11;
        } elseif ($datain == "zarinpalsetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($text == "مرچنت زرین پال" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($user['step'] == "merchant_zarinpal") {
            $adminAction = 14;
        } elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\CardEditingAndPanelOptions::handle(
            NowPaymentsManage: $NowPaymentsManage ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardzarinpal: $keyboardzarinpal ?? null,
            pdo: $pdo ?? null,
            row: $row,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            textcart: $textcart,
            user: $user ?? null
        );
    }
}
