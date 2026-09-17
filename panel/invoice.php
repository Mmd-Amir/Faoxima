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

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $requestedIds = $_POST['ids'] ?? [];
    $deletedCount = fx_bulk_delete_ids($pdo, 'invoice', 'id_invoice', $requestedIds, false);
    fx_bulk_delete_redirect('invoice.php', count($requestedIds), $deletedCount);
}

$invQ = trim((string)($_GET['q'] ?? ''));
$df = fx_date_filter_resolve();
$invStatusOptions = [
    'unpaid'        => 'در انتظار پرداخت',
    'active'        => 'فعال',
    'disabledn'     => 'ناموجود در پنل',
    'end_of_time'   => 'اتمام زمان',
    'end_of_volume' => 'اتمام حجم',
    'sendedwarn'    => 'هشدار پایانی',
    'send_on_hold'  => 'خطای اتصال',
    'removebyuser'  => 'حذف توسط کاربر',
    'removebyadmin' => 'حذف توسط ادمین',
];
$invStatus = fx_status_filter_current();

$whereSql = '1=1';
$whereParams = [];
if ($invQ !== '') {
    $invLike = '%' . $invQ . '%';
    $whereSql .= ' AND (id_invoice LIKE :l1 OR id_user LIKE :l2 OR username LIKE :l3 OR name_product LIKE :l4)';
    $whereParams[':l1'] = $invLike;
    $whereParams[':l2'] = $invLike;
    $whereParams[':l3'] = $invLike;
    $whereParams[':l4'] = $invLike;
}
if ($df['active']) {
    $whereSql .= ' AND time_sell BETWEEN :dfrom AND :dto';
    $whereParams[':dfrom'] = $df['from'];
    $whereParams[':dto'] = $df['to'];
}
$whereSql .= fx_status_filter_sql('Status', $invStatus, $invStatusOptions, $whereParams, ':statusVal');

$pg = fx_paginate($pdo, "SELECT COUNT(*) FROM invoice WHERE $whereSql", $whereParams, 5);

