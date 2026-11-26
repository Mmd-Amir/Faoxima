<?php

namespace Faoxima\Cron\Backup;

use PDO;
use CURLFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupRuntime
{
    public static function logMessage($level, $message, $context = [])
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

    public static function logException(Throwable $e, $additionalContext = [])
    {
        $context = array_merge([
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], $additionalContext);
        self::logMessage('ERROR', 'Exception occurred: ' . $e->getMessage(), $context);
    }

    public static function shutdownHandler()
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::logMessage('CRITICAL', 'Fatal Error: ' . $error['message'], [
                'type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
        }
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline)
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
        self::logMessage('ERROR', sprintf('[%s] %s', $errorType, $errstr), [
            'file' => $errfile,
            'line' => $errline,
            'error_code' => $errno,
        ]);
        return false;
    }

    public static function isExecAvailable()
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

    public static function rxExecWithDeadline(string $command, float $deadline, string $stdoutFile, string $stderrFile): int
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

    public static function runMysqldumpWithFallback($dbhost, $usernamedb, $passworddb, $dbname, $tmpDump, $tmpDumpErr, ?float $deadline = null)
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
            $return_var = self::rxExecWithDeadline($command, $deadline, $tmpDump, $tmpDumpErr);
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

    public static function createSqlDump(?PDO $pdo, $databaseName, $filePath, ?float $deadline = null)
    {
        if (!($pdo instanceof PDO)) {
            self::logMessage('ERROR', 'createSqlDump: PDO connection is not available');
            return false;
        }
        if ($deadline === null) {
            $deadline = microtime(true) + 240;
        }
        try {
            $handle = @fopen($filePath, 'w');
            if ($handle === false) {
                self::logMessage('ERROR', 'Unable to open dump file for writing', ['file' => $filePath]);
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
            self::logException($throwable, ['function' => 'createSqlDump', 'database' => $databaseName]);
            return false;
        }
    }

    public static function addPathToZip(ZipArchive $zip, $path, $basePath)
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
            self::logException($e, ['function' => 'addPathToZip', 'path' => $path]);
        }
    }

    public static function rxBackupDeliveryEnsure(PDO $pdo): bool
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
            self::logException($e, ['function' => 'rxBackupDeliveryEnsure']);
            return false;
        }
    }

    public static function rxBackupDeliverySend(PDO $pdo, array $row, $setting, $reportbackup): string
    {
        $id = (int) ($row['id'] ?? 0);
        $now = time();
        if ($id <= 0) {
            return 'failed';
        }
        if (!\Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_allowed((int) ($row['occurrence'] ?? 0), $now)) {
            $expire = $pdo->prepare("UPDATE cron_backup_delivery SET state = 'failed', next_attempt_at = 0, last_error = 'catchup_window_elapsed', updated_at = NOW() WHERE id = ? AND state = 'pending'");
            $expire->execute([$id]);
            return 'failed';
        }
        $configuredChatId = trim((string) ($setting['Channel_Report'] ?? ''));
        $chatId = trim((string) ($row['chat_id'] ?? ''));
        $threadId = (int) ($row['thread_id'] ?? 0);
        if ($chatId === '' || $chatId === '0') {
            $chatId = $configuredChatId;
            $threadId = (int) $reportbackup;
        } elseif ($threadId <= 0 && $chatId === $configuredChatId) {
            $threadId = (int) $reportbackup;
        }
        if ($chatId === '' || $chatId === '0' || $threadId <= 0) {
            $reason = ($chatId === '' || $chatId === '0') ? 'backup_report_chat_missing' : 'backup_report_topic_missing';
            $defer = $pdo->prepare("UPDATE cron_backup_delivery SET next_attempt_at = ?, last_error = ?, updated_at = NOW() WHERE id = ? AND state = 'pending' AND next_attempt_at <= ?");
            $defer->execute([$now + 900, $reason, $id, $now]);
            if ($defer->rowCount() !== 1) {
                return 'busy';
            }
            self::logMessage('ERROR', 'Backup delivery deferred because the report destination is incomplete', ['reason' => $reason]);
            return 'pending';
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
            'chat_id' => $chatId,
            'parse_mode' => 'HTML',
            'message_thread_id' => $threadId,
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
        self::logMessage('ERROR', 'Telegram sendDocument for DB backup failed', [
            'state' => $state,
            'error_code' => $errorCode,
            'description' => $description,
            'attempts' => $attempts,
        ]);
        return $state;
    }

    public static function rxBackupDeliveryProcess(PDO $pdo, $setting, $reportbackup, float $deadline): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'uncertain' => 0, 'pending' => 0];
        if (!self::rxBackupDeliveryEnsure($pdo)) {
            return $stats;
        }
        $now = time();
        try {
            $expire = $pdo->prepare("UPDATE cron_backup_delivery SET state = 'failed', next_attempt_at = 0, last_error = 'catchup_window_elapsed', updated_at = NOW() WHERE state = 'pending' AND occurrence < ?");
            $expire->execute([$now - \Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_seconds()]);
            $old = $pdo->prepare("SELECT id, file_path FROM cron_backup_delivery WHERE state IN ('sent','failed','superseded') AND updated_at < FROM_UNIXTIME(?) LIMIT 100");
            $old->execute([$now - 2592000]);
            $oldIds = [];
            foreach ($old->fetchAll(PDO::FETCH_ASSOC) as $oldRow) {
                $oldPath = (string) ($oldRow['file_path'] ?? '');
                if ($oldPath !== '' && is_file($oldPath) && preg_match('/^backup-[0-9]+-[a-f0-9]+\.zip$/', basename($oldPath))) {
                    @unlink($oldPath);
                }
                $oldIds[] = (int) $oldRow['id'];
            }
            if ($oldIds) {
                $pdo->exec('DELETE FROM cron_backup_delivery WHERE id IN (' . implode(',', $oldIds) . ')');
            }
            $stale = $pdo->prepare("UPDATE cron_backup_delivery SET state = 'uncertain', next_attempt_at = ?, last_error = 'delivery_interrupted', updated_at = NOW() WHERE state = 'sending' AND updated_at < FROM_UNIXTIME(?)");
            $stale->execute([$now + 300, $now - 600]);
            $stmt = $pdo->prepare("SELECT * FROM cron_backup_delivery WHERE state = 'pending' AND attempts < 5 AND next_attempt_at <= ? ORDER BY id ASC LIMIT 3");
            $stmt->execute([$now]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logException($e, ['function' => 'rxBackupDeliveryProcess']);
            return $stats;
        }
        foreach ($rows as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }
            try {
                $state = self::rxBackupDeliverySend($pdo, $row, $setting, $reportbackup);
            } catch (Throwable $e) {
                self::logException($e, ['function' => 'rxBackupDeliverySend']);
                $state = 'uncertain';
            }
            if (isset($stats[$state])) {
                $stats[$state]++;
            }
        }
        return $stats;
    }
}
