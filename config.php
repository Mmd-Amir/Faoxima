<?php

if (!defined('_FX_SHARD')) {
    define('_FX_SHARD','b08d416dac363b09');
}

$dbname     = '';
$usernamedb = '';
$passworddb = '';
$dbhost     = '';

$redis_host     = '';
$redis_port     = '6379';
$redis_password = '';
$redis_database = '0';

$connect = null;
$pdo     = null;
$dsn     = '';

$options = [
    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES   => false,
    \PDO::ATTR_PERSISTENT         => true,
];
if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+03:30'";
}

if (!function_exists('rx_db_is_saturation_error')) {
    function rx_db_is_saturation_error($error)
    {
        $msg = is_object($error) ? (string) $error->getMessage() : (string) $error;
        if ($msg === '') {
            return false;
        }
        return strpos($msg, '1040') !== false
            || strpos($msg, '1203') !== false
            || strpos($msg, '08004') !== false
            || stripos($msg, 'Too many connections') !== false
            || stripos($msg, 'max_user_connections') !== false;
    }
}

if (!function_exists('rx_db_connect_retries')) {
    function rx_db_connect_retries()
    {
        if (defined('RX_DB_CONNECT_RETRIES')) {
            return max(0, (int) RX_DB_CONNECT_RETRIES);
        }
        return 4;
    }
}

if (!function_exists('rx_db_connect_backoff_ms')) {
    function rx_db_connect_backoff_ms()
    {
        if (defined('RX_DB_CONNECT_BACKOFF_MS')) {
            return max(0, (int) RX_DB_CONNECT_BACKOFF_MS);
        }
        return 150;
    }
}

if (!function_exists('rx_db_backoff_sleep')) {
    function rx_db_backoff_sleep($attempt)
    {
        $base   = rx_db_connect_backoff_ms() * 1000;
        $jitter = function_exists('random_int') ? random_int(0, 60000) : mt_rand(0, 60000);
        @usleep($base + $jitter);
    }
}

if (!function_exists('rx_connect_pdo')) {
    function rx_connect_pdo($dsn, $username, $password, $options)
    {
        $retries = rx_db_connect_retries();
        $attempt = 0;
        while (true) {
            try {
                return new \PDO($dsn, (string) $username, (string) $password, is_array($options) ? $options : []);
            } catch (\PDOException $e) {
                if ($attempt < $retries && rx_db_is_saturation_error($e)) {
                    rx_db_backoff_sleep($attempt);
                    $attempt++;
                    continue;
                }
                throw $e;
            }
        }
    }
}

if (!function_exists('rx_connect_mysqli')) {
    function rx_connect_mysqli($host, $username, $password, $database)
    {
        if (function_exists('mysqli_report')) {
            @mysqli_report(MYSQLI_REPORT_OFF);
        }
        $retries = rx_db_connect_retries();
        $attempt = 0;
        while (true) {
            $conn = @mysqli_connect($host, $username, $password, $database);
            if ($conn instanceof mysqli) {
                return $conn;
            }
            $errno     = function_exists('mysqli_connect_errno') ? (int) mysqli_connect_errno() : 0;
            $saturated = in_array($errno, [1040, 1203], true)
                || rx_db_is_saturation_error(function_exists('mysqli_connect_error') ? (string) mysqli_connect_error() : '');
            if ($attempt < $retries && $saturated) {
                rx_db_backoff_sleep($attempt);
                $attempt++;
                continue;
            }
            return null;
        }
    }
}

if (!function_exists('getMysqliConnection')) {
    function getMysqliConnection()
    {
        if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
            return $GLOBALS['connect'];
        }
        $db   = $GLOBALS['dbname'] ?? '';
        $user = $GLOBALS['usernamedb'] ?? '';
        $pass = $GLOBALS['passworddb'] ?? '';
        if ($db === '' || $user === '') {
            return null;
        }
        $host = $GLOBALS['dbhost'] ?? '';
        $conn = rx_connect_mysqli($host !== '' ? $host : 'localhost', $user, $pass, $db);
        if ($conn instanceof mysqli) {
            @mysqli_set_charset($conn, 'utf8mb4');
            $GLOBALS['connect'] = $conn;
            return $conn;
        }
        return null;
    }
}

