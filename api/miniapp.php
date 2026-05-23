<?php


declare(strict_types=1);

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ob_start();

@ini_set('display_errors',         '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors',             '1');
error_reporting(E_ALL);

$GLOBALS['__miniapp_response_sent'] = false;

function __miniapp_emit(int $http, array $payload): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $GLOBALS['__miniapp_response_sent'] = true;
}

register_shutdown_function(static function () {
    if (!empty($GLOBALS['__miniapp_response_sent'])) {
        return;
    }
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (is_array($err) && in_array($err['type'], $fatal, true)) {
        __miniapp_emit(500, [
            'status' => false,
            'msg'    => 'PHP fatal: ' . $err['message'],
            'detail' => basename((string)$err['file']) . ':' . (int)$err['line'],
            'obj'    => [],
        ]);
        return;
    }
    __miniapp_emit(500, [
        'status' => false,
        'msg'    => 'miniapp.php finished without emitting a response',
        'obj'    => [],
    ]);
});

try {
    require_once __DIR__ . '/lib/Bootstrap.php';
    require_once __DIR__ . '/handlers/BaseHandler.php';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    ob_start();

    $actions = [
        'user_info'              => 'UserInfoHandler',
        'invoices'               => 'InvoicesHandler',
        'service'                => 'ServiceHandler',
        'countries'              => 'CountriesHandler',
        'categories'             => 'CategoriesHandler',
        'time_ranges'            => 'TimeRangesHandler',
        'services'               => 'ServicesHandler',
        'custom_price'           => 'CustomPriceHandler',
        'purchase'               => 'PurchaseHandler',
        'payment_methods'        => 'PaymentMethodsHandler',
        'payment_init'           => 'PaymentInitHandler',
        'payment_receipt'        => 'PaymentReceiptHandler',
        'payment_status'         => 'PaymentStatusHandler',
        'crypto_currencies'      => 'CryptoCurrenciesHandler',
        'crypto_invoice_init'    => 'CryptoInvoiceInitHandler',
        'crypto_submit_hash'     => 'CryptoSubmitHashHandler',
        'crypto_cancel_invoice'  => 'CryptoCancelInvoiceHandler',
        'service_action'         => 'ServiceActionHandler',
        'service_renew_options'  => 'ServiceRenewOptionsHandler',
        'service_renew_confirm'  => 'ServiceRenewConfirmHandler',
        'service_extra_quote'    => ['class' => 'ServiceExtraHandler', 'mode' => 'quote'],
        'service_extra_confirm'  => ['class' => 'ServiceExtraHandler', 'mode' => 'confirm'],
        'service_simple_action'  => 'ServiceSimpleActionHandler',
        'brand_info'             => ['class' => 'BrandHandler', 'mode' => 'info'],
        'brand_save'             => ['class' => 'BrandHandler', 'mode' => 'save'],
        'brand_upload_logo'      => ['class' => 'BrandHandler', 'mode' => 'upload'],
        'service_configs'        => 'ServiceConfigsHandler',
        'pending_payments'       => 'PendingPaymentsHandler',
    ];

    $payload = FaoximaInput::payload();
    $action = FaoximaInput::string($payload, 'actions');

    if ($action === '' || !isset($actions[$action])) {
        FaoximaResponse::badRequest('Action invalid');
    }

    $token = FaoximaAuth::extractBearerToken();
    if ($token === null) {
        FaoximaLogger::debug('Missing/invalid Authorization header', ['action' => $action]);
        FaoximaResponse::unauthorized('Authorization header missing or malformed');
    }

    $user = FaoximaAuth::userFromToken($token);
    if ($user === null) {
        FaoximaLogger::debug('Bearer token did not match any user', ['action' => $action]);
        FaoximaResponse::forbidden('Token invalid');
    }

    if (($user['User_Status'] ?? '') === 'block') {
        FaoximaLogger::warn('Blocked user attempted miniapp action', [
            'user_id' => $user['id'],
            'action'  => $action,
        ]);
        FaoximaResponse::fail(403, 'user blocked');
    }

    $entry = $actions[$action];
    if (is_string($entry)) {
        $handlerClass = $entry;
        $handlerMode = null;
    } else {
        $handlerClass = (string)($entry['class'] ?? '');
        $handlerMode = $entry['mode'] ?? null;
    }

    $handlerFile = __DIR__ . '/handlers/' . $handlerClass . '.php';
    if (!is_file($handlerFile)) {
        FaoximaLogger::critical('Handler file missing', ['handler' => $handlerClass]);
        FaoximaResponse::serverError('Handler not available');
    }
    require_once $handlerFile;

    if (!class_exists($handlerClass)) {
        FaoximaLogger::critical('Handler class missing', ['handler' => $handlerClass]);
        FaoximaResponse::serverError('Handler class not loadable');
    }


    $handler = new $handlerClass($user, $payload);
    if ($handlerMode !== null && property_exists($handler, 'mode')) {
        $handler->mode = $handlerMode;
    }
    $handler->handle();

    __miniapp_emit(500, [
        'status' => false,
        'msg'    => 'Handler returned without responding',
        'obj'    => [],
    ]);
} catch (Throwable $e) {
    if (class_exists('FaoximaLogger')) {
        try {
            FaoximaLogger::exception($e, 'miniapp.php top-level exception');
        } catch (Throwable $_) {  }
    }
    __miniapp_emit(500, [
        'status' => false,
        'msg'    => 'miniapp.php exception: ' . $e->getMessage(),
        'detail' => basename($e->getFile()) . ':' . $e->getLine(),
        'obj'    => [],
    ]);
}

