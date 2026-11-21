<?php

// legacy runtime bootstrap (global scope): config.php, function.php, botapi.php, ini and output buffering
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');

$headrs = getallheaders();
$setting = \Faoxima\Core\Database\Db::select("setting", "*");


require_once __DIR__ . '/../lib/ApiCredential.php';

require_once __DIR__ . '/../src/bootstrap.php';

return \Faoxima\Api\Endpoints\Service::handle(
    APIKEY: $APIKEY ?? null,
    data: $data,
    headrs: $headrs ?? null,
    method: $method,
    pdo: $pdo ?? null,
    stmt: $stmt
);
