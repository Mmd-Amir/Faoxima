<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $Startelegram, $abangateway, $adminChannelMenu, $adminExecution, $adminPanelsMenu, $adminUsersMenu, $admin_ids, $adminrulecheck, $atlaspay, $backadmin, $blupal, $change_product, $cubepay, $datain, $domainhostsEscaped, $from_id, $iranpaykeyboard, $keyboard_Category_manage, $keyboard_shop_manage, $keyboard_stat, $keyboardadmin, $keyboardhelpadmin, $keyboardzarinpal, $message_id, $miniAppInstructionText, $nowpayment_setting_keyboard, $resolvedName, $rialit, $rx_back_origin, $rx_pem_entry_step, $setting, $setting_panel, $shopkeyboard, $text, $text_panel_admin_login_template, $textadmin, $textbot, $textbotlang, $tonpay, $trnado, $tronnowpayments, $variza, $version.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $channelkeyboard, $rawProcessing, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DashboardAndNavigation
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        $Startelegram,
        $abangateway,
        &$adminAction,
        $adminChannelMenu,
        $adminExecution,
        $adminPanelsMenu,
        &$adminRouteMatched,
        $adminUsersMenu,
        $admin_ids,
        $adminrulecheck,
        $atlaspay,
        $backadmin,
        $blupal,
        $change_product,
        &$channelkeyboard,
        $cubepay,
        $datain,
        $domainhostsEscaped,
        $from_id,
        $iranpaykeyboard,
        $keyboard_Category_manage,
        $keyboard_shop_manage,
        $keyboard_stat,
        $keyboardadmin,
        $keyboardhelpadmin,
        $keyboardzarinpal,
        $message_id,
        $miniAppInstructionText,
        $nowpayment_setting_keyboard,
        &$rawProcessing,
        $resolvedName,
        $rialit,
        $rx_back_origin,
        $rx_pem_entry_step,
        $setting,
        $setting_panel,
        $shopkeyboard,
        $text,
        $text_panel_admin_login_template,
        $textadmin,
        $textbot,
        $textbotlang,
        $tonpay,
        $trnado,
        $tronnowpayments,
        &$user,
        $variza,
        $version
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminRouteMatched = false;
        $adminAction = null;

        if (in_array($text, $textadmin) || $datain == "admin") {
            $adminAction = 1;
        } elseif ($text == $textbotlang['Admin']['backadmin'] || $text == "🏠 منوی مدیریت" || $text == "منوی مدیریت 🏠" || $text == "منوی مدیریت" || $text == "بازگشت به منوی مدیریت 🏠" || $text == "بازگشت به منوی مدیریت" || $datain == "adm_hub_main") {
            $adminAction = 2;
        } elseif ($datain == "hide_mini_app_instruction") {
            $adminAction = 3;
        } elseif ($text == "🔙 بازگشت به منوی پنل" && in_array($from_id, $admin_ids)) {
            $adminAction = 4;
        } elseif (($text == $textbotlang['Admin']['backmenu']) || ((isset($datain) ? (string)$datain : '') === 'backmenu')) {
            $adminAction = 5;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\DashboardAndNavigation::handle(
            CartManage: $CartManage ?? null,
            NowPaymentsManage: $NowPaymentsManage ?? null,
            Startelegram: $Startelegram ?? null,
            abangateway: $abangateway ?? null,
            adminAction: $adminAction ?? null,
            adminChannelMenu: $adminChannelMenu ?? null,
            adminExecution: $adminExecution ?? null,
            adminPanelsMenu: $adminPanelsMenu ?? null,
            adminUsersMenu: $adminUsersMenu ?? null,
            admin_ids: $admin_ids ?? null,
            adminrulecheck: $adminrulecheck ?? null,
            atlaspay: $atlaspay ?? null,
            backadmin: $backadmin ?? null,
            blupal: $blupal ?? null,
            change_product: $change_product ?? null,
            channelkeyboard: $channelkeyboard,
            cubepay: $cubepay ?? null,
            datain: $datain ?? null,
            domainhostsEscaped: $domainhostsEscaped ?? null,
            from_id: $from_id ?? null,
            iranpaykeyboard: $iranpaykeyboard ?? null,
            keyboard_Category_manage: $keyboard_Category_manage ?? null,
            keyboard_shop_manage: $keyboard_shop_manage ?? null,
            keyboard_stat: $keyboard_stat ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardhelpadmin: $keyboardhelpadmin ?? null,
            keyboardzarinpal: $keyboardzarinpal ?? null,
            message_id: $message_id ?? null,
            miniAppInstructionText: $miniAppInstructionText ?? null,
            nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
            rawProcessing: $rawProcessing,
            resolvedName: $resolvedName ?? null,
            rialit: $rialit ?? null,
            rx_back_origin: $rx_back_origin ?? null,
            rx_pem_entry_step: $rx_pem_entry_step ?? null,
            setting: $setting ?? null,
            setting_panel: $setting_panel ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            text_panel_admin_login_template: $text_panel_admin_login_template ?? null,
            textbot: $textbot ?? null,
            textbotlang: $textbotlang ?? null,
            tonpay: $tonpay ?? null,
            trnado: $trnado ?? null,
            tronnowpayments: $tronnowpayments ?? null,
            user: $user,
            variza: $variza ?? null,
            version: $version ?? null
        );
    }
}
