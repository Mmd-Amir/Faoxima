<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
ini_set('display_errors', '0');
if ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    ini_set('session.cookie_secure', '1');
}
session_start();



ob_start();
try {
    require_once __DIR__ . '/../config.php';
} catch (Throwable $e) {
    \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => false, 'code' => 'server'], 500);
}
ob_end_clean();
if (!@include_once __DIR__ . '/lib/ad_boot.php') {
    http_response_code(503);
    header('Retry-After: 3600');
    exit;
}
require_once __DIR__ . '/lib/ad_client.php';
require_once __DIR__ . '/lib/csrf.php';

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'banner') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => false, 'code' => 'method'], 405);
    }
    $nonce = (string)($_GET['t'] ?? '');
    $expected = (string)($_SESSION['fx_ad_nonce'] ?? '');
    if ($expected === '' || $nonce === '' || !hash_equals($expected, $nonce)) {
        \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => false, 'code' => 'forbidden'], 403);
    }
    $last = (int)($_SESSION['fx_ad_last'] ?? 0);
    if ($last > 0 && time() - $last < 3) {
        \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => true, 'ad' => null]);
    }
    $_SESSION['fx_ad_last'] = time();
    session_write_close();
    \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => true, 'ad' => \Faoxima\Admin\Web\Support\AdClient::fx_ad_fetch('banner')]);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => false, 'code' => 'server'], 500);
}

$adUser = (string)($_SESSION['user'] ?? '');
if ($adUser === '') {
    \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => false, 'code' => 'auth'], 401);
}

try {
    $chk = $pdo->prepare('SELECT 1 FROM admin WHERE username = :u LIMIT 1');
    $chk->execute([':u' => $adUser]);
    if (!$chk->fetchColumn()) {
        \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => false, 'code' => 'auth'], 401);
    }
    \Faoxima\Admin\Web\Support\Schema::faoxima_schema_ensure_column($pdo, 'admin', 'ad_modal_hidden_until', 'INT(11) NULL DEFAULT 0');
} catch (Throwable $e) {
    \Faoxima\Admin\Web\Support\AdApiPage::fx_ad_json(['ok' => false, 'code' => 'server'], 500);
}

\Faoxima\Admin\Web\AdPreferences::dispatch(
    $pdo,
    $adUser,
    $action,
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
    $_POST['_csrf'] ?? null,
    [\Faoxima\Admin\Web\Support\Csrf::class, 'fx_csrf_token'],
    [\Faoxima\Admin\Web\Support\AdClient::class, 'fx_ad_fetch'],
    [\Faoxima\Admin\Web\Support\AdApiPage::class, 'fx_ad_json'],
);
