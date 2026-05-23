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
require_once __DIR__ . '/lib/icons.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}


function faoxima_text_group(string $key): string {
    $k = strtolower($key);
    if ($k === 'text_start' || $k === 'text_roll' || $k === 'text_fq' || $k === 'text_dec_fq')
        return 'متن‌های شروع و راهنما';
    if (strpos($k, 'text_sell') === 0 || strpos($k, 'text_extend') === 0 || strpos($k, 'text_tariff') === 0)
        return 'متن دکمه‌های خرید و تمدید';
    if (strpos($k, 'text_account') === 0 || strpos($k, 'text_purchased') === 0 || strpos($k, 'accountwallet') === 0)
        return 'متن دکمه‌های حساب کاربری';
    if (strpos($k, 'text_affiliates') === 0 || strpos($k, 'agent') !== false)
        return 'متن‌های نمایندگی و زیرمجموعه';
    if (strpos($k, 'text_support') === 0 || strpos($k, 'text_help') === 0 || strpos($k, 'text_channel') === 0)
        return 'متن‌های پشتیبانی و آموزش';
    if (strpos($k, 'text_wheel') === 0 || strpos($k, 'text_dice') === 0)
        return 'متن‌های گردونه شانس';
    if (strpos($k, 'text_usertest') === 0 || strpos($k, 'crontest') === 0)
        return 'متن‌های اکانت تست';
    if (strpos($k, 'cart') !== false || strpos($k, 'payment') !== false || strpos($k, 'pay') !== false || strpos($k, 'zarinp') !== false)
        return 'متن‌های پرداخت';
    if (strpos($k, 'textafter') === 0 || strpos($k, 'textmanual') === 0 || strpos($k, 'textselectlocation') === 0)
        return 'متن‌های بعد از خرید';
    if (strpos($k, 'text_discount') === 0 || strpos($k, 'text_add_balance') === 0)
        return 'تخفیف و کیف پول';
    return 'سایر';
}

