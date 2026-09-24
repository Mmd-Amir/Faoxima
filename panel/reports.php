<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) return;

    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        @header_remove('Content-Encoding');
        @header_remove('Content-Length');
        @ini_set('zlib.output_compression', '0');
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = htmlspecialchars(
        $err['message'] . ' @ ' . basename((string)$err['file']) . ':' . (int)$err['line'],
        ENT_QUOTES, 'UTF-8'
    );
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8">'
       . '<title>خطای سرور</title>'
       . '<body style="font-family:sans-serif;background:#0a0a0f;color:#f1f3f8;padding:32px;">'
       . '<h2>خطای داخلی سرور</h2><pre style="white-space:pre-wrap">' . $msg . '</pre>'
       . '<p><a style="color:#3b82f6" href="login.php">بازگشت به ورود</a></p>'
       . '</body></html>';
});

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
if ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    ini_set('session.cookie_secure', '1');
}
session_start();

if (empty($_SESSION['_session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['_session_regenerated'] = true;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../re/rx/function/statistics_helpers.php';


$sessionUser = isset($_SESSION["user"]) && is_string($_SESSION["user"]) && $_SESSION["user"] !== ''
    ? $_SESSION["user"]
    : null;

if ($sessionUser === null) {
    header('Location: login.php');
    exit;
}

$query = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
$query->bindValue(':username', $sessionUser, PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}


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
    $st = $pdo->prepare("SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE time_sell >= :a AND " . rx_stats_historical_sale_predicate());
    $st->bindValue(':a', (int)$todayStart, PDO::PARAM_INT); $st->execute();
    $incomeToday = (float)$st->fetchColumn();

    $totalIncome = rx_stats_total_sales_amount($pdo);
    $totalOrders = rx_stats_total_sales_count($pdo);
    $totalUsers  = rx_stats_total_users($pdo);
    $avg30Start = $nowTs - 30 * 86400;
    $st30 = $pdo->prepare("SELECT COALESCE(SUM(price_product),0), COUNT(*) FROM invoice WHERE time_sell >= :a AND " . rx_stats_historical_sale_predicate());
    $st30->bindValue(':a', (int)$avg30Start, PDO::PARAM_INT); $st30->execute();
    $row30 = $st30->fetch(PDO::FETCH_NUM);
    $ord30 = (int)($row30[1] ?? 0);
    $avgOrder = $ord30 > 0 ? ((float)($row30[0] ?? 0) / $ord30) : 0.0;
} catch (\Throwable $e) { error_log('[reports] cards: ' . $e->getMessage()); }

try {
    $prod30Start = $nowTs - 30 * 86400;
    $prodTopN = 8;
    $st = $pdo->prepare("SELECT name_product, COUNT(*) AS total FROM invoice WHERE time_sell >= :a AND " . rx_stats_historical_sale_predicate() . " GROUP BY name_product ORDER BY total DESC");
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

$validSale = rx_stats_historical_sale_predicate();

$validSaleI = rx_stats_historical_sale_predicate('i');

$purchaseMarkers = "(id_invoice IS NULL OR id_invoice = '' OR id_invoice = 'none' OR (id_invoice NOT LIKE 'getconfigafterpay%' AND id_invoice NOT LIKE 'getextenduser%' AND id_invoice NOT LIKE 'getextravolumeuser%' AND id_invoice NOT LIKE 'getextratimeuser%'))";

$realPaymentPredicate = rx_stats_real_payment_predicate('p');

try {
    $st = $pdo->query("SELECT CASE WHEN a.id_admin IS NOT NULL THEN 'admin' WHEN u.agent = 'n2' THEN 'n2' WHEN u.agent = 'n' THEN 'n' ELSE 'f' END AS grp, COUNT(*) AS users FROM user u LEFT JOIN admin a ON a.id_admin = u.id GROUP BY grp");
    $groupUsers = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $groupUsers[(string)$r['grp']] = (int)$r['users']; }

    $st = $pdo->query("SELECT CASE WHEN a.id_admin IS NOT NULL THEN 'admin' WHEN u.agent = 'n2' THEN 'n2' WHEN u.agent = 'n' THEN 'n' ELSE 'f' END AS grp, COALESCE(SUM(i.price_product),0) AS revenue, COUNT(i.id_invoice) AS orders FROM user u LEFT JOIN admin a ON a.id_admin = u.id LEFT JOIN invoice i ON i.id_user = u.id AND {$validSaleI} GROUP BY grp");
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
    $st = $pdo->query("SELECT i.id_user, COALESCE(NULLIF(MAX(u.username),''), i.id_user) AS uname, COALESCE(SUM(i.price_product),0) AS revenue, COUNT(*) AS orders FROM invoice i LEFT JOIN user u ON u.id = i.id_user WHERE {$validSaleI} GROUP BY i.id_user ORDER BY revenue DESC LIMIT 10");
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
    $row = $pdo->query("SELECT COALESCE(SUM(discount_amount),0) AS total, COUNT(*) AS cnt FROM invoice WHERE {$validSale} AND discount_amount + 0 > 0")->fetch(PDO::FETCH_ASSOC);
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

$json_prod_labels  = json_encode($prodLabels, JSON_UNESCAPED_UNICODE);
$json_prod_data    = json_encode($prodData);
$json_group_labels = json_encode(array_column($groupRows, 'label'), JSON_UNESCAPED_UNICODE);
$json_group_users  = json_encode(array_map('intval', array_column($groupRows, 'users')));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>گزارش‌ها و تحلیل | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <script src="js/theme.js?v=flat50" defer></script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper reports-page">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('chart-bar', 'svg-icon svg-lg'); ?>
                        گزارش‌ها و تحلیل
                    </div>
                    <div class="page-head__sub"><?php echo function_exists('jdate') ? jdate('F Y') : date('Y/m'); ?></div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">فروش امروز</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$incomeToday; ?>" data-suffix=" T"><?php echo number_format($incomeToday); ?> T</span>
                        </div>
                        <span class="stat-card__icon icon-green"><svg class="svg-icon" viewBox="0 0 1024 1024" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M136.948 908.811c5.657 0 10.24-4.583 10.24-10.24V610.755c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v287.816c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V610.755c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v287.816c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.96c5.657 0 10.24-4.583 10.24-10.24V551.322c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v347.249c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V551.322c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v347.249c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.342c5.657 0 10.24-4.583 10.24-10.24V492.497c0-5.651-4.588-10.24-10.24-10.24h-81.92c-5.652 0-10.24 4.589-10.24 10.24v406.692c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V492.497c0-28.271 22.924-51.2 51.2-51.2h81.92c28.276 0 51.2 22.929 51.2 51.2v406.692c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.958c5.657 0 10.24-4.583 10.24-10.24V441.299c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v457.892c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V441.299c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v457.892c0 28.278-22.922 51.2-51.2 51.2zm-6.205-841.902C677.379 271.088 355.268 367.011 19.245 387.336c-11.29.683-19.889 10.389-19.206 21.679s10.389 19.889 21.679 19.206c342.256-20.702 670.39-118.419 964.372-284.046 9.854-5.552 13.342-18.041 7.79-27.896s-18.041-13.342-27.896-7.79z"/><path d="M901.21 112.64l102.39.154c11.311.017 20.494-9.138 20.511-20.449s-9.138-20.494-20.449-20.511l-102.39-.154c-11.311-.017-20.494 9.138-20.511 20.449s9.138 20.494 20.449 20.511z"/><path d="M983.151 92.251l-.307 101.827c-.034 11.311 9.107 20.508 20.418 20.542s20.508-9.107 20.542-20.418l.307-101.827c.034-11.311-9.107-20.508-20.418-20.542s-20.508 9.107-20.542 20.418z"/></g></svg></span>
                    </div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">میانگین ارزش سفارش</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$avgOrder; ?>" data-suffix=" T"><?php echo number_format($avgOrder); ?> T</span>
                        </div>
                        <span class="stat-card__icon icon-cyan"><?php echo icon('receipt', 'svg-icon'); ?></span>
                    </div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">کل سفارشات</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$totalOrders; ?>"><?php echo number_format($totalOrders); ?></span>
                        </div>
                        <span class="stat-card__icon icon-rose"><?php echo icon('cart-check-round', 'svg-icon'); ?></span>
                    </div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">کل کاربران</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$totalUsers; ?>"><?php echo number_format($totalUsers); ?></span>
                        </div>
                        <span class="stat-card__icon icon-blue"><?php echo icon('users', 'svg-icon'); ?></span>
                    </div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">مجموع مبلغ تخفیف</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$discountTotal; ?>" data-suffix=" T"><?php echo number_format($discountTotal); ?> T</span>
                        </div>
                        <span class="stat-card__icon icon-amber"><?php echo icon('percent-discount', 'svg-icon'); ?></span>
                    </div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">تعداد استفاده از تخفیف</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$discountCount; ?>"><?php echo number_format($discountCount); ?></span>
                        </div>
                        <span class="stat-card__icon icon-purple"><?php echo icon('coupon', 'svg-icon'); ?></span>
                    </div>
                </div>
            </div>

            <div class="panel observe-in">
                <div class="panel__head">
                    <div>
                        <div class="panel__title">فروش به تفکیک محصول</div>
                        <div class="panel__sub">پرفروش‌ترین محصولات بر اساس تعداد فروش (۳۰ روز اخیر)</div>
                    </div>
                </div>
                <?php if (!empty($prodData)): ?>
                    <div class="chart-box sm chart-box--auto"><div class="chart-scroll"><canvas id="productChart"></canvas></div></div>
                <?php else: ?>
                    <div class="hdr-dd__empty" style="padding:60px 10px;">داده‌ای برای نمایش نیست</div>
                <?php endif; ?>
            </div>

            <div class="panel observe-in">
                    <div class="panel__head">
                        <div>
                            <div class="panel__title">تحلیل گروه‌های کاربری</div>
                            <div class="panel__sub">تعداد کاربران و مجموع خرید هر گروه</div>
                        </div>
                    </div>
                    <?php if (!empty($groupRows)): ?>
                        <div class="chart-box sm chart-box--hbar"><canvas id="groupChart"></canvas></div>
                        <div class="table-wrap table-wrap--compact">
                            <table class="app-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>گروه</th>
                                        <th>تعداد کاربران</th>
                                        <th>تعداد سفارش</th>
                                        <th>مجموع خرید</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($groupRows as $g): ?>
                                    <tr>
                                        <td data-label="گروه"><?php echo htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="تعداد کاربران"><?php echo number_format($g['users']); ?></td>
                                        <td data-label="تعداد سفارش"><?php echo number_format($g['orders']); ?></td>
                                        <td data-label="مجموع خرید"><?php echo number_format($g['revenue']); ?> T</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="hdr-dd__empty" style="padding:60px 10px;">داده‌ای برای نمایش نیست</div>
                    <?php endif; ?>
            </div>

            <?php if (!empty($discountTopCodes)): ?>
            <div class="panel observe-in">
                <div class="panel__head">
                    <div>
                        <div class="panel__title">تخفیف‌ها به تفکیک کد</div>
                        <div class="panel__sub">پرکاربردترین کدهای تخفیف بر اساس تعداد استفاده</div>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="app-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>کد تخفیف</th>
                                <th>تعداد استفاده</th>
                                <th>مجموع تخفیف</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($discountTopCodes as $d): ?>
                            <tr>
                                <td data-label="کد تخفیف" style="direction:ltr;"><?php echo htmlspecialchars((string)$d['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="تعداد استفاده"><?php echo number_format((int)$d['uses']); ?></td>
                                <td data-label="مجموع تخفیف"><?php echo number_format((float)$d['total']); ?> T</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $leaderboards = [
                [
                    'id'    => 'buyers',
                    'title' => 'برترین خریداران بر اساس مبلغ',
                    'sub'   => '۱۰ کاربر با بیشترین مجموع خرید معتبر',
                    'btn'   => 'مشاهده فهرست ۱۰ خریدار برتر',
                    'icon'  => 'wallet-plus',
                    'tone'  => 'icon-rose',
                    'rows'  => $topBuyers,
                    'count' => count($topBuyers),
                    'cols'  => ['#', 'کاربر', 'تعداد سفارش', 'مجموع خرید'],
                    'name'  => 'uname',
                    'uid'   => 'id_user',
                    'cells' => [
                        ['label' => 'تعداد سفارش', 'key' => 'orders',  'money' => false],
                        ['label' => 'مجموع خرید',  'key' => 'revenue', 'money' => true],
                    ],
                ],
                [
                    'id'    => 'active',
                    'title' => 'کاربران با بیشترین سرویس فعال',
                    'sub'   => 'شمارش سرویس‌های با وضعیت active',
                    'btn'   => 'مشاهده فهرست کاربران با بیشترین سرویس فعال',
                    'icon'  => 'server-stack',
                    'tone'  => 'icon-green',
                    'rows'  => $topActive,
                    'count' => count($topActive),
                    'cols'  => ['#', 'کاربر', 'سرویس فعال', 'ارزش سرویس‌ها'],
                    'name'  => 'uname',
                    'uid'   => 'id_user',
                    'cells' => [
                        ['label' => 'سرویس فعال',    'key' => 'actives', 'money' => false],
                        ['label' => 'ارزش سرویس‌ها', 'key' => 'revenue', 'money' => true],
                    ],
                ],
                [
                    'id'    => 'deposit',
                    'title' => 'برترین کاربران بر اساس مجموع شارژ',
                    'sub'   => 'جمع پرداخت‌های تاییدشده کیف پول',
                    'btn'   => 'مشاهده فهرست برترین کاربران بر اساس شارژ',
                    'icon'  => 'credit-card',
                    'tone'  => 'icon-amber',
                    'rows'  => $topDeposit,
                    'count' => count($topDeposit),
                    'cols'  => ['#', 'کاربر', 'تعداد پرداخت', 'مجموع شارژ'],
                    'name'  => 'uname',
                    'uid'   => 'id_user',
                    'cells' => [
                        ['label' => 'تعداد پرداخت', 'key' => 'cnt',     'money' => false],
                        ['label' => 'مجموع شارژ',   'key' => 'deposit', 'money' => true],
                    ],
                ],
                [
                    'id'    => 'balance',
                    'title' => 'برترین کاربران بر اساس موجودی فعلی',
                    'sub'   => 'موجودی لحظه‌ای کیف پول',
                    'btn'   => 'مشاهده فهرست کاربران بر اساس موجودی',
                    'icon'  => 'atm-machine',
                    'tone'  => 'icon-cyan',
                    'rows'  => $topBalance,
                    'count' => count($topBalance),
                    'cols'  => ['#', 'کاربر', 'موجودی'],
                    'name'  => 'uname',
                    'uid'   => 'id',
                    'cells' => [
                        ['label' => 'موجودی', 'key' => 'Balance', 'money' => true],
                    ],
                ],
            ];
            ?>

            <div class="panel observe-in">
                <div class="panel__head">
                    <div>
                        <div class="panel__title">فهرست‌های برترین کاربران</div>
                        <div class="panel__sub">برای مشاهده هر جدول روی دکمه مربوط به آن بزنید</div>
                    </div>
                </div>
                <div class="lb-grid">
                    <?php foreach ($leaderboards as $lb): ?>
                        <button type="button" class="lb-btn" data-lb-open="lbmodal-<?php echo $lb['id']; ?>"<?php echo empty($lb['rows']) ? ' disabled' : ''; ?>>
                            <span class="lb-btn__icon <?php echo $lb['tone']; ?>"><?php echo icon($lb['icon'], 'svg-icon'); ?></span>
                            <span class="lb-btn__text">
                                <span class="lb-btn__title"><?php echo $lb['title']; ?></span>
                                <span class="lb-btn__sub"><?php echo empty($lb['rows']) ? 'داده‌ای برای نمایش نیست' : $lb['btn']; ?></span>
                            </span>
                            <span class="lb-btn__count"><?php echo number_format($lb['count']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</section>

<?php foreach ($leaderboards as $lb): ?>
    <?php if (empty($lb['rows'])) { continue; } ?>
    <div class="modal-overlay lb-modal" id="lbmodal-<?php echo $lb['id']; ?>" role="dialog" aria-modal="true" aria-labelledby="lbtitle-<?php echo $lb['id']; ?>">
        <div class="modal-box lb-modal__box">
            <div class="modal-head">
                <div class="lb-modal__head">
                    <span class="lb-btn__icon <?php echo $lb['tone']; ?>"><?php echo icon($lb['icon'], 'svg-icon'); ?></span>
                    <div>
                        <div class="modal-head__title" id="lbtitle-<?php echo $lb['id']; ?>"><?php echo $lb['title']; ?></div>
                        <div class="panel__sub"><?php echo $lb['sub']; ?></div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-lb-close aria-label="بستن">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-wrap">
                    <table class="app-table" style="width:100%">
                        <thead>
                            <tr><?php foreach ($lb['cols'] as $c): ?><th><?php echo $c; ?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                        <?php $i = 0; foreach ($lb['rows'] as $r): $i++; ?>
                            <tr>
                                <td data-label="#"><?php echo number_format($i); ?></td>
                                <td data-label="کاربر">
                                    <span class="cell-user__text">
                                        <span class="cell-user__name" style="direction:ltr;"><?php echo htmlspecialchars((string)$r[$lb['name']], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="cell-user__sub">شناسه: <?php echo htmlspecialchars((string)$r[$lb['uid']], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                </td>
                                <?php foreach ($lb['cells'] as $cell): ?>
                                    <td data-label="<?php echo $cell['label']; ?>"><?php echo $cell['money'] ? number_format((float)$r[$cell['key']]) . ' T' : number_format((int)$r[$cell['key']]); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script src="js/chart.min.js?v=flat3"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var FA = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    function fa(n){ return String(n).replace(/\d/g, function(d){ return FA[+d]; }); }
    function faShort(v){ var n = Number(v) || 0, a = Math.abs(n); if (a >= 1e6){ var m = n/1e6; return fa((m % 1 === 0) ? m.toFixed(0) : m.toFixed(1)) + 'م'; } if (a >= 1e3){ return fa(Math.round(n/1e3)) + 'هزار'; } return fa(n); }
    function hexA(hex, a){
        var m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
        if(!m){ return 'rgba(124,58,237,'+a+')'; }
        var n = parseInt(m[1],16);
        return 'rgba('+((n>>16)&255)+','+((n>>8)&255)+','+(n&255)+','+a+')';
    }
    function accent(){ return (getComputedStyle(document.documentElement).getPropertyValue('--accent')||'#a855f7').trim(); }
    function vw(){ return window.innerWidth || 1024; }
    function narrow(){ return vw() <= 640; }
    function tick(){ var w = vw(); if (w <= 420) return 9; if (w <= 640) return 10; if (w <= 900) return 10.5; return 11.5; }
    function longest(arr){ var n = 0; for (var i = 0; i < arr.length; i++){ var l = String(arr[i]).length; if (l > n) n = l; } return n; }
    function textPx(len, size){ return Math.ceil(len * size * 0.62); }
    function shortOne(s, max){
        var t = String(s).split('|')[0].replace(/\s+/g, ' ').trim();
        if (!t) t = String(s).replace(/\s+/g, ' ').trim();
        if (t.length > max) t = t.slice(0, max - 1).trim() + '…';
        return t;
    }
    function shortLabels(arr, max){
        var out = [], seen = {}, i, t, k, n;
        for (i = 0; i < arr.length; i++){
            t = shortOne(arr[i], max); k = t; n = 2;
            while (seen[k]) { k = t + ' (' + fa(n) + ')'; n++; }
            seen[k] = true; out.push(k);
        }
        return out;
    }
    function colors(){
        var dark = document.documentElement.getAttribute('data-theme') !== 'light';
        return {
            text: dark ? '#94a3b8' : '#475569',
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(100,116,139,0.18)',
            tipBg: dark ? '#1d1d1e' : '#ffffff',
            tipBorder: dark ? '#2a2a2d' : '#e2e8f0'
        };
    }
    Chart.defaults.font.family = "Vazirmatn, Tahoma, sans-serif";

    var PALETTE = ['#6366f1','#a855f7','#06b6d4','#10b981','#f59e0b','#f43f5e','#3b82f6','#94a3b8'];
    var PROD_LABELS = <?php echo $json_prod_labels; ?>;
    var GROUP_LABELS = <?php echo $json_group_labels; ?>;
    var product, groupc;

    function buildProduct(){
        var el = document.getElementById('productChart'); if(!el) return;
        var inner = el.parentNode;
        var box = inner.classList.contains('chart-scroll') ? inner.parentNode : inner;
        var c = colors();
        var fs = tick();
        var maxChars = vw() <= 420 ? 16 : vw() <= 640 ? 20 : 28;
        var labels = shortLabels(PROD_LABELS, maxChars);
        var axisNeed = Math.min(Math.round(vw() * 0.42), textPx(longest(labels), fs) + 20);

        var rowH = 42;
        var chrome = 50;
        var needH = Math.max(220, PROD_LABELS.length * rowH + chrome);
        var capH = Math.max(260, Math.round(window.innerHeight * 0.62));
        if (needH > capH) {
            box.classList.add('is-scroll');
            box.style.height = capH + 'px';
            box.style.setProperty('--chart-inner-h', needH + 'px');
        } else {
            box.classList.remove('is-scroll');
            box.style.height = needH + 'px';
            box.style.removeProperty('--chart-inner-h');
        }

        if (product) product.destroy();
        product = new Chart(el, {
            type:'bar',
            data:{ labels: labels, datasets:[{ label:'فروش', data: <?php echo $json_prod_data; ?>, backgroundColor: PALETTE, borderRadius:10, borderSkipped:false, maxBarThickness:26, minBarLength:2, categoryPercentage:0.82, barPercentage:0.9 }]},
            options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y',
                layout:{ padding:{ left:4, right:12, top:4, bottom:4 } },
                plugins:{ legend:{display:false},
                    tooltip:{ rtl:true, textDirection:'rtl', backgroundColor:c.tipBg, titleColor:c.text, bodyColor:c.text, borderColor:c.tipBorder, borderWidth:1, padding:12, cornerRadius:10, displayColors:false, caretPadding:8, titleFont:{size:12.5}, bodyFont:{size:12.5}, titleMarginBottom:6, position:'nearest',
                        callbacks:{ title:function(items){ return items.length ? String(PROD_LABELS[items[0].dataIndex]) : ''; }, label:function(ctx){ return fa(ctx.parsed.x) + ' فروش'; } } } },
                scales:{
                    x:{ grid:{color:c.grid}, ticks:{ color:c.text, font:{size:fs}, precision:0, maxTicksLimit:narrow() ? 5 : 8, callback:function(v){ return fa(v); } } },
                    y:{ grid:{display:false}, afterFit:function(scale){ if (scale.width < axisNeed) scale.width = axisNeed; }, ticks:{ color:c.text, font:{size:fs}, autoSkip:false, crossAlign:'far', padding:8, callback:function(val){ return this.getLabelForValue(val); } } }
                } }
        });
    }

    function buildGroup(){
        var el = document.getElementById('groupChart'); if(!el) return;
        var c = colors();
        var fs = tick();
        if (groupc) groupc.destroy();
        groupc = new Chart(el, {
            type:'bar',
            data:{ labels: GROUP_LABELS, datasets:[{ label:'کاربران', data: <?php echo $json_group_users; ?>, backgroundColor: PALETTE, borderRadius:10, borderSkipped:false }]},
            options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y',
                layout:{ padding:{ left:4, right:10, top:4, bottom:4 } },
                plugins:{ legend:{display:false}, tooltip:{ rtl:true, textDirection:'rtl', backgroundColor:c.tipBg, titleColor:c.text, bodyColor:c.text, borderColor:c.tipBorder, borderWidth:1, padding:12, cornerRadius:10, displayColors:false, caretPadding:8, titleFont:{size:12.5}, bodyFont:{size:12.5}, titleMarginBottom:6, position:'nearest', callbacks:{ title:function(items){ return items.length ? String(items[0].label) : ''; }, label:function(ctx){ return fa(ctx.parsed.x) + ' کاربر'; } } } },
                scales:{ x:{ grid:{color:c.grid}, ticks:{ color:c.text, font:{size:fs}, precision:0, maxTicksLimit:narrow() ? 5 : 8, callback:function(v){ return fa(v); } } }, y:{ grid:{display:false}, afterFit:function(scale){ var need = textPx(longest(GROUP_LABELS), fs) + 22; if (scale.width < need) scale.width = need; }, ticks:{ color:c.text, font:{size:fs}, autoSkip:false, crossAlign:'far', padding:8, callback:function(val){ return this.getLabelForValue(val); } } } } }
        });
    }

    function buildAll(){ buildProduct(); buildGroup(); }
    document.addEventListener('faoxima:themechange', function(){ buildAll(); });
    var rzT = null, rzN = null;
    window.addEventListener('resize', function(){
        if (rzT) clearTimeout(rzT);
        rzT = setTimeout(function(){
            var n = narrow() + '|' + tick();
            if (n !== rzN) { rzN = n; buildAll(); }
        }, 200);
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ rzN = narrow() + '|' + tick(); buildAll(); });
    else { rzN = narrow() + '|' + tick(); buildAll(); }
})();

(function(){
    var open = null, lastFocus = null;

    var lockedScrollY = 0;

    function show(id){
        var m = document.getElementById(id);
        if (!m || m === open) return;
        if (open) hide();
        lastFocus = document.activeElement;
        lockedScrollY = window.scrollY || window.pageYOffset || 0;
        m.classList.add('active');
        document.body.classList.add('lb-modal-open');
        document.documentElement.classList.add('lb-modal-open');
        open = m;
        var c = m.querySelector('[data-lb-close]');
        if (c) { try { c.focus({ preventScroll: true }); } catch (e) { c.focus(); } }
        if ((window.scrollY || window.pageYOffset || 0) !== lockedScrollY) window.scrollTo(0, lockedScrollY);
    }

    function hide(){
        if (!open) return;
        open.classList.remove('active');
        document.body.classList.remove('lb-modal-open');
        document.documentElement.classList.remove('lb-modal-open');
        open = null;
        if (lastFocus && typeof lastFocus.focus === 'function') {
            try { lastFocus.focus({ preventScroll: true }); } catch (e) { lastFocus.focus(); }
        }
        lastFocus = null;
        window.scrollTo(0, lockedScrollY);
    }

    document.addEventListener('click', function(e){
        var t = e.target.closest('[data-lb-open]');
        if (t) { e.preventDefault(); show(t.getAttribute('data-lb-open')); return; }
        if (e.target.closest('[data-lb-close]')) { e.preventDefault(); hide(); return; }
        if (open && e.target === open) hide();
    });

    document.addEventListener('keydown', function(e){
        if (!open) return;
        if (e.key === 'Escape' || e.key === 'Esc') { e.preventDefault(); hide(); return; }
        if (e.key !== 'Tab') return;
        var f = open.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
})();
</script>

</body>
</html>
