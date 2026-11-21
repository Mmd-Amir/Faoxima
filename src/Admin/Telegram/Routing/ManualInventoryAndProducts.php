<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $change_product, $connect, $datain, $from_id, $json_list_marzban_panel, $list_marzban_panel_edit_product, $message_id, $name_product, $paymentreports, $pdo, $setting, $shopkeyboard, $text, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $Response, $adminAction, $adminRouteMatched, $dataget, $id_product, $info_product, $json_list_product_list_admin, $location, $panel, $product, $row, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ManualInventoryAndProducts
{
    public static function handle(
        &$Response,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $change_product,
        $connect,
        &$dataget,
        $datain,
        $from_id,
        &$id_product,
        &$info_product,
        $json_list_marzban_panel,
        &$json_list_product_list_admin,
        $list_marzban_panel_edit_product,
        &$location,
        $message_id,
        $name_product,
        &$panel,
        $paymentreports,
        $pdo,
        &$product,
        &$row,
        $setting,
        $shopkeyboard,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/^cmdelete_(\w+)$/', (string) $datain, $cmDel) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 1;
        } elseif ($text == "❌ حذف محصول" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == "selectloc") {
            $adminAction = 3;
        } elseif ($user['step'] == "remove-product") {
            $adminAction = 4;
        } elseif ($text == "✏️ ویرایش محصول" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif (preg_match('/locationedit_(\w+)/', $datain, $dataget)) {
            $adminAction = 6;
        } elseif (preg_match('/^typeagenteditproduct_(\w+)/', $datain, $dataget)) {
            $adminAction = 7;
        } elseif (preg_match('/^productedit_(\w+)/', $datain, $dataget)) {
            $adminAction = 8;
        } elseif ($text == "قیمت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "change_price") {
            $adminAction = 10;
        } elseif ($text == "یادداشت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($user['step'] == "change_note") {
            $adminAction = 12;
        } elseif ($text == "دسته بندی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($user['step'] == "change_categroy") {
            $adminAction = 14;
        } elseif ($text == "نام محصول" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        } elseif ($user['step'] == "change_name") {
            $adminAction = 16;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ManualInventoryAndProducts::handle(
            Response: $Response,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            change_product: $change_product ?? null,
            cmDel: $cmDel ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            id_product: $id_product,
            info_product: $info_product,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            json_list_product_list_admin: $json_list_product_list_admin,
            list_marzban_panel_edit_product: $list_marzban_panel_edit_product ?? null,
            location: $location,
            message_id: $message_id ?? null,
            name_product: $name_product ?? null,
            panel: $panel,
            paymentreports: $paymentreports ?? null,
            pdo: $pdo ?? null,
            product: $product,
            row: $row,
            setting: $setting ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            username: $username ?? null
        );
    }
}
