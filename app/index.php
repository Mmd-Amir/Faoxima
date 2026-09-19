<?php


declare(strict_types=1);


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
if ($scriptDir === '.' || $scriptDir === '') {
    $scriptDir = '';
} elseif ($scriptDir !== '/') {
    $scriptDir = '/' . ltrim($scriptDir, '/');
    $scriptDir = rtrim($scriptDir, '/');
} else {
    $scriptDir = '/';
}
$basename = $scriptDir === '' ? '/' : $scriptDir;
$prefix = $basename === '/' ? '/' : $basename . '/';
$assetPrefix = $prefix;

$rootForApi = $basename === '/' ? '/' : rtrim(str_replace('\\', '/', dirname($basename)), '/');
if ($rootForApi === '' || $rootForApi === '.') {
    $rootForApi = '/';
}
$apiPath = $rootForApi === '/' ? '/api' : $rootForApi . '/api';

$apiUrl = $apiPath;

$brandAppVersion = trim((string)@file_get_contents(__DIR__ . '/version')) ?: '1.0.5';


const FX_DEFAULT_BRAND_NAME = 'faoxima';
$brandName = FX_DEFAULT_BRAND_NAME;
$brandMark = 'M';
$brandTitle = '';
$brandLogoUrl = '';
$brandLogoState = 'default';
$brandAccent = '';
$brandMode = 'dark';



if (is_file(__DIR__ . '/../config.php') && is_file(__DIR__ . '/../function.php')) {
    @require_once __DIR__ . '/../config.php';
    @require_once __DIR__ . '/../function.php';
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && function_exists('select')) {
        try {
            $nameRow = select('shopSetting', '*', 'Namevalue', 'brand_name', 'select');
            $markRow = select('shopSetting', '*', 'Namevalue', 'brand_mark', 'select');
            $titleRow = select('shopSetting', '*', 'Namevalue', 'brand_title', 'select');
            $logoRow = select('shopSetting', '*', 'Namevalue', 'brand_logo', 'select');
            $logoStateRow = select('shopSetting', '*', 'Namevalue', 'brand_logo_state', 'select');
            $accentRow = select('shopSetting', '*', 'Namevalue', 'brand_accent', 'select');
            $qrDisabledRow = select('shopSetting', '*', 'Namevalue', 'qr_disabled', 'select');
            if (is_array($accentRow) && isset($accentRow['value']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$accentRow['value'])) {
                $brandAccent = strtolower((string)$accentRow['value']);
            }
            if (is_array($nameRow) && isset($nameRow['value']) && $nameRow['value'] !== '') {
                $brandName = (string)$nameRow['value'];
            }
            if (is_array($markRow) && isset($markRow['value']) && $markRow['value'] !== '') {
                $brandMark = (string)$markRow['value'];
            }
            if (is_array($titleRow) && isset($titleRow['value']) && $titleRow['value'] !== '') {
                $brandTitle = (string)$titleRow['value'];
            }
            if (is_array($logoRow) && isset($logoRow['value']) && $logoRow['value'] !== '') {
                $logoBasename = basename((string)$logoRow['value']);
                $logoPath = __DIR__ . '/assets/branding/' . $logoBasename;
                if (is_file($logoPath)) {
                    $logoInfo = @getimagesize($logoPath);
                    $logoMime = is_array($logoInfo) ? (string)($logoInfo['mime'] ?? '') : '';
                    $logoBytes = @file_get_contents($logoPath);
                    if (in_array($logoMime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true) && is_string($logoBytes) && $logoBytes !== '') {
                        $brandLogoUrl = 'data:' . $logoMime . ';base64,' . base64_encode($logoBytes);
                    }
                }
            }
            if (is_array($logoStateRow) && isset($logoStateRow['value'])) {
                $brandLogoState = (string)$logoStateRow['value'];
            }
            if (is_array($qrDisabledRow) && ((string)($qrDisabledRow['value'] ?? '0')) === '1') {
                $qrEnabled = false;
            }
        } catch (\Throwable $rxBrandLoadError) {

        }
    }
}


if (!in_array($brandLogoState, ['default', 'custom', 'initials'], true)) $brandLogoState = 'default';

// A custom logo file always wins the state; if it's missing, fall back to initials
// (never default) once the user has left the DEFAULT state.
if ($brandLogoUrl !== '') {
    $brandLogoState = 'custom';
} elseif ($brandLogoState === 'custom') {
    $brandLogoState = 'initials';
}

