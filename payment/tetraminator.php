<?php
ini_set('error_log', 'error_log');
ini_set('display_errors', '0');
ignore_user_abort(true);
if (function_exists('set_time_limit')) {
    @set_time_limit(90);
}

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
}

$tetraminatorMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
if ($tetraminatorMethod !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo 'method not allowed';
    return;
}

$tetraminatorToken = isset($_GET['cb']) && is_string($_GET['cb']) ? trim($_GET['cb']) : '';
if ($tetraminatorToken === '' || preg_match('/^[a-f0-9]{40}$/', $tetraminatorToken) !== 1) {
    http_response_code(400);
    echo 'invalid reference';
    return;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../src/bootstrap.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    echo 'unavailable';
    return;
}

$setting = \Faoxima\Core\Database\Db::select('setting', '*');
if (class_exists('ManagePanel')) {
    $ManagePanel = new ManagePanel();
}

try {
    $tetraminatorOutcome = \Faoxima\Payments\Gateways\Tetraminator\Recovery::handleCallback($pdo, $setting, $tetraminatorToken);
} catch (Throwable $e) {
    \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_WEBHOOK_FAILED', $e->getMessage());
    http_response_code(500);
    echo 'failed';
    return;
}

http_response_code((int) ($tetraminatorOutcome['http'] ?? 500));
echo (string) ($tetraminatorOutcome['body'] ?? 'failed');
