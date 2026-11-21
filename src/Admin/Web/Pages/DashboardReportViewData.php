<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $catData, $catLabels, $dayAgoTs, $pdo, $since24Str, $weekData, $weekLabels.
 * Inherited or exported by reference: $activity, $json_cat_data, $json_cat_labels, $json_week_data, $json_week_labels, $latestOrders, $r, $st.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DashboardReportViewData
{
    public static function handle(
        &$activity,
        $adminWebPage,
        $catData,
        $catLabels,
        $dayAgoTs,
        &$json_cat_data,
        &$json_cat_labels,
        &$json_week_data,
        &$json_week_labels,
        &$latestOrders,
        $pdo,
        &$r,
        $since24Str,
        &$st,
        $weekData,
        $weekLabels
    ) {
        if (($adminWebPage ?? null) !== 'index.php') {
            return;
        }

        try {
            $st = $pdo->prepare("SELECT id_invoice, username, id_user, name_product, price_product, status, time_sell FROM invoice WHERE name_product != 'سرویس تست' AND time_sell >= :since AND status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') ORDER BY (time_sell + 0) DESC LIMIT 200");
            $st->bindValue(':since', (int)$dayAgoTs, PDO::PARAM_INT); $st->execute();
            $latestOrders = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { error_log('[dashboard] latest: ' . $e->getMessage()); }

        try {
            $st = $pdo->prepare("SELECT id_invoice, username, name_product, time_sell AS t FROM invoice WHERE name_product != 'سرویس تست' AND time_sell >= :since AND status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') ORDER BY (time_sell + 0) DESC LIMIT 200");
            $st->bindValue(':since', (int)$dayAgoTs, PDO::PARAM_INT); $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $activity[] = ['t' => (int)$r['t'], 'ico' => 'green', 'icon' => 'cart-shopping', 'title' => 'سفارش جدید', 'sub' => trim((string)$r['username'] . ' — ' . (string)$r['name_product'], ' —')];
            }
            $st = $pdo->prepare("SELECT id_user, price, Payment_Method, `time` AS t FROM Payment_report WHERE " . \Faoxima\Admin\Reporting\Statistics::rx_stats_real_payment_predicate() . " AND `time` >= :s ORDER BY `time` DESC LIMIT 200");
            $st->bindValue(':s', $since24Str, PDO::PARAM_STR); $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $ts = strtotime(str_replace('/', '-', (string)$r['t']));
                if ($ts === false) continue;
                $sub = number_format((float)$r['price']) . ' تومان';
                if (!empty($r['Payment_Method'])) $sub .= ' — ' . (string)$r['Payment_Method'];
                $activity[] = ['t' => (int)$ts, 'ico' => 'amber', 'icon' => 'money-bill', 'title' => 'پرداخت', 'sub' => $sub];
            }
            $st = $pdo->prepare("SELECT username, register AS t FROM user WHERE register != 'none' AND register >= :since ORDER BY (register + 0) DESC LIMIT 200");
            $st->bindValue(':since', (int)$dayAgoTs, PDO::PARAM_INT); $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $activity[] = ['t' => (int)$r['t'], 'ico' => 'purple', 'icon' => 'user-plus', 'title' => 'ثبت‌نام کاربر', 'sub' => (string)$r['username']];
            }
            usort($activity, function ($a, $b) { return $b['t'] <=> $a['t']; });
            $activity = array_slice($activity, 0, 200);
        } catch (\Throwable $e) { error_log('[dashboard] activity: ' . $e->getMessage()); }

        $json_cat_labels = json_encode($catLabels, JSON_UNESCAPED_UNICODE);
        $json_cat_data   = json_encode($catData);
        $json_week_labels = json_encode($weekLabels, JSON_UNESCAPED_UNICODE);
        $json_week_data   = json_encode($weekData);

        return true;
    }
}
