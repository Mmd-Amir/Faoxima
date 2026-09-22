<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('default_charset', 'UTF-8');

if (!defined('RX_INSTALLER_RUNNING')) {
    define('RX_INSTALLER_RUNNING', true);
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
}

session_start();

require_once __DIR__ . '/includes/requirements.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php';

$rootDirectory = dirname(__DIR__) . '/';
$configDirectory = $rootDirectory . 'config.php';
$unhandledSecrets = [$_POST['tg_bot_token'] ?? '', $_POST['database_password'] ?? ''];
set_exception_handler(static function (Throwable $error) use ($rootDirectory, $unhandledSecrets): void {
    $errorId = rx_installer_log($rootDirectory, 'unhandled', $error, $unhandledSecrets);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>خطای نصب</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#07060b;color:#f5f3f8;font-family:Tahoma,sans-serif"><main style="width:min(520px,calc(100% - 32px));padding:32px;border:1px solid rgba(167,112,255,.2);border-radius:20px;background:#120e1b;text-align:center"><h1 style="font-size:22px">نصب تکمیل نشد</h1><p style="color:#aaa4b5">یک خطای پیش‌بینی‌نشده در اینستالر ثبت شد. صفحه را تازه‌سازی و دوباره تلاش کنید.</p><small style="color:#b77aff">شناسه خطا: ' . rx_escape_html($errorId) . '</small></main></body></html>';
});
$projectDepthInfo = rx_project_subdirectory_depth($rootDirectory);
$isRootExecution = $projectDepthInfo['depth'] <= 0;
$uPOST = rx_sanitize_input($_POST);
$rxAction = $uPOST['rx_action'] ?? '';

