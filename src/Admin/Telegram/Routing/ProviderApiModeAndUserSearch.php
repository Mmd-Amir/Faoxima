<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminPanelsMenu, $adminrulecheck, $backadmin, $connect, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $datagetr, $next_page, $page, $result, $row, $rows.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderApiModeAndUserSearch
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        $adminPanelsMenu,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $connect,
        &$dataget,
        &$datagetr,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        &$next_page,
        &$page,
        $pdo,
        &$result,
        &$row,
        &$rows,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "edit_xui_api_mode" && preg_match('/editxuimode#(legacy|token)/', $datain, $dataget)) {
            $adminAction = 1;
        } elseif ($user['step'] == "edit_xui_api_token") {
            $adminAction = 2;
        } elseif ($text == "🔐 ویرایش رمز عبور" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == "GetpaawordNew") {
            $adminAction = 4;
        } elseif ($text == "❌ حذف پنل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "confirmremovepanel") {
            $adminAction = 6;
        } elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managruser'] || $datain == "backlistuser") {
            $adminAction = 7;
        } elseif ($datain == "alllistusers") {
            $adminAction = 8;
        } elseif ($datain == 'next_pageuser') {
            $adminAction = 9;
        } elseif ($datain == 'previous_pageuser') {
            $adminAction = 10;
        } elseif ($datain == 'close_listusers') {
            $adminAction = 11;
        } elseif ($datain == "agentlistusers") {
            $adminAction = 12;
        } elseif (preg_match('/agenttypshowlist_(\w+)/', $datain, $datagetr)) {
            $adminAction = 13;
        } elseif (preg_match('/next_pageuseragent_(\w+)/', $datain, $datagetr)) {
            $adminAction = 14;
        } elseif (preg_match('/previous_pageuseragent_(\w+)/', $datain, $datagetr)) {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ProviderApiModeAndUserSearch::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            adminPanelsMenu: $adminPanelsMenu ?? null,
            backadmin: $backadmin ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            datagetr: $datagetr ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            next_page: $next_page,
            page: $page,
            pdo: $pdo ?? null,
            result: $result,
            row: $row,
            rows: $rows,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
