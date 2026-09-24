<?php


declare(strict_types=1);

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

/**
 * Self-healing OPcache invalidation: on every request, force-invalidates every watched
 * PHP file so the next require_once() below recompiles it from what's actually on disk
 * right now, instead of possibly continuing to run stale bytecode from before the file
 * was last uploaded/edited until someone manually restarts PHP-FPM or clears OPcache
 * from a hosting panel.
 *
 * opcache_invalidate($file, true) — force=true — always discards that file's cached
 * bytecode and lets it recompile on next load; it does NOT depend on comparing mtimes
 * or on any particular OPcache status field shape (those vary across PHP versions), so
 * there's nothing here that can silently stop working after a PHP upgrade.
 *
 * Deliberately file-by-file rather than a blanket opcache_reset() — resetting the whole
 * cache would force PHP to recompile every single file in the entire codebase on every
 * request, which defeats the point of OPcache and would visibly slow the app down. This
 * only touches the small, known set of files this endpoint actually loads.
 *
 * miniapp.php itself must be the file the webserver invokes directly (not something
 * required by another PHP file's cached bytecode), so it's the one place guaranteed to
 * get freshly re-parsed on every actual code deploy — from here it can reach into
 * OPcache and force any *other* file (handlers, libs) to catch up too.
 */
if (function_exists('opcache_invalidate')) {
    $__watchDirs = [__DIR__, __DIR__ . '/handlers', __DIR__ . '/lib'];
    foreach ($__watchDirs as $__dir) {
        if (!is_dir($__dir)) {
            continue;
        }
        foreach (glob($__dir . '/*.php') ?: [] as $__file) {
            @opcache_invalidate($__file, true);
        }
    }
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
        @error_log('[miniapp] fatal: ' . $err['message'] . ' @ ' . (string)$err['file'] . ':' . (int)$err['line']);
        __miniapp_emit(500, [
            'status' => false,
            'msg'    => 'Internal server error',
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
    require_once __DIR__ . '/handlers/service_output.php';

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
        'payment_reset'          => 'PaymentResetHandler',
        'crypto_currencies'      => 'CryptoCurrenciesHandler',
        'crypto_invoice_init'    => 'CryptoInvoiceInitHandler',
        'crypto_submit_hash'     => 'CryptoSubmitHashHandler',
        'crypto_upload_receipt'  => 'CryptoReceiptUploadHandler',
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
        'notification_info'      => ['class' => 'NotificationHandler', 'mode' => 'info'],
        'notification_recent'    => ['class' => 'NotificationHandler', 'mode' => 'recent'],
        'notification_save'      => ['class' => 'NotificationHandler', 'mode' => 'save'],
        'notification_delete'    => ['class' => 'NotificationHandler', 'mode' => 'delete'],
        'notification_dismiss'   => ['class' => 'NotificationHandler', 'mode' => 'dismiss'],
        'service_configs'        => 'ServiceConfigsHandler',
        'pending_payments'       => 'PendingPaymentsHandler',
        'transactions'           => 'TransactionHistoryHandler',
        'wallet_transfer_quote'   => ['class' => 'WalletTransferHandler', 'mode' => 'quote'],
        'wallet_transfer_confirm' => ['class' => 'WalletTransferHandler', 'mode' => 'confirm'],
        'redeem_giftcode'        => 'GiftCodeHandler',
        'discount_validate'      => 'DiscountValidateHandler',
        'discount_eligible'      => 'DiscountEligibleHandler',
        'tickets'                => ['class' => 'TicketsHandler', 'mode' => 'list'],
        'ticket_thread'          => ['class' => 'TicketsHandler', 'mode' => 'thread'],
        'ticket_departments'     => ['class' => 'TicketsHandler', 'mode' => 'departments'],
        'ticket_create'          => ['class' => 'TicketsHandler', 'mode' => 'create'],
        'ticket_reply'           => ['class' => 'TicketsHandler', 'mode' => 'reply'],
        'ticket_close'           => ['class' => 'TicketsHandler', 'mode' => 'close'],
        'ticket_media'           => ['class' => 'TicketsHandler', 'mode' => 'media'],
        'config_file'            => 'ConfigFileHandler',
        'config_dl_token'        => ['class' => 'ConfigFileHandler', 'mode' => 'token'],
        'ticket_react'           => ['class' => 'TicketsHandler', 'mode' => 'react'],
        'card_photo'             => 'CardPhotoUploadHandler',
        'card_select'            => 'CardSelectHandler',
        'test_account_info'      => ['class' => 'TestAccountHandler', 'mode' => 'info'],
        'test_account_create'    => ['class' => 'TestAccountHandler', 'mode' => 'create'],
        'service_locations'      => 'ServiceLocationsHandler',
    ];

    $payload = FaoximaInput::payload();
    $action = FaoximaInput::string($payload, 'actions');

    if ($action === '' || !isset($actions[$action])) {
        FaoximaResponse::badRequest('Action invalid');
    }

    $token = FaoximaAuth::extractBearerToken();
    $signedDownloadUser = null;
    if ($token === null && $action === 'config_file') {
        $queryToken = $_GET['dl_token'] ?? '';
        if (is_string($queryToken) && $queryToken !== '') {
            require_once __DIR__ . '/handlers/ConfigFileHandler.php';
            $signedDownloadUser = ConfigFileHandler::verifyDownloadToken($queryToken);
            if ($signedDownloadUser === null && preg_match('/^[a-f0-9]{40}$/i', $queryToken)) {
                $token = $queryToken;
            }
        }
    }
    if ($token === null && $signedDownloadUser === null) {
        FaoximaLogger::debug('Missing/invalid Authorization header', ['action' => $action]);
        FaoximaResponse::unauthorized('Authorization header missing or malformed');
    }

    $user = $signedDownloadUser !== null
        ? select('user', '*', 'id', (string)$signedDownloadUser, 'select')
        : FaoximaAuth::userFromToken($token);
    if ($signedDownloadUser !== null && !is_array($user)) {
        $user = null;
    }
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

    $adminIds = select('admin', 'id_admin', null, null, 'FETCH_COLUMN', ['cache' => false]);
    $isAdmin = is_array($adminIds) && in_array((int)$user['id'], array_map('intval', $adminIds), true);
    if (!$isAdmin && ($user['joinchannel'] ?? '') !== 'active') {
        $channelsId = select('channels', 'link', null, null, 'FETCH_COLUMN', ['cache' => false]);
        if (!empty($channelsId) && is_array($channelsId)) {
            FaoximaResponse::fail(403, 'join channel required');
        }
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
    @error_log('[miniapp] exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    __miniapp_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'obj'    => [],
    ]);
}