if (empty($_SESSION['rx_csrf'])) {
    $_SESSION['rx_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['rx_csrf'];
$installationLockOwner = $_SESSION['rx_installation_lock_owner'] ?? '';
if (!is_string($installationLockOwner) || !preg_match('/^[a-f0-9]{48}$/', $installationLockOwner)) {
    $installationLockOwner = bin2hex(random_bytes(24));
    $_SESSION['rx_installation_lock_owner'] = $installationLockOwner;
}

if (!$isRootExecution && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    rx_defer_installer_cleanup($rootDirectory);
    if (($_SESSION['rx_step'] ?? '') === 'success') {
        unset($_SESSION['rx_step'], $_SESSION['rx_success_messages'], $_SESSION['rx_bot_username']);
    }
}

if (!$isRootExecution && $rxAction === 'keepalive') {
    header('Content-Type: application/json; charset=utf-8');
    $csrfValid = isset($uPOST['csrf_token']) && hash_equals($csrfToken, (string) $uPOST['csrf_token']);
    $keptAlive = $csrfValid && rx_defer_installer_cleanup($rootDirectory);
    echo json_encode(['ok' => $keptAlive], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$isRootExecution && $rxAction === 'cleanup') {
    header('Content-Type: application/json; charset=utf-8');
    $csrfValid = isset($uPOST['csrf_token']) && hash_equals($csrfToken, (string) $uPOST['csrf_token']);
    $allowed = ($_SESSION['rx_step'] ?? '') === 'success' && $csrfValid;
    $cleaned = $allowed ? rx_cleanup_installer(__DIR__, 'installer_shutdown') : false;
    if ($allowed) {
        rx_release_installation_lock($rootDirectory, $installationLockOwner);
    }
    if ($cleaned) {
        unset(
            $_SESSION['rx_step'],
            $_SESSION['rx_success_messages'],
            $_SESSION['rx_bot_username'],
            $_SESSION['rx_requirement_checks'],
            $_SESSION['rx_requirements_passed'],
            $_SESSION['rx_installation_lock_owner']
        );
    }
    echo json_encode(['ok' => $cleaned], JSON_UNESCAPED_UNICODE);
    exit;
}

$ERROR = [];
$SUCCESS = [];
$errorStage = '';
$errorId = '';
$botUsername = '';
$successMessages = [];
$formValues = $uPOST;
unset($formValues['tg_bot_token'], $formValues['database_password'], $formValues['csrf_token']);

if ($isRootExecution) {
    $rootBlockedPath = $projectDepthInfo['path'];
    $rootBlockedDomain = $_SERVER['HTTP_HOST'] ?? '';
    $pageTitle = 'اجرای اینستالر در ریشه مجاز نیست';
    $activeView = 'root_blocked';
} else {
    $requirementChecks = rx_run_requirement_checks($rootDirectory);
    $_SESSION['rx_requirement_checks'] = $requirementChecks;
    $requirementsPassed = rx_requirement_checks_passed($requirementChecks);
    $_SESSION['rx_requirements_passed'] = $requirementsPassed;

    if ($rxAction === 'proceed' && $requirementsPassed) {
        $csrfValid = isset($uPOST['csrf_token']) && hash_equals($csrfToken, (string) $uPOST['csrf_token']);
        if ($csrfValid) {
            $_SESSION['rx_step'] = 'install';
        } else {
            $ERROR[] = 'نشست نصب معتبر نیست. صفحه را تازه‌سازی کنید.';
            $errorStage = 'security';
        }
    }

    if (($_SESSION['rx_step'] ?? '') === 'success' && rx_config_is_already_installed($configDirectory)) {
        $activeView = 'success';
        $successMessages = $_SESSION['rx_success_messages'] ?? [];
        $botUsername = $_SESSION['rx_bot_username'] ?? '';
    } else {
        $currentStepName = $_SESSION['rx_step'] ?? 'requirements';
        if ($currentStepName !== 'requirements' && !$requirementsPassed) {
            $currentStepName = 'requirements';
            $_SESSION['rx_step'] = 'requirements';
        }

        $tempPath = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/installer/index.php'));
        $tempPath = str_replace('//', '/', '/' . trim($tempPath, '/'));
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $webAddress = rtrim($host . $tempPath, '/') . '/';
        $defaultWebhookAddress = 'https://' . $webAddress . 'index.php';

        if ($currentStepName === 'install' && !is_file($configDirectory)) {
            $ERROR[] = 'فایل config.php در پروژه یافت نشد.';
            $errorStage = 'config';
        }

        if ($currentStepName === 'install' && $rxAction === 'install' && empty($ERROR)) {
            $csrfValid = isset($uPOST['csrf_token']) && hash_equals($csrfToken, (string) $uPOST['csrf_token']);
            $tgAdminId = (string) ($uPOST['admin_id'] ?? '');
            $tgBotToken = (string) ($uPOST['tg_bot_token'] ?? '');
            $dbInfo = [
                'host' => (string) ($uPOST['database_host'] ?? (getenv('DB_HOST') ?: 'localhost')),
                'name' => (string) ($uPOST['database_name'] ?? ''),
                'username' => (string) ($uPOST['database_username'] ?? ''),
                'password' => (string) ($uPOST['database_password'] ?? ''),
            ];
            $inputUrl = (string) ($uPOST['bot_address_webhook'] ?? $defaultWebhookAddress);
            $secrets = [$tgBotToken, $dbInfo['password']];
            $document = rx_normalize_domain_address($inputUrl);
            $tgBot = [];
            $configBackup = null;
            $configWritten = false;
            $webhookRegistered = false;
            $installationLockHeld = false;
            $cleanupDeferred = false;

            if (!$csrfValid) {
                $ERROR[] = 'نشست نصب منقضی شده است. صفحه را تازه‌سازی کنید.';
                $errorStage = 'security';
            } elseif (!rx_is_https() || !preg_match('#^https://#i', $inputUrl)) {
                $ERROR[] = 'اینستالر و آدرس وب‌هوک باید با HTTPS در دسترس باشند.';
                $errorStage = 'webhook';
            } elseif ($document === null || $host === '') {
                $ERROR[] = 'آدرس وب‌هوک معتبر نیست.';
                $errorStage = 'webhook';
            } elseif (!rx_is_valid_telegram_token($tgBotToken)) {
                $ERROR[] = 'قالب توکن ربات تلگرام معتبر نیست.';
                $errorStage = 'telegram';
            } elseif (!rx_is_valid_telegram_id($tgAdminId)) {
                $ERROR[] = 'آیدی عددی مدیر معتبر نیست.';
                $errorStage = 'telegram';
            } elseif (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $dbInfo['name'])) {
                $ERROR[] = 'نام دیتابیس معتبر نیست.';
                $errorStage = 'database';
            } elseif (!preg_match('/^[A-Za-z0-9.\-:\[\]]{1,255}$/', $dbInfo['host'])) {
                $ERROR[] = 'میزبان دیتابیس معتبر نیست.';
                $errorStage = 'database';
            } elseif ($dbInfo['username'] === '' || strlen($dbInfo['username']) > 128) {
                $ERROR[] = 'نام کاربری دیتابیس معتبر نیست.';
                $errorStage = 'database';
            }

            if (empty($ERROR)) {
                $tgBot['details'] = rx_telegram_request($tgBotToken, 'getMe');
                if (empty($tgBot['details']['ok']) || empty($tgBot['details']['result']['username'])) {
                    $ERROR[] = 'اتصال به ربات تلگرام ناموفق بود. توکن یا دسترسی شبکه را بررسی کنید.';
                    $errorStage = 'telegram';
                } else {
                    $tgBot['recognition'] = rx_telegram_request($tgBotToken, 'getChat', ['chat_id' => $tgAdminId]);
                    if (empty($tgBot['recognition']['ok'])) {
                        $ERROR[] = 'مدیر در تلگرام شناسایی نشد. ابتدا ربات را با حساب مدیر Start کنید.';
                        $errorStage = 'telegram';
                    } else {
                        $SUCCESS[] = 'ربات تلگرام و مدیر تأیید شدند';
                    }
                }
            }

            if (empty($ERROR)) {
                try {
                    $pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 10];
                    try {
                        $pdo = new PDO('mysql:host=' . $dbInfo['host'] . ';dbname=' . $dbInfo['name'] . ';charset=utf8mb4', $dbInfo['username'], $dbInfo['password'], $pdoOptions);
                    } catch (PDOException $databaseError) {
                        $serverPdo = new PDO('mysql:host=' . $dbInfo['host'] . ';charset=utf8mb4', $dbInfo['username'], $dbInfo['password'], $pdoOptions);
                        $quotedName = str_replace('`', '``', $dbInfo['name']);
                        $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . $quotedName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                        $pdo = new PDO('mysql:host=' . $dbInfo['host'] . ';dbname=' . $dbInfo['name'] . ';charset=utf8mb4', $dbInfo['username'], $dbInfo['password'], $pdoOptions);
                    }
                    $pdo->query('SELECT 1');
                    $pdo = null;
                    $SUCCESS[] = 'اتصال دیتابیس تأیید شد';
                } catch (Throwable $databaseError) {
                    $errorId = rx_installer_log($rootDirectory, 'database', $databaseError, $secrets);
                    $ERROR[] = 'اتصال به دیتابیس برقرار نشد. اطلاعات و سطح دسترسی کاربر را بررسی کنید.';
                    $errorStage = 'database';
                }
            }

            if (empty($ERROR)) {
                $installationLockHeld = rx_acquire_installation_lock($rootDirectory, $installationLockOwner);
                if (!$installationLockHeld) {
                    $ERROR[] = 'یک فرآیند نصب دیگر در حال اجراست. چند دقیقه دیگر دوباره تلاش کنید.';
                    $errorStage = 'security';
                } else {
                    $cleanupDeferred = rx_defer_installer_cleanup($rootDirectory);
                    if (!$cleanupDeferred) {
                        $ERROR[] = 'ایمن‌سازی موقت فایل‌های اینستالر ممکن نبود.';
                        $errorStage = 'security';
                    }
                }
            }

            if (empty($ERROR)) {
                $rawConfigData = @file_get_contents($configDirectory);
                if ($rawConfigData === false) {
                    $ERROR[] = 'خواندن فایل config.php ممکن نبود.';
                    $errorStage = 'config';
                } else {
                    $configBackup = $rawConfigData;
                    $replacementValues = [
                        '{database_name}' => $dbInfo['name'],
                        '{username_db}' => $dbInfo['username'],
                        '{password_db}' => $dbInfo['password'],
                        '{db_host}' => $dbInfo['host'],
                        '{API_KEY}' => $tgBotToken,
                        '{admin_number}' => $tgAdminId,
                        '{domain_name}' => $document['address'],
                        '{username_bot}' => $tgBot['details']['result']['username'],
                    ];
                    $expectedConfig = [
                        'dbname' => $dbInfo['name'],
                        'usernamedb' => $dbInfo['username'],
                        'passworddb' => $dbInfo['password'],
                        'dbhost' => $dbInfo['host'],
                        'APIKEY' => $tgBotToken,
                        'adminnumber' => $tgAdminId,
                        'domainhosts' => $document['address'],
                        'usernamebot' => $tgBot['details']['result']['username'],
                    ];
                    $replacementCount = 0;
                    $newConfigData = rx_update_config_values($rawConfigData, $replacementValues, $replacementCount);
                    $configValidation = rx_validate_config_source($newConfigData, $expectedConfig);
                    if (!$configValidation['ok'] || $replacementCount < count($expectedConfig)) {
                        $ERROR[] = 'تولید فایل تنظیمات کامل و معتبر نبود.';
                        $errorStage = 'config';
                    } else {
                        $writeResult = rx_write_config_atomically($configDirectory, $newConfigData);
                        if (!$writeResult['ok']) {
                            $ERROR[] = 'ذخیره امن فایل تنظیمات ناموفق بود.';
                            $errorStage = 'config';
                        } else {
                            $configWritten = true;
                            $SUCCESS[] = 'فایل تنظیمات اعتبارسنجی و ذخیره شد';
                        }
                    }
                }
            }

            if (empty($ERROR)) {
                $migrationResult = rx_run_table_migrations($rootDirectory, $dbInfo);
                if (!$migrationResult['ok']) {
                    $errorId = rx_installer_log($rootDirectory, 'migration', $migrationResult['message'], $secrets);
                    $ERROR[] = 'اجرای migration یا تأیید ساختار دیتابیس ناموفق بود.';
                    $errorStage = 'migration';
                } else {
                    $SUCCESS[] = 'ساختار دیتابیس ایجاد و تأیید شد';
                }
            }

            if (empty($ERROR)) {
                if (!rx_ensure_admin_record($dbInfo, $tgAdminId)) {
                    $errorId = rx_installer_log($rootDirectory, 'admin', 'Admin record could not be created or verified.', $secrets);
                    $ERROR[] = 'ایجاد یا تأیید حساب مدیر ناموفق بود.';
                    $errorStage = 'admin';
                } else {
                    $SUCCESS[] = 'حساب مدیر ایجاد و تأیید شد';
                }
            }

            if (empty($ERROR)) {
                $webhookUrl = 'https://' . $document['address'] . '/index.php';
                $webhookSecret = rx_telegram_webhook_secret($rootDirectory, $tgBotToken);
                if ($webhookSecret === null) {
                    $errorId = rx_installer_log($rootDirectory, 'webhook_secret', 'Webhook secret token could not be generated.', $secrets);
                    $ERROR[] = 'تولید کلید امنیتی وب‌هوک ناموفق بود.';
                    $errorStage = 'webhook';
                } else {
                    $setWebhook = rx_telegram_request($tgBotToken, 'setWebhook', [
                        'url' => $webhookUrl,
                        'secret_token' => $webhookSecret,
                        'drop_pending_updates' => false,
                    ]);
                }
                if (empty($ERROR) && empty($setWebhook['ok'])) {
                    $description = rx_safe_error_message($setWebhook['description'] ?? 'Telegram rejected setWebhook.', $secrets);
                    $errorId = rx_installer_log($rootDirectory, 'webhook_register', $description, $secrets);
                    $ERROR[] = 'ثبت وب‌هوک در تلگرام ناموفق بود: ' . $description;
                    $errorStage = 'webhook';
                } elseif (empty($ERROR)) {
                    $webhookRegistered = true;
                    $webhookInfo = rx_telegram_request($tgBotToken, 'getWebhookInfo');
                    $registeredUrl = (string) ($webhookInfo['result']['url'] ?? '');
                    if (empty($webhookInfo['ok']) || rtrim($registeredUrl, '/') !== rtrim($webhookUrl, '/')) {
                        rx_telegram_request($tgBotToken, 'deleteWebhook');
                        $webhookRegistered = false;
                        $errorId = rx_installer_log($rootDirectory, 'webhook_verify', 'Telegram webhook URL verification failed.', $secrets);
                        $ERROR[] = 'تلگرام ثبت وب‌هوک را تأیید نکرد.';
                        $errorStage = 'webhook';
                    } else {
                        $SUCCESS[] = 'وب‌هوک در تلگرام ثبت و تأیید شد';
                    }
                }
            }

            if (!empty($ERROR) && $configWritten && is_string($configBackup)) {
                if ($webhookRegistered) {
                    rx_telegram_request($tgBotToken, 'deleteWebhook');
                }
                $rollback = rx_write_config_atomically($configDirectory, $configBackup);
                if (!$rollback['ok']) {
                    $rollbackId = rx_installer_log($rootDirectory, 'config_rollback', $rollback['message'], $secrets);
                    $ERROR[] = 'بازگردانی config.php ناموفق بود. شناسه خطا: ' . $rollbackId;
                }
            }

            if (!empty($ERROR) && $installationLockHeld) {
                if ($cleanupDeferred) {
                    rx_cancel_installer_cleanup_deferral($rootDirectory);
                    $cleanupDeferred = false;
                }
                rx_release_installation_lock($rootDirectory, $installationLockOwner);
                $installationLockHeld = false;
            }

            if (empty($ERROR)) {
                $botUsername = (string) $tgBot['details']['result']['username'];
                $SUCCESS[] = 'نصب با موفقیت تکمیل شد';
                rx_telegram_request($tgBotToken, 'sendMessage', [
                    'chat_id' => $tgAdminId,
                    'text' => 'نصب فاکسیما با موفقیت انجام شد و شما به عنوان مدیر اصلی ثبت شدید.',
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => 'شروع ربات', 'callback_data' => 'start']]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                $_SESSION['rx_step'] = 'success';
                $_SESSION['rx_success_messages'] = $SUCCESS;
                $_SESSION['rx_bot_username'] = $botUsername;
                $successMessages = $SUCCESS;
                $activeView = 'success';
            } else {
                if ($errorId === '') {
                    $errorId = rx_installer_log($rootDirectory, $errorStage !== '' ? $errorStage : 'install', implode(' | ', $ERROR), $secrets);
                }
                $activeView = 'install';
            }
        } else {
            $activeView = $currentStepName;
        }
    }
    $pageTitle = 'نصب و راه‌اندازی فاکسیما';
}
$assetVersion = (string) max(
    (int) @filemtime(__DIR__ . '/assets/installer.css'),
    (int) @filemtime(__DIR__ . '/assets/installer.js')
);
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa" data-theme="purple">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#07060b">
    <title><?php echo rx_escape_html($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/installer.css?v=<?php echo rawurlencode($assetVersion); ?>">
</head>
<body data-error-stage="<?php echo rx_escape_html($errorStage); ?>" data-keepalive-token="<?php echo rx_escape_html($csrfToken); ?>">
<?php echo rx_icon_sprite(); ?>
<div class="ambient ambient-one"></div>
<div class="ambient ambient-two"></div>
<div class="app-shell">
    <header class="app-header">
        <a class="brand" href="./" aria-label="فاکسیما">
            <span class="brand-mark"><img src="assets/img/faoxima.jpg" alt=""></span>
            <span class="brand-copy"><strong>فاکسیما</strong><small>راه‌اندازی امن ربات</small></span>
        </a>
        <span class="installer-label"><svg><use href="#i-terminal"/></svg> Installer</span>
    </header>
    <main class="app-main">
        <div class="hero">
            <span class="eyebrow">راه‌اندازی مرحله‌به‌مرحله</span>
            <h1>نصب و راه‌اندازی فاکسیما</h1>
            <p>چند مرحله کوتاه تا ربات شما با تنظیمات امن و بررسی‌شده آماده استفاده شود.</p>
        </div>

        <?php if ($isRootExecution): ?>
            <?php require __DIR__ . '/steps/root_blocked.php'; ?>
        <?php else: ?>
            <div class="setup-layout" data-server-view="<?php echo rx_escape_html($activeView); ?>">
                <aside class="setup-sidebar" aria-label="مراحل نصب">
                    <div class="sidebar-heading">مراحل نصب <span id="sidebar-progress-label"><?php echo $activeView === 'success' ? '۴ از ۴' : ($activeView === 'requirements' ? '۱ از ۴' : '۲ از ۴'); ?></span></div>
                    <div class="sidebar-progress"><span id="sidebar-progress-fill" style="width:<?php echo $activeView === 'success' ? '100' : ($activeView === 'requirements' ? '25' : '50'); ?>%"></span></div>
                    <ol class="setup-steps">
                        <?php
                        $serverStep = $activeView === 'success' ? 4 : ($activeView === 'requirements' ? 1 : 2);
                        $steps = [
                            [1, 'i-server', 'بررسی سیستم', 'پیش‌نیازها و دسترسی‌ها'],
                            [2, 'i-telegram', 'تلگرام', 'ربات و مدیر اصلی'],
                            [3, 'i-database', 'دیتابیس', 'اتصال و اطلاعات پایگاه‌داده'],
                            [4, 'i-rocket', 'مرور و نصب', 'تأیید نهایی و راه‌اندازی'],
                        ];
                        foreach ($steps as $step):
                            $state = $step[0] < $serverStep ? 'is-complete' : ($step[0] === $serverStep ? 'is-active' : '');
                        ?>
                            <li class="setup-step <?php echo $state; ?>" data-nav-step="<?php echo $step[0]; ?>">
                                <span class="step-icon"><svg><use href="#<?php echo $step[0] < $serverStep ? 'i-check' : $step[1]; ?>"/></svg></span>
                                <span><strong><?php echo $step[2]; ?></strong><small><?php echo $step[3]; ?></small></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <div class="sidebar-security"><svg><use href="#i-shield-check"/></svg><span><strong>نصب امن</strong><small>اطلاعات حساس در خروجی نمایش داده نمی‌شود.</small></span></div>
                </aside>
                <section class="setup-content">
                    <?php if (!empty($ERROR)): ?>
                        <div class="alert alert-danger" role="alert">
                            <svg><use href="#i-x-circle"/></svg>
                            <div><strong>نصب تکمیل نشد</strong><?php foreach ($ERROR as $message): ?><span><?php echo rx_escape_html($message); ?></span><?php endforeach; ?><?php if ($errorId !== ''): ?><small>مرحله: <?php echo rx_escape_html($errorStage); ?> · شناسه خطا: <bdi><?php echo rx_escape_html($errorId); ?></bdi></small><?php endif; ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($activeView === 'requirements'): ?>
                        <?php require __DIR__ . '/steps/requirements.php'; ?>
                    <?php elseif ($activeView === 'install'): ?>
                        <?php require __DIR__ . '/steps/install_form.php'; ?>
                    <?php elseif ($activeView === 'success'): ?>
                        <?php require __DIR__ . '/steps/success.php'; ?>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
    <footer class="app-footer"><span>Faoxima Installer</span><span>راه‌اندازی ساده، امن و قابل بررسی</span></footer>
</div>
<div class="install-loading" id="install-loading" hidden aria-live="polite">
    <div class="loading-panel"><span class="loading-orbit"><svg><use href="#i-cog"/></svg></span><h2>در حال آماده‌سازی ربات…</h2><p>درخواست واقعی نصب در حال پردازش است. این صفحه را نبندید.</p><div class="loading-line"><span></span></div></div>
</div>
<script src="assets/installer.js?v=<?php echo rawurlencode($assetVersion); ?>"></script>
</body>
</html>
