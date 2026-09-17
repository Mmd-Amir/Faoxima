<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', dirname(__DIR__));
}
@chdir(REFACTORED_LEGACY_ROOT);
require __DIR__ . '/_error_log.php';
require __DIR__ . '/_perf_log.php';
rx_perf_start('webhook');
rx_perf_mark('entry');
require __DIR__ . '/rx/index/index.php';
rx_perf_mark('index_compiled_done');

