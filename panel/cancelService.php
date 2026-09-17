<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/../function.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

/* ---------- helpers ---------- */
function fx_table_has_col(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
        $st->execute([':t' => $table, ':c' => $col]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) { return false; }
}
/* normalized status -> [label, badgeClass, filterKey] */
function fx_req_status($raw): array {
    $r = strtolower(trim((string)$raw));
    $raws = (string)$raw;
    if (in_array($r, ['unpaid','waiting','awaitinghash','pending'], true) || strpos($r,'wait')!==false || mb_strpos($raws,'در انتظار')!==false) return ['در انتظار', 'badge-warning', 'wait'];
    if ($r === 'paid' || $r === 'success' || mb_strpos($raws,'تایید')!==false || mb_strpos($raws,'موفق')!==false) return ['تاییدشده', 'badge-active', 'ok'];
    if ($r === 'reject' || $r === 'rejected' || mb_strpos($raws,'رد')!==false || mb_strpos($raws,'لغو')!==false) return ['ردشده', 'badge-block', 'reject'];
    if ($r === 'expire' || $r === 'expired' || mb_strpos($raws,'منقضی')!==false) return ['منقضی', 'badge-gray', 'expire'];
    return [$raws !== '' ? $raws : '—', 'badge-gray', 'other'];
}
/* request "type" label from id_order + method */
function fx_req_type($idOrder, $method): string {
    $io = (string)$idOrder; $m = strtolower(trim((string)$method));
    if ($m === 'cart to cart' || $m === 'carttocart_pv') return 'رسید بانکی';
    if ($m === 'add balance by admin' || $m === 'low balance by admin') return 'تنظیم کیف پول (ادمین)';
    if (in_array($m, ['plisio','nowpayment','digitaltron','arze digital offline'], true)) return 'ارز دیجیتال';
    if ($m === 'star telegram') return 'استارز تلگرام';
    if (strpos($io,'Add_Balance')!==false) return 'افزایش موجودی';
    if (strpos($io,'getconfigafterpay')!==false) return 'خرید سرویس';
    if (strpos($io,'getextenduser')!==false) return 'تمدید سرویس';
    if (strpos($io,'getextravolumeuser')!==false) return 'حجم اضافه';
    if (strpos($io,'getextratimeuser')!==false) return 'زمان اضافه';
    if ($m !== '') return 'پرداخت (' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ')';
    return 'پرداخت';
}
function fx_purpose($idOrder): string {
    $io = (string)$idOrder;
    if (strpos($io,'Add_Balance')!==false) return 'افزایش موجودی کیف پول';
    if (strpos($io,'getconfigafterpay')!==false) return 'خرید سرویس';
    if (strpos($io,'getextenduser')!==false) return 'تمدید سرویس';
    if (strpos($io,'getextravolumeuser')!==false) return 'حجم اضافه';
    if (strpos($io,'getextratimeuser')!==false) return 'زمان اضافه';
    return '';
}
function fx_method_label($method): string {
    $m = strtolower(trim((string)$method));
    if ($m === '') return '';
    $map = [
        'cart to cart'         => 'کارت به کارت',
        'carttocart_pv'        => 'کارت به کارت (پیروزپی)',
        'piroozpay'            => 'پیروزپی',
        'iranpay1'             => 'ایران‌پی',
        'currency rial 3'      => 'ایران‌پی',
        'plisio'               => 'ارز دیجیتال (Plisio)',
        'nowpayment'           => 'ارز دیجیتال (NOWPayments)',
        'digitaltron'          => 'ارز دیجیتال (دیجیتال‌ترون)',
        'arze digital offline' => 'ارز دیجیتال (آفلاین)',
        'star telegram'        => 'استارز تلگرام',
        'add balance by admin' => 'افزایش موجودی توسط ادمین',
        'low balance by admin' => 'کاهش موجودی توسط ادمین',
    ];
    return $map[$m] ?? (string)$method;
}
function fx_req_ts($value): int {
    $v = trim((string)$value);
    if ($v === '') return 0;
    if (ctype_digit($v)) return (int)$v;
    $ts = strtotime(str_replace('/', '-', $v));
    return $ts !== false ? $ts : 0;
}
function fx_invoice_username($idInvoice): string {
    $s = (string)$idInvoice;
    if (strpos($s, '|') === false) return '';
    $s = explode('|', $s, 2)[1];
    $s = preg_replace('/%[0-9a-fA-F]+$/', '', $s);
    return trim((string)$s);
}

