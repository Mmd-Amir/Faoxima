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

$rootForApi = $basename === '/' ? '/' : rtrim(dirname($basename), '/');
if ($rootForApi === '' || $rootForApi === '.') {
    $rootForApi = '/';
}
$apiPath = $rootForApi === '/' ? '/api' : $rootForApi . '/api';

$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
if (is_string($forwardedProto) && $forwardedProto !== '') {
    $scheme = explode(',', $forwardedProto)[0];
} elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
    $scheme = $_SERVER['REQUEST_SCHEME'];
} else {
    $https = $_SERVER['HTTPS'] ?? '';
    $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiUrl = rtrim($scheme . '://' . $host, '/') . $apiPath;

$brandAppVersion = trim((string)@file_get_contents(__DIR__ . '/version')) ?: '0.0.1';


$brandName = 'Faoxima';
$brandMark = 'M';
$brandLogoUrl = '';


$defaultLogoPath = __DIR__ . '/assets/branding/default_logo.jpg';
if (is_file($defaultLogoPath)) {
    $brandLogoUrl = $assetPrefix . 'assets/branding/default_logo.jpg?v=' . @filemtime($defaultLogoPath);
}

if (is_file(__DIR__ . '/../config.php') && is_file(__DIR__ . '/../function.php')) {
    @require_once __DIR__ . '/../config.php';
    @require_once __DIR__ . '/../function.php';
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && function_exists('select')) {
        try {
            $nameRow = select('shopSetting', '*', 'Namevalue', 'brand_name', 'select');
            $markRow = select('shopSetting', '*', 'Namevalue', 'brand_mark', 'select');
            $logoRow = select('shopSetting', '*', 'Namevalue', 'brand_logo', 'select');
            if (is_array($nameRow) && isset($nameRow['value']) && $nameRow['value'] !== '') {
                $brandName = (string)$nameRow['value'];
            }
            if (is_array($markRow) && isset($markRow['value']) && $markRow['value'] !== '') {
                $brandMark = (string)$markRow['value'];
            }
            if (is_array($logoRow) && isset($logoRow['value']) && $logoRow['value'] !== '') {
                $logoBasename = basename((string)$logoRow['value']);
                $logoPath = __DIR__ . '/assets/branding/' . $logoBasename;
                if (is_file($logoPath)) {
                    $brandLogoUrl = $assetPrefix . 'assets/branding/' . $logoBasename . '?v=' . @filemtime($logoPath);
                }
            }
        } catch (\Throwable $rxBrandLoadError) {

        }
    }
}


$version = $brandAppVersion;

$config = [
    'basename'    => $basename,
    'prefix'      => $prefix,
    'apiUrl'      => $apiUrl,
    'assetPrefix' => $assetPrefix,
    'version'     => $version,
    'brand'       => [
        'name'     => $brandName,
        'mark'     => $brandMark,
        'logo_url' => $brandLogoUrl,
    ],
];


$buildStamp = (string) @filemtime(__FILE__);
if (!isset($version) || !is_string($version) || $version === '') {
    $version = $brandAppVersion ?? '0.0.1';
}
$cacheBust = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$version) . '.' . $buildStamp;

$sdkLocal    = htmlspecialchars($assetPrefix . 'js/telegram-web-app.js?v=' . $cacheBust, ENT_QUOTES);
$sdkFallback = 'https://telegram.org/js/telegram-web-app.js';
$cssUrl      = htmlspecialchars($assetPrefix . 'assets/css/app.css?v=' . $cacheBust, ENT_QUOTES);


