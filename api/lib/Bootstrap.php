<?php


declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Validator.php';


require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../function.php';
require_once __DIR__ . '/../../botapi.php';
require_once __DIR__ . '/../../panels.php';
require_once __DIR__ . '/../../jdf.php';
require_once __DIR__ . '/../../keyboard.php';

date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');


if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

