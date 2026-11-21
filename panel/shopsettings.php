<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'shopsettings.php';

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

if (\Faoxima\Admin\Web\Pages\ShopsettingsRequest::handle(
        SHOP_GROUPS: $SHOP_GROUPS,
        _csrf: $_csrf,
        pdo: $pdo ?? null,
        query: $query,
        r: $r,
        row: $row,
        savedNum: $savedNum,
        shopRows: $shopRows,
        showSaved: $showSaved,
        tableMissing: $tableMissing
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
    <title>قابلیت‌های فروشگاه | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat39">
    <script src="js/theme.js?v=dark62" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-settings">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('package', 'svg-icon svg-lg'); ?>
                        قابلیت‌های فروشگاه
                    </div>
                    <div class="page-head__sub">قابلیت‌های نمایش و خرید فروشگاه — مطابق منوی «🛒 وضعیت قابلیت‌های فروشگاه» در ربات</div>
                </div>
            </div>

            <?php if ($tableMissing): ?>
                <div class="alert alert-warning">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-exclamation', 'svg-icon'); ?>
                    <span>جدول <code>shopSetting</code> در دیتابیس هنوز ساخته نشده. لطفاً یک‌بار از این صفحه خارج شده و دوباره وارد شوید — جدول به‌صورت خودکار ساخته می‌شود.</span>
                </div>
            <?php endif; ?>

            <?php if ($showSaved): ?>
                <div class="alert <?php echo $savedNum > 0 ? 'alert-success' : 'alert-warning'; ?>">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon($savedNum > 0 ? 'circle-check' : 'circle-exclamation', 'svg-icon'); ?>
                    <span>
                        <?php echo $savedNum > 0 ? ($savedNum . ' تغییر ذخیره شد.') : 'هیچ تغییری انجام نشد.'; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!$tableMissing): ?>
                <form method="POST" action="shopsettings.php" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_save" value="1">
                    <div class="setting-grid">
                        <?php foreach ($SHOP_GROUPS as $gKey => $group): ?>
                            <div class="card">
                                <div class="card__head">
                                    <div class="card__title">
                                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon($group['icon'], 'svg-icon svg-md'); ?>
                                        <span><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <?php foreach ($group['fields'] as $f):
                                    $key = $f['name'];
                                    $cur = $shopRows[$key] ?? '';
                                    $idAttr = 'f_' . htmlspecialchars($key, ENT_QUOTES);
                                ?>
                                    <div class="setting-row">
                                        <label for="<?php echo $idAttr; ?>" class="setting-row__label">
                                            <?php echo htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                        <div class="setting-row__control">
                                            <?php if ($f['type'] === 'toggle'): ?>
                                                <label class="switch" title="<?php echo htmlspecialchars($key); ?>">
                                                    <input type="checkbox" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                        <?php echo \Faoxima\Admin\Web\Pages\ShopsettingsRequestHelpers::faoxima_shop_is_on($cur, $f['on'], $f['off']) ? 'checked' : ''; ?>>
                                                    <span class="switch__slot"></span>
                                                </label>
                                            <?php else: ?>
                                                <input type="number" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                    value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES); ?>"
                                                    placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                    style="direction:ltr; text-align:left;">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="save-bar">
                        <button type="reset" class="btn btn-outline">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('rotate-left', 'svg-icon svg-sm'); ?>
                            <span>بازنشانی</span>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon svg-sm'); ?>
                            <span>ذخیره</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </section>
</section>

</body>
</html>


