<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $datain, $from_id, $message_id, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $text, $value, $valuenew.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CoefficientsAndWalletDefaults
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$dataget,
        $datain,
        $from_id,
        $message_id,
        $shopkeyboard,
        &$text,
        $textbotlang,
        $update,
        $user,
        &$value,
        &$valuenew
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "changecoefficient") {
            $adminAction = 1;
        } elseif ($user['step'] == "getusage_coefficient") {
            $adminAction = 2;
        } elseif ($datain == "changenamenode") {
            $adminAction = 3;
        } elseif ($user['step'] == "getnamenode") {
            $adminAction = 4;
        } elseif ($datain == "changeipnode") {
            $adminAction = 5;
        } elseif ($user['step'] == "getipnodeset") {
            $adminAction = 6;
        } elseif ($datain == "reconnectnode") {
            $adminAction = 7;
        } elseif ($datain == "removenode") {
            $adminAction = 8;
        } elseif ($text == "💎 مالی و گزارشات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($text == "🎁 کش بک تمدید" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "getpricecashback") {
            $adminAction = 11;
        } elseif ($user['step'] == "getagent") {
            $adminAction = 12;
        } elseif ($user['step'] == "getrenewcashtarget") {
            $adminAction = 13;
        } elseif ($user['step'] == "getrenewcashtargetdays") {
            $adminAction = 14;
        } elseif (preg_match('/^editpayment-(.*)-(.*)/', $datain, $dataget)) {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\CoefficientsAndWalletDefaults::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            message_id: $message_id ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            value: $value,
            valuenew: $valuenew
        );
    }
}
