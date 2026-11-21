<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminChannelMenu, $adminExecution, $adminrulecheck, $backadmin, $datain, $from_id, $json_list_marzban_panel, $keyboard_shop_manage, $keyboardadmin, $keyboardtimereset, $name_product, $pdo, $setting, $shopkeyboard, $textbotlang.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $panel, $text, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReportRouting
{
    public static function handle(
        &$adminAction,
        $adminChannelMenu,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $datain,
        $from_id,
        $json_list_marzban_panel,
        $keyboard_shop_manage,
        $keyboardadmin,
        $keyboardtimereset,
        $name_product,
        &$panel,
        $pdo,
        $setting,
        $shopkeyboard,
        &$text,
        $textbotlang,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (($text == "📣 گزارشات ربات" || (isset($datain) && $datain === 'set_reports')) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "addchannelid") {
            $adminAction = 2;
        } elseif ($text == "🏬 تنظیمات فروشگاه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($text == "🛍 افزودن محصول" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($user['step'] == "get_limit") {
            $adminAction = 5;
        } elseif ($user['step'] == "get_agent") {
            $adminAction = 6;
        } elseif ($user['step'] == "get_location") {
            $adminAction = 7;
        } elseif ($user['step'] == "getcategory") {
            $adminAction = 8;
        } elseif ($user['step'] == "get_time") {
            $adminAction = 9;
        } elseif ($user['step'] == "get_price") {
            $adminAction = 10;
        } elseif ($user['step'] == "gettimereset") {
            $adminAction = 11;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ReportRouting::handle(
            adminAction: $adminAction ?? null,
            adminChannelMenu: $adminChannelMenu ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            from_id: $from_id ?? null,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            keyboard_shop_manage: $keyboard_shop_manage ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardtimereset: $keyboardtimereset ?? null,
            name_product: $name_product ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            user: $user
        );
    }
}
