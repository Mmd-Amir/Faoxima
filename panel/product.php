<?php

require_once __DIR__ . '/../src/bootstrap.php';

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/ordering.php';
require_once __DIR__ . '/lib/bulk_price.php';
require_once __DIR__ . '/../function.php';

\Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo);

if (!(isset($_GET['ajax']) && $_GET['ajax'] === 'fail2ban_check')) {
    \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
}
$_csrf = \Faoxima\Admin\Web\Support\Csrf::fx_csrf_token();

if (($_GET['ajax'] ?? null) === 'fail2ban_check') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(\Faoxima\Admin\Web\Pages\ProductPage::fail2ban($pdo, trim((string) ($_GET['panel'] ?? ''))));
    exit;
}

if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo, ['administrator'], $adminnumber ?? null);
    $requestedIds = $_POST['ids'] ?? [];
    $deletedCount = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_ids($pdo, 'product', 'id', $requestedIds);
    \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_redirect('product.php', count($requestedIds), $deletedCount);
}

if (($_POST['_action'] ?? '') === 'product_delete') {
    \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo, ['administrator'], $adminnumber ?? null);
    \Faoxima\Admin\Web\Pages\ProductPage::delete($pdo, (int) ($_POST['id'] ?? 0));
    header('Location: product.php');
    exit;
}

