<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $abangateway, $adminExecution, $backadmin, $blupal, $callback_query_id, $cubepay, $datain, $from_id, $keyboardzarinpal, $message_id, $nowpayment_setting_keyboard, $rialit, $text, $textbotlang, $tonpay, $trnado, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CashbackSettings
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        $abangateway,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $blupal,
        $callback_query_id,
        $cubepay,
        &$dataget,
        $datain,
        $from_id,
        $keyboardzarinpal,
        $message_id,
        $nowpayment_setting_keyboard,
        $rialit,
        $text,
        $textbotlang,
        $tonpay,
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

        if ($text == "💰 کش‌بک کارت") {
            $adminAction = 1;
        } elseif ($user['step'] == "getcashcart") {
            $adminAction = 2;
        } elseif ($text == "💰 کش بک ترونادو") {
            $adminAction = 3;
        } elseif ($user['step'] == "getcashiranpay2") {
            $adminAction = 4;
        } elseif ($text == "💰 کش بک تون‌پی") {
            $adminAction = 5;
        } elseif ($user['step'] == "getcashtonpay") {
            $adminAction = 6;
        } elseif ($text == "💰 کش بک بلوپال") {
            $adminAction = 7;
        } elseif ($user['step'] == "getcashblupal") {
            $adminAction = 8;
        } elseif ($text == "💰 کش بک واریزا") {
            $adminAction = 9;
        } elseif ($user['step'] == "getcashvariza") {
            $adminAction = 10;
        } elseif ($text == "💰 کش بک آبان گیت وی") {
            $adminAction = 11;
        } elseif ($user['step'] == "getcashabangateway") {
            $adminAction = 12;
        } elseif ($text == "💰 کش بک اطلس‌پی") {
            $adminAction = 13;
        } elseif ($user['step'] == "getcashatlaspay") {
            $adminAction = 14;
        } elseif ($text == "💰 کش بک کیوب‌پی") {
            $adminAction = 15;
        } elseif ($user['step'] == "getcashcubepay") {
            $adminAction = 16;
        } elseif ($text == "⚖️ کارمزد کیوب‌پی") {
            $adminAction = 17;
        } elseif ($user['step'] == "getfeecubepay") {
            $adminAction = 18;
        } elseif ($text == "💰 کش بک plisio") {
            $adminAction = 19;
        } elseif ($user['step'] == "getcashplisio") {
            $adminAction = 20;
        } elseif ($text == "💰 کش بک nowpayment") {
            $adminAction = 21;
        } elseif ($user['step'] == "getcashnowpayment") {
            $adminAction = 22;
        } elseif ($text == "💰 کش بک زرین پال") {
            $adminAction = 23;
        } elseif ($user['step'] == "getcashzarinpal") {
            $adminAction = 24;
        } elseif ($user['step'] == "getcashtarget") {
            $adminAction = 25;
        } elseif ($user['step'] == "getcashtargetdays") {
            $adminAction = 26;
        } elseif (preg_match('/^cbscope_toggle#(\w+)/', $datain, $dataget) && $user['step'] == "getcashscope") {
            $adminAction = 27;
        } elseif ($datain == "cbscope_all" && $user['step'] == "getcashscope") {
            $adminAction = 28;
        } elseif ($datain == "cbscope_confirm" && $user['step'] == "getcashscope") {
            $adminAction = 29;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\CashbackSettings::handle(
            CartManage: $CartManage ?? null,
            NowPaymentsManage: $NowPaymentsManage ?? null,
            abangateway: $abangateway ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            blupal: $blupal ?? null,
            callback_query_id: $callback_query_id ?? null,
            cubepay: $cubepay ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            keyboardzarinpal: $keyboardzarinpal ?? null,
            message_id: $message_id ?? null,
            nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
            rialit: $rialit ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            tonpay: $tonpay ?? null,
            trnado: $trnado ?? null,
            user: $user ?? null
        );
    }
}
