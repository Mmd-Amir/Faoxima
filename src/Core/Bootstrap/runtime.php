<?php


if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', REFACTORED_LEGACY_ROOT);
}
$composerAutoload = APP_ROOT_PATH . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
    unset($composerAutoload);
} else {
    error_log('Composer autoloader not found. Optional dependencies may be unavailable.');
    unset($composerAutoload);
}
require_once APP_ROOT_PATH . '/config.php';
ini_set('error_log', APP_ROOT_PATH . '/error_log');
if (!defined('TRONADO_API_CONFIGURATION')) {
    $tronadoApiConfiguration = [
        'base_url' => 'https://bot.tronado.cloud',
        'order_token_path' => '/api/v5/GetOrderToken',
        'price_toman_path' => '/Tron/GetPriceToToman',
        'get_status_path' => '/Order/GetStatus',
        'get_status_by_payment_id_path' => '/Order/GetStatusByPaymentID',
    ];
    define('TRONADO_API_CONFIGURATION', $tronadoApiConfiguration);
    unset($tronadoApiConfiguration);
}
if (!defined('TRONADO_ORDER_TOKEN_ENDPOINTS')) {
    $tronadoConfig = TRONADO_API_CONFIGURATION;
    $baseUrl = rtrim((string) ($tronadoConfig['base_url'] ?? ''), '/');
    $path = '/' . ltrim((string) ($tronadoConfig['order_token_path'] ?? ''), '/');
    define('TRONADO_ORDER_TOKEN_ENDPOINTS', $baseUrl !== '' ? [$baseUrl . $path] : []);
    unset($baseUrl, $path, $tronadoConfig);
}
if (!defined('RX_APP_RUNTIME_LOADED')) {
    define('RX_APP_RUNTIME_LOADED', true);
}
