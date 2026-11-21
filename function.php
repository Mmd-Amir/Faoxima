<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(REFACTORED_LEGACY_ROOT);
require_once REFACTORED_LEGACY_ROOT . '/src/Core/Logging/functions.php';
require_once REFACTORED_LEGACY_ROOT . '/src/bootstrap.php';

try {
    require_once REFACTORED_LEGACY_ROOT . '/src/Core/Bootstrap/runtime.php';
} catch (Throwable $__rx_throwable) {
    \Faoxima\Core\Logging\RuntimeLog::throwable('function', $__rx_throwable);
    throw $__rx_throwable;
}
