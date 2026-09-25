<?php
date_default_timezone_set('Asia/Tehran');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/extra_filter.php';
require_once __DIR__ . '/lib/csrf.php';

if (!function_exists('rxReceiptJdate')) {
    function rxReceiptJdate($raw): string
    {
        $s = trim((string) $raw);
        if ($s === '') return '';
        $ts = ctype_digit($s) ? (int) $s : strtotime($s);
        if ($ts === false || $ts <= 0) return $s;
        return jdate('Y/m/d H:i:s', $ts, '', 'Asia/Tehran', 'fa');
    }
}

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

fx_csrf_guard();

if (isset($_GET['media']) && $_GET['media'] !== '') {
    $mediaOrder = (string) $_GET['media'];
    $mediaReport = rxReceiptGet($mediaOrder, false);
    if (!$mediaReport) {
        http_response_code(404);
        exit;
    }

    $mediaFileId = (string) ($mediaReport['card_photo_file_id'] ?? '');
    if ($mediaFileId === '') {
        http_response_code(404);
        exit;
    }

    $f = telegram('getFile', ['file_id' => $mediaFileId]);
    if (empty($f['ok'])) {
        http_response_code(404);
        exit;
    }

    $mediaFilePath = $f['result']['file_path'] ?? '';
    if ($mediaFilePath === '') {
        http_response_code(404);
        exit;
    }

    $bin = @file_get_contents("https://api.telegram.org/file/bot{$APIKEY}/{$mediaFilePath}");
    if ($bin === false || strlen($bin) === 0) {
        http_response_code(502);
        exit;
    }

    $mediaExt = strtolower(pathinfo($mediaFilePath, PATHINFO_EXTENSION));
    $mediaContentType = $mediaExt === 'png' ? 'image/png' : ($mediaExt === 'gif' ? 'image/gif' : 'image/jpeg');
    header('Content-Type: ' . $mediaContentType);
    header('Cache-Control: private, max-age=3600');
    echo $bin;
    exit;
}

