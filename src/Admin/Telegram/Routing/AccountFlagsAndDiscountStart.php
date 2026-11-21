<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $_toggle_notice, $adminExecution, $adminrulecheck, $backadmin, $connect, $datain, $from_id, $keyboard, $keyboardadmin, $message_id, $otherservice, $setting, $shopkeyboard, $text_inline, $textbotlang, $user.
 * Inherited or exported by reference: $Response, $adminAction, $adminRouteMatched, $dataget, $info_product, $text, $username.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AccountFlagsAndDiscountStart
{
    public static function handle(
        $ManagePanel,
        &$Response,
        $_toggle_notice,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $connect,
        &$dataget,
        $datain,
        $from_id,
        &$info_product,
        $keyboard,
        $keyboardadmin,
        $message_id,
        $otherservice,
        $setting,
        $shopkeyboard,
        &$text,
        $text_inline,
        $textbotlang,
        $user,
        &$username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/^toggleusercard_(\d+)_(\d+)$/', $datain, $dataget)) {
            $adminAction = 1;
        } elseif (preg_match('/unbanuserr_(\w+)/', $datain, $dataget)) {
            $adminAction = 2;
        } elseif (preg_match('/confirmnumber_(\w+)/', $datain, $dataget)) {
            $adminAction = 3;
        } elseif (preg_match('/viewpaymentuser_(\w+)/', $datain, $dataget)) {
            $adminAction = 4;
        } elseif (preg_match('/affiliates-(\w+)/', $datain, $dataget)) {
            $adminAction = 5;
        } elseif (preg_match('/removeaffiliate-(\w+)/', $datain, $dataget)) {
            $adminAction = 6;
        } elseif (preg_match('/removeaffiliateuser-(\w+)/', $datain, $dataget)) {
            $adminAction = 7;
        } elseif (preg_match('/removeservice-(.*)/', $datain, $dataget)) {
            $adminAction = 8;
        } elseif (preg_match('/removeserviceandback-(\w+)/', $datain, $dataget)) {
            $adminAction = 9;
        } elseif ($text == "🎁 ساخت کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "get_codesell") {
            $adminAction = 11;
        } elseif ($user['step'] == "get_price_codesell") {
            $adminAction = 12;
        } elseif ($user['step'] == "getlimitcode") {
            $adminAction = 13;
        } elseif ($user['step'] == "gettypecodeagent") {
            $adminAction = 14;
        } elseif ($user['step'] == "gettimediscount") {
            $adminAction = 15;
        } elseif (preg_match('/discountlimitbuy_(\w+)/', $datain, $dataget)) {
            $adminAction = 16;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\AccountFlagsAndDiscountStart::handle(
            ManagePanel: $ManagePanel ?? null,
            Response: $Response,
            _toggle_notice: $_toggle_notice ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            info_product: $info_product,
            keyboard: $keyboard ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            otherservice: $otherservice ?? null,
            setting: $setting ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            text: $text,
            text_inline: $text_inline ?? null,
            textbotlang: $textbotlang ?? null,
            username: $username
        );
    }
}
