<?php

namespace Faoxima\Telegram\Keyboard;

/**
 * Handler stage. Inherited by value: none.
 * Inherited or exported by reference: $keyboard_buy.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PurchaseButton
{
    public static function handle(
        &$keyboard_buy
    ) {
        $keyboard_buy = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🛍خرید اشتراک", 'callback_data' => 'buy'],
                    ],
                ]
            ]);

        return 1;
    }
}
