<?php

namespace Faoxima\Admin\Telegram\Routing;

use PDO;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $datain, $from_id, $pdo, $text, $textbotlang, $update.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $channelkeyboard, $kb, $stmt, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ChannelManagement
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$channelkeyboard,
        $datain,
        $from_id,
        &$kb,
        $pdo,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (($text == $textbotlang['Admin']['channel']['title'] || $text == "➕ افزودن کانال" || (isset($datain) && $datain == 'ch_add')) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "addchannel") {
            $adminAction = 2;
        } elseif ($user['step'] == "getremark") {
            $adminAction = 3;
        } elseif ($user['step'] == "getlinkjoin") {
            $adminAction = 4;
        } elseif ($adminrulecheck['rule'] == "administrator" && (
            (isset($datain) && preg_match('/^ch_manage_(\d+)$/', (string)$datain)) ||
            (
                !empty($text)
                && isset($user['step'])
                && $user['step'] === "channel"
                && $text !== "📢 کانال و اطلاع‌رسانی"
                && $text !== "📣 گزارشات ربات"
                && $text !== "📯 تنظیمات کانال"
                && $text !== "🔙 بازگشت به لیست کانال‌ها"
                && ($pdo instanceof PDO)
                && method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_find_channel_from_input')
                && ($rxPreMatched = \Faoxima\Admin\Telegram\ChannelNavigation::rx_find_channel_from_input($text, $datain ?? '', $pdo))
            ) ||
            (
                isset($user['step'])
                && $user['step'] === "channel"
                && !empty($text)
                && !in_array($text, [
                    "➕ افزودن کانال",
                    "➕ اضافه کردن کانال",
                    $textbotlang['Admin']['channel']['title'] ?? '',
                    $textbotlang['Admin']['backadmin'] ?? '',
                    $textbotlang['Admin']['backmenu'] ?? '',
                    "🏠 بازگشت به منوی مدیریت",
                    "بازگشت به منوی مدیریت 🏠",
                    "بازگشت به منوی مدیریت",
                    "منوی مدیریت 🏠",
                    "🏠 منوی مدیریت",
                    "منوی مدیریت",
                    "▶️ بازگشت به منوی قبل",
                    "بازگشت به منوی قبل ▶️",
                    "🔙 بازگشت به منوی قبل",
                    "بازگشت به منوی قبل ⬅️",
                    "بازگشت به منوی قبل",
                    "🔙 بازگشت به لیست کانال‌ها",
                    "بازگشت به لیست کانال‌ها",
                    "📯 تنظیمات کانال",
                    "📣 گزارشات ربات",
                    "📢 کانال و اطلاع‌رسانی"
                ], true)
            )
        )) {
            $adminAction = 5;
        } elseif ($adminrulecheck['rule'] == "administrator" && (
            (isset($datain) && preg_match('/^ch_del_(\d+)$/', (string)$datain, $delMatches)) ||
            ($text == "❌ حذف کانال" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
        )) {
            $adminAction = 6;
        } elseif ($adminrulecheck['rule'] == "administrator" && (
            (isset($datain) && preg_match('/^ch_editremark_(\d+)$/', (string)$datain, $editRemMatches)) ||
            ($text == "✏️ ویرایش عنوان دکمه" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
        )) {
            $adminAction = 7;
        } elseif ($user['step'] == "ch_edit_remark" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($adminrulecheck['rule'] == "administrator" && (
            (isset($datain) && preg_match('/^ch_editjoin_(\d+)$/', (string)$datain, $editJoinMatches)) ||
            ($text == "🔗 ویرایش لینک عضویت" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
        )) {
            $adminAction = 9;
        } elseif ($user['step'] == "ch_edit_linkjoin" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ChannelManagement::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            channelkeyboard: $channelkeyboard,
            datain: $datain ?? null,
            delMatches: $delMatches ?? null,
            editJoinMatches: $editJoinMatches ?? null,
            editRemMatches: $editRemMatches ?? null,
            from_id: $from_id ?? null,
            kb: $kb,
            pdo: $pdo ?? null,
            rxPreMatched: $rxPreMatched ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user
        );
    }
}
