<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
require_once REFACTORED_LEGACY_ROOT . '/src/Core/Logging/functions.php';
@chdir(REFACTORED_LEGACY_ROOT);
require_once REFACTORED_LEGACY_ROOT . '/src/bootstrap.php';

\Faoxima\Admin\Telegram\Runtime\Dispatcher::run();
