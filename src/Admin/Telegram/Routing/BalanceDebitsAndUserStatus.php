<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $connect, $datain, $from_id, $keyboard, $keyboardadmin, $message_id, $paymentreports, $pdo, $setting, $text, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $Response, $adminAction, $adminRouteMatched, $dataget, $invoice, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BalanceDebitsAndUserStatus
{
    public static function handle(
        &$Response,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $connect,
        &$dataget,
        $datain,
        $from_id,
        &$invoice,
        $keyboard,
        $keyboardadmin,
        $message_id,
        $paymentreports,
        $pdo,
        &$randomString,
        $setting,
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

        if (preg_match('/lowbalanceuser_(\w+)/', $datain, $dataget)) {
            $adminAction = 1;
        } elseif ($user['step'] == "addbalanceuser") {
            $adminAction = 2;
        } elseif (preg_match('/banuserlist_(\w+)/', $datain, $dataget)) {
            $adminAction = 3;
        } elseif (preg_match('/blockuserfake_(\w+)/', $datain, $dataget)) {
            $adminAction = 4;
        } elseif ($user['step'] == "adddecriptionblock") {
            $adminAction = 5;
        } elseif (preg_match('/acceptblock_(\w+)/', $datain, $dataget)) {
            $adminAction = 6;
        } elseif (preg_match('/verify_(\w+)/', $datain, $dataget)) {
            $adminAction = 7;
        } elseif (preg_match('/unverify-(\w+)/', $datain, $dataget)) {
            $adminAction = 8;
        } elseif (preg_match('/^cardverifyoff_(\d+)$/', $datain, $dataget)) {
            $adminAction = 9;
        } elseif (preg_match('/^cardverifyon_(\d+)$/', $datain, $dataget)) {
            $adminAction = 10;
        } elseif (preg_match('/^usercardmenu_(\d+)$/', $datain, $dataget)) {
            $adminAction = 11;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\BalanceDebitsAndUserStatus::handle(
            Response: $Response,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            invoice: $invoice,
            keyboard: $keyboard ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            paymentreports: $paymentreports ?? null,
            pdo: $pdo ?? null,
            randomString: $randomString,
            setting: $setting ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            username: $username ?? null
        );
    }
}
