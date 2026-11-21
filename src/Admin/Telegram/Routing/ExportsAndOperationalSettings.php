<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $backuser, $callback_query_id, $connect, $datain, $from_id, $keyboardexportdata, $setting, $setting_panel, $text, $textbotlang, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $filename, $id_invoice, $invoice, $result, $row, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ExportsAndOperationalSettings
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $backuser,
        $callback_query_id,
        $connect,
        &$dataget,
        $datain,
        &$filename,
        $from_id,
        &$id_invoice,
        &$invoice,
        $keyboardexportdata,
        &$result,
        &$row,
        $setting,
        $setting_panel,
        $text,
        $textbotlang,
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

        if ($text == "🪪 خروجی گرفتن اطلاعات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($text == "🕚 تنظیمات کرون جاب" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($datain == "cronjobs_settings" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($datain == "cronjob_display" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($datain == "cronjobs_back_settings" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif (preg_match('/^cronjob_config-([A-Za-z0-9_]+)$/', $datain, $rx_cron_cfg) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif (preg_match('/^cronjob_apply-([A-Za-z0-9_]+)-(minute|hour|day|disabled)-(\d+)$/', $datain, $rx_cron_apply) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif (preg_match('/^cronjob_sethour-(lottery|statusday)$/', $datain, $rx_cron_h) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif (preg_match('/^cronjob_get_hour-(lottery|statusday)$/', (string) ($user['step'] ?? ''), $rx_cron_hs) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($text == "خروجی کاربران" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($text == "خروجی سفارشات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($text == "خروجی گرفتن پرداخت ها" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif (preg_match('/rejectremoceserviceadmin-(\w+)/', $datain, $dataget)) {
            $adminAction = 13;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ExportsAndOperationalSettings::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            backuser: $backuser ?? null,
            callback_query_id: $callback_query_id ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            filename: $filename,
            from_id: $from_id ?? null,
            id_invoice: $id_invoice,
            invoice: $invoice,
            keyboardexportdata: $keyboardexportdata ?? null,
            result: $result,
            row: $row,
            rx_cron_apply: $rx_cron_apply ?? null,
            rx_cron_cfg: $rx_cron_cfg ?? null,
            rx_cron_h: $rx_cron_h ?? null,
            rx_cron_hs: $rx_cron_hs ?? null,
            setting: $setting ?? null,
            setting_panel: $setting_panel ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            value: $value
        );
    }
}
