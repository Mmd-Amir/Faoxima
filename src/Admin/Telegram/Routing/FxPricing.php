<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class FxPricing
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminRouteMatched = false;
        $adminAction = null;

        if (is_callable([\Faoxima\Orders\Pricing\ExchangePricing::class, 'fxAdminButtonLabel']) && $text == \Faoxima\Orders\Pricing\ExchangePricing::fxAdminButtonLabel() && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (is_string($datain) && strpos($datain, 'fxp_') === 0 && is_callable([\Faoxima\Orders\Pricing\ExchangePricing::class, 'fxAdminMenuRender']) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($user['step'] == 'fxp_input' && empty($datain) && isset($update['message']) && is_callable([\Faoxima\Orders\Pricing\ExchangePricing::class, 'fxAdminMenuRender']) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\FxPricing::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null
        );
    }
}
