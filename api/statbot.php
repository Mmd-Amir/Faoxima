<?php

// legacy runtime bootstrap (global scope): config.php, function.php, botapi.php, ini and output buffering
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../lib/ApiCredential.php';

require_once __DIR__ . '/../src/bootstrap.php';

return \Faoxima\Api\Endpoints\Statbot::handle(
    APIKEY: $APIKEY ?? null,
    data: $data ?? null,
    headrs: $headrs,
    pdo: $pdo ?? null,
    setting: $setting,
    stmt: $stmt
);
