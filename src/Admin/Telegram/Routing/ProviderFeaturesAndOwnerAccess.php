<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $Startelegram, $abangateway, $adminExecution, $adminnumber, $adminrulecheck, $atlaspay, $backadmin, $blupal, $callback_query_id, $cubepay, $datain, $domainhosts, $from_id, $iranpaykeyboard, $keyboardprotocol, $keyboardzarinpal, $message_id, $nowpayment_setting_keyboard, $optionMarzban, $pdo, $text, $textbotlang, $tonpay, $trnado, $tronnowpayments, $update, $user, $variza.
 * Inherited or exported by reference: $Response, $adminAction, $adminRouteMatched, $button, $tableName.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderFeaturesAndOwnerAccess
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        &$Response,
        $Startelegram,
        $abangateway,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminnumber,
        $adminrulecheck,
        $atlaspay,
        $backadmin,
        $blupal,
        &$button,
        $callback_query_id,
        $cubepay,
        $datain,
        $domainhosts,
        $from_id,
        $iranpaykeyboard,
        $keyboardprotocol,
        $keyboardzarinpal,
        $message_id,
        $nowpayment_setting_keyboard,
        $optionMarzban,
        $pdo,
        &$tableName,
        $text,
        $textbotlang,
        $tonpay,
        $trnado,
        $tronnowpayments,
        $update,
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

        if ($user['step'] == "apiblupal") {
            $adminAction = 1;
        } elseif ($text == "🔑 ثبت توکن API کیوب‌پی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == "apicubepay") {
            $adminAction = 3;
        } elseif ($text == "🔑 ثبت توکن API واریزا" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($user['step'] == "apivariza") {
            $adminAction = 5;
        } elseif ($text == "🔐 ثبت کلید وب‌هوک واریزا" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "variza_webhook_secret") {
            $adminAction = 7;
        } elseif ($text == "🔗 ثبت آدرس درگاه آبان گیت وی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "urlabangateway") {
            $adminAction = 9;
        } elseif ($text == "🔑 ثبت کلید اتصال آبان گیت وی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "apiabangateway") {
            $adminAction = 11;
        } elseif ($datain == "affilnecurrencysetting") {
            $adminAction = 12;
        } elseif (($rxGatewayLabelRoute = \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminGatewayLabelRoute($text, $user['step'] ?? null)) !== null) {
            $adminAction = 13;
        } elseif ($text == "⚙️  اینباند اکانت غیرفعال" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($user['step'] == "getprotocoldisable") {
            $adminAction = 15;
        } elseif ($user['step'] == "getInbounddisable") {
            $adminAction = 16;
        } elseif ($text == "🗑 بهینه سازی ربات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 17;
        } elseif ($text == "💀 بازنشانی ربات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 18;
        } elseif ($datain == "resetbot_cancel") {
            $adminAction = 19;
        } elseif ($datain == "resetbot_confirm" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 20;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ProviderFeaturesAndOwnerAccess::handle(
            CartManage: $CartManage ?? null,
            NowPaymentsManage: $NowPaymentsManage ?? null,
            Response: $Response,
            Startelegram: $Startelegram ?? null,
            abangateway: $abangateway ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            adminnumber: $adminnumber ?? null,
            atlaspay: $atlaspay ?? null,
            backadmin: $backadmin ?? null,
            blupal: $blupal ?? null,
            button: $button,
            callback_query_id: $callback_query_id ?? null,
            cubepay: $cubepay ?? null,
            domainhosts: $domainhosts ?? null,
            from_id: $from_id ?? null,
            iranpaykeyboard: $iranpaykeyboard ?? null,
            keyboardprotocol: $keyboardprotocol ?? null,
            keyboardzarinpal: $keyboardzarinpal ?? null,
            message_id: $message_id ?? null,
            nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
            optionMarzban: $optionMarzban ?? null,
            pdo: $pdo ?? null,
            rxGatewayLabelRoute: $rxGatewayLabelRoute ?? null,
            tableName: $tableName,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            tonpay: $tonpay ?? null,
            trnado: $trnado ?? null,
            tronnowpayments: $tronnowpayments ?? null,
            update: $update ?? null,
            user: $user ?? null,
            variza: $variza ?? null
        );
    }
}
