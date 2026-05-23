<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

if (extension_loaded('zlib') && empty(ini_get('zlib.output_compression'))) {
    @ini_set('zlib.output_compression', '1');
}

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}


function faoxima_target_sql(string $target): array {
    switch ($target) {
        case 'active':
            return [" WHERE (User_Status IS NULL OR User_Status != 'block')", []];
        case 'agents':
            return [" WHERE agentstatus = 'agent' OR agentstatus = 'agent2'", []];
        case 'all_users':
        default:

            return [" WHERE (User_Status IS NULL OR User_Status != 'block')", []];
    }
}


if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $ajax = (string)$_GET['ajax'];

    if ($ajax === 'count') {
        $target = (string)($_GET['target'] ?? 'all_users');
        [$whereSql, $params] = faoxima_target_sql($target);
        try {
            $sql = "SELECT COUNT(*) FROM user" . $whereSql;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($ajax === 'send_batch') {
        @set_time_limit(20);
        $target  = (string)($_GET['target'] ?? 'all_users');
        $offset  = max(0, (int)($_GET['offset'] ?? 0));
        $batch   = max(1, min(30, (int)($_GET['batch'] ?? 15)));
        $message = (string)($_POST['message'] ?? '');
        $parse   = (string)($_POST['parse'] ?? 'HTML');
        if ($parse !== 'HTML' && $parse !== 'Markdown' && $parse !== 'plain') $parse = 'HTML';
        $parseMode = $parse === 'plain' ? '' : $parse;


        if (!in_array($target, ['all_users', 'active', 'agents'], true)) {
            echo json_encode(['ok' => false, 'error' => 'target_invalid']);
            exit;
        }

        $trim = trim($message);
        if ($trim === '') {
            echo json_encode(['ok' => false, 'error' => 'message_empty']);
            exit;
        }

        if (mb_strlen($message, 'UTF-8') > 4000) {
            echo json_encode(['ok' => false, 'error' => 'message_too_long']);
            exit;
        }

        [$whereSql, $params] = faoxima_target_sql($target);
        $sql = "SELECT id FROM user" . $whereSql . " ORDER BY id ASC LIMIT :lim OFFSET :off";
        try {
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':lim', $batch, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'query: ' . $e->getMessage()]);
            exit;
        }

        $sent = 0; $failed = 0;
        foreach ($rows as $r) {
            $uid = (int)$r['id'];
            if ($uid <= 0) continue;
            try {
                $res = sendmessage($uid, $message, null, $parseMode);
                if (is_array($res) && !empty($res['ok'])) $sent++;
                else $failed++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        echo json_encode([
            'ok'       => true,
            'sent'     => $sent,
            'failed'   => $failed,
            'next_off' => $offset + count($rows),
            'done'     => count($rows) < $batch,
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown_ajax']);
    exit;
}


$initialCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM user WHERE (User_Status IS NULL OR User_Status != 'block')");
    $initialCount = (int)$stmt->fetchColumn();
} catch (\Throwable $e) {

}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>پیام همگانی | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer>

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
            border-radius: 12px;
            background: var(--surface-3);
            border: 1px solid var(--border-mid);
            color: var(--text-main);
            font-family: 'Arad', sans-serif;
            font-size: 14px;
            line-height: 1.9;
            resize: vertical;
        }
        .bc-msg:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }

        .bc-stat {
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
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
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('megaphone', 'svg-icon svg-lg'); ?>
                        پیام همگانی
                    </div>
                    <div class="page-head__sub">ارسال پیام به یک گروه از کاربران ربات</div>
                </div>
            </div>

            <div class="bc-grid">
                <div class="card">
                    <div class="card__head">
                        <div class="card__title">
                            <?php echo icon('text', 'svg-icon svg-md'); ?>
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

                    <div class="alert" style="background:var(--accent-soft); border:1px solid var(--accent-mid); color:var(--text-main); padding:10px 14px; border-radius:8px; font-size:12.5px; line-height:1.8;">
                        <?php echo icon('circle-info', 'svg-icon svg-sm'); ?>
                        ارسال در دسته‌های ۱۵تایی انجام می‌شود تا با محدودیت تلگرام برخورد نکنیم. کاربرانی که ربات را بلاک کرده‌اند خودکار رد می‌شوند.
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="card__head">
                            <div class="card__title">
                                <?php echo icon('users', 'svg-icon svg-md'); ?>
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
                            <?php echo icon('paper-plane', 'svg-icon svg-sm'); ?>
                            <span>شروع ارسال</span>
                        </button>
                    </div>

                    <div class="progress-wrap" id="bc-progress">
                        <div class="card">
                            <div class="card__head">
                                <div class="card__title">
                                    <?php echo icon('chart-line', 'svg-icon svg-md'); ?>
                                    <span>پیشرفت</span>
                                </div>
                            </div>
                            <div class="progress-bar"><div class="progress-bar__fill" id="bc-fill"></div></div>
                            <div class="progress-info">
                                <span><span id="bc-pdone">۰</span> از <span id="bc-ptotal">۰</span></span>
                                <span><span id="bc-pct">۰</span>٪</span>
                            </div>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--border-soft); display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: var(--color-success);">✓ موفق: <b id="bc-sent">۰</b></span>
                                <span style="color: var(--color-danger);">✗ ناموفق: <b id="bc-failed">۰</b></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>
</section>

<script>
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


