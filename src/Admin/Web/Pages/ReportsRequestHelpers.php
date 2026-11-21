<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ReportsRequestHelpers
{
    public static function fx_admin_report_data(PDO $pdo): array
{
    $nowTs    = time();
    $dayAgoTs = $nowTs - 86400;

    $incomeToday  = 0.0;
    $totalIncome  = 0.0;
    $totalOrders  = 0;
    $totalUsers   = 0;
    $avgOrder     = 0.0;
    $prodLabels   = [];
    $prodData     = [];
    $groupRows    = [];
    $topBuyers    = [];
    $topActive    = [];
    $topDeposit   = [];
    $topBalance   = [];
    $discountTotal   = 0.0;
    $discountCount   = 0;
    $discountTopCodes = [];

    try {
        $jToday = explode('-', jdate('Y-n-j', $nowTs, '', 'Asia/Tehran', 'en'));
        $todayStart = (int)jmktime(0, 0, 0, (int)($jToday[1] ?? 1), (int)($jToday[2] ?? 1), (int)($jToday[0] ?? 1400));
        $st = $pdo->prepare("SELECT COALESCE(SUM(price_product),0) FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE time_sell >= :a AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate());
        $st->bindValue(':a', (int)$todayStart, PDO::PARAM_INT); $st->execute();
        $incomeToday = (float)$st->fetchColumn();

        $totalIncome = \Faoxima\Admin\Reporting\Statistics::rx_stats_total_sales_amount($pdo);
        $totalOrders = \Faoxima\Admin\Reporting\Statistics::rx_stats_total_sales_count($pdo);
        $totalUsers  = \Faoxima\Admin\Reporting\Statistics::rx_stats_total_users($pdo);
        $avg30Start = $nowTs - 30 * 86400;
        $st30 = $pdo->prepare("SELECT COALESCE(SUM(price_product),0), COUNT(*) FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE time_sell >= :a AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate());
        $st30->bindValue(':a', (int)$avg30Start, PDO::PARAM_INT); $st30->execute();
        $row30 = $st30->fetch(PDO::FETCH_NUM);
        $ord30 = (int)($row30[1] ?? 0);
        $avgOrder = $ord30 > 0 ? ((float)($row30[0] ?? 0) / $ord30) : 0.0;
    } catch (\Throwable $e) { error_log('[reports] cards: ' . $e->getMessage()); }

    try {
        $prod30Start = $nowTs - 30 * 86400;
        $prodTopN = 8;
        $st = $pdo->prepare("SELECT name_product, COUNT(*) AS total FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE time_sell >= :a AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate() . " GROUP BY name_product ORDER BY total DESC");
        $st->bindValue(':a', (int)$prod30Start, PDO::PARAM_INT); $st->execute();
        $prodRows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ((int)$r['total'] <= 0) continue;
            $prodRows[] = ['name' => (string)$r['name_product'], 'total' => (int)$r['total']];
        }
        $prodOtherCount = 0;
        $prodOtherSales = 0;
        if (count($prodRows) > $prodTopN) {
            $tail = array_slice($prodRows, $prodTopN - 1);
            $prodRows = array_slice($prodRows, 0, $prodTopN - 1);
            $prodOtherCount = count($tail);
            foreach ($tail as $t) { $prodOtherSales += $t['total']; }
        }
        foreach ($prodRows as $r) {
            $prodLabels[] = $r['name'];
            $prodData[]   = $r['total'];
        }
        if ($prodOtherCount > 0) {
            $prodLabels[] = 'سایر محصولات (' . $prodOtherCount . ')';
            $prodData[]   = $prodOtherSales;
        }
    } catch (\Throwable $e) { error_log('[reports] products: ' . $e->getMessage()); }

    $validSale = \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate();

    $validSaleI = \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate('i');

    $purchaseMarkers = "(id_invoice IS NULL OR id_invoice = '' OR id_invoice = 'none' OR (id_invoice NOT LIKE 'getconfigafterpay%' AND id_invoice NOT LIKE 'getextenduser%' AND id_invoice NOT LIKE 'getextravolumeuser%' AND id_invoice NOT LIKE 'getextratimeuser%'))";

    $realPaymentPredicate = \Faoxima\Admin\Reporting\Statistics::rx_stats_real_payment_predicate('p');

    try {
        $st = $pdo->query("SELECT CASE WHEN a.id_admin IS NOT NULL THEN 'admin' WHEN u.agent = 'n2' THEN 'n2' WHEN u.agent = 'n' THEN 'n' ELSE 'f' END AS grp, COUNT(*) AS users FROM user u LEFT JOIN admin a ON a.id_admin = u.id GROUP BY grp");
        $groupUsers = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $groupUsers[(string)$r['grp']] = (int)$r['users']; }

        $st = $pdo->query("SELECT CASE WHEN a.id_admin IS NOT NULL THEN 'admin' WHEN u.agent = 'n2' THEN 'n2' WHEN u.agent = 'n' THEN 'n' ELSE 'f' END AS grp, COALESCE(SUM(i.price_product),0) AS revenue, COUNT(i.id_invoice) AS orders FROM user u LEFT JOIN admin a ON a.id_admin = u.id LEFT JOIN " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " i ON i.id_user = u.id AND {$validSaleI} GROUP BY grp");
        $groupSales = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $groupSales[(string)$r['grp']] = ['revenue' => (float)$r['revenue'], 'orders' => (int)$r['orders']]; }

        $groupNames = ['f' => 'کاربران عادی', 'n' => 'نمایندگان', 'n2' => 'نمایندگان ویژه', 'admin' => 'مدیران'];
        foreach ($groupNames as $key => $label) {
            $groupRows[] = [
                'label'   => $label,
                'users'   => (int)($groupUsers[$key] ?? 0),
                'orders'  => (int)($groupSales[$key]['orders'] ?? 0),
                'revenue' => (float)($groupSales[$key]['revenue'] ?? 0),
            ];
        }
    } catch (\Throwable $e) { error_log('[reports] groups: ' . $e->getMessage()); }

    try {
        $st = $pdo->query("SELECT i.id_user, COALESCE(NULLIF(MAX(u.username),''), i.id_user) AS uname, COALESCE(SUM(i.price_product),0) AS revenue, COUNT(*) AS orders FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " i LEFT JOIN user u ON u.id = i.id_user WHERE {$validSaleI} GROUP BY i.id_user ORDER BY revenue DESC LIMIT 10");
        $topBuyers = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { error_log('[reports] topbuyers: ' . $e->getMessage()); }

    try {
        $st = $pdo->query("SELECT i.id_user, COALESCE(NULLIF(MAX(u.username),''), i.id_user) AS uname, COUNT(*) AS actives, COALESCE(SUM(i.price_product),0) AS revenue FROM invoice i LEFT JOIN user u ON u.id = i.id_user WHERE i.Status = 'active' AND i.name_product != 'سرویس تست' GROUP BY i.id_user ORDER BY actives DESC LIMIT 10");
        $topActive = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { error_log('[reports] topactive: ' . $e->getMessage()); }

    try {
        $st = $pdo->query("SELECT p.id_user, COALESCE(NULLIF(MAX(u.username),''), p.id_user) AS uname, COALESCE(SUM(p.price),0) AS deposit, COUNT(*) AS cnt FROM Payment_report p LEFT JOIN user u ON u.id = p.id_user WHERE {$realPaymentPredicate} AND {$purchaseMarkers} GROUP BY p.id_user ORDER BY deposit DESC LIMIT 10");
        $topDeposit = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { error_log('[reports] topdeposit: ' . $e->getMessage()); }

    try {
        $st = $pdo->query("SELECT id, COALESCE(NULLIF(username,''), id) AS uname, Balance FROM user WHERE Balance > 0 ORDER BY Balance DESC LIMIT 10");
        $topBalance = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { error_log('[reports] topbalance: ' . $e->getMessage()); }

    try {
        $row = $pdo->query("SELECT COALESCE(SUM(discount_amount),0) AS total, COUNT(*) AS cnt FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE {$validSale} AND discount_amount + 0 > 0")->fetch(PDO::FETCH_ASSOC);
        $discountTotal = (float)($row['total'] ?? 0);
        $discountCount = (int)($row['cnt'] ?? 0);

        $logRow = $pdo->query("SELECT COALESCE(SUM(discount_amount),0) AS total, COUNT(*) AS cnt FROM order_discount_log")->fetch(PDO::FETCH_ASSOC);
        if ((int)($logRow['cnt'] ?? 0) > $discountCount) {
            $discountTotal = (float)($logRow['total'] ?? 0);
            $discountCount = (int)($logRow['cnt'] ?? 0);
        }

        $st = $pdo->query("SELECT code, COALESCE(SUM(discount_amount),0) AS total, COUNT(*) AS uses FROM order_discount_log GROUP BY code ORDER BY total DESC LIMIT 5");
        $discountTopCodes = $st->fetchAll(PDO::FETCH_ASSOC);
        if (empty($discountTopCodes)) {
            $st = $pdo->query("SELECT discount_code AS code, COALESCE(SUM(discount_amount),0) AS total, COUNT(*) AS uses FROM invoice WHERE discount_code IS NOT NULL AND discount_code != '' GROUP BY discount_code ORDER BY total DESC LIMIT 5");
            $discountTopCodes = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($discountCount === 0) {
            $legacy = $pdo->query("SELECT COALESCE(SUM(CAST(limitused AS UNSIGNED)),0) FROM Discount")->fetchColumn();
            $legacySell = $pdo->query("SELECT COALESCE(SUM(CAST(usedDiscount AS UNSIGNED)),0) FROM DiscountSell")->fetchColumn();
            $discountCount = (int)$legacy + (int)$legacySell;
        }
    } catch (\Throwable $e) { error_log('[reports] discounts: ' . $e->getMessage()); }
    return [
        'incomeToday' => $incomeToday,
        'totalIncome' => $totalIncome,
        'totalOrders' => $totalOrders,
        'totalUsers' => $totalUsers,
        'avgOrder' => $avgOrder,
        'prodLabels' => $prodLabels,
        'prodData' => $prodData,
        'groupRows' => $groupRows,
        'topBuyers' => $topBuyers,
        'topActive' => $topActive,
        'topDeposit' => $topDeposit,
        'topBalance' => $topBalance,
        'discountTotal' => $discountTotal,
        'discountCount' => $discountCount,
        'discountTopCodes' => $discountTopCodes,
    ];
}
}
