<?php

/*
 * Hamoix — web-only build (Telegram bot removed).
 *
 * The admin panel and reseller panel are the single entry point for sales,
 * management and provisioning. The root URL forwards there.
 */

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}

header('Location: panel/index.php', true, 302);
exit;
