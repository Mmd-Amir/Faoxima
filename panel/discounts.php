<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'discounts.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/csrf.php';

if (\Faoxima\Admin\Web\Pages\DiscountsRequest::handle(
        _csrf: $_csrf,
        action: $action,
        df1: $df1,
        df2: $df2,
        discDateKeep: $discDateKeep,
        discFilterActive: $discFilterActive,
        discFilterCriteria: $discFilterCriteria,
        discQ: $discQ,
        discountAgentOptions: $discountAgentOptions,
        discountSectionOptions: $discountSectionOptions,
        flash: $flash,
        giftDateKeep: $giftDateKeep,
        giftFilterActive: $giftFilterActive,
        giftFilterCriteria: $giftFilterCriteria,
        giftQ: $giftQ,
        list: $list,
        pdo: $pdo ?? null,
        pg1: $pg1,
        query: $query,
        section: $section,
        stmt: $stmt,
        value: $value
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\DiscountSearchResults::handle(
        adminWebPage: $adminWebPage ?? null,
        df1: $df1 ?? null,
        df2: $df2 ?? null,
        discDateKeep: $discDateKeep ?? null,
        discFilterActive: $discFilterActive ?? null,
        discQ: $discQ ?? null,
        flash: $flash,
        giftDateKeep: $giftDateKeep ?? null,
        giftFilterActive: $giftFilterActive ?? null,
        giftList: $giftList,
        giftQ: $giftQ ?? null,
        list: $list,
        listcategory: $listcategory,
        listpanel: $listpanel,
        p: $p,
        panelNameByCode: $panelNameByCode,
        pdo: $pdo ?? null,
        pg1: $pg1,
        pg2: $pg2,
        r: $r,
        v: $v
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
    <title>کدهای تخفیف | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=tickets105">
    <script src="js/money-input.js?v=fx1" defer></script>
    <script src="js/theme.js?v=dark62" defer>

</script>
</head>
<body class="fx-discounts">

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list fx-page-discounts">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <svg class="svg-icon svg-lg" viewBox="0 0 511.998 511.998" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M179.34,262.92c-9.217,0-16.716,7.499-16.716,16.716s7.499,16.716,16.716,16.716c9.217,0,16.716-7.499,16.716-16.716C196.056,270.419,188.559,262.92,179.34,262.92z"/><path d="M379.934,262.92c-9.217,0-16.716,7.499-16.716,16.716s7.499,16.716,16.716,16.716c9.217,0,16.716-7.499,16.716-16.716C396.65,270.419,389.152,262.92,379.934,262.92z"/><path d="M474.505,354.619l22.789-22.805c19.596-19.573,19.616-51.325,0-70.919L291.456,55.058l-47.275,47.28c-6.529,6.529-17.107,6.53-23.638,0c-6.529-6.524-6.529-17.108,0-23.638l47.275-47.28l-16.716-16.717c-19.548-19.558-51.268-19.638-70.924,0.007l-22.811,22.794l-11.514-8.2C113.358,6.17,67.665,8.998,38.341,38.342C9.382,67.306,5.584,112.524,29.309,145.864l8.184,11.514l-22.789,22.805c-19.596,19.573-19.616,51.325,0,70.919l16.716,16.716l47.275-47.275c6.529-6.529,17.108-6.529,23.638,0c6.529,6.524,6.529,17.113,0,23.638l-47.275,47.275l205.839,205.839c19.548,19.559,51.268,19.639,70.924-0.006l22.811-22.8l11.525,8.228c32.14,22.971,77.86,20.592,107.501-9.06c28.959-28.965,32.758-74.183,9.032-107.523L474.505,354.619z M125.981,173.262l47.275-47.281c6.529-6.529,17.108-6.529,23.638,0c6.529,6.524,6.529,17.108,0,23.638l-47.275,47.281c-6.529,6.529-17.107,6.53-23.638,0C119.452,190.376,119.452,179.791,125.981,173.262z M179.34,329.785c-27.653,0-50.148-22.495-50.148-50.148s22.495-50.148,50.148-50.148s50.148,22.495,50.148,50.148S206.994,329.785,179.34,329.785z M296.353,396.649c0,9.234-7.488,16.716-16.716,16.716c-9.228,0-16.716-7.482-16.716-16.716V162.624c0-9.234,7.488-16.716,16.716-16.716c9.228,0,16.716,7.482,16.716,16.716V396.649z M379.934,329.785c-27.653,0-50.148-22.495-50.148-50.148s22.495-50.148,50.148-50.148s50.148,22.495,50.148,50.148S407.588,329.785,379.934,329.785z"/></g></svg>
                        کدهای تخفیف
                    </div>
                    <div class="page-head__sub">مدیریت کدهای تخفیف و هدیه</div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-soft-success" onclick="openModal('modal-add-gift')">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن کد هدیه</span>
                    </button>
                    <button class="btn btn-primary" onclick="openModal('modal-add-discount')">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن کد تخفیف</span>
                    </button>
                </div>
            </div>

            <?php if ($flash['ok']): ?>
                <div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:var(--radius-md); margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($flash['ok'], ENT_QUOTES); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flash['err']): ?>
                <div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:var(--radius-md); margin-bottom:18px;">
                    <?php echo htmlspecialchars($flash['err'], ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_flash_html('1', 'کدهای تخفیف'); ?>
            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_flash_html('2', 'کدهای هدیه'); ?>
            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_flash_html('1', 'کدهای تخفیف'); ?>
            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_flash_html('2', 'کدهای هدیه'); ?>

            <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('discounts.php', $discQ, array_merge(['p2' => $pg2['page'] > 1 ? $pg2['page'] : null, 'q2' => $giftQ !== '' ? $giftQ : null], $discDateKeep, $giftDateKeep), 'جستجو در کد تخفیف یا کاربر هدف…', 'q1'); ?>

            <?php $fxFd = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_parts('discounts.php', array_merge(['q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null, 'p2' => $pg2['page'] > 1 ? $pg2['page'] : null], $discDateKeep, $giftDateKeep), $discFilterActive ? (int)$pg1['total'] : 0, $discFilterCriteria, '1'); ?>
            <?php echo \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_ui('discounts.php', '1', array_merge(['p2' => $pg2['page'] > 1 ? $pg2['page'] : null, 'q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null], $giftDateKeep), 'انقضا', $fxFd['button'], $fxFd['form'], true); ?>

            <div class="card invoice-results">
                <div class="invoice-results__head"><div><h2>کدهای تخفیف</h2><p>مدیریت تخفیف خرید، تمدید و سایر خدمات</p></div><span class="invoice-page-count"><?php echo strtr(number_format((int)$pg1['total']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> کد<?php echo $discQ !== '' ? ' مطابق جستجو' : ''; ?></span></div>
                <div id="bulk-scope-1">
                <div class="table-wrap">
                    <table id="discountsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all-1" onclick="faoximaToggleAll(this, 'bulk-scope-1')"></th>
                                <th>کد</th>
                                <th>نوع</th>
                                <th>مقدار</th>
                                <th>سقف کل</th>
                                <th>تعداد استفاده</th>
                                <th>گروه هدف</th>
                                <th>محدودیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px 0; color:var(--text-muted);">
                                هنوز کدی ثبت نشده است.
                            </td></tr>
                        <?php else: foreach ($list as $d):
                            $kind = $d['value_type'] ?? '';
                            if (!in_array($kind, ['percent','amount','free'], true)) {
                                $kind = in_array(($d['type'] ?? ''), ['percent','amount','free'], true) ? $d['type'] : 'percent';
                            }
                            [$typeLabel, $typeBadge] = \Faoxima\Admin\Web\Pages\DiscountsRequestHelpers::faoxima_d_label_type($kind);
                            $sectionVal = $d['section'] ?? '';
                            if ($sectionVal === '') $sectionVal = in_array(($d['type'] ?? ''), ['buy','extend'], true) ? $d['type'] : 'all';
                            [$secLabel, $secBadge] = \Faoxima\Admin\Web\Pages\DiscountsRequestHelpers::faoxima_d_label_section($sectionVal);
                            [$agentLabel, $agentBadge] = \Faoxima\Admin\Web\Pages\DiscountsRequestHelpers::faoxima_d_label_agent($d['agent'] ?? '');
                            $valDisplay = htmlspecialchars((string)$d['price'], ENT_QUOTES);
                            if ($kind === 'percent') $valDisplay .= '%';
                            elseif ($kind === 'amount') $valDisplay = number_format((int)$d['price']) . ' <small>تومان</small>';
                            elseif ($kind === 'free') $valDisplay = 'رایگان';
                            $limit = (int)$d['limitDiscount'];
                            $used  = (int)$d['usedDiscount'];
                            $remaining = $limit > 0 ? max(0, $limit - $used) : -1;
                            $targetUser = trim((string)($d['target_user'] ?? ''));
                            $editAgent = in_array(($d['agent'] ?? ''), ['f','n','n2'], true) ? $d['agent'] : 'allusers';
                            $editExpTs = (int)($d['time'] ?? 0);
                            $editExpDays = ($editExpTs > time()) ? (int)ceil(($editExpTs - time()) / 86400) : 0;
                            $targetingMode = in_array(($d['targeting_mode'] ?? ''), ['category','panel'], true) ? $d['targeting_mode'] : 'none';
                            $codeCategoryVal = (string)($d['code_category'] ?? 'all');
                            [$targetingLabel, $targetingBadge] = \Faoxima\Admin\Web\Pages\DiscountsRequestHelpers::faoxima_d_label_targeting($targetingMode, $codeCategoryVal, $d['code_panel'] ?? '', $panelNameByCode);
                        ?>
                            <tr data-detail-row data-detail-title="کد <?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo (int)$d['id']; ?>"></label></td>
                                <td data-label="کد" data-summary="1"><code style="direction:ltr; background:var(--accent-soft); color:var(--accent); padding:4px 8px; border-radius:var(--radius-xs); font-weight:700;"><?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?></code></td>
                                <td data-label="نوع" data-summary="1">
                                    <?php
                                        $discountTypeBadgesHtml = [
                                            '<span class="badge ' . $typeBadge . '">' . $typeLabel . '</span>',
                                            '<span class="badge ' . $secBadge . '">' . $secLabel . '</span>',
                                        ];
                                        if ($targetUser !== '') {
                                            $discountTypeBadgesHtml[] = '<span class="badge badge-warning">کاربر ' . htmlspecialchars($targetUser, ENT_QUOTES) . '</span>';
                                        }
                                        if ($targetingMode !== 'none') {
                                            $discountTypeBadgesHtml[] = '<span class="badge ' . $targetingBadge . '">' . $targetingLabel . '</span>';
                                        }
                                        echo \Faoxima\Admin\Web\Support\CompactBadges::faoxima_render_compact_badges($discountTypeBadgesHtml);
                                    ?>
                                </td>
                                <td data-label="مقدار" data-summary="1"><?php echo $valDisplay; ?></td>
                                <td data-label="سقف کل"><?php echo $limit > 0 ? $limit : '<span class="text-muted">نامحدود</span>'; ?></td>
                                <td data-label="تعداد استفاده">
                                    <b style="color:<?php echo ($remaining === 0) ? 'var(--color-danger)' : 'var(--color-success)'; ?>;"><?php echo $used; ?></b>
                                    <?php if ($limit > 0): ?>/ <?php echo $limit; ?><?php endif; ?>
                                </td>
                                <td data-label="گروه هدف"><span class="badge <?php echo $agentBadge; ?>"><?php echo $agentLabel; ?></span></td>
                                <td data-label="محدودیت">
                                    <?php if ($d['usefirst'] == '1'): ?>
                                        <span class="badge badge-warning">فقط خرید اول</span>
                                    <?php endif; ?>
                                    <?php if ($d['useuser'] == '1'): ?>
                                        <span class="badge badge-info">یک بار/کاربر</span>
                                    <?php endif; ?>
                                    <?php if ($d['usefirst'] != '1' && $d['useuser'] != '1'): ?>
                                        <span class="text-muted">نامحدود</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="عملیات" class="cell-actions">
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$d['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?>"
                                            data-vt="<?php echo htmlspecialchars($kind, ENT_QUOTES); ?>"
                                            data-section="<?php echo htmlspecialchars($sectionVal, ENT_QUOTES); ?>"
                                            data-price="<?php echo htmlspecialchars((string)$d['price'], ENT_QUOTES); ?>"
                                            data-limit="<?php echo (int)$d['limitDiscount']; ?>"
                                            data-agent="<?php echo htmlspecialchars($editAgent, ENT_QUOTES); ?>"
                                            data-first="<?php echo $d['usefirst'] == '1' ? '1' : '0'; ?>"
                                            data-useuser="<?php echo $d['useuser'] == '1' ? '1' : '0'; ?>"
                                            data-target="<?php echo htmlspecialchars($targetUser, ENT_QUOTES); ?>"
                                            data-exp="<?php echo (int)$editExpDays; ?>"
                                            data-targeting-mode="<?php echo htmlspecialchars($targetingMode, ENT_QUOTES); ?>"
                                            data-category="<?php echo htmlspecialchars($codeCategoryVal === 'all' ? '' : $codeCategoryVal, ENT_QUOTES); ?>"
                                            data-panel="<?php echo htmlspecialchars((string)($d['code_panel'] ?? ''), ENT_QUOTES); ?>"
                                            onclick="editDiscount(this)">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('شمارنده استفاده این کد صفر شود؟');">
                                            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                            <input type="hidden" name="_action" value="reset_usage">
                                            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-warning" title="بازنشانی شمارنده استفاده">
                                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('rotate-left', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('کد <?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?> حذف شود؟');">
                                            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                            <input type="hidden" name="_action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <?php
                    $d1Keep = ['p2' => $pg2['page'] > 1 ? $pg2['page'] : null, 'q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null];
                    foreach (['dpreset1', 'ddays1', 'dfrom1', 'dto1', 'dpreset2', 'ddays2', 'dfrom2', 'dto2'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $d1Keep[$dk] = $_GET[$dk];
                    }
                    echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html($pg1['page'], $pg1['pages'], $pg1['total'], count($list), 'discounts.php', $d1Keep, 'p1');
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="button" class="btn btn-soft-danger btn-sm js-bulk-delete-btn js-bulk-delete-btn-1" style="display:none;" onclick="faoximaBulkDelete('bulk-scope-1', 'bulk_delete', '_action')">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                </div>
            </div>

            <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('discounts.php', $giftQ, array_merge(['p1' => $pg1['page'] > 1 ? $pg1['page'] : null, 'q1' => $discQ !== '' ? $discQ : null], $discDateKeep, $giftDateKeep), 'جستجو در کد هدیه یا کاربر هدف…', 'q2'); ?>

            <?php $fxFd2 = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_parts('discounts.php', array_merge(['q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null, 'p1' => $pg1['page'] > 1 ? $pg1['page'] : null], $discDateKeep, $giftDateKeep), $giftFilterActive ? (int)$pg2['total'] : 0, $giftFilterCriteria, '2'); ?>
            <?php echo \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_ui('discounts.php', '2', array_merge(['p1' => $pg1['page'] > 1 ? $pg1['page'] : null, 'q2' => $giftQ !== '' ? $giftQ : null, 'q1' => $discQ !== '' ? $discQ : null], $discDateKeep), 'انقضا', $fxFd2['button'], $fxFd2['form'], true); ?>

            <div class="card invoice-results" style="margin-top:18px">
                <div class="invoice-results__head"><div><h2>کدهای هدیه</h2><p>مدیریت اعتبار هدیه و شارژ کیف پول کاربران</p></div><span class="invoice-page-count"><?php echo strtr(number_format((int)$pg2['total']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> کد<?php echo $giftQ !== '' ? ' مطابق جستجو' : ''; ?></span></div>
                <div id="bulk-scope-2">
                <div class="table-wrap">
                    <table id="giftsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all-2" onclick="faoximaToggleAll(this, 'bulk-scope-2')"></th>
                                <th>کد</th>
                                <th>مبلغ</th>
                                <th>سقف کل</th>
                                <th>تعداد استفاده</th>
                                <th>اختصاصی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($giftList)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px 0; color:var(--text-muted);">هنوز کد هدیه‌ای ثبت نشده است.</td></tr>
                        <?php else: foreach ($giftList as $g):
                            $glimit = (int)($g['limituse'] ?? 0);
                            $gused  = (int)($g['limitused'] ?? 0);
                            $gtarget = trim((string)($g['target_user'] ?? ''));
                            $gEditExpTs = (int)($g['expire_at'] ?? 0);
                            $gEditExpDays = ($gEditExpTs > time()) ? (int)ceil(($gEditExpTs - time()) / 86400) : 0;
                        ?>
                            <tr data-detail-row data-detail-title="کد هدیه <?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo (int)$g['id']; ?>"></label></td>
                                <td data-label="کد" data-summary="1"><code style="direction:ltr; background:var(--accent-soft); color:var(--accent); padding:4px 8px; border-radius:var(--radius-xs); font-weight:700;"><?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?></code></td>
                                <td data-label="مبلغ" data-summary="1"><?php echo number_format((int)($g['price'] ?? 0)); ?> <small>تومان</small></td>
                                <td data-label="سقف کل"><?php echo $glimit > 0 ? $glimit : '<span class="text-muted">نامحدود</span>'; ?></td>
                                <td data-label="تعداد استفاده"><?php echo $gused; ?><?php if ($glimit > 0): ?> / <?php echo $glimit; ?><?php endif; ?></td>
                                <td data-label="اختصاصی" data-summary="1"><?php echo $gtarget !== '' ? '<span class="badge badge-warning">کاربر ' . htmlspecialchars($gtarget, ENT_QUOTES) . '</span>' : '<span class="text-muted">عمومی</span>'; ?></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$g['id']; ?>"
                                            data-code="<?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?>"
                                            data-price="<?php echo (int)($g['price'] ?? 0); ?>"
                                            data-limit="<?php echo (int)($g['limituse'] ?? 0); ?>"
                                            data-target="<?php echo htmlspecialchars($gtarget, ENT_QUOTES); ?>"
                                            data-exp="<?php echo (int)$gEditExpDays; ?>"
                                            onclick="editGift(this)">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('کد هدیه حذف شود؟');">
                                            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                            <input type="hidden" name="_action" value="gift_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <?php
                    $d2Keep = ['p1' => $pg1['page'] > 1 ? $pg1['page'] : null, 'q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null];
                    foreach (['dpreset1', 'ddays1', 'dfrom1', 'dto1', 'dpreset2', 'ddays2', 'dfrom2', 'dto2'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $d2Keep[$dk] = $_GET[$dk];
                    }
                    echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html($pg2['page'], $pg2['pages'], $pg2['total'], count($giftList), 'discounts.php', $d2Keep, 'p2');
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="button" class="btn btn-soft-danger btn-sm js-bulk-delete-btn js-bulk-delete-btn-2" style="display:none;" onclick="faoximaBulkDelete('bulk-scope-2', 'gift_bulk_delete', '_action')">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                </div>
            </div>


<div id="modal-add-discount" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن کد تخفیف جدید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-discount')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="add">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد تخفیف</label>
                    <input type="text" name="codeDiscount" class="form-control" style="direction:ltr; font-family:'JetBrains Mono', monospace;" placeholder="FAOXIMA20" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع تخفیف</label>
                    <select name="type" class="form-control" required onchange="updateValueHint(this.value)">
                        <option value="percent">درصدی (٪)</option>
                        <option value="amount">مبلغی (تومان)</option>
                        <option value="free">رایگان (۱۰۰٪)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" id="valueLabel">مقدار تخفیف (٪)</label>
                    <input type="text" name="price" id="add_price" class="form-control" placeholder="20" required min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="limitDiscount" class="form-control" placeholder="100" value="0" min="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                <input type="number" name="expire_days" class="form-control" placeholder="0" value="0" min="0">
            </div>

            <div class="form-group">
                <label class="form-label">بخش کد <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-section-picker>
                    <?php foreach ($discountSectionOptions as $secVal => $secLabel): ?>
                    <button type="button" class="chip" data-section-chip data-value="<?php echo htmlspecialchars($secVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($secLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="section_csv" data-section-hidden value="all">
            </div>

            <div class="form-group">
                <label class="form-label">نحوه هدف‌گیری</label>
                <select name="targeting_mode" id="targeting_mode" class="form-control" onchange="faoximaToggleTargeting(this.value)">
                    <option value="none">بدون محدودیت (همه محصولات/پنل‌ها)</option>
                    <option value="category">بر اساس دسته‌بندی</option>
                    <option value="panel">بر اساس پنل مشخص</option>
                </select>
            </div>
            <div class="form-group" id="targeting_category_wrap" style="display:none;">
                <label class="form-label">دسته‌بندی‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-category-picker>
                    <?php foreach ($listcategory as $catName): ?>
                        <button type="button" class="chip" data-category-chip data-value="<?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_category_csv" data-category-hidden value="">
            </div>
            <div class="form-group" id="targeting_panel_wrap" style="display:none;">
                <label class="form-label">پنل‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-panel-picker>
                    <?php foreach ($listpanel as $p): ?>
                        <button type="button" class="chip" data-panel-chip data-value="<?php echo htmlspecialchars((string)($p['code_panel'] ?? ''), ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)($p['name_panel'] ?? ''), ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_panel_csv" data-panel-hidden value="">
            </div>

            <div class="form-group">
                <label class="form-label">گروه هدف <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-agent-picker>
                    <?php foreach ($discountAgentOptions as $agVal => $agLabel): ?>
                    <button type="button" class="chip" data-agent-chip data-value="<?php echo htmlspecialchars($agVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($agLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="agent_csv" data-agent-hidden value="allusers">
            </div>

            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="target_user" class="form-control" style="direction:ltr" placeholder="123456789">
            </div>

            <div class="form-group" style="display:flex; flex-direction:column; gap:8px;">
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="usefirst" value="1">
                    فقط برای خرید اول کاربر قابل استفاده باشد
                </label>
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="useuser" value="1">
                    هر کاربر فقط یک بار بتواند استفاده کند
                </label>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-discount')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon svg-sm'); ?>
                    افزودن کد
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-add-gift" class="modal-overlay">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن کد هدیه</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-gift')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="gift_add">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد هدیه</label>
                    <input type="text" name="code" class="form-control" style="direction:ltr;" placeholder="GIFT50" required>
                </div>
                <div class="form-group">
                    <label class="form-label">مبلغ (تومان)</label>
                    <input type="text" name="gift_price" class="form-control" data-money placeholder="50000" required min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="gift_limit" class="form-control" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                    <input type="number" name="gift_expire_days" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="gift_target" class="form-control" style="direction:ltr" placeholder="123456789">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-gift')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon svg-sm'); ?> افزودن</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-discount" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد تخفیف</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-discount')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="ed_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد تخفیف</label>
                    <input type="text" name="codeDiscount" id="ed_code" class="form-control" style="direction:ltr; font-family:'JetBrains Mono', monospace;" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع تخفیف</label>
                    <select name="type" id="ed_type" class="form-control" required onchange="updateEditValueHint(this.value)">
                        <option value="percent">درصدی (٪)</option>
                        <option value="amount">مبلغی (تومان)</option>
                        <option value="free">رایگان (۱۰۰٪)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" id="ed_valueLabel">مقدار تخفیف (٪)</label>
                    <input type="text" name="price" id="ed_price" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="limitDiscount" id="ed_limit" class="form-control" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                <input type="number" name="expire_days" id="ed_exp" class="form-control" min="0">
            </div>

            <div class="form-group">
                <label class="form-label">بخش کد <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_section_picker" data-section-picker>
                    <?php foreach ($discountSectionOptions as $secVal => $secLabel): ?>
                    <button type="button" class="chip" data-section-chip data-value="<?php echo htmlspecialchars($secVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($secLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="section_csv" id="ed_section_csv" data-section-hidden value="all">
            </div>

            <div class="form-group">
                <label class="form-label">نحوه هدف‌گیری</label>
                <select name="targeting_mode" id="ed_targeting_mode" class="form-control" onchange="faoximaToggleEditTargeting(this.value)">
                    <option value="none">بدون محدودیت (همه محصولات/پنل‌ها)</option>
                    <option value="category">بر اساس دسته‌بندی</option>
                    <option value="panel">بر اساس پنل مشخص</option>
                </select>
            </div>
            <div class="form-group" id="ed_targeting_category_wrap" style="display:none;">
                <label class="form-label">دسته‌بندی‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_category_picker" data-category-picker>
                    <?php foreach ($listcategory as $catName): ?>
                        <button type="button" class="chip" data-category-chip data-value="<?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_category_csv" id="ed_code_category" data-category-hidden value="">
            </div>
            <div class="form-group" id="ed_targeting_panel_wrap" style="display:none;">
                <label class="form-label">پنل‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_panel_picker" data-panel-picker>
                    <?php foreach ($listpanel as $p): ?>
                        <button type="button" class="chip" data-panel-chip data-value="<?php echo htmlspecialchars((string)($p['code_panel'] ?? ''), ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)($p['name_panel'] ?? ''), ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_panel_csv" id="ed_code_panel_csv" data-panel-hidden value="">
            </div>
            <div class="form-group">
                <label class="form-label">گروه هدف <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_agent_picker" data-agent-picker>
                    <?php foreach ($discountAgentOptions as $agVal => $agLabel): ?>
                    <button type="button" class="chip" data-agent-chip data-value="<?php echo htmlspecialchars($agVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($agLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="agent_csv" id="ed_agent_csv" data-agent-hidden value="allusers">
            </div>

            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="target_user" id="ed_target" class="form-control" style="direction:ltr">
            </div>
            <div class="form-group" style="display:flex; flex-direction:column; gap:8px;">
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="usefirst" id="ed_first" value="1">
                    فقط برای خرید اول کاربر قابل استفاده باشد
                </label>
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="useuser" id="ed_useuser" value="1">
                    هر کاربر فقط یک بار بتواند استفاده کند
                </label>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-edit-discount')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon svg-sm'); ?> ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-gift" class="modal-overlay">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد هدیه</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-gift')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="gift_edit">
            <input type="hidden" name="id" id="ed_g_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد هدیه</label>
                    <input type="text" name="code" id="ed_g_code" class="form-control" style="direction:ltr;" required>
                </div>
                <div class="form-group">
                    <label class="form-label">مبلغ (تومان)</label>
                    <input type="text" name="gift_price" id="ed_g_price" class="form-control" data-money required min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="gift_limit" id="ed_g_limit" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                    <input type="number" name="gift_expire_days" id="ed_g_exp" class="form-control" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="gift_target" id="ed_g_target" class="form-control" style="direction:ltr">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-edit-gift')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon svg-sm'); ?> ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<script src="js/bulk-select.js?v=fx2"></script>
<script src="js/compact-badges.js?v=fx1"></script>
<script>
function faoximaToggleAll(master, scopeId) {
    var scope = scopeId ? document.getElementById(scopeId) : document;
    scope.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(function(cb) {
        if (cb.checked === master.checked) return;
        cb.checked = master.checked;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
var fxBulkHandle1 = FxBulkSelect.init({
    scope: '1',
    checkboxSelector: 'input[name="ids[]"]',
    scopeRoot: document.getElementById('bulk-scope-1'),
    formEl: null,
    deleteButtonSelector: '.js-bulk-delete-btn-1',
    clearOnQueryFlags: ['bulk1']
});
var fxBulkHandle2 = FxBulkSelect.init({
    scope: '2',
    checkboxSelector: 'input[name="ids[]"]',
    scopeRoot: document.getElementById('bulk-scope-2'),
    formEl: null,
    deleteButtonSelector: '.js-bulk-delete-btn-2',
    clearOnQueryFlags: ['bulk2']
});
function faoximaBulkDelete(scopeId, actionValue, actionField) {
    var scope = document.getElementById(scopeId);
    var handle = scopeId === 'bulk-scope-1' ? fxBulkHandle1 : fxBulkHandle2;
    var idSet = {};
    Array.prototype.forEach.call(scope.querySelectorAll('input[name="ids[]"]:checked'), function (cb) {
        idSet[cb.value] = true;
    });
    FxBulkSelect.getPersistedIds(handle.key).forEach(function (v) {
        idSet[v] = true;
    });
    var ids = Object.keys(idSet);
    if (ids.length === 0) return;
    if (!confirm('آیا از حذف موارد انتخاب‌شده مطمئن هستید؟')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'discounts.php';
    var actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = actionField;
    actionInput.value = actionValue;
    form.appendChild(actionInput);
    ids.forEach(function(val) {
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'ids[]';
        idInput.value = val;
        form.appendChild(idInput);
    });
    document.body.appendChild(form);
    form.submit();
}
function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    if (typeof window.closeDetailSheet === 'function') window.closeDetailSheet();
    m.classList.add('active');
    if (id === 'modal-add-discount') {
        m.querySelector('form')?.reset();
        var picker = document.querySelector('#targeting_category_wrap [data-category-picker]');
        if (picker) faoximaCategoryPickerSetSelected(picker, []);
        faoximaToggleTargeting('none');
        updateValueHint('percent');
        faoximaSectionPickerSetSelected(m.querySelector('[data-section-picker]'), ['all']);
        faoximaAgentPickerSetSelected(m.querySelector('[data-agent-picker]'), ['allusers']);
    }
}
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

function updateValueHint(type) {
    var lbl = document.getElementById('valueLabel');
    if (type === 'percent') lbl.textContent = 'مقدار تخفیف (٪)';
    else if (type === 'amount') lbl.textContent = 'مقدار تخفیف (تومان)';
    else lbl.textContent = 'مقدار (استفاده‌نمی‌شود)';
    if (window.FaoximaMoneyInput) {
        window.FaoximaMoneyInput.setMode(document.getElementById('add_price'), type === 'amount');
    }
}

function updateEditValueHint(type) {
    var lbl = document.getElementById('ed_valueLabel');
    if (!lbl) return;
    if (type === 'percent') lbl.textContent = 'مقدار تخفیف (٪)';
    else if (type === 'amount') lbl.textContent = 'مقدار تخفیف (تومان)';
    else lbl.textContent = 'مقدار (استفاده‌نمی‌شود)';
    if (window.FaoximaMoneyInput) {
        window.FaoximaMoneyInput.setMode(document.getElementById('ed_price'), type === 'amount');
    }
}

function faoximaToggleTargeting(mode) {
    document.getElementById('targeting_category_wrap').style.display = (mode === 'category') ? '' : 'none';
    document.getElementById('targeting_panel_wrap').style.display = (mode === 'panel') ? '' : 'none';
}
function faoximaToggleEditTargeting(mode) {
    document.getElementById('ed_targeting_category_wrap').style.display = (mode === 'category') ? '' : 'none';
    document.getElementById('ed_targeting_panel_wrap').style.display = (mode === 'panel') ? '' : 'none';
}

function faoximaChipPickerSync(picker, hiddenAttr, chipAttr) {
    var hidden = picker.parentElement.querySelector('[' + hiddenAttr + ']');
    if (!hidden) return;
    var values = Array.from(picker.querySelectorAll('.chip.is-active')).map(function (c) { return c.getAttribute('data-value'); });
    hidden.value = values.join(',');
}
function faoximaChipPickerSetSelected(picker, values, chipAttr, hiddenAttr) {
    var set = values.filter(function (v) { return v !== ''; });
    picker.querySelectorAll('.chip[' + chipAttr + ']').forEach(function (chip) {
        chip.classList.toggle('is-active', set.indexOf(chip.getAttribute('data-value')) !== -1);
    });
    faoximaChipPickerSync(picker, hiddenAttr, chipAttr);
}
function faoximaCategoryPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-category-hidden', 'data-category-chip');
}
function faoximaCategoryPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-category-chip', 'data-category-hidden');
}
function faoximaPanelPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-panel-hidden', 'data-panel-chip');
}
function faoximaPanelPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-panel-chip', 'data-panel-hidden');
}
function faoximaSectionPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-section-hidden', 'data-section-chip');
}
function faoximaSectionPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-section-chip', 'data-section-hidden');
}
function faoximaAgentPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-agent-hidden', 'data-agent-chip');
}
function faoximaAgentPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-agent-chip', 'data-agent-hidden');
}
function faoximaBindChipPicker(pickerRootSelector, chipSelector, syncFn) {
    document.querySelectorAll(pickerRootSelector).forEach(function (picker) {
        picker.addEventListener('click', function (ev) {
            var chip = ev.target.closest(chipSelector);
            if (!chip || !picker.contains(chip)) return;
            chip.classList.toggle('is-active');
            syncFn(picker);
        });
    });
}
faoximaBindChipPicker('[data-category-picker]', '.chip[data-category-chip]', faoximaCategoryPickerSync);
faoximaBindChipPicker('[data-panel-picker]', '.chip[data-panel-chip]', faoximaPanelPickerSync);
faoximaBindChipPicker('[data-section-picker]', '.chip[data-section-chip]', faoximaSectionPickerSync);
faoximaBindChipPicker('[data-agent-picker]', '.chip[data-agent-chip]', faoximaAgentPickerSync);

function editDiscount(btn) {
    document.getElementById('ed_id').value      = btn.getAttribute('data-id');
    document.getElementById('ed_code').value    = btn.getAttribute('data-code');
    document.getElementById('ed_type').value    = btn.getAttribute('data-vt');
    document.getElementById('ed_price').value   = btn.getAttribute('data-price');
    document.getElementById('ed_limit').value   = btn.getAttribute('data-limit');
    document.getElementById('ed_exp').value      = btn.getAttribute('data-exp');
    document.getElementById('ed_target').value  = btn.getAttribute('data-target');
    document.getElementById('ed_first').checked   = btn.getAttribute('data-first') === '1';
    document.getElementById('ed_useuser').checked = btn.getAttribute('data-useuser') === '1';
    updateEditValueHint(btn.getAttribute('data-vt'));

    var targetingMode = btn.getAttribute('data-targeting-mode') || 'none';
    document.getElementById('ed_targeting_mode').value = targetingMode;
    var categoryStr = btn.getAttribute('data-category') || '';
    var categoryArr = categoryStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaCategoryPickerSetSelected(document.getElementById('ed_category_picker'), categoryArr);
    var panelStr = btn.getAttribute('data-panel') || '';
    var panelArr = panelStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaPanelPickerSetSelected(document.getElementById('ed_panel_picker'), panelArr);
    faoximaToggleEditTargeting(targetingMode);

    var sectionStr = btn.getAttribute('data-section') || 'all';
    var sectionArr = sectionStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaSectionPickerSetSelected(document.getElementById('ed_section_picker'), sectionArr);

    var agentStr = btn.getAttribute('data-agent') || 'allusers';
    var agentArr = agentStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaAgentPickerSetSelected(document.getElementById('ed_agent_picker'), agentArr);

    openModal('modal-edit-discount');
}
function editGift(btn) {
    document.getElementById('ed_g_id').value     = btn.getAttribute('data-id');
    document.getElementById('ed_g_code').value   = btn.getAttribute('data-code');
    document.getElementById('ed_g_price').value  = window.FaoximaMoneyInput ? window.FaoximaMoneyInput.format(btn.getAttribute('data-price')) : btn.getAttribute('data-price');
    document.getElementById('ed_g_limit').value  = btn.getAttribute('data-limit');
    document.getElementById('ed_g_target').value = btn.getAttribute('data-target');
    document.getElementById('ed_g_exp').value    = btn.getAttribute('data-exp');
    openModal('modal-edit-gift');
}
</script>
</body>
</html>


