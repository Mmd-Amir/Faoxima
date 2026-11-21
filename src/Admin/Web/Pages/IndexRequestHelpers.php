<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class IndexRequestHelpers
{
    public static function faoxima_chart_data(\PDO $pdo, string $range, int $customDays = 7): array
{
    $now = time();

    $bucketHours = 0;
    $bucketCount = 0;
    $labelFmt    = '';

    switch ($range) {
        case '24h':
            $bucketHours = 1;
            $bucketCount = 24;
            $labelFmt    = 'H:00';
            break;
        case '7d':
            $bucketHours = 24;
            $bucketCount = 7;
            $labelFmt    = 'm/d';
            break;
        case '3m':

            $bucketHours = 24 * 7;
            $bucketCount = 13;
            $labelFmt    = 'm/d';
            break;
        case 'custom':
            $customDays = max(1, min(365, $customDays));
            if ($customDays <= 30) {
                $bucketHours = 24;
                $bucketCount = $customDays;
                $labelFmt    = 'm/d';
            } else {

                $bucketHours = 24 * 7;
                $bucketCount = (int)ceil($customDays / 7);
                $labelFmt    = 'm/d';
            }
            break;
        default:
            return ['labels' => [], 'data' => []];
    }

    $bucketSec = $bucketHours * 3600;
    $earliest  = $now - $bucketCount * $bucketSec;


    $stmt = $pdo->prepare(
        "SELECT time_sell, price_product
           FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales
          WHERE time_sell >= :since
            AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate()
    );
    $stmt->bindValue(':since', $earliest, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $buckets = array_fill(0, $bucketCount, 0);
    foreach ($rows as $r) {
        $t = (int)$r['time_sell'];
        if ($t < $earliest || $t > $now) continue;
        $idx = (int)floor(($t - $earliest) / $bucketSec);
        if ($idx < 0 || $idx >= $bucketCount) continue;
        $buckets[$idx] += (int)$r['price_product'];
    }


    $labels = [];
    for ($i = 0; $i < $bucketCount; $i++) {
        $bucketStart = $earliest + $i * $bucketSec;
        $labels[] = function_exists('jdate')
            ? jdate($labelFmt, $bucketStart)
            : date($labelFmt, $bucketStart);
    }

    return ['labels' => $labels, 'data' => $buckets];
}

    public static function faoxima_income_between(PDO $pdo, int $from, int $to): float {
    return (float) \Faoxima\Admin\Reporting\Statistics::rx_stats_income_between($pdo, $from, $to);
}

    public static function faoxima_orders_between(PDO $pdo, int $from, int $to): int {
    return (int) \Faoxima\Admin\Reporting\Statistics::rx_stats_orders_between($pdo, $from, $to);
}

    public static function faoxima_users_between(PDO $pdo, int $from, int $to): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register != 'none' AND register >= :a AND register < :b");
    $s->bindValue(':a', (int)$from, PDO::PARAM_INT); $s->bindValue(':b', (int)$to, PDO::PARAM_INT); $s->execute();
    return (int)$s->fetchColumn();
}

    public static function faoxima_pct(float $cur, float $prev): ?int {
    if ($prev <= 0) return null;
    return (int)round(($cur - $prev) / $prev * 100);
}

    public static function faoxima_trend_pill(?int $pct, ?string $text = null): string {
    $cls = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
    $ic  = $pct > 0 ? 'arrow-up' : ($pct < 0 ? 'arrow-down' : 'arrow-right');
    if ($text !== null) {
        $body = '<span>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    } elseif ($pct === null) {
        $body = '<span>بدون سابقهٔ مقایسه</span>';
    } elseif ($pct > 100) {
        $body = '<span>بیش از ۱۰۰٪ رشد</span><span>نسبت به ۲۴ ساعت قبل</span>';
    } else {
        $pctText = ($pct > 0 ? '+' : '') . $pct . '٪';
        $body = '<span dir="ltr">' . htmlspecialchars($pctText, ENT_QUOTES, 'UTF-8') . '</span><span>نسبت به ۲۴ ساعت قبل</span>';
    }
    return '<span class="stat-card__trend ' . $cls . '">' . \Faoxima\Admin\Web\Support\Icons::icon($ic, 'svg-icon') . ' ' . $body . '</span>';
}

    public static function faoxima_status_badge(string $s): array {
    switch ($s) {
        case 'active':        return ['تحویل شده', 'badge-success'];
        case 'send_on_hold':  return ['در حال ارسال', 'badge-warning'];
        case 'sendedwarn':    return ['هشدار', 'badge-warning'];
        case 'end_of_time':   return ['پایان زمان', 'badge-gray'];
        case 'end_of_volume': return ['پایان حجم', 'badge-gray'];
        case 'expired':       return ['منقضی', 'badge-gray'];
        default:              return [$s !== '' ? $s : 'نامشخص', 'badge-gray'];
    }
}
}
