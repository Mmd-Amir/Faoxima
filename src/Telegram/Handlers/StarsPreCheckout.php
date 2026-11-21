<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $telegramRoute, $update.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class StarsPreCheckout
{
    public static function handle(
        $telegramRoute,
        $update
    ) {
        if ($telegramRoute === 'stars_pre_checkout') {
            \Faoxima\Payments\Gateways\Stars\Settlement::starsPreCheckoutV2($update['pre_checkout_query']);
            return;
        }

        return true;
    }
}
