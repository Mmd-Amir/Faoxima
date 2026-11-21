<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $backadmin, $callback_query_id, $datain, $keyboardadmin, $text, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminExecution, $adminRouteMatched, $adminrulecheck, $dataget, $message_id, $pdo, $setting, $textbotlang.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AntiSpamSettings
{
    public static function handle(
        &$adminAction,
        &$adminExecution,
        &$adminRouteMatched,
        &$adminrulecheck,
        $backadmin,
        $callback_query_id,
        &$dataget,
        $datain,
        $keyboardadmin,
        &$message_id,
        &$pdo,
        &$setting,
        $text,
        &$textbotlang,
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

        if ($datain == "antispam_noop" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($datain == "antispam_toggle" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($datain == "antispam_set_count" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($datain == "antispam_set_seconds" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($datain == "antispam_set_mute" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "antispam_get_count" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "antispam_get_seconds" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "antispam_get_mute" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
            $adminAction = 9;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\AntiSpamSettings::handle(
            adminAction: $adminAction,
            adminExecution: $adminExecution,
            adminrulecheck: $adminrulecheck,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            dataget: $dataget,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id,
            pdo: $pdo,
            setting: $setting,
            text: $text ?? null,
            textbotlang: $textbotlang,
            update: $update ?? null
        );
    }
}
