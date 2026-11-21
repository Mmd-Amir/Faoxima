<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $json_list_marzban_panel, $keyboardadmin, $keyboardtimereset, $pdo, $rxGiftBatch, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data, $dataget, $grp, $keyboardagent, $message_id, $panel, $product, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProductAudiencesAndUserControls
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        &$data,
        &$dataget,
        $datain,
        $from_id,
        &$grp,
        $json_list_marzban_panel,
        $keyboardadmin,
        &$keyboardagent,
        $keyboardtimereset,
        &$message_id,
        &$panel,
        $pdo,
        &$product,
        $rxGiftBatch,
        $shopkeyboard,
        &$stmt,
        &$text,
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

        if ($text == "نوع کاربری" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "change_type_agent") {
            $adminAction = 2;
        } elseif ($text == "نوع ریست حجم" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == "change_reset_data") {
            $adminAction = 4;
        } elseif ($text == "موقعیت محصول" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "change_loc_data") {
            $adminAction = 6;
        } elseif ($text == "حجم" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "change_val") {
            $adminAction = 8;
        } elseif ($text == "زمان" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "change_time") {
            $adminAction = 10;
        } elseif ($datain == "balanceaddall") {
            $adminAction = 11;
        } elseif ($user['step'] == "add_Balance_all") {
            $adminAction = 12;
        } elseif (preg_match('/typebalanceall_(\w+)/', $datain, $dataget)) {
            $adminAction = 13;
        } elseif (preg_match('/typecustomer_(\w+)/', $datain, $dataget)) {
            $adminAction = 14;
        } elseif ($user['step'] == "getmeesagestatus" || (is_string($datain) && preg_match('/^bulkgift_resume_([a-f0-9]{16})$/', $datain, $rxGiftResume))) {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ProductAudiencesAndUserControls::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            data: $data,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            grp: $grp,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardagent: $keyboardagent,
            keyboardtimereset: $keyboardtimereset ?? null,
            message_id: $message_id,
            panel: $panel,
            pdo: $pdo ?? null,
            product: $product,
            rxGiftBatch: $rxGiftBatch ?? null,
            rxGiftResume: $rxGiftResume ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
