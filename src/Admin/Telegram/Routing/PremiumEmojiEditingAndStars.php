<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $Startelegram, $adminExecution, $adminrulecheck, $backadmin, $caption, $datain, $from_id, $keyboardadmin, $nowpayment_setting_keyboard, $pdo, $photo, $photoid, $rxPemRow, $text, $textbotlang, $user, $video, $videoid.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PremiumEmojiEditingAndStars
{
    public static function handle(
        $Startelegram,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $caption,
        &$data,
        $datain,
        $from_id,
        $keyboardadmin,
        $nowpayment_setting_keyboard,
        $pdo,
        $photo,
        $photoid,
        $rxPemRow,
        $text,
        $textbotlang,
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

        if (preg_match('/^premium_emoji_edit_(\d+)(?:_(\d+))?$/', $datain, $rxPemMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (preg_match('/^premium_emoji_del_(\d+)$/', $datain, $rxPemMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif (preg_match('/^premium_emoji_replace_(\d+)_(\d+)$/', (string)$datain, $rxPemReplaceMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($datain == "startelegram") {
            $adminAction = 4;
        } elseif ($text == "⬇️ کف استار") {
            $adminAction = 5;
        } elseif ($user['step'] == "getmainaqstar") {
            $adminAction = 6;
        } elseif ($text == "⬆️ سقف استار") {
            $adminAction = 7;
        } elseif ($user['step'] == "maxbalancestar") {
            $adminAction = 8;
        } elseif ($text == "⬇️ کف nowpayment") {
            $adminAction = 9;
        } elseif ($user['step'] == "getmainaqnowpayment") {
            $adminAction = 10;
        } elseif ($text == "⬆️ سقف nowpayment") {
            $adminAction = 11;
        } elseif ($user['step'] == "maxbalancenowpayment") {
            $adminAction = 12;
        } elseif ($text == "📚 آموزش استار" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($user['step'] == "gethelpstar") {
            $adminAction = 14;
        } elseif ($text == "💰 کش بک استار") {
            $adminAction = 15;
        } elseif ($user['step'] == "chashbackstar") {
            $adminAction = 16;
        } elseif ($text == "🔋 قیمت سریع حجم") {
            $adminAction = 17;
        } elseif ($user['step'] == "getpricef") {
            $adminAction = 18;
        } elseif ($user['step'] == "getpricnn") {
            $adminAction = 19;
        } elseif ($user['step'] == "getpricnn2") {
            $adminAction = 20;
        } elseif ($text == "⏳ قیمت سریع زمان") {
            $adminAction = 21;
        } elseif ($user['step'] == "getpriceftime") {
            $adminAction = 22;
        } elseif ($user['step'] == "getpricnntime") {
            $adminAction = 23;
        } elseif ($user['step'] == "getpricnn2time") {
            $adminAction = 24;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\PremiumEmojiEditingAndStars::handle(
            Startelegram: $Startelegram ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            caption: $caption ?? null,
            data: $data,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            rxPemMatch: $rxPemMatch ?? null,
            rxPemReplaceMatch: $rxPemReplaceMatch ?? null,
            rxPemRow: $rxPemRow ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
