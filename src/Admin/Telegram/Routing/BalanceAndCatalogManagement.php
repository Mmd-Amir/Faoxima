<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $callback_query_id, $connect, $datain, $from_id, $id_user, $keyboardadmin, $lastmessage, $message_id, $paymentreports, $pdo, $setting, $sumvolume, $text, $text_expie_agent, $textbotlang, $update, $username.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $stmt, $user, $userjoin.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BalanceAndCatalogManagement
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $callback_query_id,
        $connect,
        &$dataget,
        $datain,
        $from_id,
        $id_user,
        $keyboardadmin,
        $lastmessage,
        $message_id,
        $paymentreports,
        $pdo,
        $setting,
        &$stmt,
        $sumvolume,
        $text,
        $text_expie_agent,
        $textbotlang,
        $update,
        &$user,
        &$userjoin,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($text == "⬇️ کم کردن موجودی") {
            $adminAction = 1;
        } elseif ($user['step'] == "Negative_Balance") {
            $adminAction = 2;
        } elseif ($user['step'] == "get_price_Negative") {
            $adminAction = 3;
        } elseif ($datain == "searchuser" || $datain == "support_search" || $text == "👁‍🗨 جستجو کاربر") {
            $adminAction = 4;
        } elseif ($user['step'] == "show_info" || preg_match('/manageuser_(\w+)/', $datain, $dataget) || preg_match('/updateinfouser_(\w+)/', $datain, $dataget) || (is_string($datain) && preg_match('/^toggle_user_cardpayment_(\d+)$/', $datain)) || strpos($text, "/user ") !== false || strpos($text, "/id ") !== false) {
            $adminAction = 5;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\BalanceAndCatalogManagement::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            id_user: $id_user ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            lastmessage: $lastmessage ?? null,
            message_id: $message_id ?? null,
            paymentreports: $paymentreports ?? null,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            sumvolume: $sumvolume ?? null,
            text: $text ?? null,
            text_expie_agent: $text_expie_agent ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user,
            userjoin: $userjoin,
            username: $username ?? null
        );
    }
}
