<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/extra_filter.php';
require_once __DIR__ . '/lib/csrf.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

fx_csrf_guard();

if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $requestedIds = $_POST['ids'] ?? [];
    $deletedCount = fx_bulk_delete_ids($pdo, 'Payment_report', 'id', $requestedIds);
    fx_bulk_delete_redirect('payment.php', count($requestedIds), $deletedCount);
}

$payQ = trim((string)($_GET['q'] ?? ''));
$df = fx_date_filter_resolve();
$payStatusOptions = [
    'paid'    => 'پرداخت موفق',
    'Unpaid'  => 'ناموفق',
    'expire'  => 'منقضی شده',
    'reject'  => 'رد شده',
    'cancelled' => 'لغو شده',
    'waiting' => 'در انتظار تایید',
];
$payStatus = fx_status_filter_current();
$payAf = fx_amount_filter_resolve();
$payMethod = fx_extra_text_param('method', 400);
$payMethodLabels = [
    'cart to cart'         => 'کارت به کارت',
    'low balance by admin' => 'کسر توسط ادمین',
    'add balance by admin' => 'افزایش توسط ادمین',
    'Currency Rial 1'      => 'درگاه ریالی ۱',
    'Currency Rial tow'    => 'درگاه ریالی ۲',
    'Currency Rial 3'      => 'درگاه ریالی ۳',
    'aqayepardakht'        => 'آقای پرداخت',
    'zarinpal'             => 'زرین‌پال',
    'plisio'               => 'Plisio',
    'arze digital offline' => 'ارز دیجیتال آفلاین',
    'Star Telegram'        => 'استارز تلگرام',
    'nowpayment'           => 'NowPayment',
];
$payMethodOptions = [];
try {
    $payMethodStmt = $pdo->query("SELECT DISTINCT Payment_Method FROM Payment_report WHERE Payment_Method IS NOT NULL AND Payment_Method <> '' LIMIT 200");
    foreach ($payMethodStmt->fetchAll(PDO::FETCH_COLUMN) as $pmv) {
        $pmv = (string)$pmv;
        $payMethodOptions[$pmv] = $payMethodLabels[$pmv] ?? $pmv;
    }
} catch (\Throwable $e) {
}
if ($payMethod !== '' && !isset($payMethodOptions[$payMethod])) {
    $payMethodOptions[$payMethod] = $payMethodLabels[$payMethod] ?? $payMethod;
}
asort($payMethodOptions, SORT_STRING);
$payExtraKeep = array_merge(fx_amount_filter_keep($payAf), ['method' => $payMethod !== '' ? $payMethod : null]);

$whereSql = '1=1';
$whereParams = [];
if ($payQ !== '') {
    $payLike = '%' . $payQ . '%';
    $whereSql .= ' AND (id_order LIKE :l1 OR id_user LIKE :l2)';
    $whereParams[':l1'] = $payLike;
    $whereParams[':l2'] = $payLike;
}
$whereSql .= fx_date_filter_sql_mixed_named('time', $df['from'], $df['to'], $whereParams, 'd');
$whereSql .= fx_status_filter_sql('payment_Status', $payStatus, $payStatusOptions, $whereParams, ':statusVal');
$whereSql .= fx_amount_filter_sql('price', $payAf, $whereParams, 'am');
if ($payMethod !== '') {
    $whereSql .= ' AND Payment_Method = :pmethod';
    $whereParams[':pmethod'] = $payMethod;
}

$payStatusActive = $payStatus !== '' && isset($payStatusOptions[$payStatus]);
$payFilterActive = $payQ !== '' || $df['active'] || $payStatusActive || $payAf['active'] || $payMethod !== '';
$payDateKeep = fx_filter_delete_date_params('', $df['active']);
$payFilterParams = array_merge(['q' => $payQ !== '' ? $payQ : null, 'status' => $payStatusActive ? $payStatus : null], $payDateKeep, $payExtraKeep);
$payExtraCriteria = array_filter([
    'مبلغ' => $payAf['label'],
    'روش پرداخت' => $payMethod !== '' ? ($payMethodOptions[$payMethod] ?? $payMethod) : '',
], function ($v) { return $v !== ''; });
$payFilterCriteria = array_merge(fx_filter_delete_criteria($payStatusActive ? $payStatusOptions[$payStatus] : '', $df, $payQ), $payExtraCriteria);
if (fx_filter_delete_requested()) {
    [$fdMatched, $fdDeleted] = $payFilterActive ? fx_filter_delete_where($pdo, 'Payment_report', $whereSql, $whereParams) : [0, 0];
    fx_filter_delete_redirect('payment.php', $payFilterParams, $fdMatched, $fdDeleted);
}

