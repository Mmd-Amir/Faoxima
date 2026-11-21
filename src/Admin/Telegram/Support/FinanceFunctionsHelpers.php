<?php

namespace Faoxima\Admin\Telegram\Support;

use PDO;

final class FinanceFunctionsHelpers
{
    public static function rxAdminAffiliateReportData(PDO $pdo): array
    {
        $rxTotalCommissionStmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE category = 'affiliate_commission' AND direction = 'credit'");
        $rxTotalCommission = number_format((float) $rxTotalCommissionStmt->fetchColumn());
        $rxTotalStartGiftStmt = $pdo->query("SELECT COUNT(*) FROM reagent_report WHERE get_gift = 1");
        $rxTotalStartGiftClaims = (int) $rxTotalStartGiftStmt->fetchColumn();
        $rxTotalReferredStmt = $pdo->query("SELECT COUNT(*) FROM user WHERE affiliates IS NOT NULL AND affiliates != '' AND affiliates != '0'");
        $rxTotalReferredUsers = (int) $rxTotalReferredStmt->fetchColumn();
        $rxTopReferrersStmt = $pdo->query("SELECT wl.id_user, COALESCE(NULLIF(MAX(u.username),''), wl.id_user) AS uname, COALESCE(SUM(wl.amount),0) AS total_commission, COUNT(*) AS payouts FROM wallet_ledger wl LEFT JOIN user u ON u.id = wl.id_user WHERE wl.category = 'affiliate_commission' AND wl.direction = 'credit' GROUP BY wl.id_user ORDER BY total_commission DESC LIMIT 10");
        $rxTopReferrers = $rxTopReferrersStmt->fetchAll(PDO::FETCH_ASSOC);
        return [$rxTotalCommission, $rxTotalStartGiftClaims, $rxTotalReferredUsers, $rxTopReferrers];
    }

    public static function rxAdminWalletLimitValue($raw, string $default, string $group, $value)
    {
        $limits = json_decode($raw, true);
        if (!is_array($limits)) {
            $limits = ['n' => $default, 'n2' => $default, 'f' => $default];
        }
        if ($group === 'allusers') {
            foreach (['n', 'n2', 'f'] as $agent) {
                $limits[$agent] = $value;
            }
        } else {
            $limits[$group] = $value;
        }
        return json_encode($limits);
    }
}
