<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

session_start();
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
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>چیدمان کیبورد — پنل فاکسیما</title>
    <link rel="preload" href="fonts/Arad-BoldDots2.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer>

</script>
    <script src="js/keyboard_editor.js" defer>

</script>
    <style>
        body { padding-top: 0 !important; }
        .kb-topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex; align-items: center; gap: 10px;
            padding: 12px 20px;
            background: rgba(13, 16, 22, 0.92);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-soft);
            flex-wrap: wrap;
        }
        .kb-topbar__brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 700; color: var(--accent); font-size: 14px;
        }
        .kb-topbar__brand .logo-mark {
            width: 28px; height: 28px;
            display: grid; place-items: center;
            background: var(--accent-soft);
            color: var(--accent);
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            border: 1px solid var(--accent-mid);
        }
        .kb-topbar__grow { flex: 1 1 auto; }
        @media (max-width: 600px) {
            .kb-topbar { padding: 10px 12px; }
            .kb-topbar__brand span:not(.logo-mark) { display: none; }
        }
    </style>
</head>
<body>


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
        <span class="logo-mark">F</span>
        <span>چیدمان کیبورد</span>
    </div>
    <div class="kb-topbar__grow"></div>
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
            <span style="font-family: monospace; font-size: 11px;">~/ربات/کیبورد</span>
        </div>

        <div id="kb-rows" class="kb-rows" aria-live="polite">
            
        </div>

        <button id="kb-add-row" class="kb-add-row" type="button">
            <?php echo icon('plus', 'svg-icon'); ?>
            <span>افزودن ردیف جدید</span>
        </button>
    </div>

    <div class="card" style="margin-top: 20px;">
        <div class="card__head">
            <div class="card__title">
                <?php echo icon('circle-info', 'svg-icon svg-md'); ?>
                <span>راهنمای رنگ‌بندی دکمه‌ها</span>
            </div>
        </div>
        <div style="padding: 0 4px; color: var(--text-muted); font-size: 13px; line-height: 1.9;">
            <p style="margin: 0 0 10px;">
                می‌توانید برای هر دکمه یکی از چهار حالت رنگی را انتخاب کنید. این رنگ‌ها برای دکمه‌های inline-keyboard ربات تلگرام اعمال می‌شوند:
            </p>
            <ul style="margin: 0; padding-inline-start: 18px; line-height: 2;">
                <li><b style="color: var(--text-main);">پیش‌فرض (Default)</b> — رنگ خودکار با توجه به تم کاربر در تلگرام</li>
                <li><b style="color: var(--accent);">اصلی (Primary)</b> — رنگ تم پنل</li>
                <li><b style="color: #22c55e;">موفقیت (Success)</b> — سبز برای خرید، فعال‌سازی</li>
                <li><b style="color: #ef4444;">خطر (Danger)</b> — قرمز برای لغو، حذف</li>
            </ul>
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


