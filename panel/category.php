<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'category.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/ordering.php';
require_once __DIR__ . '/../function.php';

if (\Faoxima\Admin\Web\Pages\CategoryRequest::handle(
        catQ: $catQ,
        categories: $categories,
        pdo: $pdo ?? null,
        pg: $pg,
        query: $query,
        result: $result,
        stmt: $stmt,
        v: $v ?? null
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
    <title>مدیریت دسته‌بندی‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=left94">
    <link rel="stylesheet" href="css/order_modal.css?v=ord1">
    <script src="js/theme.js?v=dark62" defer></script>
</head>
<body class="fx-categories">
<section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
        <div class="wrapper fx-page-list fx-page-categories">
            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <svg class="svg-icon svg-lg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5C3 3.89543 3.89543 3 5 3H9C10.1046 3 11 3.89543 11 5V9C11 10.1046 10.1046 11 9 11H5C3.89543 11 3 10.1046 3 9V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 5C13 3.89543 13.8954 3 15 3H19C20.1046 3 21 3.89543 21 5V9C21 10.1046 20.1046 11 19 11H15C13.8954 11 13 10.1046 13 9V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 15C3 13.8954 3.89543 13 5 13H9C10.1046 13 11 13.8954 11 15V19C11 20.1046 10.1046 21 9 21H5C3.89543 21 3 20.1046 3 19V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 15C13 13.8954 13.8954 13 15 13H19C20.1046 13 21 13.8954 21 15V19C21 20.1046 20.1046 21 19 21H15C13.8954 21 13 20.1046 13 19V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        دسته‌بندی‌ها
                    </div>
                    <div class="page-head__sub">مدیریت دسته‌بندی محصولات</div>
                </div>
                <div class="chip-row">
                    <button onclick="openModal('modal-add-category')" class="btn btn-primary btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> افزودن دسته‌بندی
                    </button>
                    <button onclick="openModal('modal-move-category')" class="btn btn-soft-purple btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right-arrow-left', 'svg-icon'); ?> جابجایی دسته‌بندی‌ها
                    </button>
                </div>
            </div>

            <?php echo \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_flash_html(); ?>

            <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('category.php', $catQ, [], 'جستجو در شناسه یا نام دسته‌بندی…'); ?>

            <div class="card category-catalog<?php echo empty($categories) ? ' category-catalog--empty' : ''; ?>">
                <div class="category-catalog__head"><div><h2>دسته‌بندی محصولات</h2><p>گروه‌بندی محصولات برای دسترسی سریع‌تر کاربران</p></div><span class="category-count"><?php echo strtr(number_format((int)$pg['total']), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); ?> دسته‌بندی<?php echo $catQ !== '' ? ' مطابق جستجو' : ''; ?></span></div>
                <?php if (empty($categories)): ?>
                <div class="category-empty"><span class="category-empty__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('folder', 'svg-icon'); ?></span><h3><?php echo $catQ !== '' ? 'دسته‌بندی موردنظر پیدا نشد' : 'اولین دسته‌بندی را بسازید'; ?></h3><p><?php echo $catQ !== '' ? 'عبارت دیگری را جستجو کنید یا به فهرست کامل برگردید.' : 'محصولات خود را در گروه‌های مشخص قرار دهید تا انتخاب برای کاربران ساده‌تر شود.'; ?></p><?php if ($catQ !== ''): ?><a href="category.php" class="btn btn-outline btn-sm">نمایش همهٔ دسته‌بندی‌ها</a><?php else: ?><button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-category')"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon svg-sm'); ?> افزودن دسته‌بندی</button><?php endif; ?></div>
                <?php endif; ?>
                <form method="POST" action="category.php" id="bulk-form">
                    <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <div class="table-wrap">
                        <table id="categoriesTable" class="display app-table app-table--summary" style="width:100%">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="check-all" onclick="faoximaToggleAll(this)"></th>
                                    <th>شناسه</th>
                                    <th>نام دسته‌بندی</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr data-detail-row data-detail-title="<?php echo htmlspecialchars($cat['remark'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo $cat['id']; ?>"></label></td>
                                    <td data-label="شناسه" data-summary="1"><bdi class="category-id">#<?php echo (int)$cat['id']; ?></bdi></td>
                                    <td data-label="نام دسته‌بندی" data-summary="1"><span class="category-name"><span class="category-name__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('folder', 'svg-icon svg-sm'); ?></span><b><?php echo htmlspecialchars($cat['remark'], ENT_QUOTES, 'UTF-8'); ?></b></span></td>
                                    <td data-label="عملیات" class="cell-actions">
                                        <div class="cell-actions__group">
                                            <button type="button"
                                                class="btn btn-sm btn-soft-info"
                                                title="ویرایش"
                                                onclick="faoximaOpenEdit(<?php echo $cat['id']; ?>, <?php echo htmlspecialchars(json_encode($cat['remark'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen', 'svg-icon'); ?>
                                            </button>
                                            <button type="button"
                                                class="btn btn-sm btn-soft-danger"
                                                title="حذف"
                                                onclick="if(confirm('آیا از حذف این دسته‌بندی مطمئن هستید؟')) { document.getElementById('del-id').value=<?php echo $cat['id']; ?>; document.getElementById('delete-form').submit(); }">
                                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html($pg['page'], $pg['pages'], $pg['total'], count($categories), 'category.php', ['q' => $catQ !== '' ? $catQ : null]); ?>
                    </div>
                    <div style="padding:12px 0;">
                        <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف دسته‌بندی‌های انتخاب‌شده مطمئن هستید؟')">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</section>

<form method="POST" action="category.php" id="delete-form">
    <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="del-id" value="">
</form>

<?php echo \Faoxima\Admin\Web\Support\Ordering::fx_order_modal_html('category', 'modal-move-category'); ?>

<div id="modal-add-category" class="modal-overlay">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن دسته‌بندی</span>
            <button class="modal-close" onclick="closeModal('modal-add-category')">&times;</button>
        </div>
        <form action="category.php" method="POST">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <p class="category-form-lead">یک نام کوتاه و مشخص برای گروه محصولات انتخاب کنید.</p>
            <div class="form-group">
                <label class="form-label">نام دسته‌بندی</label>
                <input type="text" name="remark" class="form-control" placeholder="نام دسته‌بندی را وارد کنید" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">افزودن</button>
        </form>
    </div>
</div>

<div id="modal-edit-category" class="modal-overlay">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش دسته‌بندی</span>
            <button class="modal-close" onclick="closeModal('modal-edit-category')">&times;</button>
        </div>
        <form action="category.php" method="POST">
            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-cat-id" value="">
            <p class="category-form-lead">نام دسته‌بندی را تغییر دهید و سپس ذخیره کنید.</p>
            <div class="form-group">
                <label class="form-label">نام دسته‌بندی</label>
                <input type="text" name="remark" id="edit-cat-remark" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">ذخیره</button>
        </form>
    </div>
</div>

<script src="js/bulk-select.js?v=fx2"></script>
<script src="js/order_modal.js?v=ord1"></script>
<script>
function faoximaOpenEdit(id, remark) {
    document.getElementById('edit-cat-id').value = id;
    document.getElementById('edit-cat-remark').value = remark;
    openModal('modal-edit-category');
}
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
