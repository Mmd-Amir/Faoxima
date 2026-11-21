<?php

declare(strict_types=1);

// legacy runtime bootstrap (global scope): config.php, function.php, botapi.php, ini and output buffering
$__projectRoot = dirname(__DIR__);
$__bgMtime = 0;
foreach (['/images.jpeg', '/images.jpg', '/custom.jpg', '/custom.jpeg'] as $__bgRel) {
    $__bgFull = $__projectRoot . $__bgRel;
    if (is_file($__bgFull)) {
        $__bgMtime = max($__bgMtime, (int) @filemtime($__bgFull));
    }
}
$__etag = '"qr-' . md5(($_SERVER['QUERY_STRING'] ?? '') . '|' . $__bgMtime) . '"';
header('Cache-Control: public, max-age=300, must-revalidate');
header('ETag: ' . $__etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $__etag) {
    http_response_code(304);
    exit;
}

$__qrRootDir = dirname(__DIR__);
if (is_file($__qrRootDir . '/config.php') && is_file($__qrRootDir . '/function.php')) {
    @require_once $__qrRootDir . '/config.php';
    @require_once $__qrRootDir . '/function.php';
}
if (method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled()) {
    http_response_code(404);
    exit;
} elseif (!method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Core\Database\Db::runtimeReady()) {
    $__qrDisRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "qr_disabled", "select");
    if (is_array($__qrDisRow) && ((string)($__qrDisRow['value'] ?? '0')) === '1') {
        http_response_code(404);
        exit;
    }
}

$payload = isset($_GET['d']) ? (string) $_GET['d'] : '';
$payload = substr($payload, 0, 2048);
if ($payload === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'd (data) is required';
    exit;
}

$size = isset($_GET['s']) ? (int) $_GET['s'] : 320;
if ($size < 80) $size = 80;
if ($size > 800) $size = 800;

$style = isset($_GET['style']) ? strtolower((string)$_GET['style']) : 'plain';


$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];
foreach ($autoloadCandidates as $auto) {
    if (is_file($auto)) {
        require_once $auto;
        break;
    }
}

require_once __DIR__ . '/../src/bootstrap.php';

return \Faoxima\Api\Endpoints\Qr::handle(
    bBoost: $bBoost ?? null,
    c: $c,
    cur: $cur,
    g: $g,
    needInnerLine: $needInnerLine ?? null,
    opacity: $opacity ?? null,
    payload: $payload ?? null,
    r: $r,
    result: $result,
    size: $size ?? null,
    style: $style ?? null,
    tintB: $tintB ?? null,
    tintG: $tintG ?? null,
    tintR: $tintR ?? null,
    url: $url
);
