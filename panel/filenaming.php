<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/../re/rx/function/database_helpers_1.php';
require_once __DIR__ . '/../re/rx/function/file_naming.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
$query->execute([':username' => $_SESSION['user'] ?? '']);
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION['user']) || !$adminRow) {
    header('Location: login.php');
    exit;
}

$fnCsrf = fx_csrf_token();
$fnMaster = rxFileNamingEnabled(true);
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>تنظیمات ارسال فایل توسط ربات | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat35">
    <link rel="stylesheet" href="css/filenaming.css?v=fn6">
    <script src="js/theme.js?v=flat50" defer></script>
    <script src="js/filenaming.js?v=fn6" defer></script>
</head>
<body>
<section id="container">
    <?php include 'header.php'; ?>
    <section id="main-content">
        <div class="wrapper fn-page" id="fnPage" data-api="filenaming_api.php" data-csrf="<?php echo htmlspecialchars($fnCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('package', 'svg-icon svg-lg'); ?>
                        تنظیمات ارسال فایل توسط ربات
                    </div>
                    <div class="page-head__sub">نام فایل و کپشن سفارشی برای کانفیگ‌هایی که ربات برای کاربران ارسال می‌کند</div>
                </div>
                <div class="fn-head-actions">
                    <a class="btn btn-outline btn-sm" href="miscsettings.php">
                        <?php echo icon('chevron-right', 'svg-icon svg-sm'); ?>
                        <span>بازگشت به تنظیمات</span>
                    </a>
                </div>
            </div>

            <div class="card fn-master-card">
                <div class="fn-master">
                    <div class="fn-master__text">
                        <div class="fn-master__title">
                            <?php echo icon('sparkles', 'svg-icon svg-md'); ?>
                            <span>فعال‌سازی نام و کپشن سفارشی</span>
                        </div>
                        <div class="fn-master__hint">وقتی این کلید خاموش است، فایل‌ها دقیقاً با همان نام و کپشن قبلی ارسال می‌شوند.</div>
                    </div>
                    <label class="switch fn-master__switch">
                        <input type="checkbox" role="switch" id="fnMaster" aria-label="فعال‌سازی نام و کپشن سفارشی" <?php echo $fnMaster ? 'checked' : ''; ?>>
                        <span class="switch__slot"></span>
                    </label>
                </div>
            </div>

            <div class="card fn-rules-card">
                <div class="card__head">
                    <div class="card__title">
                        <?php echo icon('list', 'svg-icon svg-md'); ?>
                        <span>قوانین نام و کپشن</span>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="fnAdd">
                        <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن تنظیم</span>
                    </button>
                </div>
                <div class="fn-state" id="fnRulesState" role="status" aria-live="polite"></div>
                <div class="fn-rules" id="fnRules"></div>
                <div class="mdt-foot fn-rules-foot" id="fnRulesFoot" hidden>
                    <div class="mdt-info" id="fnRulesInfo" role="status" aria-live="polite"></div>
                    <nav class="mdt-pager" id="fnRulesPager" aria-label="صفحه‌بندی قوانین نام و کپشن"></nav>
                </div>
            </div>

            <div class="fn-modal" id="fnModal" hidden>
                <div class="fn-modal__backdrop" data-fn-close="1"></div>
                <div class="fn-modal__sheet" role="dialog" aria-modal="true" aria-labelledby="fnModalTitle">
                    <div class="fn-modal__head">
                        <div class="fn-modal__title" id="fnModalTitle">افزودن تنظیم</div>
                        <button type="button" class="btn-icon fn-modal__close" data-fn-close="1" aria-label="بستن">
                            <?php echo icon('xmark', 'svg-icon svg-md'); ?>
                        </button>
                    </div>
                    <form class="fn-form" id="fnForm" autocomplete="off">
                        <input type="hidden" name="id" id="fnId" value="0">
                        <input type="hidden" name="source" id="fnSource" value="">
                        <input type="hidden" name="file_ext" id="fnExt" value="conf">
                        <input type="hidden" name="match_key" id="fnKey" value="">
                        <input type="hidden" name="match_label" id="fnLabel" value="">
                        <input type="hidden" name="match_basis" id="fnBasis" value="">

                        <div class="fn-step">
                            <div class="fn-step__title"><span class="fn-step__num">۱</span> پنل</div>
                            <div class="fn-field">
                                <span class="fn-field__label" id="fnPanelLabel">پنل موردنظر</span>
                                <select class="form-control" id="fnPanel" name="panel_ref" required>
                                    <option value="">در حال بارگذاری پنل‌ها…</option>
                                </select>
                            </div>
                        </div>

                        <div class="fn-step" id="fnServiceStep" hidden>
                            <div class="fn-step__title"><span class="fn-step__num">۲</span> سرویس منبع</div>
                            <div class="fn-field">
                                <span class="fn-field__label" id="fnServiceLabel">انتخاب سرویس کاربر</span>
                                <span class="fn-field__hint" id="fnServiceHint">آیدی عددی تلگرام کاربر را وارد کنید تا فقط سرویس‌های همان کاربر در پنل انتخاب‌شده جستجو شوند. این انتخاب فقط برای شناسایی کانفیگ‌هاست.</span>
                                <div class="fn-customer-lookup" id="fnCustomerLookup">
                                    <label class="fn-field" for="fnCustomerId">
                                        <span class="fn-field__label">آیدی عددی کاربر</span>
                                        <input class="form-control" id="fnCustomerId" type="text" inputmode="numeric" maxlength="20" dir="ltr" placeholder="مثلاً 123456789" autocomplete="off">
                                    </label>
                                    <button class="btn btn-primary btn-sm" type="button" id="fnFindServices">جستجوی سرویس‌ها</button>
                                </div>
                                <div class="fn-service-status" id="fnServiceStatus" role="status" aria-live="polite"></div>
                                <div class="fn-service-row" id="fnServiceRow">
                                    <select class="form-control" id="fnService" name="source_service">
                                        <option value="">ابتدا یک سرویس انتخاب کنید…</option>
                                    </select>
                                    <button type="button" class="btn btn-outline btn-sm" id="fnLoadTargets">
                                        <?php echo icon('refresh', 'svg-icon svg-sm'); ?>
                                        <span>بارگذاری کانفیگ‌ها</span>
                                    </button>
                                </div>
                                <div class="fn-service-pager" id="fnServicePager" hidden>
                                    <button class="btn btn-outline btn-sm" type="button" id="fnServicesPrev">صفحه قبل</button>
                                    <span id="fnServicesPage"></span>
                                    <button class="btn btn-outline btn-sm" type="button" id="fnServicesNext">صفحه بعد</button>
                                </div>
                                <div class="fn-hintbox" id="fnServiceNote" hidden>برای این پنل نیازی به انتخاب سرویس نیست؛ فایل‌ها مستقیماً از خود پنل خوانده می‌شوند.</div>
                            </div>
                        </div>

                        <div class="fn-step">
                            <div class="fn-step__title"><span class="fn-step__num">۳</span> کانفیگ هدف</div>
                            <div class="fn-field" id="fnTargetFilterWrap" hidden>
                                <input type="search" class="form-control" id="fnTargetFilter" placeholder="جستجوی کانفیگ…" aria-label="جستجوی کانفیگ">
                            </div>
                            <div class="fn-targets" id="fnTargets" role="radiogroup" aria-label="کانفیگ هدف">
                                <div class="fn-hintbox" id="fnTargetsHint">برای مشاهده کانفیگ‌ها، پنل و سرویس منبع را انتخاب کنید.</div>
                            </div>
                        </div>

                        <div class="fn-step">
                            <div class="fn-step__title"><span class="fn-step__num">۴</span> نام و کپشن</div>
                            <label class="fn-field">
                                <span class="fn-field__label">نام فایل سفارشی</span>
                                <span class="fn-field__hint" id="fnNameHint">نام اصلی فایل به‌طور کامل با این نام جایگزین می‌شود.</span>
                                <input type="text" class="form-control" id="fnFilename" name="custom_filename" maxlength="180" placeholder="Germany.conf" required>
                            </label>
                            <label class="fn-field">
                                <span class="fn-field__label">کپشن سفارشی</span>
                                <span class="fn-field__hint">این متن به کپشن فعلی ربات اضافه می‌شود و به‌صورت متن ساده و ایمن ارسال می‌گردد؛ کاراکترهای ویژه باعث خطا نمی‌شوند.</span>
                                <textarea class="form-control" id="fnCaption" name="custom_caption" rows="3" maxlength="1024" placeholder="🇩🇪 کانفیگ وایرگارد آلمان"></textarea>
                            </label>
                        </div>

                        <div class="fn-step fn-preview">
                            <div class="fn-step__title"><span class="fn-step__num">۵</span> پیش‌نمایش</div>
                            <div class="fn-preview__row">
                                <span class="fn-preview__key">نام نهایی فایل</span>
                                <code class="fn-preview__value" id="fnPreviewName">—</code>
                            </div>
                            <div class="fn-preview__row">
                                <span class="fn-preview__key">کپشن سفارشی</span>
                                <div class="fn-preview__caption" id="fnPreviewCaption">—</div>
                            </div>
                            <div class="fn-preview__note" id="fnPreviewNote" hidden></div>
                            <div class="fn-hintbox">کپشن سفارشی با حفظ اطلاعات اصلی ارسال اضافه می‌شود و در صورت کمبود فضا کوتاه خواهد شد.</div>
                        </div>

                        <label class="fn-check">
                            <input type="checkbox" id="fnEnabled" name="is_enabled" checked>
                            <span>این تنظیم فعال باشد</span>
                        </label>

                        <div class="fn-form__error" id="fnFormError" role="alert" hidden></div>

                        <div class="fn-form__actions">
                            <button type="button" class="btn btn-outline" data-fn-close="1">انصراف</button>
                            <button type="submit" class="btn btn-primary" id="fnSave">
                                <?php echo icon('check', 'svg-icon svg-sm'); ?>
                                <span>ذخیره تنظیم</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fn-toast" id="fnToast" role="status" aria-live="polite" hidden></div>
        </div>
    </section>
</section>
</body>
</html>
