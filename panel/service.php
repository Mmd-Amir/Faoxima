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
    $deletedCount = fx_bulk_delete_ids($pdo, 'service_other', 'id', $requestedIds);
    fx_bulk_delete_redirect('service.php', count($requestedIds), $deletedCount);
}

$svcQ = trim((string)($_GET['q'] ?? ''));
$df = fx_date_filter_resolve();
$svcTypeOptions = [
    'extend_user'          => 'تمدید سرویس',
    'extend_user_by_admin' => 'تمدید توسط ادمین',
    'extra_user'           => 'حجم اضافه',
    'extra_time_user'      => 'زمان اضافه',
    'transfertouser'       => 'انتقال سرویس',
    'extends_not_user'     => 'تمدید (خارج از لیست)',
    'change_location'      => 'تغییر لوکیشن',
];
$svcStatus = fx_status_filter_current();

$whereSql = '1=1';
$whereParams = [];
if ($svcQ !== '') {
    $svcLike = '%' . $svcQ . '%';
    $whereSql .= ' AND (id_user LIKE :l1 OR username LIKE :l2)';
    $whereParams[':l1'] = $svcLike;
    $whereParams[':l2'] = $svcLike;
}
$whereSql .= fx_date_filter_sql_mixed_named('time', $df['from'], $df['to'], $whereParams, 'd');
$whereSql .= fx_status_filter_sql('type', $svcStatus, $svcTypeOptions, $whereParams, ':typeVal');

$pg = fx_paginate($pdo, "SELECT COUNT(*) FROM service_other WHERE $whereSql", $whereParams, 5);

