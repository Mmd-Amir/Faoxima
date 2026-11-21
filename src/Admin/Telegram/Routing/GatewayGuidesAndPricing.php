<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $abangateway, $adminExecution, $adminrulecheck, $atlaspay, $backadmin, $blupal, $caption, $cubepay, $from_id, $photo, $photoid, $text, $user, $variza, $video, $videoid.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class GatewayGuidesAndPricing
{
    public static function handle(
        $CartManage,
        $abangateway,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $atlaspay,
        $backadmin,
        $blupal,
        $caption,
        $cubepay,
        &$data,
        $from_id,
        $photo,
        $photoid,
        $text,
        $user,
        $variza,
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

        if ($user['step'] == "helpblupal") {
            $adminAction = 1;
        } elseif ($text == "📚 آموزش واریزا" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == "helpvariza") {
            $adminAction = 3;
        } elseif ($text == "📚 آموزش آبان گیت وی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($user['step'] == "helpabangateway") {
            $adminAction = 5;
        } elseif ($text == "📚 آموزش اطلس‌پی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 6;
        } elseif ($user['step'] == "helpatlaspay") {
            $adminAction = 7;
        } elseif ($text == "📚 آموزش کیوب‌پی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($user['step'] == "helpcubepay") {
            $adminAction = 9;
        } elseif ($text == "📚 آموزش زرین پال" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($user['step'] == "helpzarinpal") {
            $adminAction = 11;
        } elseif ($text == "📚 آموزش  ارزی افلاین" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($user['step'] == "helpofflinearze") {
            $adminAction = 13;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\GatewayGuidesAndPricing::handle(
            CartManage: $CartManage ?? null,
            abangateway: $abangateway ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            atlaspay: $atlaspay ?? null,
            backadmin: $backadmin ?? null,
            blupal: $blupal ?? null,
            caption: $caption ?? null,
            cubepay: $cubepay ?? null,
            data: $data,
            from_id: $from_id ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            text: $text ?? null,
            variza: $variza ?? null,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
