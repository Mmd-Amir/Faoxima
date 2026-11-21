<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $datain, $from_id, $message_id, $text, $textbotlang.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $setting, $value, $valuenew.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ShopFeaturesAndPaymentLimits
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        &$dataget,
        $datain,
        $from_id,
        $message_id,
        &$setting,
        $text,
        $textbotlang,
        &$value,
        &$valuenew
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($text == "🛒 وضعیت قابلیت های فروشگاه" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (preg_match('/^editshops-(.*)-(.*)/', $datain, $dataget)) {
            $adminAction = 2;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ShopFeaturesAndPaymentLimits::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            message_id: $message_id ?? null,
            setting: $setting,
            textbotlang: $textbotlang ?? null,
            value: $value,
            valuenew: $valuenew
        );
    }
}
