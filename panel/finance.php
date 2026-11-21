<?php

require_once __DIR__ . '/../src/bootstrap.php';

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/csrf.php';

\Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo);
\Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();

[$FIN_GROUPS, $paySettings, $textbotRows] = \Faoxima\Admin\Web\Pages\FinancePage::load($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
    \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo, ['administrator'], $adminnumber ?? null);
    header('Location: ' . \Faoxima\Admin\Web\Pages\FinancePage::save($pdo, $_POST, $paySettings, $textbotRows));
    exit;
}
$showEdgeRejected = isset($_GET['edge_rejected']) && (int) $_GET['edge_rejected'] > 0;
$showSaved = isset($_GET['saved']);
$savedNum = (int) ($_GET['saved'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تنظیمات مالی | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat39">
    <script src="js/money-input.js?v=fx1" defer></script>
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
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('money-bill', 'svg-icon svg-lg'); ?>
                        تنظیمات مالی
                    </div>
                    <div class="page-head__sub">شماره کارت، والت ترون، درگاه‌های پرداخت، کش‌بک و حدود مبلغ</div>
                </div>
            </div>

            <?php if ($showSaved): ?>
                <div class="alert <?php echo $savedNum > 0 ? 'alert-success' : 'alert-warning'; ?>">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon($savedNum > 0 ? 'circle-check' : 'circle-exclamation', 'svg-icon'); ?>
                    <span>
                        <?php echo $savedNum > 0 ? ($savedNum . ' فیلد ذخیره شد.') : 'هیچ تغییری انجام نشد.'; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($showEdgeRejected): ?>
                <div class="alert alert-danger">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-exclamation', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars(\Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_edge_error_message(), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="finance.php" autocomplete="off">
                <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                <input type="hidden" name="_save" value="1">

                <div class="setting-grid">
                    <?php foreach ($FIN_GROUPS as $gKey => $group): ?>
                        <div class="card">
                            <div class="card__head">
                                <div class="card__title">
                                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon($group['icon'], 'svg-icon svg-md'); ?>
                                    <span><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <?php foreach ($group['fields'] as $f):
                                $key = $f['name'];

                                if (($f['type'] ?? '') === 'textbot') {
                                    $cur = $textbotRows[$key] ?? '';
                                } else {
                                    $cur = $paySettings[$key] ?? '';
                                }
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
                                                    <?php echo \Faoxima\Admin\Web\Pages\FinanceRequestHelpers::faoxima_fin_is_on($cur, $f['on'], $f['off']) ? 'checked' : ''; ?>>
                                                <span class="switch__slot"></span>
                                            </label>
                                        <?php elseif ($f['type'] === 'number' && !empty($f['money'])): ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                data-money
                                                style="direction:ltr; text-align:left;">
                                        <?php elseif ($f['type'] === 'number'): ?>
                                            <input type="number" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                style="direction:ltr; text-align:left;">
                                        <?php elseif ($f['type'] === 'secret'): ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value=""
                                                placeholder="<?php echo htmlspecialchars(\Faoxima\Admin\Web\Pages\FinanceRequestHelpers::faoxima_fin_mask_secret($cur) ?: ($f['placeholder'] ?? ''), ENT_QUOTES); ?>"
                                                style="direction:ltr; text-align:left; min-width: 180px;"
                                                autocomplete="off">
                                        <?php elseif ($f['type'] === 'textbot'): ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES, 'UTF-8'); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                style="min-width: 220px;"
                                                title="این مقدار به جدول textbot نوشته می‌شود (برای نمایش به کاربر)">
                                        <?php else: ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                style="direction:ltr; text-align:left; min-width: 180px;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-info">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-info', 'svg-icon'); ?>
                    <span>فیلدهای مرچنت/کلید API به‌صورت <code>••••XXXX</code> نمایش داده می‌شوند. برای تغییر، مقدار جدید را تایپ کنید — اگر خالی بماند، تغییری اعمال نمی‌شود.</span>
                </div>

                <div class="save-bar">
                    <button type="reset" class="btn btn-outline">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('rotate-left', 'svg-icon svg-sm'); ?>
                        <span>بازنشانی</span>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon svg-sm'); ?>
                        <span>ذخیره تغییرات</span>
                    </button>
                </div>
            </form>


        </div>
    </section>
</section>

</body>
</html>