$flash = ['ok' => '', 'err' => ''];

if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $requestedTokens = $_POST['ids'] ?? [];
    $prIds = []; $csIds = [];
    foreach ($requestedTokens as $token) {
        if (preg_match('/^pr:(\d+)$/', (string)$token, $m)) $prIds[] = (int)$m[1];
        elseif (preg_match('/^cs:(\d+)$/', (string)$token, $m)) $csIds[] = (int)$m[1];
    }
    $deletedCount = 0;
    if ($prIds) {
        $ph = implode(',', array_fill(0, count($prIds), '?'));
        $st = $pdo->prepare("SELECT id, payment_Status FROM Payment_report WHERE id IN ($ph)");
        $st->execute($prIds);
        $allowedPr = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (in_array(strtolower((string)$row['payment_Status']), ['reject', 'rejected', 'expire', 'expired'], true)) {
                $allowedPr[] = (int)$row['id'];
            }
        }
        if ($allowedPr) $deletedCount += fx_bulk_delete_ids($pdo, 'Payment_report', 'id', $allowedPr);
    }
    if ($csIds) $deletedCount += fx_bulk_delete_ids($pdo, 'cancel_service', 'id', $csIds);
    fx_bulk_delete_redirect('cancelService.php', count($requestedTokens), $deletedCount);
}

/* ---------- delete actions ---------- */
// legacy: delete a cancel_service row
if (isset($_GET['removeid']) && $_GET['removeid'] !== '') {
    try {
        $stmt = $pdo->prepare("DELETE FROM cancel_service WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['removeid']]);
    } catch (\Throwable $e) {}
    header("Location: cancelService.php");
    exit;
}
// delete a resolved (reject/expire) payment request record (cleanup only)
if (isset($_GET['delpr']) && $_GET['delpr'] !== '') {
    try {
        $cur = $pdo->prepare("SELECT payment_Status FROM Payment_report WHERE id = :id LIMIT 1");
        $cur->execute([':id' => (int)$_GET['delpr']]);
        $st = strtolower((string)$cur->fetchColumn());
        if (in_array($st, ['reject','rejected','expire','expired'], true)) {
            $pdo->prepare("DELETE FROM Payment_report WHERE id = :id")->execute([':id' => (int)$_GET['delpr']]);
        }
    } catch (\Throwable $e) {}
    header("Location: cancelService.php");
    exit;
}

/* ---------- aggregate all requests ---------- */
$prHasSource = fx_table_has_col($pdo, 'Payment_report', 'source');
$reqQ = trim((string)($_GET['q'] ?? ''));
$reqLike = '%' . $reqQ . '%';
$rows = [];

// 1) Payment_report — admin-actionable money requests (bank receipts, wallet charges, crypto, gateway)
try {
    if ($reqQ !== '') {
        $st = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user LIKE :q1 OR id_order LIKE :q2 OR id_invoice LIKE :q3 ORDER BY id DESC LIMIT 1000");
        $st->execute([':q1' => $reqLike, ':q2' => $reqLike, ':q3' => $reqLike]);
    } else {
        $st = $pdo->query("SELECT * FROM Payment_report ORDER BY id DESC LIMIT 1000");
    }
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        $method = (string)($r['Payment_Method'] ?? '');
        // skip purely automatic/empty rows with no actionable method
        [$stLabel, $stClass, $stKey] = fx_req_status($r['payment_Status'] ?? '');
        $src = $prHasSource ? (string)($r['source'] ?? '') : '';
        $purpose = fx_purpose($r['id_order'] ?? '');
        if ($purpose === '') $purpose = fx_purpose($r['id_invoice'] ?? '');
        $svcUser = fx_invoice_username($r['id_invoice'] ?? '');
        $details = [];
        if ($method !== '') $details[] = 'روش: ' . fx_method_label($method);
        if ($purpose !== '') $details[] = $purpose;
        if ($svcUser !== '') $details[] = 'سرویس: ' . $svcUser;
        if ($stKey === 'reject' && !empty($r['dec_not_confirmed'])) $details[] = (string)$r['dec_not_confirmed'];
        $rows[] = [
            'sortid'  => 2000000000 + (int)($r['id'] ?? 0),
            'sortts'  => fx_req_ts($r['time'] ?? ''),
            'idlabel' => '#' . (int)($r['id'] ?? 0),
            'type'    => fx_req_type($r['id_order'] ?? '', $method),
            'source'  => ($src === 'miniapp') ? 'مینی‌اپ' : 'ربات',
            'user'    => (string)($r['id_user'] ?? ''),
            'details' => implode(' — ', $details),
            'amount'  => (int)($r['price'] ?? 0),
            'date'    => (string)($r['time'] ?? ''),
            'stLabel' => $stLabel, 'stClass' => $stClass, 'stKey' => $stKey,
            'del'     => in_array($stKey, ['reject','expire'], true) ? ('cancelService.php?delpr=' . (int)($r['id'] ?? 0)) : '',
            'pk'      => 'pr:' . (int)($r['id'] ?? 0),
        ];
    }
} catch (\Throwable $e) { $flash['err'] = 'بارگذاری درخواست‌های پرداخت ناموفق: ' . $e->getMessage(); }

