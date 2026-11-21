<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $atlaspay, $atlaspayMode, $backadmin, $callback_query_id, $caption, $datain, $domainhosts, $from_id, $pdo, $photo, $photoid, $rialit, $text, $textbotlang, $user, $video, $videoid.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $data, $list, $result, $url.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RialitAndGatewayCredentials
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $atlaspay,
        $atlaspayMode,
        $backadmin,
        $callback_query_id,
        $caption,
        &$data,
        $datain,
        $domainhosts,
        $from_id,
        &$list,
        $pdo,
        $photo,
        $photoid,
        &$result,
        $rialit,
        $text,
        $textbotlang,
        &$url,
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

        if ($text == "🔑 ثبت API Key ریالیت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "apirialit") {
            $adminAction = 2;
        } elseif ($text == "🏷️ نام نمایشی درگاه ریالیت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == "gettextrialit") {
            $adminAction = 4;
        } elseif ($text == "💰 کش بک ریالیت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "getcashrialit") {
            $adminAction = 6;
        } elseif ($text == "⬇️ کف ریالیت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "getmainrialit") {
            $adminAction = 8;
        } elseif ($text == "⬆️ سقف ریالیت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "getmaxrialit") {
            $adminAction = 10;
        } elseif ($text == "🌐 آدرس وب‌هوک ریالیت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($datain == "rialit_whstatus" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($datain == "rialit_whread" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($datain == "rialit_whregister" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($text == "🛡 آدرس‌های IP مجاز وب‌هوک" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        } elseif ($user['step'] == "rialit_trustips") {
            $adminAction = 16;
        } elseif ($text == "🧩 پراکسی‌های مورد اعتماد" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 17;
        } elseif ($user['step'] == "rialit_trustproxies") {
            $adminAction = 18;
        } elseif ($text == "📚 آموزش ریالیت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 19;
        } elseif ($user['step'] == "helprialit") {
            $adminAction = 20;
        } elseif (($text == "🔀 مقصد پرداخت اطلس‌پی" || preg_match('/^atlaspay_paymode_(official|custom)$/', (string) $datain, $atlaspayModeMatch)) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 21;
        } elseif ($text == "🔑 ثبت API Key اطلس‌پی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 22;
        } elseif ($user['step'] == "apiatlaspay") {
            $adminAction = 23;
        } elseif ($text == "🔑 ثبت API Key بلوپال" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 24;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\RialitAndGatewayCredentials::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            atlaspay: $atlaspay ?? null,
            atlaspayMode: $atlaspayMode ?? null,
            atlaspayModeMatch: $atlaspayModeMatch ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            caption: $caption ?? null,
            data: $data,
            domainhosts: $domainhosts ?? null,
            from_id: $from_id ?? null,
            list: $list,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            result: $result,
            rialit: $rialit ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            url: $url,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
