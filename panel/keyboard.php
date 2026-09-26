<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/lib/icons.php';


$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}


$DEFAULT_KEYBOARD_JSON = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';


$action = filter_input(INPUT_GET, 'action');
if ($action === 'reaset' || $action === 'reset') {
    update("setting", "keyboardmain", $DEFAULT_KEYBOARD_JSON, null, null);
    header('Location: keyboard.php');
    exit;
}


$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $keyboard = json_decode($raw, true);
    if (is_array($keyboard)) {

        $clean = [];
        $allowedStyles = ['primary', 'success', 'danger'];
        foreach ($keyboard as $row) {
            if (!is_array($row)) continue;
            $cleanRow = [];
            foreach ($row as $btn) {
                if (is_string($btn)) {
                    $cleanRow[] = ['text' => $btn];
                } elseif (is_array($btn) && isset($btn['text'])) {
                    $b = ['text' => (string)$btn['text']];
                    if (isset($btn['style']) && in_array($btn['style'], $allowedStyles, true)) {
                        $b['style'] = $btn['style'];
                    }
                    $cleanRow[] = $b;
                }
            }
            $clean[] = $cleanRow;
        }
        $payload = ['keyboard' => $clean];
        update("setting", "keyboardmain", json_encode($payload, JSON_UNESCAPED_UNICODE), null, null);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8', true, 400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}


$settingRow = select("setting", "keyboardmain", null, null, "select");
$currentJson = $DEFAULT_KEYBOARD_JSON;
if (is_array($settingRow) && !empty($settingRow['keyboardmain'])) {
    $decoded = json_decode($settingRow['keyboardmain'], true);
    if (is_array($decoded) && isset($decoded['keyboard'])) {
        $currentJson = $settingRow['keyboardmain'];
    }
}


$textbotMap = [];
try {
    $textbotRows = select("textbot", "*", null, null, "fetchAll");
    if (is_array($textbotRows)) {
        foreach ($textbotRows as $row) {
            if (isset($row['id_text']) && isset($row['text']) && $row['text'] !== '') {
                $textbotMap[(string)$row['id_text']] = (string)$row['text'];
            }
        }
    }
} catch (\Throwable $e) {

    error_log('[panel/keyboard] textbot load failed: ' . $e->getMessage());
}


$primaryKeys = [
    'text_sell', 'text_extend', 'text_usertest', 'text_wheel_luck',
    'text_Purchased_services', 'accountwallet',
    'text_affiliates', 'text_Tariff_list',
    'text_support', 'text_help',
    'text_miniapp_button',
];
if (!isset($textbotMap['text_miniapp_button'])) {
    $textbotMap['text_miniapp_button'] = '🚀 Open Mini App';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-color="blue">
<head>
    <script>
    (function(){try{var t=localStorage.getItem('faoxima_theme');
    if(t!=='light'&&t!=='dark')t='dark';
    document.documentElement.setAttribute('data-theme',t);
    var c=localStorage.getItem('faoxima_color');
    if(c)document.documentElement.setAttribute('data-color',c);}catch(e){}})();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>چیدمان کیبورد — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <script src="js/theme.js?v=flat50" defer>

</script>
    <script src="js/keyboard_editor.js?v=touch2" defer>

</script>
    <style>
        body { padding-top: 0 !important; }
        .kb-topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex; align-items: center; gap: 10px 16px; justify-content: space-between;
            padding: 11px 22px;
            background: var(--surface-1);
            border-bottom: 1px solid var(--border-soft);
            box-shadow: var(--shadow-1);
            flex-wrap: wrap;
        }
        .kb-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        [data-theme="light"] .kb-topbar { background: var(--surface-1); border-bottom-color: var(--border-soft); box-shadow: var(--shadow-1); }
        .kb-topbar .btn { border-radius: 100px; gap: 7px; }
        .kb-topbar .btn:hover { border-color: color-mix(in srgb, var(--accent) 55%, transparent); transform: none; }
        .kb-topbar__brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; color: var(--accent); font-size: 16px; letter-spacing: -.2px;
        }
        .kb-topbar__brand .logo-mark {
            width: 30px; height: 30px;
            display: grid; place-items: center;
            background: #fff;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(20, 20, 30, 0.18), 0 3px 8px rgba(20, 20, 30, 0.14);
        }
        [data-theme="dark"] .kb-topbar__brand .logo-mark,
        :root:not([data-theme="light"]) .kb-topbar__brand .logo-mark {
            box-shadow: none;
        }
        .kb-topbar__brand .logo-mark img {
            width: 100%; height: 100%;
            object-fit: contain; object-position: center;
            transform: scale(1.35);
            display: block;
        }
        .kb-topbar__grow { flex: 1 1 auto; }
        @media (max-width: 600px) {
            .kb-topbar { padding: 10px 13px; gap: 10px; flex-wrap: nowrap; }
            .kb-topbar__brand { min-width: 0; }
            .kb-topbar__brand span:not(.logo-mark) { font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .kb-actions { gap: 7px; flex-shrink: 0; }
            .kb-topbar .btn span { display: none; }
            .kb-topbar .btn { padding: 8px 10px; }
        }
    </style>
</head>
<body class="fx-keyboard-editor">


<script type="application/json" id="kb-initial">
<?php echo $currentJson; ?>
</script>
<script type="application/json" id="kb-textbot">
<?php echo json_encode($textbotMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
</script>
<script type="application/json" id="kb-primary-keys">
<?php echo json_encode($primaryKeys); ?>
</script>

<div class="kb-topbar">
    <div class="kb-topbar__brand">
        <span class="logo-mark"><img src="logo/faoxima.jpg" alt="faoxima" loading="lazy" width="30" height="30"></span>
        <span>چیدمان کیبورد</span>
    </div>
    <div class="kb-actions">
    <a class="btn btn-outline btn-sm" href="index.php">
        <?php echo icon('arrow-right', 'svg-icon svg-sm'); ?>
        <span>بازگشت</span>
    </a>
    <a class="btn btn-soft-warning btn-sm" href="keyboard.php?action=reset"
       onclick="return confirm('چیدمان فعلی به حالت پیش‌فرض بازمی‌گردد. ادامه می‌دهید؟');">
        <?php echo icon('rotate-left', 'svg-icon svg-sm'); ?>
        <span>بازگرداندن پیش‌فرض</span>
    </a>
    </div>
</div>

<div class="kb-page">

    <div class="kb-hero">
        <div class="kb-hero__text">
            <h1>
                <?php echo icon('keyboard', 'svg-icon svg-lg'); ?>
                چیدمان کیبورد ربات
            </h1>
            <p>
                دکمه‌های کیبورد ربات تلگرام شما را اینجا بسازید، مرتب کنید و رنگ‌بندی کنید. تغییرات به‌طور خودکار ذخیره می‌شوند.
            </p>
        </div>
    </div>

    <div class="kb-preview-wrap">
        <div class="kb-preview-head">
            <span><span class="dot"></span> پیش‌نمایش زنده — همینطور که می‌سازید</span>
            <span>~/ربات/کیبورد</span>
        </div>

        <div id="kb-rows" class="kb-rows" aria-live="polite">
            
        </div>

        <button id="kb-add-row" class="kb-add-row" type="button">
            <?php echo icon('plus', 'svg-icon'); ?>
            <span>افزودن ردیف جدید</span>
        </button>
    </div>

    <div class="card">
        <div class="card__head">
            <div class="card__title">
                <?php echo icon('circle-info', 'svg-icon svg-md'); ?>
                <span>راهنمای رنگ‌بندی دکمه‌ها</span>
            </div>
        </div>
        <div>
            <p>
                می‌توانید برای هر دکمه یکی از چهار حالت رنگی را انتخاب کنید. این رنگ‌ها برای دکمه‌های inline-keyboard ربات تلگرام اعمال می‌شوند:
            </p>
            <ul>
                <li><b>پیش‌فرض (Default)</b> — رنگ خودکار با توجه به تم کاربر در تلگرام</li>
                <li><b>اصلی (Primary)</b> — رنگ تم پنل</li>
                <li><b>موفقیت (Success)</b> — سبز برای خرید، فعال‌سازی</li>
                <li><b>خطر (Danger)</b> — قرمز برای لغو، حذف</li>
            </ul>
            <p>دکمه «<b>text_miniapp_button</b>» (مینی‌اپ) فقط در حالت کیبورد شیشه‌ای نمایش داده می‌شود.</p>
        </div>
    </div>
</div>


<div id="kb-toast" class="kb-toast" role="status" aria-live="polite"></div>


<div id="kb-modal" class="kb-modal" role="dialog" aria-modal="true" aria-labelledby="kb-modal-title">
    <div class="kb-modal__box">
        <form id="kb-edit-form">
            <div class="kb-modal__head" id="kb-modal-title">
                <?php echo icon('pen-to-square'); ?>
                <span id="kb-modal-title-text">ویرایش دکمه</span>
            </div>
            <div class="kb-modal__body">

                <div class="kb-field">
                    <label>دکمه</label>
                    <select id="kb-edit-select" class="kb-select">
                        
                    </select>
                </div>

                <div class="kb-field" id="kb-custom-wrap" style="display:none;">
                    <label>متن دستی</label>
                    <input id="kb-edit-text" type="text" maxlength="80" placeholder="متن دلخواه..." autocomplete="off">
                </div>

                <div class="kb-field">
                    <label>رنگ دکمه</label>
                    <div class="kb-color-picker">
                        <label class="kb-color-opt" data-color="default">
                            <input type="radio" name="kb-color" value="default" checked>
                            <div class="kb-color-opt__card">
                                <div class="kb-color-opt__swatch"></div>
                                <div class="kb-color-opt__name">پیش‌فرض</div>
                                <div class="kb-color-opt__hint">رنگ خودکار</div>
                            </div>
                        </label>
                        <label class="kb-color-opt" data-color="primary">
                            <input type="radio" name="kb-color" value="primary">
                            <div class="kb-color-opt__card">
                                <div class="kb-color-opt__swatch"></div>
                                <div class="kb-color-opt__name">اصلی</div>
                                <div class="kb-color-opt__hint">primary</div>
                            </div>
                        </label>
                        <label class="kb-color-opt" data-color="success">
                            <input type="radio" name="kb-color" value="success">
                            <div class="kb-color-opt__card">
                                <div class="kb-color-opt__swatch"></div>
                                <div class="kb-color-opt__name">موفقیت</div>
                                <div class="kb-color-opt__hint">success — سبز</div>
                            </div>
                        </label>
                        <label class="kb-color-opt" data-color="danger">
                            <input type="radio" name="kb-color" value="danger">
                            <div class="kb-color-opt__card">
                                <div class="kb-color-opt__swatch"></div>
                                <div class="kb-color-opt__name">خطر</div>
                                <div class="kb-color-opt__hint">danger — قرمز</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="kb-modal__foot">
                <button type="button" id="kb-cancel" class="btn btn-outline btn-sm">
                    <?php echo icon('xmark', 'svg-icon svg-sm'); ?>
                    <span>انصراف</span>
                </button>
                <button type="submit" id="kb-save" class="btn btn-primary btn-sm">
                    <?php echo icon('check', 'svg-icon svg-sm'); ?>
                    <span>ذخیره دکمه</span>
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>


