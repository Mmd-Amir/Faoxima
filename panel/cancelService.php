<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'cancelService.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/../function.php';

if (\Faoxima\Admin\Web\Pages\CancelserviceRequest::handle(
        cur: $cur,
        df: $df,
        flash: $flash,
        method: $method,
        pdo: $pdo ?? null,
        query: $query,
        r: $r,
        reqDateKeep: $reqDateKeep,
        reqFilterActive: $reqFilterActive,
        reqFilterCriteria: $reqFilterCriteria,
        reqFilterDeletable: $reqFilterDeletable,
        reqFilterNote: $reqFilterNote,
        reqFilterParams: $reqFilterParams,
        reqPage: $reqPage,
        reqPages: $reqPages,
        reqQ: $reqQ,
        reqStatus: $reqStatus,
        reqStatusActive: $reqStatusActive,
        reqStatusOptions: $reqStatusOptions,
        reqTotal: $reqTotal,
        result: $result,
        rows: $rows,
        st: $st ?? null,
        stmt: $stmt
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
    <title>لیست درخواست‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=filterdrawer111">
<script src="js/theme.js?v=dark62" defer></script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list fx-page-requests">

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

            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_flash_html(); ?>

            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_flash_html(); ?>

            <div class="invoice-overview">
                <div class="invoice-overview__main"><span class="invoice-overview__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('list', 'svg-icon'); ?></span><div><span><?php echo $reqFilterActive ? 'درخواست‌های مطابق فیلتر' : 'کل درخواست‌های فهرست'; ?></span><strong><?php echo strtr(number_format($reqTotal), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?><small>درخواست</small></strong></div></div>
                <div class="invoice-overview__detail"><span>وضعیت انتخاب‌شده</span><b><?php echo htmlspecialchars($reqStatusActive ? $reqStatusOptions[$reqStatus] : 'همهٔ وضعیت‌ها', ENT_QUOTES, 'UTF-8'); ?></b></div>
                <div class="invoice-overview__detail"><span>بازهٔ نمایش</span><b><?php echo htmlspecialchars($df['active'] ? $df['label'] : 'تمام تاریخ‌ها', ENT_QUOTES, 'UTF-8'); ?></b></div>
                <?php if ($reqFilterActive): ?><a href="cancelService.php" class="btn btn-outline btn-sm invoice-reset">پاک‌کردن فیلترها</a><?php endif; ?>
            </div>

            <details class="wrapper fx-page-list fx-filter-drawer fx-page-requests">
                <summary class="fx-filter-drawer__summary">
                    <span class="fx-filter-drawer__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('filter', 'svg-icon'); ?></span>
                    <span class="fx-filter-drawer__text"><strong>جستجو و فیلترها</strong><small>جستجو، وضعیت و بازهٔ زمانی</small></span>
                    <span class="fx-filter-drawer__action"><span class="fx-filter-drawer__show">نمایش فیلترها</span><span class="fx-filter-drawer__hide">بستن فیلترها</span></span>
                    <span class="fx-filter-drawer__chevron" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-down', 'svg-icon'); ?></span>
                </summary>
            <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('cancelService.php', $reqQ, array_merge(['status' => $reqStatus !== '' ? $reqStatus : null], $reqDateKeep), 'جستجو در آیدی کاربر، شناسه سفارش یا کد پیگیری…'); ?>

            <?php echo \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_ui('cancelService.php', $reqStatusOptions, $reqStatus, array_merge(['q' => $reqQ !== '' ? $reqQ : null], $reqDateKeep)); ?>

            <?php $fxFd = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_parts('cancelService.php', $reqFilterParams, $reqFilterActive ? $reqFilterDeletable : 0, $reqFilterCriteria, '', $reqFilterNote); ?>
            <?php echo \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_ui('cancelService.php', '', ['q' => $reqQ !== '' ? $reqQ : null, 'status' => $reqStatus !== '' ? $reqStatus : null], '', $fxFd['button'], $fxFd['form'], true); ?>
            </details>

            <div class="card invoice-results">
                <div class="invoice-results__head"><div><h2>آرشیو درخواست‌ها</h2><p>جزئیات درخواست‌های کاربران از ربات و مینی‌اپ</p></div><span class="invoice-page-count"><?php echo strtr(number_format(count($rows)), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> درخواست در این صفحه</span></div>
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
                                <th>مبلغ (تومان)</th>
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
                                <td data-label="شناسه" style="direction:ltr; text-align:right;" data-summary="1"><bdi class="invoice-reference">#<?php echo htmlspecialchars($row['idlabel'], ENT_QUOTES, 'UTF-8'); ?></bdi></td>
                                <td data-label="نوع درخواست" data-filter-value="<?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="منبع" data-filter-value="<?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?>"><span class="badge badge-info"><?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="آیدی کاربر">
                                    <?php if ($row['user'] !== ''): ?>
                                        <a href="user.php?id=<?php echo htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8'); ?>" class="text-link"><?php echo htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td data-label="جزئیات" style="max-width:340px;"><span class="request-details"><?php echo htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="مبلغ (تومان)"><span class="invoice-amount"><bdi><?php echo $row['amount'] > 0 ? number_format($row['amount']) : '—'; ?></bdi><?php if ($row['amount'] > 0): ?><small class="currency-unit">تومان</small><?php endif; ?></span></td>
                                <td data-label="تاریخ" style="direction:ltr; text-align:right; font-size:11.5px;"><span class="invoice-date"><?php echo $row['date'] !== '' ? htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') : '—'; ?></span></td>
                                <td data-label="وضعیت" data-filter-value="<?php echo htmlspecialchars($row['stLabel'], ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><span class="badge <?php echo $row['stClass']; ?>"><?php echo htmlspecialchars($row['stLabel'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <?php if ($row['del'] !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($row['del'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-soft-danger" onclick="return confirm('این درخواست حذف شود؟')">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف
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
                    echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html($reqPage, $reqPages, $reqTotal, count($rows), 'cancelService.php', $reqKeep);
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف درخواست‌های انتخاب‌شده مطمئن هستید؟')">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                <div class="page-head__sub" style="margin-top:12px;">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('lightbulb','svg-icon svg-sm'); ?> تأیید/رد نهاییِ پرداخت‌ها و افزایش/کاهش موجودی همچنان از داخل ربات انجام می‌شود (تا حساب‌داری دوبار اعمال نشود)؛ این صفحه همه‌ی درخواست‌ها را برای مشاهده، جست‌وجو و فیلتر یک‌جا نمایش می‌دهد.
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
