<?php

/**
 * Reseller service renewal (web-only).
 *
 * Renewing a service buys the same product again on top of what the client
 * still has: days are added to the current expiry (from now if already
 * expired) and the volume quota is topped up by the product's volume. The
 * reseller's wallet is charged the product's reseller price, mirroring the
 * panel's own "extend" behaviour for the supported panel types.
 */

require_once __DIR__ . '/layout.php';

$reseller = reseller_require_login();
$pdo = $GLOBALS['pdo'];
$rid = (int) $reseller['id'];

require_once __DIR__ . '/../../function.php';
require_once __DIR__ . '/../../panels.php';
$ManagePanel = new ManagePanel();

$products = reseller_allowed_products($reseller);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reseller_csrf_check();
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $code = (string) ($_POST['product'] ?? '');

    $sel = $pdo->prepare("SELECT * FROM reseller_service WHERE id = :id AND reseller_id = :rid LIMIT 1");
    $sel->execute([':id' => $serviceId, ':rid' => $rid]);
    $svc = $sel->fetch(PDO::FETCH_ASSOC);

    $product = null;
    foreach ($products as $p) {
        if ((string) $p['code_product'] === $code) {
            $product = $p;
            break;
        }
    }

    $err = '';
    if (!$svc) {
        $err = 'سرویس یافت نشد.';
    } elseif ($svc['status'] !== 'active') {
        $err = 'فقط سرویس‌های فعال قابل تمدید هستند.';
    } elseif (!$product) {
        $err = 'محصول انتخابی برای تمدید معتبر نیست یا توسط مدیر فعال نشده است.';
    } else {
        $panelRow = select('marzban_panel', '*', 'name_panel', (string) $svc['panel_name'], 'select');
        if (!is_array($panelRow) || empty($panelRow['type'])) {
            $err = 'پنل سرویس یافت نشد.';
        }
    }

    $price = $product ? reseller_product_price($product) : 0;
    if ($err === '' && (int) $reseller['balance'] < $price) {
        $err = 'موجودی کیف پول برای تمدید کافی نیست.';
    }

    if ($err !== '') {
        reseller_flash_set('error', $err);
        header('Location: renew.php?service_id=' . $serviceId);
        exit;
    }

    $panelName = (string) $svc['panel_name'];
    $username = (string) $svc['username'];
    $days = (int) ($product['Service_time'] ?? 0);
    $volumeGb = (float) ($product['Volume_constraint'] ?? 0);
    $volumeBytes = $volumeGb > 0 ? (int) round($volumeGb * pow(1024, 3)) : 0;

    // Current state from the panel (units: data_limit bytes, expire seconds).
    $cur = $ManagePanel->DataUser($panelName, $username);
    if (!is_array($cur) || (($cur['status'] ?? '') === 'Unsuccessful')) {
        $msg = is_array($cur) && isset($cur['msg']) ? $cur['msg'] : 'نامشخص';
        reseller_flash_set('error', 'دریافت وضعیت سرویس از پنل ناموفق بود: ' . $msg);
        header('Location: renew.php?service_id=' . $serviceId);
        exit;
    }

    $oldExpire = (int) ($cur['expire'] ?? 0);
    $baseExpire = max(time(), $oldExpire);
    $newExpire = $days > 0 ? $baseExpire + $days * 86400 : 0;

    $oldLimit = (float) ($cur['data_limit'] ?? 0);
    $newLimit = $volumeBytes > 0 ? $oldLimit + $volumeBytes : 0;

    $type = (string) $panelRow['type'];
    if ($type === 'marzban') {
        $config = ['data_limit' => $newLimit, 'expire' => $newExpire];
    } elseif ($type === 'marzneshin') {
        $config = ['data_limit' => $newLimit, 'expire_date' => $newExpire, 'expire_strategy' => 'fixed_date'];
    } elseif ($type === 'x-ui_single') {
        $config = [
            'settings' => json_encode([
                'clients' => [
                    [
                        'totalGB'    => xui_bytes_to_gb($newLimit),
                        'expiryTime' => $newExpire * 1000,
                        'enable'     => true,
                    ],
                ],
            ]),
        ];
    } else {
        reseller_flash_set('error', 'تمدید برای این نوع پنل (' . $type . ') پشتیبانی نمی‌شود.');
        header('Location: renew.php?service_id=' . $serviceId);
        exit;
    }

    try {
        $res = $ManagePanel->Modifyuser($username, $panelName, $config);
    } catch (\Throwable $e) {
        error_log('[reseller renew] Modifyuser: ' . $e->getMessage());
        $res = ['status' => false, 'msg' => 'خطای داخلی در تمدید سرویس'];
    }
    if (!is_array($res) || empty($res['status'])) {
        $msg = is_array($res) && isset($res['msg']) ? $res['msg'] : 'نامشخص';
        reseller_flash_set('error', 'تمدید در پنل ناموفق بود: ' . $msg);
        header('Location: renew.php?service_id=' . $serviceId);
        exit;
    }

    // Charge the wallet only after the panel confirmed the update.
    $charge = reseller_wallet_apply(
        $rid,
        'purchase',
        -$price,
        'تمدید سرویس ' . ($product['name_product'] ?? $product['code_product']) . ' (' . $username . ')',
        'service:' . $serviceId
    );
    if (!$charge['ok']) {
        reseller_flash_set('error', 'تمدید در پنل انجام شد اما کسر از کیف پول ناموفق بود؛ با مدیر تماس بگیرید.');
        header('Location: renew.php?service_id=' . $serviceId);
        exit;
    }

    $newVolGb = $volumeBytes > 0 ? ((float) ($svc['volume_gb'] ?? 0) + $volumeGb) : 0;
    $newDays = (int) ($svc['days'] ?? 0) + $days;
    $upd = $pdo->prepare(
        "UPDATE reseller_service SET expire_at = :exp, volume_gb = :vol, days = :days WHERE id = :id"
    );
    $upd->execute([
        ':exp'  => $newExpire > 0 ? (string) $newExpire : '',
        ':vol'  => (string) $newVolGb,
        ':days' => (string) $newDays,
        ':id'   => $serviceId,
    ]);

    reseller_flash_set('success', 'سرویس با موفقیت تمدید شد.');
    header('Location: services.php');
    exit;
}

