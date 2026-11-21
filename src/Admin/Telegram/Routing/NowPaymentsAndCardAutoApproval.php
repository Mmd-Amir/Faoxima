<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $CartManage, $Exception_auto_cart_keyboard, $Trust_auto_cart_keyboard, $adminExecution, $autoconfirm_advanced_keyboard, $backadmin, $datain, $from_id, $message_id, $nowpayment_setting_keyboard, $pdo, $setting, $text, $textbotlang, $user.
 * Inherited or exported by reference: $Response, $adminAction, $adminRouteMatched, $code_product, $dataget, $panel, $product, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class NowPaymentsAndCardAutoApproval
{
    public static function handle(
        $CartManage,
        $Exception_auto_cart_keyboard,
        &$Response,
        $Trust_auto_cart_keyboard,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $autoconfirm_advanced_keyboard,
        $backadmin,
        &$code_product,
        &$dataget,
        $datain,
        $from_id,
        $message_id,
        $nowpayment_setting_keyboard,
        &$panel,
        $pdo,
        &$product,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "nowpaymentsetting") {
            $adminAction = 1;
        } elseif ($text == "⏳ زمان تایید خودکار") {
            $adminAction = 2;
        } elseif ($user['step'] == "gettimeauto") {
            $adminAction = 3;
        } elseif ($text == "نمایش برای خرید اول") {
            $adminAction = 4;
        } elseif (preg_match('/status_on_buy-(.*)-(.*)/', $datain, $dataget)) {
            $adminAction = 5;
        } elseif ($text == "⚙️ تنظیمات تایید خودکار") {
            $adminAction = 6;
        } elseif ($text == "◀️ بازگشت به تنظیمات پیشرفته") {
            $adminAction = 7;
        } elseif ($text == "🚫 استثناء کاربران") {
            $adminAction = 8;
        } elseif ($text == "➕ استثناء کردن کاربر") {
            $adminAction = 9;
        } elseif ($user['step'] == "getidExceptio") {
            $adminAction = 10;
        } elseif ($text == "❌ حذف کاربر از لیست") {
            $adminAction = 11;
        } elseif ($user['step'] == "getidExceptioremove") {
            $adminAction = 12;
        } elseif ($text == "👁 نمایش لیست استثناء") {
            $adminAction = 13;
        } elseif ($text == "✅ کاربران معتمد" || $text == "🔁 وضعیت حالت معتمدین") {
            $adminAction = 14;
        } elseif ($text == "➕ افزودن به معتمدین") {
            $adminAction = 15;
        } elseif ($user['step'] == "getidTrustAdd") {
            $adminAction = 16;
        } elseif ($text == "❌ حذف از معتمدین") {
            $adminAction = 17;
        } elseif ($user['step'] == "getidTrustRemove") {
            $adminAction = 18;
        } elseif ($text == "👁 نمایش لیست معتمدین") {
            $adminAction = 19;
        } elseif ($text == "🟢 فعال کردن حالت معتمدین" || $text == "🔴 غیرفعال کردن حالت معتمدین") {
            $adminAction = 20;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\NowPaymentsAndCardAutoApproval::handle(
            CartManage: $CartManage ?? null,
            Exception_auto_cart_keyboard: $Exception_auto_cart_keyboard ?? null,
            Response: $Response,
            Trust_auto_cart_keyboard: $Trust_auto_cart_keyboard ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            autoconfirm_advanced_keyboard: $autoconfirm_advanced_keyboard ?? null,
            backadmin: $backadmin ?? null,
            code_product: $code_product,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            message_id: $message_id ?? null,
            nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            product: $product,
            setting: $setting ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null
        );
    }
}
