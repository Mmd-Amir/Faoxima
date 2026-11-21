<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $datain, $from_id, $json_list_marzban_panel, $keyboardadmin, $keyboardchangelimit, $message_id, $pdo, $setting, $text, $textbotlang, $update, $user, $userdate.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $datagetr, $id_user, $panel, $rwSlotMatch, $userslist, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class LocationLimitsAndBulkGifts
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        &$datagetr,
        $datain,
        $from_id,
        &$id_user,
        $json_list_marzban_panel,
        $keyboardadmin,
        $keyboardchangelimit,
        $message_id,
        &$panel,
        $pdo,
        &$rwSlotMatch,
        $setting,
        $text,
        $textbotlang,
        $update,
        $user,
        $userdate,
        &$userslist,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "changeloclimit") {
            $adminAction = 1;
        } elseif ($text == "↙️ محدودیت کلی") {
            $adminAction = 2;
        } elseif ($user['step'] == "limitchangeall") {
            $adminAction = 3;
        } elseif ($text == "🆓 محدودیت رایگان") {
            $adminAction = 4;
        } elseif ($user['step'] == "limitfreechangefree") {
            $adminAction = 5;
        } elseif ($text == "🔄 ریست محدودیت کل کاربران") {
            $adminAction = 6;
        } elseif ($datain == "reasetchangeloc") {
            $adminAction = 7;
        } elseif ($datain == "randomwalletlimit") {
            $adminAction = 8;
        } elseif (preg_match('/^randomwallet_slot_(\d)$/', $datain, $rwSlotMatch)) {
            $adminAction = 9;
        } elseif ($user['step'] == "get_randomwallet_slot") {
            $adminAction = 10;
        } elseif (preg_match('/changeloclimitbyuser_(\w+)/', $datain, $datagetr)) {
            $adminAction = 11;
        } elseif ($user['step'] == "getlimitchangenewbyuser") {
            $adminAction = 12;
        } elseif (preg_match('/hidepanel_(\w+)/', $datain, $datagetr)) {
            $adminAction = 13;
        } elseif ($text == "/finish") {
            $adminAction = 14;
        } elseif ($user['step'] == "getpanelhidebotsaz") {
            $adminAction = 15;
        } elseif (preg_match('/removehide_(\w+)/', $datain, $datagetr)) {
            $adminAction = 16;
        } elseif ($text == "/remove") {
            $adminAction = 17;
        } elseif ($user['step'] == "getremovehidepanel") {
            $adminAction = 18;
        } elseif ($datain == "voloume_or_day_all") {
            $adminAction = 19;
        } elseif ($user['step'] == "getpanelgift") {
            $adminAction = 20;
        } elseif (preg_match('/typegift_(\w+)/', $datain, $datagetr)) {
            $adminAction = 21;
        } elseif ($user['step'] == "getvaluegift") {
            $adminAction = 22;
        } elseif ($user['step'] == "gettextgift") {
            $adminAction = 23;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\LocationLimitsAndBulkGifts::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            datagetr: $datagetr ?? null,
            from_id: $from_id ?? null,
            id_user: $id_user,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardchangelimit: $keyboardchangelimit ?? null,
            message_id: $message_id ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            rwSlotMatch: $rwSlotMatch ?? null,
            setting: $setting ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            userdate: $userdate ?? null,
            userslist: $userslist,
            value: $value
        );
    }
}
