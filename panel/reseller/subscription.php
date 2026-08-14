<?php

/**
 * Public subscription delivery page for a reseller service.
 *
 * Shown to the end customer (shareable via the unguessable sub_token). Displays
 * the subscription link, a QR code, and the individual configs.
 */

require_once __DIR__ . '/lib.php';

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['token'] ?? ''));
$pdo = $GLOBALS['pdo'] ?? null;

$svc = null;
if ($token !== '' && $pdo instanceof PDO) {
    $stmt = $pdo->prepare("SELECT * FROM reseller_service WHERE sub_token = :t LIMIT 1");
    $stmt->execute([':t' => $token]);
    $svc = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$svc) {
    http_response_code(404);
    ?>
    <!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/aurora.css"><title>یافت نشد</title></head>
    <body class="login-page"><div class="login-card"><div class="login-body" style="text-align:center;">
    <h2>اشتراک یافت نشد</h2><p class="text-muted">لینک نامعتبر است یا منقضی شده است.</p>
    </div></div></body></html>
    <?php
    exit;
}

$subLink = (string) ($svc['sub_link'] ?? '');
$configs = [];
$rawConfigs = (string) ($svc['uuid'] ?? '');
if ($rawConfigs !== '') {
    $decoded = json_decode($rawConfigs, true);
    if (is_array($decoded)) {
        $configs = array_values(array_filter(array_map('strval', $decoded), static function ($c) {
            return trim($c) !== '';
        }));
    }
}
$expireTs = (int) ($svc['expire_at'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>اشتراک سرویس</title>
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/aurora.css">
    <style>
        .sub-wrap { max-width: 560px; margin: 24px auto; padding: 0 16px; }
        .sub-qr { display:flex; justify-content:center; padding:18px; }
        .sub-qr img { width:240px; height:240px; border-radius:14px; background:#fff; padding:10px; }
        .sub-link-box { display:flex; gap:8px; }
        .sub-link-box input { flex:1; direction:ltr; }
        .config-item { direction:ltr; font-family:'JetBrains Mono',monospace; font-size:12px; word-break:break-all;
            background:var(--surface-2,rgba(255,255,255,0.04)); border:1px solid var(--border-soft,#2a2a35);
            border-radius:10px; padding:10px; margin-bottom:8px; }
    </style>
</head>
<body class="public-page">
<div class="public-brand">
    <div class="public-brand__name"><span class="public-brand__mark"><?php echo icon('server', 'svg-icon svg-sm'); ?></span><span>Hamoix · اشتراک امن</span></div>
    <span class="app-status-pill">اتصال آماده</span>
</div>
<div class="sub-wrap">
    <div class="welcome-banner" style="margin-bottom:18px;">
        <div class="welcome-banner__copy">
            <div class="welcome-banner__eyebrow"><?php echo icon('shield', 'svg-icon svg-xs'); ?> اشتراک امن Hamoix</div>
            <h2>اتصال شما آماده استفاده است</h2>
            <p>لینک اشتراک را در برنامه‌ی مورد علاقه‌تان وارد کنید یا با QR اسکن کنید.</p>
        </div>
    </div>
    <div class="card">
        <div class="card__head"><div class="card__title"><?php echo icon('paper-plane', 'svg-icon svg-sm'); ?> اشتراک شما آماده است</div></div>
        <div style="padding:16px;">
            <div class="sub-qr">
                <?php if ($subLink !== ''): ?>
                    <img src="qr.php?token=<?php echo reseller_e($token); ?>" alt="QR">
                <?php endif; ?>
            </div>

            <?php if ($subLink !== ''): ?>
            <label class="form-label">لینک اشتراک (Subscription)</label>
            <div class="sub-link-box">
                <input type="text" id="subLink" class="form-control" value="<?php echo reseller_e($subLink); ?>" readonly>
                <button type="button" class="btn btn-primary" onclick="copySub()"><?php echo icon('copy', 'svg-icon svg-sm'); ?></button>
            </div>
            <?php else: ?>
                <div class="alert alert-info"><?php echo icon('circle-info', 'svg-icon'); ?><span>لینک اشتراک در دسترس نیست.</span></div>
            <?php endif; ?>

            <div class="info-grid mt-3" style="display:flex; gap:16px; flex-wrap:wrap; color:var(--text-muted);">
                <span><?php echo icon('package', 'svg-icon svg-xs'); ?> حجم: <?php echo reseller_e($svc['volume_gb']); ?> GB</span>
                <span><?php echo icon('circle-dot', 'svg-icon svg-xs'); ?> مدت: <?php echo reseller_e($svc['days']); ?> روز</span>
                <span><?php echo icon('circle-info', 'svg-icon svg-xs'); ?> انقضا: <?php echo $expireTs > 0 ? reseller_jdate($expireTs, 'Y/m/d') : 'نامحدود'; ?></span>
            </div>
        </div>
    </div>

    <?php if ($configs): ?>
    <div class="card mt-3">
        <div class="card__head"><div class="card__title"><?php echo icon('list', 'svg-icon svg-sm'); ?> کانفیگ‌ها</div></div>
        <div style="padding:16px;">
            <?php foreach ($configs as $cfg): ?>
                <div class="config-item" onclick="navigator.clipboard && navigator.clipboard.writeText(this.textContent.trim())" title="برای کپی کلیک کنید"><?php echo reseller_e($cfg); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function copySub() {
    var i = document.getElementById('subLink');
    if (!i) return;
    i.select();
    if (navigator.clipboard) { navigator.clipboard.writeText(i.value); }
    else { document.execCommand('copy'); }
}
</script>
</body>
</html>
