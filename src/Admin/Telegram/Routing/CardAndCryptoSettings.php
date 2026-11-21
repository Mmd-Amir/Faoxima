<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $Startelegram, $abangateway, $adminExecution, $adminrulecheck, $atlaspay, $backadmin, $blupal, $callback_query_id, $cubepay, $datain, $doneTxt, $from_id, $iranpaykeyboard, $keyboardadmin, $keyboardzarinpal, $message_id, $nowpayment_setting_keyboard, $setting, $text, $textbotlang, $tonpay, $trnado, $tronnowpayments, $user, $variza.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $cmm, $cur, $cwd, $cwm, $label, $ok, $okMsg, $row, $rxGatewayLimitRoute.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CardAndCryptoSettings
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        $Startelegram,
        $abangateway,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $atlaspay,
        $backadmin,
        $blupal,
        $callback_query_id,
        &$cmm,
        $cubepay,
        &$cur,
        &$cwd,
        &$cwm,
        $datain,
        $doneTxt,
        $from_id,
        $iranpaykeyboard,
        $keyboardadmin,
        $keyboardzarinpal,
        &$label,
        $message_id,
        $nowpayment_setting_keyboard,
        &$ok,
        &$okMsg,
        &$row,
        &$rxGatewayLimitRoute,
        $setting,
        $text,
        $textbotlang,
        $tonpay,
        $trnado,
        $tronnowpayments,
        $user,
        $variza
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($text == "⬇️ کف کارت به کارت") {
            $adminAction = 1;
        } elseif ($user['step'] == "getmaincart") {
            $adminAction = 2;
        } elseif ($text == "⬆️ سقف کارت به کارت") {
            $adminAction = 3;
        } elseif ($text == "🔑 حداقل مبلغ احراز کارت") {
            $adminAction = 4;
        } elseif ($user['step'] == "get_cvmin_cart") {
            $adminAction = 5;
        } elseif ($user['step'] == "getmaxcart") {
            $adminAction = 6;
        } elseif (($rxGatewayLimitRoute = \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminGatewayLimitRoute($text, $user['step'] ?? null)) !== null) {
            $adminAction = 7;
        } elseif ($datain == "walletaddress" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif (preg_match('/^cryptomemo_(yes|no)_(TON|USDT_TON)$/', (string) $datain, $cmm) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "cryptowallet_set_memo" && empty($datain)) {
            $adminAction = 10;
        } elseif (preg_match('/^cryptowallet_del_([A-Za-z0-9_]{2,20})$/', (string) $datain, $cwd) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif (preg_match('/^cryptowallet_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $cwm) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($user['step'] == "cryptowallet_set" && empty($datain)) {
            $adminAction = 13;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\CardAndCryptoSettings::handle(
            CartManage: $CartManage ?? null,
            NowPaymentsManage: $NowPaymentsManage ?? null,
            Startelegram: $Startelegram ?? null,
            abangateway: $abangateway ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            atlaspay: $atlaspay ?? null,
            backadmin: $backadmin ?? null,
            blupal: $blupal ?? null,
            callback_query_id: $callback_query_id ?? null,
            cmm: $cmm ?? null,
            cubepay: $cubepay ?? null,
            cur: $cur,
            cwd: $cwd ?? null,
            cwm: $cwm ?? null,
            doneTxt: $doneTxt ?? null,
            from_id: $from_id ?? null,
            iranpaykeyboard: $iranpaykeyboard ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            keyboardzarinpal: $keyboardzarinpal ?? null,
            label: $label,
            message_id: $message_id ?? null,
            nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
            ok: $ok,
            okMsg: $okMsg,
            row: $row,
            rxGatewayLimitRoute: $rxGatewayLimitRoute ?? null,
            setting: $setting ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            tonpay: $tonpay ?? null,
            trnado: $trnado ?? null,
            tronnowpayments: $tronnowpayments ?? null,
            user: $user ?? null,
            variza: $variza ?? null
        );
    }
}
