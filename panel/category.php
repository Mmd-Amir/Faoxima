<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/../function.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

fx_csrf_guard();

if (!empty($_POST['action']) && $_POST['action'] === 'add') {
    $remark = trim((string)($_POST['remark'] ?? ''));
    if ($remark !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = :remark");
        $stmt->execute([':remark' => $remark]);
        if ((int)$stmt->fetchColumn() === 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO category (remark) VALUES (:remark)");
            $stmt->execute([':remark' => $remark]);
        }
    }
    header('Location: category.php');
    exit;
}

if (!empty($_POST['action']) && $_POST['action'] === 'edit') {
    $id     = (int)($_POST['id'] ?? 0);
    $remark = trim((string)($_POST['remark'] ?? ''));
    if ($id > 0 && $remark !== '') {
        $old = $pdo->prepare("SELECT remark FROM category WHERE id = :id");
        $old->execute([':id' => $id]);
        $oldRemark = (string)($old->fetchColumn() ?: '');
        $pdo->prepare("UPDATE category SET remark = :remark WHERE id = :id")
            ->execute([':remark' => $remark, ':id' => $id]);
        if ($oldRemark !== '' && $oldRemark !== $remark) {
            $pdo->prepare("UPDATE product SET category = :new WHERE category = :old")
                ->execute([':new' => $remark, ':old' => $oldRemark]);
        }
    }
    header('Location: category.php');
    exit;
}

if (!empty($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM category WHERE id = :id")->execute([':id' => $id]);
    }
    header('Location: category.php');
    exit;
}

if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $requestedIds = $_POST['ids'] ?? [];
    $deletedCount = fx_bulk_delete_ids($pdo, 'category', 'id', $requestedIds);
    fx_bulk_delete_redirect('category.php', count($requestedIds), $deletedCount);
}

$catQ = fx_search_current();
$catWhereSql = '1=1';
$catParams = [];
if ($catQ !== '') {
    $catLike = '%' . $catQ . '%';
    $catWhereSql .= ' AND (id LIKE :cq1 OR remark LIKE :cq2)';
    $catParams[':cq1'] = $catLike;
    $catParams[':cq2'] = $catLike;
}

$pg = fx_paginate($pdo, "SELECT COUNT(*) FROM category WHERE $catWhereSql", $catParams, 5);
$query = $pdo->prepare("SELECT * FROM category WHERE $catWhereSql ORDER BY id ASC LIMIT :perPage OFFSET :offset");
foreach ($catParams as $k => $v) $query->bindValue($k, $v, PDO::PARAM_STR);
$query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
$query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$query->execute();
$categories = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت دسته‌بندی‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
    <script src="js/theme.js?v=flat5" defer></script>
</head>
<body>
<section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
        <div class="wrapper fx-page-list">
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
                        <?php echo icon('plus', 'svg-icon'); ?> افزودن دسته‌بندی
                    </button>
                </div>
            </div>

            <?php echo fx_bulk_delete_flash_html(); ?>

            <?php echo fx_search_ui('category.php', $catQ, [], 'جستجو در شناسه یا نام دسته‌بندی…'); ?>

            <div class="card">
                <form method="POST" action="category.php" id="bulk-form">
                    <?php echo fx_csrf_field(); ?>
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
                                    <td data-label="شناسه" data-summary="1"><?php echo $cat['id']; ?></td>
                                    <td data-label="نام دسته‌بندی" data-summary="1"><?php echo htmlspecialchars($cat['remark'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="عملیات" class="cell-actions">
                                        <div class="cell-actions__group">
                                            <button type="button"
                                                class="btn btn-sm btn-soft-info"
                                                title="ویرایش"
                                                onclick="faoximaOpenEdit(<?php echo $cat['id']; ?>, <?php echo htmlspecialchars(json_encode($cat['remark'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <?php echo icon('pen', 'svg-icon'); ?>
                                            </button>
                                            <button type="button"
                                                class="btn btn-sm btn-soft-danger"
                                                title="حذف"
                                                onclick="if(confirm('آیا از حذف این دسته‌بندی مطمئن هستید؟')) { document.getElementById('del-id').value=<?php echo $cat['id']; ?>; document.getElementById('delete-form').submit(); }">
                                                <?php echo icon('trash', 'svg-icon'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php echo fx_pager_html($pg['page'], $pg['pages'], $pg['total'], count($categories), 'category.php', ['q' => $catQ !== '' ? $catQ : null]); ?>
                    </div>
                    <div style="padding:12px 0;">
                        <button type="submit" class="btn btn-soft-danger btn-sm js-bulk-delete-btn" style="display:none;" onclick="return confirm('آیا از حذف دسته‌بندی‌های انتخاب‌شده مطمئن هستید؟')">
                            <?php echo icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</section>

<form method="POST" action="category.php" id="delete-form">
    <?php echo fx_csrf_field(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="del-id" value="">
</form>

<div id="modal-add-category" class="modal-overlay">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن دسته‌بندی</span>
            <button class="modal-close" onclick="closeModal('modal-add-category')">&times;</button>
        </div>
        <form action="category.php" method="POST">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="action" value="add">
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
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-cat-id" value="">
            <div class="form-group">
                <label class="form-label">نام دسته‌بندی</label>
                <input type="text" name="remark" id="edit-cat-remark" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">ذخیره</button>
        </form>
    </div>
</div>

<script src="js/bulk-select.js?v=fx2"></script>
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
