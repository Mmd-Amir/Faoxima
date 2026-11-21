<?php

declare(strict_types=1);

$faoximaAutoloaderPath = __DIR__ . '/Core/Bootstrap/Autoloader.php';
if (function_exists('opcache_invalidate') && !ini_get('opcache.validate_timestamps')) {
    @opcache_invalidate($faoximaAutoloaderPath, false);
}
require_once $faoximaAutoloaderPath;
unset($faoximaAutoloaderPath);

\Faoxima\Core\Bootstrap\Autoloader::register();

require_once __DIR__ . '/Core/Bootstrap/constants.php';
