<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $abangateway, $adminExecution, $adminrulecheck, $atlaspay, $backadmin, $blupal, $callback_query_id, $cubepay, $datain, $from_id, $message_id, $pdo, $rialit, $text, $textbotlang, $tonpay, $tonpayMode, $trnado, $user, $variza.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $datagetr, $id_user, $label.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AgentStatusAndPaymentSettings
{
    public static function handle(
        $abangateway,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $atlaspay,
        $backadmin,
        $blupal,
        $callback_query_id,
        $cubepay,
        &$datagetr,
        $datain,
        $from_id,
        &$id_user,
        &$label,
        $message_id,
        $pdo,
        $rialit,
        $text,
        $textbotlang,
        $tonpay,
        $tonpayMode,
        $trnado,
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

        if (preg_match('/^setagenttype_(n|n2)_(\w+)/', $datain, $datagetr)) {
            $adminAction = 1;
        } elseif ($datain == "iranpay2setting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($datain == "tonpaysetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($datain == "rialitsetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($datain == "cubepaysetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($datain == "blupalsetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($datain == "varizasetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($datain == "abangatewaysetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($datain == "atlaspaysetting" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($text == "📊 موجودی و اطلاعات حساب" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($text == "وضعیت  درگاه ترونادو" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($datain == "onternado") {
            $adminAction = 12;
        } elseif ($datain == "offternado") {
            $adminAction = 13;
        } elseif ($text == "🔑 ثبت API Key ترونادو" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($user['step'] == "apiternado") {
            $adminAction = 15;
        } elseif ($text == "🔏 ثبت کلید امضای IPN ترونادو" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 16;
        } elseif ($user['step'] == "ipnsigningkeytronado") {
            $adminAction = 17;
        } elseif ($text == "💼 آدرس کیف پول ترونادو" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 18;
        } elseif ($text == "⚖️ درصد کارمزد کسب‌وکار" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 19;
        } elseif ($user['step'] == "wageFromBusinessPercentageTronado") {
            $adminAction = 20;
        } elseif ($text == "🔑 ثبت API Key تون‌پی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 21;
        } elseif ($user['step'] == "apitonpay") {
            $adminAction = 22;
        } elseif ($text == "🔐 ثبت API Key کاستوم تون‌پی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 23;
        } elseif ($user['step'] == "apitonpaycustom") {
            $adminAction = 24;
        } elseif (($text == "🔀 مقصد پرداخت تون‌پی" || preg_match('/^tonpay_paymode_(bot|web|custom)$/', (string) $datain, $tonpayModeMatch)) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 25;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\AgentStatusAndPaymentSettings::handle(
            abangateway: $abangateway ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            atlaspay: $atlaspay ?? null,
            backadmin: $backadmin ?? null,
            blupal: $blupal ?? null,
            callback_query_id: $callback_query_id ?? null,
            cubepay: $cubepay ?? null,
            datagetr: $datagetr ?? null,
            from_id: $from_id ?? null,
            id_user: $id_user,
            label: $label,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            rialit: $rialit ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            tonpay: $tonpay ?? null,
            tonpayMode: $tonpayMode ?? null,
            tonpayModeMatch: $tonpayModeMatch ?? null,
            trnado: $trnado ?? null,
            variza: $variza ?? null
        );
    }
}
