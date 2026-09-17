<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../re/rx/function/database_helpers_1.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
$query->execute([':username' => $_SESSION['user'] ?? '']);
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION['user']) || !$adminRow) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$fields = [
    'receipt_topic_reporting' => 'ارسال رسید کارت‌به‌کارت به تاپیک گزارش',
    'subscription_link_button' => 'Get Subscription Link Button',
];

$values = array_fill_keys(array_keys($fields), '1');
$row = $pdo->query('SELECT receipt_topic_reporting, subscription_link_button FROM setting LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (is_array($row)) {
    foreach ($values as $key => $default) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            $values[$key] = (string)$row[$key];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incoming = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $incoming)) {
        http_response_code(403);
        exit('درخواست نامعتبر — توکن CSRF اشتباه است');
    }
    $newValues = [];
    foreach ($fields as $key => $label) {
        $newValues[$key] = isset($_POST['f_' . $key]) ? '1' : '0';
    }
    $stmt = $pdo->prepare('UPDATE setting SET receipt_topic_reporting = :receipt, subscription_link_button = :subscription');
    $stmt->execute([
        ':receipt' => $newValues['receipt_topic_reporting'],
        ':subscription' => $newValues['subscription_link_button'],
    ]);
    if (function_exists('faoxima_bust_bot_selectcache')) {
        faoxima_bust_bot_selectcache('setting');
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('setting');
    }
    header('Location: miscsettings.php?saved=1');
    exit;
}

function faoxima_misc_enabled($value): bool
{
    return !in_array(strtolower(trim((string)$value)), ['0', 'off', 'false', 'no'], true);
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تنظیمات متفرقه | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat32">
    <script src="js/theme.js?v=flat5" defer></script>
</head>
<body>
<section id="container">
    <?php include 'header.php'; ?>
    <section id="main-content">
        <div class="wrapper">
            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('sliders', 'svg-icon svg-lg'); ?>
                        تنظیمات متفرقه
                    </div>
                    <div class="page-head__sub">تنظیم رفتار گزارش رسید و تحویل لینک سرویس</div>
                </div>
            </div>
            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span>تنظیمات با موفقیت ذخیره شد.</span>
                </div>
            <?php endif; ?>
            <form method="POST" action="miscsettings.php" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="setting-grid">
                    <div class="card">
                        <div class="card__head">
                            <div class="card__title">
                                <?php echo icon('sliders', 'svg-icon svg-md'); ?>
                                <span>رفتارهای اختیاری</span>
                            </div>
                        </div>
                        <?php foreach ($fields as $key => $label): ?>
                            <div class="setting-row">
                                <label for="f_<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="setting-row__label">
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </label>
                                <div class="setting-row__control">
                                    <label class="switch">
                                        <input type="checkbox" id="f_<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" name="f_<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo faoxima_misc_enabled($values[$key]) ? 'checked' : ''; ?>>
                                        <span class="switch__slot"></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="save-bar">
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
