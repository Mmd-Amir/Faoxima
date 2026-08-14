<?php

/**
 * Reseller panel chrome (header / sidebar / footer).
 *
 * Reuses the admin panel's compiled theme (../css/theme.css + ../js/theme.js)
 * but renders a reseller-scoped navigation and account menu.
 */

require_once __DIR__ . '/lib.php';

if (!function_exists('reseller_nav_items')) {
    function reseller_nav_items()
    {
        return [
            'index'    => ['icon' => 'home',          'label' => 'داشبورد',        'file' => 'index.php'],
            'wallet'   => ['icon' => 'wallet',        'label' => 'کیف پول',        'file' => 'wallet.php'],
            'services' => ['icon' => 'package',       'label' => 'سرویس‌ها',       'file' => 'services.php'],
            'new'      => ['icon' => 'cart-shopping', 'label' => 'ساخت سرویس',     'file' => 'service_new.php'],
            'reports'  => ['icon' => 'chart-line',    'label' => 'حسابداری',       'file' => 'reports.php'],
            'withdraw' => ['icon' => 'coins',         'label' => 'برداشت وجه',     'file' => 'withdraw.php'],
            'pricing'  => ['icon' => 'money-bill',     'label' => 'قیمت فروش',      'file' => 'pricing.php'],
            'password' => ['icon' => 'lock',           'label' => 'تغییر رمز',      'file' => 'password.php'],
        ];
    }
}

if (!function_exists('reseller_layout_head')) {
    function reseller_layout_head($title, $active, array $reseller)
    {
        $name = trim((string) ($reseller['name'] ?? '')) !== ''
            ? (string) $reseller['name']
            : (string) $reseller['username'];
        $balance = number_format((float) ($reseller['balance'] ?? 0));
        ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo reseller_e($title); ?> — پنل نمایندگی</title>
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/aurora.css">
    <script src="../js/theme.js" defer></script>
    <script>
    (function () {
        try {
            var color = localStorage.getItem('faoxima_color') || 'blue';
            var theme = localStorage.getItem('faoxima_theme') || 'dark';
            var html = document.documentElement;
            if (!html.getAttribute('data-color')) html.setAttribute('data-color', color);
            if (!html.getAttribute('data-theme')) html.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
    </script>
</head>
<body>
<section id="container">

<header class="app-header">
    <div class="app-header__left">
        <button class="btn-icon" id="sidebar-toggle" aria-label="منو"><?php echo icon('bars'); ?></button>
        <a href="index.php" class="app-logo">
            <span class="app-logo__mark"><?php echo icon('user-tag', 'svg-icon svg-sm'); ?></span>
            پنل&nbsp;<span>نمایندگی</span>
        </a>
        <span class="app-status-pill"><?php echo icon('wallet', 'svg-icon svg-xs'); ?>&nbsp;موجودی: <?php echo $balance; ?> تومان</span>
    </div>

    <div class="profile-wrap">
        <button class="profile-trigger" type="button" aria-label="حساب کاربری">
            <span class="profile-info">
                <b><?php echo reseller_e($name); ?></b>
                <small>نماینده</small>
            </span>
            <?php echo icon('chevron-down', 'svg-icon svg-xs'); ?>
        </button>
        <div class="profile-menu">
            <div class="profile-menu__head">
                <b><?php echo reseller_e($name); ?></b>
                <small>نماینده فروش</small>
            </div>
            <div class="theme-swatches no-close" title="رنگ تم">
                <span class="swatch" data-color="red"    title="قرمز"></span>
                <span class="swatch" data-color="blue"   title="آبی"></span>
                <span class="swatch" data-color="purple" title="بنفش"></span>
                <span class="swatch" data-color="yellow" title="زرد"></span>
                <span class="swatch" data-color="orange" title="نارنجی"></span>
                <span class="swatch" data-color="green"  title="سبز"></span>
            </div>
            <hr>
            <button id="theme-toggle" type="button">
                <span id="theme-toggle-icon"><?php echo icon('moon', 'svg-icon svg-sm'); ?></span>
                <span id="theme-toggle-label">حالت روز</span>
            </button>
            <hr>
            <a href="logout.php" class="menu-danger">
                <?php echo icon('arrow-right-from-bracket', 'svg-icon svg-sm'); ?>
                <span>خروج از حساب</span>
            </a>
        </div>
    </div>
</header>

<aside class="app-sidebar">
    <div class="sidebar-section-label">منوی نماینده</div>
    <ul class="sidebar-menu">
        <?php foreach (reseller_nav_items() as $key => $item): ?>
            <li>
                <a href="<?php echo $item['file']; ?>"<?php echo $key === $active ? ' class="active"' : ''; ?>>
                    <span class="menu-symbol"><?php echo icon($item['icon'], 'svg-icon svg-sm'); ?></span>
                    <span><?php echo reseller_e($item['label']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-version" style="margin-top:auto; padding:14px 16px; border-top:1px solid var(--border-soft,#2a2a35); display:flex; align-items:center; gap:8px; color:var(--text-muted,#8a8a9a); font-size:12px;">
        <span class="menu-symbol"><?php echo icon('user-tag', 'svg-icon svg-sm'); ?></span>
        <span>پنل نمایندگی فاکسیما</span>
    </div>
</aside>

<div class="sidebar-overlay"></div>

<section id="main-content">
    <div class="wrapper">
<?php
    }
}

if (!function_exists('reseller_layout_foot')) {
    function reseller_layout_foot()
    {
        ?>
    </div>
</section>
</section>
</body>
</html>
<?php
    }
}

if (!function_exists('reseller_flash_set')) {
    function reseller_flash_set($type, $msg)
    {
        $_SESSION['reseller_flash'] = ['type' => $type, 'msg' => $msg];
    }
}

if (!function_exists('reseller_flash_render')) {
    function reseller_flash_render()
    {
        if (empty($_SESSION['reseller_flash'])) {
            return;
        }
        $f = $_SESSION['reseller_flash'];
        unset($_SESSION['reseller_flash']);
        $cls = $f['type'] === 'error' ? 'alert-error' : ($f['type'] === 'info' ? 'alert-info' : 'alert-success');
        echo '<div class="alert ' . $cls . '">' . reseller_e($f['msg']) . '</div>';
    }
}

if (!function_exists('reseller_csrf_field')) {
    function reseller_csrf_field()
    {
        return '<input type="hidden" name="_csrf" value="' . reseller_e(reseller_csrf_token()) . '">';
    }
}
