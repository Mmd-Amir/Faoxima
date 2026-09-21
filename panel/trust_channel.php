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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_csrf = $_SESSION['csrf_token'];

$FIELDS = [
    ['type' => 'toggle', 'col' => 'PublicLog_Status',        'label' => 'فعال‌سازی گزارش عمومی خرید', 'on' => '1', 'off' => '0'],
    ['type' => 'text',   'col' => 'PublicLog_Channel',       'label' => 'آیدی کانال اعتماد',           'placeholder' => '@channel یا -100…'],
    ['type' => 'toggle', 'col' => 'PublicLog_NewSub',        'label' => 'خرید اشتراک جدید',            'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_Renewal',       'label' => 'تمدید سرویس',                 'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_VolumeTopup',   'label' => 'افزایش حجم',                  'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_TimeExtra',     'label' => 'افزایش زمان',                 'on' => '1', 'off' => '0'],
    ['type' => 'toggle', 'col' => 'PublicLog_WalletDeposit', 'label' => 'شارژ کیف پول',                'on' => '1', 'off' => '0'],
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
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تنظیم کانال اعتماد | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat32">
    <script src="js/theme.js?v=flat50" defer></script>
    <style>
        .tc-card { max-width: 720px; margin: 0 auto; }
        .tc-savebar { max-width: 720px; margin: 0 auto; justify-content: flex-end; }
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
                    <?php foreach ($FIELDS as $f):
                        $col = $f['col'];
                        $val = $settingRow[$col] ?? null;
                        $idAttr = 'f_' . htmlspecialchars($col, ENT_QUOTES);
                    ?>
                        <div class="setting-row">
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
