<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
ini_set('display_errors', '0');
session_start();







try {
    ob_start();
    require_once __DIR__ . '/../config.php';
    if (!@include_once __DIR__ . '/lib/ad_boot.php') {
        http_response_code(503);
        header('Retry-After: 3600');
        exit;
    }
    ob_end_clean();
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('server', 500);
}

require_once __DIR__ . '/lib/csrf.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('server', 500);
}

$fnUser = (string) ($_SESSION['user'] ?? '');
if ($fnUser === '') {
    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('auth', 401);
}
try {
    $fnAdminStmt = $pdo->prepare('SELECT * FROM admin WHERE username = ? LIMIT 1');
    $fnAdminStmt->execute([$fnUser]);
    $fnAdminRow = $fnAdminStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('server', 500);
}
if (!is_array($fnAdminRow)) {
    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('forbidden', 403);
}

$fnMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$fnAction = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';

$fnPostActions = ['save', 'delete', 'toggle', 'master', 'preview'];
if (in_array($fnAction, $fnPostActions, true)) {
    if ($fnMethod !== 'POST') {
        \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('method', 405);
    }
    \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
}

$fnBootstrapBufferLevel = ob_get_level();
try {
    ob_start();
    if ($fnAction === 'targets') {
        require_once __DIR__ . '/../panels.php';
    } else {
        require_once __DIR__ . '/../src/bootstrap.php';
    }
    while (ob_get_level() > $fnBootstrapBufferLevel) {
        ob_end_clean();
    }
} catch (Throwable $e) {
    while (ob_get_level() > $fnBootstrapBufferLevel) {
        ob_end_clean();
    }
    error_log('[filenaming_api bootstrap] ' . $e->getMessage());
    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('server', 500);
}

if (in_array($fnAction, ['rules', 'save', 'delete', 'toggle'], true)
    && (!method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingTableReady') || !\Faoxima\Services\Delivery\FileNaming::rxFileNamingTableReady())) {
    \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('migration_required', 409);
}

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'filenaming_api.php';
\Faoxima\Admin\Web\Actions\FilenameCatalog::handle(
    fnAction: $fnAction ?? null,
    pdo: $pdo ?? null
);
\Faoxima\Admin\Web\Actions\FilenameRules::handle(
    fnAction: $fnAction ?? null,
    pdo: $pdo ?? null
);
unset($adminWebPage);