// 2) cancel_service — service cancel requests
try {
    if ($reqQ !== '') {
        $st = $pdo->prepare("SELECT * FROM cancel_service WHERE id_user LIKE :q1 OR username LIKE :q2 ORDER BY id DESC LIMIT 2000");
        $st->execute([':q1' => $reqLike, ':q2' => $reqLike]);
    } else {
        $st = $pdo->query("SELECT * FROM cancel_service ORDER BY id DESC LIMIT 2000");
    }
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        [$stLabel, $stClass, $stKey] = fx_req_status($r['status'] ?? '');
        $rows[] = [
            'sortid'  => 1000000000 + (int)($r['id'] ?? 0),
            'sortts'  => 0,
            'idlabel' => 'C' . (int)($r['id'] ?? 0),
            'type'    => 'لغو سرویس',
            'source'  => 'ربات',
            'user'    => (string)($r['id_user'] ?? ''),
            'details' => 'سرویس: ' . (string)($r['username'] ?? '') . (trim((string)($r['description'] ?? '')) !== '' ? ' — ' . (string)$r['description'] : ''),
            'amount'  => 0,
            'date'    => '',
            'stLabel' => $stLabel, 'stClass' => $stClass, 'stKey' => $stKey,
            'del'     => 'cancelService.php?removeid=' . (int)($r['id'] ?? 0),
            'pk'      => 'cs:' . (int)($r['id'] ?? 0),
        ];
    }
} catch (\Throwable $e) {}

// 3) national-net refund requests (invoice waiting for admin approval)
try {
    if ($reqQ !== '') {
        $st = $pdo->prepare("SELECT id_invoice, id_user, username, name_product, price_product, time_sell FROM invoice WHERE Status = 'nm_refund_pending' AND (id_user LIKE :q1 OR username LIKE :q2 OR id_invoice LIKE :q3) ORDER BY id DESC LIMIT 500");
        $st->execute([':q1' => $reqLike, ':q2' => $reqLike, ':q3' => $reqLike]);
    } else {
        $st = $pdo->query("SELECT id_invoice, id_user, username, name_product, price_product, time_sell FROM invoice WHERE Status = 'nm_refund_pending' ORDER BY id DESC LIMIT 500");
    }
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        $refundTs = fx_req_ts($r['time_sell'] ?? '');
        $rows[] = [
            'sortid'  => 1500000000,
            'sortts'  => $refundTs,
            'idlabel' => (string)($r['id_invoice'] ?? ''),
            'type'    => 'بازگشت وجه (نت ملی)',
            'source'  => 'ربات',
            'user'    => (string)($r['id_user'] ?? ''),
            'details' => 'سرویس: ' . (string)($r['username'] ?? '') . ' — ' . (string)($r['name_product'] ?? ''),
            'amount'  => (int)($r['price_product'] ?? 0),
            'date'    => $refundTs > 0 ? date('Y/m/d H:i:s', $refundTs) : '',
            'stLabel' => 'در انتظار', 'stClass' => 'badge-warning', 'stKey' => 'wait',
            'del'     => '',
            'pk'      => '',
        ];
    }
} catch (\Throwable $e) {}

