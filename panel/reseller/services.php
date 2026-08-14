<?php

require_once __DIR__ . '/layout.php';

$reseller = reseller_require_login();
$pdo = $GLOBALS['pdo'];
$rid = (int) $reseller['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reseller_csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $force = (int) ($_POST['force'] ?? 0) === 1;

    $sel = $pdo->prepare("SELECT * FROM reseller_service WHERE id = :id AND reseller_id = :rid LIMIT 1");
    $sel->execute([':id' => $serviceId, ':rid' => $rid]);
    $svc = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$svc) {
        reseller_flash_set('error', 'سرویس یافت نشد.');
        header('Location: services.php');
        exit;
    }

    if ($action === 'delete') {            $alreadyGone = false;
        if (!$force) {
            require_once __DIR__ . '/../../function.php';
            require_once __DIR__ . '/../../panels.php';
            $ManagePanel = new ManagePanel();
            $ok = false;
            try {
                // If the panel explicitly says the client does not exist, the
                // delete is effectively complete. Connection errors are NOT
                // treated as "gone" so we never drop the local record while
                // the user still exists on the panel.
                $cur = $ManagePanel->DataUser((string) $svc['panel_name'], (string) $svc['username']);
                $stillThere = is_array($cur) && (($cur['status'] ?? '') !== 'Unsuccessful');
                if ($stillThere) {
                    $res = $ManagePanel->RemoveUser((string) $svc['panel_name'], (string) $svc['username']);
                    $ok = is_array($res) && (($res['status'] ?? '') === 'successful');
                } else {
                    $curMsg = is_array($cur) ? (string) ($cur['msg'] ?? '') : '';
                    $notFoundHints = array('not found', 'notfound', 'not exist', '404', 'inbound not found', 'user not found', 'not_found');
                    $hintHit = false;
                    foreach ($notFoundHints as $hint) {
                        if (stripos($curMsg, $hint) !== false) {
                            $hintHit = true;
                            break;
                        }
                    }
                    $alreadyGone = $hintHit;
                }
            } catch (\Throwable $e) {
                error_log('[reseller services delete] ' . $e->getMessage());
            }
            if ($ok || $alreadyGone) {
                $pdo->prepare("UPDATE reseller_service SET status = 'deleted' WHERE id = :id")
                    ->execute([':id' => $serviceId]);
                reseller_flash_set('success', 'سرویس حذف شد.');
            } else {
                reseller_flash_set('error', 'حذف از پنل ناموفق بود. اگر سرویس در پنل وجود ندارد، از «حذف محلی» استفاده کنید.');
            }
        } else {
            // Force: remove the local record regardless of panel state.
            $pdo->prepare("UPDATE reseller_service SET status = 'deleted' WHERE id = :id")
                ->execute([':id' => $serviceId]);
            reseller_flash_set('success', 'سرویس به‌صورت محلی حذف شد.');
        }
        header('Location: services.php');
        exit;
    }
}