$query = $pdo->prepare("SELECT * FROM invoice WHERE $whereSql ORDER BY id_invoice DESC LIMIT :perPage OFFSET :offset");
foreach ($whereParams as $k => $v) $query->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
$query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$query->execute();
$listinvoice = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت سفارشات | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
<script src="js/theme.js?v=flat5" defer>

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
                        <svg class="svg-icon svg-lg" viewBox="0 0 512 512" aria-hidden="true"><path fill="currentColor" stroke="none" d="M302.793,440.856c0-1.925,0.096-3.861,0.286-5.834c0.868-8.667,3.013-16.371,6.216-23.006c2.174-4.529,4.891-8.533,7.942-12.032H35.753c-6.292,0-12.518,1.373-17.829,4.405c-5.32,3.06-9.83,7.522-13.166,14.234c-2.212,4.49-3.881,10.049-4.577,16.989C0.057,436.853,0,438.073,0,439.274c0,4.577,0.848,8.876,2.441,13.004c1.601,4.119,3.947,8.066,6.96,11.718c5.996,7.294,14.711,13.338,24.321,16.904c6.398,2.393,13.157,3.68,19.678,3.709h-0.01l269.158,0.152c-5.597-5.244-10.316-11.403-13.748-18.334C304.986,458.714,302.793,450.029,302.793,440.856z"/><path fill="currentColor" stroke="none" d="M511.276,27.22H152.364c0.162,22.815,0.668,46.764,0.668,71.82c0,43.581-1.583,90.345-9.573,139.522c-7.484,46.098-20.622,94.32-43.256,143.907h256.477v0.134c8.914,0.114,17.685,2.364,25.37,6.664c7.837,4.395,14.597,11.022,18.726,19.612c2.573,5.387,3.785,11.345,3.785,17.333c0,4.93-0.83,9.907-2.651,14.606c-1.83,4.701-4.691,9.162-8.819,12.69c-6.026,5.158-13.339,7.628-20.308,7.598c-6.092,0-11.947-1.783-16.913-4.976c-4.977-3.194-9.115-7.838-11.575-13.586l16.093-6.903c1.012,2.355,2.727,4.339,4.939,5.749c2.193,1.421,4.824,2.212,7.456,2.212c3.022-0.02,6.016-0.954,8.952-3.423c1.563-1.335,2.908-3.251,3.852-5.702c0.954-2.431,1.478-5.339,1.478-8.266c0-3.565-0.792-7.102-2.069-9.753c-2.422-5.025-6.417-9.057-11.518-11.928c-5.082-2.851-11.221-4.452-17.419-4.433c-6.14-0.01-12.29,1.526-17.639,4.681c-5.358,3.175-9.991,7.894-13.357,14.835c-2.231,4.634-3.871,10.287-4.557,17.161c-0.144,1.382-0.2,2.746-0.2,4.081c0,6.388,1.496,12.338,4.195,17.81c2.697,5.473,6.616,10.449,11.46,14.654c5.997,5.225,13.366,9.2,21.308,11.451l8.771,0.009c8.524-0.028,14.559-1.553,20.222-4.08c5.635-2.527,10.983-6.293,17.066-11.155c24.121-19.23,42.951-43.123,57.729-70.409c14.777-27.286,25.428-57.958,33.006-90.326C509.245,244.073,512.02,172.662,512,108.717C512,79.59,511.447,52.036,511.276,27.22z M205.774,83.147h136.024c0.314,31.072,0.162,63.926-2.918,98.248H202.847C205.928,147.073,206.09,114.218,205.774,83.147z M417.794,329.717H174.665v-20.431h243.129V329.717z M436.805,252.882H193.676V232.46h243.129V252.882z"/></svg>
                        لیست سفارشات
                    </div>
                    <div class="page-head__sub">آرشیو کامل سفارشات کاربران</div>
                </div>
            </div>

            <?php echo fx_bulk_delete_flash_html(); ?>

            <?php echo fx_search_ui('invoice.php', $invQ, ['status' => $invStatus !== '' ? $invStatus : null], 'جستجو در شناسه سفارش، آیدی کاربر، نام کانفیگ یا محصول…'); ?>

            <?php echo fx_status_filter_ui('invoice.php', $invStatusOptions, $invStatus, ['q' => $invQ !== '' ? $invQ : null]); ?>

            <?php echo fx_date_filter_ui('invoice.php', '', ['q' => $invQ !== '' ? $invQ : null, 'status' => $invStatus !== '' ? $invStatus : null]); ?>

            <div class="card">
                <form method="POST" action="invoice.php" id="bulk-form">
                <input type="hidden" name="action" value="bulk_delete">
                <div class="table-wrap">
                    <table id="invoiceTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all" onclick="faoximaToggleAll(this)"></th>
                                <th>شناسه سفارش</th>
                                <th>آیدی کاربر</th>
                                <th>نام کانفیگ</th>
                                <th>لوکیشن</th>
                                <th>محصول</th>
                                <th>تاریخ سفارش</th>
                                <th>مبلغ (T)</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listinvoice as $list):
                            $time_sell = $list['time_sell'];
                            if (is_numeric($time_sell)) $time_sell = jdate('Y/m/d | H:i', $time_sell);
                            $statusText = 'نامشخص';
                            $statusClass = 'badge-gray';
                            switch ($list['Status']) {
                                case 'unpaid':         $statusText = 'در انتظار پرداخت'; $statusClass = 'badge-unpaid'; break;
                                case 'active':         $statusText = 'فعال';            $statusClass = 'badge-active'; break;
                                case 'disabledn':      $statusText = 'ناموجود در پنل';   $statusClass = 'badge-gray';   break;
                                case 'end_of_time':    $statusText = 'اتمام زمان';      $statusClass = 'badge-danger'; break;
                                case 'end_of_volume':  $statusText = 'اتمام حجم';       $statusClass = 'badge-danger'; break;
                                case 'sendedwarn':     $statusText = 'هشدار پایانی';    $statusClass = 'badge-warning';break;
                                case 'send_on_hold':   $statusText = 'خطای اتصال';      $statusClass = 'badge-info';   break;
                                case 'removebyuser':   $statusText = 'حذف توسط کاربر';   $statusClass = 'badge-gray';   break;
                                case 'removebyadmin':  $statusText = 'حذف توسط ادمین';   $statusClass = 'badge-danger'; break;
                                default:               $statusText = htmlspecialchars($list['Status'], ENT_QUOTES, 'UTF-8');
                            }
                            $price = ($list['price_product'] == 0) ? 'رایگان' : number_format($list['price_product']);
                        ?>
                            <tr data-detail-row data-detail-title="سفارش <?php echo htmlspecialchars((string)$list['id_invoice'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo htmlspecialchars($list['id_invoice'], ENT_QUOTES, 'UTF-8'); ?>"></label></td>
                                <td data-label="شناسه سفارش" data-summary="1"><?php echo $list['id_invoice']; ?></td>
                                <td data-label="آیدی کاربر">
                                    <a href="user.php?id=<?php echo $list['id_user']; ?>" class="text-link">
                                        <?php echo $list['id_user']; ?>
                                    </a>
                                </td>
                                <td data-label="نام کانفیگ" style="direction:ltr; text-align:right;">
                                    <?php echo htmlspecialchars($list['username'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td data-label="لوکیشن"><?php echo htmlspecialchars($list['Service_location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="محصول" data-summary="1"><?php echo htmlspecialchars($list['name_product'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="تاریخ سفارش" style="direction:ltr; text-align:right;"><?php echo $time_sell; ?></td>
                                <td data-label="مبلغ (T)"><?php echo $price; ?></td>
                                <td data-label="وضعیت" data-summary="1"><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    $invKeep = ['q' => $invQ !== '' ? $invQ : null];
                    foreach (['dpreset', 'ddays', 'dfrom', 'dto', 'status'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $invKeep[$dk] = $_GET[$dk];
                    }
                    echo fx_pager_html($pg['page'], $pg['pages'], $pg['total'], count($listinvoice), 'invoice.php', $invKeep);
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف سفارشات انتخاب‌شده مطمئن هستید؟')">
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
</script>
</body>
</html>


