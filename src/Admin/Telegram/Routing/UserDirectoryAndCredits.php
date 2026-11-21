<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $connect, $datain, $from_id, $keyboardadmin, $message_id, $paymentreports, $pdo, $setting, $text, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $invoice, $next_page, $page, $randomString, $result, $row, $rows, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class UserDirectoryAndCredits
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $connect,
        &$dataget,
        $datain,
        $from_id,
        &$invoice,
        $keyboardadmin,
        $message_id,
        &$next_page,
        &$page,
        $paymentreports,
        $pdo,
        &$randomString,
        &$result,
        &$row,
        &$rows,
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

        if ($datain == "balanceuserlist") {
            $adminAction = 1;
        } elseif ($datain == 'next_pageuserbalance') {
            $adminAction = 2;
        } elseif ($datain == 'previous_pageuserbalance') {
            $adminAction = 3;
        } elseif ($datain == "activesubuserlist") {
            $adminAction = 4;
        } elseif ($datain == 'next_pageuseractivesub') {
            $adminAction = 5;
        } elseif ($datain == 'previous_pageuseractivesub') {
            $adminAction = 6;
        } elseif ($datain == "listrefral") {
            $adminAction = 7;
        } elseif ($datain == 'next_pageuserrefral') {
            $adminAction = 8;
        } elseif ($datain == 'previous_pageuserrefral') {
            $adminAction = 9;
        } elseif (preg_match('/addbalanceuser_(\w+)/', $datain, $dataget)) {
            $adminAction = 10;
        } elseif ($user['step'] == "addbalanceusercurrent") {
            $adminAction = 11;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\UserDirectoryAndCredits::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            invoice: $invoice,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            next_page: $next_page,
            page: $page,
            paymentreports: $paymentreports ?? null,
            pdo: $pdo ?? null,
            randomString: $randomString,
            result: $result,
            row: $row,
            rows: $rows,
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
