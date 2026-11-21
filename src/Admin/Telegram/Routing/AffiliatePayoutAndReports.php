<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $affiliates, $affiliatesAntiFraud, $backadmin, $datain, $datatextbot, $from_id, $message_id, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AffiliatePayoutAndReports
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $affiliates,
        $affiliatesAntiFraud,
        $backadmin,
        $datain,
        $datatextbot,
        $from_id,
        $message_id,
        $pdo,
        $text,
        $textbotlang,
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

        if ($text == "🎁 پورسانت بعد از خرید" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($datain == "oncommission") {
            $adminAction = 2;
        } elseif ($datain == "offcommission") {
            $adminAction = 3;
        } elseif ($text == "🎁 هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($datain == "onDiscountaffiliates") {
            $adminAction = 5;
        } elseif ($datain == "offDiscountaffiliates") {
            $adminAction = 6;
        } elseif ($text == "🌟 مبلغ هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "getdiscont") {
            $adminAction = 8;
        } elseif ($datain == "affiliates_set_percentage" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($datain == "affiliates_set_start_amount" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($datain == "affiliates_toggle_start_gift" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($datain == "affiliates_toggle_commission" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($datain == "affiliates_toggle_first_buy_only" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif (($text == "🛡 حفاظت از سواستفاده" || $datain == "affiliates_antifraud_menu") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($text == "☎️ الزام تایید شماره" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        } elseif ($datain == "affiliates_toggle_phone_required" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 16;
        } elseif ($text == "⏳ حداقل سن اکانت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 17;
        } elseif ($user['step'] == "setminaccountage") {
            $adminAction = 18;
        } elseif ($text == "📅 سقف روزانه معرفی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 19;
        } elseif ($user['step'] == "setdailycap") {
            $adminAction = 20;
        } elseif ($text == "🗓 سقف ماهانه معرفی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 21;
        } elseif ($user['step'] == "setmonthlycap") {
            $adminAction = 22;
        } elseif (($text == "📊 گزارشات زیرمجموعه" || $datain == "affiliates_reports") && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 23;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\AffiliatePayoutAndReports::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            affiliates: $affiliates ?? null,
            affiliatesAntiFraud: $affiliatesAntiFraud ?? null,
            backadmin: $backadmin ?? null,
            datatextbot: $datatextbot ?? null,
            from_id: $from_id ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null
        );
    }
}
