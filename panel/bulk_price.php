<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
ob_start();

require_once __DIR__ . '/lib/bulk_price.php';



register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('[bulk_price.php] fatal: ' . $err['message'] . ' @ ' . basename($err['file']) . ':' . $err['line']);
        if (!headers_sent()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => \Faoxima\Admin\Web\Support\BulkPrice::fx_bp_messages()['server_error']], JSON_UNESCAPED_UNICODE);
        }
    }
});

try {
    session_start();
    require_once __DIR__ . '/../config.php';
    if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
    require_once __DIR__ . '/lib/csrf.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('database connection unavailable');
    }

    $admin = false;
    if (!empty($_SESSION['user'])) {
        $query = $pdo->prepare("SELECT 1 FROM admin WHERE username = :username LIMIT 1");
        $query->execute([':username' => $_SESSION['user']]);
        $admin = (bool)$query->fetchColumn();
    }
    if (!$admin) {
        \Faoxima\Admin\Web\Support\BulkPricePage::fx_bp_respond(401, ['ok' => false, 'error' => 'unauthorized']);
    }

    \Faoxima\Admin\Web\BulkPriceController::dispatch($pdo, $_SERVER['REQUEST_METHOD'], $_GET, $_POST, $_SERVER, (string) $_SESSION['user'], [\Faoxima\Admin\Web\Support\Csrf::class, 'fx_csrf_token'], [\Faoxima\Admin\Web\Support\BulkPricePage::class, 'fx_bp_respond']);
} catch (\Throwable $e) {
    error_log('[bulk_price.php] request: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    \Faoxima\Admin\Web\Support\BulkPricePage::fx_bp_respond(500, ['ok' => false, 'error' => 'server_error']);
}