// GET: pick an active service, then a product.
$serviceId = (int) ($_GET['service_id'] ?? 0);
$svc = null;
if ($serviceId > 0) {
    $sel = $pdo->prepare("SELECT * FROM reseller_service WHERE id = :id AND reseller_id = :rid LIMIT 1");
    $sel->execute([':id' => $serviceId, ':rid' => $rid]);
    $svc = $sel->fetch(PDO::FETCH_ASSOC);
    if ($svc && $svc['status'] !== 'active') {
        $svc = null;
    }
}

$allServices = [];
if (!$svc) {
    $st = $pdo->prepare("SELECT id, username, panel_name, volume_gb, days, expire_at FROM reseller_service WHERE reseller_id = :rid AND status = 'active' ORDER BY id DESC LIMIT 200");
    $st->execute([':rid' => $rid]);
    $allServices = $st->fetchAll(PDO::FETCH_ASSOC);
}

reseller_layout_head('تمدید سرویس', 'services', $reseller);
?>
<div class="page-head">
    <div>
        <div class="page-head__title"><?php echo icon('rotate-left', 'svg-icon svg-lg'); ?> تمدید سرویس</div>
        <div class="page-head__sub">افزودن زمان و حجم به سرویس فعال مشتری</div>
    </div>
</div>

<?php reseller_flash_render(); ?>

<?php if (!$products): ?>
    <div class="alert alert-info">
        <?php echo icon('circle-info', 'svg-icon'); ?>
        <span>هنوز محصولی برای فروش نماینده توسط مدیر فعال نشده است.</span>
    </div>
<?php elseif (!$svc && !$allServices): ?>
    <div class="alert alert-info">
        <?php echo icon('circle-info', 'svg-icon'); ?>
        <span>سرویس فعالی برای تمدید وجود ندارد. ابتدا از «ساخت سرویس» یک سرویس بسازید.</span>
    </div>
