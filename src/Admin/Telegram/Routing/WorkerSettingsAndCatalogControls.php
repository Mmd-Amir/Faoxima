<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $adminExecution, $admin_ids, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $helpedit, $json_list_helpkey, $keyboardadmin, $message_id, $pdo, $photo, $photoid, $setting, $setting_panel, $text, $textbotlang, $update, $video, $videoid.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WorkerSettingsAndCatalogControls
{
    public static function handle(
        $CartManage,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $admin_ids,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $helpedit,
        $json_list_helpkey,
        $keyboardadmin,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $setting,
        $setting_panel,
        $text,
        $textbotlang,
        $update,
        &$user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminRouteMatched = false;
        $adminAction = null;

        if ($datain == "settimecornremove" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "getdaycron") {
            $adminAction = 2;
        } elseif ($text == "✏️ ویرایش آموزش" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif ($user['step'] == "getnameforedite") {
            $adminAction = 4;
        } elseif ($text == "ویرایش نام آموزش" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($user['step'] == "changenamehelp") {
            $adminAction = 6;
        } elseif ($text == "ویرایش دسته بندی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($user['step'] == "changecategoryhelp") {
            $adminAction = 8;
        } elseif ($text == "ویرایش توضیحات" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "changedeshelp") {
            $adminAction = 10;
        } elseif ($text == "ویرایش رسانه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($user['step'] == "changemedia") {
            $adminAction = 12;
        } elseif ($text == "ویرایش لینک برنامه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($user['step'] == "changeapptitlehelp") {
            $adminAction = 14;
        } elseif ($user['step'] == "changeapplinkhelp") {
            $adminAction = 15;
        } elseif ($text == "💰 غیرفعال کارت" || $datain == "cart_hide_num") {
            $adminAction = 16;
        } elseif ($user['step'] == "showcardallusers") {
            $adminAction = 17;
        } elseif ($text == "💰 فعال شماره کارت" || $datain == "cart_show_num") {
            $adminAction = 18;
        } elseif (is_string($datain ?? null) && strpos($datain, 'rsva:') === 0) {
            $adminAction = 19;
        } elseif (is_string($user['step'] ?? null) && strpos((string) $user['step'], 'rsvadate|') === 0) {
            $adminAction = 20;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\WorkerSettingsAndCatalogControls::handle(
            CartManage: $CartManage ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            admin_ids: $admin_ids ?? null,
            adminrulecheck: $adminrulecheck ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            helpedit: $helpedit ?? null,
            json_list_helpkey: $json_list_helpkey ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            setting: $setting ?? null,
            setting_panel: $setting_panel ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
