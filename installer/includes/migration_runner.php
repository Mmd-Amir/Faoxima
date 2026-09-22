<?php

$rootDirectory = getenv('RX_INSTALL_ROOT');
$rootDirectory = is_string($rootDirectory) ? rtrim($rootDirectory, '/\\') : '';
$runnerKey = getenv('RX_INSTALL_RUNNER_KEY');
$runnerKey = is_string($runnerKey) ? $runnerKey : '';
$completed = false;

$emitResult = static function (array $result): void {
    $payload = base64_encode(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $line = "RX_INSTALL_RESULT:{$payload}\n";
    $stream = @fopen('php://stderr', 'wb');
    if (is_resource($stream)) {
        fwrite($stream, $line);
        fclose($stream);
        return;
    }
    echo $line;
};

register_shutdown_function(static function () use (&$completed, $emitResult): void {
    if ($completed) {
        return;
    }
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    $message = is_array($error) && in_array($error['type'] ?? 0, $fatalTypes, true)
        ? (string) ($error['message'] ?? 'خطای ناشناخته در migration')
        : 'اجرای migration پیش از تکمیل متوقف شد.';
    $emitResult(['ok' => false, 'message' => $message]);
});

if (strlen($runnerKey) !== 48 || !ctype_xdigit($runnerKey)) {
    $completed = true;
    $emitResult(['ok' => false, 'message' => 'مجوز اجرای migration معتبر نیست.']);
    exit(1);
}

if ($rootDirectory === '' || !is_dir($rootDirectory) || !is_file($rootDirectory . DIRECTORY_SEPARATOR . 'table.php')) {
    $completed = true;
    $emitResult(['ok' => false, 'message' => 'مسیر table.php معتبر نیست.']);
    exit(1);
}

define('RX_INSTALLER_RUNNING', true);
define('REFACTORED_LEGACY_ROOT', $rootDirectory);
chdir($rootDirectory);

try {
    ob_start();
    include $rootDirectory . DIRECTORY_SEPARATOR . 'table.php';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $completed = true;
    $emitResult(['ok' => true, 'message' => '']);
    exit(0);
} catch (Throwable $error) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $completed = true;
    $emitResult(['ok' => false, 'message' => $error->getMessage()]);
    exit(1);
}
