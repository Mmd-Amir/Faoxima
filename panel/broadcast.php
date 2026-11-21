<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'broadcast.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/csrf.php';

if (\Faoxima\Admin\Web\Pages\BroadcastRequest::handle(
        _csrf: $_csrf,
        initialCount: $initialCount,
        pdo: $pdo ?? null,
        query: $query,
        stmt: $stmt
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
    <title>پیام همگانی | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <script src="js/theme.js?v=dark62" defer>

</script>
    <style>
        .bc-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .bc-grid { grid-template-columns: 1fr; }
        }
        .bc-msg {
            width: 100%;
            min-height: 220px;
            padding: 14px;
            border-radius: var(--radius-md);
            background: var(--surface-3);
            border: 1px solid var(--border-mid);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            line-height: 1.9;
            resize: vertical;
        }
        .bc-msg:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }

        .bc-stat {
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 10px;
        }
        .bc-stat__label { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .bc-stat__value { font-size: 24px; font-weight: 700; color: var(--accent); }

        .progress-wrap { margin-top: 20px; display: none; }
        .progress-wrap.active { display: block; }
        .progress-bar {
            height: 10px;
            background: var(--surface-3);
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid var(--border-mid);
        }
        .progress-bar__fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent-mid));
            width: 0%;
            transition: width 0.3s ease;
        }
        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-settings-theme">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('megaphone', 'svg-icon svg-lg'); ?>
                        پیام همگانی
                    </div>
                    <div class="page-head__sub">ارسال پیام به یک گروه از کاربران ربات</div>
                </div>
            </div>

            <div class="bc-grid">
                <div class="card">
                    <div class="card__head">
                        <div class="card__title">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('text', 'svg-icon svg-md'); ?>
                            <span>متن پیام</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">پیام شما (پشتیبانی از HTML تلگرام)</label>
                        <textarea id="bc-message" class="bc-msg" placeholder="پیام خود را اینجا بنویسید…&#10;&#10;مثال:&#10;<b>تخفیف ویژه!</b>&#10;امروز تمام محصولات با کد <code>OFF20</code> تخفیف دارند."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوع فرمت متن</label>
                        <select id="bc-parse" class="form-control" style="width: 200px;">
                            <option value="HTML">HTML</option>
                            <option value="Markdown">Markdown</option>
                            <option value="plain">متن ساده</option>
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-info', 'svg-icon svg-sm'); ?>
                        ارسال در دسته‌های ۱۵تایی انجام می‌شود تا با محدودیت تلگرام برخورد نکنیم. کاربرانی که ربات را بلاک کرده‌اند خودکار رد می‌شوند.
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="card__head">
                            <div class="card__title">
                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('users', 'svg-icon svg-md'); ?>
                                <span>مخاطبان</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">گروه هدف</label>
                            <select id="bc-target" class="form-control">
                                <option value="all_users" selected>تمامی کاربران فعال</option>
                                <option value="active">فقط کاربران فعال (همان بالا)</option>
                                <option value="agents">فقط نمایندگان</option>
                            </select>
                        </div>
                        <div class="bc-stat">
                            <div class="bc-stat__label">تعداد گیرنده</div>
                            <div class="bc-stat__value" id="bc-count"><?php echo number_format($initialCount); ?></div>
                        </div>
                        <button type="button" class="btn btn-primary btn-block" id="bc-send">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('paper-plane', 'svg-icon svg-sm'); ?>
                            <span>شروع ارسال</span>
                        </button>
                    </div>

                    <div class="progress-wrap" id="bc-progress">
                        <div class="card">
                            <div class="card__head">
                                <div class="card__title">
                                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('chart-line', 'svg-icon svg-md'); ?>
                                    <span>پیشرفت</span>
                                </div>
                            </div>
                            <div class="progress-bar"><div class="progress-bar__fill" id="bc-fill"></div></div>
                            <div class="progress-info">
                                <span><span id="bc-pdone">۰</span> از <span id="bc-ptotal">۰</span></span>
                                <span><span id="bc-pct">۰</span>٪</span>
                            </div>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--border-soft); display: flex; justify-content: space-between; font-size: 13px;">
                                <span class="progress-summary__ok">✓ موفق: <b id="bc-sent">۰</b></span>
                                <span class="progress-summary__fail">✗ ناموفق: <b id="bc-failed">۰</b></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>
