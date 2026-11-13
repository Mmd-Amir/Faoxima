<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../botapi.php';

if (empty($_SESSION['user']) || !is_string($_SESSION['user']) || $_SESSION['user'] === '') {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_csrf = $_SESSION['csrf_token'];

$SECTIONS = [
    'start' => ['label' => 'استارت ربات (/start)'],
    'cart'  => ['label' => 'کارت به کارت'],
    'buy'   => ['label' => 'خرید اشتراک'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incoming = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $incoming)) {
        http_response_code(403);
        exit('درخواست نامعتبر — توکن CSRF اشتباه است');
    }
}

$settingRow = [];
try {
    $stmt = $pdo->query("SELECT * FROM setting LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (is_array($row)) $settingRow = $row;
} catch (\Throwable $e) {
    error_log('[panel/banner] load failed: ' . $e->getMessage());
}

function faoxima_banner_mint_file_id($tmpPath, $mime, $name)
{
    global $pdo;
    $adminId = '';
    try {
        $stmt = $pdo->query("SELECT id_admin FROM admin ORDER BY id_admin ASC LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $adminId = is_array($row) ? (string)($row['id_admin'] ?? '') : '';
    } catch (\Throwable $e) {
        error_log('[panel/banner] admin lookup failed: ' . $e->getMessage());
    }
    if ($adminId === '') {
        return ['ok' => false, 'error' => 'هیچ ادمینی برای دریافت تصویر آزمایشی یافت نشد.'];
    }
    $cf = new CURLFile($tmpPath, $mime !== '' ? $mime : 'image/jpeg', $name);
    $res = telegram('sendPhoto', [
        'chat_id' => $adminId,
        'photo' => $cf,
    ]);
    if (empty($res['ok'])) {
        return ['ok' => false, 'error' => 'ارسال تصویر به تلگرام ناموفق بود.'];
    }
    $photos = $res['result']['photo'] ?? [];
    $fileId = (is_array($photos) && $photos) ? (end($photos)['file_id'] ?? '') : '';
    $messageId = $res['result']['message_id'] ?? null;
    if ($messageId) {
        deletemessage($adminId, $messageId);
    }
    if ($fileId === '') {
        return ['ok' => false, 'error' => 'دریافت شناسه فایل از تلگرام ناموفق بود.'];
    }
    return ['ok' => true, 'file_id' => $fileId];
}

$formErrors = [];
$savedCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
    foreach ($SECTIONS as $key => $meta) {
        $statusCol = "banner_{$key}_status";
        $fileCol = "banner_{$key}_file_id";

        $newStatus = isset($_POST['f_' . $statusCol]) ? '1' : '0';
        $newFileId = trim((string)($_POST['f_' . $fileCol] ?? ''));

        $uploadKey = 'upload_' . $key;
        if (isset($_FILES[$uploadKey]) && ($_FILES[$uploadKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string)$_FILES[$uploadKey]['tmp_name'];
            if ($tmp !== '' && is_uploaded_file($tmp)) {
                $mime = function_exists('mime_content_type') ? (string)(mime_content_type($tmp) ?: '') : (string)($_FILES[$uploadKey]['type'] ?? '');
                $name = (string)($_FILES[$uploadKey]['name'] ?? 'banner.jpg');
                $mint = faoxima_banner_mint_file_id($tmp, $mime, $name);
                if ($mint['ok']) {
                    $newFileId = $mint['file_id'];
                } else {
                    $formErrors[] = "{$meta['label']}: {$mint['error']}";
                }
            }
        }

        if (mb_strlen($newFileId) > 255) {
            $newFileId = mb_substr($newFileId, 0, 255);
        }

        $curStatus = (string)($settingRow[$statusCol] ?? '0');
        $curFileId = (string)($settingRow[$fileCol] ?? '');

        if ($curStatus !== $newStatus) {
            $statusSql = "UPDATE setting SET `{$statusCol}` = :v";
            if ($key === 'start' && $newStatus === '1' && array_key_exists('start_media_status', $settingRow)) {
                $statusSql .= ", `start_media_status` = '0'";
            }
            try {
                $upd = $pdo->prepare($statusSql);
                $upd->bindValue(':v', $newStatus, PDO::PARAM_STR);
                $upd->execute();
                if ($key === 'buy' && $newStatus === '1') {
                    try {
                        $pdo->exec("UPDATE section_media SET media_status = '0' WHERE section_id = 'buy'");
                    } catch (\Throwable $mediaError) {
                        error_log('[panel/banner] buy media exclusion failed: ' . $mediaError->getMessage());
                    }
                }
                $savedCount++;
            } catch (\Throwable $e) {
                error_log('[panel/banner] ' . $statusCol . ' failed: ' . $e->getMessage());
            }
        }
        if ($curFileId !== $newFileId) {
            try {
                $upd = $pdo->prepare("UPDATE setting SET `{$fileCol}` = :v");
                $upd->bindValue(':v', $newFileId, PDO::PARAM_STR);
                $upd->execute();
                $savedCount++;
            } catch (\Throwable $e) {
                error_log('[panel/banner] ' . $fileCol . ' failed: ' . $e->getMessage());
            }
        }
    }

    if (empty($formErrors)) {
        header('Location: banner.php?saved=' . $savedCount);
        exit;
    }
    $stmt = $pdo->query("SELECT * FROM setting LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (is_array($row)) $settingRow = $row;
}

$showSaved = isset($_GET['saved']);
$savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تنظیم بنر | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat39">
    <script src="js/theme.js?v=dark62" defer></script>
    <style>
        .bn-card { max-width: 720px; margin: 0 auto; }
        .bn-savebar { max-width: 720px; margin: 0 auto; justify-content: flex-end; }
        .bn-section-title { font-weight: 600; margin: 0 0 4px; }

        .bn-upload { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .bn-upload input[type="file"] {
            position: absolute;
            width: 1px; height: 1px;
            padding: 0; margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }
        .bn-upload__btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-mid);
            background: var(--surface-1);
            color: var(--text-main);
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
            white-space: nowrap;
        }
        .bn-upload__btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-soft);
            transform: none;
        }
        .bn-upload__btn svg { width: 16px; height: 16px; }
        .bn-upload__name {
            font-size: 12px;
            color: var(--text-dim);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 220px;
        }
        .bn-upload__name.has-file { color: var(--accent); font-weight: 600; }
        .bn-upload__clear {
            display: none;
            align-items: center;
            justify-content: center;
            width: 26px; height: 26px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-mid);
            background: var(--surface-1);
            color: var(--color-danger);
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .bn-upload__clear:hover { background: var(--color-danger-soft); border-color: var(--color-danger); }
        .bn-upload__clear.show { display: inline-flex; }
        .bn-upload__clear svg { width: 14px; height: 14px; }
        #main-content .wrapper.fx-page-settings .bn-card { padding: 24px; border: 1px solid var(--border-soft); border-radius: 22px; background: radial-gradient(ellipse at 100% 0%, var(--accent-soft), transparent 65%), var(--surface-1); }
        .bn-heading { display: flex; align-items: center; gap: 12px; padding-bottom: 20px; margin-bottom: 6px; border-bottom: 1px solid var(--border-soft); }
        .bn-heading__icon { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: 13px; background: var(--accent-soft); color: var(--accent-ink); }
        .bn-heading__icon .svg-icon { width: 24px; height: 24px; }
        .bn-heading strong { display: block; font-size: 15px; }
        .bn-heading p { margin: 5px 0 0; font-size: 11px; line-height: 1.8; color: var(--text-muted); }
        .bn-section + .bn-section { padding-top: 8px; margin-top: 18px; border-top: 1px solid var(--border-soft); }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row { margin: 0; padding: 17px 0; gap: 18px; }
        #main-content .wrapper.fx-page-settings .bn-card .bn-toggle-row { display: flex; align-items: center; justify-content: space-between; border: 0; }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row__label { font-size: 13px; line-height: 1.8; cursor: pointer; }
        #main-content .wrapper.fx-page-settings .bn-card .bn-section-title { font-size: 13px; margin: 0; }
        .bn-fields { padding: 16px; border: 1px solid var(--border-soft); border-radius: 14px; background: var(--surface-2); }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row.bn-field-row { display: flex; flex-direction: column; align-items: stretch; gap: 10px; padding: 0; border: 0; }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row.bn-field-row .setting-row__label { flex: 0 0 auto; margin: 0; text-align: center; font-size: 12px; }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row.bn-field-row .setting-row__control { display: flex; flex-direction: column; align-items: stretch; gap: 10px; flex: 0 0 auto; width: calc(100% - 32px); max-width: 560px; min-width: 0; margin: 0 auto; }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row.bn-field-row input[type="text"] { box-sizing: border-box; width: 100%; min-width: 0; min-height: 42px; height: 42px; padding: 10px 12px; background: var(--surface-1); border: 1px solid var(--border-soft); border-radius: 10px; color: var(--text-main); font-size: 13px; }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row.bn-field-row input[type="text"]:focus { border-color: var(--accent); box-shadow: none; outline: 2px solid var(--accent-soft); outline-offset: 2px; }
        #main-content .wrapper.fx-page-settings .bn-card .setting-row.bn-upload-row { padding-top: 16px; margin-top: 16px; border-top: 1px solid var(--border-soft); }
        .bn-card .bn-upload { justify-content: center; gap: 10px; min-width: 0; }
        .bn-card .bn-upload__btn { padding: 9px 14px; min-height: 38px; border-radius: 10px; font-size: 12px; }
        .bn-card .bn-upload__name { font-size: 11px; max-width: 100%; }
        .bn-card .bn-upload input:focus-visible + .bn-upload__btn { outline: 2px solid var(--accent); outline-offset: 3px; }
        .bn-card .switch:focus-within { outline: 2px solid var(--accent); outline-offset: 4px; border-radius: 30px; }
        #main-content .bn-savebar { padding: 18px 0; gap: 8px; }
        #main-content .bn-savebar .btn { min-height: 38px; border-radius: 10px; font-size: 12px; }
        @media (max-width: 600px) {
            #main-content .wrapper.fx-page-settings .bn-card { padding: 18px; border-radius: 18px; }
            .bn-heading strong { font-size: 14px; }
            .bn-fields { padding: 12px; }
            #main-content .wrapper.fx-page-settings .bn-card .bn-section-title { font-size: 12px; }
            .bn-card .bn-upload { flex-direction: column; gap: 8px; }
        }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-settings">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('image', 'svg-icon svg-lg'); ?>
                        تنظیم بنر
                    </div>
                    <div class="page-head__sub">برای استارت ربات، کارت به کارت و خرید اشتراک، بنر تصویری اختصاصی تنظیم کنید</div>
                </div>
            </div>

            <?php if ($showSaved): ?>
                <div class="alert alert-success">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span>
                        <?php if ($savedNum > 0): ?>
                            تغییرات ذخیره شد. (<?php echo $savedNum; ?> فیلد به‌روزرسانی شد)
                        <?php else: ?>
                            هیچ تغییری انجام نشد.
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php foreach ($formErrors as $err): ?>
                <div class="alert alert-error">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endforeach; ?>

            <form method="POST" action="banner.php" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="_save" value="1">

                <div class="card bn-card">
                    <div class="bn-heading">
                        <span class="bn-heading__icon" aria-hidden="true"><?php echo icon('image', 'svg-icon'); ?></span>
                        <div><strong>بنرهای تصویری ربات</strong><p>برای هر بخش، تصویر دلخواه را با لینک، شناسهٔ تلگرام یا آپلود فایل مشخص کنید.</p></div>
                    </div>
                    <?php foreach ($SECTIONS as $key => $meta):
                        $statusCol = "banner_{$key}_status";
                        $fileCol = "banner_{$key}_file_id";
                        $statusVal = (string)($settingRow[$statusCol] ?? '0');
                        $fileVal = (string)($settingRow[$fileCol] ?? '');
                        $isOn = $statusVal === '1';
                    ?>
                        <div class="bn-section">
                        <div class="setting-row bn-toggle-row">
                            <label class="setting-row__label" for="status_<?php echo $key; ?>">
                                <div class="bn-section-title"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </label>
                            <div class="setting-row__control">
                                <label class="switch" title="<?php echo htmlspecialchars($statusCol); ?>">
                                    <input type="checkbox" role="switch" id="status_<?php echo $key; ?>" name="f_<?php echo $statusCol; ?>" <?php echo $isOn ? 'checked' : ''; ?>>
                                    <span class="switch__slot"></span>
                                </label>
                            </div>
                        </div>
                        <div class="bn-fields">
                        <div class="setting-row bn-field-row">
                            <label for="f_<?php echo $fileCol; ?>" class="setting-row__label">لینک تصویر یا شناسه فایل تلگرام</label>
                            <div class="setting-row__control">
                                <input type="text" id="f_<?php echo $fileCol; ?>" name="f_<?php echo $fileCol; ?>"
                                    value="<?php echo htmlspecialchars($fileVal, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="https://... یا file_id"
                                    style="direction:ltr; text-align:left;">
                            </div>
                        </div>
                        <div class="setting-row bn-field-row bn-upload-row">
                            <label for="upload_<?php echo $key; ?>" class="setting-row__label">آپلود تصویر</label>
                            <div class="setting-row__control">
                                <div class="bn-upload" data-bn-upload>
                                    <input type="file" id="upload_<?php echo $key; ?>" name="upload_<?php echo $key; ?>" accept="image/*" data-bn-upload-input>
                                    <label for="upload_<?php echo $key; ?>" class="bn-upload__btn">
                                        <?php echo icon('image', 'svg-icon'); ?>
                                        <span>انتخاب تصویر</span>
                                    </label>
                                    <span class="bn-upload__name" data-bn-upload-name>
                                        <?php echo $fileVal !== '' ? 'تصویری قبلاً تنظیم شده است' : 'فایلی انتخاب نشده'; ?>
                                    </span>
                                    <button type="button" class="bn-upload__clear" data-bn-upload-clear title="لغو انتخاب">
                                        <?php echo icon('xmark', 'svg-icon'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="save-bar bn-savebar">
                    <button type="reset" class="btn btn-outline">
                        <?php echo icon('rotate-left', 'svg-icon svg-sm'); ?>
                        <span>بازنشانی</span>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo icon('check', 'svg-icon svg-sm'); ?>
                        <span>ذخیره تغییرات</span>
                    </button>
                </div>
            </form>

        </div>
    </section>
</section>

<script>
document.querySelectorAll('[data-bn-upload]').forEach(function (wrap) {
    var input = wrap.querySelector('[data-bn-upload-input]');
    var name = wrap.querySelector('[data-bn-upload-name]');
    var clearBtn = wrap.querySelector('[data-bn-upload-clear]');
    var defaultText = name.textContent.trim();

    input.addEventListener('change', function () {
        if (input.files && input.files.length > 0) {
            name.textContent = input.files[0].name;
            name.classList.add('has-file');
            clearBtn.classList.add('show');
        } else {
            name.textContent = defaultText;
            name.classList.remove('has-file');
            clearBtn.classList.remove('show');
        }
    });

    clearBtn.addEventListener('click', function () {
        input.value = '';
        name.textContent = defaultText;
        name.classList.remove('has-file');
        clearBtn.classList.remove('show');
    });
});
</script>

</body>
</html>
