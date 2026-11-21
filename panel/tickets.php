<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'tickets.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
date_default_timezone_set('Asia/Tehran');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../jdf.php';

if (\Faoxima\Admin\Web\Pages\TicketsRequest::handle(
        _csrf: $_csrf,
        pdo: $pdo ?? null,
        setting: $setting
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\TicketActions::handle(
        TK_REACTION_EMOJI: $TK_REACTION_EMOJI,
        adminWebPage: $adminWebPage ?? null,
        emoji: $emoji,
        out: $out,
        pdo: $pdo ?? null,
        r: $r,
        rows: $rows,
        st: $st,
        thread: $thread
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\TicketMediaAndReplies::handle(
        APIKEY: $APIKEY ?? null,
        act: $act,
        adminWebPage: $adminWebPage ?? null,
        domainhosts: $domainhosts ?? null,
        f: $f,
        host: $host,
        path: $path,
        pdo: $pdo ?? null,
        replyKb: $replyKb ?? null,
        row: $row,
        rxMiniAppNote: $rxMiniAppNote ?? null,
        setting: $setting ?? null,
        st: $st,
        text: $text
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\TicketCreationAndListing::handle(
        adminCreateError: $adminCreateError,
        adminWebPage: $adminWebPage ?? null,
        departments: $departments,
        domainhosts: $domainhosts ?? null,
        host: $host,
        kb: $kb,
        note: $note ?? null,
        pdo: $pdo ?? null,
        r: $r,
        setting: $setting ?? null,
        st: $st,
        text: $text,
        thread: $thread,
        ticketRow: $ticketRow,
        ticketSubject: $ticketSubject,
        ticketSubjects: $ticketSubjects,
        tickets: $tickets,
        tkBodyById: $tkBodyById,
        tkDateKeep: $tkDateKeep,
        tkDf: $tkDf,
        tkFilterActive: $tkFilterActive,
        tkFilterCriteria: $tkFilterCriteria,
        tkFilterParams: $tkFilterParams,
        tkPg: $tkPg,
        tkQ: $tkQ,
        tkReactionsByMsg: $tkReactionsByMsg,
        tkStatus: $tkStatus,
        tkStatusActive: $tkStatusActive,
        tkStatusOptions: $tkStatusOptions,
        viewT: $viewT
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
    <title>تیکت‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=filterdrawer111">
    <script src="js/theme.js?v=dark62" defer></script>
    <style>
        .tk-chat { display:flex; flex-direction:column; gap:12px; max-height:60vh; overflow-y:auto; padding:6px 2px; }
        .tk-row { display:flex; }
        .tk-row.admin { justify-content:flex-start; }
        .tk-row.user { justify-content:flex-end; }
        .tk-bubble { max-width:100%; box-sizing:border-box; border-radius:var(--radius-lg); padding:10px 13px; font-size:13.5px; line-height:1.9; word-break:break-word; border:1px solid var(--border-mid); }
        .tk-row.admin .tk-bubble { background:var(--accent-soft); border-color:var(--accent-mid); border-top-left-radius:0; }
        .tk-row.user .tk-bubble { background:var(--surface-1); border-top-right-radius:0; }
        .tk-who { font-size:11.5px; font-weight:700; opacity:.8; margin-bottom:4px; }
        .tk-row.admin .tk-who { color:var(--accent); }
        .tk-time { font-size:10.5px; color:var(--text-muted); margin-top:6px; direction:ltr; }
        .tk-media-grid { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; max-width:100%; }
        .tk-thumb { width:92px; height:92px; min-width:92px; max-width:92px; min-height:92px; max-height:92px; border-radius:var(--radius-sm); overflow:hidden; cursor:pointer; border:1px solid var(--border-mid); background:var(--surface-2); position:relative; flex:0 0 92px; box-sizing:border-box; transition:transform .12s; }
        .tk-thumb:hover { transform:scale(1.04); }
        .tk-thumb img, .tk-thumb video { width:100%; height:100%; object-fit:cover; display:block; }
        .tk-thumb .tk-play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:24px; color:#fff; background:rgba(0,0,0,.30); }
        .tk-reply { display:flex; flex-direction:column; gap:10px; margin-top:16px; }
        .tk-reply textarea { width:100%; min-height:74px; resize:vertical; }
        .tk-actions { display:flex; gap:8px; flex-wrap:wrap; }

        .tk-compose-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:12px; }
        .tk-compose-hint { display:flex; align-items:center; gap:7px; color:var(--text-muted); font-size:12px; line-height:1.5; }
        .tk-compose-hint .svg-icon { width:15px; height:15px; flex-shrink:0; opacity:.75; }
        .tk-compose-btns { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-inline-start:auto; }

        .tk-danger-zone { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:18px; padding:14px 16px; border:1px solid var(--border-soft); border-radius:var(--radius-md); background:var(--surface-2); }
        .tk-danger-zone__label { display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:12.5px; font-weight:700; }
        .tk-danger-zone__label .svg-icon { width:16px; height:16px; flex-shrink:0; opacity:.8; }
        .tk-danger-zone__btns { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-inline-start:auto; }
        .tk-danger-zone__btns form { display:contents; }
        .tk-btn-delete { opacity:.85; }
        .tk-btn-delete:hover { opacity:1; }

        @media (max-width:600px) {
            .tk-compose-bar, .tk-danger-zone { flex-direction:column; align-items:stretch; }
            .tk-compose-btns, .tk-danger-zone__btns { margin-inline-start:0; }
            .tk-compose-btns .btn, .tk-danger-zone__btns .btn { flex:1 1 auto; justify-content:center; }
        }
        .tkup-wrap { display:flex; flex-direction:column; gap:8px; }
        .tkup-slot { display:flex; flex-direction:column; gap:8px; }
        .tkup-slot [hidden] { display:none !important; }
        .tkup-input { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; }
        .tkup-drop { display:flex; align-items:center; justify-content:center; gap:10px; min-height:64px; padding:14px; border:1.5px dashed var(--border-mid); border-radius:var(--radius-md); background:var(--surface-2); color:var(--text-muted); cursor:pointer; font-size:13px; font-weight:600; transition:.18s; user-select:none; }
        .tkup-dropwrap { display:flex; align-items:stretch; gap:8px; }
        .tkup-dropwrap .tkup-drop { flex:1 1 auto; min-width:0; }
        .tkup-slot-rm { flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; width:44px; border:1.5px dashed var(--border-mid); border-radius:var(--radius-md); background:var(--surface-2); color:var(--text-muted); cursor:pointer; transition:.18s; }
        .tkup-slot-rm:hover { border-color:var(--color-danger); color:var(--color-danger); background:var(--color-danger-soft); border-style:solid; }
        .tkup-slot-rm .svg-icon { width:17px; height:17px; }
        .tkup-slot-rm[hidden] { display:none !important; }
        .tkup-drop .svg-icon { width:20px; height:20px; margin-inline-start:0; }
        .tkup-drop .svg-icon + * { margin-inline-start:0; }
        .tkup-drop:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-soft); }
        .tkup-drop:focus-within { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        .tkup-drop:active { transform:scale(.99); }
        .tkup-chip { display:flex; align-items:center; gap:10px; padding:8px 10px; background:var(--surface-2); border:1px solid var(--border-soft); border-radius:var(--radius-md); }
        .tkup-thumb { display:block; width:44px; height:44px; min-width:44px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--border-mid); background:var(--surface-3); }
        .tkup-thumb img, .tkup-thumb video { width:100%; height:100%; object-fit:cover; display:block; }
        .tkup-meta { flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .tkup-name { font-size:12.5px; direction:ltr; unicode-bidi:isolate; text-align:right; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tkup-size { font-size:11px; color:var(--text-dim); direction:ltr; text-align:right; }
        .tkup-rm { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; min-width:28px; padding:0; border:none; border-radius:999px; background:transparent; color:var(--text-muted); cursor:pointer; transition:.14s; }
        .tkup-rm .svg-icon { width:14px; height:14px; margin-inline-start:0; }
        .tkup-rm:hover { color:var(--color-danger); background:var(--color-danger-soft); }
        .tkup-error { font-size:11.5px; color:var(--color-danger); background:var(--color-danger-soft); border-radius:var(--radius-md); padding:6px 10px; }
        .tkup-progress { height:4px; background:var(--surface-3); border-radius:999px; overflow:hidden; margin-top:8px; }
        .tkup-progress span { display:block; height:100%; width:40%; background:var(--accent); border-radius:999px; animation:tkup-slide 1.1s ease-in-out infinite; }
        @keyframes tkup-slide { 0% { transform:translateX(260%); } 100% { transform:translateX(-260%); } }
        @media (prefers-reduced-motion: reduce) { .tkup-progress span { animation:none; width:100%; } }
        @media (max-width:600px){ .tkup-drop { min-height:56px; } }
        .tk-lightbox { position:fixed; inset:0; background:rgba(0,0,0,.88); display:none; align-items:center; justify-content:center; z-index:9999; padding:18px; }
        .tk-lightbox.open { display:flex; }
        .tk-lightbox img, .tk-lightbox video { max-width:96vw; max-height:92vh; border-radius:var(--radius-md); }
        .tk-lightbox__close { position:absolute; top:12px; inset-inline-end:16px; color:#fff; font-size:32px; cursor:pointer; line-height:1; }
        @media (max-width:600px){ .tk-bubble{ max-width:90%; } .tk-thumb{ width:78px; height:78px; } }

        .tk-bubble-wrap { position:relative; max-width:78%; }
        .tk-quote { border-inline-start:3px solid var(--accent-mid, #9b8cff); background:rgba(127,127,127,.10); border-radius:var(--radius-xs); padding:5px 8px; font-size:11.5px; color:var(--text-muted); margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tk-receipt { display:inline-flex; align-items:center; margin-inline-start:4px; color:var(--text-muted); vertical-align:middle; }
        .tk-receipt .svg-icon { width:12px; height:12px; }
        .tk-receipt .tk-receipt-2 { margin-inline-start:-6px; }
        .tk-receipt.is-seen { color:var(--accent, #6c5ce7); }
        .tk-reactions { display:flex; flex-wrap:wrap; gap:4px; margin-top:4px; }
        .tk-reaction-chip { display:inline-flex; align-items:center; font-size:12px; background:var(--surface-2); border:1px solid var(--border-mid); border-radius:999px; padding:1px 7px; line-height:1.6; }
        .tk-reaction-chip.is-mine { border-color:var(--accent-mid,#9b8cff); background:var(--accent-soft); }
        .tk-msg-actions { display:flex; gap:4px; margin-top:4px; opacity:0; pointer-events:none; transition:opacity .14s ease; }
        .tk-row:hover .tk-msg-actions, .tk-msg-actions:focus-within { opacity:1; pointer-events:auto; }
        .tk-msg-action-btn { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:999px; border:1px solid var(--border-mid); background:var(--surface-2); color:var(--text-muted); font-size:13px; cursor:pointer; transition:background-color .14s ease, border-color .14s ease, color .14s ease; }
        .tk-msg-action-btn .svg-icon { width:13px; height:13px; }
        .tk-msg-action-btn:hover { background:var(--accent-soft); color:var(--accent); border-color:var(--accent-mid,#9b8cff); }
        .tk-reaction-picker { position:absolute; top:-38px; inset-inline-start:0; display:flex; gap:4px; background:var(--surface-1); border:1px solid var(--border-mid); border-radius:999px; padding:5px 7px; box-shadow:0 8px 20px rgba(0,0,0,.25); z-index:20; }
        .tk-reaction-picker-floating { position:fixed; top:auto; left:auto; inset-inline-start:auto; z-index:9998; }
        .tk-reaction-opt { background:transparent; border:none; font-size:17px; line-height:1; cursor:pointer; padding:2px; border-radius:var(--radius-xs); transition:transform .12s ease, background .12s ease; }
        .tk-reaction-opt:hover { background:var(--surface-2); transform:scale(1.15); }
        .tk-reply-preview-bar { display:flex; align-items:center; justify-content:space-between; gap:8px; border-inline-start:3px solid var(--accent-mid,#9b8cff); background:var(--surface-2); border-radius:var(--radius-md); padding:7px 10px; margin-bottom:8px; }
        .tk-reply-preview-who { font-size:11px; font-weight:700; color:var(--accent,#6c5ce7); }
        .tk-reply-preview-text { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:320px; }
        .tk-reply-cancel { flex:0 0 auto; cursor:pointer; color:var(--text-muted); font-size:20px; line-height:1; padding:0 4px; }
        .tk-reply-cancel:hover { color:var(--danger,#ef4444); }

        .tk-bulk-bar { display:flex; align-items:center; justify-content:flex-end; gap:10px; margin-bottom:12px; }
    </style>
</head>
<body class="fx-tickets">

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-tickets<?php echo $viewT === '' ? ' fx-page-list fx-ticket-inbox' : ' fx-ticket-thread'; ?>">

            <?php if ($viewT === ''): ?>

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('message', 'svg-icon svg-lg'); ?>
                        تیکت‌ها
                    </div>
                    <div class="page-head__sub">مدیریت تیکت‌های پشتیبانی کاربران از ربات و مینی‌اپ</div>
                </div>
                <div class="chip-row">
                    <button type="button" class="btn btn-soft-success" onclick="openModal('modal-open-ticket')"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> باز کردن تیکت</button>
                </div>
            </div>

            <?php if ($adminCreateError !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($adminCreateError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_flash_html(); ?>

            <div class="invoice-overview">
                <div class="invoice-overview__main"><span class="invoice-overview__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('message', 'svg-icon'); ?></span><div><span><?php echo $tkFilterActive ? 'تیکت‌های مطابق فیلتر' : 'کل تیکت‌های ثبت‌شده'; ?></span><strong><?php echo strtr(number_format((int)$tkPg['total']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?><small>تیکت</small></strong></div></div>
                <div class="invoice-overview__detail"><span>وضعیت انتخاب‌شده</span><b><?php echo htmlspecialchars($tkStatusActive ? $tkStatusOptions[$tkStatus] : 'همهٔ وضعیت‌ها', ENT_QUOTES, 'UTF-8'); ?></b></div>
                <div class="invoice-overview__detail"><span>بازهٔ نمایش</span><b><?php echo htmlspecialchars($tkDf['active'] ? $tkDf['label'] : 'تمام تاریخ‌ها', ENT_QUOTES, 'UTF-8'); ?></b></div>
                <?php if ($tkFilterActive): ?><a href="tickets.php" class="btn btn-outline btn-sm invoice-reset">پاک‌کردن فیلترها</a><?php endif; ?>
            </div>

            <details class="wrapper fx-page-list fx-filter-drawer fx-page-tickets fx-ticket-inbox">
                <summary class="fx-filter-drawer__summary">
                    <span class="fx-filter-drawer__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('filter', 'svg-icon'); ?></span>
                    <span class="fx-filter-drawer__text"><strong>جستجو و فیلترها</strong><small>جستجو، وضعیت و بازهٔ زمانی</small></span>
                    <span class="fx-filter-drawer__action"><span class="fx-filter-drawer__show">نمایش فیلترها</span><span class="fx-filter-drawer__hide">بستن فیلترها</span></span>
                    <span class="fx-filter-drawer__chevron" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-down', 'svg-icon'); ?></span>
                </summary>
            <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('tickets.php', $tkQ, array_merge(['status' => $tkStatus !== '' ? $tkStatus : null], $tkDateKeep), 'جستجو در کد پیگیری، آیدی کاربر یا متن پیام…'); ?>

            <?php echo \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_ui('tickets.php', $tkStatusOptions, $tkStatus, array_merge(['q' => $tkQ !== '' ? $tkQ : null], $tkDateKeep)); ?>

            <?php $fxFd = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_parts('tickets.php', $tkFilterParams, $tkFilterActive ? (int)$tkPg['total'] : 0, $tkFilterCriteria); ?>
            <?php echo \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_ui('tickets.php', '', ['q' => $tkQ !== '' ? $tkQ : null, 'status' => $tkStatus !== '' ? $tkStatus : null], '', $fxFd['button'], $fxFd['form'], true); ?>
            </details>

            <div class="card invoice-results">
                <div class="invoice-results__head"><div><h2>صندوق تیکت‌ها</h2><p>پیگیری پیام‌ها و درخواست‌های پشتیبانی کاربران</p></div><span class="invoice-page-count"><?php echo strtr(number_format(count($tickets)), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> تیکت در این صفحه</span></div>
                <div class="tk-bulk-bar" id="tk-bulk-bar">
                    <button type="button" class="btn btn-sm btn-soft-danger js-bulk-delete-btn" style="display:none;" id="tk-bulk-delete-btn"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها</button>
                </div>
                <div class="table-wrap">
                    <table id="ticketsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th data-no-sort="1"><input type="checkbox" id="tk-select-all"></th>
                                <th>کد پیگیری</th>
                                <th>کاربر</th>
                                <th>دپارتمان</th>
                                <th>موضوع</th>
                                <th>وضعیت</th>
                                <th>آخرین به‌روزرسانی</th>
                                <th>پیام‌ها</th>
                                <th data-no-sort="1">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $tt):
                            [$slabel, $sclass] = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_status_badge((string)$tt['status']); ?>
                            <tr data-detail-row data-detail-title="تیکت <?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td><input type="checkbox" class="tk-row-check" value="<?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                                <td data-label="کد پیگیری" style="direction:ltr; text-align:right;" data-summary="1"><bdi class="invoice-reference"><?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?></bdi></td>
                                <td data-label="کاربر">
                                    <a href="user.php?id=<?php echo urlencode((string)$tt['iduser']); ?>" class="text-link" style="direction:ltr; display:inline-block;"><?php echo htmlspecialchars((string)$tt['iduser'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </td>
                                <td data-label="دپارتمان" data-filter-value="<?php echo htmlspecialchars((string)$tt['name_departman'], ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><?php echo htmlspecialchars((string)$tt['name_departman'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php $tsubj = $ticketSubjects[$tt['Tracking']] ?? ''; ?>
                                <td data-label="موضوع"><?php echo $tsubj !== '' ? htmlspecialchars($tsubj, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                <td data-label="وضعیت" data-filter-value="<?php echo htmlspecialchars($slabel, ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><span class="badge <?php echo $sclass; ?>"><?php echo $slabel; ?></span></td>
                                <td data-label="آخرین به‌روزرسانی" style="direction:ltr; text-align:right; font-size:11.5px;"><?php echo htmlspecialchars(\Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_jdate($tt['last_time']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="پیام‌ها"><?php echo (int)$tt['cnt']; ?></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <a href="tickets.php?t=<?php echo urlencode((string)$tt['Tracking']); ?>" class="btn btn-sm btn-soft-purple"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('message', 'svg-icon'); ?> مشاهده</a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('کل این تیکت حذف شود؟');">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="t" value="<?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html($tkPg['page'], $tkPg['pages'], $tkPg['total'], count($tickets), 'tickets.php', array_merge(['q' => $tkQ !== '' ? $tkQ : null, 'status' => $tkStatus !== '' ? $tkStatus : null], $tkDateKeep)); ?>
                </div>
            </div>

            <div id="modal-open-ticket" class="modal-overlay<?php echo $adminCreateError !== '' ? ' active' : ''; ?>">
                <div class="modal-box" style="max-width:560px;">
                    <div class="modal-head">
                        <span class="modal-head__title">باز کردن تیکت جدید</span>
                        <button type="button" class="modal-close" onclick="closeModal('modal-open-ticket')">&times;</button>
                    </div>
                    <form method="POST" action="tickets.php" enctype="multipart/form-data">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="_action" value="admin_create">
                        <div class="form-group">
                            <label class="form-label">دپارتمان</label>
                            <select name="department" class="form-control" required>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo htmlspecialchars((string)$d['name_departman'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$d['name_departman'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">موضوع</label>
                            <input type="text" name="subject" class="form-control" placeholder="موضوع تیکت" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">شناسه عددی کاربر</label>
                            <input type="number" name="target_user_id" class="form-control" placeholder="مثلاً 123456789" required min="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">پیام</label>
                            <textarea name="text" class="form-control" placeholder="متن پیام..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">تصاویر / ویدیو (اختیاری)</label>
                            <div id="ac-media-wrap" class="tkup-wrap">
                                <div class="tkup-slot">
                                                                        <div class="tkup-dropwrap">
                                        <label class="tkup-drop">
                                            <input type="file" name="media[]" accept="image/*,video/*" class="tkup-input ac-media-input">
                                            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            <span>انتخاب تصویر / ویدیو</span>
                                        </label>
                                        <button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                    </div>
                                    <div class="tkup-chip" hidden>
                                        <span class="tkup-thumb"></span>
                                        <span class="tkup-meta">
                                            <span class="tkup-name" dir="ltr"></span>
                                            <span class="tkup-size" dir="ltr"></span>
                                        </span>
                                        <button type="button" class="tkup-rm" aria-label="حذف فایل"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon'); ?></button>
                                    </div>
                                    <div class="tkup-error" role="alert" hidden></div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:6px;">
                                <small style="opacity:.7; font-size:11.5px;">می‌توانید تا ۵ عکس/ویدیو اضافه کنید.</small>
                                <button type="button" id="ac-add-media" class="btn btn-sm btn-soft-purple"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> افزودن فایل</button>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-open-ticket')">انصراف</button>
                            <button type="submit" class="btn btn-primary btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> ایجاد تیکت</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php else:
                [$slabel, $sclass] = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_status_badge((string)$ticketRow['status']);
                $isClosed = $ticketRow['status'] === 'close'; ?>

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('message', 'svg-icon svg-lg'); ?>
                        تیکت <span style="direction:ltr; font-family:monospace;"><?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="page-head__sub">
                        کاربر: <a href="user.php?id=<?php echo urlencode((string)$ticketRow['iduser']); ?>" class="text-link" style="direction:ltr; display:inline-block;"><?php echo htmlspecialchars((string)$ticketRow['iduser'], ENT_QUOTES, 'UTF-8'); ?></a>
                        — دپارتمان: <?php echo htmlspecialchars((string)$ticketRow['name_departman'], ENT_QUOTES, 'UTF-8'); ?>
                        — موضوع: <?php echo htmlspecialchars($ticketSubject !== '' ? $ticketSubject : '—', ENT_QUOTES, 'UTF-8'); ?>
                        — <span class="badge <?php echo $sclass; ?>"><?php echo $slabel; ?></span>
                    </div>
                </div>
                <div class="chip-row">
                    <a href="tickets.php" class="btn btn-outline btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right', 'svg-icon'); ?> بازگشت به لیست</a>
                </div>
            </div>

            <div class="card ticket-conversation">
                <div class="ticket-conversation__head"><div><span class="ticket-conversation__mark" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('message', 'svg-icon svg-sm'); ?></span><strong>گفت‌وگوی تیکت</strong></div><span class="badge <?php echo $sclass; ?>"><?php echo $slabel; ?></span></div>
                <div class="tk-chat">
                    <?php foreach ($thread as $mrow):
                        $m = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_media((string)$mrow['text']);
                        $mid = (int)$mrow['id'];
                        $side = ($mrow['result'] === 'admin') ? 'admin' : 'user';
                        $who = ($mrow['result'] === 'admin') ? '👨‍💻 پشتیبانی' : '👤 کاربر';
                        $replyToId = isset($mrow['reply_to_id']) ? (int)$mrow['reply_to_id'] : 0;
                        $seen = ($side === 'admin') ? ((int)($mrow['seen_by_user'] ?? 0) === 1) : true;
                        $reactions = $tkReactionsByMsg[$mid] ?? []; ?>
                        <div class="tk-row <?php echo $side; ?>" data-msg-id="<?php echo $mid; ?>" data-msg-body="<?php echo htmlspecialchars($m['body'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="tk-bubble-wrap">
                            <div class="tk-bubble">
                                <div class="tk-who"><?php echo $who; ?></div>
                                <?php if ($replyToId > 0 && isset($tkBodyById[$replyToId])): ?>
                                    <div class="tk-quote"><?php echo htmlspecialchars(mb_substr($tkBodyById[$replyToId], 0, 120) ?: 'پیوست', ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <?php if ($m['body'] !== ''): ?><div><?php echo nl2br(htmlspecialchars($m['body'], ENT_QUOTES, 'UTF-8')); ?></div><?php endif; ?>
                                <?php if (count($m['media'])): ?>
                                <div class="tk-media-grid">
                                    <?php foreach ($m['media'] as $mi => $mItem):
                                        $murl = 'tickets.php?media=' . (int)$mrow['id'] . '&i=' . (int)$mi; ?>
                                        <div class="tk-thumb" data-full="<?php echo htmlspecialchars($murl, ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo $mItem['type'] === 'video' ? 'video' : 'photo'; ?>">
                                            <?php if ($mItem['type'] === 'video'): ?>
                                                <video src="<?php echo htmlspecialchars($murl, ENT_QUOTES, 'UTF-8'); ?>" preload="metadata" muted></video>
                                                <span class="tk-play">▶</span>
                                            <?php else: ?>
                                                <img src="<?php echo htmlspecialchars($murl, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" alt="">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="tk-time">
                                    <?php echo htmlspecialchars(\Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_jdate($mrow['time']), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($side === 'admin'): ?>
                                        <span class="tk-receipt <?php echo $seen ? 'is-seen' : ''; ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon'); ?><?php if ($seen) echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon tk-receipt-2'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (count($reactions)): ?>
                            <div class="tk-reactions">
                                <?php foreach ($reactions as $r): ?>
                                    <span class="tk-reaction-chip<?php echo $r['mine'] ? ' is-mine' : ''; ?>"><?php echo htmlspecialchars($r['emoji'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="tk-msg-actions">
                                <?php if ($side === 'user'): ?>
                                <button type="button" class="tk-msg-action-btn" data-act="react" title="واکنش">🙂</button>
                                <?php endif; ?>
                                <button type="button" class="tk-msg-action-btn" data-act="reply" title="پاسخ"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right-arrow-left', 'svg-icon'); ?></button>
                                <button type="button" class="tk-msg-action-btn" data-act="copy" title="کپی"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('copy', 'svg-icon'); ?></button>
                            </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form class="tk-reply" method="POST" action="tickets.php?t=<?php echo urlencode($viewT); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_action" value="reply">
                    <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="reply_to_id" id="tk-reply-to-id" value="">
                    <div id="tk-reply-preview" class="tk-reply-preview" style="display:none">
                        <div class="tk-reply-preview-bar">
                            <div class="tk-reply-preview-body">
                                <div class="tk-reply-preview-who" id="tk-reply-preview-who"></div>
                                <div class="tk-reply-preview-text" id="tk-reply-preview-text"></div>
                            </div>
                            <span class="tk-reply-cancel" id="tk-reply-cancel">&times;</span>
                        </div>
                    </div>
                    <textarea name="text" class="form-control" placeholder="پاسخ خود را بنویسید..."></textarea>
                    <div id="tk-media-wrap" class="tkup-wrap">
                        <div class="tkup-slot">
                                                        <div class="tkup-dropwrap">
                                <label class="tkup-drop">
                                    <input type="file" name="media[]" accept="image/*,video/*" class="tkup-input tk-media-input">
                                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <span>انتخاب تصویر / ویدیو</span>
                                </label>
                                <button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <div class="tkup-chip" hidden>
                                <span class="tkup-thumb"></span>
                                <span class="tkup-meta">
                                    <span class="tkup-name" dir="ltr"></span>
                                    <span class="tkup-size" dir="ltr"></span>
                                </span>
                                <button type="button" class="tkup-rm" aria-label="حذف فایل"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon'); ?></button>
                            </div>
                            <div class="tkup-error" role="alert" hidden></div>
                        </div>
                    </div>
                    <div class="tk-compose-bar">
                        <div class="tk-compose-hint">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-info', 'svg-icon'); ?>
                            <span>می‌توانید تا ۵ عکس/ویدیو اضافه کنید.</span>
                        </div>
                        <div class="tk-compose-btns">
                            <button type="button" id="tk-add-media" class="btn btn-sm btn-soft-purple"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> افزودن فایل</button>
                            <button type="submit" class="btn btn-primary btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('paper-plane', 'svg-icon'); ?> ارسال پاسخ</button>
                        </div>
                    </div>
                </form>

                <div class="tk-danger-zone">
                    <div class="tk-danger-zone__label">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('sliders', 'svg-icon'); ?>
                        <span>مدیریت تیکت</span>
                    </div>
                    <div class="tk-danger-zone__btns">
                        <?php if ($isClosed): ?>
                            <form method="POST" action="tickets.php">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="_action" value="reopen">
                                <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-soft-success btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon'); ?> باز کردن مجدد تیکت</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="tickets.php" onsubmit="return confirm('این تیکت بسته شود؟');">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="_action" value="close">
                                <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-soft-warning btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('ban', 'svg-icon'); ?> بستن تیکت</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="tickets.php" onsubmit="return confirm('کل این تیکت حذف شود؟ این کار قابل بازگشت نیست.');">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="_action" value="delete">
                            <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-soft-danger btn-sm tk-btn-delete"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف کامل تیکت</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tk-lightbox" id="tk-lightbox"><span class="tk-lightbox__close" id="tk-lightbox-close">&times;</span><div id="tk-lightbox-body"></div></div>

            <script>
            (function () {
                var chat = document.querySelector('.tk-chat');
                if (chat) chat.scrollTop = chat.scrollHeight;

                function tkupInit(wrapId, addBtnId) {
                    var wrap = document.getElementById(wrapId);
                    var add = document.getElementById(addBtnId);
                    if (!wrap || !add) return;
                    var IMG_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
                    var X_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                    function buildSlot() {
                        var slot = document.createElement('div');
                        slot.className = 'tkup-slot';
                        slot.innerHTML = '<div class="tkup-dropwrap"><label class="tkup-drop"><input type="file" name="media[]" accept="image/*,video/*" class="tkup-input">' + IMG_ICON + '<span>انتخاب تصویر / ویدیو</span></label><button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر">' + X_ICON + '</button></div>' +
                            '<div class="tkup-chip" hidden><span class="tkup-thumb"></span><span class="tkup-meta"><span class="tkup-name" dir="ltr"></span><span class="tkup-size" dir="ltr"></span></span><button type="button" class="tkup-rm" aria-label="حذف فایل">' + X_ICON + '</button></div>' +
                            '<div class="tkup-error" role="alert" hidden></div>';
                        return slot;
                    }
                    function refreshAdd() {
                        var slots = wrap.querySelectorAll('.tkup-slot');
                        add.style.display = slots.length >= 5 ? 'none' : '';
                        for (var i = 0; i < slots.length; i++) {
                            var b = slots[i].querySelector('.tkup-slot-rm');
                            if (b) b.hidden = slots.length <= 1;
                        }
                    }
                    function fmtSize(b) {
                        if (b < 1048576) return (b / 1024).toLocaleString('fa-IR', { maximumFractionDigits: 0 }) + ' کیلوبایت';
                        return (b / 1048576).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' مگابایت';
                    }
                    wrap.addEventListener('change', function (e) {
                        var inp = e.target;
                        if (!inp.matches('input[type="file"]')) return;
                        var slot = inp.closest('.tkup-slot');
                        if (!slot) return;
                        var file = inp.files && inp.files[0];
                        if (!file) return;
                        var err = slot.querySelector('.tkup-error');
                        if (file.type.indexOf('image/') !== 0 && file.type.indexOf('video/') !== 0) {
                            inp.value = '';
                            err.textContent = 'نوع فایل مجاز نیست؛ فقط تصویر یا ویدیو انتخاب کنید.';
                            err.hidden = false;
                            return;
                        }
                        err.hidden = true;
                        err.textContent = '';
                        var url = URL.createObjectURL(file);
                        slot.querySelector('.tkup-thumb').innerHTML = file.type.indexOf('video/') === 0
                            ? '<video src="' + url + '" muted playsinline preload="metadata"></video>'
                            : '<img src="' + url + '" alt="">';
                        slot.querySelector('.tkup-name').textContent = file.name;
                        slot.querySelector('.tkup-size').textContent = fmtSize(file.size);
                        slot.querySelector('.tkup-drop').hidden = true;
                        slot.querySelector('.tkup-chip').hidden = false;
                    });
                    wrap.addEventListener('click', function (e) {
                        var rm = e.target.closest('.tkup-rm, .tkup-slot-rm');
                        if (!rm) return;
                        var slot = rm.closest('.tkup-slot');
                        if (!slot) return;
                        if (rm.classList.contains('tkup-slot-rm') && wrap.querySelectorAll('.tkup-slot').length <= 1) return;
                        var th = slot.querySelector('.tkup-thumb');
                        var m = th && th.firstChild;
                        if (m && m.src) URL.revokeObjectURL(m.src);
                        slot.remove();
                        refreshAdd();
                    });
                    add.addEventListener('click', function () {
                        if (wrap.querySelectorAll('.tkup-slot').length >= 5) return;
                        wrap.appendChild(buildSlot());
                        refreshAdd();
                    });
                    refreshAdd();
                }

                function tkupBindSubmit(form) {
                    if (!form) return;
                    var btn = form.querySelector('button[type="submit"]');
                    if (!btn) return;
                    btn.dataset.tkupLabel = btn.innerHTML;
                    form.addEventListener('submit', function (e) {
                        if (form.classList.contains('tkup-busy')) {
                            e.preventDefault();
                            return;
                        }
                        form.classList.add('tkup-busy');
                        form.querySelectorAll('.tkup-slot').forEach(function (s) {
                            var i = s.querySelector('input[type="file"]');
                            if (i && (!i.files || !i.files.length)) s.remove();
                        });
                        form.querySelectorAll('.tkup-thumb').forEach(function (t) {
                            var m = t.firstChild;
                            if (m && m.src) URL.revokeObjectURL(m.src);
                        });
                        btn.disabled = true;
                        btn.setAttribute('aria-busy', 'true');
                        btn.innerHTML = '<svg class="svg-icon spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg> در حال ارسال…';
                        var bar = document.createElement('div');
                        bar.className = 'tkup-progress';
                        bar.innerHTML = '<span></span>';
                        var anchor = form.querySelector('.tk-compose-bar') || form.querySelector('.tk-actions') || form.querySelector('.modal-foot');
                        if (anchor) anchor.insertAdjacentElement('afterend', bar);
                    });
                    window.addEventListener('pageshow', function () {
                        if (!form.classList.contains('tkup-busy')) return;
                        form.classList.remove('tkup-busy');
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                        btn.innerHTML = btn.dataset.tkupLabel;
                        var bar = form.querySelector('.tkup-progress');
                        if (bar) bar.remove();
                    });
                }

                tkupInit('tk-media-wrap', 'tk-add-media');
                tkupBindSubmit(document.querySelector('.tk-reply'));

                var lb = document.getElementById('tk-lightbox');
                var lbBody = document.getElementById('tk-lightbox-body');
                var lbClose = document.getElementById('tk-lightbox-close');
                function closeLb() { lb.classList.remove('open'); lbBody.innerHTML = ''; }
                document.querySelectorAll('.tk-thumb').forEach(function (th) {
                    th.addEventListener('click', function () {
                        var url = th.getAttribute('data-full');
                        var type = th.getAttribute('data-type');
                        lbBody.innerHTML = type === 'video'
                            ? '<video src="' + url + '" controls autoplay playsinline></video>'
                            : '<img src="' + url + '" alt="">';
                        lb.classList.add('open');
                    });
                });
                if (lbClose) lbClose.addEventListener('click', closeLb);
                if (lb) lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLb(); });

                var CSRF = <?php echo json_encode($_csrf); ?>;
                var REACTION_EMOJI = <?php echo json_encode($TK_REACTION_EMOJI, JSON_UNESCAPED_UNICODE); ?>;
                var chatEl = document.querySelector('.tk-chat');
                var replyIdInput = document.getElementById('tk-reply-to-id');
                var replyPreview = document.getElementById('tk-reply-preview');
                var replyPreviewWho = document.getElementById('tk-reply-preview-who');
                var replyPreviewText = document.getElementById('tk-reply-preview-text');
                var replyCancel = document.getElementById('tk-reply-cancel');
                var replyTextarea = document.querySelector('.tk-reply textarea[name="text"]');

                function closePickers() {
                    document.querySelectorAll('.tk-reaction-picker').forEach(function (p) { p.remove(); });
                }

                function positionFloatingPicker(picker, anchorBtn) {
                    var r = anchorBtn.getBoundingClientRect();
                    var top = r.top - 44;
                    if (top < 8) top = r.bottom + 6;
                    picker.style.top = top + 'px';
                    var left = r.left;
                    var maxLeft = window.innerWidth - picker.offsetWidth - 8;
                    if (left > maxLeft) left = Math.max(8, maxLeft);
                    picker.style.left = left + 'px';
                }
                window.addEventListener('scroll', closePickers, true);
                window.addEventListener('resize', closePickers);

                function clearReply() {
                    if (replyIdInput) replyIdInput.value = '';
                    if (replyPreview) replyPreview.style.display = 'none';
                }
                if (replyCancel) replyCancel.addEventListener('click', clearReply);

                if (chatEl) {
                    chatEl.addEventListener('click', function (e) {
                        var btn = e.target.closest ? e.target.closest('.tk-msg-action-btn') : null;
                        if (!btn) {
                            if (!e.target.closest('.tk-reaction-picker')) closePickers();
                            return;
                        }
                        var row = btn.closest('.tk-row');
                        if (!row) return;
                        var mid = row.getAttribute('data-msg-id');
                        var body = row.getAttribute('data-msg-body') || '';
                        var act = btn.getAttribute('data-act');
                        var isAdmin = row.classList.contains('admin');

                        if (act === 'copy') {
                            var doCopy = function (text) {
                                if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
                                var ta = document.createElement('textarea');
                                ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                                document.body.appendChild(ta); ta.select();
                                try { document.execCommand('copy'); } catch (_) {}
                                ta.remove();
                                return Promise.resolve();
                            };
                            doCopy(body);
                            return;
                        }

                        if (act === 'reply') {
                            if (replyIdInput) replyIdInput.value = mid;
                            if (replyPreviewWho) replyPreviewWho.textContent = isAdmin ? 'پشتیبانی' : 'کاربر';
                            if (replyPreviewText) replyPreviewText.textContent = body.slice(0, 140) || 'پیوست';
                            if (replyPreview) replyPreview.style.display = '';
                            if (replyTextarea) replyTextarea.focus();
                            return;
                        }

                        if (act === 'react') {
                            closePickers();
                            var picker = document.createElement('div');
                            picker.className = 'tk-reaction-picker tk-reaction-picker-floating';
                            REACTION_EMOJI.forEach(function (em) {
                                var opt = document.createElement('button');
                                opt.type = 'button';
                                opt.className = 'tk-reaction-opt';
                                opt.textContent = em;
                                opt.addEventListener('click', function () {
                                    var existingMine = row.querySelector('.tk-reaction-chip.is-mine');
                                    var current = existingMine ? existingMine.textContent : '';
                                    var toSend = current === em ? '' : em;
                                    closePickers();
                                    var fd = new FormData();
                                    fd.append('_csrf', CSRF);
                                    fd.append('message_id', mid);
                                    fd.append('emoji', toSend);
                                    fetch('tickets.php?ajax=react', { method: 'POST', body: fd })
                                        .then(function (r) { return r.json(); })
                                        .then(function (res) {
                                            if (!res || !res.ok) return;
                                            var wrap = row.querySelector('.tk-bubble-wrap');
                                            var old = wrap.querySelector('.tk-reactions');
                                            if (old) old.remove();
                                            if (res.reactions && res.reactions.length) {
                                                var box = document.createElement('div');
                                                box.className = 'tk-reactions';
                                                res.reactions.forEach(function (rr) {
                                                    var chip = document.createElement('span');
                                                    chip.className = 'tk-reaction-chip' + (rr.mine ? ' is-mine' : '');
                                                    chip.textContent = rr.emoji;
                                                    box.appendChild(chip);
                                                });
                                                wrap.querySelector('.tk-bubble').insertAdjacentElement('afterend', box);
                                            }
                                        });
                                });
                                picker.appendChild(opt);
                            });
                            document.body.appendChild(picker);
                            positionFloatingPicker(picker, btn);
                            return;
                        }
                    });
                }

                var TRACKING = <?php echo json_encode($viewT); ?>;
                var RECEIPT_CHECK_2_HTML = <?php echo json_encode(\Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon tk-receipt-2')); ?>;
                function pollTicketState() {
                    if (!TRACKING) return;
                    fetch('tickets.php?ajax=poll_state&t=' + encodeURIComponent(TRACKING))
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (!res || !res.ok || !Array.isArray(res.messages)) return;
                            res.messages.forEach(function (m) {
                                var row = document.querySelector('.tk-row[data-msg-id="' + m.id + '"]');
                                if (!row) return;
                                var receipt = row.querySelector('.tk-receipt');
                                if (receipt) {
                                    receipt.classList.toggle('is-seen', !!m.seen);
                                    var second = receipt.querySelector('.tk-receipt-2');
                                    if (m.seen && !second) {
                                        receipt.insertAdjacentHTML('beforeend', RECEIPT_CHECK_2_HTML);
                                    } else if (!m.seen && second) {
                                        second.remove();
                                    }
                                }
                                var wrap = row.querySelector('.tk-bubble-wrap');
                                if (!wrap) return;
                                var old = wrap.querySelector('.tk-reactions');
                                var oldEmoji = old ? Array.prototype.map.call(old.children, function (c) { return c.textContent; }).join(',') : '';
                                var newEmoji = (m.reactions || []).map(function (r) { return r.emoji; }).join(',');
                                if (oldEmoji === newEmoji) return;
                                if (old) old.remove();
                                if (m.reactions && m.reactions.length) {
                                    var box = document.createElement('div');
                                    box.className = 'tk-reactions';
                                    m.reactions.forEach(function (rr) {
                                        var chip = document.createElement('span');
                                        chip.className = 'tk-reaction-chip' + (rr.mine ? ' is-mine' : '');
                                        chip.textContent = rr.emoji;
                                        box.appendChild(chip);
                                    });
                                    wrap.querySelector('.tk-bubble').insertAdjacentElement('afterend', box);
                                }
                            });
                        })
                        .catch(function () {});
                }
                if (TRACKING) setInterval(pollTicketState, 4000);
            })();
            </script>

            <?php endif; ?>

        </div>
    </section>
</section>

<?php if ($viewT === ''): ?>
<script src="js/bulk-select.js?v=fx2"></script>
<script>
  function openModal(id) { var m = document.getElementById(id); if (!m) return; if (typeof window.closeDetailSheet === 'function') window.closeDetailSheet(); m.classList.add('active'); }
  function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

  document.addEventListener("DOMContentLoaded", function () {

    function tkupInit(wrapId, addBtnId) {
      var wrap = document.getElementById(wrapId);
      var add = document.getElementById(addBtnId);
      if (!wrap || !add) return;
      var IMG_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
      var X_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
      function buildSlot() {
        var slot = document.createElement('div');
        slot.className = 'tkup-slot';
        slot.innerHTML = '<div class="tkup-dropwrap"><label class="tkup-drop"><input type="file" name="media[]" accept="image/*,video/*" class="tkup-input">' + IMG_ICON + '<span>انتخاب تصویر / ویدیو</span></label><button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر">' + X_ICON + '</button></div>' +
          '<div class="tkup-chip" hidden><span class="tkup-thumb"></span><span class="tkup-meta"><span class="tkup-name" dir="ltr"></span><span class="tkup-size" dir="ltr"></span></span><button type="button" class="tkup-rm" aria-label="حذف فایل">' + X_ICON + '</button></div>' +
          '<div class="tkup-error" role="alert" hidden></div>';
        return slot;
      }
      function refreshAdd() {
        var slots = wrap.querySelectorAll('.tkup-slot');
        add.style.display = slots.length >= 5 ? 'none' : '';
        for (var i = 0; i < slots.length; i++) {
          var b = slots[i].querySelector('.tkup-slot-rm');
          if (b) b.hidden = slots.length <= 1;
        }
      }
      function fmtSize(b) {
        if (b < 1048576) return (b / 1024).toLocaleString('fa-IR', { maximumFractionDigits: 0 }) + ' کیلوبایت';
        return (b / 1048576).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' مگابایت';
      }
      wrap.addEventListener('change', function (e) {
        var inp = e.target;
        if (!inp.matches('input[type="file"]')) return;
        var slot = inp.closest('.tkup-slot');
        if (!slot) return;
        var file = inp.files && inp.files[0];
        if (!file) return;
        var err = slot.querySelector('.tkup-error');
        if (file.type.indexOf('image/') !== 0 && file.type.indexOf('video/') !== 0) {
          inp.value = '';
          err.textContent = 'نوع فایل مجاز نیست؛ فقط تصویر یا ویدیو انتخاب کنید.';
          err.hidden = false;
          return;
        }
        err.hidden = true;
        err.textContent = '';
        var url = URL.createObjectURL(file);
        slot.querySelector('.tkup-thumb').innerHTML = file.type.indexOf('video/') === 0
          ? '<video src="' + url + '" muted playsinline preload="metadata"></video>'
          : '<img src="' + url + '" alt="">';
        slot.querySelector('.tkup-name').textContent = file.name;
        slot.querySelector('.tkup-size').textContent = fmtSize(file.size);
        slot.querySelector('.tkup-drop').hidden = true;
        slot.querySelector('.tkup-chip').hidden = false;
      });
      wrap.addEventListener('click', function (e) {
        var rm = e.target.closest('.tkup-rm, .tkup-slot-rm');
        if (!rm) return;
        var slot = rm.closest('.tkup-slot');
        if (!slot) return;
        if (rm.classList.contains('tkup-slot-rm') && wrap.querySelectorAll('.tkup-slot').length <= 1) return;
        var th = slot.querySelector('.tkup-thumb');
        var m = th && th.firstChild;
        if (m && m.src) URL.revokeObjectURL(m.src);
        slot.remove();
        refreshAdd();
      });
      add.addEventListener('click', function () {
        if (wrap.querySelectorAll('.tkup-slot').length >= 5) return;
        wrap.appendChild(buildSlot());
        refreshAdd();
      });
      refreshAdd();
    }

    function tkupBindSubmit(form) {
      if (!form) return;
      var btn = form.querySelector('button[type="submit"]');
      if (!btn) return;
      btn.dataset.tkupLabel = btn.innerHTML;
      form.addEventListener('submit', function (e) {
        if (form.classList.contains('tkup-busy')) {
          e.preventDefault();
          return;
        }
        form.classList.add('tkup-busy');
        form.querySelectorAll('.tkup-slot').forEach(function (s) {
          var i = s.querySelector('input[type="file"]');
          if (i && (!i.files || !i.files.length)) s.remove();
        });
        form.querySelectorAll('.tkup-thumb').forEach(function (t) {
          var m = t.firstChild;
          if (m && m.src) URL.revokeObjectURL(m.src);
        });
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML = '<svg class="svg-icon spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg> در حال ارسال…';
        var bar = document.createElement('div');
        bar.className = 'tkup-progress';
        bar.innerHTML = '<span></span>';
        var anchor = form.querySelector('.tk-actions') || form.querySelector('.modal-foot');
        if (anchor) anchor.insertAdjacentElement('afterend', bar);
      });
      window.addEventListener('pageshow', function () {
        if (!form.classList.contains('tkup-busy')) return;
        form.classList.remove('tkup-busy');
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.innerHTML = btn.dataset.tkupLabel;
        var bar = form.querySelector('.tkup-progress');
        if (bar) bar.remove();
      });
    }

    tkupInit('ac-media-wrap', 'ac-add-media');
    tkupBindSubmit(document.querySelector('#modal-open-ticket form'));

    var CSRF = <?php echo json_encode($_csrf); ?>;
    var table = document.getElementById('ticketsTable');
    var selectAll = document.getElementById('tk-select-all');
    var bulkDeleteBtn = document.getElementById('tk-bulk-delete-btn');

    var fxTicketsHandle = FxBulkSelect.init({
      scope: 'default',
      checkboxSelector: '.tk-row-check',
      scopeRoot: table || document,
      formEl: null,
      deleteButtonSelector: '.js-bulk-delete-btn',
      clearOnQueryFlags: []
    });

    function syncSelectAll() {
      if (!selectAll || !table) return;
      var visible = Array.prototype.slice.call(table.querySelectorAll('tbody .tk-row-check'));
      var persisted = FxBulkSelect.getPersistedIds(fxTicketsHandle.key);
      selectAll.checked = visible.length > 0 && visible.every(function (cb) { return persisted.indexOf(cb.value) !== -1; });
    }
    syncSelectAll();

    if (table) {
      table.addEventListener('change', function (e) {
        var cb = e.target;
        if (!cb.classList || !cb.classList.contains('tk-row-check')) return;
        syncSelectAll();
      });
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        var visible = Array.prototype.slice.call(table.querySelectorAll('tbody .tk-row-check'));
        visible.forEach(function (cb) {
          cb.checked = selectAll.checked;
          if (selectAll.checked) fxTicketsHandle.add(cb.value); else fxTicketsHandle.remove(cb.value);
        });
        syncSelectAll();
      });
    }

    if (bulkDeleteBtn) {
      bulkDeleteBtn.addEventListener('click', function () {
        var ids = FxBulkSelect.getPersistedIds(fxTicketsHandle.key);
        if (ids.length === 0) return;
        if (!confirm('همه تیکت‌های انتخاب‌شده (' + ids.length + ' مورد) حذف شوند؟ این کار قابل بازگشت نیست.')) return;
        var fd = new FormData();
        fd.append('_csrf', CSRF);
        fd.append('trackings', JSON.stringify(ids));
        bulkDeleteBtn.disabled = true;
        fetch('tickets.php?ajax=bulk_delete', { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res && res.ok) {
              fxTicketsHandle.clear();
              location.reload();
            } else {
              bulkDeleteBtn.disabled = false;
              alert('حذف با خطا مواجه شد.');
            }
          })
          .catch(function () {
            bulkDeleteBtn.disabled = false;
            alert('حذف با خطا مواجه شد.');
          });
      });
    }
  });
</script>
<?php endif; ?>
</body>
</html>
