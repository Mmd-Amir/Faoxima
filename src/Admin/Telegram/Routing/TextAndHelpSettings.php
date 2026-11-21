<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $datain, $datatextbot, $from_id, $keyboardadmin, $message_id, $pdo, $rxPemBase, $rxPemButtons, $rxPemMode, $rxPemSettingsCb, $rxPemSuccessMsg, $text, $textbot, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $rxPemRow.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TextAndHelpSettings
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $datain,
        $datatextbot,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        $rxPemBase,
        $rxPemButtons,
        $rxPemMode,
        &$rxPemRow,
        $rxPemSettingsCb,
        $rxPemSuccessMsg,
        $text,
        $textbot,
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

        if ($datain == "cancel_sendmessage") {
            $adminAction = 1;
        } elseif ($text == "📝 تنظیم متن ربات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($text == "📱 متن پیشنهاد مینی‌اپ" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == 'edit_miniapp_suggest_text' && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($user['step'] == "premium_emoji_get_char" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "premium_emoji_get_id" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "premium_emoji_edit_id" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\TextAndHelpSettings::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            datatextbot: $datatextbot ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            rxPemBase: $rxPemBase ?? null,
            rxPemButtons: $rxPemButtons ?? null,
            rxPemMode: $rxPemMode ?? null,
            rxPemRow: $rxPemRow,
            rxPemSettingsCb: $rxPemSettingsCb ?? null,
            rxPemSuccessMsg: $rxPemSuccessMsg ?? null,
            text: $text ?? null,
            textbot: $textbot ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
