<?php

namespace Faoxima\Cashback;

final class ChargeBonus
{
    public static function pendingChargeBonus($user)
    {
        $pv4 = (string)($user['Processing_value_four'] ?? '');
        if (strpos($pv4, 'chg|') !== 0) return 0;
        $parts = explode('|', $pv4);
        $bonus = isset($parts[1]) ? intval($parts[1]) : 0;
        return max(0, $bonus);
    }
}
