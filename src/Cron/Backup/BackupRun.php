<?php

namespace Faoxima\Cron\Backup;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\TaskState;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupRun
{
    private PDO $pdo;
    private $setting;
    private $reportThread;
    private string $root;
    private string $runtimeDir;
    private int $occurrence;
    private float $deadline;

    public static function authorizeRequest(string $root): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $authFile = $root . '/cron/.cron_internal_auth';
        $token = isset($_SERVER['HTTP_X_CRON_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CRON_TOKEN']) : '';
        $source = isset($_SERVER['HTTP_X_CRON_SOURCE']) ? trim((string) $_SERVER['HTTP_X_CRON_SOURCE']) : '';
        $valid = $source === 'cron-orchestrator' && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_auth_valid($authFile, $token);
        if (!$valid) {
            http_response_code(403);
            exit('Backup request is not authorized');
        }
    }

    public static function configureEnvironment(): void
    {
        date_default_timezone_set('Asia/Tehran');
        if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
            @putenv('TZ=Asia/Tehran');
        }
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL);
    }

    public static function acquireLock(string $root): void
    {
        $lockFile = $root . '/cronbot/backup.lock';
        $fh = @fopen($lockFile, 'c');
        if ($fh === false) {
            if (is_file($lockFile) && (time() - (int) @filemtime($lockFile)) < 600) {
                TaskState::rx_cron_task_defer('backup_lock_busy', 30);
                exit;
            }
            @file_put_contents($lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));
            register_shutdown_function(static function () use ($lockFile) {
                @unlink($lockFile);
            });
            return;
        }
        if (!@flock($fh, LOCK_EX | LOCK_NB)) {
            @fclose($fh);
            TaskState::rx_cron_task_defer('backup_lock_busy', 30);
            exit;
        }
        @ftruncate($fh, 0);
        @fwrite($fh, getmypid() . '|' . date('Y-m-d H:i:s'));
        @fflush($fh);
        register_shutdown_function(static function () use ($fh) {
            @flock($fh, LOCK_UN);
            @fclose($fh);
        });
    }

    public static function detachFromRequest(): void
    {
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
    }

    public static function configureLogging(string $root): string
    {
        $logFilePath = $root . '/cronbot/../logs/backup_' . date('Y-m-d') . '.log';
        $logDirectory = dirname($logFilePath);
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0755, true);
        }
        ini_set('log_errors', 1);
        ini_set('error_log', $logFilePath);
        define('LOG_LEVEL', 'ERROR');

        set_error_handler([BackupRuntime::class, 'errorHandler']);
        set_exception_handler([BackupRuntime::class, 'logException']);
        register_shutdown_function([BackupRuntime::class, 'shutdownHandler']);
        return $logFilePath;
    }

    public static function execute(string $root): void
    {
        global $dbhost, $dbname, $passworddb, $setting, $usernamedb;
        try {
            require_once $root . '/config.php';
            require_once $root . '/function.php';
            require_once $root . '/botapi.php';
            $run = self::open($root);
            $run->perform();
        } catch (Throwable $e) {
            BackupRuntime::logException($e, ['script' => 'backup_main']);
            TaskState::rx_cron_task_fail('backup_exception', false);
        }
    }

    private static function open(string $root): self
    {
        $pdo = Db::getDatabaseConnection();
        if (!($pdo instanceof PDO) && function_exists('get_pdo_connection')) {
            $pdo = get_pdo_connection();
        }
        if (!($pdo instanceof PDO)) {
            throw new RuntimeException('Failed to establish PDO connection');
        }
        $run = new self();
        $run->pdo = $pdo;
        $run->root = $root;
        $run->reportThread = Db::select("topicid", "idreport", "report", "backupfile", "select")['idreport'] ?? null;
        $run->setting = Db::select("setting", "*");
        $GLOBALS['setting'] = $run->setting;
        $run->deadline = microtime(true) + 240;
        $run->occurrence = TaskState::rx_cron_task_occurrence(time());
        $run->runtimeDir = $root . '/cronbot' . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($run->runtimeDir) && !@mkdir($run->runtimeDir, 0700, true) && !is_dir($run->runtimeDir)) {
            throw new RuntimeException('Cannot create backup delivery directory');
        }
        return $run;
    }

    private function perform(): void
    {
        BackupRuntime::rxBackupDeliveryProcess($this->pdo, $this->setting, $this->reportThread, $this->deadline);
        if ($this->occurrenceAlreadyHandled()) {
            $this->settle('backup_occurrence_handled');
            return;
        }
        if ($this->refreshThrottled()) {
            BackupRuntime::logMessage('INFO', 'Backup delivery is rate-limited; keeping the pending archive instead of exporting again', [
                'occurrence' => $this->occurrence,
            ]);
            $this->settle('backup_refresh_throttled');
            return;
        }
        $workDir = $this->runtimeDir . DIRECTORY_SEPARATOR . 'work-' . bin2hex(random_bytes(12));
        if (!mkdir($workDir, 0700)) {
            throw new RuntimeException('Cannot create private backup directory');
        }
        $sql = $workDir . '/backup.sql';
        $zipFile = $workDir . '/backup.zip';
        $persistentZip = $this->runtimeDir . '/backup-' . $this->occurrence . '-' . bin2hex(random_bytes(6)) . '.zip';
        register_shutdown_function(static function () use ($workDir) {
            foreach (['backup.sql', 'backup.zip', 'backup.sql.tmp', 'backup.sql.tmp.err'] as $name) {
                $path = $workDir . '/' . $name;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($workDir);
        });

        $this->createDump($sql);
        $this->assertDumpUsable($sql);
        if (!class_exists('ZipArchive')) {
            BackupRuntime::logMessage('CRITICAL', 'ZipArchive class not available');
            $this->abort("❌ ماژول ZipArchive در هاست فعال نیست و امکان ارسال بکاپ وجود ندارد.", 'ziparchive_unavailable', 'ZipArchive not available');
        }
        $this->buildArchive($sql, $zipFile, $persistentZip);
        $deliveryId = $this->recordDelivery($persistentZip);
        $this->deliver($deliveryId);
        if (file_exists($sql)) {
            unlink($sql);
        }
    }

    private function refreshThrottled(): bool
    {
        try {
            $now = time();
            $stmt = $this->pdo->prepare("SELECT 1 FROM cron_backup_delivery WHERE state = 'pending' AND next_attempt_at > ? AND created_at > FROM_UNIXTIME(?) LIMIT 1");
            $stmt->execute([$now, $now - 300]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function settle(string $message): void
    {
        $next = 0;
        try {
            $next = (int) $this->pdo->query("SELECT MIN(next_attempt_at) FROM cron_backup_delivery WHERE state = 'pending' AND attempts < 5")->fetchColumn();
        } catch (Throwable $e) {
            $next = 0;
        }
        if ($next > 0 && $this->retryFitsBeforeNextSlot($next)) {
            TaskState::rx_cron_task_defer('backup_delivery_pending', max(60, min(900, $next - time())));
            return;
        }
        TaskState::rx_cron_task_complete($message);
    }

    private function retryFitsBeforeNextSlot(int $retryAt): bool
    {
        try {
            $schedules = \Faoxima\Core\Cron\Schedules::loadCronSchedules();
            $interval = \Faoxima\Core\Cron\Schedules::rx_cron_schedule_interval_seconds($schedules['backupbot'] ?? ['unit' => 'hour', 'value' => 5]);
        } catch (Throwable $e) {
            return false;
        }
        return $interval > 0 && $retryAt + 60 < $this->occurrence + $interval;
    }

    private function supersedeOlder(int $newId): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id, state, file_path FROM cron_backup_delivery WHERE id < ? AND file_path <> '' AND state IN ('pending','uncertain','failed') ORDER BY id ASC LIMIT 50");
            $stmt->execute([$newId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $update = $this->pdo->prepare("UPDATE cron_backup_delivery SET last_error = CASE WHEN state = 'failed' THEN last_error ELSE 'superseded_by_newer_backup' END, state = CASE WHEN state = 'failed' THEN 'failed' ELSE 'superseded' END, file_path = '', next_attempt_at = 0, updated_at = NOW() WHERE id = ? AND state = ? AND file_path = ?");
            $runtimeDir = realpath($this->runtimeDir);
            foreach ($rows as $row) {
                $path = (string) ($row['file_path'] ?? '');
                $update->execute([(int) $row['id'], (string) $row['state'], $path]);
                if ($update->rowCount() !== 1) {
                    continue;
                }
                if ($runtimeDir !== false && is_file($path) && preg_match('/^backup-[0-9]+-[a-f0-9]+\.zip$/', basename($path)) && realpath(dirname($path)) === $runtimeDir) {
                    @unlink($path);
                }
            }
        } catch (Throwable $e) {
            BackupRuntime::logException($e, ['function' => 'backup_supersede_older']);
        }
    }

    private function occurrenceAlreadyHandled(): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM cron_backup_delivery WHERE occurrence = ? LIMIT 1');
            $stmt->execute([$this->occurrence]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function createDump(string $sql): void
    {
        global $dbhost, $dbname, $passworddb, $usernamedb;
        $created = false;
        $tmp = $sql . '.tmp';
        $tmpErr = $tmp . '.err';
        if (BackupRuntime::isExecAvailable() && function_exists('proc_open')) {
            $result = BackupRuntime::runMysqldumpWithFallback($dbhost, $usernamedb, $passworddb, $dbname, $tmp, $tmpErr, $this->deadline);
            if ($result['success']) {
                @rename($tmp, $sql);
                $created = file_exists($sql) && filesize($sql) > 0;
            } else {
                @unlink($tmp);
            }
            @unlink($tmpErr);
        }
        if (!$created && microtime(true) < $this->deadline) {
            $created = BackupRuntime::createSqlDump($this->pdo, $dbname, $sql, $this->deadline);
        }
        if (!$created) {
            BackupRuntime::logMessage('CRITICAL', 'Failed to create database backup using any method');
            $this->abort("❌❌❌❌❌❌خطا در بکاپ گرفتن لطفا به پشتیبانی اطلاع دهید", 'backup_creation_failed', 'Database backup creation failed');
        }
    }

    private function assertDumpUsable(string $sql): void
    {
        if (!file_exists($sql) || filesize($sql) === 0) {
            BackupRuntime::logMessage('ERROR', 'Backup file is empty or does not exist', ['file' => $sql]);
            $this->abort("❌ فایل بکاپ ایجاد شده خالی است. لطفا بررسی شود.", 'backup_empty', 'Backup file is empty', $sql);
        }
    }

    private function buildArchive(string $sql, string $zipFile, string $persistentZip): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            BackupRuntime::logMessage('ERROR', 'Failed to open zip file for writing', ['file' => $zipFile]);
            TaskState::rx_cron_task_fail('backup_zip_open_failed', false);
            throw new RuntimeException('Zip file creation failed');
        }
        $zip->addFile($sql, basename($sql));

        $vpnbotPath = $this->root . '/vpnbot';
        if (is_dir($vpnbotPath)) {
            BackupRuntime::addPathToZip($zip, $vpnbotPath, $this->root . '/');
        } else {
            BackupRuntime::logMessage('WARNING', 'vpnbot directory not found for inclusion in backup zip', [
                'path' => $vpnbotPath,
            ]);
        }

        $zip->close();
        if (!file_exists($zipFile) || filesize($zipFile) === 0) {
            BackupRuntime::logMessage('ERROR', 'Zip file is empty or does not exist', ['file' => $zipFile]);
            $this->abort("❌ فایل فشرده‌ی بکاپ ایجاد نشد یا خالی است.", 'backup_zip_failed', 'Zip file creation failed', $zipFile);
        }
        if (!@rename($zipFile, $persistentZip)) {
            if (!@copy($zipFile, $persistentZip)) {
                TaskState::rx_cron_task_fail('backup_persist_failed', false);
                throw new RuntimeException('Cannot persist backup archive');
            }
            @unlink($zipFile);
        }
    }

    private function recordDelivery(string $persistentZip): int
    {
        $id = 0;
        try {
            if (!BackupRuntime::rxBackupDeliveryEnsure($this->pdo)) {
                throw new RuntimeException('Backup delivery table unavailable');
            }
            $insert = $this->pdo->prepare("INSERT INTO cron_backup_delivery (occurrence, state, attempts, next_attempt_at, file_path, chat_id, thread_id, created_at, updated_at) VALUES (?, 'pending', 0, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))");
            $now = time();
            $hold = (int) $this->pdo->query("SELECT COALESCE(MAX(next_attempt_at), 0) FROM cron_backup_delivery WHERE state = 'pending'")->fetchColumn();
            $insert->execute([
                $this->occurrence,
                max($now, $hold),
                $persistentZip,
                (string) ($this->setting['Channel_Report'] ?? ''),
                $this->reportThread,
                $now,
                $now,
            ]);
            $id = (int) $this->pdo->lastInsertId();
        } catch (Throwable $e) {
            BackupRuntime::logException($e, ['function' => 'backup_delivery_insert']);
        }
        if ($id > 0) {
            $this->supersedeOlder($id);
        }
        return $id;
    }

    private function deliver(int $deliveryId): void
    {
        $state = 'uncertain';
        if ($deliveryId > 0) {
            try {
                $stmt = $this->pdo->prepare('SELECT * FROM cron_backup_delivery WHERE id = ? LIMIT 1');
                $stmt->execute([$deliveryId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $state = is_array($row) ? BackupRuntime::rxBackupDeliverySend($this->pdo, $row, $this->setting, $this->reportThread) : 'uncertain';
            } catch (Throwable $e) {
                BackupRuntime::logException($e, ['function' => 'rxBackupDeliverySend']);
                $state = 'uncertain';
            }
        }
        $ok = $state === 'sent';
        BackupRuntime::logMessage('INFO', 'Telegram sendDocument for DB backup attempted', [
            'result' => $ok ? 'success' : $state,
            'occurrence' => $this->occurrence,
        ]);
        if ($ok) {
            TaskState::rx_cron_task_complete('backup_delivered');
        } elseif ($state === 'pending' || $state === 'busy') {
            $this->settle('backup_delivery_queued');
        } elseif ($state === 'uncertain' && $deliveryId > 0) {
            TaskState::rx_cron_task_complete('backup_retained_for_delivery_review');
        } elseif ($state === 'uncertain') {
            TaskState::rx_cron_task_uncertain('backup_delivery_record_missing', 1800);
        } else {
            TaskState::rx_cron_task_fail('backup_delivery_failed', false);
        }
    }

    private function abort(string $telegramText, string $taskReason, string $message, ?string $discard = null): void
    {
        $chatId = trim((string) ($this->setting['Channel_Report'] ?? ''));
        if ($chatId !== '' && $chatId !== '0' && (int) $this->reportThread > 0) {
            telegram('sendmessage', [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'message_thread_id' => (int) $this->reportThread,
                'text' => $telegramText,
            ]);
        }
        if ($discard !== null && file_exists($discard)) {
            unlink($discard);
        }
        TaskState::rx_cron_task_fail($taskReason, false);
        throw new RuntimeException($message);
    }
}