$query = $pdo->prepare("SELECT * FROM service_other WHERE $whereSql ORDER BY id DESC LIMIT :perPage OFFSET :offset");
foreach ($whereParams as $k => $v) $query->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
$query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$query->execute();
$listservices = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>خدمات انجام شده | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
<script src="js/theme.js?v=flat50" defer>

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
                        <svg class="svg-icon svg-lg" viewBox="0 0 138.518 138.518" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M20.547,112.328c2.083,0,3.771,1.691,3.771,3.776c0,2.079-1.688,3.765-3.771,3.765c-2.084,0-3.773-1.686-3.773-3.765C16.774,114.02,18.463,112.328,20.547,112.328z"/><path d="M20.547,78.709c2.083,0,3.771,1.69,3.771,3.775c0,2.08-1.688,3.766-3.771,3.766c-2.084,0-3.773-1.686-3.773-3.766C16.774,80.399,18.463,78.709,20.547,78.709z"/><circle cx="20.547" cy="48.862" r="3.771"/><path d="M14.478,126.589c-2.881,0-5.244-2.354-5.244-5.248v-10.484c0-2.895,2.363-5.247,5.244-5.247h48.603c0-0.011,0-0.011,0-0.022c0-4.422,0.792-8.656,2.169-12.618H14.478c-2.881,0-5.244-2.362-5.244-5.253V77.236c0-2.893,2.363-5.246,5.244-5.246h68.345c5.56-3.126,11.962-4.923,18.779-4.923c5.976,0,11.62,1.408,16.668,3.85l-1.686-9.757c1.708-1.866,2.782-4.331,2.782-7.058V43.617c0-0.117-0.057-0.208-0.057-0.32L108.848,5.211C108.53,2.35,105.654,0,102.446,0h-81.53c-3.207,0-6.09,2.35-6.404,5.211L4.049,43.297c0,0.112-0.055,0.203-0.055,0.32v10.484c0,2.722,1.071,5.192,2.78,7.058L4.049,76.914c0,0.115-0.055,0.208-0.055,0.322v10.485c0,2.719,1.071,5.192,2.78,7.059l-2.725,15.754c0,0.114-0.055,0.207-0.055,0.322v10.484c0,5.784,4.706,10.49,10.484,10.49h58.999c-1.521-1.631-2.91-3.371-4.13-5.242H14.478L14.478,126.589z M9.239,43.617c0-2.893,2.365-5.25,5.246-5.25h94.401c2.885,0,5.248,2.358,5.248,5.25v10.484c0,2.893-2.363,5.25-5.248,5.25H14.478c-2.881,0-5.244-2.357-5.244-5.25V43.617H9.239z"/><path d="M101.603,72.679c-18.178,0-32.919,14.73-32.919,32.92c0,18.178,14.741,32.919,32.919,32.919c18.181,0,32.921-14.741,32.921-32.919C134.523,87.42,119.783,72.679,101.603,72.679z M118.064,110.534h-11.531v11.519h-9.871v-11.519H85.133v-9.883h11.529V89.133h9.871v11.519h11.531V110.534z"/></g></svg>
                        لیست خدمات انجام شده
                    </div>
                    <div class="page-head__sub">گزارش تمدید، انتقال، تغییر لوکیشن و سایر عملیات</div>
                </div>
            </div>

            <?php echo fx_bulk_delete_flash_html(); ?>

            <?php echo fx_search_ui('service.php', $svcQ, ['status' => $svcStatus !== '' ? $svcStatus : null], 'جستجو در شناسه، آیدی کاربر یا نام کانفیگ…'); ?>

            <?php echo fx_status_filter_ui('service.php', $svcTypeOptions, $svcStatus, ['q' => $svcQ !== '' ? $svcQ : null], 'status', 'فیلتر بر اساس نوع خدمت'); ?>

            <?php echo fx_date_filter_ui('service.php', '', ['q' => $svcQ !== '' ? $svcQ : null, 'status' => $svcStatus !== '' ? $svcStatus : null]); ?>

            <div class="card">
                <form method="POST" action="service.php" id="bulk-form">
                <input type="hidden" name="action" value="bulk_delete">
                <div class="table-wrap">
                    <table id="servicesTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all" onclick="faoximaToggleAll(this)"></th>
                                <th>شناسه</th>
                                <th>آیدی کاربر</th>
                                <th>نام کانفیگ</th>
                                <th>تاریخ سفارش</th>
                                <th>قیمت (T)</th>
                                <th>نوع خدمت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listservices as $list):
                            $time = $list['time'];
                            if (is_numeric($time)) $time = jdate('Y/m/d | H:i', $time);
                            $typeText = 'نامشخص'; $badgeClass = 'badge-gray';
                            switch ($list['type']) {
                                case 'extend_user':           $typeText='تمدید سرویس';       $badgeClass='badge-success'; break;
                                case 'extend_user_by_admin':  $typeText='تمدید توسط ادمین';   $badgeClass='badge-info';    break;
                                case 'extra_user':            $typeText='حجم اضافه';         $badgeClass='badge-purple';  break;
                                case 'extra_time_user':       $typeText='زمان اضافه';        $badgeClass='badge-purple';  break;
                                case 'transfertouser':        $typeText='انتقال سرویس';      $badgeClass='badge-warning'; break;
                                case 'extends_not_user':      $typeText='تمدید (خارج از لیست)'; $badgeClass='badge-gray'; break;
                                case 'change_location':       $typeText='تغییر لوکیشن';      $badgeClass='badge-cyan';    break;
                                default:                      $typeText=htmlspecialchars($list['type'], ENT_QUOTES, 'UTF-8');
                            }
                            $price = ($list['price'] == 0) ? 'رایگان' : number_format($list['price']);
                        ?>
                            <tr data-detail-row data-detail-title="خدمت #<?php echo $list['id']; ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo $list['id']; ?>"></label></td>
                                <td data-label="شناسه" data-summary="1"><?php echo $list['id']; ?></td>
                                <td data-label="آیدی کاربر">
                                    <a href="user.php?id=<?php echo $list['id_user']; ?>" class="text-link">
                                        <?php echo $list['id_user']; ?>
                                    </a>
                                </td>
                                <td data-label="نام کانفیگ" style="direction:ltr; text-align:right;" data-summary="1"><?php echo htmlspecialchars($list['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="تاریخ سفارش" style="direction:ltr; text-align:right;"><?php echo $time; ?></td>
                                <td data-label="قیمت (T)"><?php echo $price; ?></td>
                                <td data-label="نوع خدمت" data-summary="1"><span class="badge <?php echo $badgeClass; ?>"><?php echo $typeText; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    $svcKeep = ['q' => $svcQ !== '' ? $svcQ : null];
                    foreach (['dpreset', 'ddays', 'dfrom', 'dto', 'status'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $svcKeep[$dk] = $_GET[$dk];
                    }
                    echo fx_pager_html($pg['page'], $pg['pages'], $pg['total'], count($listservices), 'service.php', $svcKeep);
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف رکوردهای انتخاب‌شده مطمئن هستید؟')">
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


