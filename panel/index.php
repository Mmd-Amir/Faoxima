<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'index.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


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
    echo '<!DOCTYPE html><html lang="fa" dir="rtl" data-theme="dark"><meta charset="utf-8">'
       . '<title>خطای سرور</title>'
       . '<body style="font-family:sans-serif;background:#1B1B1D;color:#F4F4F5;padding:32px;">'
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
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/../jdf.php';

if (\Faoxima\Admin\Web\Pages\IndexRequest::handle(
        pdo: $pdo ?? null,
        result: $result
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\DashboardCharts::handle(
        adminWebPage: $adminWebPage ?? null,
        cat: $cat,
        cfg: $cfg,
        items: $items,
        json_data: $json_data,
        json_labels: $json_labels,
        payload: $payload,
        pdo: $pdo ?? null,
        r: $r,
        resultcontsell: $resultcontsell,
        resultcount: $resultcount,
        resultcountday: $resultcountday,
        st: $st,
        stmt: $stmt,
        total_income: $total_income
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\DashboardRevenue::handle(
        activity: $activity,
        adminWebPage: $adminWebPage ?? null,
        catData: $catData,
        catLabels: $catLabels,
        dayAgoTs: $dayAgoTs,
        incTrend: $incTrend,
        latestOrders: $latestOrders,
        newUsersWeek: $newUsersWeek,
        ordTrend: $ordTrend,
        pdo: $pdo ?? null,
        r: $r,
        resultcountday: $resultcountday ?? null,
        since24Str: $since24Str,
        st: $st,
        u24Trend: $u24Trend,
        weekData: $weekData,
        weekLabels: $weekLabels
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\DashboardReportViewData::handle(
        activity: $activity,
        adminWebPage: $adminWebPage ?? null,
        catData: $catData ?? null,
        catLabels: $catLabels ?? null,
        dayAgoTs: $dayAgoTs ?? null,
        json_cat_data: $json_cat_data,
        json_cat_labels: $json_cat_labels,
        json_week_data: $json_week_data,
        json_week_labels: $json_week_labels,
        latestOrders: $latestOrders,
        pdo: $pdo ?? null,
        r: $r,
        since24Str: $since24Str ?? null,
        st: $st,
        weekData: $weekData ?? null,
        weekLabels: $weekLabels ?? null
    ) !== true) {
    unset($adminWebPage);
    return;
}

unset($adminWebPage);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>پنل مدیریت ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=labels98">
    <script src="js/theme.js?v=dark62" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('grid', 'svg-icon svg-lg'); ?>
                        داشبورد
                    </div>
                    <div class="page-head__sub"><?php echo function_exists('jdate') ? jdate('l، j F Y') : date('Y/m/d'); ?></div>
                </div>
                <div class="chip-row">
                    <a href="invoice.php" class="chip"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon svg-sm'); ?><span>سفارشات</span></a>
                    <a href="reports.php" class="chip"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chart-bar', 'svg-icon svg-sm'); ?><span>گزارش‌ها</span></a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">تعداد کل کاربران</span>
                            
                        </div>
                        <span class="stat-card__icon icon-blue"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('users', 'svg-icon'); ?></span>
                    </div>
                    <div class="stat-card__metric"><span class="stat-card__value" data-count="<?php echo (int)$resultcount; ?>"><?php echo number_format($resultcount); ?></span><?php echo \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_trend_pill($newUsersWeek > 0 ? 1 : 0, '+' . number_format($newUsersWeek) . ' این هفته'); ?></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">تعداد کل سفارشات</span>
                            
                        </div>
                        <span class="stat-card__icon icon-rose"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('cart-check-round', 'svg-icon'); ?></span>
                    </div>
                    <div class="stat-card__metric"><span class="stat-card__value" data-count="<?php echo (int)$resultcontsell; ?>"><?php echo number_format($resultcontsell); ?></span><?php echo \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_trend_pill($ordTrend); ?></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">جمع کل فروش</span>
                            
                        </div>
                        <span class="stat-card__icon icon-green"><svg class="svg-icon" viewBox="0 0 1024 1024" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M136.948 908.811c5.657 0 10.24-4.583 10.24-10.24V610.755c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v287.816c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V610.755c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v287.816c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.96c5.657 0 10.24-4.583 10.24-10.24V551.322c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v347.249c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V551.322c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v347.249c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.342c5.657 0 10.24-4.583 10.24-10.24V492.497c0-5.651-4.588-10.24-10.24-10.24h-81.92c-5.652 0-10.24 4.589-10.24 10.24v406.692c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V492.497c0-28.271 22.924-51.2 51.2-51.2h81.92c28.276 0 51.2 22.929 51.2 51.2v406.692c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.958c5.657 0 10.24-4.583 10.24-10.24V441.299c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v457.892c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V441.299c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v457.892c0 28.278-22.922 51.2-51.2 51.2zm-6.205-841.902C677.379 271.088 355.268 367.011 19.245 387.336c-11.29.683-19.889 10.389-19.206 21.679s10.389 19.889 21.679 19.206c342.256-20.702 670.39-118.419 964.372-284.046 9.854-5.552 13.342-18.041 7.79-27.896s-18.041-13.342-27.896-7.79z"/><path d="M901.21 112.64l102.39.154c11.311.017 20.494-9.138 20.511-20.449s-9.138-20.494-20.449-20.511l-102.39-.154c-11.311-.017-20.494 9.138-20.511 20.449s9.138 20.494 20.449 20.511z"/><path d="M983.151 92.251l-.307 101.827c-.034 11.311 9.107 20.508 20.418 20.542s20.508-9.107 20.542-20.418l.307-101.827c.034-11.311-9.107-20.508-20.418-20.542s-20.508 9.107-20.542 20.418z"/></g></svg></span>
                    </div>
                    <div class="stat-card__metric"><span class="stat-card__value" data-count="<?php echo (int)$total_income; ?>" ><?php echo number_format($total_income); ?></span><small class="stat-card__currency">تومان</small><?php echo \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_trend_pill($incTrend); ?></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">کاربران جدید (۲۴ ساعت)</span>
                            
                        </div>
                        <span class="stat-card__icon icon-purple"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('user-plus', 'svg-icon'); ?></span>
                    </div>
                    <div class="stat-card__metric"><span class="stat-card__value" data-count="<?php echo (int)$resultcountday; ?>"><?php echo number_format($resultcountday); ?></span><?php echo \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_trend_pill($u24Trend); ?></div>
                </div>
            </div>

            <div class="dash-row dash-analytics">
                <div class="panel observe-in revenue-panel">
                    <div class="panel__head">
                        <div>
                            <div class="panel__title">روند درآمد</div>
                            <div class="panel__sub" id="revenueSub">۷ روز اخیر</div>
                        </div>
                        <div class="chart-toolbar" role="group" aria-label="بازه زمانی">
                            <button class="chart-range-btn" data-range="24h">۲۴ ساعت</button>
                            <button class="chart-range-btn active" data-range="7d">هفته</button>
                            <button class="chart-range-btn" data-range="3m">۳ ماه</button>
                            <button class="chart-range-btn" id="customRangeBtn">روز دلخواه</button>
                            <span id="customRangeBox" style="display:none; align-items:center; gap:6px;">
                                <input type="number" id="customRangeInput" min="1" max="365" placeholder="روز" inputmode="numeric" aria-label="تعداد روز" style="width:62px; padding:6px 8px; border-radius:var(--radius-md); border:1px solid var(--border-soft); background:var(--surface-1); color:var(--text-main); font-family:inherit; font-size:12px; text-align:center;">
                                <button class="chart-range-btn" id="customRangeApply">نمایش</button>
                            </span>
                        </div>
                    </div>
                    <div class="revenue-summary">
                        <div class="revenue-summary__total"><span>درآمد این بازه</span><strong id="revenueTotal">—</strong><small>تومان</small></div>
                        <div class="revenue-summary__details">
                            <div><span>میانگین هر بازه</span><b id="revenueAverage">—</b></div>
                            <div><span>بیشترین درآمد</span><b id="revenuePeak">—</b></div>
                        </div>
                    </div>
                    <div class="chart-box revenue-chart"><canvas id="revenueChart" role="img" aria-label="نمودار روند درآمد در بازه انتخاب‌شده"></canvas></div>
                    <div class="revenue-caption"><span><i></i>درآمد ثبت‌شده</span><span>برای مشاهده جزئیات، یک نقطه را انتخاب کنید</span></div>
                </div>

                <div class="panel observe-in category-panel">
                    <div class="panel__head">
                        <div>
                            <div class="panel__title">دسته‌بندی فروش</div>
                            <div class="panel__sub">بر اساس مبلغ فروش</div>
                        </div>
                    </div>
                    <?php if (!empty($catData)): ?>
                        <div class="donut-wrap"><canvas id="categoryChart"></canvas></div>
                    <?php else: ?>
                        <div class="mdt-empty">داده‌ای برای نمایش نیست</div>
                    <?php endif; ?>
                </div>

                <div class="panel observe-in activity-panel">
                    <div class="panel__head">
                        <div>
                            <div class="panel__title">فعالیت اخیر</div>
                            <div class="panel__sub">۲۴ ساعت اخیر</div>
                        </div>
                        <span class="activity-count"><?php echo strtr(number_format(count($activity)), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> رویداد</span>
                    </div>
                    <?php if (!empty($activity)): ?>
                        <div class="activity-list" id="dashActivity">
                            <?php foreach ($activity as $act): ?>
                                <div class="activity-item">
                                    <span class="activity-ico <?php echo $act['ico']; ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon($act['icon'], 'svg-icon'); ?></span>
                                    <div class="activity-body">
                                        <div class="activity-event-head"><b><?php echo htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8'); ?></b><time datetime="<?php echo htmlspecialchars(date('c', (int)$act['t']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo strtr(date('H:i', (int)$act['t']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?></time></div>
                                        <small><?php echo htmlspecialchars($act['sub'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mdt-foot"><span class="mdt-info"></span><div class="mdt-pager" data-pager="dashActivity" data-page-size="3"></div></div>
                    <?php else: ?>
                        <div class="mdt-empty">فعالیتی در ۲۴ ساعت گذشته ثبت نشده است</div>
                    <?php endif; ?>
                    <div class="activity-foot"><a href="reports.php"><span>مشاهدهٔ همهٔ گزارش‌ها</span><?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-left', 'svg-icon svg-sm'); ?></a></div>
                </div>
            </div>

            <div class="dash-row dash-weekly">
                <div class="panel observe-in weekly-panel">
                    <div class="panel__head">
                        <div>
                            <div class="panel__title">فروش هفتگی</div>
                            <div class="panel__sub">تعداد فروش در هر روز هفتهٔ جاری (شنبه تا جمعه)</div>
                        </div>
                        <span class="weekly-period">هفتهٔ جاری</span>
                    </div>
                    <div class="weekly-summary">
                        <div class="weekly-total"><span>مجموع فروش هفته</span><div><strong id="weeklyTotal">—</strong><small>سفارش</small></div></div>
                        <div class="weekly-detail"><span>روز پرفروش</span><b id="weeklyBest">—</b></div>
                        <div class="weekly-detail"><span>روزهای دارای فروش</span><b id="weeklyActive">—</b></div>
                    </div>
                    <div class="chart-box sm"><canvas id="trafficChart" role="img" aria-label="تعداد فروش روزانه در هفتهٔ جاری"></canvas></div>
                    <div class="weekly-caption"><span><i></i>تعداد سفارش‌ها</span><span>جزئیات هر روز با انتخاب ستون</span></div>
                </div>
            </div>

            <div class="panel observe-in orders-panel">
                <div class="panel__head">
                    <div class="orders-heading">
                        <span class="orders-heading__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('cart-check-round', 'svg-icon'); ?></span>
                        <div><div class="panel__title">آخرین سفارشات</div><div class="panel__sub">سفارش‌های ثبت‌شده در ۲۴ ساعت اخیر</div></div>
                    </div>
                    <a href="invoice.php" class="btn btn-sm orders-all"><span>همهٔ سفارش‌ها</span><?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-left', 'svg-icon svg-sm'); ?></a>
                </div>
                <?php if (!empty($latestOrders)): ?>
                <div class="table-wrap" style="overflow-x:auto;">
                    <table class="app-table app-table--summary" id="dashOrders">
                        <thead>
                            <tr>
                                <th>کاربر</th>
                                <th>محصول</th>
                                <th>مبلغ</th>
                                <th>وضعیت</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestOrders as $od):
                                list($stLabel, $stClass) = \Faoxima\Admin\Web\Pages\IndexRequestHelpers::faoxima_status_badge((string)($od['status'] ?? ''));
                                $oTime = (int)($od['time_sell'] ?? 0);
                            ?>
                            <tr data-detail-row data-detail-title="<?php echo htmlspecialchars((string)(!empty($od['username']) ? $od['username'] : ($od['id_user'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                                <td data-label="کاربر" data-summary="1"><div class="order-person"><span class="order-person__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('users', 'svg-icon svg-sm'); ?></span><span class="order-cell"><b dir="auto"><?php echo htmlspecialchars((string)(!empty($od['username']) ? $od['username'] : ($od['id_user'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></b><small>شناسه: <bdi><?php echo htmlspecialchars((string)($od['id_user'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></bdi></small></span></div></td>
                                <td data-label="محصول" data-summary="1"><div class="order-cell"><b><?php echo htmlspecialchars((string)($od['name_product'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></b><small>سفارش <bdi>#<?php echo htmlspecialchars((string)($od['id_invoice'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></bdi></small></div></td>
                                <td data-label="مبلغ"><span class="order-amount"><bdi><?php echo strtr(number_format((float)($od['price_product'] ?? 0)), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?></bdi> <small class="currency-unit">تومان</small></span></td>
                                <td data-label="وضعیت" data-summary="1"><span class="badge order-status <?php echo $stClass; ?>"><?php echo htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="تاریخ"><div class="order-cell order-date"><span dir="ltr"><?php echo $oTime > 0 ? (function_exists('jdate') ? jdate('Y/m/d', $oTime) : date('Y/m/d', $oTime)) : '—'; ?></span><?php if ($oTime > 0): ?><small><?php echo \Faoxima\Admin\Web\Support\Icons::icon('clock', 'svg-icon svg-xs'); ?><span dir="ltr"><?php echo strtr(date('H:i', $oTime), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?></span></small><?php endif; ?></div></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mdt-foot"><span class="mdt-info"></span><div class="mdt-pager" data-pager-rows="dashOrders" data-page-size="3"></div></div>
                <?php else: ?>
                    <div class="mdt-empty">سفارشی در ۲۴ ساعت گذشته ثبت نشده است</div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</section>

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
    function accent(){ var style=getComputedStyle(document.documentElement); return (style.getPropertyValue(document.documentElement.getAttribute('data-theme') === 'light' ? '--accent-ink' : '--accent')||'#a855f7').trim(); }
    function colors(){
        var dark = document.documentElement.getAttribute('data-theme') !== 'light';
        return {
            text: dark ? '#A1A1AA' : '#344054',
            grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(100,116,139,0.18)',
            tipBg: dark ? '#232326' : '#ffffff',
            tipBorder: dark ? '#303033' : '#e2e8f0'
        };
    }
    Chart.defaults.font.family = "Vazirmatn, Tahoma, sans-serif";
    Chart.defaults.font.size = 11.5;

    var PALETTE = ['#6366f1','#a855f7','#06b6d4','#10b981','#f59e0b','#f43f5e'];
    var revenue, category, traffic;
    var revLabels = <?php echo $json_labels; ?>;
    var revData   = <?php echo $json_data; ?>;
    var currentRange = '7d';

    function updateRevenueSummary(){
        var values = revData.map(function(v){ return Number(v) || 0; });
        var total = values.reduce(function(sum, v){ return sum + v; }, 0);
        var format = new Intl.NumberFormat('fa-IR', { maximumFractionDigits:0 });
        document.getElementById('revenueTotal').textContent = format.format(total);
        document.getElementById('revenueAverage').textContent = format.format(values.length ? total / values.length : 0) + ' تومان';
        document.getElementById('revenuePeak').textContent = format.format(values.reduce(function(peak, v){ return Math.max(peak, v); }, 0)) + ' تومان';
    }

    function buildRevenue(){
        var el = document.getElementById('revenueChart'); if(!el) return;
        var c = colors(), ac = accent();
        if (revenue) revenue.destroy();
        updateRevenueSummary();
        revenue = new Chart(el, {
            type:'line',
            data:{ labels: revLabels, datasets:[{ label:'درآمد', data: revData, borderColor: ac,
                backgroundColor:function(context){ var chart=context.chart, area=chart.chartArea; if(!area) return 'transparent'; var dark=document.documentElement.getAttribute('data-theme') !== 'light'; var gradient=chart.ctx.createLinearGradient(0,area.top,0,area.bottom); gradient.addColorStop(0,hexA(ac,dark ? 0.25 : 0.12)); gradient.addColorStop(0.65,hexA(ac,dark ? 0.07 : 0.025)); gradient.addColorStop(1,hexA(ac,0)); return gradient; },
                fill:true, cubicInterpolationMode:'monotone', borderWidth:3, borderCapStyle:'round', pointRadius:function(context){ return context.dataset.data.length === 1 ? 4 : 0; }, pointHitRadius:20, pointHoverRadius:6, pointBackgroundColor:ac, pointHoverBackgroundColor:ac, pointHoverBorderColor:c.tipBg, pointHoverBorderWidth:3 }]},
            plugins:[{ id:'revenueGuide', beforeDatasetsDraw:function(chart){ var active=chart.tooltip && chart.tooltip.getActiveElements(); if(!active || !active.length) return; var x=active[0].element.x, ctx=chart.ctx, area=chart.chartArea; ctx.save(); ctx.beginPath(); ctx.setLineDash([4,5]); ctx.strokeStyle=hexA(ac,0.4); ctx.lineWidth=1; ctx.moveTo(x,area.top); ctx.lineTo(x,area.bottom); ctx.stroke(); ctx.restore(); } }],
            options:{ responsive:true, maintainAspectRatio:false, animation:{duration:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 650}, layout:{padding:{top:12,right:12,left:4}}, interaction:{mode:'index',intersect:false},
                plugins:{ legend:{display:false}, tooltip:{ rtl:true, displayColors:false, backgroundColor:c.tipBg, titleColor:c.text, bodyColor:getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim(), borderColor:hexA(ac,0.35), borderWidth:1, padding:16, cornerRadius:12, titleMarginBottom:10, bodyFont:{size:15,weight:'bold'}, callbacks:{ title:function(items){ return items.length ? fa(items[0].label) : ''; }, label:function(ctx){ return new Intl.NumberFormat('fa-IR').format(ctx.parsed.y) + ' تومان'; } } } },
                scales:{ x:{ border:{display:false}, grid:{display:false}, ticks:{color:c.text,maxRotation:0,autoSkip:true,maxTicksLimit:7,padding:14,callback:function(value){ return fa(this.getLabelForValue(value)); }} }, y:{ beginAtZero:true, border:{display:false}, grid:{color:c.grid,drawTicks:false}, ticks:{ color:c.text,maxTicksLimit:5,padding:12,callback:function(v){ return faShort(v); } } } } }
        });
    }

    function buildCategory(){
        var el = document.getElementById('categoryChart'); if(!el) return;
        var c = colors();
        if (category) category.destroy();
        category = new Chart(el, {
            type:'doughnut',
            data:{ labels: <?php echo $json_cat_labels; ?>, datasets:[{ data: <?php echo $json_cat_data; ?>, backgroundColor: PALETTE, borderWidth:0, hoverOffset:10 }]},
            options:{ responsive:true, maintainAspectRatio:false, cutout:'66%',
                plugins:{ legend:{ position:'bottom', rtl:true, labels:{ color:c.text, padding:14, usePointStyle:true, font:{size:12} } },
                    tooltip:{ rtl:true, backgroundColor:c.tipBg, titleColor:c.text, bodyColor:c.text, borderColor:c.tipBorder, borderWidth:1, padding:12, cornerRadius:8, callbacks:{ label:function(ctx){ return ctx.label + ': ' + new Intl.NumberFormat('fa-IR').format(ctx.parsed) + ' تومان'; } } } } }
        });
    }

    function buildTraffic(){
        var el = document.getElementById('trafficChart'); if(!el) return;
        var c = colors(), ac = accent();
        var labels = <?php echo $json_week_labels; ?>;
        var values = <?php echo $json_week_data; ?>;
        var total = values.reduce(function(sum, value){ return sum + Number(value); }, 0);
        var peak = Math.max.apply(null, values.concat([0]));
        var best = values.reduce(function(days, value, index){ if(peak > 0 && Number(value) === peak) days.push(labels[index]); return days; }, []);
        document.getElementById('weeklyTotal').textContent = new Intl.NumberFormat('fa-IR').format(total);
        document.getElementById('weeklyBest').textContent = best.length ? best.join('، ') : 'هنوز فروشی ثبت نشده';
        document.getElementById('weeklyActive').textContent = fa(values.filter(function(value){ return Number(value) > 0; }).length) + ' روز از ' + fa(values.length) + ' روز';
        el.setAttribute('aria-label', 'فروش هفته: ' + fa(total) + ' سفارش. ' + labels.map(function(label, index){ return label + ': ' + fa(values[index]); }).join('، '));
        if (traffic) traffic.destroy();
        traffic = new Chart(el, {
            type:'bar',
            data:{ labels:labels, datasets:[{ label:'فروش', data:values,
                backgroundColor:ac,
                hoverBackgroundColor:ac, maxBarThickness:48, categoryPercentage:0.55, barPercentage:0.8, borderRadius:9, borderSkipped:false }]},
            plugins:[{ id:'weeklyValues', afterDatasetsDraw:function(chart){ var ctx=chart.ctx, meta=chart.getDatasetMeta(0); ctx.save(); ctx.textAlign='center'; ctx.textBaseline='bottom'; ctx.font='600 12px Vazirmatn, Tahoma, sans-serif'; meta.data.forEach(function(bar,index){ var value=Number(values[index]); ctx.fillStyle=value > 0 ? getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim() : c.text; ctx.fillText(fa(value),bar.x,bar.y - 9); }); ctx.restore(); } }],
            options:{ responsive:true, maintainAspectRatio:false, layout:{padding:{top:24,left:4,right:8}}, animation:{duration:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 650}, interaction:{mode:'index',intersect:false},
                plugins:{ legend:{display:false}, tooltip:{ rtl:true, displayColors:false, backgroundColor:c.tipBg, titleColor:c.text, bodyColor:getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim(), borderColor:hexA(ac,0.35), borderWidth:1, padding:14, cornerRadius:12, titleMarginBottom:8, bodyFont:{size:14,weight:'bold'}, callbacks:{ label:function(ctx){ return fa(ctx.parsed.y) + ' سفارش'; } } } },
                scales:{ x:{ border:{display:false}, grid:{display:false}, ticks:{color:c.text,padding:12,maxRotation:0,autoSkip:false,font:{size:11}} }, y:{ beginAtZero:true, grace:'18%', suggestedMax:peak === 0 ? 1 : undefined, border:{display:false}, grid:{color:c.grid,drawTicks:false}, ticks:{ color:c.text, precision:0,maxTicksLimit:5,padding:10,callback:function(v){ return fa(v); } } } } }
        });
    }

    function buildAll(){ buildRevenue(); buildCategory(); buildTraffic(); }

    var TITLES = { '24h':'۲۴ ساعت اخیر', '7d':'۷ روز اخیر', '3m':'۳ ماه اخیر' };
    var subEl = document.getElementById('revenueSub');
    var rangeBtns = document.querySelectorAll('.chart-range-btn[data-range]');
    var customBtn = document.getElementById('customRangeBtn');
    var customBox = document.getElementById('customRangeBox');
    var customInput = document.getElementById('customRangeInput');
    var customApply = document.getElementById('customRangeApply');

    function setActive(r){ rangeBtns.forEach(function(b){ b.classList.toggle('active', b.dataset.range === r); }); if (customBtn) customBtn.classList.toggle('active', r === 'custom'); }

    function loadRange(url, range, subText){
        fetch(url, { credentials:'same-origin', headers:{'Accept':'application/json'} })
            .then(function(x){ return x.json(); })
            .then(function(j){ if(!j.ok) throw 0; revLabels=j.labels; revData=j.data; currentRange=range; if(subEl) subEl.textContent=subText; setActive(range); updateRevenueSummary(); if(revenue){ revenue.data.labels=revLabels; revenue.data.datasets[0].data=revData; revenue.update(); } })
            .catch(function(){ if(subEl) subEl.textContent='خطا در بارگذاری'; });
    }

    rangeBtns.forEach(function(b){
        b.addEventListener('click', function(){
            var r = b.dataset.range; if (r === currentRange) return;
            if (customBox) customBox.style.display = 'none';
            loadRange('index.php?ajax=chart&range=' + encodeURIComponent(r), r, TITLES[r] || '');
        });
    });

    function applyCustom(){
        var d = parseInt(customInput.value, 10);
        if (isNaN(d) || d < 1) d = 1;
        if (d > 365) d = 365;
        customInput.value = d;
        loadRange('index.php?ajax=chart&range=custom&days=' + d, 'custom', fa(d) + ' روز اخیر');
    }
    if (customBtn) {
        customBtn.addEventListener('click', function(){
            if (!customBox) return;
            var open = customBox.style.display === 'none' || customBox.style.display === '';
            customBox.style.display = open ? 'inline-flex' : 'none';
            if (open && customInput) customInput.focus();
        });
    }
    if (customApply) customApply.addEventListener('click', applyCustom);
    if (customInput) customInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); applyCustom(); } });

    document.addEventListener('faoxima:themechange', function(){ buildAll(); });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', buildAll);
    else buildAll();
})();
</script>

<script>
(function () {
    var FA = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    function faNum(n){ return String(n).replace(/\d/g, function(d){ return FA[+d]; }); }
    function paginate(items, pager, info, pageSize) {
        var total = items.length;
        var pages = Math.max(1, Math.ceil(total / pageSize));
        var current = 1;
        function pageList() {
            var out = [];
            function add(n){ if (n >= 1 && n <= pages && out.indexOf(n) === -1) out.push(n); }
            add(1);
            for (var i = current - 1; i <= current + 1; i++) add(i);
            add(pages);
            out.sort(function (a, b) { return a - b; });
            return out;
        }
        function render() {
            var start = (current - 1) * pageSize;
            var end = start + pageSize;
            for (var i = 0; i < total; i++) items[i].style.display = (i >= start && i < end) ? '' : 'none';
            pager.innerHTML = '';
            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'mdt-page' + (current === 1 ? ' disabled' : '');
            prev.textContent = 'قبلی';
            prev.addEventListener('click', function () { if (current > 1) { current--; render(); } });
            pager.appendChild(prev);
            var list = pageList(), last = 0;
            for (var j = 0; j < list.length; j++) {
                var n = list[j];
                if (last && n - last > 1) {
                    var dots = document.createElement('span');
                    dots.className = 'mdt-dots';
                    dots.textContent = '…';
                    pager.appendChild(dots);
                }
                (function (num) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'mdt-page' + (num === current ? ' active' : '');
                    b.textContent = faNum(num);
                    b.addEventListener('click', function () { if (num !== current) { current = num; render(); } });
                    pager.appendChild(b);
                })(n);
                last = n;
            }
            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'mdt-page' + (current === pages ? ' disabled' : '');
            next.textContent = 'بعدی';
            next.addEventListener('click', function () { if (current < pages) { current++; render(); } });
            pager.appendChild(next);
            if (info) {
                var from = total ? start + 1 : 0;
                var to = Math.min(end, total);
                info.textContent = total ? ('نمایش ' + faNum(from) + ' تا ' + faNum(to) + ' از ' + faNum(total)) : '';
            }
        }
        render();
    }
    function setup(pager, items) {
        var size = parseInt(pager.getAttribute('data-page-size'), 10) || 5;
        var foot = pager.closest('.mdt-foot');
        var info = foot ? foot.querySelector('.mdt-info') : null;
        if (items.length === 0) { if (foot) foot.style.display = 'none'; return; }
        paginate(items, pager, info, size);
    }
    document.querySelectorAll('.mdt-pager[data-pager]').forEach(function (pager) {
        var host = document.getElementById(pager.getAttribute('data-pager'));
        if (!host) return;
        setup(pager, Array.prototype.slice.call(host.querySelectorAll(':scope > .activity-item')));
    });
    document.querySelectorAll('.mdt-pager[data-pager-rows]').forEach(function (pager) {
        var table = document.getElementById(pager.getAttribute('data-pager-rows'));
        if (!table) return;
        setup(pager, Array.prototype.slice.call(table.querySelectorAll('tbody > tr')));
    });
})();
</script>

</body>
</html>