<?php else: ?>

<?php if (!$svc): ?>
<div class="card mb-3">
    <div class="card__head"><div class="card__title"><?php echo icon('package', 'svg-icon svg-sm'); ?> انتخاب سرویس</div></div>
    <div style="padding:16px;">
        <form method="get" action="renew.php">
            <div class="form-group">
                <label class="form-label">سرویس فعال</label>
                <select name="service_id" class="form-control" required>
                    <option value="">— انتخاب سرویس —</option>
                    <?php foreach ($allServices as $s): ?>
                        <?php $exp = (int) $s['expire_at']; ?>
                        <option value="<?php echo (int) $s['id']; ?>">
                            <?php echo reseller_e($s['username'] . ' — ' . $s['panel_name'] . ' — ' . $s['volume_gb'] . 'GB/' . $s['days'] . 'د'); ?>
                            <?php echo $exp > 0 ? ' — انقضا: ' . reseller_jdate($exp, 'Y/m/d') : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mt-2"><?php echo icon('arrow-left', 'svg-icon svg-sm'); ?> ادامه</button>
        </form>
    </div>
</div>
<?php else: ?>

<div class="card">
    <div class="card__head"><div class="card__title"><?php echo icon('rotate-left', 'svg-icon svg-sm'); ?> تمدید سرویس «<?php echo reseller_e($svc['username']); ?>»</div></div>
    <div style="padding:16px;">
        <div class="info-grid mb-3" style="display:flex; gap:14px; flex-wrap:wrap; color:var(--text-muted);">
            <span><?php echo icon('server', 'svg-icon svg-xs'); ?> پنل: <?php echo reseller_e($svc['panel_name']); ?></span>
            <span><?php echo icon('package', 'svg-icon svg-xs'); ?> حجم فعلی: <?php echo reseller_e($svc['volume_gb']); ?> GB</span>
            <span><?php echo icon('circle-dot', 'svg-icon svg-xs'); ?> مدت فعلی: <?php echo reseller_e($svc['days']); ?> روز</span>
            <?php if ((int) $svc['expire_at'] > 0): ?>
                <span><?php echo icon('circle-dot', 'svg-icon svg-xs'); ?> انقضا: <?php echo reseller_jdate((int) $svc['expire_at'], 'Y/m/d'); ?></span>
            <?php endif; ?>
        </div>

        <form method="post" action="renew.php">
            <?php echo reseller_csrf_field(); ?>
            <input type="hidden" name="service_id" value="<?php echo (int) $svc['id']; ?>">
            <div class="form-group">
                <label class="form-label">محصول تمدید (همان مشخصات به سرویس اضافه می‌شود)</label>
                <select name="product" class="form-control" required>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $price = reseller_product_price($p);
                        $vol = trim((string) ($p['Volume_constraint'] ?? ''));
                        $dys = trim((string) ($p['Service_time'] ?? ''));
                        $label = ($p['name_product'] ?? $p['code_product']) . ' — ' . number_format($price) . ' تومان';
                        if ($vol !== '' || $dys !== '') $label .= " ({$vol}GB / {$dys} روز)";
                        ?>
                        <option value="<?php echo reseller_e($p['code_product']); ?>">
                            <?php echo reseller_e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="alert alert-info">
                <?php echo icon('circle-info', 'svg-icon'); ?>
                <span>زمان تمدید از پایان تاریخ فعلی سرویس (یا امروز، اگر منقضی شده باشد) محاسبه می‌شود و حجم محصول به سقف فعلی اضافه می‌گردد.</span>
            </div>
            <button type="submit" class="btn btn-primary mt-2">
                <?php echo icon('rotate-left', 'svg-icon svg-sm'); ?> تمدید و کسر از کیف پول
            </button>
            <a href="services.php" class="btn btn-outline mt-2">انصراف</a>
        </form>
    </div>
</div>

<?php endif; ?>
<?php endif; ?>

<?php
reseller_layout_foot();
