<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'reports.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
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

if (\Faoxima\Admin\Web\Pages\ReportsRequest::handle(
        avgOrder: $avgOrder,
        discountCount: $discountCount,
        discountTopCodes: $discountTopCodes,
        discountTotal: $discountTotal,
        groupRows: $groupRows,
        incomeToday: $incomeToday,
        json_group_labels: $json_group_labels,
        json_group_users: $json_group_users,
        json_prod_data: $json_prod_data,
        json_prod_labels: $json_prod_labels,
        pdo: $pdo ?? null,
        prodData: $prodData,
        prodLabels: $prodLabels,
        result: $result,
        topActive: $topActive,
        topBalance: $topBalance,
        topBuyers: $topBuyers,
        topDeposit: $topDeposit,
        totalOrders: $totalOrders,
        totalUsers: $totalUsers
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
    <title>گزارش‌ها و تحلیل | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=leaderboards109">
    <script src="js/theme.js?v=dark62" defer></script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper reports-page">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('chart-bar', 'svg-icon svg-lg'); ?>
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


                        </div>
                        <span class="stat-card__icon icon-green"><svg class="svg-icon" viewBox="0 0 1024 1024" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M136.948 908.811c5.657 0 10.24-4.583 10.24-10.24V610.755c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v287.816c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V610.755c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v287.816c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.96c5.657 0 10.24-4.583 10.24-10.24V551.322c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v347.249c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V551.322c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v347.249c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.342c5.657 0 10.24-4.583 10.24-10.24V492.497c0-5.651-4.588-10.24-10.24-10.24h-81.92c-5.652 0-10.24 4.589-10.24 10.24v406.692c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V492.497c0-28.271 22.924-51.2 51.2-51.2h81.92c28.276 0 51.2 22.929 51.2 51.2v406.692c0 28.278-22.922 51.2-51.2 51.2zm278.414-40.958c5.657 0 10.24-4.583 10.24-10.24V441.299c0-5.657-4.583-10.24-10.24-10.24h-81.92a10.238 10.238 0 00-10.24 10.24v457.892c0 5.657 4.583 10.24 10.24 10.24h81.92zm0 40.96h-81.92c-28.278 0-51.2-22.922-51.2-51.2V441.299c0-28.278 22.922-51.2 51.2-51.2h81.92c28.278 0 51.2 22.922 51.2 51.2v457.892c0 28.278-22.922 51.2-51.2 51.2zm-6.205-841.902C677.379 271.088 355.268 367.011 19.245 387.336c-11.29.683-19.889 10.389-19.206 21.679s10.389 19.889 21.679 19.206c342.256-20.702 670.39-118.419 964.372-284.046 9.854-5.552 13.342-18.041 7.79-27.896s-18.041-13.342-27.896-7.79z"/><path d="M901.21 112.64l102.39.154c11.311.017 20.494-9.138 20.511-20.449s-9.138-20.494-20.449-20.511l-102.39-.154c-11.311-.017-20.494 9.138-20.511 20.449s9.138 20.494 20.449 20.511z"/><path d="M983.151 92.251l-.307 101.827c-.034 11.311 9.107 20.508 20.418 20.542s20.508-9.107 20.542-20.418l.307-101.827c.034-11.311-9.107-20.508-20.418-20.542s-20.508 9.107-20.542 20.418z"/></g></svg></span>
                    </div>
                    <div class="report-stat-metric"><span class="stat-card__value"><span data-count="<?php echo (int)$incomeToday; ?>"><?php echo number_format($incomeToday); ?></span> <small class="currency-unit">تومان</small></span></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">میانگین ارزش سفارش</span>

                        </div>
                        <span class="stat-card__icon icon-cyan"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon'); ?></span>
                    </div>
                    <div class="report-stat-metric"><span class="stat-card__value"><span data-count="<?php echo (int)$avgOrder; ?>"><?php echo number_format($avgOrder); ?></span> <small class="currency-unit">تومان</small></span></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">کل سفارشات</span>

                        </div>
                        <span class="stat-card__icon icon-rose"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('cart-check-round', 'svg-icon'); ?></span>
                    </div>
                    <div class="report-stat-metric"><span class="stat-card__value" data-count="<?php echo (int)$totalOrders; ?>"><?php echo number_format($totalOrders); ?></span></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">کل کاربران</span>

                        </div>
                        <span class="stat-card__icon icon-blue"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('users', 'svg-icon'); ?></span>
                    </div>
                    <div class="report-stat-metric"><span class="stat-card__value" data-count="<?php echo (int)$totalUsers; ?>"><?php echo number_format($totalUsers); ?></span></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">مجموع مبلغ تخفیف</span>

                        </div>
                        <span class="stat-card__icon icon-amber"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('percent-discount', 'svg-icon'); ?></span>
                    </div>
                    <div class="report-stat-metric"><span class="stat-card__value"><span data-count="<?php echo (int)$discountTotal; ?>"><?php echo number_format($discountTotal); ?></span> <small class="currency-unit">تومان</small></span></div>
                </div>

                <div class="stat-card observe-in">
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">تعداد استفاده از تخفیف</span>

                        </div>
                        <span class="stat-card__icon icon-purple"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('coupon', 'svg-icon'); ?></span>
                    </div>
                    <div class="report-stat-metric"><span class="stat-card__value" data-count="<?php echo (int)$discountCount; ?>"><?php echo number_format($discountCount); ?></span></div>
                </div>
                </div>

            <div class="panel observe-in report-product-panel">
                <div class="panel__head">
                    <div>
                        <div class="panel__title">فروش به تفکیک محصول</div>
                        <div class="panel__sub">پرفروش‌ترین محصولات بر اساس تعداد فروش (۳۰ روز اخیر)</div>
                    </div>
                    <span class="product-report-period">۳۰ روز اخیر</span>
                </div>
                <?php if (!empty($prodData)): ?>
                    <?php $productReportTotal = array_sum(array_map('intval', $prodData)); $productReportShare = $productReportTotal > 0 ? (int)round(((int)$prodData[0] / $productReportTotal) * 100) : 0; ?>
                    <div class="product-report-summary">
                        <div class="product-report-total"><span>مجموع فروش این بازه</span><div><strong><?php echo strtr(number_format($productReportTotal), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?></strong><small>سفارش</small></div></div>
                        <div class="product-report-best"><span>پرفروش‌ترین محصول</span><b><?php echo htmlspecialchars((string)$prodLabels[0], ENT_QUOTES, 'UTF-8'); ?></b></div>
                        <div class="product-report-share"><span>سهم محصول برتر</span><b><?php echo strtr((string)$productReportShare, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?>٪</b></div>
                    </div>
                    <div class="chart-box sm chart-box--auto"><div class="chart-scroll"><canvas id="productChart" role="img" aria-label="تعداد فروش محصولات در ۳۰ روز اخیر"></canvas></div></div>
                    <div class="product-report-caption"><span><i></i>تعداد فروش هر محصول</span><span>برای مشاهدهٔ نام کامل و جزئیات، نوار را انتخاب کنید</span></div>
                <?php else: ?>
                    <div class="mdt-empty">داده‌ای برای نمایش نیست</div>
                <?php endif; ?>
            </div>

            <div class="panel observe-in report-groups-panel">
                    <div class="panel__head">
                        <div>
                            <div class="panel__title">تحلیل گروه‌های کاربری</div>
                            <div class="panel__sub">تعداد کاربران و مجموع خرید هر گروه</div>
                        </div>
                        <span class="group-report-tag"><?php echo strtr((string)count($groupRows), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> گروه کاربری</span>
                    </div>
                    <?php if (!empty($groupRows)): ?>
                        <div class="group-report-summary">
                            <div><span>مجموع کاربران گروه‌ها</span><strong><?php echo number_format(array_sum(array_column($groupRows, 'users'))); ?><small>کاربر</small></strong></div>
                            <div><span>تعداد سفارش‌ها</span><strong><?php echo number_format(array_sum(array_column($groupRows, 'orders'))); ?><small>سفارش</small></strong></div>
                            <div><span>مجموع خرید گروه‌ها</span><strong><?php echo number_format(array_sum(array_column($groupRows, 'revenue'))); ?><small>تومان</small></strong></div>
                        </div>
                        <div class="chart-box sm chart-box--hbar"><canvas id="groupChart" role="img" aria-label="مقایسهٔ تعداد کاربران گروه‌های کاربری"></canvas></div>
                        <div class="group-report-caption"><span><i></i>تعداد کاربران هر گروه</span><span>برای مشاهدهٔ جزئیات، نوار را انتخاب کنید</span></div>
                        <div class="table-wrap table-wrap--compact">
                            <table id="groupReportTable" class="app-table" style="width:100%">
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
                                        <td data-label="گروه"><span class="group-report-name"><span aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('users', 'svg-icon svg-sm'); ?></span><?php echo htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td data-label="تعداد کاربران"><?php echo number_format($g['users']); ?></td>
                                        <td data-label="تعداد سفارش"><?php echo number_format($g['orders']); ?></td>
                                        <td data-label="مجموع خرید"><?php echo number_format($g['revenue']); ?> <small class="currency-unit">تومان</small></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="mdt-empty">داده‌ای برای نمایش نیست</div>
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
                                <td data-label="مجموع تخفیف"><?php echo number_format((float)$d['total']); ?> <small class="currency-unit">تومان</small></td>
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

            <div class="panel observe-in report-leaderboards">
                <div class="panel__head">
                    <div>
                        <div class="panel__title">فهرست‌های برترین کاربران</div>
                        <div class="panel__sub">برای مشاهده هر جدول روی دکمه مربوط به آن بزنید</div>
                    </div>
                </div>
                <div class="lb-grid">
                    <?php foreach ($leaderboards as $lb): ?>
                        <button type="button" class="lb-btn" data-lb-open="lbmodal-<?php echo $lb['id']; ?>"<?php echo empty($lb['rows']) ? ' disabled' : ''; ?>>
                            <span class="lb-btn__icon <?php echo $lb['tone']; ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon($lb['icon'], 'svg-icon'); ?></span>
                            <span class="lb-btn__text">
                                <span class="lb-btn__title"><?php echo $lb['title']; ?></span>
                                <span class="lb-btn__sub"><?php echo empty($lb['rows']) ? 'داده‌ای برای نمایش نیست' : $lb['btn']; ?></span>
                            </span>
                            <span class="lb-btn__count"><strong><?php echo number_format($lb['count']); ?></strong><small>کاربر</small></span>
                            <span class="lb-btn__arrow" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-left', 'svg-icon'); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</section>

<?php foreach ($leaderboards as $lb): ?>
    <?php if (empty($lb['rows'])) { continue; } ?>
    <div class="modal-overlay lb-modal lb-ranking-modal" id="lbmodal-<?php echo $lb['id']; ?>" role="dialog" aria-modal="true" aria-labelledby="lbtitle-<?php echo $lb['id']; ?>">
        <div class="modal-box lb-modal__box">
            <div class="modal-head">
                <div class="lb-modal__head">
                    <span class="lb-btn__icon <?php echo $lb['tone']; ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon($lb['icon'], 'svg-icon'); ?></span>
                    <div>
                        <div class="modal-head__title" id="lbtitle-<?php echo $lb['id']; ?>"><?php echo $lb['title']; ?></div>
                        <div class="panel__sub"><?php echo $lb['sub']; ?></div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-lb-close aria-label="بستن">&times;</button>
            </div>
            <div class="modal-body">
                <div class="lb-modal__summary"><span>رتبه‌بندی کاربران</span><span><strong><?php echo number_format($lb['count']); ?></strong> کاربر در این فهرست</span></div>
                <div class="table-wrap">
                    <table class="app-table" style="width:100%">
                        <thead>
                            <tr><?php foreach ($lb['cols'] as $c): ?><th><?php echo $c; ?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                        <?php $i = 0; foreach ($lb['rows'] as $r): $i++; ?>
                            <tr>
                                <td data-label="#"><span class="lb-rank<?php echo $i <= 3 ? ' lb-rank--top' : ''; ?>"><?php echo number_format($i); ?></span></td>
                                <td data-label="کاربر">
                                    <span class="cell-user__text">
                                        <span class="cell-user__name" style="direction:ltr;"><?php echo htmlspecialchars((string)$r[$lb['name']], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="cell-user__sub">شناسه: <?php echo htmlspecialchars((string)$r[$lb['uid']], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                </td>
                                <?php foreach ($lb['cells'] as $cell): ?>
                                    <td class="lb-metric" data-label="<?php echo $cell['label']; ?>"><span><?php echo $cell['money'] ? number_format((float)$r[$cell['key']]) . ' <small class="currency-unit">تومان</small>' : number_format((int)$r[$cell['key']]); ?></span></td>
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
    function tick(){ return vw() <= 900 ? 11 : 11.5; }
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
            text: dark ? '#A1A1AA' : '#475569',
            grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(100,116,139,0.18)',
            neutral: dark ? 'rgba(148,163,184,0.30)' : 'rgba(100,116,139,0.22)',
            tipBg: dark ? '#232326' : '#ffffff',
            tipBorder: dark ? '#303033' : '#e2e8f0'
        };
    }
    Chart.defaults.font.family = "Vazirmatn, Tahoma, sans-serif";

    var PALETTE = ['#6366f1','#a855f7','#06b6d4','#10b981','#f59e0b','#f43f5e','#3b82f6','#94a3b8'];
    var PROD_LABELS = <?php echo $json_prod_labels; ?>;
    var PROD_DATA = <?php echo $json_prod_data; ?>;
    var GROUP_LABELS = <?php echo $json_group_labels; ?>;
    var GROUP_DATA = <?php echo $json_group_users; ?>;
    var product, groupc;

    function buildProduct(){
        var el = document.getElementById('productChart'); if(!el) return;
        var inner = el.parentNode;
        var box = inner.classList.contains('chart-scroll') ? inner.parentNode : inner;
        var c = colors();
        var fs = tick();
        var ac = accent();
        var maxProd = 0;
        var barColors = PROD_DATA.map(function (v, i) { return i === maxProd ? ac : c.neutral; });
        var maxChars = vw() <= 420 ? 16 : vw() <= 640 ? 20 : 28;
        var labels = shortLabels(PROD_LABELS, maxChars);
        var axisNeed = Math.min(Math.round(vw() * 0.42), textPx(longest(labels), fs) + 20);

        var rowH = 48;
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
            data:{ labels: labels, datasets:[{ label:'فروش', data: PROD_DATA, backgroundColor: barColors, hoverBackgroundColor:ac, borderRadius:7, borderSkipped:false, maxBarThickness:18, categoryPercentage:0.72, barPercentage:0.8 }]},
            plugins:[{id:'productCounts',afterDatasetsDraw:function(chart){var ctx=chart.ctx; ctx.save(); ctx.font='600 12px Vazirmatn, Tahoma, sans-serif'; ctx.textAlign='left'; ctx.textBaseline='middle'; ctx.fillStyle=getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim(); chart.getDatasetMeta(0).data.forEach(function(bar,index){ctx.fillText(fa(PROD_DATA[index]),bar.x+9,bar.y);}); ctx.restore();}}],
            options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', animation:{duration:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 650}, interaction:{mode:'nearest',axis:'y',intersect:false},
                layout:{ padding:{ left:4, right:44, top:10, bottom:4 } },
                plugins:{ legend:{display:false},
                    tooltip:{ rtl:true, textDirection:'rtl', backgroundColor:c.tipBg, titleColor:c.text, bodyColor:c.text, borderColor:hexA(ac,0.35), borderWidth:1, padding:16, cornerRadius:12, displayColors:false, caretPadding:8, titleFont:{size:12.5}, bodyFont:{size:13,weight:'bold'}, titleMarginBottom:8, position:'nearest',
                        callbacks:{ title:function(items){ return items.length ? String(PROD_LABELS[items[0].dataIndex]) : ''; }, label:function(ctx){ return fa(ctx.parsed.x) + ' فروش'; } } } },
                scales:{
                    x:{ beginAtZero:true, grace:'10%', border:{display:false}, grid:{color:c.grid,drawTicks:false}, ticks:{ color:c.text, font:{size:fs}, precision:0, padding:10, maxTicksLimit:narrow() ? 4 : 6, callback:function(v){ return fa(v); } } },
                    y:{ border:{display:false}, grid:{display:false}, afterFit:function(scale){ if (scale.width < axisNeed) scale.width = axisNeed; }, ticks:{ color:c.text, font:{size:fs}, autoSkip:false, crossAlign:'far', padding:12, callback:function(val){ return this.getLabelForValue(val); } } }
                } }
        });
    }

    function buildGroup(){
        var el = document.getElementById('groupChart'); if(!el) return;
        var c = colors();
        var fs = tick();
        var ac = accent();
        var maxGroup = 0;
        for (var i = 1; i < GROUP_DATA.length; i++){ if ((Number(GROUP_DATA[i]) || 0) > (Number(GROUP_DATA[maxGroup]) || 0)) maxGroup = i; }
        var groupColors = GROUP_DATA.map(function (v, i) { return i === maxGroup ? ac : c.neutral; });
        if (groupc) groupc.destroy();
        groupc = new Chart(el, {
            type:'bar',
            data:{ labels: GROUP_LABELS, datasets:[{ label:'کاربران', data: GROUP_DATA, backgroundColor: groupColors, hoverBackgroundColor:ac, borderRadius:7, borderSkipped:false, maxBarThickness:20, categoryPercentage:0.65, barPercentage:0.8 }]},
            plugins:[{id:'groupCounts',afterDatasetsDraw:function(chart){var ctx=chart.ctx;ctx.save();ctx.font='600 12px Vazirmatn, Tahoma, sans-serif';ctx.textAlign='left';ctx.textBaseline='middle';ctx.fillStyle=getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim();chart.getDatasetMeta(0).data.forEach(function(bar,index){ctx.fillText(fa(GROUP_DATA[index]),bar.x+9,bar.y);});ctx.restore();}}],
            options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', animation:{duration:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 650}, interaction:{mode:'nearest',axis:'y',intersect:false},
                layout:{ padding:{ left:4, right:44, top:8, bottom:4 } },
                plugins:{ legend:{display:false}, tooltip:{ rtl:true, textDirection:'rtl', backgroundColor:c.tipBg, titleColor:c.text, bodyColor:c.text, borderColor:c.tipBorder, borderWidth:1, padding:12, cornerRadius:8, displayColors:false, caretPadding:8, titleFont:{size:12.5}, bodyFont:{size:12.5}, titleMarginBottom:6, position:'nearest', callbacks:{ title:function(items){ return items.length ? String(items[0].label) : ''; }, label:function(ctx){ return fa(ctx.parsed.x) + ' کاربر'; } } } },
                scales:{ x:{ beginAtZero:true, grace:'10%', border:{display:false}, grid:{color:c.grid,drawTicks:false}, ticks:{ color:c.text, font:{size:fs}, precision:0, padding:10, maxTicksLimit:narrow() ? 4 : 6, callback:function(v){ return fa(v); } } }, y:{ border:{display:false}, grid:{display:false}, afterFit:function(scale){ var need = textPx(longest(GROUP_LABELS), fs) + 22; if (scale.width < need) scale.width = need; }, ticks:{ color:c.text, font:{size:fs}, autoSkip:false, crossAlign:'far', padding:12, callback:function(val){ return this.getLabelForValue(val); } } } } }
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
