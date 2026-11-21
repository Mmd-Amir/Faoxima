<?php

namespace Faoxima\Telegram\Conversation;

/**
 * Handler stage. Inherited by value: none.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Initialize
{
    public static function handle(
        
    ) {
        if (!defined('RX_MIN_TRANSFER_AMOUNT')) {
            define('RX_MIN_TRANSFER_AMOUNT', 1000);
        }


        return true;
    }
}