$rxFlash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_rx_action'])) {
    $rxAction = (string) $_POST['_rx_action'];
    $rxOrderId = (string) ($_POST['id_order'] ?? '');
    $rxActorLabel = (string) ($_SESSION['user'] ?? '');
    $rxKeepQs = (string) ($_POST['_keep_qs'] ?? '');

    if ($rxAction === 'bulk_delete') {
        $rxRequestedIds = $_POST['ids'] ?? [];
        $rxRequestedIds = is_array($rxRequestedIds) ? array_values(array_unique(array_filter(array_map('strval', $rxRequestedIds), function ($v) { return $v !== ''; }))) : [];
        $rxDeletedCount = 0;
        foreach ($rxRequestedIds as $rxBulkOrderId) {
            if (rxReceiptHardDelete($rxBulkOrderId)) {
                $rxDeletedCount++;
            }
        }
        $rxRequestedCount = count($rxRequestedIds);
        if ($rxRequestedCount === 0) {
            $rxFlash = ['type' => 'err', 'text' => 'هیچ رسیدی انتخاب نشده بود.'];
        } elseif ($rxDeletedCount === 0) {
            $rxFlash = ['type' => 'err', 'text' => 'هیچ‌کدام از رسیدهای انتخاب‌شده قابل حذف نبودند.'];
        } elseif ($rxDeletedCount === $rxRequestedCount) {
            $rxFlash = ['type' => 'ok', 'text' => 'تعداد ' . number_format($rxDeletedCount) . ' رسید با موفقیت حذف شد.'];
        } else {
            $rxFlash = ['type' => 'ok', 'text' => 'از ' . number_format($rxRequestedCount) . ' رسید انتخاب‌شده، ' . number_format($rxDeletedCount) . ' مورد حذف شد و ' . number_format($rxRequestedCount - $rxDeletedCount) . ' مورد به‌دلیل تغییر وضعیت یا نامعتبر بودن حذف نشد.'];
        }
        $_SESSION['_rx_flash'] = $rxFlash;
        $rxBulkFlag = 'bulk=' . ($rxDeletedCount > 0 ? 'ok' : 'err');
        $rxRedirectQs = '?' . ($rxKeepQs !== '' ? ($rxKeepQs . '&' . $rxBulkFlag) : $rxBulkFlag);
        header('Location: receipts.php' . $rxRedirectQs);
        exit;
    }

    if ($rxOrderId === '') {
        $rxFlash = ['type' => 'err', 'text' => 'درخواست نامعتبر است.'];
    } elseif ($rxAction === 'confirm') {
        $res = rxReceiptConfirm($rxOrderId, ['actor_label' => $rxActorLabel]);
        if ($res['ok']) {
            $rxFlash = ['type' => 'ok', 'text' => 'رسید با موفقیت تایید شد.'];
        } elseif (($res['reason'] ?? '') === 'fulfillment_failed') {
            $rxFlash = ['type' => 'err', 'text' => 'پرداخت به‌صورت نهایی تایید نشد و رسید برای بررسی مجدد به حالت در انتظار بازگشت.'];
        } elseif (in_array($res['reason'] ?? '', ['race_lost', 'already_paid', 'already_final', 'not_waiting'], true)) {
            $rxFlash = ['type' => 'err', 'text' => 'این رسید قبلاً بررسی شده یا وضعیت آن تغییر کرده است.'];
        } elseif (($res['reason'] ?? '') === 'purchase_receipts_pending') {
            $rxFlash = ['type' => 'err', 'text' => 'ابتدا رسیدهای خرید یا تمدید این کاربر را بررسی و تایید کنید.'];
        } else {
            $rxFlash = ['type' => 'err', 'text' => 'تکمیل عملیات با خطا مواجه شد، دوباره تلاش کنید.'];
        }
    } elseif ($rxAction === 'reject') {
        $rxReason = trim((string) ($_POST['reason'] ?? ''));
        if ($rxReason === '') {
            $rxFlash = ['type' => 'err', 'text' => 'برای رد کردن رسید باید دلیل را وارد کنید.'];
        } else {
            $res = rxReceiptReject($rxOrderId, $rxReason, ['actor_label' => $rxActorLabel]);
            $rxFlash = $res['ok']
                ? ['type' => 'ok', 'text' => 'رسید با موفقیت رد شد.']
                : ['type' => 'err', 'text' => 'این رسید قبلاً بررسی شده یا وضعیت آن تغییر کرده است.'];
        }
    } elseif ($rxAction === 'reopen') {
        $res = rxReceiptReopen($rxOrderId);
        $rxFlash = $res['ok']
            ? ['type' => 'ok', 'text' => 'رسید دوباره در انتظار بررسی قرار گرفت.']
            : ['type' => 'err', 'text' => 'این رسید قابل بازگردانی نیست.'];
    } elseif ($rxAction === 'delete') {
        $ok = rxReceiptHardDelete($rxOrderId);
        $rxFlash = $ok
            ? ['type' => 'ok', 'text' => 'رسید با موفقیت حذف شد.']
            : ['type' => 'err', 'text' => 'حذف رسید ممکن نشد.'];
    }

    $_SESSION['_rx_flash'] = $rxFlash;
    $redirectQs = $rxKeepQs !== '' ? ('?' . $rxKeepQs) : '';
    header('Location: receipts.php' . $redirectQs);
    exit;
}

if (isset($_SESSION['_rx_flash'])) {
    $rxFlash = $_SESSION['_rx_flash'];
    unset($_SESSION['_rx_flash']);
}

