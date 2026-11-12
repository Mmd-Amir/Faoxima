<?php
require_once __DIR__ . '/cron_task_state.php';
if (PHP_SAPI !== 'cli') {
    $rxCronAuthFile = dirname(__DIR__) . '/cron/.cron_internal_auth';
    $rxCronToken = isset($_SERVER['HTTP_X_CRON_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CRON_TOKEN']) : '';
    $rxCronSource = isset($_SERVER['HTTP_X_CRON_SOURCE']) ? trim((string) $_SERVER['HTTP_X_CRON_SOURCE']) : '';
    $rxCronAuthValid = false;
    if ($rxCronToken !== '' && $rxCronSource === 'cron-orchestrator' && is_readable($rxCronAuthFile)) {
        $rxCronAuthData = trim((string) @file_get_contents($rxCronAuthFile));
        $rxCronAuthParts = explode('|', $rxCronAuthData, 2);
        if (count($rxCronAuthParts) === 2) {
            $rxCronExpectedHash = trim((string) $rxCronAuthParts[0]);
            $rxCronIssuedAt = (int) $rxCronAuthParts[1];
            if ($rxCronExpectedHash !== '' && $rxCronIssuedAt > 0 && abs(time() - $rxCronIssuedAt) <= 120) {
                $rxCronAuthValid = hash_equals($rxCronExpectedHash, hash('sha256', $rxCronToken));
            }
        }
    }
    if (!$rxCronAuthValid) {
        http_response_code(403);
        exit('Backup request is not authorized');
    }
}
date_default_timezone_set('Asia/Tehran');
if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
    @putenv('TZ=Asia/Tehran');
}
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

$rxBackupLock = __DIR__ . '/backup.lock';
$rxBackupFh = @fopen($rxBackupLock, 'c');
if ($rxBackupFh === false) {
    if (is_file($rxBackupLock) && (time() - (int) @filemtime($rxBackupLock)) < 600) {
        exit;
    }
    @file_put_contents($rxBackupLock, getmypid() . '|' . date('Y-m-d H:i:s'));
    register_shutdown_function(static function () use ($rxBackupLock) { @unlink($rxBackupLock); });
} else {
    if (!@flock($rxBackupFh, LOCK_EX | LOCK_NB)) {
        @fclose($rxBackupFh);
        exit;
    }
    @ftruncate($rxBackupFh, 0);
    @fwrite($rxBackupFh, getmypid() . '|' . date('Y-m-d H:i:s'));
    @fflush($rxBackupFh);
    register_shutdown_function(static function () use ($rxBackupFh) {
        @flock($rxBackupFh, LOCK_UN);
        @fclose($rxBackupFh);
    });
}


if (function_exists('rx_cron_task_attach')) {
    rx_cron_task_attach('backupbot', 600);
}

ignore_user_abort(true);
@set_time_limit(0);
@ini_set('memory_limit', '256M');
if (function_exists('fastcgi_finish_request')) {
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Length: 0');
        header('Connection: close');
    }
    @ob_end_flush();
    @flush();
    @fastcgi_finish_request();
}

