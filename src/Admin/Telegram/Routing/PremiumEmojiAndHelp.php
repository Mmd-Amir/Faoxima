<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $caption, $connect, $datain, $document, $fileid, $from_id, $json_list_helpkey, $keyboardadmin, $keyboardhelpadmin, $message_id, $pdo, $photo, $photoid, $setting, $text, $textbotlang, $update, $user, $video, $videoid.
 * Inherited or exported by reference: $Response, $adminAction, $adminRouteMatched, $dataget, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PremiumEmojiAndHelp
{
    public static function handle(
        &$Response,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $caption,
        $connect,
        &$dataget,
        $datain,
        $document,
        $fileid,
        $from_id,
        $json_list_helpkey,
        $keyboardadmin,
        $keyboardhelpadmin,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/^premium_emoji_pack_link(?:_(\d+))?$/', (string)$user['step'], $rxPemPackInputStep) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (preg_match('/sendmessageuser_(\w+)/', $datain, $dataget)) {
            $adminAction = 2;
        } elseif ($user['step'] == "sendmessagetext") {
            $adminAction = 3;
        } elseif ($user['step'] == "sendmessagetid") {
            $adminAction = 4;
        } elseif ($text == "📤 فوروارد پیام برای یک کاربر") {
            $adminAction = 5;
        } elseif ($user['step'] == "getmessageforward") {
            $adminAction = 6;
        } elseif ($user['step'] == "getbtnresponseforward") {
            $adminAction = 7;
        } elseif ($text == "📚 بخش آموزش" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif (($text == "📥 دریافت پیش‌فرض‌ها" || $datain == "help_load_default") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($text == "📚 افزودن آموزش" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "add_name_help") {
            $adminAction = 11;
        } elseif ($user['step'] == "getcatgoryhelp") {
            $adminAction = 12;
        } elseif ($user['step'] == "add_dec") {
            $adminAction = 13;
        } elseif ($user['step'] == "add_app_title_help" && $datain == "help_skip_app_link") {
            $adminAction = 14;
        } elseif ($user['step'] == "add_app_title_help") {
            $adminAction = 15;
        } elseif ($user['step'] == "add_app_link_help") {
            $adminAction = 16;
        } elseif ($text == "❌ حذف آموزش" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 17;
        } elseif ($user['step'] == "remove_help") {
            $adminAction = 18;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\PremiumEmojiAndHelp::handle(
            Response: $Response,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            caption: $caption ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            document: $document ?? null,
            fileid: $fileid ?? null,
            from_id: $from_id ?? null,
            json_list_helpkey: $json_list_helpkey ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardhelpadmin: $keyboardhelpadmin ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            rxPemPackInputStep: $rxPemPackInputStep ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
