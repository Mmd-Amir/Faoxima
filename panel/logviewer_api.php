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
    if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
    ob_end_clean();
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('server', 500);
}

require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/logviewer.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('server', 500);
}

$lvUser = (string)($_SESSION['user'] ?? '');
if ($lvUser === '') {
    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('auth', 401);
}
try {
    $lvAllowed = \Faoxima\Admin\Web\Support\Logviewer::lv_admin_allowed($pdo, $lvUser);
} catch (Throwable $e) {
    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('server', 500);
}
if (!$lvAllowed) {
    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('forbidden', 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';

$postActions = ['delete_file', 'delete_bulk'];
if (in_array($action, $postActions, true)) {
    if ($method !== 'POST') {
        \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('method', 405);
    }
    \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
} elseif ($method !== 'GET') {
    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('method', 405);
}

session_write_close();

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'logviewer_api.php';
\Faoxima\Admin\Web\Actions\LogViewerActions::handle(
    action: $action ?? null,
    ev: $ev ?? null,
    name: $name ?? null
);
unset($adminWebPage);
