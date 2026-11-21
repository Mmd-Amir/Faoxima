<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $connect, $datain, $from_id, $inline_query_id, $keyboardadmin, $message_id, $pdo, $query, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $datagetr, $datatime, $destination, $id_user, $next_page, $page, $result, $results, $row, $rows, $stmt, $userdate.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class InlineInvoiceAndResellerTools
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $connect,
        &$datagetr,
        $datain,
        &$datatime,
        &$destination,
        $from_id,
        &$id_user,
        $inline_query_id,
        $keyboardadmin,
        $message_id,
        &$next_page,
        &$page,
        $pdo,
        $query,
        &$result,
        &$results,
        &$row,
        &$rows,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        &$userdate
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminRouteMatched = false;
        $adminAction = null;

        if (isset($update["inline_query"])) {
            $adminAction = 1;
        } elseif (preg_match('/vieworderuser_(\w+)/', $datain, $datagetr)) {
            $adminAction = 2;
        } elseif (preg_match('/next_pageinvoice_(\w+)/', $datain, $datagetr)) {
            $adminAction = 3;
        } elseif (preg_match('/previous_pageinvoice_(\w+)/', $datain, $datagetr)) {
            $adminAction = 4;
        } elseif ($datain == "cartuserlist") {
            $adminAction = 5;
        } elseif ($datain == 'next_pageusercart') {
            $adminAction = 6;
        } elseif ($datain == 'previous_pageusercart') {
            $adminAction = 7;
        } elseif (preg_match('/createbot_(\w+)/', $datain, $datagetr)) {
            $adminAction = 8;
        } elseif ($user['step'] == "gettokenbot") {
            $adminAction = 9;
        } elseif ($user['step'] == "getadminidbot") {
            $adminAction = 10;
        } elseif (preg_match('/removebotsell_(\w+)/', $datain, $datagetr)) {
            $adminAction = 11;
        } elseif (preg_match('/setvolumesrc_(\w+)/', $datain, $datagetr)) {
            $adminAction = 12;
        } elseif ($user['step'] == "getpricevolumesrc") {
            $adminAction = 13;
        } elseif (preg_match('/settimepricesrc_(\w+)/', $datain, $datagetr)) {
            $adminAction = 14;
        } elseif ($user['step'] == "getpricetimesrc") {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\InlineInvoiceAndResellerTools::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            connect: $connect ?? null,
            datagetr: $datagetr ?? null,
            datatime: $datatime,
            destination: $destination,
            from_id: $from_id ?? null,
            id_user: $id_user,
            inline_query_id: $inline_query_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            next_page: $next_page,
            page: $page,
            pdo: $pdo ?? null,
            query: $query ?? null,
            result: $result,
            results: $results,
            row: $row,
            rows: $rows,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null,
            userdate: $userdate
        );
    }
}
