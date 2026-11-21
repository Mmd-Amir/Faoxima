<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $ManagePanel, $adminExecution, $adminrulecheck, $backadmin, $datain, $first_name, $from_id, $keyboardadmin, $otherreport, $pdo, $setting, $shopkeyboard, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $datagetr, $dataoutput, $filename, $id_invoice, $id_user, $list, $listusers, $marzban_list_get, $message_id, $nameloc, $stmt, $userdate, $userslist.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BulkGiftExecutionAndExports
{
    public static function handle(
        $CartManage,
        $ManagePanel,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$dataget,
        &$datagetr,
        $datain,
        &$dataoutput,
        &$filename,
        $first_name,
        $from_id,
        &$id_invoice,
        &$id_user,
        $keyboardadmin,
        &$list,
        &$listusers,
        &$marzban_list_get,
        &$message_id,
        &$nameloc,
        $otherreport,
        $pdo,
        $setting,
        $shopkeyboard,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        &$userdate,
        &$userslist
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "startgift") {
            $adminAction = 1;
        } elseif ($datain == "cancel_gift") {
            $adminAction = 2;
        } elseif (preg_match('/expireset_(\w+)/', $datain, $datagetr)) {
            $adminAction = 3;
        } elseif ($user['step'] == "gettime_expire_agent") {
            $adminAction = 4;
        } elseif ($text == "♻️ نمایش گروهی شماره کارت") {
            $adminAction = 5;
        } elseif ($user['step'] == "getlistidcart") {
            $adminAction = 6;
        } elseif ($text == "📄 خروجی شماره کارت فعال") {
            $adminAction = 7;
        } elseif ($text == "🎉 پورسانت فقط برای خرید اول" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($datain == "on_buy_porsant") {
            $adminAction = 9;
        } elseif ($datain == "off_buy_porsant") {
            $adminAction = 10;
        } elseif (preg_match('/changestatusadmin_(\w+)/', $datain, $dataget)) {
            $adminAction = 11;
        } elseif (preg_match('/confirmaccountdisableadmin_(\w+)/', $datain, $dataget)) {
            $adminAction = 12;
        } elseif (preg_match('/removefull-(.*)/', $datain, $dataget)) {
            $adminAction = 13;
        } elseif (preg_match('/confirmremovefulls-(.*)/', $datain, $dataget)) {
            $adminAction = 14;
        } elseif ($text == "🛒 افزودن دسته‌بندی") {
            $adminAction = 15;
        } elseif ($user['step'] == "getremarkcategory") {
            $adminAction = 16;
        } elseif ($text == "❌ حذف دسته بندی") {
            $adminAction = 17;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\BulkGiftExecutionAndExports::handle(
            CartManage: $CartManage ?? null,
            ManagePanel: $ManagePanel ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            dataget: $dataget ?? null,
            datagetr: $datagetr ?? null,
            dataoutput: $dataoutput,
            filename: $filename,
            first_name: $first_name ?? null,
            from_id: $from_id ?? null,
            id_invoice: $id_invoice,
            id_user: $id_user,
            keyboardadmin: $keyboardadmin ?? null,
            list: $list,
            listusers: $listusers,
            marzban_list_get: $marzban_list_get,
            message_id: $message_id,
            nameloc: $nameloc,
            otherreport: $otherreport ?? null,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            userdate: $userdate,
            userslist: $userslist
        );
    }
}
