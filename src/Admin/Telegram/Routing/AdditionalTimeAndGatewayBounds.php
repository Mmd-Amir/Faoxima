<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $configedit, $datain, $from_id, $json_list_marzban_panel, $keyboardadmin, $message_id, $optionManualsale, $pdo, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $grp, $panel, $product, $products, $result, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AdditionalTimeAndGatewayBounds
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $configedit,
        &$dataget,
        $datain,
        $from_id,
        &$grp,
        $json_list_marzban_panel,
        $keyboardadmin,
        $message_id,
        $optionManualsale,
        &$panel,
        $pdo,
        &$product,
        &$products,
        &$result,
        $shopkeyboard,
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

        if ($text == "⏳ قیمت زمان اضافه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "GetPricetimeextra") {
            $adminAction = 2;
        } elseif ($user['step'] == "gettypeextratime") {
            $adminAction = 3;
        } elseif ($text == "🔒 کارت پس از اولین پرداخت" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($datain == "onpayverify") {
            $adminAction = 5;
        } elseif ($datain == "offpayverify") {
            $adminAction = 6;
        } elseif ($text == "✏️ ویرایش کانفیگ") {
            $adminAction = 7;
        } elseif ($user['step'] == "getnameedit") {
            $adminAction = 8;
        } elseif ($text == "مخشصات کانفیگ") {
            $adminAction = 9;
        } elseif ($user['step'] == "getcontentedit") {
            $adminAction = 10;
        } elseif ($text == "⬆️ افزایش قیمت") {
            $adminAction = 11;
        } elseif ($user['step'] == "getaddpricepeoductloc") {
            $adminAction = 12;
        } elseif ($user['step'] == "getagentaddpriceproduct") {
            $adminAction = 13;
        } elseif (preg_match('/^typeaddprice_(\w+)/', $datain, $dataget)) {
            $adminAction = 14;
        } elseif ($user['step'] == "getaddpricepeoduct") {
            $adminAction = 15;
        } elseif ($text == "⬇️ کاهش گروهی قیمت") {
            $adminAction = 16;
        } elseif ($user['step'] == "getlowpricepeoductloc") {
            $adminAction = 17;
        } elseif ($user['step'] == "getkampricepeoductloc") {
            $adminAction = 18;
        } elseif ($user['step'] == "getkampricepeoduct") {
            $adminAction = 19;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\AdditionalTimeAndGatewayBounds::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            configedit: $configedit ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            grp: $grp,
            json_list_marzban_panel: $json_list_marzban_panel ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            optionManualsale: $optionManualsale ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            product: $product,
            products: $products,
            result: $result,
            shopkeyboard: $shopkeyboard ?? null,
            stmt: $stmt,
            text: $text,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
