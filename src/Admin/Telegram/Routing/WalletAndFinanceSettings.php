<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminExecution, $adminrulecheck, $backadmin, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $textbotlang, $update, $user.
 * Inherited or exported by reference: $Percent, $adminAction, $adminRouteMatched, $codeproduct, $dataget, $datagetr, $datatime, $day, $extend, $grp, $id_user, $lastupdate, $namestatus, $output, $row, $status, $stmt, $text, $usernameconfig.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WalletAndFinanceSettings
{
    public static function handle(
        $ManagePanel,
        &$Percent,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$codeproduct,
        &$dataget,
        &$datagetr,
        $datain,
        &$datatime,
        &$day,
        &$extend,
        $from_id,
        &$grp,
        &$id_user,
        $keyboardadmin,
        &$lastupdate,
        $message_id,
        &$namestatus,
        &$output,
        $pdo,
        &$row,
        &$status,
        &$stmt,
        &$text,
        $textbotlang,
        $update,
        $user,
        &$usernameconfig
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "mainbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "minbalance") {
            $adminAction = 2;
        } elseif ($user['step'] == "getagentbalancemin") {
            $adminAction = 3;
        } elseif ($datain == "maxbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($user['step'] == "maxbalance") {
            $adminAction = 5;
        } elseif ($user['step'] == "getagentbalancemax") {
            $adminAction = 6;
        } elseif (preg_match('/removeagent_(\w+)/', $datain, $dataget)) {
            $adminAction = 7;
        } elseif (preg_match('/addagent_(\w+)/', $datain, $dataget)) {
            $adminAction = 8;
        } elseif ($user['step'] == "gettypeagentoflist") {
            $adminAction = 9;
        } elseif (preg_match('/Percentlow_(\w+)/', $datain, $dataget)) {
            $adminAction = 10;
        } elseif ($user['step'] == "getpercentuser") {
            $adminAction = 11;
        } elseif (preg_match('/maxbuyagent_(\w+)/', $datain, $dataget)) {
            $adminAction = 12;
        } elseif ($user['step'] == "getmaxbuyagent") {
            $adminAction = 13;
        } elseif ($datain == "searchorder") {
            $adminAction = 14;
        } elseif ($user['step'] == "GetusernameconfigAndOrdedrs" || strpos($text, "/config ") !== false || preg_match('/manageinvoice_(\w+)/', $datain, $datagetr)) {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\WalletAndFinanceSettings::handle(
            ManagePanel: $ManagePanel ?? null,
            Percent: $Percent,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            codeproduct: $codeproduct,
            dataget: $dataget ?? null,
            datagetr: $datagetr ?? null,
            datatime: $datatime,
            day: $day,
            extend: $extend,
            from_id: $from_id ?? null,
            grp: $grp,
            id_user: $id_user,
            keyboardadmin: $keyboardadmin ?? null,
            lastupdate: $lastupdate,
            message_id: $message_id ?? null,
            namestatus: $namestatus,
            output: $output,
            pdo: $pdo ?? null,
            row: $row,
            status: $status,
            stmt: $stmt,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            usernameconfig: $usernameconfig
        );
    }
}
