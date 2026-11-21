<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $backuser, $datain, $from_id, $iranpaykeyboard, $keyboardadmin, $message_id, $okMsg, $text, $textbotlang, $trnado, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $cur, $label, $ok, $row.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CryptoWalletConfiguration
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $backuser,
        &$cur,
        $datain,
        $from_id,
        $iranpaykeyboard,
        $keyboardadmin,
        &$label,
        $message_id,
        &$ok,
        $okMsg,
        &$row,
        $text,
        $textbotlang,
        $trnado,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "cryptowallet_new" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "cryptowallet_new_network" && empty($datain)) {
            $adminAction = 2;
        } elseif (preg_match('/^cryptowalletnewmemo_(yes|no)$/', (string) $datain, $cwnm) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == "cryptowallet_new_memo" && empty($datain)) {
            $adminAction = 4;
        } elseif ($user['step'] == "cryptowallet_new_address" && empty($datain)) {
            $adminAction = 5;
        } elseif (preg_match('/^cryptowalletmanual_([A-Za-z0-9_]{2,20})$/', (string) $datain, $cwmm) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "walletaddresssiranpay" && empty($datain)) {
            $adminAction = 7;
        } elseif ($text == "api  درگاه ارزی ریالی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "apiiranpay") {
            $adminAction = 9;
        } elseif ($text == "⬇️ کف ریالی سوم") {
            $adminAction = 10;
        } elseif ($user['step'] == "minbalanceiranpay") {
            $adminAction = 11;
        } elseif ($text == "⬆️ سقف ریالی سوم") {
            $adminAction = 12;
        } elseif ($user['step'] == "maxbalanceiranpay") {
            $adminAction = 13;
        } elseif ($text == "📍 کف حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($user['step'] == "GetmaineExtra") {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\CryptoWalletConfiguration::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            backuser: $backuser ?? null,
            cur: $cur,
            cwmm: $cwmm ?? null,
            cwnm: $cwnm ?? null,
            from_id: $from_id ?? null,
            iranpaykeyboard: $iranpaykeyboard ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            label: $label,
            message_id: $message_id ?? null,
            ok: $ok,
            okMsg: $okMsg ?? null,
            row: $row,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            trnado: $trnado ?? null,
            user: $user ?? null
        );
    }
}
