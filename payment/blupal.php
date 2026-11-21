<?php
ini_set('error_log', 'error_log');
ignore_user_abort(true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../src/bootstrap.php';





\Faoxima\Payments\Gateways\Blupal\Callbacks::blupal_process_webhook();
