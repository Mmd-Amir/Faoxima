<?php

require_once __DIR__ . '/../src/bootstrap.php';

date_default_timezone_set('Asia/Tehran');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/extra_filter.php';
require_once __DIR__ . '/lib/csrf.php';
















\Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo);
\Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();

if (isset($_GET['media']) && $_GET['media'] !== '') {
    \Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::fx_receipts_send_media((string) $_GET['media'], $APIKEY);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_rx_action'])) {
    [$_SESSION['_rx_flash'], $redirect] = \Faoxima\Admin\Web\Pages\ReceiptsPage::submit($_POST, (string) ($_SESSION['user'] ?? ''));
    header('Location: ' . $redirect);
    exit;
}
$rxFlash = $_SESSION['_rx_flash'] ?? null;
unset($_SESSION['_rx_flash']);

$receiptDates = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve();
[$rxQ, $rxStatusOptions, $rxExtraKeep, $rxStatus, $rxFilterActive, $rxFilterParams, $rxDateKeep, $rxFilterCriteria, $rxPg, $rxRows, $rxPendingCount, $rxConfirmedTodayCount, $rxConfirmedTodayAmount, $rxRejectedTodayCount, $rxStatusMeta, $rxKeepQsArr, $rxKeepQsString] = \Faoxima\Admin\Web\Pages\ReceiptsPage::view(
    $pdo, $_GET, $receiptDates,
    \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_current(),
    \Faoxima\Admin\Web\Support\ExtraFilter::fx_amount_filter_resolve(),
    \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('otype', 40),
    \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_date_params('', $receiptDates['active']),
    \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_requested(),
);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>رسیدهای پرداخت | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=filterdrawer111">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat39">
    <script src="js/theme.js?v=dark62" defer></script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list receipt-page fx-page-receipts">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon svg-lg'); ?>
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

            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_flash_html(); ?>

            <div class="receipt-summary-grid">
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('hourglass', 'svg-icon svg-sm'); ?> در انتظار بررسی</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxPendingCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon svg-sm'); ?> تایید شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxConfirmedTodayCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?> رد شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxRejectedTodayCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('coins', 'svg-icon svg-sm'); ?> مبلغ تایید شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($rxConfirmedTodayAmount); ?> <small class="currency-unit">تومان</small></div>
                </div>
            </div>

            <details class="wrapper fx-page-list fx-filter-drawer fx-page-receipts">
                <summary class="fx-filter-drawer__summary">
                    <span class="fx-filter-drawer__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('filter', 'svg-icon'); ?></span>
                    <span class="fx-filter-drawer__text"><strong>جستجو و فیلترها</strong><small>جستجو، وضعیت و بازهٔ زمانی</small></span>
                    <span class="fx-filter-drawer__action"><span class="fx-filter-drawer__show">نمایش فیلترها</span><span class="fx-filter-drawer__hide">بستن فیلترها</span></span>
                    <span class="fx-filter-drawer__chevron" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-down', 'svg-icon'); ?></span>
                </summary>
            <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('receipts.php', $rxQ, array_merge(['status' => $rxStatus !== '' ? $rxStatus : null], $rxDateKeep, $rxExtraKeep), 'جستجو در کد پیگیری، آیدی کاربر یا ۴ رقم آخر کارت…'); ?>

            <?php echo \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_ui('receipts.php', $rxStatusOptions, $rxStatus, array_merge(['q' => $rxQ !== '' ? $rxQ : null], $rxDateKeep, $rxExtraKeep)); ?>

            <?php $fxFd = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_parts('receipts.php', $rxFilterParams, $rxFilterActive ? (int)$rxPg['total'] : 0, $rxFilterCriteria); ?>
            <?php echo \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_ui('receipts.php', '', array_merge(['q' => $rxQ !== '' ? $rxQ : null, 'status' => $rxStatus !== '' ? $rxStatus : null], $rxExtraKeep), '', $fxFd['button'], $fxFd['form'], true); ?>
            </details>

            <div class="card invoice-results">
                <div class="invoice-results__head"><div><h2>آرشیو رسیدهای پرداخت</h2><p>بررسی رسیدها، وضعیت تأیید و جزئیات پرداخت کارت به کارت</p></div><span class="invoice-page-count"><?php echo strtr(number_format((int)$rxPg['total']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> رسید<?php echo $rxFilterActive ? ' مطابق فیلتر' : ' ثبت‌شده'; ?></span></div>
                <form method="POST" action="receipts.php" id="rx-bulk-form">
                <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                <input type="hidden" name="_rx_action" value="bulk_delete">
                <input type="hidden" name="_keep_qs" value="<?php echo htmlspecialchars($rxKeepQsString, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="table-wrap">
                    <table id="receiptsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="rx-check-all" onclick="rxToggleAll(this)"></th>
                                <th>وضعیت</th>
                                <th>کاربر</th>
                                <th>کد پیگیری</th>
                                <th>مبلغ (تومان)</th>
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
                                    <span class="track-id invoice-reference" onclick="rxCopy('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>')" title="کپی کردن"><?php echo htmlspecialchars((string) $row['id_order'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td data-label="مبلغ (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo number_format((int) $row['price']); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                <td data-label="نوع سفارش"><span class="receipt-order-type"><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="۴ رقم آخر کارت" style="direction:ltr; text-align:right;"><?php echo $row['card_last4'] !== null && $row['card_last4'] !== '' ? htmlspecialchars((string) $row['card_last4'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                <td data-label="تاریخ" style="direction:ltr; text-align:right;"><span class="invoice-date"><?php echo htmlspecialchars(\Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::rxReceiptJdate($row['time']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <?php
                                    $rxViewRow = $row;
                                    unset($rxViewRow['card_photo_file_id']);
                                    $rxViewRow['time'] = \Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::rxReceiptJdate($row['time'] ?? '');
                                    $rxViewRow['at_updated'] = \Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::rxReceiptJdate($row['at_updated'] ?? '');
                                    ?>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="rxOpenView(<?php echo htmlspecialchars(json_encode($rxViewRow, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('eye', 'svg-icon svg-sm'); ?>
                                    </button>
                                    <?php if ($isWaiting): ?>
                                        <button type="button" class="btn btn-soft-success btn-sm" onclick="rxOpenConfirm('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo number_format((int) $row['price']); ?>', '<?php echo htmlspecialchars(addslashes((string) $row['id_user']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($rowKeepQs, ENT_QUOTES, 'UTF-8'); ?>')">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-check', 'svg-icon svg-sm'); ?>
                                        </button>
                                        <button type="button" class="btn btn-soft-danger btn-sm" onclick="rxOpenReject('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($rowKeepQs, ENT_QUOTES, 'UTF-8'); ?>')">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?>
                                        </button>
                                    <?php elseif ($isReject): ?>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="rxOpenReopen('<?php echo htmlspecialchars(addslashes((string) $row['id_order']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($rowKeepQs, ENT_QUOTES, 'UTF-8'); ?>')">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right-arrow-left', 'svg-icon svg-sm'); ?>
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
                    echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html($rxPg['page'], $rxPg['pages'], $rxPg['total'], count($rxRows), 'receipts.php', $rxPagerKeep);
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="submit" class="btn btn-soft-danger btn-sm js-rx-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف رسیدهای انتخاب‌شده مطمئن هستید؟')">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm'); ?> حذف انتخاب‌شده‌ها
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
            <span class="modal-head__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon svg-sm'); ?> جزئیات رسید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-view')">&times;</button>
        </div>
        <div class="modal-body receipt-detail-body" id="rx-view-body"></div>
    </div>
</div>

<div id="modal-rx-photo-preview" class="modal-overlay receipt-photo-preview-overlay">
    <div class="modal-box receipt-photo-preview-box">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon svg-sm'); ?> تصویر رسید</span>
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
            <span class="modal-head__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-check', 'svg-icon svg-sm'); ?> تایید رسید پرداخت</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-confirm')">&times;</button>
        </div>
        <form method="POST" action="receipts.php" id="rx-confirm-form">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <input type="hidden" name="_rx_action" value="confirm">
            <input type="hidden" name="id_order" id="rx-confirm-order-id" value="">
            <input type="hidden" name="_keep_qs" id="rx-confirm-keep-qs" value="">
            <div class="modal-body">
                <p>آیا از تایید این رسید پرداخت مطمئن هستید؟</p>
                <div class="detail-sheet__field"><span class="detail-sheet__field-label">مبلغ</span><span class="detail-sheet__field-value" id="rx-confirm-price"></span></div>
                <div class="detail-sheet__field"><span class="detail-sheet__field-label">آیدی کاربر</span><span class="detail-sheet__field-value" id="rx-confirm-user"></span></div>
                <div class="detail-sheet__field"><span class="detail-sheet__field-label">کد پیگیری</span><span class="detail-sheet__field-value" id="rx-confirm-order"></span></div>
                <label for="rx-confirm-reason">دلیل تأیید دستی (برای پرداخت‌های «تأیید رسید با بررسی» بدون واریز منطبق الزامی است)</label>
                <textarea name="reason" id="rx-confirm-reason" rows="2" class="form-control"></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-rx-confirm')">انصراف</button>
                <button type="submit" class="btn btn-primary" id="rx-confirm-submit">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-check', 'svg-icon svg-sm'); ?>
                    <span>تایید نهایی</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-rx-reject" class="modal-overlay">
    <div class="modal-box" style="max-width: 460px;">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?> رد رسید پرداخت</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-reject')">&times;</button>
        </div>
        <form method="POST" action="receipts.php" id="rx-reject-form">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
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
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?>
                    <span>رد رسید</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-rx-reopen" class="modal-overlay">
    <div class="modal-box" style="max-width: 420px;">
        <div class="modal-head">
            <span class="modal-head__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right-arrow-left', 'svg-icon svg-sm'); ?> بازگردانی رسید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-rx-reopen')">&times;</button>
        </div>
        <form method="POST" action="receipts.php" id="rx-reopen-form">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
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
    document.getElementById('rx-confirm-reason').value = '';
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
