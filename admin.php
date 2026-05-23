<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}


$adminPath = __DIR__ . '/re/admin.php';
if (is_file($adminPath)) {
    require $adminPath;
    return;
}


error_log('[admin.php] Missing required file: ' . $adminPath
    . '. The bot management panel cannot run. '
    . 'Restore the /re directory from backup or reinstall the refactored bundle.');

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Missing $adminPath\n");
    exit(1);
}

http_response_code(500);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => false,
    'msg'    => 'پنل مدیریت در دسترس نیست: فایل re/admin.php روی سرور موجود نیست. لطفاً پروژه را به‌طور کامل آپلود کنید.',
], JSON_UNESCAPED_UNICODE);

