<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $pdo, $resultcountday.
 * Inherited or exported by reference: $activity, $catData, $catLabels, $dayAgoTs, $incTrend, $latestOrders, $newUsersWeek, $ordTrend, $r, $since24Str, $st, $u24Trend, $weekData, $weekLabels.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DashboardRevenue
{
    public static function handle(
        &$activity,
        $adminWebPage,
        &$catData,
        &$catLabels,
        &$dayAgoTs,
        &$incTrend,
        &$latestOrders,
        &$newUsersWeek,
        &$ordTrend,
        $pdo,
        &$r,
        $resultcountday,
        &$since24Str,
        &$st,
        &$u24Trend,
        &$weekData,
        &$weekLabels
    ) {
        if (($adminWebPage ?? null) !== 'index.php') {
            return;
        }








        $nowTs      = time();
        $weekAgoTs  = $nowTs - 7 * 86400;
        $twoWeekTs  = $nowTs - 14 * 86400;
        $dayAgoTs   = $nowTs - 86400;
        $twoDayTs   = $nowTs - 2 * 86400;
        $weekChartStart = strtotime('today') - (((int)date('w') + 1) % 7) * 86400;
        $since24Str = date('Y/m/d H:i:s', $dayAgoTs);

        $incTrend = 0; $ordTrend = 0; $u24Trend = 0; $newUsersWeek = 0;
        $catLabels = []; $catData = [];
        $weekLabels = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        $weekData = array_fill(0, 7, 0);
        $latestOrders = [];
        $activity = [];

        try {
            $incTrend = \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_pct(\Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_income_between($pdo, $dayAgoTs, $nowTs), \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_income_between($pdo, $twoDayTs, $dayAgoTs));
            $ordTrend = \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_pct((float)\Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_orders_between($pdo, $dayAgoTs, $nowTs), (float)\Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_orders_between($pdo, $twoDayTs, $dayAgoTs));
            $u24Trend = \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_pct((float)$resultcountday, (float)\Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_users_between($pdo, $twoDayTs, $dayAgoTs));
            $newUsersWeek = \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_users_between($pdo, $weekAgoTs, $nowTs);
        } catch (\Throwable $e) { error_log('[dashboard] trends: ' . $e->getMessage()); }

        try {
            $st = $pdo->query("SELECT COALESCE(NULLIF(TRIM(p.category),''),'بدون دسته') AS cat, COALESCE(SUM(i.price_product),0) AS total
        FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " i LEFT JOIN product p ON p.name_product = i.name_product
        WHERE " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate('i') . "
        GROUP BY cat ORDER BY total DESC");
            $catRows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ((float)$r['total'] <= 0) continue;
                $catRows[] = $r;
            }
            if (count($catRows) <= 6) {
                foreach ($catRows as $r) {
                    $catLabels[] = (string)$r['cat'];
                    $catData[]   = (float)$r['total'];
                }
            } else {
                $catOther = 0.0;
                foreach ($catRows as $i => $r) {
                    if ($i < 5) {
                        $catLabels[] = (string)$r['cat'];
                        $catData[]   = (float)$r['total'];
                    } else {
                        $catOther += (float)$r['total'];
                    }
                }
                $catLabels[] = 'سایر';
                $catData[]   = $catOther;
            }
        } catch (\Throwable $e) { error_log('[dashboard] category: ' . $e->getMessage()); }

        try {
            $st = $pdo->prepare("SELECT time_sell FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE time_sell >= :a AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate());
            $st->bindValue(':a', (int)$weekChartStart, PDO::PARAM_INT); $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ts) {
                $t = (int)$ts; if ($t < $weekChartStart) continue;
                $idx = ((int)date('w', $t) + 1) % 7;
                $weekData[$idx]++;
            }
        } catch (\Throwable $e) { error_log('[dashboard] weekly: ' . $e->getMessage()); }

        return true;
    }
}
