<?php

/**
 * Compatibility entry for the panel provider layer.
 *
 * The implementation lives in src/Services/Panels (ManagePanel, the Panel* operation traits, the provider drivers in Drivers/,
 * Remnawave/RemnawaveManager) and src/Core/Http/CurlRequest. This file prepares the historical request environment that every
 * consumer of `panels.php` relied on (working directory, configuration, helper functions, timezone, error log) and registers the
 * class under its original global name `ManagePanel`, which the bot, the web panel, the Mini App API, the payment callbacks and
 * Cron instantiate. The alias is registered here only: `class_exists('ManagePanel', false)` doubles as the "panel layer loaded"
 * gate, so it must not be provided by the autoloader.
 */
if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(__DIR__);

ini_set('error_log', 'error_log');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/src/bootstrap.php';
date_default_timezone_set('Asia/Tehran');

if (!class_exists('ManagePanel', false)) {
    class_alias(\Faoxima\Services\Panels\ManagePanel::class, 'ManagePanel');
}