if (!function_exists('rx_cleanup_installer')) {
    function rx_cleanup_installer(string $installerDir, string $trigger = 'installer'): bool {
        $rootDir = dirname($installerDir);
        $logsDir = $rootDir . DIRECTORY_SEPARATOR . 'logs';
        $flagFile = $logsDir . DIRECTORY_SEPARATOR . '.cleanup_failed';

        if ($trigger === 'config_bootstrap' && is_file($flagFile)) {
            $mtime = (int)@filemtime($flagFile);
            if ((time() - $mtime) < 300) {
                return false;
            }
        }

        if (!is_dir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        $indexFile = $installerDir . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($indexFile)) {
            @chmod($indexFile, 0666);
            @file_put_contents($indexFile, "<?php http_response_code(404); exit;\n");
        }

        @chmod($rootDir, 0777);
        @chmod($installerDir, 0777);

        $deleteRecursive = static function (string $dir) use (&$deleteRecursive): bool {
            if (!is_dir($dir)) {
                return true;
            }
            @chmod($dir, 0777);
            $items = @scandir($dir);
            if ($items === false) {
                return false;
            }

            $success = true;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                clearstatcache(true, $path);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($path, true);
                }
                @chmod($path, 0777);

                if (is_dir($path) && !is_link($path)) {
                    if (!$deleteRecursive($path)) {
                        $success = false;
                    }
                    if (!@rmdir($path)) {
                        $success = false;
                    }
                } else {
                    if (!@unlink($path)) {
                        @chmod($path, 0666);
                        if (!@unlink($path)) {
                            $success = false;
                        }
                    }
                }
            }
            return $success;
        };

        $deleteRecursive($installerDir);
        clearstatcache(true, $installerDir);

        if (!is_dir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        if (@rmdir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        $escapedDir = escapeshellarg($installerDir);
        $shellCmd = strncasecmp(PHP_OS, 'WIN', 3) === 0
            ? "rmdir /s /q {$escapedDir} 2>&1"
            : "chmod -R 777 {$escapedDir} 2>&1; rm -rf {$escapedDir} 2>&1";

        if (function_exists('exec')) {
            @exec($shellCmd);
        } elseif (function_exists('shell_exec')) {
            @shell_exec($shellCmd);
        } elseif (function_exists('system')) {
            ob_start();
            @system($shellCmd);
            ob_end_clean();
        } elseif (function_exists('passthru')) {
            ob_start();
            @passthru($shellCmd);
            ob_end_clean();
        }

        clearstatcache(true, $installerDir);
        if (!is_dir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        @touch($flagFile);

        if ($trigger === 'installer_shutdown') {
            $phpBin = PHP_BINARY && is_executable(PHP_BINARY) ? PHP_BINARY : 'php';
            $cleanCode = "define('REFACTORED_LEGACY_ROOT', " . var_export($rootDir, true) . ");";
            $cleanCode .= "require_once " . var_export($rootDir . '/config.php', true) . ";";
            $cleanCode .= "if (function_exists('rx_cleanup_installer')) { rx_cleanup_installer(" . var_export($installerDir, true) . ", 'background'); }";
            $cmdStr = escapeshellarg($phpBin) . " -r " . escapeshellarg("sleep(1); " . $cleanCode);
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                @pclose(@popen("start /B " . $cmdStr, "r"));
            } else {
                @exec($cmdStr . " > /dev/null 2>&1 &");
            }
        }

        return !is_dir($installerDir);
    }
}

if ($dbname !== '' && $usernamedb !== '') {
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }
    $rxInstallerDir = __DIR__ . DIRECTORY_SEPARATOR . 'installer';
    if (is_dir($rxInstallerDir) && !defined('RX_INSTALLER_RUNNING')) {
        rx_cleanup_installer($rxInstallerDir, 'config_bootstrap');
    }
    $dbhostResolved = $dbhost !== '' ? $dbhost : 'localhost';
    if (!defined('FAOXIMA_LAZY_MYSQLI') || FAOXIMA_LAZY_MYSQLI !== true) {
        $connect = rx_connect_mysqli($dbhostResolved, $usernamedb, $passworddb, $dbname);
        if ($connect instanceof mysqli) {
            @mysqli_set_charset($connect, 'utf8mb4');
            @mysqli_query($connect, "SET time_zone = '+03:30'");
        } else {
            $connect = null;
            error_log('config.php mysqli_connect failed (after retries).');
        }
    } else {
        $connect = null;
    }

    $dsn = 'mysql:host=' . $dbhostResolved . ';dbname=' . $dbname . ';charset=utf8mb4';
    try {
        $pdo = rx_connect_pdo($dsn, $usernamedb, $passworddb, $options);
        if ($pdo instanceof PDO && function_exists('rx_perf_wrap_pdo')) {
            rx_perf_wrap_pdo($pdo);
        }
    } catch (\PDOException $rxPdoError) {
        $pdo = null;
        error_log('config.php PDO connection failed: ' . $rxPdoError->getMessage());
    }
} else {
    $rxInstallerPending = is_file(__DIR__ . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'index.php');
    if (!$rxInstallerPending) {
        $rxConfigEmptyMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_config_empty.flag';
        if (!is_file($rxConfigEmptyMarker) || (time() - (int) @filemtime($rxConfigEmptyMarker)) > 3600) {
            error_log('config.php: database credentials are empty — fill $dbname/$usernamedb/$passworddb to enable DB-backed features.');
            @touch($rxConfigEmptyMarker);
        }
        unset($rxConfigEmptyMarker);
    }
    unset($rxInstallerPending);
}

$APIKEY                     = '';
$adminnumber                = '';
$domainhosts                = '';
$usernamebot                = '';
$telegramCurlTimeout        = 10;
$telegramStrictIpValidation = true;
$domainhosts                = rtrim(preg_replace('#^https?://#', '', $domainhosts), '/');

if (!defined('APP_ORIGIN') && $domainhosts !== '') {
    define('APP_ORIGIN', 'https://' . $domainhosts);
}

$GLOBALS['dbname']                     = $dbname;
$GLOBALS['usernamedb']                 = $usernamedb;
$GLOBALS['passworddb']                 = $passworddb;
$GLOBALS['dbhost']                     = $dbhost;
$GLOBALS['redis_host']                 = $redis_host;
$GLOBALS['redis_port']                 = $redis_port;
$GLOBALS['redis_password']             = $redis_password;
$GLOBALS['redis_database']             = $redis_database;
$GLOBALS['dsn']                        = $dsn;
$GLOBALS['options']                    = $options;
$GLOBALS['pdo']                        = $pdo;
$GLOBALS['connect']                    = $connect;
$GLOBALS['APIKEY']                     = $APIKEY;
$GLOBALS['adminnumber']                = $adminnumber;
$GLOBALS['domainhosts']                = $domainhosts;
$GLOBALS['usernamebot']                = $usernamebot;
$GLOBALS['telegramCurlTimeout']        = $telegramCurlTimeout;
$GLOBALS['telegramStrictIpValidation'] = $telegramStrictIpValidation;
