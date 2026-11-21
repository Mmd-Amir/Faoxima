<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $ip_list, $iplogin_unlimited, $message_id, $pdo, $shopkeyboard, $text, $textbotlang, $toggle_msg, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $key, $marzban_list_get, $stmt, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReceiptMaintenance
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $callback_query_id,
        &$dataget,
        $datain,
        $from_id,
        $ip_list,
        $iplogin_unlimited,
        &$key,
        &$marzban_list_get,
        $message_id,
        $pdo,
        $shopkeyboard,
        &$stmt,
        $text,
        $textbotlang,
        $toggle_msg,
        $update,
        $user,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "removeresid") {
            $adminAction = 1;
        } elseif (preg_match('/showinfopay_(\w+)/', $datain, $dataget)) {
            $adminAction = 2;
        } elseif ($text == "🎛 تنظیم اینباند") {
            $adminAction = 3;
        } elseif ($user['step'] == "getdatainboundproduct") {
            $adminAction = 4;
        } elseif ($datain == "iploginset") {
            $adminAction = 5;
        } elseif ($datain == "iploginunlim_on" || $datain == "iploginunlim_off") {
            $adminAction = 6;
        } elseif ($datain == "addiplogin") {
            $adminAction = 7;
        } elseif ($user['step'] == "getiplogin") {
            $adminAction = 8;
        } elseif (preg_match('/^deliplogin_(\d+)$/', $datain, $ipdel_match)) {
            $adminAction = 9;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ReceiptMaintenance::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            ip_list: $ip_list ?? null,
            ipdel_match: $ipdel_match ?? null,
            iplogin_unlimited: $iplogin_unlimited ?? null,
            key: $key,
            marzban_list_get: $marzban_list_get,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            toggle_msg: $toggle_msg ?? null,
            update: $update ?? null,
            user: $user ?? null,
            value: $value
        );
    }
}