$rxQ = trim((string) ($_GET['q'] ?? ''));
$rxDf = fx_date_filter_resolve();
$rxStatusOptions = [
    'waiting'    => 'در انتظار بررسی',
    'processing' => 'در حال پردازش',
    'paid'       => 'تایید شده',
    'reject'     => 'رد شده',
    'expire'     => 'منقضی شده',
    'cancelled'  => 'لغو شده',
];
$rxStatus = fx_status_filter_current();
$rxAf = fx_amount_filter_resolve();
$rxTypeOptions = [
    'getconfigafterpay'  => 'خرید سرویس',
    'getextenduser'      => 'تمدید سرویس',
    'getextravolumeuser' => 'افزایش حجم',
    'getextratimeuser'   => 'افزایش زمان',
    'wallet'             => 'شارژ کیف پول',
];
$rxType = fx_extra_text_param('otype', 40);
if (!isset($rxTypeOptions[$rxType])) $rxType = '';
$rxExtraKeep = array_merge(fx_amount_filter_keep($rxAf), ['otype' => $rxType !== '' ? $rxType : null]);

$rxWhereSql = rxReceiptScopeSql();
$rxWhereParams = [];
if ($rxQ !== '') {
    $rxLike = '%' . $rxQ . '%';
    $rxWhereSql .= ' AND (id_order LIKE :l1 OR id_user LIKE :l2 OR card_last4 LIKE :l3)';
    $rxWhereParams[':l1'] = $rxLike;
    $rxWhereParams[':l2'] = $rxLike;
    $rxWhereParams[':l3'] = $rxLike;
}
$rxWhereSql .= fx_date_filter_sql_mixed_named('time', $rxDf['from'], $rxDf['to'], $rxWhereParams, 'd');
$rxWhereSql .= fx_status_filter_sql('payment_Status', $rxStatus, $rxStatusOptions, $rxWhereParams, ':statusVal');
$rxWhereSql .= fx_amount_filter_sql('price', $rxAf, $rxWhereParams, 'am');
if ($rxType === 'wallet') {
    $rxWhereSql .= " AND (id_invoice IS NULL OR CAST(SUBSTRING_INDEX(id_invoice, '|', 1) AS BINARY) NOT IN ('getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'))";
} elseif ($rxType !== '') {
    $rxWhereSql .= " AND CAST(SUBSTRING_INDEX(id_invoice, '|', 1) AS BINARY) = :otype";
    $rxWhereParams[':otype'] = $rxType;
}

$rxStatusActive = $rxStatus !== '' && isset($rxStatusOptions[$rxStatus]);
$rxFilterActive = $rxQ !== '' || $rxDf['active'] || $rxStatusActive || $rxAf['active'] || $rxType !== '';
$rxDateKeep = fx_filter_delete_date_params('', $rxDf['active']);
$rxFilterParams = array_merge(['q' => $rxQ !== '' ? $rxQ : null, 'status' => $rxStatusActive ? $rxStatus : null], $rxDateKeep, $rxExtraKeep);
$rxExtraCriteria = array_filter([
    'مبلغ' => $rxAf['label'],
    'نوع سفارش' => $rxType !== '' ? $rxTypeOptions[$rxType] : '',
], function ($v) { return $v !== ''; });
$rxFilterCriteria = array_merge(fx_filter_delete_criteria($rxStatusActive ? $rxStatusOptions[$rxStatus] : '', $rxDf, $rxQ), $rxExtraCriteria);
if (fx_filter_delete_requested()) {
    $fdMatched = 0;
    $fdDeleted = 0;
    if ($rxFilterActive) {
        $fdOrderIds = fx_filter_delete_collect($pdo, 'Payment_report', 'id_order', $rxWhereSql, $rxWhereParams);
        $fdMatched = count($fdOrderIds);
        foreach ($fdOrderIds as $fdOrderId) {
            if (rxReceiptHardDelete($fdOrderId)) {
                $fdDeleted++;
            }
        }
    }
    fx_filter_delete_redirect('receipts.php', $rxFilterParams, $fdMatched, $fdDeleted);
}