$brandIsDefaultLogo = ($brandLogoState === 'default');
if ($brandIsDefaultLogo) {
    $defaultLogoPath = __DIR__ . '/assets/branding/default_logo.jpg';
    $defaultLogoBytes = @file_get_contents($defaultLogoPath);
    if (is_string($defaultLogoBytes) && $defaultLogoBytes !== '') {
        $brandLogoUrl = 'data:image/jpeg;base64,' . base64_encode($defaultLogoBytes);
    } else {
        $brandLogoUrl = '';
        $brandLogoState = 'initials';
        $brandIsDefaultLogo = false;
    }
}

$version = $brandAppVersion;
$qrEnabled = $qrEnabled ?? true;

$hardR = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['_r'] ?? ''));
$versionSafe = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$version);
function fx_asset_v(string $absPath, string $versionSafe, string $hardR): string {
    $stamp = (string) @filemtime($absPath);
    return $versionSafe . '.' . $stamp . ($hardR !== '' ? '.' . $hardR : '');
}

$config = [
    'basename'    => $basename,
    'prefix'      => $prefix,
    'apiUrl'      => $apiUrl,
    'assetPrefix' => $assetPrefix,
    'version'     => $version,
    'qrEnabled'   => $qrEnabled,
    'forceDark'   => ($brandMode === 'dark'),
    'brand'       => [
        'name'            => $brandName,
        'mark'            => $brandMark,
        'title'           => $brandTitle,
        'logo_url'        => $brandLogoUrl,
        'avatar_state'    => $brandLogoState,
        'is_default_logo' => $brandIsDefaultLogo,
        'accent'          => $brandAccent,
        'mode'            => $brandMode,
    ],
];

$sdkLocal    = htmlspecialchars($assetPrefix . 'js/telegram-web-app.js?v=' . fx_asset_v(__DIR__ . '/js/telegram-web-app.js', $versionSafe, $hardR), ENT_QUOTES);
$sdkFallback = 'https://telegram.org/js/telegram-web-app.js';
$cssUrl      = htmlspecialchars($assetPrefix . 'assets/css/app.css?v=' . fx_asset_v(__DIR__ . '/assets/css/app.css', $versionSafe, $hardR), ENT_QUOTES);


