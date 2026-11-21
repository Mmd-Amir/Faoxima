<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $APIKEY, $adminExecution, $adminrulecheck, $backadmin, $datain, $from_id, $keyboardadmin, $message_id, $optionMarzban, $optionPasarGuard, $pdo, $photo, $photoid, $setting_panel, $text, $textbotlang, $textsetprotocol, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $key, $panel, $response, $stmt, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AgentSettingsAndServerMaintenance
{
    public static function handle(
        $APIKEY,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$dataget,
        $datain,
        $from_id,
        &$key,
        $keyboardadmin,
        $message_id,
        $optionMarzban,
        $optionPasarGuard,
        &$panel,
        $pdo,
        $photo,
        $photoid,
        &$response,
        $setting_panel,
        &$stmt,
        $text,
        $textbotlang,
        $textsetprotocol,
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

        if ($text == "💰 عضویت نمایندگی") {
            $adminAction = 1;
        } elseif ($user['step'] == "getpricereqagent") {
            $adminAction = 2;
        } elseif ($text == "🤖 تایید رسید بدون بررسی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($datain == "onauto") {
            $adminAction = 4;
        } elseif ($datain == "offauto") {
            $adminAction = 5;
        } elseif (preg_match('/transferaccount_(\w+)/', $datain, $dataget)) {
            $adminAction = 6;
        } elseif ($user['step'] == "getidfortransfers") {
            $adminAction = 7;
        } elseif ($text == "📷 تنظیمات کیو آر کد") {
            $adminAction = 8;
        } elseif ($text == "🔄 وضعیت کیوآرکد") {
            $adminAction = 9;
        } elseif ($text == "🖼 پس‌زمینه کیوآرکد") {
            $adminAction = 10;
        } elseif ($user['step'] == "getimagebackgroundqr") {
            $adminAction = 11;
        } elseif ($text == "⚙️ پروتکل اینباند" || $text == "🎛 تنظیم نام گروه" || $text == "⚙️ تنظیم نود") {
            $adminAction = 12;
        } elseif ($user['step'] == "setinboundandprotocol") {
            $adminAction = 13;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\AgentSettingsAndServerMaintenance::handle(
            APIKEY: $APIKEY ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            key: $key,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            optionMarzban: $optionMarzban ?? null,
            optionPasarGuard: $optionPasarGuard ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            response: $response,
            setting_panel: $setting_panel ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            textsetprotocol: $textsetprotocol ?? null,
            update: $update ?? null,
            user: $user ?? null,
            value: $value
        );
    }
}