$logFilePath = __DIR__ . '/../logs/backup_' . date('Y-m-d') . '.log';
$logDirectory = dirname($logFilePath);
if (!is_dir($logDirectory)) {
    @mkdir($logDirectory, 0755, true);
}
ini_set('log_errors', 1);
ini_set('error_log', $logFilePath);
define('LOG_LEVEL', 'ERROR');
function logMessage($level, $message, $context = [])
{
    global $logFilePath;
    $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
    $currentLevel = defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO';
    if ($levels[$level] < $levels[$currentLevel]) {
        return;
    }
    $timestamp = date('Y-m-d H:i:s');
    $contextString = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    $caller = isset($backtrace[1]) ? $backtrace[1] : $backtrace[0];
    $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
    $line = isset($caller['line']) ? $caller['line'] : 0;
    $logEntry = sprintf(
        "[%s] [%s] [%s:%d] %s%s\n",
        $timestamp,
        strtoupper($level),
        $file,
        $line,
        $message,
        $contextString
    );
    @file_put_contents($logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    error_log(strip_tags($logEntry));
}
function logException(Throwable $e, $additionalContext = [])
{
    $context = array_merge([
        'exception_class' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ], $additionalContext);
    logMessage('ERROR', 'Exception occurred: ' . $e->getMessage(), $context);
}
function shutdownHandler()
{
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logMessage('CRITICAL', 'Fatal Error: ' . $error['message'], [
            'type' => $error['type'],
            'file' => $error['file'],
            'line' => $error['line'],
        ]);
    }
}
function errorHandler($errno, $errstr, $errfile, $errline)
{
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED',
    ];
    $errorType = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'UNKNOWN';
    logMessage('ERROR', sprintf('[%s] %s', $errorType, $errstr), [
        'file' => $errfile,
        'line' => $errline,
        'error_code' => $errno,
    ]);
    return false;
}
set_error_handler('errorHandler');
set_exception_handler('logException');
register_shutdown_function('shutdownHandler');
try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../function.php';
    require_once __DIR__ . '/../botapi.php';
    $pdoInstance = getDatabaseConnection();
    if (!($pdoInstance instanceof PDO) && function_exists('get_pdo_connection')) {
        $pdoInstance = get_pdo_connection();
    }
    if (!($pdoInstance instanceof PDO)) {
        throw new RuntimeException('Failed to establish PDO connection');
    }
    function isExecAvailable()
    {
        static $canExec;
        if ($canExec !== null) {
            return $canExec;
        }
        if (!function_exists('exec')) {
            $canExec = false;
            return $canExec;
        }
        $disabledFunctions = ini_get('disable_functions');
        if (!empty($disabledFunctions)) {
            $disabledList = array_map('trim', explode(',', $disabledFunctions));
            if (in_array('exec', $disabledList, true)) {
                $canExec = false;
                return $canExec;
            }
        }
        $canExec = true;
        return $canExec;
    }
    function rxExecWithDeadline(string $command, float $deadline, string $stdoutFile, string $stderrFile): int
    {
        if (!function_exists('proc_open')) {
            return 1;
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutFile, 'w'],
            2 => ['file', $stderrFile, 'w'],
        ];
        $pipes = [];
        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return 1;
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        $exitCode = 1;
        while (true) {
            $status = proc_get_status($process);
            if (!is_array($status) || empty($status['running'])) {
                $exitCode = isset($status['exitcode']) ? (int) $status['exitcode'] : 0;
                break;
            }
            if (microtime(true) >= $deadline) {
                @proc_terminate($process, 9);
                usleep(200000);
                $status = proc_get_status($process);
                if (is_array($status) && !empty($status['running'])) {
                    @proc_terminate($process, 9);
                }
                $exitCode = 124;
                break;
            }
            usleep(100000);
        }
        $closeCode = @proc_close($process);
        if ($exitCode === 0 && $closeCode !== 0 && $closeCode !== -1) {
            $exitCode = $closeCode;
        }
        return $exitCode;
    }

    function runMysqldumpWithFallback($dbhost, $usernamedb, $passworddb, $dbname, $tmpDump, $tmpDumpErr, ?float $deadline = null)
    {
        $baseArgs = sprintf(
            '-h %s -u %s --no-tablespaces %s',
            escapeshellarg((string)($dbhost !== '' ? $dbhost : 'localhost')),
            escapeshellarg((string)$usernamedb),
            escapeshellarg((string)$dbname)
        );
        $envPrefix = sprintf('MYSQL_PWD=%s', escapeshellarg((string)$passworddb));

        $attempts = [
            sprintf('%s mysqldump %s', $envPrefix, $baseArgs),
            sprintf('%s mysqldump --ssl-mode=DISABLED %s', $envPrefix, $baseArgs),
            sprintf('%s mysqldump --ssl=0 %s', $envPrefix, $baseArgs),
        ];
        if ($deadline === null) {
            $deadline = microtime(true) + 240;
        }

        $lastOutput = [];
        $lastReturnVar = 1;
        $lastStderr = '';

        foreach ($attempts as $command) {
            if (microtime(true) >= $deadline) {
                break;
            }
            @unlink($tmpDump);
            @unlink($tmpDumpErr);
            $return_var = rxExecWithDeadline($command, $deadline, $tmpDump, $tmpDumpErr);
            $stderrOutput = is_file($tmpDumpErr) ? (string) @file_get_contents($tmpDumpErr) : '';
            $lastOutput = [];
            $lastReturnVar = $return_var;
            $lastStderr = $stderrOutput;

            if ($return_var === 0 && file_exists($tmpDump) && filesize($tmpDump) > 0) {
                return ['success' => true, 'output' => $lastOutput, 'return_var' => $return_var, 'stderr' => $stderrOutput];
            }
        }

        @unlink($tmpDump);
        return ['success' => false, 'output' => $lastOutput, 'return_var' => $lastReturnVar, 'stderr' => $lastStderr];
    }

    function createSqlDump(?PDO $pdo, $databaseName, $filePath, ?float $deadline = null)
    {
        if (!($pdo instanceof PDO)) {
            logMessage('ERROR', 'createSqlDump: PDO connection is not available');
            return false;
        }
        if ($deadline === null) {
            $deadline = microtime(true) + 240;
        }
        try {
            $handle = @fopen($filePath, 'w');
            if ($handle === false) {
                logMessage('ERROR', 'Unable to open dump file for writing', ['file' => $filePath]);
                return false;
            }
            $header = sprintf("-- Database: `%s`\n-- Generated at: %s\n\nSET FOREIGN_KEY_CHECKS=0;\n\n", $databaseName, date('c'));
            fwrite($handle, $header);
            $tablesStmt = $pdo->query('SHOW TABLES');
            $tableCount = 0;
            while ($tableRow = $tablesStmt->fetch(PDO::FETCH_NUM)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('backup_deadline_exceeded');
                }
                $tableName = $tableRow[0];
                $tableCount++;
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
                $createData = $createStmt->fetch(PDO::FETCH_ASSOC);
                if (!isset($createData['Create Table'])) {
                    continue;
                }
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($handle, $createData['Create Table'] . ";\n\n");
                $dataStmt = $pdo->query("SELECT * FROM `{$tableName}`");
                $rowCount = 0;
                while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                    if (($rowCount % 100) === 0 && microtime(true) >= $deadline) {
                        throw new RuntimeException('backup_deadline_exceeded');
                    }
                    $rowCount++;
                    $columns = [];
                    $values = [];
                    foreach ($row as $column => $value) {
                        $columns[] = '`' . str_replace('`', '``', $column) . '`';
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote($value);
                        }
                    }
                    $insertLine = sprintf(
                        "INSERT INTO `%s` (%s) VALUES (%s);\n",
                        $tableName,
                        implode(', ', $columns),
                        implode(', ', $values)
                    );
                    fwrite($handle, $insertLine);
                }
                fwrite($handle, "\n");
            }
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
            return true;
        } catch (Throwable $throwable) {
            logException($throwable, ['function' => 'createSqlDump', 'database' => $databaseName]);
            return false;
        }
    }
    function addPathToZip(ZipArchive $zip, $path, $basePath)
    {
        $normalizedBase = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        try {
            if (is_dir($path)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                $fileCount = 0;
                foreach ($files as $file) {
                    $filePath = (string) $file;
                    $relativePath = ltrim(str_replace($normalizedBase, '', $filePath), DIRECTORY_SEPARATOR);
                    if ($file->isDir()) {
                        $zip->addEmptyDir($relativePath);
                    } elseif ($file->isFile()) {
                        $zip->addFile($filePath, $relativePath);
                        $fileCount++;
                    }
                }
            } elseif (is_file($path)) {
                $relativePath = ltrim(str_replace($normalizedBase, '', $path), DIRECTORY_SEPARATOR);
                $zip->addFile($path, $relativePath);
            }
        } catch (Throwable $e) {
            logException($e, ['function' => 'addPathToZip', 'path' => $path]);
        }
    }
    function rxBackupDeliveryEnsure(PDO $pdo): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cron_backup_delivery (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                occurrence BIGINT UNSIGNED NOT NULL DEFAULT 0,
                state VARCHAR(16) NOT NULL DEFAULT 'pending',
                attempts INT NOT NULL DEFAULT 0,
                next_attempt_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                file_path VARCHAR(512) NOT NULL DEFAULT '',
                chat_id VARCHAR(64) NOT NULL DEFAULT '',
                thread_id BIGINT NULL DEFAULT NULL,
                last_error VARCHAR(255) NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_backup_state_next (state, next_attempt_at),
                KEY idx_backup_occurrence (occurrence)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ready = true;
            return true;
        } catch (Throwable $e) {
            logException($e, ['function' => 'rxBackupDeliveryEnsure']);
            return false;
        }
    }

    function rxBackupDeliverySend(PDO $pdo, array $row, $setting, $reportbackup): string
    {
        $id = (int) ($row['id'] ?? 0);
        $now = time();
        if ($id <= 0) {
            return 'failed';
        }
        $claim = $pdo->prepare("UPDATE cron_backup_delivery SET state = 'sending', attempts = attempts + 1, last_error = NULL, updated_at = NOW() WHERE id = ? AND state = 'pending' AND attempts < 5 AND next_attempt_at <= ?");
        $claim->execute([$id, $now]);
        if ($claim->rowCount() !== 1) {
            return 'busy';
        }
        $filePath = (string) ($row['file_path'] ?? '');
        if ($filePath === '' || !is_file($filePath) || filesize($filePath) <= 0) {
            $fail = $pdo->prepare("UPDATE cron_backup_delivery SET state = 'failed', next_attempt_at = 0, last_error = 'backup_file_missing', updated_at = NOW() WHERE id = ? AND state = 'sending'");
            $fail->execute([$id]);
            return 'failed';
        }
        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        $sendResult = telegram('sendDocument', [
            'chat_id' => (string) ($row['chat_id'] ?? ($setting['Channel_Report'] ?? '')),
            'parse_mode' => 'HTML',
            'message_thread_id' => $row['thread_id'] ?? $reportbackup,
            'document' => new CURLFile($filePath),
            'caption' => "📦 خروجی دیتابیس ربات اصلی\n\nبرای حمایت از این پروژه، لطفاً در گیت‌هاب به آن ستاره (Star) دهید.\n⭐ https://github.com/Mmd-Amir/Faoxima",
            '_rx_curl_timeout' => 30,
            '_rx_no_network_retry' => true,
        ]);
        $ok = is_array($sendResult) && ($sendResult['ok'] ?? false) === true;
        if ($ok) {
            $done = $pdo->prepare("UPDATE cron_backup_delivery SET state = 'sent', last_error = NULL, next_attempt_at = 0, updated_at = NOW() WHERE id = ? AND state = 'sending'");
            $done->execute([$id]);
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            return 'sent';
        }
        $errorCode = is_array($sendResult) ? (int) ($sendResult['error_code'] ?? 0) : 0;
        $description = is_array($sendResult) ? substr((string) ($sendResult['description'] ?? 'no response'), 0, 200) : 'no response';
        if ($errorCode === 429) {
            $retryAfter = is_array($sendResult) ? max(60, (int) ($sendResult['parameters']['retry_after'] ?? 900)) : 900;
            $state = $attempts >= 5 ? 'failed' : 'pending';
            $next = $state === 'pending' ? $now + $retryAfter : 0;
        } elseif ($errorCode >= 400) {
            $state = $attempts >= 3 ? 'failed' : 'pending';
            $next = $state === 'pending' ? $now + 900 : 0;
        } else {
            $state = 'uncertain';
            $next = $now + 1800;
        }
        $update = $pdo->prepare("UPDATE cron_backup_delivery SET state = ?, next_attempt_at = ?, last_error = ?, updated_at = NOW() WHERE id = ? AND state = 'sending'");
        $update->execute([$state, $next, $description, $id]);
        logMessage('ERROR', 'Telegram sendDocument for DB backup failed', [
            'state' => $state,
            'error_code' => $errorCode,
            'description' => $description,
            'attempts' => $attempts,
        ]);
        return $state;
    }

    function rxBackupDeliveryProcess(PDO $pdo, $setting, $reportbackup, float $deadline): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'uncertain' => 0, 'pending' => 0];
        if (!rxBackupDeliveryEnsure($pdo)) {
            return $stats;
        }
        $now = time();
        try {
            $prune = $pdo->prepare("DELETE FROM cron_backup_delivery WHERE state IN ('sent','failed') AND updated_at < FROM_UNIXTIME(?) LIMIT 100");
            $prune->execute([$now - 2592000]);
            $stale = $pdo->prepare("UPDATE cron_backup_delivery SET state = 'uncertain', next_attempt_at = ?, last_error = 'delivery_interrupted', updated_at = NOW() WHERE state = 'sending' AND updated_at < FROM_UNIXTIME(?)");
            $stale->execute([$now + 300, $now - 600]);
            $stmt = $pdo->prepare("SELECT * FROM cron_backup_delivery WHERE state = 'pending' AND attempts < 5 AND next_attempt_at <= ? ORDER BY id ASC LIMIT 3");
            $stmt->execute([$now]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            logException($e, ['function' => 'rxBackupDeliveryProcess']);
            return $stats;
        }
        foreach ($rows as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }
            try {
                $state = rxBackupDeliverySend($pdo, $row, $setting, $reportbackup);
            } catch (Throwable $e) {
                logException($e, ['function' => 'rxBackupDeliverySend']);
                $state = 'uncertain';
            }
            if (isset($stats[$state])) {
                $stats[$state]++;
            }
        }
        return $stats;
    }

    $reportbackup = select("topicid", "idreport", "report", "backupfile", "select")['idreport'];
    $destination = __DIR__;
    $setting = select("setting", "*");
    $sourcefir = dirname($destination);
    $rxBackupDeadline = microtime(true) + 240;
    $rxBackupOccurrence = function_exists('rx_cron_task_occurrence') ? rx_cron_task_occurrence(time()) : time();
    $rxBackupRuntimeDir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($rxBackupRuntimeDir) && !@mkdir($rxBackupRuntimeDir, 0700, true) && !is_dir($rxBackupRuntimeDir)) {
        throw new RuntimeException('Cannot create backup delivery directory');
    }
    $rxDeliveryStats = rxBackupDeliveryProcess($pdoInstance, $setting, $reportbackup, $rxBackupDeadline);
    $rxBackupSentBefore = (int) ($rxDeliveryStats['sent'] ?? 0);
    $rxOpenDelivery = 0;
    try {
        $openStmt = $pdoInstance->prepare("SELECT COUNT(*) FROM cron_backup_delivery WHERE state IN ('pending','sending')");
        $openStmt->execute();
        $rxOpenDelivery = (int) $openStmt->fetchColumn();
    } catch (Throwable $e) {
        $rxOpenDelivery = 0;
    }
    if ($rxOpenDelivery > 0 || $rxBackupSentBefore > 0) {
        logMessage('INFO', 'Backup delivery is still pending; skipping new dump to avoid backlog', [
            'open' => $rxOpenDelivery,
            'stats' => $rxDeliveryStats,
        ]);
        if ($rxOpenDelivery > 0 && function_exists('rx_cron_task_defer')) {
            $nextStmt = $pdoInstance->query("SELECT MIN(next_attempt_at) FROM cron_backup_delivery WHERE state = 'pending'");
            $nextAttempt = (int) $nextStmt->fetchColumn();
            rx_cron_task_defer('backup_delivery_pending', max(60, $nextAttempt - time()));
        } elseif (function_exists('rx_cron_task_complete')) {
            rx_cron_task_complete('backup_delivery_processed');
        }
        return;
    }
    $rxSameOccurrence = false;
    try {
        $sameStmt = $pdoInstance->prepare('SELECT id FROM cron_backup_delivery WHERE occurrence = ? LIMIT 1');
        $sameStmt->execute([$rxBackupOccurrence]);
        $rxSameOccurrence = $sameStmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        $rxSameOccurrence = false;
    }
    if ($rxSameOccurrence) {
        if (function_exists('rx_cron_task_complete')) {
            rx_cron_task_complete('backup_occurrence_handled');
        }
        return;
    }
    $privateBackupDir = $rxBackupRuntimeDir . DIRECTORY_SEPARATOR . 'work-' . bin2hex(random_bytes(12));
    if (!mkdir($privateBackupDir, 0700)) {
        throw new RuntimeException('Cannot create private backup directory');
    }
    $backup_file_name = $privateBackupDir . '/backup.sql';
    $zip_file_name = $privateBackupDir . '/backup.zip';
    $rxPersistentZip = $rxBackupRuntimeDir . '/backup-' . $rxBackupOccurrence . '-' . bin2hex(random_bytes(6)) . '.zip';
    register_shutdown_function(static function () use ($privateBackupDir) {
        foreach (['backup.sql', 'backup.zip', 'backup.sql.tmp', 'backup.sql.tmp.err'] as $name) {
            $path = $privateBackupDir . '/' . $name;
            if (is_file($path)) @unlink($path);
        }
        @rmdir($privateBackupDir);
    });
    $dumpCreated = false;
    $tmpDump = $backup_file_name . '.tmp';
    $tmpDumpErr = $tmpDump . '.err';
    if (isExecAvailable() && function_exists('proc_open')) {
        $dumpResult = runMysqldumpWithFallback($dbhost, $usernamedb, $passworddb, $dbname, $tmpDump, $tmpDumpErr, $rxBackupDeadline);
        if ($dumpResult['success']) {
            @rename($tmpDump, $backup_file_name);
            $dumpCreated = file_exists($backup_file_name) && filesize($backup_file_name) > 0;
        } else {
            @unlink($tmpDump);
        }
        @unlink($tmpDumpErr);
    }
    if (!$dumpCreated && microtime(true) < $rxBackupDeadline) {
        $dumpCreated = createSqlDump($pdoInstance, $dbname, $backup_file_name, $rxBackupDeadline);
    }
    if (!$dumpCreated) {
        logMessage('CRITICAL', 'Failed to create database backup using any method');
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'parse_mode' => 'HTML',
            'message_thread_id' => $reportbackup,
            'text' => "❌❌❌❌❌❌خطا در بکاپ گرفتن لطفا به پشتیبانی اطلاع دهید",
        ]);
        if (function_exists('rx_cron_task_fail')) {
            rx_cron_task_fail('backup_creation_failed', false);
        }
        throw new RuntimeException('Database backup creation failed');
    }
    if (!file_exists($backup_file_name) || filesize($backup_file_name) === 0) {
        logMessage('ERROR', 'Backup file is empty or does not exist', ['file' => $backup_file_name]);
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'parse_mode' => 'HTML',
            'message_thread_id' => $reportbackup,
            'text' => "❌ فایل بکاپ ایجاد شده خالی است. لطفا بررسی شود.",
        ]);
        if (file_exists($backup_file_name)) {
            unlink($backup_file_name);
        }
        if (function_exists('rx_cron_task_fail')) {
            rx_cron_task_fail('backup_empty', false);
        }
        throw new RuntimeException('Backup file is empty');
    }
    if (!class_exists('ZipArchive')) {
        logMessage('CRITICAL', 'ZipArchive class not available');
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'parse_mode' => 'HTML',
            'message_thread_id' => $reportbackup,
            'text' => "❌ ماژول ZipArchive در هاست فعال نیست و امکان ارسال بکاپ وجود ندارد.",
        ]);
        if (function_exists('rx_cron_task_fail')) {
            rx_cron_task_fail('ziparchive_unavailable', false);
        }
        throw new RuntimeException('ZipArchive not available');
    }
    $zip = new ZipArchive();
    if ($zip->open($zip_file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $zip->addFile($backup_file_name, basename($backup_file_name));

        $vpnbotPath = $sourcefir . '/vpnbot';
        if (is_dir($vpnbotPath)) {
            addPathToZip($zip, $vpnbotPath, $sourcefir . '/');
        } else {
            logMessage('WARNING', 'vpnbot directory not found for inclusion in backup zip', [
                'path' => $vpnbotPath,
            ]);
        }

        $zip->close();
        if (!file_exists($zip_file_name) || filesize($zip_file_name) === 0) {
            logMessage('ERROR', 'Zip file is empty or does not exist', ['file' => $zip_file_name]);
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'parse_mode' => 'HTML',
                'message_thread_id' => $reportbackup,
                'text' => "❌ فایل فشرده‌ی بکاپ ایجاد نشد یا خالی است.",
            ]);
            if (file_exists($zip_file_name)) {
                unlink($zip_file_name);
            }
            if (function_exists('rx_cron_task_fail')) {
                rx_cron_task_fail('backup_zip_failed', false);
            }
            throw new RuntimeException('Zip file creation failed');
        }
        if (!@rename($zip_file_name, $rxPersistentZip)) {
            if (!@copy($zip_file_name, $rxPersistentZip)) {
                if (function_exists('rx_cron_task_fail')) {
                    rx_cron_task_fail('backup_persist_failed', false);
                }
                throw new RuntimeException('Cannot persist backup archive');
            }
            @unlink($zip_file_name);
        }
        $rxDeliveryId = 0;
        try {
            if (!rxBackupDeliveryEnsure($pdoInstance)) {
                throw new RuntimeException('Backup delivery table unavailable');
            }
            $insert = $pdoInstance->prepare("INSERT INTO cron_backup_delivery (occurrence, state, attempts, next_attempt_at, file_path, chat_id, thread_id, created_at, updated_at) VALUES (?, 'pending', 0, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))");
            $now = time();
            $insert->execute([
                $rxBackupOccurrence,
                $now,
                $rxPersistentZip,
                (string) ($setting['Channel_Report'] ?? ''),
                $reportbackup,
                $now,
                $now,
            ]);
            $rxDeliveryId = (int) $pdoInstance->lastInsertId();
        } catch (Throwable $e) {
            logException($e, ['function' => 'backup_delivery_insert']);
        }
        if ($rxDeliveryId > 0) {
            try {
                $deliveryStmt = $pdoInstance->prepare('SELECT * FROM cron_backup_delivery WHERE id = ? LIMIT 1');
                $deliveryStmt->execute([$rxDeliveryId]);
                $deliveryRow = $deliveryStmt->fetch(PDO::FETCH_ASSOC);
                $rxSendState = is_array($deliveryRow) ? rxBackupDeliverySend($pdoInstance, $deliveryRow, $setting, $reportbackup) : 'uncertain';
            } catch (Throwable $e) {
                logException($e, ['function' => 'rxBackupDeliverySend']);
                $rxSendState = 'uncertain';
            }
        } else {
            $rxSendState = 'uncertain';
        }
        $rxSendOk = $rxSendState === 'sent';
        logMessage('INFO', 'Telegram sendDocument for DB backup attempted', [
            'result' => $rxSendOk ? 'success' : $rxSendState,
            'occurrence' => $rxBackupOccurrence,
        ]);
        if ($rxSendOk) {
            if (function_exists('rx_cron_task_complete')) {
                rx_cron_task_complete('backup_delivered');
            }
        } elseif ($rxSendState === 'pending') {
            if (function_exists('rx_cron_task_defer')) {
                rx_cron_task_defer('backup_delivery_pending', 900);
            }
        } elseif ($rxSendState === 'uncertain' && $rxDeliveryId > 0) {
            if (function_exists('rx_cron_task_complete')) {
                rx_cron_task_complete('backup_retained_for_delivery_review');
            }
        } elseif ($rxSendState === 'uncertain') {
            if (function_exists('rx_cron_task_uncertain')) {
                rx_cron_task_uncertain('backup_delivery_record_missing', 1800);
            }
        } else {
            if (function_exists('rx_cron_task_fail')) {
                rx_cron_task_fail('backup_delivery_failed', false);
            }
        }
        if (file_exists($backup_file_name)) {
            unlink($backup_file_name);
        }
    } else {
        logMessage('ERROR', 'Failed to open zip file for writing', ['file' => $zip_file_name]);
        if (function_exists('rx_cron_task_fail')) {
            rx_cron_task_fail('backup_zip_open_failed', false);
        }
        throw new RuntimeException('Zip file creation failed');
    }
} catch (Throwable $e) {
    logException($e, ['script' => 'backup_main']);
    if (function_exists('rx_cron_task_fail')) {
        rx_cron_task_fail('backup_exception', false);
    }
}

