<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $pdo, $text, $update, $user, $wl_text.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PaymentCards
{
    public static function handle(
        $CartManage,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $pdo,
        &$stmt,
        $text,
        $update,
        $user,
        $wl_text
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "card_add_new") {
            $adminAction = 1;
        } elseif ($user['step'] == "card_add_cardnumber" && !isset($update['callback_query'])) {
            $adminAction = 2;
        } elseif ($user['step'] == "card_add_cardname" && !isset($update['callback_query'])) {
            $adminAction = 3;
        } elseif (preg_match('/^card_select_(\d+)$/', $datain, $m)) {
            $adminAction = 4;
        } elseif ($datain == "card_toggle_active") {
            $adminAction = 5;
        } elseif ($datain == "card_manage_whitelist" || preg_match('/^card_wl_page_(\d+)$/', $datain, $_wlpg_m)) {
            $adminAction = 6;
        } elseif ($datain == "card_wl_add") {
            $adminAction = 7;
        } elseif ($user['step'] == "card_wl_add_users" && !isset($update['callback_query'])) {
            $adminAction = 8;
        } elseif ($datain == "card_wl_clear") {
            $adminAction = 9;
        } elseif ($datain == "card_wl_remove") {
            $adminAction = 10;
        } elseif ($user['step'] == "card_wl_remove_uid" && !isset($update['callback_query'])) {
            $adminAction = 11;
        } elseif ($datain == "card_delete_confirm") {
            $adminAction = 12;
        } elseif ($datain == "card_delete_execute") {
            $adminAction = 13;
        } elseif ($datain == "card_edit_start") {
            $adminAction = 14;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\PaymentCards::handle(
            CartManage: $CartManage ?? null,
            _wlpg_m: $_wlpg_m ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            from_id: $from_id ?? null,
            m: $m ?? null,
            pdo: $pdo ?? null,
            stmt: $stmt,
            text: $text ?? null,
            user: $user ?? null,
            wl_text: $wl_text ?? null
        );
    }
}
