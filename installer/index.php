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
$installGuard = ['active' => false, 'stage' => '', 'configBackup' => null, 'configWritten' => false];
$rxRollbackGuardedConfig = static function () use ($rootDirectory, $configDirectory, $unhandledSecrets, &$installGuard): void {
    if ($installGuard['configWritten'] && is_string($installGuard['configBackup'])) {
        $rollback = rx_write_config_atomically($configDirectory, $installGuard['configBackup']);
        if ($rollback['ok']) {
            $installGuard['configWritten'] = false;
        } else {
            rx_installer_log($rootDirectory, 'config_rollback', $rollback['message'], $unhandledSecrets);
        }
    }
};
set_exception_handler(static function (Throwable $error) use ($rootDirectory, $unhandledSecrets, &$installGuard, $rxRollbackGuardedConfig): void {
    $stage = $installGuard['active'] && $installGuard['stage'] !== '' ? $installGuard['stage'] : 'unhandled';
    $errorId = rx_installer_log($rootDirectory, $stage, $error, $unhandledSecrets);
    if ($installGuard['active']) {
        $installGuard['active'] = false;
        $rxRollbackGuardedConfig();
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    rx_render_failure_page($stage, $errorId, 'یک خطای پیش‌بینی‌نشده در اینستالر ثبت شد. در صورت ذخیره، فایل تنظیمات به نسخه قبلی بازگردانده شد؛ صفحه را تازه‌سازی و دوباره تلاش کنید.');
});
register_shutdown_function(static function () use ($rootDirectory, $unhandledSecrets, &$installGuard, $rxRollbackGuardedConfig): void {
    if (!$installGuard['active']) {
        return;
    }
    $installGuard['active'] = false;
    $stage = $installGuard['stage'] !== '' ? $installGuard['stage'] : 'install';
    $lastError = error_get_last();
    $isFatal = is_array($lastError) && in_array($lastError['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    $detail = $isFatal ? (string) ($lastError['message'] ?? '') : 'Installation request ended before completion.';
    $timedOut = $isFatal && stripos($detail, 'Maximum execution time') !== false;
    $errorId = rx_installer_log($rootDirectory, $stage, $detail, $unhandledSecrets);
    $rxRollbackGuardedConfig();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    rx_render_failure_page(
        $stage,
        $errorId,
        $timedOut
            ? 'زمان مجاز اجرای درخواست روی هاست به پایان رسید و نصب متوقف شد. در صورت ذخیره، فایل تنظیمات به نسخه قبلی بازگردانده شد و می‌توانید دوباره تلاش کنید.'
            : 'فرآیند نصب پیش از تکمیل متوقف شد. در صورت ذخیره، فایل تنظیمات به نسخه قبلی بازگردانده شد و می‌توانید دوباره تلاش کنید.'
    );
});
$projectDepthInfo = rx_project_subdirectory_depth($rootDirectory);
$isRootExecution = $projectDepthInfo['depth'] <= 0;
$uPOST = rx_sanitize_input($_POST);
$rxAction = $uPOST['rx_action'] ?? '';

if (empty($_SESSION['rx_csrf'])) {
    $_SESSION['rx_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['rx_csrf'];

if (!$isRootExecution && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    rx_defer_installer_cleanup($rootDirectory);
    if (($_SESSION['rx_step'] ?? '') === 'success' && !rx_config_is_already_installed($configDirectory)) {
        unset($_SESSION['rx_step'], $_SESSION['rx_success_messages'], $_SESSION['rx_bot_username']);
    }
}

if (!$isRootExecution && $rxAction === 'keepalive') {
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    $csrfValid = isset($uPOST['csrf_token']) && hash_equals($csrfToken, (string) $uPOST['csrf_token']);
    $keptAlive = $csrfValid && rx_defer_installer_cleanup($rootDirectory);
    echo json_encode(['ok' => $keptAlive], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$isRootExecution && $rxAction === 'cleanup') {
    header('Content-Type: application/json; charset=utf-8');
    $csrfValid = isset($uPOST['csrf_token']) && hash_equals($csrfToken, (string) $uPOST['csrf_token']);
    $allowed = ($_SESSION['rx_step'] ?? '') === 'success' && $csrfValid && rx_config_is_already_installed($configDirectory);
    $cleaned = $allowed ? rx_cleanup_installer(__DIR__, 'installer_shutdown') : false;
    if ($allowed && !rx_installation_lock_is_busy($rootDirectory)) {
        @unlink(rx_installation_lock_path($rootDirectory));
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
$installationBusy = false;
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
            $configWasInstalled = false;
            $migrationStarted = false;
            $webhookRegistered = false;
            $installationLock = null;
            $installationLockHeld = false;
            $cleanupDeferred = false;
            $webhookSecret = null;
            $webhookUrl = '';

            if (!$csrfValid) {
                $ERROR[] = 'نشست نصب منقضی شده است. صفحه را تازه‌سازی کنید.';
                $errorStage = 'security';
            } elseif (!rx_is_valid_telegram_token($tgBotToken)) {
                $ERROR[] = 'قالب توکن ربات تلگرام معتبر نیست.';
                $errorStage = 'telegram';
            } elseif (!rx_is_valid_telegram_id($tgAdminId)) {
                $ERROR[] = 'آیدی عددی مدیر معتبر نیست.';
                $errorStage = 'telegram';
            } elseif (!rx_is_https() || !preg_match('#^https://#i', $inputUrl)) {
                $ERROR[] = 'اینستالر و آدرس وب‌هوک باید با HTTPS در دسترس باشند.';
                $errorStage = 'webhook';
            } elseif ($document === null || $host === '') {
                $ERROR[] = 'آدرس وب‌هوک معتبر نیست.';
                $errorStage = 'webhook';
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
                session_write_close();
                if (function_exists('ignore_user_abort')) {
                    @ignore_user_abort(true);
                }
                if (function_exists('set_time_limit')) {
                    @set_time_limit(300);
                }
                $lockResult = rx_acquire_installation_lock($rootDirectory);
                if ($lockResult['busy']) {
                    $ERROR[] = 'یک درخواست نصب دیگر هم‌اکنون روی سرور در حال اجراست (مثلاً به‌دلیل ارسال دوباره فرم یا تازه‌سازی صفحه). چند لحظه صبر کنید و صفحه را تازه‌سازی کنید؛ اگر آن درخواست موفق شده باشد صفحه پایان نصب نمایش داده می‌شود.';
                    $errorStage = 'security';
                } else {
                    $installationLock = $lockResult['handle'];
                    $installationLockHeld = $lockResult['acquired'];
                    $installGuard['active'] = true;
                }
            }

            if (empty($ERROR)) {
                try {
                    $installGuard['stage'] = 'telegram';
                    $tgBot['details'] = rx_telegram_request($tgBotToken, 'getMe');
                    if (empty($tgBot['details']['ok']) || empty($tgBot['details']['result']['username'])) {
                        $description = rx_telegram_error_description($tgBot['details'], $secrets);
                        $ERROR[] = !empty($tgBot['details']['transport_error'])
                            ? 'ارتباط با سرور تلگرام برقرار نشد: ' . $description . ' دسترسی هاست به api.telegram.org را بررسی و دوباره تلاش کنید.'
                            : 'توکن ربات توسط تلگرام پذیرفته نشد: ' . $description;
                        $errorStage = 'telegram';
                    } else {
                        $tgBot['recognition'] = rx_telegram_request($tgBotToken, 'getChat', ['chat_id' => $tgAdminId]);
                        if (empty($tgBot['recognition']['ok'])) {
                            $description = rx_telegram_error_description($tgBot['recognition'], $secrets);
                            $ERROR[] = !empty($tgBot['recognition']['transport_error'])
                                ? 'ارتباط با سرور تلگرام برقرار نشد: ' . $description . ' دوباره تلاش کنید.'
                                : 'مدیر در تلگرام شناسایی نشد. ابتدا ربات @' . $tgBot['details']['result']['username'] . ' را با حساب مدیر Start کنید. پاسخ تلگرام: ' . $description;
                            $errorStage = 'telegram';
                        } else {
                            $SUCCESS[] = 'ربات تلگرام و مدیر تأیید شدند';
                        }
                    }

                    if (empty($ERROR)) {
                        $installGuard['stage'] = 'database';
                        try {
                            $databaseConnection = rx_database_connect($dbInfo);
                            $databaseConnection->query('SELECT 1');
                            $databaseConnection = null;
                            $SUCCESS[] = 'اتصال دیتابیس تأیید شد';
                        } catch (Throwable $databaseError) {
                            $databaseConnection = null;
                            $errorId = rx_installer_log($rootDirectory, 'database', $databaseError, $secrets);
                            $ERROR[] = rx_database_error_message($databaseError);
                            $errorStage = 'database';
                        }
                    }

                    if (empty($ERROR)) {
                        $installGuard['stage'] = 'cleanup';
                        $cleanupDeferred = rx_defer_installer_cleanup($rootDirectory);
                        if (!$cleanupDeferred) {
                            $ERROR[] = 'نوشتن فایل موقت در پوشه logs ممکن نبود. دسترسی نوشتن پوشه logs را بررسی کنید.';
                            $errorStage = 'cleanup';
                        }
                    }

                    if (empty($ERROR)) {
                        $installGuard['stage'] = 'config';
                        $rawConfigData = @file_get_contents($configDirectory);
                        if ($rawConfigData === false) {
                            $ERROR[] = 'خواندن فایل config.php ممکن نبود.';
                            $errorStage = 'config';
                        } else {
                            $configBackup = $rawConfigData;
                            $configWasInstalled = rx_config_is_already_installed($configDirectory);
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
                                $ERROR[] = 'تولید فایل تنظیمات کامل و معتبر نبود. ' . $configValidation['message'];
                                $errorStage = 'config';
                            } else {
                                $installGuard['configBackup'] = $configBackup;
                                $writeResult = rx_write_config_atomically($configDirectory, $newConfigData);
                                if (!$writeResult['ok']) {
                                    $ERROR[] = 'ذخیره امن فایل تنظیمات ناموفق بود. ' . $writeResult['message'];
                                    $errorStage = 'config';
                                } else {
                                    $configWritten = true;
                                    $installGuard['configWritten'] = true;
                                    $SUCCESS[] = 'فایل تنظیمات اعتبارسنجی و ذخیره شد';
                                }
                            }
                        }
                    }

                    if (empty($ERROR)) {
                        $installGuard['stage'] = 'migration';
                        $migrationStarted = true;
                        $migrationResult = rx_run_table_migrations($rootDirectory, $dbInfo);
                        if (!$migrationResult['ok']) {
                            $errorId = rx_installer_log($rootDirectory, 'migration', $migrationResult['message'], $secrets);
                            $ERROR[] = mb_substr(rx_safe_error_message($migrationResult['message'], $secrets), 0, 600, 'UTF-8');
                            $errorStage = 'migration';
                        } else {
                            $SUCCESS[] = 'ساختار دیتابیس ایجاد و تأیید شد';
                        }
                    }

                    if (empty($ERROR)) {
                        $installGuard['stage'] = 'admin';
                        $adminResult = rx_ensure_admin_record($dbInfo, $tgAdminId);
                        if (!$adminResult['ok']) {
                            $errorId = rx_installer_log($rootDirectory, 'admin', $adminResult['message'], $secrets);
                            $ERROR[] = 'ایجاد یا تأیید حساب مدیر ناموفق بود: ' . mb_substr(rx_safe_error_message($adminResult['message'], $secrets), 0, 300, 'UTF-8');
                            $errorStage = 'admin';
                        } else {
                            $SUCCESS[] = $adminResult['created'] ? 'حساب مدیر اصلی ایجاد شد' : 'حساب مدیر اصلی تأیید شد';
                        }
                    }

                    if (empty($ERROR)) {
                        $installGuard['stage'] = 'webhook';
                        $webhookUrl = 'https://' . $document['address'] . '/index.php';
                        $webhookSecret = rx_telegram_webhook_secret($rootDirectory, $tgBotToken);
                        if ($webhookSecret === null) {
                            $errorId = rx_installer_log($rootDirectory, 'webhook', 'Webhook secret token could not be generated.', $secrets);
                            $ERROR[] = 'تولید کلید امنیتی وب‌هوک ناموفق بود. فایل lib/WebhookAuth.php را بررسی کنید.';
                            $errorStage = 'webhook';
                        } else {
                            $setWebhook = rx_telegram_request($tgBotToken, 'setWebhook', [
                                'url' => $webhookUrl,
                                'secret_token' => $webhookSecret,
                                'drop_pending_updates' => 'false',
                            ]);
                            if (empty($setWebhook['ok'])) {
                                $description = rx_telegram_error_description($setWebhook, array_merge($secrets, [$webhookSecret]));
                                $errorId = rx_installer_log($rootDirectory, 'webhook', 'setWebhook failed: ' . $description, $secrets);
                                $ERROR[] = 'ثبت وب‌هوک در تلگرام ناموفق بود: ' . $description;
                                $errorStage = 'webhook';
                            } else {
                                $webhookRegistered = true;
                                $webhookWarning = '';
                                for ($verifyAttempt = 1; $verifyAttempt <= 2; $verifyAttempt++) {
                                    $webhookInfo = rx_telegram_request($tgBotToken, 'getWebhookInfo', [], 10);
                                    $registeredUrl = (string) ($webhookInfo['result']['url'] ?? '');
                                    if (!empty($webhookInfo['ok']) && rtrim($registeredUrl, '/') === rtrim($webhookUrl, '/')) {
                                        $webhookWarning = '';
                                        break;
                                    }
                                    $webhookWarning = !empty($webhookInfo['ok'])
                                        ? 'هشدار: آدرس وب‌هوکی که تلگرام گزارش کرد با آدرس ثبت‌شده یکسان نبود؛ وضعیت وب‌هوک را بعداً بررسی کنید.'
                                        : 'هشدار: وب‌هوک ثبت شد اما تأیید وضعیت آن به‌طور موقت ممکن نشد؛ وضعیت وب‌هوک را بعداً بررسی کنید.';
                                    if ($verifyAttempt < 2) {
                                        sleep(1);
                                    }
                                }
                                if ($webhookWarning === '') {
                                    $SUCCESS[] = 'وب‌هوک در تلگرام ثبت و تأیید شد';
                                } else {
                                    rx_installer_log($rootDirectory, 'webhook_verify', $webhookWarning, $secrets);
                                    $SUCCESS[] = 'وب‌هوک در تلگرام ثبت شد';
                                    $SUCCESS[] = $webhookWarning;
                                }
                            }
                        }
                    }
                } catch (Throwable $installError) {
                    $failedStage = $installGuard['stage'] !== '' ? $installGuard['stage'] : 'install';
                    $errorId = rx_installer_log($rootDirectory, $failedStage, $installError, $secrets);
                    $ERROR[] = 'خطای پیش‌بینی‌نشده: ' . mb_substr(rx_safe_error_message($installError->getMessage(), $secrets), 0, 300, 'UTF-8');
                    $errorStage = $failedStage;
                }

                if (!empty($ERROR) && $configWritten && is_string($configBackup)) {
                    if ($migrationStarted && !$configWasInstalled) {
                        rx_telegram_request($tgBotToken, 'deleteWebhook', [], 10);
                    }
                    $rollback = rx_write_config_atomically($configDirectory, $configBackup);
                    if ($rollback['ok']) {
                        $configWritten = false;
                        $installGuard['configWritten'] = false;
                    } else {
                        $rollbackId = rx_installer_log($rootDirectory, 'config_rollback', $rollback['message'], $secrets);
                        $ERROR[] = 'بازگردانی config.php ناموفق بود. شناسه خطا: ' . $rollbackId;
                    }
                }

                $installGuard['active'] = false;
                rx_release_installation_lock($installationLock);
                $installationLock = null;
                $installationLockHeld = false;
            }

            if (empty($ERROR)) {
                $botUsername = (string) $tgBot['details']['result']['username'];
                $welcome = rx_telegram_request($tgBotToken, 'sendMessage', [
                    'chat_id' => $tgAdminId,
                    'text' => 'نصب فاکسیما با موفقیت انجام شد و شما به عنوان مدیر اصلی ثبت شدید.',
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => 'شروع ربات', 'callback_data' => 'start']]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], 10);
                if (empty($welcome['ok'])) {
                    $SUCCESS[] = 'پیام تأیید در تلگرام ارسال نشد؛ می‌توانید ربات را مستقیماً باز کنید';
                }
                $SUCCESS[] = 'نصب با موفقیت تکمیل شد';
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
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
            if ($activeView === 'install' && rx_installation_lock_is_busy($rootDirectory)) {
                $installationBusy = true;
            }
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
                    <?php if ($installationBusy): ?>
                        <div class="alert alert-warning" role="status">
                            <svg><use href="#i-warn"/></svg>
                            <div><strong>نصب در حال اجراست</strong><span>یک درخواست نصب هم‌اکنون روی سرور در حال پردازش است. تا پایان آن صبر کنید و سپس صفحه را تازه‌سازی کنید.</span></div>
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
    <div class="loading-panel"><span class="loading-orbit"><svg><use href="#i-cog"/></svg></span><h2>در حال آماده‌سازی ربات…</h2><p>درخواست واقعی نصب در حال پردازش است. این صفحه را نبندید.</p><div class="loading-line"><span></span></div><div class="loading-recovery" id="install-loading-recovery" hidden><p>پاسخ سرور بیش از حد انتظار طول کشیده است. ممکن است نصب هنوز در حال انجام باشد یا ارتباط قطع شده باشد.</p><div class="loading-recovery-actions"><a class="btn btn-primary" href="index.php"><svg><use href="#i-refresh"/></svg>بررسی وضعیت نصب</a><button type="button" class="btn btn-secondary" id="install-loading-dismiss"><svg><use href="#i-arrow-right"/></svg>بازگشت به فرم</button></div></div></div>
</div>
<script src="assets/installer.js?v=<?php echo rawurlencode($assetVersion); ?>"></script>
</body>
</html>
