<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
require_once REFACTORED_LEGACY_ROOT . '/src/Core/Logging/functions.php';
@chdir(REFACTORED_LEGACY_ROOT);

$telegramRoot = REFACTORED_LEGACY_ROOT . '/src/Telegram';

try {
    require_once REFACTORED_LEGACY_ROOT . '/src/bootstrap.php';

    // legacy runtime bootstrap (file scope): configuration, Telegram client, application functions and keyboards
    $version = file_get_contents('version');
    date_default_timezone_set('Asia/Tehran');
    $new_marzban = isset($new_marzban) ? $new_marzban : false;
    ini_set('default_charset', 'UTF-8');
    ini_set('error_log', 'error_log');
    ini_set('memory_limit', '-1');
    if (!defined('FAOXIMA_LAZY_MYSQLI')) {
        define('FAOXIMA_LAZY_MYSQLI', true);
    }
    require_once 'config.php';
    require_once REFACTORED_LEGACY_ROOT . '/lib/WebhookAuth.php';
    FaoximaWebhookAuth::enforce((string) ($APIKEY ?? ''));
    if (!defined('RX_DEFER_UPDATE_DEDUPE')) {
        define('RX_DEFER_UPDATE_DEDUPE', true);
    }
    require_once 'botapi.php';
    require_once 'jdf.php';
    require_once 'function.php';
    if (isset($update_id) && isDuplicateUpdate($update_id)) {
        if (!headers_sent()) {
            http_response_code(200);
        }
        exit;
    }
    require_once 'keyboard.php';
    require_once 'vendor/autoload.php';
    require_once 'panels.php';
    if (\Faoxima\Telegram\Runtime\Webhook::run() !== true) {
        return;
    }

    if (in_array($from_id, $admin_ids)) {
        $GLOBALS['rx_admin_scope'] = true;
        require_once 'admin.php';
    }

    $pdo = null;
    $connect->close();

} catch (Throwable $__rx_throwable) {
    \Faoxima\Core\Logging\RuntimeLog::throwable('index', $__rx_throwable);
    throw $__rx_throwable;
} finally {
    unset($telegramRoot, $telegramStage, $telegramRoute, $telegramHandler);
}
