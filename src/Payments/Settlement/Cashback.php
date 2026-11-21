<?php

namespace Faoxima\Payments\Settlement;

final class Cashback
{
    public static function applyCashback($runtimePdo, array $report, array $balanceUser, string $cashbackKey): int
    {
        $cashbackPercent = '0';
        if ($cashbackKey !== '') {
            $row = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $cashbackKey, 'select');
            if (is_array($row)) {
                $cashbackPercent = (string)($row['ValuePay'] ?? '0');
            }
        }

        if ($cashbackPercent === '' || $cashbackPercent === '0'
            || !\Faoxima\Cashback\Eligibility::cashbackEligibleForKey(
                $runtimePdo, $cashbackKey, $balanceUser['register'] ?? null, $report['id_invoice'] ?? null,
                $balanceUser['id'] ?? null, $report['id_order'] ?? null
            )) {
            return 0;
        }

        $cashbackAmount = (int) floor(((int) ($report['price'] ?? 0) * (int) $cashbackPercent) / 100);
        return \Faoxima\Cashback\Credits::creditOnce($runtimePdo, $report['id_order'], $balanceUser['id'], $cashbackAmount,
            $cashbackKey, 'هدیه بازگشت وجه ' . $cashbackKey) === 'credited' ? $cashbackAmount : 0;
    }
}
