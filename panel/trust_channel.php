<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/icons.php';

if (empty($_SESSION['user']) || !is_string($_SESSION['user']) || $_SESSION['user'] === '') {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_csrf = $_SESSION['csrf_token'];

$FIELDS = [
    ['type' => 'toggle', 'col' => 'PublicLog_Status',        'label' => 'فعال‌سازی گزارش عمومی خرید', 'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_NewSub',        'label' => 'خرید اشتراک جدید',            'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_Renewal',       'label' => 'تمدید سرویس',                 'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_VolumeTopup',   'label' => 'افزایش حجم',                  'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_TimeExtra',     'label' => 'افزایش زمان',                 'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_WalletDeposit', 'label' => 'شارژ کیف پول',                'on' => '1', 'off' => '0'],
    ['type' => 'text',   'col' => 'PublicLog_Channel',       'label' => 'آیدی کانال اعتماد',           'placeholder' => '@channel یا -100…'],
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
    error_log('[panel/trust_channel] load failed: ' . $e->getMessage());
}

$savedCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
    foreach ($FIELDS as $f) {
        $col = $f['col'];
        $cur = $settingRow[$col] ?? null;
        if ($f['type'] === 'toggle') {
            $new = isset($_POST['f_' . $col]) ? $f['on'] : $f['off'];
        } else {
            $new = (string)($_POST['f_' . $col] ?? '');
            if (mb_strlen($new) > 500) {
                $new = mb_substr($new, 0, 500);
            }
        }
        if ((string)$cur !== (string)$new) {
            try {
                $sanCol = preg_replace('/[^A-Za-z0-9_]/', '', $col);
                $upd = $pdo->prepare("UPDATE setting SET `{$sanCol}` = :v");
                $upd->bindValue(':v', $new, PDO::PARAM_STR);
                $upd->execute();
                $savedCount++;
            } catch (\Throwable $e) {
                error_log('[panel/trust_channel] ' . $col . ' failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: trust_channel.php?saved=' . $savedCount);
    exit;
}

$showSaved = isset($_GET['saved']);
$savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

function faoxima_trust_toggle_on($cur, $on, $off) {
    if ($cur === null) return false;
    if ((string)$cur === (string)$on)  return true;
    if ((string)$cur === (string)$off) return false;
    return in_array(strtolower(trim((string)$cur)), ['1','on','true','yes'], true);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تنظیم کانال اعتماد | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat39">
    <script src="js/theme.js?v=dark62" defer></script>
    <style>
        .tc-card { max-width: 720px; margin: 0 auto; }
        .tc-savebar { max-width: 720px; margin: 0 auto; justify-content: flex-end; }
        #main-content .tc-card { padding: 24px; border: 1px solid var(--border-soft); border-radius: 22px; background: radial-gradient(ellipse at 100% 0%, var(--accent-soft), transparent 65%), var(--surface-1); }
        .tc-heading { display: flex; align-items: center; gap: 12px; padding-bottom: 20px; margin-bottom: 6px; border-bottom: 1px solid var(--border-soft); }
        .tc-heading__icon { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: 13px; color: var(--accent-ink); background: var(--accent-soft); }
        .tc-heading__icon .svg-icon { width: 24px; height: 24px; }
        .tc-heading strong { display: block; font-size: 15px; }
        .tc-heading p { margin: 5px 0 0; color: var(--text-muted); font-size: 11px; line-height: 1.8; }
        #main-content .tc-card .setting-row { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 17px 0; margin: 0; border-bottom: 1px solid var(--border-soft); }
        #main-content .tc-card .setting-row:last-child { border-bottom: 0; padding-bottom: 0; }
        #main-content .tc-card .setting-row__label { font-size: 13px; line-height: 1.8; cursor: pointer; }
        #main-content .tc-card .setting-row__control { flex: 0 0 auto; }
        #main-content .wrapper.fx-page-settings .tc-card .tc-row--channel { box-sizing: border-box; flex-direction: column; align-items: stretch; gap: 10px; padding: 16px; margin: 8px 0; border: 1px solid var(--border-soft); border-radius: 14px; background: var(--surface-2); }
        #main-content .wrapper.fx-page-settings .tc-card .tc-row--channel .setting-row__label { flex: 0 0 auto; margin: 0; font-size: 12px; line-height: 1.8; text-align: center; }
        #main-content .wrapper.fx-page-settings .tc-card .setting-row.tc-row--channel .setting-row__control { display: flex; flex-direction: column; align-items: stretch; gap: 10px; width: calc(100% - 32px); max-width: 560px; min-width: 0; margin: 0 auto; flex: 0 0 auto; }
        #main-content .wrapper.fx-page-settings .tc-card .tc-row--channel input[type="text"] { flex: 0 0 auto; width: 100%; height: 42px; min-height: 42px; margin: 0; padding: 10px 12px; font-size: 13px; }
        #main-content .tc-card input[type="text"] { box-sizing: border-box; width: 100%; min-height: 44px; padding: 11px 13px; color: var(--text-main); background: var(--surface-1); border: 1px solid var(--border-soft); border-radius: 10px; font-size: 13px; }
        #main-content .tc-card input[type="text"]:focus { border-color: var(--accent); outline: 2px solid var(--accent-soft); outline-offset: 2px; }
        #main-content .tc-card .tc-channel-hint { display: block; width: 100%; margin: 0; color: var(--text-muted); font-size: 10px; line-height: 1.8; text-align: center; overflow-wrap: anywhere; }
        #main-content .tc-card .switch:focus-within { outline: 2px solid var(--accent); outline-offset: 4px; border-radius: 30px; }
        #main-content .tc-savebar { padding: 18px 0; gap: 8px; }
        #main-content .tc-savebar .btn { min-height: 38px; border-radius: 10px; font-size: 12px; }
        @media (max-width: 600px) {
            #main-content .tc-card { padding: 18px; border-radius: 18px; }
            #main-content .tc-card .setting-row__label { font-size: 12px; }
            #main-content .wrapper.fx-page-settings .tc-card .tc-row--channel { padding: 12px; }
            .tc-heading strong { font-size: 14px; }
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
                        <?php echo icon('shield', 'svg-icon svg-lg'); ?>
                        تنظیم کانال اعتماد
                    </div>
                    <div class="page-head__sub">گزارش عمومی خریدهای موفق را به یک کانال تلگرامی ارسال کنید</div>
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

            <form method="POST" action="trust_channel.php" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="_save" value="1">

                <div class="card tc-card">
                    <div class="tc-heading">
                        <span class="tc-heading__icon" aria-hidden="true"><?php echo icon('shield', 'svg-icon'); ?></span>
                        <div><strong>گزارش خرید در کانال اعتماد</strong><p>کانال مقصد و رویدادهایی را که می‌خواهید منتشر شوند انتخاب کنید.</p></div>
                    </div>
                    <?php foreach ($FIELDS as $f):
                        $col = $f['col'];
                        $val = $settingRow[$col] ?? null;
                        $idAttr = 'f_' . htmlspecialchars($col, ENT_QUOTES);
                    ?>
                        <div class="setting-row<?php echo $f['type'] === 'text' ? ' tc-row--channel' : ''; ?>">
                            <label for="<?php echo $idAttr; ?>" class="setting-row__label">
                                <?php echo htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                            <div class="setting-row__control">
                                <?php if ($f['type'] === 'toggle'): ?>
                                    <label class="switch" title="<?php echo htmlspecialchars($col); ?>">
                                        <input type="checkbox" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                            <?php echo faoxima_trust_toggle_on($val, $f['on'], $f['off']) ? 'checked' : ''; ?>>
                                        <span class="switch__slot"></span>
                                    </label>
                                <?php else: ?>
                                    <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                        value="<?php echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                        style="direction:ltr; text-align:left;">
                                    <small class="tc-channel-hint">نام کاربری کانال با @ یا شناسهٔ عددی آن با پیشوند ‎-100 را وارد کنید.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="save-bar tc-savebar">
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

</body>
</html>