// newest first
usort($rows, function ($a, $b) {
    $t = ($b['sortts'] ?? 0) <=> ($a['sortts'] ?? 0);
    if ($t !== 0) return $t;
    return ($b['sortid'] ?? 0) <=> ($a['sortid'] ?? 0);
});
$df = fx_date_filter_resolve();
if ($df['active']) {
    $rows = array_values(array_filter($rows, function ($r) use ($df) {
        if ((int)($r['sortts'] ?? 0) === 0) return true;
        return fx_date_filter_matches_ts((int)$r['sortts'], $df['from'], $df['to']);
    }));
}

$reqStatusOptions = [
    'wait'   => 'در انتظار',
    'ok'     => 'تاییدشده',
    'reject' => 'ردشده',
    'expire' => 'منقضی',
    'other'  => 'سایر',
];
$reqStatus = fx_status_filter_current();
if ($reqStatus !== '') {
    $rows = array_values(array_filter($rows, function ($r) use ($reqStatus) {
        return fx_status_filter_matches($reqStatus, (string)($r['stKey'] ?? ''));
    }));
}

foreach ($rows as $i => $r) {
    $rows[$i]['idlabel'] = (string)($i + 1);
}

$reqPerPage = 5;
$reqTotal = count($rows);
$reqPages = max(1, (int)ceil($reqTotal / $reqPerPage));
$reqPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
if ($reqPage > $reqPages) $reqPage = $reqPages;
$rows = array_slice($rows, ($reqPage - 1) * $reqPerPage, $reqPerPage);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>لیست درخواست‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
<script src="js/theme.js?v=flat5" defer></script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <svg class="svg-icon svg-lg" viewBox="0 -4.5 31 31" aria-hidden="true"><g fill="currentColor" stroke="none"><g transform="translate(-206,-626)" fill="currentColor"><path d="M235,643 L216,643 C214.896,643 214,643.896 214,645 C214,646.104 214.896,647 216,647 L235,647 C236.104,647 237,646.104 237,645 C237,643.896 236.104,643 235,643 L235,643 Z M235,635 L216,635 C214.896,635 214,635.896 214,637 C214,638.104 214.896,639 216,639 L235,639 C236.104,639 237,638.104 237,637 C237,635.896 236.104,635 235,635 L235,635 Z M216,631 L235,631 C236.104,631 237,630.104 237,629 C237,627.896 236.104,627 235,627 L216,627 C214.896,627 214,627.896 214,629 C214,630.104 214.896,631 216,631 L216,631 Z M209,642 C207.343,642 206,643.343 206,645 C206,646.657 207.343,648 209,648 C210.657,648 212,646.657 212,645 C212,643.343 210.657,642 209,642 L209,642 Z M209,634 C207.343,634 206,635.343 206,637 C206,638.657 207.343,640 209,640 C210.657,640 212,638.657 212,637 C212,635.343 210.657,634 209,634 L209,634 Z M209,626 C207.343,626 206,627.343 206,629 C206,630.657 207.343,632 209,632 C210.657,632 212,630.657 212,629 C212,627.343 210.657,626 209,626 L209,626 Z"/></g></g></svg>
                        لیست درخواست‌ها
                    </div>
                    <div class="page-head__sub">همه‌ی درخواست‌های کاربران (رسید بانکی، افزایش موجودی، ارز دیجیتال، تمدید، حجم/زمان اضافه، لغو سرویس و بازگشت وجه) از ربات و مینی‌اپ</div>
                </div>
            </div>

            <?php if ($flash['err']): ?>
                <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($flash['err'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php echo fx_bulk_delete_flash_html(); ?>

            <?php echo fx_search_ui('cancelService.php', $reqQ, ['status' => $reqStatus !== '' ? $reqStatus : null], 'جستجو در آیدی کاربر، شناسه سفارش یا کد پیگیری…'); ?>

            <?php echo fx_status_filter_ui('cancelService.php', $reqStatusOptions, $reqStatus, ['q' => $reqQ !== '' ? $reqQ : null]); ?>

            <?php echo fx_date_filter_ui('cancelService.php', '', ['q' => $reqQ !== '' ? $reqQ : null, 'status' => $reqStatus !== '' ? $reqStatus : null]); ?>

            <div class="card">
                <form method="POST" action="cancelService.php" id="bulk-form">
                <input type="hidden" name="action" value="bulk_delete">
                <div class="table-wrap">
                    <table id="requestsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all" onclick="faoximaToggleAll(this)"></th>
                                <th>شناسه</th>
                                <th>نوع درخواست</th>
                                <th>منبع</th>
                                <th>آیدی کاربر</th>
                                <th>جزئیات</th>
                                <th>مبلغ (T)</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                                <th data-no-sort="1">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr data-detail-row data-detail-title="<?php echo htmlspecialchars($row['idlabel'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <label class="fx-check-row">
                                    <?php if ($row['pk'] !== ''): ?>
                                        <input type="checkbox" name="ids[]" value="<?php echo htmlspecialchars($row['pk'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php else: ?>
                                        <input type="checkbox" disabled title="این ردیف قابل حذف نیست">
                                    <?php endif; ?>
                                    </label>
                                </td>
                                <td data-label="شناسه" style="direction:ltr; text-align:right;" data-summary="1"><?php echo htmlspecialchars($row['idlabel'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="نوع درخواست" data-filter-value="<?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="منبع" data-filter-value="<?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?>"><span class="badge badge-info"><?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="آیدی کاربر">
                                    <?php if ($row['user'] !== ''): ?>
                                        <a href="user.php?id=<?php echo htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8'); ?>" class="text-link"><?php echo htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td data-label="جزئیات" style="max-width:340px;"><?php echo htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="مبلغ (T)"><?php echo $row['amount'] > 0 ? number_format($row['amount']) : '—'; ?></td>
                                <td data-label="تاریخ" style="direction:ltr; text-align:right; font-size:11.5px;"><?php echo $row['date'] !== '' ? htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                <td data-label="وضعیت" data-filter-value="<?php echo htmlspecialchars($row['stLabel'], ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><span class="badge <?php echo $row['stClass']; ?>"><?php echo htmlspecialchars($row['stLabel'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <?php if ($row['del'] !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($row['del'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-soft-danger" onclick="return confirm('این درخواست حذف شود؟')">
                                            <?php echo icon('trash', 'svg-icon'); ?> حذف
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:11px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    $reqKeep = ['q' => $reqQ !== '' ? $reqQ : null];
                    foreach (['dpreset', 'ddays', 'dfrom', 'dto', 'status'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $reqKeep[$dk] = $_GET[$dk];
                    }
                    echo fx_pager_html($reqPage, $reqPages, $reqTotal, count($rows), 'cancelService.php', $reqKeep);
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف درخواست‌های انتخاب‌شده مطمئن هستید؟')">
                        <?php echo icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                <div class="page-head__sub" style="margin-top:12px;">
                    <?php echo icon('lightbulb','svg-icon svg-sm'); ?> تأیید/رد نهاییِ پرداخت‌ها و افزایش/کاهش موجودی همچنان از داخل ربات انجام می‌شود (تا حساب‌داری دوبار اعمال نشود)؛ این صفحه همه‌ی درخواست‌ها را برای مشاهده، جست‌وجو و فیلتر یک‌جا نمایش می‌دهد.
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