$flash = ['ok' => '', 'err' => ''];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'save') {
        $newValues = $_POST['t'] ?? [];
        if (!is_array($newValues)) $newValues = [];


        $cur = [];
        $r = $pdo->query("SELECT id_text, text FROM textbot");
        if ($r) {
            foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cur[(string)$row['id_text']] = (string)$row['text'];
            }
        }

        $updated = 0;
        foreach ($newValues as $key => $val) {
            $key = (string)$key;
            $val = (string)$val;
            if (!isset($cur[$key])) continue;
            if ($cur[$key] === $val) continue;
            try {
                $upd = $pdo->prepare("UPDATE textbot SET text = :v WHERE id_text = :k");
                $upd->execute([':v' => $val, ':k' => $key]);
                $updated++;
            } catch (\Throwable $e) {
                $flash['err'] = $e->getMessage();
                error_log('[panel/textbot] update failed for ' . $key . ': ' . $e->getMessage());
            }
        }
        header('Location: textbot.php?saved=' . $updated);
        exit;
    }
    elseif ($action === 'add') {
        $key = trim((string)($_POST['new_key'] ?? ''));
        $val = (string)($_POST['new_val'] ?? '');


        $errors = [];
        if ($key === '') {
            $errors[] = 'کلید نمی‌تواند خالی باشد.';
        } elseif (!preg_match('/^[A-Za-z0-9_ـ]{2,100}$/u', $key)) {
            $errors[] = 'کلید فقط می‌تواند شامل حروف، عدد و آندرلاین باشد (۲ تا ۱۰۰ کاراکتر).';
        } elseif (mb_strlen($val) > 4000) {
            $errors[] = 'متن بیش از حد طولانی است (حداکثر ۴۰۰۰ کاراکتر).';
        }

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
        } else {
            try {
                $ins = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (:k, :v)
                                      ON DUPLICATE KEY UPDATE text = VALUES(text)");
                $ins->execute([':k' => $key, ':v' => $val]);
                header('Location: textbot.php?added=' . urlencode($key));
                exit;
            } catch (\Throwable $e) {
                $flash['err'] = 'افزودن ناموفق: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'delete') {
        $key = (string)($_POST['key'] ?? '');
        if ($key !== '') {
            try {
                $d = $pdo->prepare("DELETE FROM textbot WHERE id_text = :k");
                $d->execute([':k' => $key]);
                header('Location: textbot.php?deleted=' . urlencode($key));
                exit;
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    }
}


$rows = [];
try {
    $r = $pdo->query("SELECT id_text, text FROM textbot ORDER BY id_text ASC");
    if ($r) $rows = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $flash['err'] = 'بارگذاری متن‌ها ناموفق: ' . $e->getMessage();
}


$grouped = [];
foreach ($rows as $row) {
    $g = faoxima_text_group((string)$row['id_text']);
    if (!isset($grouped[$g])) $grouped[$g] = [];
    $grouped[$g][] = $row;
}

$preferredOrder = [
    'متن‌های شروع و راهنما',
    'متن دکمه‌های خرید و تمدید',
    'متن دکمه‌های حساب کاربری',
    'متن‌های اکانت تست',
    'متن‌های نمایندگی و زیرمجموعه',
    'تخفیف و کیف پول',
    'متن‌های پرداخت',
    'متن‌های بعد از خرید',
    'متن‌های پشتیبانی و آموزش',
    'متن‌های گردونه شانس',
    'سایر',
];
$orderedGrouped = [];
foreach ($preferredOrder as $g) {
    if (isset($grouped[$g])) { $orderedGrouped[$g] = $grouped[$g]; unset($grouped[$g]); }
}
foreach ($grouped as $g => $items) $orderedGrouped[$g] = $items;

$savedNum = isset($_GET['saved']) ? (int)$_GET['saved'] : -1;
$addedKey = isset($_GET['added']) ? (string)$_GET['added'] : '';
$delKey   = isset($_GET['deleted']) ? (string)$_GET['deleted'] : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>متن‌های ربات | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer>

</script>
    <style>
        .text-grp { margin-bottom: 22px; }
        .text-grp__title {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 700;
            margin: 0 4px 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .text-grp__title::before {
            content: "";
            width: 4px; height: 16px;
            background: var(--accent);
            border-radius: 4px;
        }
        .text-row {
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 220px 1fr auto;
            gap: 14px;
            align-items: start;
            transition: border-color .15s ease;
        }
        .text-row:hover { border-color: var(--border-mid); }
        .text-key {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--accent);
            direction: ltr;
            text-align: left;
            padding-top: 8px;
            word-break: break-all;
        }
        .text-row textarea {
            width: 100%;
            background: var(--surface-3);
            border: 1px solid var(--border-mid);
            border-radius: 8px;
            color: var(--text-main);
            padding: 9px 12px;
            font-family: 'Arad', sans-serif;
            font-size: 13.5px;
            min-height: 44px;
            resize: vertical;
            line-height: 1.7;
        }
        .text-row textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .text-row textarea.changed {
            border-color: var(--color-warning);
            background: var(--color-warning-soft);
        }
        @media (max-width: 768px) {
            .text-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .text-key { padding-top: 0; font-size: 11px; }
        }
        .save-bar {
            position: sticky;
            bottom: 0;
            margin-top: 24px;
            padding: 14px 0;
            background: linear-gradient(180deg, transparent, var(--bg-grad-bot) 30%);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            z-index: 5;
        }
        .filter-bar {
            display: flex; gap: 10px; flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
        }
        .filter-bar input {
            flex: 1 1 240px;
            min-width: 200px;
            padding: 10px 14px;
            background: var(--surface-2);
            border: 1px solid var(--border-mid);
            border-radius: 10px;
            color: var(--text-main);
            font-family: 'Arad', sans-serif;
            font-size: 13.5px;
        }
        .filter-bar input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .text-row.hidden { display: none; }
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
                        <?php echo icon('text', 'svg-icon svg-lg'); ?>
                        ویرایش متن‌های ربات
                    </div>
                    <div class="page-head__sub">تمام برچسب‌های نمایشی و پیام‌های ربات در یک‌جا</div>
                </div>
                <div>
                    <button class="btn btn-outline" onclick="openModal('modal-add-text')">
                        <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن کلید جدید</span>
                    </button>
                </div>
            </div>

            <?php if ($savedNum > 0): ?>
                <div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:10px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo $savedNum; ?> متن به‌روزرسانی شد.</span>
                </div>
            <?php elseif ($savedNum === 0): ?>
                <div class="alert" style="background:var(--surface-3); border:1px solid var(--border-mid); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    تغییری انجام نشد.
                </div>
            <?php endif; ?>
            <?php if ($addedKey !== ''): ?>
                <div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    کلید جدید <code><?php echo htmlspecialchars($addedKey, ENT_QUOTES); ?></code> افزوده شد.
                </div>
            <?php endif; ?>
            <?php if ($delKey !== ''): ?>
                <div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    کلید <code><?php echo htmlspecialchars($delKey, ENT_QUOTES); ?></code> حذف شد.
                </div>
            <?php endif; ?>
            <?php if ($flash['err']): ?>
                <div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    <?php echo htmlspecialchars($flash['err'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="filter-bar">
                <input type="search" id="searchBox" placeholder="جستجو در کلید یا متن…">
                <span class="chip"><?php echo count($rows); ?> کلید</span>
            </div>

            <form method="POST" action="textbot.php" id="textForm">
                <input type="hidden" name="_action" value="save">

                <?php foreach ($orderedGrouped as $groupName => $items): ?>
                    <div class="text-grp">
                        <div class="text-grp__title"><?php echo htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php foreach ($items as $row):
                            $k = (string)$row['id_text'];
                            $v = (string)$row['text'];
                        ?>
                            <div class="text-row" data-search="<?php echo htmlspecialchars(strtolower($k . ' ' . $v), ENT_QUOTES); ?>">
                                <code class="text-key"><?php echo htmlspecialchars($k, ENT_QUOTES); ?></code>
                                <textarea name="t[<?php echo htmlspecialchars($k, ENT_QUOTES); ?>]"
                                        data-orig="<?php echo htmlspecialchars($v, ENT_QUOTES); ?>"
                                        rows="1"><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div style="display:flex; flex-direction:column; gap:6px;">
                                    <button type="button" class="btn btn-sm btn-soft-danger"
                                            onclick='deleteKey(<?php echo json_encode($k, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT); ?>)'
                                            title="حذف این کلید">
                                        <?php echo icon('trash', 'svg-icon'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="save-bar">
                    <span id="changeCount" style="align-self:center; color:var(--text-muted); font-size:13px; margin-inline-end:auto;">۰ تغییر در انتظار ذخیره</span>
                    <button type="submit" class="btn btn-primary">
                        <?php echo icon('check', 'svg-icon svg-sm'); ?>
                        <span>ذخیره تغییرات</span>
                    </button>
                </div>
            </form>

        </div>
    </section>
</section>


<div id="modal-add-text" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">افزودن کلید متن جدید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-text')">&times;</button>
        </div>
        <form method="POST" action="textbot.php">
            <input type="hidden" name="_action" value="add">
            <div class="form-group">
                <label class="form-label">کلید (انگلیسی، بدون فاصله)</label>
                <input type="text" name="new_key" class="form-control" style="direction:ltr; font-family:'JetBrains Mono', monospace;" placeholder="text_custom_button" required pattern="[A-Za-z0-9_ـ]+">
            </div>
            <div class="form-group">
                <label class="form-label">متن نمایش (فارسی)</label>
                <textarea name="new_val" class="form-control" rows="3" placeholder="مثلاً: 🔥 پیشنهاد ویژه"></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-text')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">افزودن</button>
            </div>
        </form>
    </div>
</div>


<form method="POST" action="textbot.php" id="deleteForm" style="display:none;">
    <input type="hidden" name="_action" value="delete">
    <input type="hidden" name="key" id="deleteKey">
</form>

<script>
function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

function deleteKey(k) {
    if (!confirm('کلید «' + k + '» و متن آن حذف شود؟')) return;
    document.getElementById('deleteKey').value = k;
    document.getElementById('deleteForm').submit();
}


(function () {
    var counter = document.getElementById('changeCount');
    var areas = document.querySelectorAll('#textForm textarea');
    function persianNum(n){ return String(n).replace(/\d/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); }
    function updateCount() {
        var n = 0;
        areas.forEach(function(t){
            if (t.value !== t.dataset.orig) { t.classList.add('changed'); n++; }
            else t.classList.remove('changed');
        });
        counter.textContent = persianNum(n) + ' تغییر در انتظار ذخیره';
    }
    function autogrow(t){
        t.style.height = 'auto';
        t.style.height = Math.max(44, t.scrollHeight) + 'px';
    }
    areas.forEach(function (t) {
        autogrow(t);
        t.addEventListener('input', function () { autogrow(t); updateCount(); });
    });


    var search = document.getElementById('searchBox');
    var rows = document.querySelectorAll('.text-row');
    var groups = document.querySelectorAll('.text-grp');
    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        rows.forEach(function (r) {
            r.classList.toggle('hidden', q && r.dataset.search.indexOf(q) === -1);
        });

        groups.forEach(function (g) {
            var any = g.querySelectorAll('.text-row:not(.hidden)').length > 0;
            g.style.display = any ? '' : 'none';
        });
    });
})();
</script>
</body>
</html>


