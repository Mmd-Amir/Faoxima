<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', dirname(__DIR__));
}
@chdir(REFACTORED_LEGACY_ROOT);
require __DIR__ . '/_error_log.php';
require __DIR__ . '/rx/index/index.php';