[$prodQ, $pg, $listinvoice, $listpanel, $panelTypeMap, $listcategory] = \Faoxima\Admin\Web\Pages\ProductPage::catalog($pdo, $_GET);
$nameProduct = $_POST['nameproduct'] ?? null;
if (!empty($nameProduct)) {
    $error = \Faoxima\Admin\Web\Pages\ProductPage::create($pdo, $_POST, $nameProduct);
    if ($error !== null) {
        echo $error;
        return;
    }
    header('Location: product.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت محصولات | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=left94">
    <link rel="stylesheet" href="css/order_modal.css?v=ord1">
    <link rel="stylesheet" href="css/bulk_price.css?v=bp5">
<script src="js/money-input.js?v=fx1" defer></script>
<script src="js/theme.js?v=dark62" defer>

</script>
</head>
<body class="fx-products">

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list fx-page-products">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <svg class="svg-icon svg-lg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M11.0287 2.53961C11.6327 2.20402 12.3672 2.20402 12.9713 2.5396L20.4856 6.71425C20.8031 6.89062 21 7.22524 21 7.5884V15.8232C21 16.5495 20.6062 17.2188 19.9713 17.5715L12.9713 21.4604C12.3672 21.796 11.6327 21.796 11.0287 21.4604L4.02871 17.5715C3.39378 17.2188 3 16.5495 3 15.8232V7.5884C3 7.22524 3.19689 6.89062 3.51436 6.71425L11.0287 2.53961Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.5 4.5L16.5 9.5V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 12.3281L9 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 7L12 12M12 12L21 7M12 12V21.5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                        لیست محصولات
                    </div>
                    <div class="page-head__sub">مدیریت محصولات و پنل‌های مرزبان</div>
                </div>
                <div class="chip-row">
                    <button onclick="openModal('modal-add-product')" class="btn btn-primary btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> افزودن محصول
                    </button>
                    <button onclick="openModal('modal-move-product')" class="btn btn-soft-purple btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right-arrow-left', 'svg-icon'); ?> جابجایی ردیف
                    </button>
                    <button onclick="openModal('modal-move-panel')" class="btn btn-soft-purple btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right-arrow-left', 'svg-icon'); ?> جابجایی پنل‌ها
                    </button>
                    <button type="button" data-bp-open="modal-bulk-price" class="btn btn-soft-purple btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('coins', 'svg-icon'); ?> قیمت گروهی
                    </button>
                </div>
            </div>

            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_flash_html(); ?>

            <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('product.php', $prodQ, [], 'جستجو در شناسه، نام محصول، لوکیشن یا دسته‌بندی…'); ?>

            <div class="card product-catalog">
                <div class="product-catalog__head"><div><h2>کاتالوگ محصولات</h2><p>قیمت، مشخصات سرویس و پنل‌های ارائه‌دهنده</p></div><span class="product-count"><?php echo strtr(number_format((int)$pg['total']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> محصول<?php echo $prodQ !== '' ? ' مطابق جستجو' : ''; ?></span></div>
                <form method="POST" action="product.php" id="bulk-form">
                    <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <div class="table-wrap">
                    <table id="productsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all" onclick="faoximaToggleAll(this)"></th>
                                <th>شناسه</th>
                                <th>نام محصول</th>
                                <th>قیمت</th>
                                <th>حجم (GB)</th>
                                <th>زمان (روز)</th>
                                <th>لوکیشن</th>
                                <th>گروه کاربری</th>
                                <th>دسته‌بندی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listinvoice as $list):
                            $categoryItems = array_values(array_filter(array_map('trim', explode(',', (string)($list['category'] ?? '')))));
                            $locationItems = array_values(array_filter(array_map('trim', explode(',', (string)($list['Location'] ?? '')))));
                            $agent_type = 'عادی'; $agent_badge = 'badge-gray';
                            if ($list['agent'] == 'n')  { $agent_type = 'نماینده';      $agent_badge = 'badge-purple';  }
                            if ($list['agent'] == 'n2') { $agent_type = 'نماینده ویژه';  $agent_badge = 'badge-warning'; }
                        ?>
                            <tr data-detail-row data-detail-title="<?php echo htmlspecialchars($list['name_product'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo $list['id']; ?>"></label></td>
                                <td data-label="شناسه" data-summary="1"><bdi class="product-id">#<?php echo (int)$list['id']; ?></bdi></td>
                                <td data-label="نام محصول" data-summary="1"><span class="product-name"><?php echo htmlspecialchars($list['name_product'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="قیمت" data-summary="1"><span class="product-price"><bdi><?php echo number_format($list['price_product']); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                <td data-label="حجم (GB)"><span class="product-spec"><?php echo ((int)$list['Volume_constraint'] === 0) ? 'نامحدود' : (int)$list['Volume_constraint'] . ' GB'; ?></span></td>
                                <td data-label="زمان (روز)"><span class="product-spec"><?php echo (int)$list['Service_time']; ?> روز</span></td>
                                <td data-label="لوکیشن">
                                    <?php
                                        $locationBadgesHtml = array_map(function ($locItem) {
                                            return '<span class="badge product-location-chip">' . htmlspecialchars($locItem === '/all' ? 'تمامی پنل‌ها' : $locItem, ENT_QUOTES, 'UTF-8') . '</span>';
                                        }, $locationItems);
                                        echo \Faoxima\Admin\Web\Support\CompactBadges::faoxima_render_compact_badges($locationBadgesHtml);
                                    ?>
                                </td>
                                <td data-label="گروه کاربری"><span class="badge <?php echo $agent_badge; ?>"><?php echo $agent_type; ?></span></td>
                                <td data-label="دسته‌بندی">
                                    <?php
                                        $categoryBadgesHtml = array_map(function ($catItem) {
                                            return '<span class="badge badge-gray">' . htmlspecialchars($catItem, ENT_QUOTES, 'UTF-8') . '</span>';
                                        }, $categoryItems);
                                        echo \Faoxima\Admin\Web\Support\CompactBadges::faoxima_render_compact_badges($categoryBadgesHtml);
                                    ?>
                                </td>
                                <td data-label="عملیات" class="cell-actions">
                                    <div class="product-actions">
                                        <a href="productedit.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-soft-info" title="ویرایش">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen', 'svg-icon'); ?>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-soft-purple" title="کلون"
                                            onclick="faoximaOpenClone(<?php echo htmlspecialchars(json_encode([
                                                'name'     => $list['name_product'],
                                                'price'    => $list['price_product'],
                                                'volume'   => $list['Volume_constraint'],
                                                'time'     => $list['Service_time'],
                                                'location' => $list['Location'],
                                                'agent'    => $list['agent'],
                                                'category' => $list['category'] ?? '',
                                                'note'     => $list['note'] ?? '',
                                                'ip_limit' => $list['ip_limit'] ?? '0',
                                                'hwid_limit' => $list['hwid_limit'] ?? '0',
                                                'sym_en'   => $list['symbolic_limit_enabled'] ?? '0',
                                                'sym_us'   => $list['symbolic_limit_users'] ?? '0',
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('copy', 'svg-icon'); ?>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-soft-danger" title="حذف"
                                           onclick="faoximaDeleteProduct(<?php echo (int)$list['id']; ?>)">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html($pg['page'], $pg['pages'], $pg['total'], count($listinvoice), 'product.php', ['q' => $prodQ !== '' ? $prodQ : null]); ?>
                    </div>
                    <div style="padding:12px 0;">
                        <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف محصولات انتخاب‌شده مطمئن هستید؟')">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>
</section>


<div id="modal-add-product" class="modal-overlay">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن محصول جدید</span>
            <button class="modal-close" onclick="closeModal('modal-add-product')">&times;</button>
        </div>
        <form action="product.php" method="POST">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">نام محصول</label>
                <input type="text" name="nameproduct" class="form-control" placeholder="نام محصول را وارد کنید" required>
            </div>

            <div class="form-group">
                <label class="form-label">پنل (موقعیت) <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="add_product_panel_picker" data-panel-picker>
                    <button type="button" class="chip is-active" data-panel-chip data-value="/all"><span>تمامی پنل‌ها</span></button>
                    <?php foreach ($listpanel as $panel): ?>
                        <button type="button" class="chip" data-panel-chip data-value="<?php echo htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="namepanel_csv" id="add_product_panel_hidden" data-panel-hidden value="/all">
            </div>

            <div class="form-group">
                <label class="form-label">محدودیت IP</label>
                <input type="number" name="ip_limit_product" id="add_product_ip_limit" class="form-control" min="0" max="50" step="1" value="0">
                <small style="color:var(--text-muted)" id="add_product_ip_limit_hint">مقدار ۰ به معنی بدون محدودیت است. این مقدار فقط برای پنل‌های ثنایی (3x-ui) با حالت توکنی یا Rebecca اعمال می‌شود.</small>
            </div>

            <div class="form-group">
                <label class="form-label">محدودیت HWID (تعداد دستگاه)</label>
                <input type="number" name="hwid_limit_product" id="add_product_hwid_limit" class="form-control" min="0" max="50" step="1" value="0">
                <small style="color:var(--text-muted)" id="add_product_hwid_limit_hint">تعداد دستگاه‌های مجاز برای اتصال از طریق سابسکریپشن (مخصوص پنل‌های PasarGuard، Remnawave و ثنایی (3x-ui)). مقدار ۰ به معنی بدون محدودیت است.</small>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="symbolic_limit_enabled" id="add_product_symbolic_limit_enabled" value="1" onchange="document.getElementById('add_product_symbolic_limit_users').disabled = !this.checked;">
                    Limiet (محدودیت نمایشی)
                </label>
                <input type="number" name="symbolic_limit_users" id="add_product_symbolic_limit_users" class="form-control" min="1" max="50" step="1" value="0" disabled>
                <small style="color:var(--text-muted)">این گزینه کاملاً نمایشی و مستقل از محدودیت واقعی IP (fail2ban) است و برای هر نوع پنل قابل استفاده است. در صورت فعال بودن، فقط تعداد کاربر تعیین‌شده به کاربر نمایش داده می‌شود و هیچ اطلاعات IP/دستگاه متصل نشان داده نمی‌شود.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">قیمت (تومان)</label>
                    <input type="text" name="price_product" class="form-control" data-money required>
                </div>
                <div class="form-group">
                    <label class="form-label">حجم (GB)</label>
                    <input type="number" name="volume_product" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">زمان (روز)</label>
                    <input type="number" name="time_product" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع کاربر</label>
                    <select name="agent_product" class="form-control" required>
                        <option value="f">کاربر عادی</option>
                        <option value="n">نماینده</option>
                        <option value="n2">نماینده پیشرفته</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">دسته‌بندی <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="add_product_category_picker" data-category-picker>
                    <?php foreach ($listcategory as $catName): ?>
                        <button type="button" class="chip" data-category-chip data-value="<?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?></span></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="cetegory_product_csv" data-category-hidden value="">
                <input type="text" name="cetegory_product_new" class="form-control" style="margin-top:8px;" placeholder="دسته‌بندی جدید (اختیاری)">
            </div>

            <div class="form-group">
                <label class="form-label">توضیحات (اختیاری)</label>
                <input type="text" name="note_product" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary btn-block">افزودن محصول</button>
        </form>
    </div>
</div>


<?php echo \Faoxima\Admin\Web\Support\Ordering::fx_order_modal_html('product', 'modal-move-product'); ?>
<?php echo \Faoxima\Admin\Web\Support\Ordering::fx_order_modal_html('panel', 'modal-move-panel'); ?>
<?php echo \Faoxima\Admin\Web\Support\BulkPrice::fx_bp_modal_html('modal-bulk-price'); ?>


<div id="modal-clone-product" class="modal-overlay">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-head">
            <span class="modal-head__title">کلون محصول</span>
            <button class="modal-close" onclick="closeModal('modal-clone-product')">&times;</button>
        </div>
        <form action="product.php" method="POST">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">نام محصول</label>
                <input type="text" name="nameproduct" id="clone_name" class="form-control" placeholder="نام محصول را وارد کنید" required>
            </div>

            <div class="form-group">
                <label class="form-label">پنل (موقعیت) <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="clone_product_panel_picker" data-panel-picker>
                    <button type="button" class="chip is-active" data-panel-chip data-value="/all"><span>تمامی پنل‌ها</span></button>
                    <?php foreach ($listpanel as $panel): ?>
                        <button type="button" class="chip" data-panel-chip data-value="<?php echo htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="namepanel_csv" id="clone_product_panel_hidden" data-panel-hidden value="/all">
            </div>

            <div class="form-group">
                <label class="form-label">محدودیت IP</label>
                <input type="number" name="ip_limit_product" id="clone_product_ip_limit" class="form-control" min="0" max="50" step="1" value="0">
                <small style="color:var(--text-muted)" id="clone_product_ip_limit_hint">مقدار ۰ به معنی بدون محدودیت است. این مقدار فقط برای پنل‌های ثنایی (3x-ui) با حالت توکنی یا Rebecca اعمال می‌شود.</small>
            </div>

            <div class="form-group">
                <label class="form-label">محدودیت HWID (تعداد دستگاه)</label>
                <input type="number" name="hwid_limit_product" id="clone_product_hwid_limit" class="form-control" min="0" max="50" step="1" value="0">
                <small style="color:var(--text-muted)" id="clone_product_hwid_limit_hint">تعداد دستگاه‌های مجاز برای اتصال از طریق سابسکریپشن (مخصوص پنل‌های PasarGuard، Remnawave و ثنایی (3x-ui)). مقدار ۰ به معنی بدون محدودیت است.</small>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="symbolic_limit_enabled" id="clone_product_symbolic_limit_enabled" value="1" onchange="document.getElementById('clone_product_symbolic_limit_users').disabled = !this.checked;">
                    Limiet (محدودیت نمایشی)
                </label>
                <input type="number" name="symbolic_limit_users" id="clone_product_symbolic_limit_users" class="form-control" min="1" max="50" step="1" value="0" disabled>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">قیمت (تومان)</label>
                    <input type="text" name="price_product" id="clone_price" class="form-control" data-money required>
                </div>
                <div class="form-group">
                    <label class="form-label">حجم (GB)</label>
                    <input type="number" name="volume_product" id="clone_volume" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">زمان (روز)</label>
                    <input type="number" name="time_product" id="clone_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع کاربر</label>
                    <select name="agent_product" id="clone_agent" class="form-control" required>
                        <option value="f">کاربر عادی</option>
                        <option value="n">نماینده</option>
                        <option value="n2">نماینده پیشرفته</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">دسته‌بندی <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="clone_product_category_picker" data-category-picker>
                    <?php foreach ($listcategory as $catName): ?>
                        <button type="button" class="chip" data-category-chip data-value="<?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?></span></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="cetegory_product_csv" id="clone_category_hidden" data-category-hidden value="">
                <input type="text" name="cetegory_product_new" id="clone_category_custom" class="form-control" style="margin-top:8px;" placeholder="دسته‌بندی جدید (اختیاری)">
            </div>

            <div class="form-group">
                <label class="form-label">توضیحات (اختیاری)</label>
                <input type="text" name="note_product" id="clone_note" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary btn-block">ایجاد کلون</button>
        </form>
    </div>
</div>
<form id="product-delete-form" method="POST" action="product.php" style="display:none;">
    <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
    <input type="hidden" name="_action" value="product_delete">
    <input type="hidden" name="id" value="">
</form>
<script>
function faoximaDeleteProduct(id) {
    if (!confirm('آیا از حذف این محصول مطمئن هستید؟')) return false;
    var form = document.getElementById('product-delete-form');
    if (!form) return false;
    form.querySelector('input[name="id"]').value = String(id);
    form.submit();
    return false;
}
</script>
<script src="js/bulk-select.js?v=fx2"></script>
<script src="js/compact-badges.js?v=fx1"></script>
<script src="js/order_modal.js?v=ord1"></script>
<script src="js/bulk_price.js?v=bp4"></script>
<script>
  var faoximaPanelTypeMap = <?php echo json_encode($panelTypeMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
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
  function faoximaPanelPickerSync(picker, clickedChip) {
    var allChip = picker.querySelector('.chip[data-panel-chip][data-value="/all"]');
    if (allChip) {
      if (clickedChip === allChip) {
        if (allChip.classList.contains('is-active')) {
          picker.querySelectorAll('.chip[data-panel-chip]').forEach(function (c) { if (c !== allChip) c.classList.remove('is-active'); });
        }
      } else if (clickedChip && clickedChip.classList.contains('is-active')) {
        allChip.classList.remove('is-active');
      }
      var anyActive = Array.from(picker.querySelectorAll('.chip[data-panel-chip]')).some(function (c) { return c.classList.contains('is-active'); });
      if (!anyActive) allChip.classList.add('is-active');
    }
    faoximaChipPickerSync(picker, 'data-panel-hidden', 'data-panel-chip');
    faoximaUpdateIpLimitHintForPicker(picker);
    faoximaUpdateHwidLimitHintForPicker(picker);
  }
  function faoximaPanelPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-panel-chip', 'data-panel-hidden');
    faoximaUpdateIpLimitHintForPicker(picker);
    faoximaUpdateHwidLimitHintForPicker(picker);
  }
  function faoximaBindChipPicker(pickerRootSelector, chipSelector, syncFn) {
    document.querySelectorAll(pickerRootSelector).forEach(function (picker) {
      picker.addEventListener('click', function (ev) {
        var chip = ev.target.closest(chipSelector);
        if (!chip || !picker.contains(chip)) return;
        chip.classList.toggle('is-active');
        syncFn(picker, chip);
      });
    });
  }
  faoximaBindChipPicker('[data-category-picker]', '.chip[data-category-chip]', faoximaCategoryPickerSync);
  faoximaBindChipPicker('[data-panel-picker]', '.chip[data-panel-chip]', faoximaPanelPickerSync);

  function faoximaOpenClone(p) {
    document.getElementById('clone_name').value = p.name;
    document.getElementById('clone_price').value = window.FaoximaMoneyInput ? window.FaoximaMoneyInput.format(p.price) : p.price;
    document.getElementById('clone_volume').value = p.volume;
    document.getElementById('clone_time').value = p.time;
    document.getElementById('clone_note').value = p.note;
    var ipInput = document.getElementById('clone_product_ip_limit');
    if (ipInput) ipInput.value = p.ip_limit || '0';
    var hwidInput = document.getElementById('clone_product_hwid_limit');
    if (hwidInput) hwidInput.value = p.hwid_limit || '0';
    var symEn = document.getElementById('clone_product_symbolic_limit_enabled');
    var symUs = document.getElementById('clone_product_symbolic_limit_users');
    if (symEn) { symEn.checked = p.sym_en === '1'; }
    if (symUs) { symUs.value = p.sym_us || '0'; symUs.disabled = p.sym_en !== '1'; }
    var locPicker = document.getElementById('clone_product_panel_picker');
    if (locPicker) {
      var locValues = String(p.location || '').split(',').map(function (v) { return v.trim(); }).filter(function (v) { return v !== ''; });
      faoximaPanelPickerSetSelected(locPicker, locValues);
    }
    var agentSel = document.getElementById('clone_agent');
    if (agentSel) {
      for (var j = 0; j < agentSel.options.length; j++) {
        if (agentSel.options[j].value === p.agent) { agentSel.selectedIndex = j; break; }
      }
    }
    var catPicker = document.getElementById('clone_product_category_picker');
    var catInput = document.getElementById('clone_category_custom');
    if (catPicker) {
      var catValues = String(p.category || '').split(',').map(function (v) { return v.trim(); }).filter(function (v) { return v !== ''; });
      var knownValues = Array.from(catPicker.querySelectorAll('.chip[data-category-chip]')).map(function (c) { return c.getAttribute('data-value'); });
      faoximaCategoryPickerSetSelected(catPicker, catValues);
      if (catInput) {
        var unknownValues = catValues.filter(function (v) { return knownValues.indexOf(v) === -1; });
        catInput.value = unknownValues.join(', ');
      }
    }
    openModal('modal-clone-product');
  }
  function faoximaUpdateIpLimitHintForPicker(picker) {
    var prefix = picker.id.replace(/_panel_picker$/, '');
    var hint = document.getElementById(prefix + '_ip_limit_hint');
    var hidden = picker.parentElement.querySelector('[data-panel-hidden]');
    if (!hint || !hidden) return;
    var values = hidden.value.split(',').filter(function (v) { return v !== ''; });
    var value = values[0] || '/all';
    if (value === '/all') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این مقدار فقط هنگام فروش از یک پنل ثنایی (3x-ui) با حالت توکنی یا Rebecca اعمال می‌شود.';
      return;
    }
    var type = faoximaPanelTypeMap[value];
    if (type === 'x-ui_single') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع ثنایی است؛ در حالت توکنی این مقدار اعمال می‌شود.';
      fetch('product.php?ajax=fail2ban_check&panel=' + encodeURIComponent(value), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok && !j.usable) {
            hint.textContent += ' ⚠️ محدودیت IP روی این پنل ذخیره می‌شود، اما Fail2ban فعال یا قابل استفاده نیست؛ ممکن است محدودیت عملاً اجرا نشود.';
          }
        })
        .catch(function () {});
    } else if (type === 'rebecca') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع Rebecca است؛ این مقدار مستقیماً به پنل ارسال می‌شود.';
    } else {
      hint.textContent = 'این پنل از نوع ثنایی (3x-ui) یا Rebecca نیست — مقدار محدودیت IP برای آن اعمال نخواهد شد.';
    }
  }
  function faoximaUpdateHwidLimitHintForPicker(picker) {
    var prefix = picker.id.replace(/_panel_picker$/, '');
    var hint = document.getElementById(prefix + '_hwid_limit_hint');
    var hidden = picker.parentElement.querySelector('[data-panel-hidden]');
    if (!hint || !hidden) return;
    var values = hidden.value.split(',').filter(function (v) { return v !== ''; });
    var value = values[0] || '/all';
    if (value === '/all') {
      hint.textContent = 'تعداد دستگاه‌های مجاز برای اتصال از طریق سابسکریپشن (مخصوص پنل‌های PasarGuard، Remnawave و ثنایی (3x-ui)). مقدار ۰ به معنی بدون محدودیت است.';
      return;
    }
    var type = faoximaPanelTypeMap[value];
    if (type === 'pasarguard') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع PasarGuard است؛ این مقدار هنگام ساخت کاربر به پنل ارسال می‌شود.';
    } else if (type === 'remnawave') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع Remnawave است؛ این مقدار هنگام ساخت کاربر به پنل ارسال می‌شود.';
    } else if (type === 'x-ui_single') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع ثنایی (3x-ui) است؛ در حالت توکنی این مقدار هنگام ساخت کاربر به پنل ارسال می‌شود.';
    } else {
      hint.textContent = 'این پنل از نوع PasarGuard، Remnawave یا ثنایی (3x-ui) نیست — مقدار محدودیت HWID برای آن اعمال نخواهد شد.';
    }
  }
</script>
</body>
</html>
