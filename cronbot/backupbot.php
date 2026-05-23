<?php
date_default_timezone_set('Asia/Tehran');
@putenv('TZ=Asia/Tehran');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);


$rxBackupLock = __DIR__ . '/backup.lock';
if (is_file($rxBackupLock) && (time() - (int) @filemtime($rxBackupLock)) < 600) {
    exit;
}
@file_put_contents($rxBackupLock, getmypid() . '|' . date('Y-m-d H:i:s'));
register_shutdown_function(static function () use ($rxBackupLock) { @unlink($rxBackupLock); });


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
    function createSqlDump(?PDO $pdo, $databaseName, $filePath)
    {
        if (!($pdo instanceof PDO)) {
            logMessage('ERROR', 'createSqlDump: PDO connection is not available');
            return false;
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
    $reportbackup = select("topicid", "idreport", "report", "backupfile", "select")['idreport'];
    $destination = __DIR__;
    $setting = select("setting", "*");
    $sourcefir = dirname($destination);
    $backup_file_name = 'backup_' . date("Y-m-d") . '.sql';
    $zip_file_name = 'backup_' . date("Y-m-d") . '.zip';
    $dumpCreated = false;
    $command = "mysqldump -h localhost -u {$usernamedb} -p'{$passworddb}' --no-tablespaces {$dbname} > {$backup_file_name}";
    if (isExecAvailable()) {
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        $dumpCreated = ($return_var === 0 && file_exists($backup_file_name));
        if (!$dumpCreated) {
            logMessage('ERROR', 'mysqldump command failed', [
                'return_code' => $return_var,
                'output' => implode("\n", $output)
            ]);
        }
    }
    if (!$dumpCreated) {
        $dumpCreated = createSqlDump($pdoInstance, $dbname, $backup_file_name);
    }
    if (!$dumpCreated) {
        logMessage('CRITICAL', 'Failed to create database backup using any method');
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'text' => "❌❌❌❌❌❌خطا در بکاپ گرفتن لطفا به پشتیبانی اطلاع دهید",
        ]);
        throw new RuntimeException('Database backup creation failed');
    }
    if (!file_exists($backup_file_name) || filesize($backup_file_name) === 0) {
        logMessage('ERROR', 'Backup file is empty or does not exist', ['file' => $backup_file_name]);
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'text' => "❌ فایل بکاپ ایجاد شده خالی است. لطفا بررسی شود.",
        ]);
        if (file_exists($backup_file_name)) {
            unlink($backup_file_name);
        }
        throw new RuntimeException('Backup file is empty');
    }
    if (!class_exists('ZipArchive')) {
        logMessage('CRITICAL', 'ZipArchive class not available');
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'text' => "❌ ماژول ZipArchive در هاست فعال نیست و امکان ارسال بکاپ وجود ندارد.",
        ]);
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
                'message_thread_id' => $reportbackup,
                'text' => "❌ فایل فشرده‌ی بکاپ ایجاد نشد یا خالی است.",
            ]);
            if (file_exists($zip_file_name)) {
                unlink($zip_file_name);
            }
            if (file_exists($backup_file_name)) {
                unlink($backup_file_name);
            }
            throw new RuntimeException('Zip file creation failed');
        }

        $sendResult = telegram('sendDocument', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'document' => new CURLFile($zip_file_name),
            'caption' => "📦 خروجی دیتابیس ربات اصلی\n\nبرای حمایت از این پروژه، لطفاً در گیت‌هاب به آن ستاره (Star) دهید.\n⭐ https://github.com/Mmd-Amir/Faoxima",
        ]);

        logMessage('INFO', 'Telegram sendDocument for DB backup attempted', [
            'result' => $sendResult ? 'success' : 'failed',
            'caption_length' => strlen("📦 خروجی دیتابیس ربات اصلی\n\nبرای حمایت از این پروژه، لطفاً در گیت‌هاب به آن ستاره (Star) دهید.\n⭐ https://github.com/Mmd-Amir/Faoxima"),
        ]);
        if (file_exists($zip_file_name)) {
            unlink($zip_file_name);
        }
        if (file_exists($backup_file_name)) {
            unlink($backup_file_name);
        }
    } else {
        logMessage('ERROR', 'Failed to open zip file for writing', ['file' => $zip_file_name]);
        throw new RuntimeException('Zip file creation failed');
    }
} catch (Throwable $e) {
    logException($e, ['script' => 'backup_main']);
}