$jsUrl       = htmlspecialchars($assetPrefix . 'assets/v1.0.5/app.js?v=' . fx_asset_v(__DIR__ . '/assets/v1.0.5/app.js', $versionSafe, $hardR), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="<?php echo $brandMode === 'light' ? '#eef2f8' : '#1b1b1d'; ?>" />
    <style>:root{color-scheme:<?php echo $brandMode; ?>}html,body{background-color:<?php echo $brandMode === 'light' ? '#eef2f8' : '#1b1b1d'; ?>}</style>
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>فاکسیما — Faoxima</title>
    <base href="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>" />


    <script>
    (function () {
        var THEMES = {
            gold:   { c: '#d4b878', b: '#e2c98c' },
            red:    { c: '#e57373', b: '#ef9a9a' },
            blue:   { c: '#64a8e8', b: '#82bdf3' },
            purple: { c: '#7c5cff', b: '#a98bff' },
            yellow: { c: '#f4d35e', b: '#f8e285' },
            green:  { c: '#7fc987', b: '#9bd5a3' },
            orange: { c: '#f0a868', b: '#f5be8b' }
        };
        function rgba(hex, a) {
            var m = /^#([0-9a-f]{6})$/i.exec(hex);
            if (!m) return hex;
            var n = parseInt(m[1], 16);
            return 'rgba(' + ((n>>16)&255) + ',' + ((n>>8)&255) + ',' + (n&255) + ',' + a + ')';
        }
        function darken(hex, f) {
            var m = /^#([0-9a-f]{6})$/i.exec(hex);
            if (!m) return hex;
            var n = parseInt(m[1], 16);
            return 'rgb(' + Math.round(((n>>16)&255)*f) + ',' + Math.round(((n>>8)&255)*f) + ',' + Math.round((n&255)*f) + ')';
        }
        try {
            function lighten(h,f){var m=/^#([0-9a-f]{6})$/i.exec(h);if(!m)return h;var n=parseInt(m[1],16),r=(n>>16)&255,g=(n>>8)&255,b=n&255;r=Math.round(r+(255-r)*f);g=Math.round(g+(255-g)*f);b=Math.round(b+(255-b)*f);return '#'+((1<<24)+(r<<16)+(g<<8)+b).toString(16).slice(1);}
            var GA = <?php echo $brandAccent !== '' ? ("'" . $brandAccent . "'") : 'null'; ?>;
            var key, t;
            if (GA) { key = 'custom'; t = { c: GA, b: lighten(GA, 0.28) }; }
            else { key = 'purple'; t = THEMES[key] || THEMES.gold; }
            function onAcc(h){var m=/^#([0-9a-f]{6})$/i.exec(h);if(!m)return '#ffffff';var n=parseInt(m[1],16);function lin(v){v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4);}var L=0.2126*lin((n>>16)&255)+0.7152*lin((n>>8)&255)+0.0722*lin(n&255);return L>0.45?'#14121d':'#ffffff';}
            var s = document.documentElement.style;
            s.setProperty('--gold', t.c);
            s.setProperty('--gold-bright', t.b);
            s.setProperty('--accent', t.c);
            s.setProperty('--accent-bright', t.b);
            s.setProperty('--on-accent', onAcc(t.c));
            s.setProperty('--gold-soft',   rgba(t.c, 0.10));
            s.setProperty('--gold-soft-2', rgba(t.c, 0.18));
            s.setProperty('--accent-soft',   rgba(t.c, 0.10));
            s.setProperty('--accent-soft-2', rgba(t.c, 0.18));
            s.setProperty('--accent-glow',   rgba(t.c, 0.40));
            s.setProperty('--accent-ink',    darken(t.c, 0.5));
            s.setProperty('--border',        rgba(t.c, 0.12));
            s.setProperty('--border-strong', rgba(t.c, 0.28));
            document.documentElement.dataset.color = key;

            var mode = 'dark';
            document.documentElement.dataset.theme = mode;
            window.__FAOXIMA_MODE__ = mode;
            var mc = document.querySelector('meta[name="theme-color"]');
            if (mc) mc.setAttribute('content', '#1b1b1d');
        } catch (e) {
        }
    })();
</script>

    <link rel="stylesheet" href="<?php echo $cssUrl; ?>" />

    
    <script src="<?php echo $sdkLocal; ?>
" onerror="this.onerror=null;var s=document.createElement('script');s.src='<?php echo $sdkFallback; ?>';document.head.appendChild(s);">
</script>
    <script>
      (function () {
        if (window.Telegram && window.Telegram.WebApp) return;


        try {
          document.write('<scr' + 'ipt src="<?php echo $sdkFallback; ?>"></scr' + 'ipt>');
        } catch (e) {  }
      })();
</script>
    <script>
      (function () {
        var w = window.Telegram && window.Telegram.WebApp;
        if (!w) return;
        try { w.ready(); } catch (e) {}
        try { w.expand(); } catch (e) {}
        try { w.setHeaderColor('#1b1b1d'); } catch (e) {}
        try { w.setBackgroundColor('#1b1b1d'); } catch (e) {}
      })();
</script>

    <script>
      window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

    <script>
    (function () {
        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = String(s == null ? '' : s);
            return d.innerHTML;
        }

        function showFallback(title, msg, stack) {
            var view = document.getElementById('view');
            if (!view) return;
            view.innerHTML =
                '<div class="empty">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;color:#f59e0b">' +
                '<path d="M10.3 3.86l-8.5 14.14a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3l-8.5-14.14a2 2 0 0 0-3.4 0z"/>' +
                '<path d="M12 9v4M12 17v.01"/></svg>' +
                '<h3>' + escapeHtml(title) + '</h3>' +
                '<p class="muted">' + escapeHtml(msg) + '</p>' +
                '<button class="btn btn-primary mt-md" onclick="try{sessionStorage.clear();}catch(e){}location.reload();">تلاش مجدد</button>' +
                (stack ? '<details class="mt-md" style="text-align:start">' +
                  '<summary class="muted mono" style="font-size:11px;cursor:pointer">جزئیات</summary>' +
                  '<pre class="codeblock" style="margin-top:8px;direction:ltr;text-align:start;font-size:11px">' + escapeHtml(stack) + '</pre>' +
                '</details>' : '') +
                '</div>';
        }

        window.addEventListener('error', function (e) {
            var where = (e.filename || '') + ':' + (e.lineno || '?');
            var msg = (e.error && e.error.message) || e.message || 'Script error';
            var stack = (e.error && e.error.stack) || '';
            if (!window.__FAOXIMA_APP_STARTED__) {
                showFallback('خطا در بارگذاری برنامه', msg + ' (' + where + ')', stack);
            }
        }, true);

        window.addEventListener('unhandledrejection', function (e) {
            var r = e.reason;
            var msg = (r && r.message) || String(r || 'Unhandled rejection');
            var stack = (r && r.stack) || '';
            if (!window.__FAOXIMA_APP_STARTED__) {
                showFallback('خطا در بارگذاری برنامه', msg, stack);
            }
        });


        setTimeout(function () {
            if (!window.__FAOXIMA_APP_STARTED__) {
                showFallback('بارگذاری برنامه ناموفق بود',
                    'یکی از فایل‌های JS بارگذاری نشد یا خطای پیوند ماژول داشت.', '');
            }
        }, 6000);
    })();
</script>
</head>
<body>
    <div id="app" class="app-shell">
        <script>
(function () {
    window.__hardReload = function () {
        var btn = document.getElementById('reload-btn');
        if (btn && btn.disabled) return;
        if (btn) {
            btn.disabled = true;
            btn.style.transition = 'opacity .2s';
            btn.style.opacity = '0.4';
            var svg = btn.querySelector('svg');
            if (svg) {
                svg.style.transformOrigin = 'center';
                svg.style.animation = 'hardReloadSpin .7s linear infinite';
            }
        }

        // Toast بازخورد
        try {
            var existing = document.getElementById('__hard-reload-toast');
            if (existing) existing.remove();
            var toast = document.createElement('div');
            toast.id = '__hard-reload-toast';
            toast.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);'
                + 'background:rgba(30,30,30,.92);color:#fff;font:13px/1.5 system-ui,sans-serif;'
                + 'padding:8px 18px;border-radius:20px;z-index:99999;pointer-events:none;'
                + 'box-shadow:0 2px 12px rgba(0,0,0,.35);white-space:nowrap;direction:rtl;'
                + 'transition:opacity .3s';
            toast.textContent = 'در حال بروزرسانی…';
            document.body.appendChild(toast);
        } catch (e) {}

        // inject spin keyframe once
        try {
            if (!document.getElementById('__hard-reload-style')) {
                var st = document.createElement('style');
                st.id = '__hard-reload-style';
                st.textContent = '@keyframes hardReloadSpin{to{transform:rotate(360deg)}}';
                document.head.appendChild(st);
            }
        } catch (e) {}

        var tasks = [];

        // پاکسازی sessionStorage
        try { sessionStorage.clear(); } catch (e) {}

        // پاکسازی localStorage با حفظ لاگین و تم
        try {
            var preserveKeys = ['faoxima.theme.accent', 'faoxima.theme.mode', 'faoxima.initData', 'faoxima.initDataUnsafe'];
            var keep = {};
            for (var i = 0; i < preserveKeys.length; i++) {
                try { keep[preserveKeys[i]] = localStorage.getItem(preserveKeys[i]); } catch (e) {}
            }
            try { localStorage.clear(); } catch (e) {}
            for (var k in keep) {
                if (keep[k] !== null) {
                    try { localStorage.setItem(k, keep[k]); } catch (e) {}
                }
            }
        } catch (e) {}

        // پاکسازی Cache API
        if (typeof caches !== 'undefined' && caches && typeof caches.keys === 'function') {
            tasks.push(
                caches.keys().then(function (keys) {
                    return Promise.all(keys.map(function (k) { return caches.delete(k); }));
                }).catch(function () {})
            );
        }

        // unregister Service Worker
        if (navigator.serviceWorker && typeof navigator.serviceWorker.getRegistrations === 'function') {
            tasks.push(
                navigator.serviceWorker.getRegistrations().then(function (regs) {
                    return Promise.all(regs.map(function (r) { return r.unregister(); }));
                }).catch(function () {})
            );
        }

        Promise.all(tasks).catch(function () {}).then(function () {
            try {
                // URL تمیز: حذف ?_r قدیمی، اضافه کردن یکی جدید
                var u = new URL(location.href);
                u.searchParams.delete('_r');
                u.searchParams.set('_r', Date.now().toString(36));
                location.replace(u.toString());
            } catch (e) {
                location.reload(true);
            }
        });

        // پشتیبان: اگر بعد از ۲ ثانیه هنوز reload نشده
        setTimeout(function () {
            try { location.reload(true); } catch (e) {}
        }, 2000);
    };
})();
</script>

        <main id="view" class="view" aria-live="polite">
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/loading</span>
                </header>
                <div class="card-body">
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                    <p class="muted center mono mt-md" style="font-size:11px">در حال آماده‌سازی…</p>
                </div>
            </article>
        </main>

        <nav class="tabbar">
            <a href="#/" data-route="/" class="tab"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg><span>خانه</span></a>
            <a href="#/services" data-route="/services" class="tab"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg><span>سرویس‌ها</span></a>
            <a href="#/buy" data-route="/buy" class="tab tab-cta"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg><span>خرید</span></a>
            <a href="#/account" data-route="/account" class="tab"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span>حساب</span></a>
        </nav>

        <div id="toast-host" class="toast-host" aria-live="polite" aria-atomic="true"></div>
    </div>

    <script type="module" src="<?php echo $jsUrl; ?>
">
</script>
</body>
</html>
