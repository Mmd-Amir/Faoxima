<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $ManagePanel, $adminExecution, $adminrulecheck, $backadmin, $backuser, $callback_query_id, $datain, $errorreport, $from_id, $json_list_marzban_panel, $json_list_product_list_admin, $keyboardadmin, $list_card_remove, $message_id, $pdo, $setting, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $datagetr, $datatextbot, $id_user, $info_product, $marzban_list_get, $randomString, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WebAccessAndReceiptReview
{
    public static function handle(
        $CartManage,
        $ManagePanel,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $backuser,
        $callback_query_id,
        &$dataget,
        &$datagetr,
        $datain,
        &$datatextbot,
        $errorreport,
        $from_id,
        &$id_user,
        &$info_product,
        $json_list_marzban_panel,
        $json_list_product_list_admin,
        $keyboardadmin,
        $list_card_remove,
        &$marzban_list_get,
        $message_id,
        $pdo,
        &$randomString,
        $setting,
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

        if (preg_match('/^webpanel_mgr_ipunlim_(on|off)_(\w+)$/', $datain, $webpanelMgrIpUnlimMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (preg_match('/^webpanel_mgr_ipadd_(\w+)$/', $datain, $webpanelMgrIpAddMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == "webpanel_mgr_get_ip" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif (preg_match('/^webpanel_mgr_ipdel_(\w+)_(\d+)$/', $datain, $webpanelMgrIpDelMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif (preg_match('/addordermanualـ(\w+)/', $datain, $dataget)) {
            $adminAction = 5;
        } elseif ($user['step'] == "getusernameconfig") {
            $adminAction = 6;
        } elseif ($user['step'] == "getnamepanelconfig") {
            $adminAction = 7;
        } elseif ($user['step'] == "stependforaddorder") {
            $adminAction = 8;
        } elseif ($text == "⬇️ کف خرید عمده" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "minbalancebulk") {
            $adminAction = 10;
        } elseif ($text == "❌ حذف شماره کارت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($user['step'] == "getcardremove") {
            $adminAction = 12;
        } elseif (preg_match('/^rejectrequesta_(\w+)/', $datain, $datagetr)) {
            $adminAction = 13;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\WebAccessAndReceiptReview::handle(
            CartManage: $CartManage ?? null,
            ManagePanel: $ManagePanel ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            backuser: $backuser ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget ?? null,
            datagetr: $datagetr ?? null,
            datatextbot: $datatextbot,
            errorreport: $errorreport ?? null,
            from_id: $from_id ?? null,
            id_user: $id_user,
            info_product: $info_product,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            json_list_product_list_admin: $json_list_product_list_admin ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            list_card_remove: $list_card_remove ?? null,
            marzban_list_get: $marzban_list_get,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            randomString: $randomString,
            setting: $setting ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            webpanelMgrIpAddMatch: $webpanelMgrIpAddMatch ?? null,
            webpanelMgrIpDelMatch: $webpanelMgrIpDelMatch ?? null,
            webpanelMgrIpUnlimMatch: $webpanelMgrIpUnlimMatch ?? null
        );
    }
}
