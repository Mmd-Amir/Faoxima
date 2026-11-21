<?php

namespace Faoxima\Telegram\Routing;

final class CompletionRoutes
{
    public static function finalization($update, $datain, &$dataget): ?string
    {
        return match (true) {
            isset($update['pre_checkout_query']) => 'stars_pre_checkout',
            (bool) preg_match('/extends_(\w+)_(.*)/', $datain, $dataget) => 'renewal_start',
            (bool) preg_match('/^serviceextendselects-(.*)-(.*)/', $datain, $dataget) => 'renewal_product',
            (bool) preg_match('/^confirmserivces-(.*)-(.*)/', $datain, $dataget) => 'renewal_confirm',
            default => null,
        };
    }
}