$jsUrl       = htmlspecialchars($assetPrefix . 'assets/v0.0.1/app.js?v=' . $cacheBust, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#0a0907" />
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>فاکسیما — Faoxima</title>
    <base href="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>" />

    
    <script>
    (function () {
        var THEMES = {
            gold:   { c: '
            red:    { c: '#e57373', b: '#ef9a9a' },
            blue:   { c: '#64a8e8', b: '#82bdf3' },
            purple: { c: '#b48def', b: '#c8a8f3' },
            yellow: { c: '#f4d35e', b: '#f8e285' },
            green:  { c: '#7fc987', b: '#9bd5a3' },
            orange: { c: '#f0a868', b: '#f5be8b' }
        };
        function rgba(hex, a) {
            var m = /^
            if (!m) return hex;
            var n = parseInt(m[1], 16);
            return 'rgba(' + ((n>>16)&255) + ',' + ((n>>8)&255) + ',' + (n&255) + ',' + a + ')';
        }
        try {
            var key = localStorage.getItem('faoxima.theme.accent') || 'gold';
            var t = THEMES[key] || THEMES.gold;
            var s = document.documentElement.style;
            s.setProperty('--gold', t.c);
            s.setProperty('--gold-bright', t.b);
            s.setProperty('--accent', t.c);
            s.setProperty('--accent-bright', t.b);
            s.setProperty('--gold-soft',   rgba(t.c, 0.10));
            s.setProperty('--gold-soft-2', rgba(t.c, 0.18));
            s.setProperty('--accent-soft',   rgba(t.c, 0.10));
            s.setProperty('--accent-soft-2', rgba(t.c, 0.18));
            s.setProperty('--border',        rgba(t.c, 0.12));
            s.setProperty('--border-strong', rgba(t.c, 0.28));
            document.documentElement.dataset.theme = key;
        } catch (e) {  }
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
      window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

    
    <script>
    (function () {
        var posted = {};
        function postLog(level, msg, where, stack) {
            var key = level + '|' + msg + '|' + (where || '');
            if (posted[key]) return;
            posted[key] = true;
            try {
                var apiUrl = (window.__APP_CONFIG__ && window.__APP_CONFIG__.apiUrl) || '';
                if (!apiUrl) return;
                fetch(apiUrl + '/clientlog.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        level: level, msg: String(msg || ''), where: String(where || ''),
                        stack: String(stack || ''),
                        diag: { href: location.href, ua: navigator.userAgent,
                                bootStarted: !!window.__FAOXIMA_APP_STARTED__ }
                    }),
                    keepalive: true
                }).catch(function () {});
            } catch (e) {  }
        }

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
            postLog('error', msg, where, stack);
            if (!window.__FAOXIMA_APP_STARTED__) {
                showFallback('خطا در بارگذاری برنامه', msg + ' (' + where + ')', stack);
            }
        }, true);

        window.addEventListener('unhandledrejection', function (e) {
            var r = e.reason;
            var msg = (r && r.message) || String(r || 'Unhandled rejection');
            var stack = (r && r.stack) || '';
            postLog('error', msg, 'unhandledrejection', stack);
            if (!window.__FAOXIMA_APP_STARTED__) {
                showFallback('خطا در بارگذاری برنامه', msg, stack);
            }
        });


        setTimeout(function () {
            if (!window.__FAOXIMA_APP_STARTED__) {
                postLog('error',
                    'Module entry never executed within 6s — likely a JS module 404 or static-import error.',
                    'watchdog', '');
                showFallback('بارگذاری برنامه ناموفق بود',
                    'یکی از فایل‌های JS بارگذاری نشد یا خطای پیوند ماژول داشت.', '');
            }
        }, 6000);
    })();
</script>
</head>
<body>
    <div id="app" class="app-shell">
        <header class="app-header">
            <a href="#/" class="brand" data-route="/">
                <span class="brand-mark" aria-hidden="true"<?php echo $brandLogoUrl !== '' ? ' style="background:transparent;padding:0;overflow:hidden"' : ''; ?>><?php
                    if ($brandLogoUrl !== '') {
                        echo '<img src="' . htmlspecialchars($brandLogoUrl, ENT_QUOTES) . '" alt="logo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" />';
                    } else {
                        echo htmlspecialchars($brandMark, ENT_QUOTES);
                    }
                ?></span>
                <span class="brand-name"><?php echo htmlspecialchars($brandName, ENT_QUOTES); ?></span>
            </a>
            <button id="reload-btn" class="icon-btn" aria-label="Reload" title="Reload (پاکسازی کش)"
                    onclick="window.__hardReload && window.__hardReload();">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
            </button>
<script>
(function () {
    window.__hardReload = function () {
        var btn = document.getElementById('reload-btn');
        if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }

        var tasks = [];

        try { sessionStorage.clear(); } catch (e) {}
        try {
            var preserveKeys = ['faoxima.theme.accent', 'faoxima.initData'];
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

        if (typeof caches !== 'undefined' && caches && typeof caches.keys === 'function') {
            tasks.push(
                caches.keys().then(function (keys) {
                    return Promise.all(keys.map(function (k) { return caches.delete(k); }));
                }).catch(function () {})
            );
        }

        if (navigator.serviceWorker && typeof navigator.serviceWorker.getRegistrations === 'function') {
            tasks.push(
                navigator.serviceWorker.getRegistrations().then(function (regs) {
                    return Promise.all(regs.map(function (r) { return r.unregister(); }));
                }).catch(function () {})
            );
        }

        Promise.all(tasks).catch(function () {}).then(function () {
            try {
                var u = new URL(location.href);

                u.searchParams.set('_r', Date.now().toString(36));
                location.replace(u.toString());
            } catch (e) {
                location.reload();
            }
        });

        setTimeout(function () {
            try { location.reload(); } catch (e) {}
        }, 1500);
    };
})();
</script>
        </header>

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


