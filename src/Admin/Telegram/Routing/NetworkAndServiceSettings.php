<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $from_id, $keyboardadmin, $optionManualsale, $pdo, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $button, $json_list_product_list_admin, $panel, $product, $row, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class NetworkAndServiceSettings
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        &$button,
        $from_id,
        &$json_list_product_list_admin,
        $keyboardadmin,
        $optionManualsale,
        &$panel,
        $pdo,
        &$product,
        &$row,
        &$stmt,
        &$text,
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

        if ($text == "🌐 وضعیت نت ملی" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($text == "🔄 تغییر نوع پنل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($text == "➕ افزودن کانفیگ") {
            $adminAction = 3;
        } elseif ($user['step'] == "getnameconfigm") {
            $adminAction = 4;
        } elseif ($user['step'] == "getnameproduct") {
            $adminAction = 5;
        } elseif ($user['step'] == "getconfigtext") {
            $adminAction = 6;
        } elseif (trim($text) == "❌ حذف کانفیگ") {
            $adminAction = 7;
        } elseif ($user['step'] == "getnameremove") {
            $adminAction = 8;
        } elseif ($text == "🌍 قیمت تغییر مکان" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($user['step'] == "setpricechangelocation") {
            $adminAction = 10;
        } elseif ($text == "➕ قیمت حجم اضافه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($user['step'] == "GetPriceExtra") {
            $adminAction = 12;
        } elseif ($user['step'] == "gettypeextra") {
            $adminAction = 13;
        } elseif ($text == "⚙️ قیمت حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($user['step'] == "GetPricecustomvo") {
            $adminAction = 15;
        } elseif ($user['step'] == "gettypeextracustom") {
            $adminAction = 16;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\NetworkAndServiceSettings::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            button: $button,
            from_id: $from_id ?? null,
            json_list_product_list_admin: $json_list_product_list_admin,
            keyboardadmin: $keyboardadmin ?? null,
            optionManualsale: $optionManualsale ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            product: $product,
            row: $row,
            stmt: $stmt,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