$rxPg = fx_paginate($pdo, "SELECT COUNT(*) FROM Payment_report WHERE $rxWhereSql", $rxWhereParams, 20);

$rxListStmt = $pdo->prepare("SELECT * FROM Payment_report WHERE $rxWhereSql ORDER BY id DESC LIMIT :perPage OFFSET :offset");
foreach ($rxWhereParams as $k => $v) $rxListStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
$rxListStmt->bindValue(':perPage', $rxPg['perPage'], PDO::PARAM_INT);
$rxListStmt->bindValue(':offset', $rxPg['offset'], PDO::PARAM_INT);
$rxListStmt->execute();
$rxRows = $rxListStmt->fetchAll(PDO::FETCH_ASSOC);

$rxPendingCount = (int) $pdo->query("SELECT COUNT(*) FROM Payment_report WHERE " . rxReceiptScopeSql() . " AND payment_Status = 'waiting'")->fetchColumn();

$rxTodayStart = strtotime(date('Y-m-d 00:00:00'));
$rxConfirmedTodayStmt = $pdo->prepare(
    "SELECT COUNT(*), COALESCE(SUM(price),0) FROM Payment_report WHERE " . rxReceiptScopeSql() . " AND payment_Status = 'paid' "
    . "AND ((`time` REGEXP '^[0-9]+$' AND CAST(`time` AS UNSIGNED) >= :t1) OR (`time` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(`time`, '/', '-'), '%Y-%m-%d %H:%i:%s') >= FROM_UNIXTIME(:t2)))"
);
$rxConfirmedTodayStmt->execute([':t1' => $rxTodayStart, ':t2' => $rxTodayStart]);
$rxConfirmedTodayRow = $rxConfirmedTodayStmt->fetch(PDO::FETCH_NUM);
$rxConfirmedTodayCount = (int) ($rxConfirmedTodayRow[0] ?? 0);
$rxConfirmedTodayAmount = (int) ($rxConfirmedTodayRow[1] ?? 0);

$rxRejectedTodayStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM Payment_report WHERE " . rxReceiptScopeSql() . " AND payment_Status = 'reject' "
    . "AND ((`time` REGEXP '^[0-9]+$' AND CAST(`time` AS UNSIGNED) >= :t1) OR (`time` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(`time`, '/', '-'), '%Y-%m-%d %H:%i:%s') >= FROM_UNIXTIME(:t2)))"
);
$rxRejectedTodayStmt->execute([':t1' => $rxTodayStart, ':t2' => $rxTodayStart]);
$rxRejectedTodayCount = (int) $rxRejectedTodayStmt->fetchColumn();

$rxStatusMeta = [
    'waiting'    => ['label' => 'در انتظار بررسی', 'class' => 'badge-warning'],
    'processing' => ['label' => 'در حال پردازش',   'class' => 'badge-info'],
    'paid'       => ['label' => 'تایید شده',        'class' => 'badge-success'],
    'reject'     => ['label' => 'رد شده',           'class' => 'badge-danger'],
    'expire'     => ['label' => 'منقضی شده',        'class' => 'badge-gray'],
    'cancelled'  => ['label' => 'لغو شده',          'class' => 'badge-gray'],
];

