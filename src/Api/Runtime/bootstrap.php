<?php


declare(strict_types=1);

use Faoxima\Core\Bootstrap\Application;



require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
$faoximaApplication = Application::boot();

require_once $faoximaApplication->root . '/config.php';
require_once $faoximaApplication->root . '/function.php';
require_once $faoximaApplication->root . '/botapi.php';
require_once $faoximaApplication->root . '/panels.php';
require_once $faoximaApplication->root . '/jdf.php';
require_once $faoximaApplication->root . '/keyboard.php';

date_default_timezone_set($faoximaApplication->config->timezone);
ini_set('default_charset', $faoximaApplication->config->charset);
unset($faoximaApplication);


if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

