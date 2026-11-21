<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $connect, $datain, $domainhosts, $from_id, $json_list_marzban_panel, $keyboard_Category_manage, $keyboard_shop_manage, $message_id, $pdo, $shopkeyboard, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $id_user, $next_page, $page, $result, $row.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CategoriesAndServiceMaintenance
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $connect,
        &$dataget,
        $datain,
        $domainhosts,
        $from_id,
        &$id_user,
        $json_list_marzban_panel,
        $keyboard_Category_manage,
        $keyboard_shop_manage,
        $message_id,
        &$next_page,
        &$page,
        $pdo,
        &$result,
        &$row,
        $shopkeyboard,
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

        if ($user['step'] == "removecategory") {
            $adminAction = 1;
        } elseif ($text == "مخفی کردن پنل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($text == "/end_hide") {
            $adminAction = 3;
        } elseif ($user['step'] == "getlistpanel") {
            $adminAction = 4;
        } elseif ($text == "حذف کلی پنل های مخفی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($text == "🔗 وبهوک ربات‌های نماینده") {
            $adminAction = 6;
        } elseif (preg_match('/statuscronuser-(.*)/', $datain, $dataget)) {
            $adminAction = 7;
        } elseif ($text == "🗂 مدیریت دسته‌بندی") {
            $adminAction = 8;
        } elseif ($text == "⬅️ بازگشت به منوی فروشگاه") {
            $adminAction = 9;
        } elseif ($text == "🛍 مدیریت محصولات" || $datain == "backproductadmin") {
            $adminAction = 10;
        } elseif ($text == "✏️ ویرایش دسته بندی") {
            $adminAction = 11;
        } elseif ($user['step'] == "editcategory_name") {
            $adminAction = 12;
        } elseif ($user['step'] == "get_name_new_category") {
            $adminAction = 13;
        } elseif ($datain == "zerobalance") {
            $adminAction = 14;
        } elseif ($datain == 'next_pageuserzero') {
            $adminAction = 15;
        } elseif ($datain == 'previous_pageuserzero') {
            $adminAction = 16;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\CategoriesAndServiceMaintenance::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            connect: $connect ?? null,
            dataget: $dataget ?? null,
            domainhosts: $domainhosts ?? null,
            from_id: $from_id ?? null,
            id_user: $id_user,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            keyboard_Category_manage: $keyboard_Category_manage ?? null,
            keyboard_shop_manage: $keyboard_shop_manage ?? null,
            message_id: $message_id ?? null,
            next_page: $next_page,
            page: $page,
            pdo: $pdo ?? null,
            result: $result,
            row: $row,
            shopkeyboard: $shopkeyboard ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
