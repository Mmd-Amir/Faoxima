<?php

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
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
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

$ALLOWED_STYLES = ['default', 'none', 'primary', 'success', 'danger'];
$registry = rx_account_button_registry();

$readStyleState = function () {
    $useDefaults = true;
    $row = select("setting", "keyboard_styles_all", null, null, "select", ['cache' => false]);
    $decoded = (is_array($row) && !empty($row['keyboard_styles_all'])) ? json_decode($row['keyboard_styles_all'], true) : null;
    if (!is_array($decoded)) {
        $decoded = [];
    }
    if (array_key_exists('_use_defaults', $decoded)) {
        $useDefaults = (bool) $decoded['_use_defaults'];
    }
    return [$decoded, $useDefaults];
};

$factoryAccount = [];
if (function_exists('rx_getKeyboardDefaultStyles')) {
    $factoryAll = rx_getKeyboardDefaultStyles();
    if (is_array($factoryAll) && isset($factoryAll['account']) && is_array($factoryAll['account'])) {
        foreach ($factoryAll['account'] as $k => $v) {
            if (in_array($v, $ALLOWED_STYLES, true)) {
                $factoryAccount[$k] = $v;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
        exit;
    }

    if (($payload['action'] ?? '') === 'reset') {
        update("setting", "account_keyboard_layout", '', null, null);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (!isset($payload['rows']) || !is_array($payload['rows'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
        exit;
    }

    $layoutRows = [];
    $picked = [];
    $seen = [];
    foreach ($payload['rows'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $cleanRow = [];
        foreach ($row as $btn) {
            if (!is_array($btn) || !isset($btn['id']) || !is_string($btn['id'])) {
                continue;
            }
            $id = $btn['id'];
            if (!isset($registry[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $cleanRow[] = $id;
            $style = isset($btn['style']) ? (string) $btn['style'] : 'default';
            if (in_array($style, $ALLOWED_STYLES, true)) {
                $picked[$id] = $style;
            }
        }
        if ($cleanRow) {
            $layoutRows[] = $cleanRow;
        }
    }
    if (!isset($seen['backuser'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'back_required']);
        exit;
    }

    list($allStyles, $useDefaults) = $readStyleState();
    $account = (isset($allStyles['account']) && is_array($allStyles['account'])) ? $allStyles['account'] : [];
    foreach ($picked as $id => $style) {
        $factory = $useDefaults ? ($factoryAccount[$id] ?? null) : null;
        if ($style === 'default' || ($factory !== null && $style === $factory)) {
            unset($account[$id]);
        } else {
            $account[$id] = $style;
        }
    }
    $allStyles['account'] = $account;

    update("setting", "account_keyboard_layout", rx_account_layout_encode($layoutRows), null, null);
    update("setting", "keyboard_styles_all", json_encode($allStyles, JSON_UNESCAPED_UNICODE), null, null);
    echo json_encode(['ok' => true]);
    exit;
}

list($allStyles, $useDefaults) = $readStyleState();
$accountStyles = (isset($allStyles['account']) && is_array($allStyles['account'])) ? $allStyles['account'] : [];

$textbotMap = [];
try {
    $textbotRows = select("textbot", "*", null, null, "fetchAll");
    if (is_array($textbotRows)) {
        foreach ($textbotRows as $row) {
            if (isset($row['id_text']) && isset($row['text']) && $row['text'] !== '') {
                $textbotMap[(string) $row['id_text']] = (string) $row['text'];
            }
        }
    }
} catch (\Throwable $e) {
    error_log('[panel/account_layout] textbot load failed: ' . $e->getMessage());
}

$buttons = [];
foreach ($registry as $id => $def) {
    $label = $def['label'];
    if ($def['textbot'] !== null && isset($textbotMap[$def['textbot']])) {
        $label = $textbotMap[$def['textbot']];
    }
    $savedStyle = $accountStyles[$id] ?? null;
    if (!is_string($savedStyle) || $savedStyle === 'default' || !in_array($savedStyle, $ALLOWED_STYLES, true)) {
        $savedStyle = null;
    }
    $style = $savedStyle ?? ($useDefaults ? ($factoryAccount[$id] ?? 'default') : 'default');
    if (!in_array($style, $ALLOWED_STYLES, true)) {
        $style = 'default';
    }
    $guess = 'default';
    if ($useDefaults) {
        $guess = 'primary';
        if (function_exists('rx_kb_guess_style_from_text')) {
            $g = rx_kb_guess_style_from_text($label);
            if (is_string($g) && in_array($g, $ALLOWED_STYLES, true)) {
                $guess = $g;
            }
        }
    }
    $buttons[$id] = [
        'id' => $id,
        'label' => $label,
        'style' => $style,
        'guess' => $guess,
        'conditional' => $def['condition'] !== null,
        'note' => $def['note'],
        'required' => !empty($def['required']),
    ];
}

$layoutRaw = select("setting", "account_keyboard_layout", null, null, "select", ['cache' => false]);
$layoutRaw = is_array($layoutRaw) ? trim((string) ($layoutRaw['account_keyboard_layout'] ?? '')) : '';
$layoutRows = $layoutRaw === '' ? rx_account_default_layout() : rx_account_layout_normalize($layoutRaw);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <script>
    (function(){try{document.documentElement.setAttribute('data-theme','dark'); localStorage.setItem('faoxima_theme','dark');
    var c=localStorage.getItem('faoxima_color');
    if(c)document.documentElement.setAttribute('data-color',c);}catch(e){}})();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>چیدمان حساب — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <script src="js/theme.js?v=dark62" defer></script>
    <script src="js/account_layout_editor.js?v=colors2" defer></script>
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
        :root:not([data-theme="light"]) .kb-topbar__brand .logo-mark { box-shadow: none; }
        .kb-topbar__brand .logo-mark img {
            width: 100%; height: 100%;
            object-fit: contain; object-position: center;
            transform: scale(1.35);
            display: block;
        }
        .kb-btn__cond {
            font-size: 10px; font-weight: 700; padding: 1px 6px; margin-inline-start: 6px;
            border-radius: 100px; background: rgba(255,255,255,.22); white-space: nowrap;
        }
        .kb-btn__lock { opacity: .8; margin-inline-start: 6px; font-size: 12px; }
        .acc-pv {
            max-width: 380px; margin: 14px auto 0; padding: 10px; border-radius: var(--radius-2xl);
            background: var(--surface-2, rgba(127,127,127,.12)); display: flex; flex-direction: column; gap: 6px;
        }
        .acc-pv__row { display: flex; gap: 6px; }
        .acc-pv__btn {
            flex: 1 1 0; min-width: 0; text-align: center; padding: 9px 8px; border-radius: var(--radius-md);
            font-size: 13px; font-weight: 600; color: #fff; background: #3b6a86;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .acc-pv__btn[data-style="primary"] { background: #2f6f95; }
        .acc-pv__btn[data-style="success"] { background: #3f7d34; }
        .acc-pv__btn[data-style="danger"]  { background: #9a4034; }
        .acc-pv__btn[data-style="default"] { background: #4a5560; }
        .acc-pv__btn.is-cond { outline: 1px dashed rgba(255,255,255,.55); outline-offset: -3px; }
        .acc-pv__note { text-align: center; font-size: 12px; opacity: .7; margin-top: 8px; }
        .acc-hidden-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .acc-hidden-list .btn { border-radius: 100px; }
        @media (max-width: 600px) {
            .kb-topbar { padding: 10px 13px; gap: 10px; flex-wrap: nowrap; }
            .kb-topbar__brand { min-width: 0; }
            .kb-topbar__brand span:not(.logo-mark) { font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .kb-actions { gap: 7px; flex-shrink: 0; }
            .kb-topbar .btn { padding: 8px 10px; }
        }
        .fx-keyboard-editor .kb-page > .kb-hero,
        .fx-keyboard-editor .kb-page > .card {
            background: radial-gradient(ellipse at 0% 0%, var(--accent-soft), transparent 65%), var(--surface-1);
            border-color: var(--border-soft);
        }
        .fx-keyboard-editor .kb-page > .kb-preview-wrap {
            background: radial-gradient(ellipse at 100% 0%, var(--accent-soft), transparent 65%), var(--surface-1);
            border-color: var(--border-soft);
        }
    </style>
</head>
<body class="fx-keyboard-editor">

<script type="application/json" id="acc-buttons">
<?php echo json_encode($buttons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>
</script>
<script type="application/json" id="acc-initial">
<?php echo json_encode(['rows' => $layoutRows], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>
</script>

<div class="kb-topbar">
    <div class="kb-topbar__brand">
        <span class="logo-mark"><img src="logo/faoxima.jpg" alt="faoxima" loading="lazy" width="30" height="30"></span>
        <span>چیدمان حساب</span>
    </div>
    <div class="kb-actions">
        <a class="btn btn-outline btn-sm" href="index.php">
            <?php echo icon('arrow-right', 'svg-icon svg-sm'); ?>
            <span>بازگشت</span>
        </a>
        <button type="button" id="acc-reset" class="btn btn-soft-warning btn-sm">
            <?php echo icon('rotate-left', 'svg-icon svg-sm'); ?>
            <span>بازگشت به پیش‌فرض</span>
        </button>
    </div>
</div>

<div class="kb-page">

    <div class="kb-hero">
        <div class="kb-hero__text">
            <h1>
                <?php echo icon('wallet', 'svg-icon svg-lg'); ?>
                چیدمان دکمه‌های حساب کاربری
            </h1>
            <p>
                دکمه‌های زیر اطلاعات حساب در بخش «کیف پول + شارژ» را مرتب، پنهان و رنگ‌بندی کنید. حذف یک دکمه فقط آن را از این منو پنهان می‌کند و قابلیت آن را غیرفعال نمی‌کند. تغییرات به‌طور خودکار ذخیره می‌شوند.
            </p>
        </div>
    </div>

    <div class="kb-preview-wrap">
        <div class="kb-preview-head">
            <span><span class="dot"></span> ویرایشگر زنده</span>
            <span>~/ربات/حساب</span>
        </div>

        <div id="kb-rows" class="kb-rows" aria-live="polite"></div>

        <button id="kb-add-row" class="kb-add-row" type="button">
            <?php echo icon('plus', 'svg-icon'); ?>
            <span>افزودن ردیف جدید</span>
        </button>
    </div>

    <div class="card">
        <div class="card__head">
            <div class="card__title">
                <?php echo icon('eye', 'svg-icon svg-md'); ?>
                <span>پیش‌نمایش در تلگرام</span>
            </div>
        </div>
        <div>
            <div id="acc-preview" class="acc-pv"></div>
            <div class="acc-pv__note">دکمه‌های دارای کادر خط‌چین شرطی هستند و فقط در صورت برقرار بودن شرطشان نمایش داده می‌شوند.</div>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <div class="card__title">
                <?php echo icon('circle-info', 'svg-icon svg-md'); ?>
                <span>دکمه‌های پنهان</span>
            </div>
        </div>
        <div>
            <p>دکمه‌هایی که از چیدمان حذف شده‌اند. با کلیک روی هر کدام، به آخرین ردیف برمی‌گردد.</p>
            <div id="acc-hidden" class="acc-hidden-list"></div>
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

                <div class="kb-field" id="kb-select-wrap">
                    <label>دکمه</label>
                    <select id="kb-edit-select" class="kb-select"></select>
                </div>

                <div class="kb-field" id="kb-cond-note" style="display:none;"></div>

                <div class="kb-field">
                    <label>رنگ دکمه</label>
                    <div class="kb-color-picker">
                        <label class="kb-color-opt" data-color="none">
                            <input type="radio" name="kb-color" value="none" checked>
                            <div class="kb-color-opt__card">
                                <div class="kb-color-opt__swatch"></div>
                                <div class="kb-color-opt__name">بدون رنگ</div>
                                <div class="kb-color-opt__hint">بدون رنگ خودکار</div>
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