$pg = fx_paginate($pdo, "SELECT COUNT(*) FROM Payment_report WHERE $whereSql", $whereParams, 5);

$query = $pdo->prepare("SELECT * FROM Payment_report WHERE $whereSql ORDER BY time DESC LIMIT :perPage OFFSET :offset");
foreach ($whereParams as $k => $v) $query->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
$query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$query->execute();
$listpayment = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تراکنش‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=filterdrawer111">
<script src="js/theme.js?v=dark62" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list fx-page-payments">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <svg class="svg-icon svg-lg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="none" d="M21.9883291,10.9947074 L21.9888849,16.275793 C21.9888849,17.7383249 20.8471803,18.9341973 19.4064072,19.0207742 L19.2388849,19.025793 L4.76104885,19.025793 C3.29851702,19.025793 2.10264457,17.8840884 2.01606765,16.4433154 L2.01104885,16.275793 L2.01032912,10.9947074 L21.9883291,10.9947074 Z M18.2529045,14.5 L15.7529045,14.5 L15.6511339,14.5068466 C15.2850584,14.556509 15.0029045,14.8703042 15.0029045,15.25 C15.0029045,15.6296958 15.2850584,15.943491 15.6511339,15.9931534 L15.7529045,16 L18.2529045,16 L18.3546751,15.9931534 C18.7207506,15.943491 19.0029045,15.6296958 19.0029045,15.25 C19.0029045,14.8703042 18.7207506,14.556509 18.3546751,14.5068466 L18.2529045,14.5 Z M19.2388849,5.0207074 C20.7014167,5.0207074 21.8972891,6.162412 21.9838661,7.60318507 L21.9888849,7.7707074 L21.9883291,9.4947074 L2.01032912,9.4947074 L2.01104885,7.7707074 C2.01104885,6.30817556 3.15275345,5.11230312 4.59352652,5.02572619 L4.76104885,5.0207074 L19.2388849,5.0207074 Z"/></svg>
                        گزارش تراکنش‌ها
                    </div>
                    <div class="page-head__sub">آرشیو پرداخت‌های انجام شده</div>
                </div>
            </div>

            <?php echo fx_bulk_delete_flash_html(); ?>

            <?php echo fx_filter_delete_flash_html(); ?>

            <div class="invoice-overview">
                <div class="invoice-overview__main"><span class="invoice-overview__icon" aria-hidden="true"><?php echo icon('coins', 'svg-icon'); ?></span><div><span><?php echo $payFilterActive ? 'تراکنش‌های مطابق فیلتر' : 'کل تراکنش‌های ثبت‌شده'; ?></span><strong><?php echo strtr(number_format((int)$pg['total']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?><small>تراکنش</small></strong></div></div>
                <div class="invoice-overview__detail"><span>وضعیت انتخاب‌شده</span><b><?php echo htmlspecialchars($payStatusActive ? $payStatusOptions[$payStatus] : 'همهٔ وضعیت‌ها', ENT_QUOTES, 'UTF-8'); ?></b></div>
                <div class="invoice-overview__detail"><span>بازهٔ نمایش</span><b><?php echo htmlspecialchars($df['active'] ? $df['label'] : 'تمام تاریخ‌ها', ENT_QUOTES, 'UTF-8'); ?></b></div>
                <?php if ($payFilterActive): ?><a href="payment.php" class="btn btn-outline btn-sm invoice-reset">پاک‌کردن فیلترها</a><?php endif; ?>
            </div>

            <details class="wrapper fx-page-list fx-filter-drawer fx-page-payments">
                <summary class="fx-filter-drawer__summary">
                    <span class="fx-filter-drawer__icon" aria-hidden="true"><?php echo icon('filter', 'svg-icon'); ?></span>
                    <span class="fx-filter-drawer__text"><strong>جستجو و فیلترها</strong><small>جستجو، وضعیت و بازهٔ زمانی</small></span>
                    <span class="fx-filter-drawer__action"><span class="fx-filter-drawer__show">نمایش فیلترها</span><span class="fx-filter-drawer__hide">بستن فیلترها</span></span>
                    <span class="fx-filter-drawer__chevron" aria-hidden="true"><?php echo icon('chevron-down', 'svg-icon'); ?></span>
                </summary>
            <?php echo fx_search_ui('payment.php', $payQ, array_merge(['status' => $payStatus !== '' ? $payStatus : null], $payDateKeep, $payExtraKeep), 'جستجو در آیدی کاربر یا کد پیگیری…'); ?>

            <?php echo fx_status_filter_ui('payment.php', $payStatusOptions, $payStatus, array_merge(['q' => $payQ !== '' ? $payQ : null], $payDateKeep, $payExtraKeep)); ?>

            <?php $fxFd = fx_filter_delete_parts('payment.php', $payFilterParams, $payFilterActive ? (int)$pg['total'] : 0, $payFilterCriteria); ?>
            <?php echo fx_date_filter_ui('payment.php', '', array_merge(['q' => $payQ !== '' ? $payQ : null, 'status' => $payStatus !== '' ? $payStatus : null], $payExtraKeep), '', $fxFd['button'], $fxFd['form'], true); ?>
            </details>

            <div class="card invoice-results">
                <div class="invoice-results__head"><div><h2>آرشیو تراکنش‌ها</h2><p>جزئیات مبلغ، روش پرداخت و وضعیت تراکنش</p></div><span class="invoice-page-count"><?php echo strtr(number_format(count($listpayment)), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> تراکنش در این صفحه</span></div>
                <form method="POST" action="payment.php" id="bulk-form">
                <?php echo fx_csrf_field(); ?>
                <input type="hidden" name="action" value="bulk_delete">
                <div class="table-wrap">
                    <table id="paymentTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all" onclick="faoximaToggleAll(this)"></th>
                                <th>آیدی کاربر</th>
                                <th>کد پیگیری</th>
                                <th>مبلغ (تومان)</th>
                                <th>زمان</th>
                                <th>روش پرداخت</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listpayment as $list):
                            $method = $list['Payment_Method'];
                            if (is_string($method) && isset($payMethodLabels[$method])) $method = $payMethodLabels[$method];
                            $statusText = $list['payment_Status']; $badgeClass = 'badge-gray';
                            switch ($list['payment_Status']) {
                                case 'paid':    $statusText='پرداخت موفق';    $badgeClass='badge-success'; break;
                                case 'Unpaid':  $statusText='ناموفق';        $badgeClass='badge-danger';  break;
                                case 'expire':  $statusText='منقضی شده';     $badgeClass='badge-gray';    break;
                                case 'reject':  $statusText='رد شده';        $badgeClass='badge-danger';  break;
                                case 'cancelled': $statusText='لغو شده';     $badgeClass='badge-gray';    break;
                                case 'waiting': $statusText='در انتظار تایید'; $badgeClass='badge-warning'; break;
                            }
                        ?>
                            <tr data-detail-row data-detail-title="پرداخت <?php echo htmlspecialchars($list['id_order'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo (int)$list['id']; ?>"></label></td>
                                <td data-label="آیدی کاربر">
                                    <a href="user.php?id=<?php echo $list['id_user']; ?>" class="text-link">
                                        <?php echo $list['id_user']; ?>
                                    </a>
                                </td>
                                <td data-label="کد پیگیری" data-summary="1">
                                    <span class="track-id invoice-reference" onclick="copyToClipboard('<?php echo addslashes($list['id_order']); ?>')" title="کپی کردن">
                                        <?php echo htmlspecialchars($list['id_order'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td data-label="مبلغ (تومان)"><span class="invoice-amount"><bdi><?php echo number_format($list['price']); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                <td data-label="زمان" style="direction:ltr; text-align:right;"><span class="invoice-date"><?php echo htmlspecialchars($list['time'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="روش پرداخت"><span class="payment-method"><?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="وضعیت" data-summary="1"><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    $payKeep = array_merge(['q' => $payQ !== '' ? $payQ : null], $payExtraKeep);
                    foreach (['dpreset', 'ddays', 'dfrom', 'dto', 'status'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $payKeep[$dk] = $_GET[$dk];
                    }
                    echo fx_pager_html($pg['page'], $pg['pages'], $pg['total'], count($listpayment), 'payment.php', $payKeep);
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف تراکنش‌های انتخاب‌شده مطمئن هستید؟')">
                        <?php echo icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                </form>
            </div>

        </div>
    </section>
</section>
<script src="js/bulk-select.js?v=fx2"></script>
<script>
  function faoximaToggleAll(master, formId) {
    var scope = formId ? document.getElementById(formId) : document;
    scope.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(function(cb) {
      if (cb.checked === master.checked) return;
      cb.checked = master.checked;
      cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }
  FxBulkSelect.init({
    scope: 'default',
    checkboxSelector: 'input[name="ids[]"]',
    scopeRoot: document.getElementById('bulk-form'),
    formEl: document.getElementById('bulk-form'),
    deleteButtonSelector: '.js-bulk-delete-btn',
    clearOnQueryFlags: ['bulk']
  });
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
            alert('کد پیگیری کپی شد: ' + text);
        });
    }
}
</script>
</body>
</html>


