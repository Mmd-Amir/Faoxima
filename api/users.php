<?php

// legacy runtime bootstrap (global scope): config.php, function.php, botapi.php, ini and output buffering
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

require_once __DIR__ . '/../src/bootstrap.php';

return \Faoxima\Api\Endpoints\Users::handle(
    APIKEY: $APIKEY ?? null,
    domainhosts: $domainhosts ?? null,
    errorreport: $errorreport,
    otherservice: $otherservice,
    paymentreports: $paymentreports,
    pdo: $pdo ?? null,
    porsantreport: $porsantreport,
    reportbackup: $reportbackup,
    reportcron: $reportcron,
    reportnight: $reportnight,
    reporttest: $reporttest,
    setting: $setting
);