</section>

<script>
var CSRF_TOKEN = <?php echo json_encode($_csrf, JSON_UNESCAPED_SLASHES); ?>;
(function () {
    var elTarget   = document.getElementById('bc-target');
    var elCount    = document.getElementById('bc-count');
    var elMessage  = document.getElementById('bc-message');
    var elParse    = document.getElementById('bc-parse');
    var elSend     = document.getElementById('bc-send');
    var elProgress = document.getElementById('bc-progress');
    var elFill     = document.getElementById('bc-fill');
    var elPdone    = document.getElementById('bc-pdone');
    var elPtotal   = document.getElementById('bc-ptotal');
    var elPct      = document.getElementById('bc-pct');
    var elSent     = document.getElementById('bc-sent');
    var elFailed   = document.getElementById('bc-failed');

    function persianNum(n){ return String(n).replace(/\d/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); }
    function setCount(n) { elCount.textContent = persianNum(n.toLocaleString('en-US')); }

    function refreshCount() {
        var url = 'broadcast.php?ajax=count&target=' + encodeURIComponent(elTarget.value);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.ok) setCount(j.count); });
    }
    elTarget.addEventListener('change', refreshCount);

    elSend.addEventListener('click', function () {
        var msg = elMessage.value.trim();
        if (msg === '') {
            alert('متن پیام نمی‌تواند خالی باشد.');
            elMessage.focus();
            return;
        }
        if (!confirm('پیام به ' + elCount.textContent + ' کاربر ارسال شود؟')) return;


        elProgress.classList.add('active');
        elSend.disabled = true;
        elSend.innerHTML = 'در حال ارسال…';

        var total = parseInt(elCount.textContent.replace(/[^\d]/g, '0').replace(/[۰-۹]/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }), 10) || 0;

        fetch('broadcast.php?ajax=count&target=' + encodeURIComponent(elTarget.value), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) throw new Error(j.error || 'count_failed');
                runJob(j.count);
            })
            .catch(function (err) {
                alert('خطا در محاسبه تعداد: ' + err.message);
                resetSendBtn();
            });
    });

    function resetSendBtn() {
        elSend.disabled = false;
        elSend.innerHTML = '<svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> <span>شروع ارسال</span>';
    }

    function runJob(total) {
        elPtotal.textContent = persianNum(total);
        var offset = 0;
        var sent = 0;
        var failed = 0;

        function tick() {
            var fd = new FormData();
            fd.append('message', elMessage.value);
            fd.append('parse', elParse.value);
            fd.append('_csrf', CSRF_TOKEN);
            var url = 'broadcast.php?ajax=send_batch&target=' + encodeURIComponent(elTarget.value)
                    + '&offset=' + offset + '&batch=15';
            fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.ok) throw new Error(j.error || 'send_failed');
                    sent   += j.sent;
                    failed += j.failed;
                    offset  = j.next_off;
                    var pct = total > 0 ? Math.min(100, Math.round((offset / total) * 100)) : 100;
                    elFill.style.width = pct + '%';
                    elPdone.textContent = persianNum(offset);
                    elPct.textContent   = persianNum(pct);
                    elSent.textContent  = persianNum(sent);
                    elFailed.textContent= persianNum(failed);
                    if (!j.done && offset < total) {
                        setTimeout(tick, 600);
                    } else {
                        elSend.innerHTML = '✓ ارسال انجام شد';
                        alert('عملیات تمام شد.\nموفق: ' + sent + '\nناموفق: ' + failed);
                        setTimeout(resetSendBtn, 2000);
                    }
                })
                .catch(function (err) {
                    alert('خطا در ارسال: ' + err.message);
                    resetSendBtn();
                });
        }
        tick();
    }
})();
</script>
</body>
</html>


