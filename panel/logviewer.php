<?php

require_once __DIR__ . '/../src/bootstrap.php';

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') {
    http_response_code(503);
    header('Retry-After: 3600');
    exit;
}
require_once __DIR__ . '/lib/csrf.php';

$sessionUser = $_SESSION['user'] ?? null;
$lvCsrf = \Faoxima\Admin\Web\Support\Logviewer::pageCsrf(
    $pdo,
    is_scalar($sessionUser) ? (string) $sessionUser : null,
    [\Faoxima\Admin\Web\Support\Csrf::class, 'fx_csrf_token'],
    static function ($ip): bool {
        if (\Faoxima\Admin\Web\Support\Csrf::fx_admin_ip_allowed($ip)) {
            return true;
        }
        session_unset();
        session_destroy();
        return false;
    },
);
if ($lvCsrf === null) {
    header('Location: login.php');
    exit;
}
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>مشاهده ارور‌ها | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat39">
    <link rel="stylesheet" href="css/logviewer.css?v=lv1">
    <style>
        #lv .lv-stat,
        #lv .lv-toolbar,
        #lv .lv-state {
            background: radial-gradient(ellipse at 0% 0%, var(--accent-soft), transparent 65%), var(--surface-1);
        }
    </style>
    <script src="js/theme.js?v=dark62" defer></script>
    <script src="js/logviewer.js?v=lv1" defer></script>
</head>
<body>
<section id="container">
    <?php include 'header.php'; ?>
    <section id="main-content">
        <div class="wrapper lv" id="lv" data-csrf="<?php echo htmlspecialchars($lvCsrf, ENT_QUOTES, 'UTF-8'); ?>" data-api="logviewer_api.php">
            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-exclamation', 'svg-icon svg-lg'); ?>
                        مشاهده ارور‌ها
                    </div>
                    <div class="page-head__sub">بررسی و مدیریت لاگ‌ها و خطاهای برنامه بدون نیاز به ورود به هاست</div>
                </div>
                <div class="lv-head-actions">
                    <a class="btn btn-outline btn-sm" href="miscsettings.php">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-right', 'svg-icon svg-sm'); ?>
                        <span>بازگشت به تنظیمات</span>
                    </a>
                </div>
            </div>

            <div class="lv-stats" id="lvStats" aria-live="polite"></div>

            <div class="lv-layout">
                <aside class="lv-files card" id="lvFilesCard" aria-label="فایل‌های لاگ">
                    <button type="button" class="lv-files-toggle" id="lvFilesToggle" aria-expanded="false" aria-controls="lvFilesBody">
                        <span class="lv-files-toggle__label">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('list', 'svg-icon svg-sm'); ?>
                            <span id="lvFilesCurrent">انتخاب فایل</span>
                        </span>
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-down', 'svg-icon svg-sm lv-chev'); ?>
                    </button>
                    <div class="lv-files-body" id="lvFilesBody">
                        <div class="lv-files-head">
                            <div class="lv-search lv-search--files">
                                <input type="search" id="lvFileFilter" class="lv-input" placeholder="جستجوی نام فایل…" aria-label="جستجوی نام فایل">
                                <button type="button" class="lv-search__clear" id="lvFileFilterClear" aria-label="پاک‌کردن جستجوی فایل" hidden>
                                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="lv-files-list" id="lvFilesList" role="list"></div>
                        <div class="lv-files-foot">
                            <button type="button" class="btn btn-soft-danger btn-sm btn-block" id="lvBulkDelete">
                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm'); ?>
                                <span>حذف همه لاگ‌ها…</span>
                            </button>
                        </div>
                    </div>
                </aside>

                <section class="lv-main" aria-label="رویدادها">
                    <div class="lv-toolbar card">
                        <div class="lv-toolbar__row">
                            <div class="lv-tabs" role="tablist" aria-label="نوع نمایش">
                                <button type="button" class="lv-tab is-active" role="tab" data-view="errors" aria-selected="true">خطاها</button>
                                <button type="button" class="lv-tab" role="tab" data-view="all" aria-selected="false">همه لاگ‌ها</button>
                            </div>
                            <div class="lv-toolbar__actions">
                                <div class="lv-auto">
                                    <span id="lvAutoLabel">به‌روزرسانی خودکار</span>
                                    <button type="button" class="lv-selbtn" id="lvAutoBtn" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="lvAutoLabel lvAutoText">
                                        <span id="lvAutoText">۱۰ ثانیه</span>
                                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-down', 'svg-icon svg-sm lv-chev'); ?>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm" id="lvRefresh">
                                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('refresh', 'svg-icon svg-sm'); ?>
                                    <span>بازخوانی</span>
                                </button>
                                <div class="lv-menu" id="lvExportMenu">
                                    <button type="button" class="btn btn-outline btn-sm" id="lvExportBtn" aria-haspopup="menu" aria-expanded="false" aria-controls="lvExportPop">
                                        <span>خروجی</span>
                                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-down', 'svg-icon svg-sm'); ?>
                                    </button>
                                    <div class="lv-pop lv-pop--menu" id="lvExportPop" role="menu" aria-label="خروجی" hidden>
                                        <button type="button" role="menuitem" data-export="raw">دانلود متن فایل (ماسک‌شده)</button>
                                        <button type="button" role="menuitem" data-export="json">دانلود JSON رویدادهای فیلترشده</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="lv-toolbar__row lv-filters">
                            <div class="lv-search" id="lvSearchBox">
                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('search', 'svg-icon svg-sm'); ?>
                                <input type="search" id="lvQ" class="lv-input" placeholder="جستجو در پیام، مسیر و جزئیات…" aria-label="جستجو در رویدادها" maxlength="200">
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" id="lvClear" disabled>پاک‌کردن فیلترها</button>
                        </div>
                        <div class="lv-toolbar__row lv-levelrow">
                            <div class="lv-levels" id="lvLevels" role="group" aria-label="فیلتر شدت"></div>
                            <span class="lv-levels-note" id="lvLevelsNote"></span>
                        </div>
                        <div class="lv-scope" id="lvScope" aria-live="polite"></div>
                    </div>

                    <button type="button" class="lv-newpill" id="lvNew" hidden></button>
                    <div class="lv-events" id="lvEvents" aria-live="off"></div>
                    <div class="lv-more" id="lvMore"></div>
                </section>
            </div>
        </div>
    </section>
</section>

<dialog id="lvDialog" class="lv-dialog" aria-labelledby="lvDialogTitle" aria-describedby="lvDialogBody">
    <form method="dialog" class="lv-dialog__box" id="lvDialogForm">
        <div class="lv-dialog__head"><h3 id="lvDialogTitle"></h3></div>
        <div class="lv-dialog__body" id="lvDialogBody"></div>
        <div class="lv-dialog__foot">
            <button type="button" class="btn btn-outline" id="lvDialogCancel">انصراف</button>
            <button type="button" class="btn btn-danger" id="lvDialogOk">حذف دائمی</button>
        </div>
    </form>
</dialog>
<div class="lv-toast" id="lvToast" role="status" aria-live="polite" hidden></div>
</body>
</html>
