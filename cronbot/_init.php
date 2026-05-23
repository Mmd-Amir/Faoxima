<?php


if (!defined('RX_CRON_INIT_LOADED')) {
    define('RX_CRON_INIT_LOADED', true);

    @date_default_timezone_set('Asia/Tehran');
    @putenv('TZ=Asia/Tehran');

    @ignore_user_abort(true);
    @set_time_limit(180);
    @ini_set('memory_limit', '256M');
    @ini_set('display_errors', 0);
    @ini_set('display_startup_errors', 0);


    if (function_exists('fastcgi_finish_request') && PHP_SAPI !== 'cli') {
        if (!headers_sent()) {
            @header('Content-Type: text/plain; charset=utf-8');
            @header('Content-Length: 0');
            @header('Connection: close');
        }
        @ob_end_flush();
        @flush();
        @fastcgi_finish_request();
    }
}

if (!function_exists('rx_cron_boot')) {


    function rx_cron_boot(string $jobName, int $maxAgeSeconds = 300): void
    {
        $jobName = preg_replace('/[^A-Za-z0-9_\-]/', '', $jobName);
        if ($jobName === '') {
            return;
        }
        $lockFile = __DIR__ . DIRECTORY_SEPARATOR . $jobName . '.lock';
        if (is_file($lockFile)) {
            $age = time() - (int) @filemtime($lockFile);
            if ($age >= 0 && $age < $maxAgeSeconds) {
                exit;
            }
            @unlink($lockFile);
        }
        @file_put_contents($lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));
        register_shutdown_function(static function () use ($lockFile) {
            @unlink($lockFile);
        });
    }
}


if (!function_exists('rx_cron_require_or_skip')) {


    function rx_cron_require_or_skip(string $jobName, array $files): bool
    {
        foreach ($files as $file) {
            if (!is_file($file)) {
                $msg = '[' . date('Y-m-d H:i:s') . '] [cron:' . $jobName . '] missing required file: ' . $file
                     . ' — skipping this run (upload the file to fix).';
                @error_log($msg);
                @file_put_contents(__DIR__ . '/_missing_files.log', $msg . PHP_EOL, FILE_APPEND);
                return false;
            }
        }
        foreach ($files as $file) {
            require_once $file;
        }
        return true;
    }
}


if (!function_exists('rx_cron_db_ready')) {


    function rx_cron_db_ready(string $jobName = 'cron'): bool
    {
        global $pdo, $connect;
        $pdoOk     = isset($pdo) && $pdo instanceof PDO;
        $connectOk = isset($connect) && $connect !== null;
        if ($pdoOk || $connectOk) {
            return true;
        }


        $marker = __DIR__ . '/_db_unavailable.flag';
        $age    = is_file($marker) ? (time() - (int) @filemtime($marker)) : 99999;
        if ($age > 3600) {
            $msg = '[' . date('Y-m-d H:i:s') . '] [cron:' . $jobName . '] DB connection unavailable'
                 . ' — check config.php credentials. Skipping silently for the next hour.';
            @error_log($msg);
            @file_put_contents(__DIR__ . '/_db_unavailable.log', $msg . PHP_EOL, FILE_APPEND);
            @touch($marker);
        }
        return false;
    }
}


if (!function_exists('rx_cron_load_payment_context')) {


    function rx_cron_load_payment_context(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $root = dirname(__DIR__);
        $required = [
            $root . '/config.php',
            $root . '/botapi.php',
            $root . '/panels.php',
            $root . '/function.php',
            $root . '/jdf.php',
        ];
        if (!rx_cron_require_or_skip('payment_context', $required)) {
            $cached = [
                'setting'        => [],
                'paymentreports' => null,
                'datatextbot'    => [],
                'managePanel'    => null,
                'db_ready'       => false,
            ];
            return $cached;
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require_once $root . '/vendor/autoload.php';
        }


        $hasPdo = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO;
        $hasConn = isset($GLOBALS['connect']) && $GLOBALS['connect'] !== null;
        if (!$hasPdo && !$hasConn) {
            $cached = [
                'setting'        => [],
                'paymentreports' => null,
                'datatextbot'    => [],
                'managePanel'    => null,
                'db_ready'       => false,
            ];
            return $cached;
        }

        $setting = select('setting', '*');

        $reportRow = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
        $paymentreports = is_array($reportRow) ? ($reportRow['idreport'] ?? null) : null;

        $datatextbotget = select('textbot', '*', null, null, 'fetchAll');
        $datatextbot = [
            'textafterpay'       => '',
            'textaftertext'      => '',
            'textmanual'         => '',
            'textselectlocation' => '',
            'text_wgdashboard'   => '',
            'textafterpayibsng'  => '',
        ];
        if (is_array($datatextbotget)) {
            foreach ($datatextbotget as $row) {
                $key = $row['id_text'] ?? '';
                if (isset($datatextbot[$key])) {
                    $datatextbot[$key] = $row['text'] ?? '';
                }
            }
        }

        $cached = [
            'setting'        => $setting,
            'paymentreports' => $paymentreports,
            'datatextbot'    => $datatextbot,
            'managePanel'    => new ManagePanel(),
            'db_ready'       => true,
        ];
        return $cached;
    }
}