$filter = (string) ($_GET['status'] ?? 'all');
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$now = time();
$where = "reseller_id = :rid";
$params = [':rid' => $rid];
if ($filter === 'active') {
    $where .= " AND status = 'active' AND (expire_at = '' OR CAST(expire_at AS UNSIGNED) > :now1)";
    $params[':now1'] = $now;
} elseif ($filter === 'expired') {
    $where .= " AND status = 'active' AND expire_at <> '' AND CAST(expire_at AS UNSIGNED) <= :now2";
    $params[':now2'] = $now;
} elseif ($filter === 'deleted') {
    $where .= " AND status = 'deleted'";
}
if ($q !== '') {
    $where .= " AND (username LIKE :q OR customer_name LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reseller_service WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM reseller_service WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

reseller_layout_head('سرویس‌ها', 'services', $reseller);
?>
<div class="page-head">
    <div>
        <div class="page-head__title"><?php echo icon('package', 'svg-icon svg-lg'); ?> سرویس‌های من</div>
        <div class="page-head__sub">مدیریت سرویس‌های ساخته‌شده</div>
    </div>
    <div class="chip-row">
        <a href="services.php" class="chip<?php echo $filter === 'all' ? ' is-active' : ''; ?>"><span>همه</span></a>
        <a href="services.php?status=active" class="chip<?php echo $filter === 'active' ? ' is-active' : ''; ?>"><span>فعال</span></a>
        <a href="services.php?status=expired" class="chip<?php echo $filter === 'expired' ? ' is-active' : ''; ?>"><span>منقضی</span></a>
        <a href="services.php?status=deleted" class="chip<?php echo $filter === 'deleted' ? ' is-active' : ''; ?>"><span>حذف‌شده</span></a>
        <a href="renew.php" class="chip" style="border-color:var(--accent-mid); color:var(--accent);"><?php echo icon('rotate-left', 'svg-icon svg-sm'); ?><span>تمدید سرویس</span></a>
    </div>
</div>

<?php reseller_flash_render(); ?>

<div class="card" style="margin-bottom:14px;">
    <form method="get" action="services.php" style="padding:12px 16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <?php if ($filter !== 'all'): ?><input type="hidden" name="status" value="<?php echo reseller_e($filter); ?>"><?php endif; ?>
        <input type="text" name="q" class="form-control" style="max-width:320px;" placeholder="جستجوی نام کاربری یا نام مشتری…" value="<?php echo reseller_e($q); ?>">
        <button type="submit" class="btn btn-primary btn-sm">جستجو</button>
        <?php if ($q !== ''): ?><a href="services.php<?php echo $filter !== 'all' ? '?status=' . reseller_e($filter) : ''; ?>" class="btn btn-sm btn-outline">پاک کردن</a><?php endif; ?>
        <span class="text-muted" style="margin-inline-start:auto;"><?php echo number_format($total); ?> سرویس</span>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="app-table">
            <thead>
                <tr>
                    <th>نام کاربری</th>
                    <th>مشتری</th>
                    <th>حجم/مدت</th>
                    <th>قیمت</th>
                    <th>وضعیت</th>
                    <th>انقضا</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" style="text-align:center; padding:24px;">سرویسی یافت نشد.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $s): ?>
                        <?php
                        $expTs = (int) ($s['expire_at'] ?? 0);
                        $isExpired = $s['status'] === 'active' && $expTs > 0 && $expTs <= $now;
                        ?>
                        <tr>
                            <td style="direction:ltr;"><?php echo reseller_e($s['username']); ?></td>
                            <td><?php echo reseller_e(($s['customer_name'] ?? '') !== '' ? $s['customer_name'] : '—'); ?></td>
                            <td style="direction:ltr;"><?php echo reseller_e($s['volume_gb']); ?>GB / <?php echo reseller_e($s['days']); ?>د</td>
                            <td style="direction:ltr;"><?php echo number_format((int) $s['price']); ?></td>
                            <td>
                                <?php if ($s['status'] === 'active' && !$isExpired): ?>
                                    <span class="badge badge-success">فعال</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="badge badge-warning">منقضی</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">حذف‌شده</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $expTs > 0 ? reseller_jdate($expTs, 'Y/m/d') : 'نامحدود'; ?></td>
                            <td style="display:flex; gap:6px; flex-wrap:wrap;">
                                <a href="subscription.php?token=<?php echo reseller_e($s['sub_token']); ?>" class="btn btn-sm btn-soft-info" target="_blank">
                                    <?php echo icon('eye', 'svg-icon svg-xs'); ?> اشتراک
                                </a>
                                <?php if ($s['status'] === 'active'): ?>
                                    <a href="renew.php?service_id=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-soft-success">
                                        <?php echo icon('rotate-left', 'svg-icon svg-xs'); ?> تمدید
                                    </a>
                                <?php endif; ?>
                                <?php if ($s['status'] === 'active'): ?>
                                    <form method="post" action="services.php" onsubmit="return confirm('سرویس از پنل حذف شود؟ این عملیات قابل بازگشت نیست.');" style="display:inline;">
                                        <?php echo reseller_csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo icon('trash', 'svg-icon svg-xs'); ?> حذف</button>
                                    </form>
                                    <form method="post" action="services.php" onsubmit="return confirm('سرویس بدون ارتباط با پنل (فقط محلی) حذف شود؟');" style="display:inline;">
                                        <?php echo reseller_csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="force" value="1">
                                        <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" title="حذف محلی (بدون درخواست به پنل)">حذف محلی</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div style="padding:14px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?>"
               href="services.php?status=<?php echo reseller_e($filter); ?>&q=<?php echo reseller_e($q); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php
reseller_layout_foot();
