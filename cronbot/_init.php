<?php

if (!defined('RX_CRON_INIT_LOADED')) {
    define('RX_CRON_INIT_LOADED', true);

    @date_default_timezone_set('Asia/Tehran');
    if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
        @putenv('TZ=Asia/Tehran');
    }

    @ignore_user_abort(true);
    @set_time_limit(180);
    @ini_set('memory_limit', '256M');
    @ini_set('display_errors', 0);
    @ini_set('display_startup_errors', 0);
    if (!defined('FAOXIMA_LAZY_MYSQLI')) {
        define('FAOXIMA_LAZY_MYSQLI', true);
    }
}







































