<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $adminExecution, $adminrulecheck, $backadmin, $caption, $from_id, $nowpayment_setting_keyboard, $photo, $photoid, $supportcenter, $text, $textbotlang, $tonpay, $user, $video, $videoid.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class SupportAndGatewayGuides
{
    public static function handle(
        $CartManage,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $caption,
        &$data,
        $from_id,
        $nowpayment_setting_keyboard,
        $photo,
        $photoid,
        $supportcenter,
        $text,
        $textbotlang,
        $tonpay,
        $user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "idsupportset") {
            $adminAction = 1;
        } elseif ($text == "📚 آموزش کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == "gethelpcart") {
            $adminAction = 3;
        } elseif ($text == "📚 آموزش nowpayment" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($user['step'] == "gethelpnowpayment") {
            $adminAction = 5;
        } elseif ($text == "📚 آموزش پرفکت مانی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "gethelpperfect") {
            $adminAction = 7;
        } elseif ($text == "📚 آموزش plisio" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "gethelpplisio") {
            $adminAction = 9;
        } elseif ($text == "📚 آموزش ترونادو" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "helpiranpay2") {
            $adminAction = 11;
        } elseif ($text == "📚 آموزش تون‌پی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($user['step'] == "helptonpay") {
            $adminAction = 13;
        } elseif ($text == "📚 آموزش بلوپال" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\SupportAndGatewayGuides::handle(
            CartManage: $CartManage ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            caption: $caption ?? null,
            data: $data,
            from_id: $from_id ?? null,
            nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            supportcenter: $supportcenter ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            tonpay: $tonpay ?? null,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