$rxKeepQsArr = array_merge(['q' => $rxQ !== '' ? $rxQ : null, 'status' => $rxStatus !== '' ? $rxStatus : null], $rxExtraKeep);
foreach (['dpreset', 'ddays', 'dfrom', 'dto', 'p'] as $dk) {
    if (isset($_GET[$dk]) && $_GET[$dk] !== '') $rxKeepQsArr[$dk] = $_GET[$dk];
}
$rxKeepQsString = http_build_query(array_filter($rxKeepQsArr, function ($v) { return $v !== null; }));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>رسیدهای پرداخت | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat38">
    <script src="js/theme.js?v=flat50" defer></script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list receipt-page">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('receipt', 'svg-icon svg-lg'); ?>
                        رسیدهای پرداخت کارت به کارت
                    </div>
                    <div class="page-head__sub">مدیریت و بررسی رسیدهای پرداخت کارت به کارت</div>
                </div>
            </div>

            <?php if ($rxFlash): ?>
                <div class="alert <?php echo $rxFlash['type'] === 'ok' ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo htmlspecialchars($rxFlash['text'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php echo fx_filter_delete_flash_html(); ?>

            <div class="receipt-summary-grid">
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('hourglass', 'svg-icon svg-sm'); ?> در انتظار بررسی</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxPendingCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('check', 'svg-icon svg-sm'); ?> تایید شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxConfirmedTodayCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('xmark', 'svg-icon svg-sm'); ?> رد شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxRejectedTodayCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('coins', 'svg-icon svg-sm'); ?> مبلغ تایید شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxConfirmedTodayAmount); ?></div>
                </div>
            </div>

            <?php echo fx_search_ui('receipts.php', $rxQ, array_merge(['status' => $rxStatus !== '' ? $rxStatus : null], $rxDateKeep, $rxExtraKeep), 'جستجو در کد پیگیری، آیدی کاربر یا ۴ رقم آخر کارت…'); ?>

            <?php echo fx_status_filter_ui('receipts.php', $rxStatusOptions, $rxStatus, array_merge(['q' => $rxQ !== '' ? $rxQ : null], $rxDateKeep, $rxExtraKeep)); ?>

            <?php $fxFd = fx_filter_delete_parts('receipts.php', $rxFilterParams, $rxFilterActive ? (int)$rxPg['total'] : 0, $rxFilterCriteria); ?>
            <?php echo fx_date_filter_ui('receipts.php', '', array_merge(['q' => $rxQ !== '' ? $rxQ : null, 'status' => $rxStatus !== '' ? $rxStatus : null], $rxExtraKeep), '', $fxFd['button'], $fxFd['form']); ?>

            <div class="card">
                <form method="POST" action="receipts.php" id="rx-bulk-form">
                <?php echo fx_csrf_field(); ?>
                <input type="hidden" name="_rx_action" value="bulk_delete">
                <input type="hidden" name="_keep_qs" value="<?php echo htmlspecialchars($rxKeepQsString, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="table-wrap">
                    <table class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="rx-check-all" onclick="rxToggleAll(this)"></th>
                                <th>وضعیت</th>
                                <th>کاربر</th>
                                <th>کد پیگیری</th>
                                <th>مبلغ (T)</th>
                                <th>نوع سفارش</th>
                                <th>۴ رقم آخر کارت</th>
                                <th>تاریخ</th>
                                <th data-no-sort="1">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rxRows as $row):
                            $st = (string) $row['payment_Status'];
                            $meta = $rxStatusMeta[$st] ?? ['label' => $st, 'class' => 'badge-gray'];
                            $typeRaw = explode('|', (string) $row['id_invoice']);
                            $typeLabels = [
                                'getconfigafterpay' => 'خرید سرویس',
                                'getextenduser' => 'تمدید سرویس',
                                'getextravolumeuser' => 'افزایش حجم',
                                'getextratimeuser' => 'افزایش زمان',
                            ];
                            $typeLabel = $typeLabels[$typeRaw[0]] ?? 'شارژ کیف پول';
                            $isWaiting = ($st === 'waiting');
                            $isReject = ($st === 'reject');
                            $rowKeepQs = $rxKeepQsString;
                        ?>
                            <tr data-detail-row data-detail-title="رسید <?php echo htmlspecialchars((string) $row['id_order'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo htmlspecialchars((string) $row['id_order'], ENT_QUOTES, 'UTF-8'); ?>"></label></td>
                                <td data-label="وضعیت" data-summary="1"><span class="badge <?php echo $meta['class']; ?>"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="کاربر">
                                    <a href="user.php?id=<?php echo urlencode((string) $row['id_user']); ?>" class="text-link"><?php echo htmlspecialchars((string) $row['id_user'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </td>
                                <td data-label="کد پیگیری" data-summary="1">
                                    <span class="track-id" onclick="rxCopy('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>')" title="کپی کردن"><?php echo htmlspecialchars((string) $row['id_order'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td data-label="مبلغ (T)" data-summary="1"><?php echo number_format((int) $row['price']); ?></td>
                                <td data-label="نوع سفارش"><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="۴ رقم آخر کارت" style="direction:ltr; text-align:right;"><?php echo $row['card_last4'] !== null && $row['card_last4'] !== '' ? htmlspecialchars((string) $row['card_last4'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                <td data-label="تاریخ" style="direction:ltr; text-align:right;"><?php echo htmlspecialchars(rxReceiptJdate($row['time']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <?php
                                    $rxViewRow = $row;
                                    unset($rxViewRow['card_photo_file_id']);
                                    $rxViewRow['time'] = rxReceiptJdate($row['time'] ?? '');
                                    $rxViewRow['at_updated'] = rxReceiptJdate($row['at_updated'] ?? '');
                                    ?>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="rxOpenView(<?php echo htmlspecialchars(json_encode($rxViewRow, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <?php echo icon('eye', 'svg-icon svg-sm'); ?>
                                    </button>
                                    <?php if ($isWaiting): ?>
                                        <button type="button" class="btn btn-soft-success btn-sm" onclick="rxOpenConfirm('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo number_format((int) $row['price']); ?>', '<?php echo htmlspecialchars(addslashes((string) $row['id_user']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($rowKeepQs, ENT_QUOTES, 'UTF-8'); ?>')">
                                            <?php echo icon('circle-check', 'svg-icon svg-sm'); ?>
                                        </button>
                                        <button type="button" class="btn btn-soft-danger btn-sm" onclick="rxOpenReject('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($rowKeepQs, ENT_QUOTES, 'UTF-8'); ?>')">
                                            <?php echo icon('xmark', 'svg-icon svg-sm'); ?>
                                        </button>
                                    <?php elseif ($isReject): ?>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="rxOpenReopen('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($rowKeepQs, ENT_QUOTES, 'UTF-8'); ?>')">
                                            <?php echo icon('arrow-right-arrow-left', 'svg-icon svg-sm'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rxRows)): ?>
                            <tr><td colspan="9" style="text-align:center; padding: 24px;">رسیدی برای نمایش یافت نشد.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <?php
                    $rxPagerKeep = $rxKeepQsArr;
                    unset($rxPagerKeep['p']);
                    echo fx_pager_html($rxPg['page'], $rxPg['pages'], $rxPg['total'], count($rxRows), 'receipts.php', $rxPagerKeep);
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="submit" class="btn btn-soft-danger btn-sm js-rx-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف رسیدهای انتخاب‌شده مطمئن هستید؟')">
                        <?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                </form>
            </div>

        </div>
    </section>
</section>

<div id="modal-rx-view" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo icon('receipt', 'svg-icon svg-sm'); ?> جزئیات رسید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-view')">&times;</button>
        </div>
        <div class="modal-body receipt-detail-body" id="rx-view-body"></div>
    </div>
</div>

<div id="modal-rx-photo-preview" class="modal-overlay receipt-photo-preview-overlay">
    <div class="modal-box receipt-photo-preview-box">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo icon('receipt', 'svg-icon svg-sm'); ?> تصویر رسید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-photo-preview')">&times;</button>
        </div>
        <div class="modal-body receipt-photo-preview-body">
            <img id="rx-photo-preview-img" src="" alt="تصویر رسید">
        </div>
    </div>
</div>

<div id="modal-rx-confirm" class="modal-overlay">
    <div class="modal-box" style="max-width: 460px;">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo icon('circle-check', 'svg-icon svg-sm'); ?> تایید رسید پرداخت</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-confirm')">&times;</button>
        </div>
        <form method="POST" action="receipts.php" id="rx-confirm-form">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_rx_action" value="confirm">
            <input type="hidden" name="id_order" id="rx-confirm-order-id" value="">
            <input type="hidden" name="_keep_qs" id="rx-confirm-keep-qs" value="">
            <div class="modal-body">
                <p>آیا از تایید این رسید پرداخت مطمئن هستید؟</p>
                <div class="detail-sheet__field"><span class="detail-sheet__field-label">مبلغ</span><span class="detail-sheet__field-value" id="rx-confirm-price"></span></div>
                <div class="detail-sheet__field"><span class="detail-sheet__field-label">آیدی کاربر</span><span class="detail-sheet__field-value" id="rx-confirm-user"></span></div>
                <div class="detail-sheet__field"><span class="detail-sheet__field-label">کد پیگیری</span><span class="detail-sheet__field-value" id="rx-confirm-order"></span></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-rx-confirm')">انصراف</button>
                <button type="submit" class="btn btn-primary" id="rx-confirm-submit">
                    <?php echo icon('circle-check', 'svg-icon svg-sm'); ?>
                    <span>تایید نهایی</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-rx-reject" class="modal-overlay">
    <div class="modal-box" style="max-width: 460px;">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo icon('xmark', 'svg-icon svg-sm'); ?> رد رسید پرداخت</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-reject')">&times;</button>
        </div>
        <form method="POST" action="receipts.php" id="rx-reject-form">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_rx_action" value="reject">
            <input type="hidden" name="id_order" id="rx-reject-order-id" value="">
            <input type="hidden" name="_keep_qs" id="rx-reject-keep-qs" value="">
            <div class="modal-body">
                <label for="rx-reject-reason">دلیل رد کردن</label>
                <textarea name="reason" id="rx-reject-reason" rows="4" class="form-control" required></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-rx-reject')">انصراف</button>
                <button type="submit" class="btn btn-soft-danger" id="rx-reject-submit">
                    <?php echo icon('xmark', 'svg-icon svg-sm'); ?>
                    <span>رد رسید</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-rx-reopen" class="modal-overlay">
    <div class="modal-box" style="max-width: 420px;">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo icon('arrow-right-arrow-left', 'svg-icon svg-sm'); ?> بازگردانی رسید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-reopen')">&times;</button>
        </div>
        <form method="POST" action="receipts.php" id="rx-reopen-form">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_rx_action" value="reopen">
            <input type="hidden" name="id_order" id="rx-reopen-order-id" value="">
            <input type="hidden" name="_keep_qs" id="rx-reopen-keep-qs" value="">
            <div class="modal-body">
                <p>این رسید رد شده دوباره به حالت «در انتظار بررسی» بازمی‌گردد. ادامه می‌دهید؟</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-rx-reopen')">انصراف</button>
                <button type="submit" class="btn btn-primary">بازگردانی</button>
            </div>
        </form>
    </div>
</div>

<script src="js/bulk-select.js?v=fx2"></script>
<script>
function rxCopy(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
            alert('کد پیگیری کپی شد: ' + text);
        });
    }
}

var RX_STATUS_LABELS = {
    waiting: 'در انتظار بررسی', processing: 'در حال پردازش', paid: 'تایید شده',
    reject: 'رد شده', expire: 'منقضی شده', cancelled: 'لغو شده'
};

function rxEsc(s) {
    var d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : String(s);
    return d.innerHTML;
}

function rxOpenView(row, statusLabel) {
    var fields = [
        ['وضعیت', statusLabel],
        ['کد پیگیری', row.id_order],
        ['آیدی کاربر', row.id_user],
        ['مبلغ', Number(row.price || 0).toLocaleString('en-US') + ' تومان'],
        ['روش پرداخت', row.Payment_Method],
        ['شناسه فاکتور', row.id_invoice],
        ['۴ رقم آخر کارت', row.card_last4 || '—'],
        ['زمان ثبت', row.time],
        ['آخرین بروزرسانی', row.at_updated || '—'],
        ['منبع', row.source || '—'],
        ['دلیل رد / یادداشت', row.dec_not_confirmed || '—'],
        ['عملیات پرداخت تکمیل شده', (row.direct_payment_done == 1) ? 'بله' : 'خیر']
    ];
    var html = '';
    for (var i = 0; i < fields.length; i++) {
        html += '<div class="detail-sheet__field"><span class="detail-sheet__field-label">' + rxEsc(fields[i][0]) + '</span><span class="detail-sheet__field-value">' + rxEsc(fields[i][1]) + '</span></div>';
    }
    html += '<div class="receipt-detail-photo-label detail-sheet__field-label">تصویر رسید</div>';
    var mediaUrl = 'receipts.php?media=' + encodeURIComponent(row.id_order);
    html += '<div class="receipt-detail-photo">' +
        '<img src="' + mediaUrl + '" alt="تصویر رسید" loading="lazy" onclick="rxOpenPhotoPreview(this.src)" onerror="rxPhotoLoadFailed(this)">' +
        '<div class="receipt-detail-photo__fallback" style="display:none;">تصویر رسید در دسترس نیست</div>' +
        '</div>';
    document.getElementById('rx-view-body').innerHTML = html;
    openModal('modal-rx-view');
}

function rxPhotoLoadFailed(img) {
    img.style.display = 'none';
    var fallback = img.nextElementSibling;
    if (fallback) fallback.style.display = 'block';
}

function rxOpenPhotoPreview(src) {
    document.getElementById('rx-photo-preview-img').src = src;
    openModal('modal-rx-photo-preview');
}

function rxOpenConfirm(orderId, priceFmt, userId, keepQs) {
    document.getElementById('rx-confirm-order-id').value = orderId;
    document.getElementById('rx-confirm-keep-qs').value = keepQs;
    document.getElementById('rx-confirm-price').textContent = priceFmt + ' تومان';
    document.getElementById('rx-confirm-user').textContent = userId;
    document.getElementById('rx-confirm-order').textContent = orderId;
    openModal('modal-rx-confirm');
}

function rxOpenReject(orderId, keepQs) {
    document.getElementById('rx-reject-order-id').value = orderId;
    document.getElementById('rx-reject-keep-qs').value = keepQs;
    document.getElementById('rx-reject-reason').value = '';
    openModal('modal-rx-reject');
}

function rxOpenReopen(orderId, keepQs) {
    document.getElementById('rx-reopen-order-id').value = orderId;
    document.getElementById('rx-reopen-keep-qs').value = keepQs;
    openModal('modal-rx-reopen');
}

document.getElementById('rx-confirm-form').addEventListener('submit', function () {
    var b = document.getElementById('rx-confirm-submit');
    b.disabled = true;
    b.querySelector('span').textContent = 'در حال ارسال...';
});
document.getElementById('rx-reject-form').addEventListener('submit', function () {
    var b = document.getElementById('rx-reject-submit');
    b.disabled = true;
    b.querySelector('span').textContent = 'در حال ارسال...';
});

function rxToggleAll(master, formId) {
    var scope = formId ? document.getElementById(formId) : document;
    scope.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(function (cb) {
        if (cb.checked === master.checked) return;
        cb.checked = master.checked;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
FxBulkSelect.init({
    scope: 'receipts',
    checkboxSelector: 'input[name="ids[]"]',
    scopeRoot: document.getElementById('rx-bulk-form'),
    formEl: document.getElementById('rx-bulk-form'),
    deleteButtonSelector: '.js-rx-bulk-delete-btn',
    clearOnQueryFlags: ['bulk']
});
</script>

</body>
</html>
