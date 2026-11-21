<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminnumber, $adminrulecheck, $backadmin, $datain, $from_id, $hideuserid, $keyboardadmin, $lottery, $message_id, $pdo, $setting, $text, $textbotlang, $update, $user, $wheelkeyboard.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data, $dataget, $key, $payment, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalAndPanelVisibility
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminnumber,
        $adminrulecheck,
        $backadmin,
        &$data,
        &$dataget,
        $datain,
        $from_id,
        $hideuserid,
        &$key,
        $keyboardadmin,
        $lottery,
        $message_id,
        &$payment,
        $pdo,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        $wheelkeyboard
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($text == "🔋 وضعیت تمدید" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($datain == "on_extend") {
            $adminAction = 2;
        } elseif ($datain == "off_extend") {
            $adminAction = 3;
        } elseif ((preg_match('/confirmchannel-(\w+)/', $datain, $dataget))) {
            $adminAction = 4;
        } elseif ((preg_match('/zerobalance-(\w+)/', $datain, $dataget))) {
            $adminAction = 5;
        } elseif (preg_match('/removeadmin_(\w+)/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($text == "🫣 مخفی پنل برای کاربر" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "getuserhide") {
            $adminAction = 8;
        } elseif ($text == "❌ حذف از لیست مخفی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "getuserhideforremove") {
            $adminAction = 10;
        } elseif ($datain == "scoresetting") {
            $adminAction = 11;
        } elseif ($text == "1️⃣ تنظیم جایزه نفر اول") {
            $adminAction = 12;
        } elseif ($user['step'] == "getonelotary") {
            $adminAction = 13;
        } elseif ($text == "2️⃣ تنظیم جایزه نفر دوم") {
            $adminAction = 14;
        } elseif ($user['step'] == "getonelotary2") {
            $adminAction = 15;
        } elseif ($text == "3️⃣ تنظیم جایزه نفر سوم") {
            $adminAction = 16;
        } elseif ($user['step'] == "getonelotary3") {
            $adminAction = 17;
        } elseif ($datain == "gradonhshans") {
            $adminAction = 18;
        } elseif ($text == "🎲 مبلغ برنده شدن کاربر") {
            $adminAction = 19;
        } elseif ($user['step'] == "getpricewheel") {
            $adminAction = 20;
        } elseif ($text == "💵 رسید های تایید نشده") {
            $adminAction = 21;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\RenewalAndPanelVisibility::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            adminnumber: $adminnumber ?? null,
            backadmin: $backadmin ?? null,
            data: $data,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            hideuserid: $hideuserid ?? null,
            key: $key,
            keyboardadmin: $keyboardadmin ?? null,
            lottery: $lottery ?? null,
            message_id: $message_id ?? null,
            payment: $payment,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            wheelkeyboard: $wheelkeyboard ?? null
        );
    }
}
