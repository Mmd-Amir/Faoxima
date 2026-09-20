<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
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
    'waiting' => 'در انتظار تایید',
];
$payStatus = fx_status_filter_current();

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

$pg = fx_paginate($pdo, "SELECT COUNT(*) FROM Payment_report WHERE $whereSql", $whereParams, 5);

$query = $pdo->prepare("SELECT * FROM Payment_report WHERE $whereSql ORDER BY time DESC LIMIT :perPage OFFSET :offset");
foreach ($whereParams as $k => $v) $query->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
$query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$query->execute();
$listpayment = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تراکنش‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
<script src="js/theme.js?v=flat5" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list">

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

            <?php echo fx_search_ui('payment.php', $payQ, ['status' => $payStatus !== '' ? $payStatus : null], 'جستجو در آیدی کاربر یا کد پیگیری…'); ?>

            <?php echo fx_status_filter_ui('payment.php', $payStatusOptions, $payStatus, ['q' => $payQ !== '' ? $payQ : null]); ?>

            <?php echo fx_date_filter_ui('payment.php', '', ['q' => $payQ !== '' ? $payQ : null, 'status' => $payStatus !== '' ? $payStatus : null]); ?>

            <div class="card">
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
                                <th>مبلغ (T)</th>
                                <th>زمان</th>
                                <th>روش پرداخت</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listpayment as $list):
                            $method = $list['Payment_Method'];
                            switch ($method) {
                                case 'cart to cart':         $method = 'کارت به کارت'; break;
                                case 'low balance by admin': $method = 'کسر توسط ادمین'; break;
                                case 'add balance by admin': $method = 'افزایش توسط ادمین'; break;
                                case 'Currency Rial 1':      $method = 'درگاه ریالی ۱'; break;
                                case 'Currency Rial tow':    $method = 'درگاه ریالی ۲'; break;
                                case 'Currency Rial 3':      $method = 'درگاه ریالی ۳'; break;
                                case 'aqayepardakht':        $method = 'آقای پرداخت'; break;
                                case 'zarinpal':             $method = 'زرین‌پال'; break;
                                case 'plisio':               $method = 'Plisio'; break;
                                case 'arze digital offline': $method = 'ارز دیجیتال آفلاین'; break;
                                case 'Star Telegram':        $method = 'استارز تلگرام'; break;
                                case 'nowpayment':           $method = 'NowPayment'; break;
                            }
                            $statusText = $list['payment_Status']; $badgeClass = 'badge-gray';
                            switch ($list['payment_Status']) {
                                case 'paid':    $statusText='پرداخت موفق';    $badgeClass='badge-success'; break;
                                case 'Unpaid':  $statusText='ناموفق';        $badgeClass='badge-danger';  break;
                                case 'expire':  $statusText='منقضی شده';     $badgeClass='badge-gray';    break;
                                case 'reject':  $statusText='رد شده';        $badgeClass='badge-danger';  break;
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
                                    <span class="track-id" onclick="copyToClipboard('<?php echo addslashes($list['id_order']); ?>')" title="کپی کردن">
                                        <?php echo htmlspecialchars($list['id_order'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td data-label="مبلغ (T)"><?php echo number_format($list['price']); ?></td>
                                <td data-label="زمان" style="direction:ltr; text-align:right;"><?php echo htmlspecialchars($list['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="روش پرداخت"><?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="وضعیت" data-summary="1"><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    $payKeep = ['q' => $payQ !== '' ? $payQ : null];
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


