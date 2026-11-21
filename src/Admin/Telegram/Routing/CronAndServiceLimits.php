<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminExecution, $adminnumber, $backadmin, $buyreport, $datain, $errorreport, $from_id, $json_list_marzban_panel, $keyboardlistsnode, $message_id, $optionGuard, $optionJovad, $optionManualsale, $optionPasarGuard, $optionRebecca, $option_remnawave, $optionathmarzban, $optionathx_ui, $pdo, $setting, $setting_panel, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $dataoutput, $datatextbot, $expire, $panel, $randomString, $result, $sumvolume, $text, $usernameconfig.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CronAndServiceLimits
{
    public static function handle(
        $ManagePanel,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminnumber,
        $backadmin,
        $buyreport,
        &$dataget,
        $datain,
        &$dataoutput,
        &$datatextbot,
        $errorreport,
        &$expire,
        $from_id,
        $json_list_marzban_panel,
        $keyboardlistsnode,
        $message_id,
        $optionGuard,
        $optionJovad,
        $optionManualsale,
        $optionPasarGuard,
        $optionRebecca,
        $option_remnawave,
        $optionathmarzban,
        $optionathx_ui,
        &$panel,
        $pdo,
        &$randomString,
        &$result,
        $setting,
        $setting_panel,
        &$sumvolume,
        &$text,
        $textbotlang,
        $update,
        $user,
        $username,
        &$usernameconfig
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "settimecornvolume") {
            $adminAction = 1;
        } elseif ($user['step'] == "getvolumewarn") {
            $adminAction = 2;
        } elseif ($text == "🔧 کانفیگ دستی") {
            $adminAction = 3;
        } elseif ($user['step'] == "getusernameconfigcr") {
            $adminAction = 4;
        } elseif ($user['step'] == "getcountcreate") {
            $adminAction = 5;
        } elseif ($user['step'] == "getvolumesconfig") {
            $adminAction = 6;
        } elseif ($user['step'] == "gettimeaccount") {
            $adminAction = 7;
        } elseif ($text == "🛠 قابلیت های پنل") {
            $adminAction = 8;
        } elseif ($user['step'] == "getlocoption") {
            $adminAction = 9;
        } elseif ($text == "🖥 مدیریت نود ها" || $datain == "bakcnode") {
            $adminAction = 10;
        } elseif (preg_match('/^node_(.*)/', $datain, $dataget)) {
            $adminAction = 11;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\CronAndServiceLimits::handle(
            ManagePanel: $ManagePanel ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            adminnumber: $adminnumber ?? null,
            backadmin: $backadmin ?? null,
            buyreport: $buyreport ?? null,
            dataget: $dataget ?? null,
            datain: $datain ?? null,
            dataoutput: $dataoutput,
            datatextbot: $datatextbot,
            errorreport: $errorreport ?? null,
            expire: $expire,
            from_id: $from_id ?? null,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            keyboardlistsnode: $keyboardlistsnode ?? null,
            message_id: $message_id ?? null,
            optionGuard: $optionGuard ?? null,
            optionJovad: $optionJovad ?? null,
            optionManualsale: $optionManualsale ?? null,
            optionPasarGuard: $optionPasarGuard ?? null,
            optionRebecca: $optionRebecca ?? null,
            option_remnawave: $option_remnawave ?? null,
            optionathmarzban: $optionathmarzban ?? null,
            optionathx_ui: $optionathx_ui ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            randomString: $randomString,
            result: $result,
            setting: $setting ?? null,
            setting_panel: $setting_panel ?? null,
            sumvolume: $sumvolume,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            username: $username ?? null,
            usernameconfig: $usernameconfig
        );
    }
}
