<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

if (empty($_SESSION['user']) || !is_string($_SESSION['user']) || $_SESSION['user'] === '') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تنظیمات ظاهر — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <script src="js/theme.js?v=flat50" defer></script>
    <style>
        .ap-card { max-width: 720px; }
        .ap-lead { color: var(--text-muted, #8a8a9a); font-size: 13px; margin: 2px 0 18px; }
        .ap-section-title { font-weight: 700; font-size: 15px; margin: 6px 0 4px; display: flex; align-items: center; gap: 8px; }
        .ap-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 10px; }
        @media (min-width: 560px) { .ap-grid { grid-template-columns: repeat(3, 1fr); } }
        .ap-swatch {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 14px 16px; border-radius: 16px;
            background: var(--surface-2, #1c1917); border: 1px solid var(--border-soft, #2a2a35);
            cursor: pointer; color: var(--text-main); font-weight: 600; font-size: 14px;
            transition: transform .15s ease, border-color .15s ease, box-shadow .18s ease;
            -webkit-user-select: none; user-select: none; -webkit-touch-callout: none;
        }
        .ap-swatch:hover { transform: translateY(-2px); border-color: var(--accent); }
        .ap-swatch.active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-soft); }
        .ap-dot { width: 26px; height: 26px; border-radius: 50%; flex: 0 0 auto; box-shadow: inset 0 0 0 2px rgba(255,255,255,0.16); }
        .ap-custom { margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--border-soft, #2a2a35); }
        .ap-hexrow { display: flex; align-items: center; gap: 12px; margin-top: 12px; }
        .ap-hexrow input[type="text"] {
            flex: 1; direction: ltr; text-align: left; font-family: 'JetBrains Mono', monospace;
            padding: 12px 14px; border-radius: 12px; background: var(--surface-1);
            border: 1px solid var(--border-soft, #2a2a35); color: var(--text-main); font-size: 15px;
        }
        .ap-hexrow input[type="text"]:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .ap-hexrow input[type="color"] { width: 48px; height: 48px; border: none; background: none; padding: 0; border-radius: 12px; cursor: pointer; }
        .ap-preview { width: 48px; height: 48px; border-radius: 12px; background: var(--accent); border: 1px solid rgba(255,255,255,0.16); flex: 0 0 auto; }
        .ap-note { color: var(--text-muted, #8a8a9a); font-size: 12.5px; margin-top: 10px; }
        .ap-actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-appearance">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <svg class="svg-icon svg-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path fill="currentColor" stroke="none" d="M17.8 2.2c-1-1-2.6-1-3.6 0L12.4 4l-.7-.7c-.4-.4-1-.4-1.4 0l-.8.7c-.4.4-.4 1 0 1.4l5 5c.4.4 1 .4 1.4 0l.7-.7c.4-.4.4-1 0-1.4l-.6-.7 1.8-1.8c1-1 1-2.6 0-3.6zM4.4 12c-2.2 2.2-.9 3.2-2.9 5.8l.7.7c2.6-2 3.6-.7 5.8-2.9l5.1-5.1-3.6-3.6L4.4 12z"/></svg>
                        تنظیمات ظاهر
                    </div>
                    <div class="page-head__sub">رنگ اصلی پنل را انتخاب کنید</div>
                </div>
                <div class="chip-row">
                    <a href="index.php" class="chip"><?php echo icon('home', 'svg-icon svg-sm'); ?><span>داشبورد</span></a>
                </div>
            </div>

            <div class="card ap-card">
                <div class="ap-section-title">حالت نمایش</div>
                <div class="ap-lead">حالت روشن یا تیره‌ی پنل را انتخاب کنید.</div>
                <div class="ap-modes" id="ap-modes" role="group" aria-label="حالت نمایش">
                    <button type="button" class="ap-mode" data-theme-mode="dark">حالت شب</button>
                    <button type="button" class="ap-mode" data-theme-mode="light">حالت روز</button>
                </div>

                <div class="ap-section-title">رنگ پنل</div>
                <div class="ap-lead">یک رنگ از پیش‌فرض‌ها انتخاب کنید یا کد رنگ دلخواه‌تان را وارد کنید. رنگ بلافاصله اعمال می‌شود.</div>

                <div class="ap-grid" id="ap-grid">
                    <button type="button" class="ap-swatch" data-color="blue"><span>آبی</span><span class="ap-dot" style="background:#3b82f6"></span></button>
                    <button type="button" class="ap-swatch" data-color="purple"><span>بنفش</span><span class="ap-dot" style="background:#8B5CF6"></span></button>
                    <button type="button" class="ap-swatch" data-color="red"><span>قرمز</span><span class="ap-dot" style="background:#ef4444"></span></button>
                    <button type="button" class="ap-swatch" data-color="green"><span>سبز</span><span class="ap-dot" style="background:#22c55e"></span></button>
                    <button type="button" class="ap-swatch" data-color="yellow"><span>زرد</span><span class="ap-dot" style="background:#facc15"></span></button>
                    <button type="button" class="ap-swatch" data-color="orange"><span>نارنجی</span><span class="ap-dot" style="background:#f97316"></span></button>
                </div>

                <div class="ap-custom">
                    <div class="ap-section-title">رنگ دلخواه</div>
                    <div class="ap-lead" style="margin-bottom:0">کد رنگ شش‌رقمی (مثلاً ‎#eaedf8). متن و آیکون‌ها بسته به روشن یا تیره‌بودن رنگ، خودکار سفید یا مشکی می‌شوند.</div>
                    <div class="ap-hexrow">
                        <span class="ap-preview" id="ap-preview"></span>
                        <input type="text" id="ap-hex" placeholder="#eaedf8" maxlength="7" dir="ltr" spellcheck="false" autocomplete="off">
                        <input type="color" id="ap-native" aria-label="انتخابگر رنگ">
                    </div>
                    <div class="ap-note">این رنگ فقط روی همین مرورگر ذخیره می‌شود (تنظیم شخصی).</div>
                </div>

                <div class="ap-actions">
                    <button type="button" class="btn btn-outline" id="ap-reset">بازنشانی به پیش‌فرض</button>
                </div>
            </div>

        </div>
    </section>
</section>

<script>
(function () {
    var PRESET = { red:'#ef4444', blue:'#3b82f6', purple:'#8B5CF6', yellow:'#facc15', orange:'#f97316', green:'#22c55e' };
    var DEFAULT_COLOR = 'purple';
    function norm(v) { var m = /^#?([0-9a-f]{6})$/i.exec(String(v == null ? '' : v).trim()); return m ? ('#' + m[1].toLowerCase()) : null; }

    function init() {
        var T = window.FaoximaTheme;
        var hexI = document.getElementById('ap-hex');
        var nat  = document.getElementById('ap-native');
        var prev = document.getElementById('ap-preview');
        var reset = document.getElementById('ap-reset');
        var modes = document.querySelectorAll('.ap-mode');

        function setPreview(h) { if (prev) prev.style.background = h; }
        function syncFields(h) { if (hexI) hexI.value = h; if (nat) { try { nat.value = h; } catch (e) {} } setPreview(h); }
        function apply(v) { if (T && T.setColor) T.setColor(v); }
        function syncModes() {
            var t = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            for (var i = 0; i < modes.length; i++) {
                modes[i].classList.toggle('active', modes[i].getAttribute('data-theme-mode') === t);
            }
        }

        var cur = DEFAULT_COLOR;
        try { cur = localStorage.getItem('faoxima_color') || DEFAULT_COLOR; } catch (e) {}
        var curHex = PRESET[cur] || norm(cur) || PRESET[DEFAULT_COLOR];
        syncFields(curHex);

        var tiles = document.querySelectorAll('.ap-swatch');
        for (var i = 0; i < tiles.length; i++) {
            (function (t) {
                t.addEventListener('click', function () {
                    var c = t.getAttribute('data-color');
                    apply(c);
                    syncFields(PRESET[c] || curHex);
                });
            })(tiles[i]);
        }

        for (var m = 0; m < modes.length; m++) {
            (function (b) {
                b.addEventListener('click', function () {
                    var mode = b.getAttribute('data-theme-mode');
                    if (T && T.setTheme) T.setTheme(mode);
                    syncModes();
                });
            })(modes[m]);
        }
        document.addEventListener('faoxima:themechange', syncModes);
        syncModes();

        if (hexI) hexI.addEventListener('input', function () {
            var h = norm(hexI.value); if (!h) return;
            apply(h); if (nat) { try { nat.value = h; } catch (e) {} } setPreview(h);
        });
        if (nat) nat.addEventListener('input', function () {
            var h = norm(nat.value); if (!h) return;
            apply(h); if (hexI) hexI.value = h; setPreview(h);
        });
        if (reset) reset.addEventListener('click', function () {
            apply(DEFAULT_COLOR); syncFields(PRESET[DEFAULT_COLOR]);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

</body>
</html>
