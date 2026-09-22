<?php

const RX_REQUIRED_PHP_VERSION = '8.2.0';

const RX_REQUIRED_EXTENSIONS = [
    'curl' => 'curl',
    'pdo_mysql' => 'PDO MySQL',
    'mysqli' => 'MySQLi',
    'mbstring' => 'mbstring',
    'json' => 'JSON',
    'fileinfo' => 'fileinfo',
    'openssl' => 'OpenSSL',
    'gd' => 'GD',
    'zip' => 'Zip',
];

function rx_required_writable_paths(string $rootDirectory): array
{
    return [
        'config.php' => $rootDirectory . 'config.php',
        'table.php' => $rootDirectory . 'table.php',
        '/' => $rootDirectory,
        'logs/' => $rootDirectory . 'logs',
        'storage/' => $rootDirectory . 'storage',
    ];
}

function rx_check_php_version(): array
{
    $current = PHP_VERSION;
    $ok = version_compare($current, RX_REQUIRED_PHP_VERSION, '>=');
    return [
        'id' => 'php_version',
        'label' => 'نسخه PHP',
        'detail' => $ok
            ? "نسخه فعلی {$current} (حداقل " . RX_REQUIRED_PHP_VERSION . ')'
            : "نسخه فعلی {$current} - حداقل نسخه مورد نیاز " . RX_REQUIRED_PHP_VERSION . ' است',
        'ok' => $ok,
        'critical' => true,
    ];
}

function rx_check_extensions(): array
{
    $results = [];
    foreach (RX_REQUIRED_EXTENSIONS as $extension => $label) {
        $ok = extension_loaded($extension);
        $results[] = [
            'id' => 'ext_' . $extension,
            'label' => "اکستنشن {$label}",
            'detail' => $ok ? 'فعال است' : 'نصب یا فعال نیست',
            'ok' => $ok,
            'critical' => true,
        ];
    }
    return $results;
}

function rx_check_writable_paths(string $rootDirectory): array
{
    $results = [];
    foreach (rx_required_writable_paths($rootDirectory) as $label => $path) {
        $exists = file_exists($path);
        $ok = $exists ? is_writable($path) : is_writable(dirname($path));
        $results[] = [
            'id' => 'writable_' . md5($path),
            'label' => "دسترسی نوشتن: {$label}",
            'detail' => $ok
                ? 'قابل نوشتن است'
                : ($exists ? 'مسیر قابل نوشتن نیست' : 'مسیر یافت نشد و دایرکتوری والد قابل نوشتن نیست'),
            'ok' => $ok,
            'critical' => true,
        ];
    }
    return $results;
}

function rx_check_project_files(string $rootDirectory): array
{
    $required = [
        'config.php' => $rootDirectory . 'config.php',
        'table.php' => $rootDirectory . 'table.php',
    ];
    $results = [];
    foreach ($required as $label => $path) {
        $ok = file_exists($path);
        $results[] = [
            'id' => 'file_' . md5($path),
            'label' => "فایل پروژه: {$label}",
            'detail' => $ok ? 'موجود است' : 'یافت نشد',
            'ok' => $ok,
            'critical' => true,
        ];
    }
    return $results;
}

function rx_check_migration_isolation(): array
{
    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    $phpBinary = function_exists('rx_find_php_binary') ? rx_find_php_binary() : null;
    $ok = function_exists('proc_open') && !in_array('proc_open', $disabled, true) && $phpBinary !== null;
    return [
        'id' => 'migration_isolation',
        'label' => 'اجرای ایزوله Migration',
        'detail' => $ok ? 'PHP اجرایی و proc_open در دسترس است' : 'PHP اجرایی یا تابع proc_open در دسترس نیست',
        'ok' => $ok,
        'critical' => true,
    ];
}

function rx_project_subdirectory_depth(string $rootDirectory): array
{
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $projectDir = rtrim($scriptDir, '/');
    $lastSlash = strrpos($projectDir, '/');
    $projectDir = $lastSlash === false ? '' : substr($projectDir, 0, $lastSlash);

    $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $realRoot = realpath($rootDirectory);
    $realDocRoot = $documentRoot !== '' ? realpath($documentRoot) : false;

    if ($realRoot !== false && $realDocRoot !== false) {
        $realRootNormalized = rtrim(str_replace('\\', '/', $realRoot), '/');
        $realDocRootNormalized = rtrim(str_replace('\\', '/', $realDocRoot), '/');
        $depth = $realRootNormalized === $realDocRootNormalized ? 0 : 1;
    } else {
        $normalized = trim($projectDir, '/');
        $depth = $normalized === '' ? 0 : count(explode('/', $normalized));
    }

    return [
        'depth' => $depth,
        'path' => $projectDir === '' ? '/' : $projectDir,
    ];
}

function rx_check_subdirectory_enforcement(string $rootDirectory): array
{
    $info = rx_project_subdirectory_depth($rootDirectory);
    $ok = $info['depth'] > 0;
    return [
        'id' => 'subdirectory_depth',
        'label' => 'نصب پروژه درون ساب‌دایرکتوری',
        'detail' => $ok
            ? "مسیر پروژه: {$info['path']}"
            : 'پوشه پروژه مستقیماً روی ریشه اصلی دامنه (public_html) قرار دارد',
        'ok' => $ok,
        'critical' => true,
    ];
}

function rx_run_requirement_checks(string $rootDirectory): array
{
    $checks = [];
    $checks[] = rx_check_subdirectory_enforcement($rootDirectory);
    $checks[] = rx_check_php_version();
    $checks = array_merge($checks, rx_check_extensions());
    $checks = array_merge($checks, rx_check_project_files($rootDirectory));
    $checks[] = rx_check_migration_isolation();
    $checks = array_merge($checks, rx_check_writable_paths($rootDirectory));
    return $checks;
}

function rx_requirement_checks_passed(array $checks): bool
{
    foreach ($checks as $check) {
        if (!empty($check['critical']) && empty($check['ok'])) {
            return false;
        }
    }
    return true;
}
