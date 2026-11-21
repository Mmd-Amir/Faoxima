<?php

namespace Faoxima\Admin\Telegram\Support;

final class PricingInputHelpers
{
    public static function rxAdminPricingInputStatus(string $rxFxAction, $rxFxValue): string
    {
        $rxFxRules = [
            'base'   => [0.0001, 1000000000.0, false],
            'markup' => [-90.0, 1000.0, true],
            'round'  => [1.0, 1000000.0, false],
            'stale'  => [5.0, 10080.0, false],
            'jump'   => [1.0, 100.0, true],
            'manual' => [0.0001, 1000000000.0, false],
            'test'   => [0.0001, 100000000000.0, false],
        ];
        if (!isset($rxFxRules[$rxFxAction])) {
            return 'unknown_action';
        }
        [$rxFxMin, $rxFxMax, $rxFxAllowFraction] = $rxFxRules[$rxFxAction];
        return ($rxFxValue === null || $rxFxValue < $rxFxMin || $rxFxValue > $rxFxMax || (!$rxFxAllowFraction && in_array($rxFxAction, ['round', 'stale'], true) && floor($rxFxValue) != $rxFxValue)) ? 'invalid_value' : 'valid';
    }
}
