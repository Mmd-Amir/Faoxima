<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $backuser, $departemanslist, $from_id, $keyboardadmin, $pdo, $setting, $supportcenter, $textbotlang, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $stmt, $text, $textcart.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ExtraServicesAndHelpSettings
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $backuser,
        $departemanslist,
        $from_id,
        $keyboardadmin,
        $pdo,
        $setting,
        &$stmt,
        $supportcenter,
        &$text,
        $textbotlang,
        &$textcart,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "gettypeextramain") {
            $adminAction = 1;
        } elseif ($text == "📍 سقف حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == "GetmaxeExtra") {
            $adminAction = 3;
        } elseif ($user['step'] == "gettypeextramax") {
            $adminAction = 4;
        } elseif ($text == "📍 کف زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "Getmaintime") {
            $adminAction = 6;
        } elseif ($user['step'] == "gettypeextramaintime") {
            $adminAction = 7;
        } elseif ($text == "📍 سقف زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "Getmaxtime") {
            $adminAction = 9;
        } elseif ($user['step'] == "gettypeextramaxtime") {
            $adminAction = 10;
        } elseif ($text == "🔼 اضافه کردن دپارتمان") {
            $adminAction = 11;
        } elseif ($user['step'] == "getidadmindep") {
            $adminAction = 12;
        } elseif ($user['step'] == "getdeparteman") {
            $adminAction = 13;
        } elseif ($text == "🔽 حذف کردن دپارتمان") {
            $adminAction = 14;
        } elseif ($user['step'] == "getremovedep") {
            $adminAction = 15;
        } elseif ($text == "⚙️ تنظیمات سرویس" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 16;
        } elseif ($user['step'] == "getservceid") {
            $adminAction = 17;
        } elseif ($text == "👤 تنظیم آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 18;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ExtraServicesAndHelpSettings::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            backuser: $backuser ?? null,
            departemanslist: $departemanslist ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            supportcenter: $supportcenter ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            textcart: $textcart,
            user: $user ?? null
        );
    }
}
