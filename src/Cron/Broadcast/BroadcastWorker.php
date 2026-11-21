<?php

declare(strict_types=1);

namespace Faoxima\Cron\Broadcast;

use Faoxima\Admin\Broadcast\BroadcastQueue;
use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\TaskQueue;
use Faoxima\Cron\Runtime\TaskState;
use PDO;
use Throwable;

final class BroadcastWorker
{
    private const BATCH_SIZE = 50;
    private const SOFT_TIME_LIMIT = 27;
    private const CHECKPOINT_EVERY = 1;

    private string $baseDir;
    private ?PDO $pdo;
    private int $workerId;
    private int $workersTotal = 1;
    private string $lockFile;

    private string $infoFile;
    private string $usersFileTxt;
    private string $usersFileJson;
    private string $inflightFile = '';
    private string $sentFile = '';
    private string $claimLockFile = '';
    private string $reportLockFile = '';
    private string $infoLockFile;

    private ?string $bcToken = null;
    private array $info = [];
    private ?array $active = null;
    private array $texts = [];

    private bool $hadException = false;
    private int $continuationDelay = 0;
    private string $continuationReason = '';

    private bool $usingTxtMode = false;
    private bool $usingJsonMode = false;
    private array $totals = [];
    private float $batchStart = 0.0;
    private int $processed = 0;
    private int $bSuccess = 0;
    private int $bBlocked = 0;
    private int $bDeleted = 0;
    private int $bFailed = 0;
    private int $bChatNotFound = 0;
    private array $applied = ['success' => 0, 'blocked' => 0, 'deleted' => 0, 'failed' => 0, 'chat_not_found' => 0];
    private string $stop = 'running';

    public function __construct(string $baseDir, $pdo)
    {
        $this->baseDir = $baseDir;
        $this->pdo = $pdo instanceof PDO ? $pdo : null;
        $workerId = (int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0);
        $this->workerId = max(0, min(15, $workerId));
        $this->lockFile = $baseDir . '/broadcast' . ($this->workerId > 0 ? "_w{$this->workerId}" : '') . '.lock';
        $this->infoFile = $baseDir . '/info';
        $this->usersFileTxt = $baseDir . '/users.txt';
        $this->usersFileJson = $baseDir . '/users.json';
        $this->infoLockFile = $this->infoFile . '.wlock';
    }

    public static function configureRuntime(): void
    {
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');
        @set_time_limit(35);
        @ini_set('memory_limit', '256M');
        ignore_user_abort(true);

        date_default_timezone_set('Asia/Tehran');
        if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
            @putenv('TZ=Asia/Tehran');
        }
    }

    public static function missingRequiredFile(string $baseDir, array $files): ?string
    {
        foreach ($files as $file) {
            if (!is_file($file)) {
                $msg = '[' . date('Y-m-d H:i:s') . '] [cron:sendmessage] missing required file: ' . $file
                     . ' — skipping this run (upload the file to fix).';
                @error_log($msg);
                @file_put_contents($baseDir . '/_missing_files.log', $msg . PHP_EOL, FILE_APPEND);
                return $file;
            }
        }
        return null;
    }

    public static function pauseForDatabaseOutage(string $baseDir): void
    {
        TaskState::rx_cron_task_defer('db_unavailable', 120);
        if (is_file($baseDir . '/info')) {
            BroadcastQueue::rx_broadcast_info_update($baseDir . '/info', null, static function (array &$c) {
                $st = (string) ($c['status'] ?? '');
                if ($st === 'cancelled' || $st === 'finished') {
                    return false;
                }
                BroadcastQueue::rx_broadcast_apply_pause($c, ['class' => 'temporary_database', 'temporary' => true, 'code' => 0, 'retry_after' => 0, 'detail' => 'db_unavailable', 'manual' => false]);
            });
        }
    }

    public function log(string $line): void
    {
        if (!is_file($this->baseDir . '/broadcast_log.on')) {
            return;
        }
        $f = $this->baseDir . '/broadcast_run.log';
        if (is_file($f) && (int) @filesize($f) > 2 * 1024 * 1024) {
            @rename($f, $f . '.1');
        }
        @file_put_contents(
            $f,
            '[' . date('Y-m-d H:i:s') . "][w{$this->workerId}] " . $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function complete(string $message): void
    {
        TaskState::rx_cron_task_complete($message);
    }

    public function acquireLock(): void
    {
        $lockFh = @fopen($this->lockFile, 'c');
        if ($lockFh === false) {
            if (file_exists($this->lockFile) && (time() - (int) @filemtime($this->lockFile)) < 90) {
                $this->log('SKIP — قفل فعال است (mtime fallback) age=' . (time() - (int) @filemtime($this->lockFile)) . 's');
                exit;
            }
            @file_put_contents($this->lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));
        } else {
            if (!@flock($lockFh, LOCK_EX | LOCK_NB)) {
                $this->log('SKIP — قفل فعال است (اجرای دیگری همین حالا در حال اجراست)');
                @fclose($lockFh);
                exit;
            }
            @ftruncate($lockFh, 0);
            @fwrite($lockFh, getmypid() . '|' . date('Y-m-d H:i:s'));
            @fflush($lockFh);
            register_shutdown_function(static function () use ($lockFh) {
                @flock($lockFh, LOCK_UN);
                @fclose($lockFh);
            });
        }
    }

    public function registerFatalHandler(): void
    {
        register_shutdown_function(function (): void {
            $err = error_get_last();
            if (!is_array($this->active) || !is_array($err) || !in_array((int) ($err['type'] ?? 0), [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return;
            }
            BroadcastQueue::rx_broadcast_info_update($this->active['info'], $this->active['token'], static function (array &$c) {
                if (($c['status'] ?? '') !== 'running') {
                    return false;
                }
                BroadcastQueue::rx_broadcast_apply_pause($c, ['class' => 'system_error', 'temporary' => true, 'code' => 0, 'retry_after' => 0, 'detail' => 'worker_fatal', 'manual' => false]);
            });
            @error_log('[sendmessage.php] broadcast paused after worker fatal error: ' . basename((string) ($err['file'] ?? '')) . ':' . (int) ($err['line'] ?? 0));
        });
    }

    public function run(): void
    {
        try {
            $this->loadButtonTexts();
            $this->readInfo();
            $this->passGate();
            $this->workersTotal = max(1, min(16, (int) ($_GET['workers'] ?? $_SERVER['BROADCAST_WORKERS'] ?? 1)));
            $this->announceStart();
            $this->resetStaleSideFiles();
            $this->migrateLegacyQueue();
            $this->initTotals();
            $batch = $this->claimBatch();
            $this->refreshTotals();
            $this->logBatch($batch);
            if (count($batch) === 0) {
                $this->finishEmptyBatch();
            }
            $this->sendBatch($batch);
            $this->closeBatch($batch);
        } catch (Throwable $e) {
            $this->hadException = true;
            error_log('[sendmessage.php] ' . $e->getMessage());
            $this->log('EXCEPTION — ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $exceptionClass = BroadcastQueue::rx_broadcast_classify_exception($e);
            BroadcastQueue::rx_broadcast_info_update($this->baseDir . '/info', $this->bcToken, static function (array &$c) use ($exceptionClass) {
                $st = (string) ($c['status'] ?? '');
                if ($st !== '' && $st !== 'queued' && $st !== 'running') {
                    return false;
                }
                BroadcastQueue::rx_broadcast_apply_pause($c, $exceptionClass);
            });
        } finally {
            @unlink($this->lockFile);
            if (!$this->hadException) {
                if ($this->continuationDelay > 0) {
                    TaskState::rx_cron_task_partial($this->continuationReason, $this->continuationDelay);
                } else {
                    TaskState::rx_cron_task_complete('broadcast_batch_finished');
                }
            }
        }
    }

    private function loadButtonTexts(): void
    {
        $rows = Db::select("textbot", "*", null, null, "fetchAll");
        $texts = [
            'text_usertest'   => '',
            'text_support'    => '',
            'text_help'       => '',
            'text_sell'       => '',
            'text_affiliates' => '',
            'text_Add_Balance' => '',
        ];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $idText = isset($row['id_text']) ? (string) $row['id_text'] : '';
                if ($idText !== '' && (array_key_exists($idText, $texts) || trim((string) ($row['text'] ?? '')) !== '')) {
                    $texts[$idText] = (string) ($row['text'] ?? '');
                }
            }
        }
        $this->texts = $texts;
        $GLOBALS['datatextbot'] = $texts;
    }

    private function readInfo(): void
    {
        if (!is_file($this->infoFile)) {
            $this->abandon('broadcast_no_queue');
        }
        $content = @file_get_contents($this->infoFile);
        if ($content === false) {
            $this->log('ERROR — خواندن info ناموفق');
            $this->abandon('broadcast_info_unreadable');
        }
        $info = json_decode($content, true);
        if (!is_array($info)) {
            $this->log('ERROR — info نامعتبر (JSON خراب)');
            $this->abandon('broadcast_info_invalid');
        }
        $this->info = $info;
    }

    private function abandon(string $reason): void
    {
        $this->complete($reason);
        @unlink($this->lockFile);
        exit;
    }

    private function passGate(): void
    {
        $this->bcToken = BroadcastQueue::rx_broadcast_token($this->info);
        $gate = 'run';
        $gateInfo = BroadcastQueue::rx_broadcast_info_update($this->infoFile, $this->bcToken, static function (array &$c) use (&$gate) {
            $st = (string) ($c['status'] ?? '');
            if ($st === 'cancelled' || $st === 'finished') {
                $gate = 'stop';
                return false;
            }
            if (!BroadcastQueue::rx_broadcast_is_paused($st)) {
                return false;
            }
            $until = (int) ($c['pause_until'] ?? 0);
            if (!empty($c['manual_resume']) || $until <= 0 || $until > time()) {
                $gate = 'wait';
                return false;
            }
            BroadcastQueue::rx_broadcast_resume_now($c, false);
        });
        if ($gate !== 'run' || !is_array($gateInfo)) {
            $this->deferOrComplete($gate, $gateInfo);
        }
        $this->info = $gateInfo;
        $this->active = ['info' => $this->infoFile, 'token' => $this->bcToken];
        $this->inflightFile = $this->usersFileTxt . '.w' . $this->workerId . '.' . $this->bcToken . '.inflight';
        $this->sentFile = $this->usersFileTxt . '.w' . $this->workerId . '.' . $this->bcToken . '.sent';
        $this->claimLockFile = $this->usersFileTxt . '.' . $this->bcToken . '.claim';
        $this->reportLockFile = $this->usersFileTxt . '.' . $this->bcToken . '.report';
    }

    private function deferOrComplete(string $gate, $gateInfo): void
    {
        $defer = 0;
        $reason = 'broadcast_gate_' . $gate;
        if ($gate === 'wait') {
            $pauseInfo = is_array($gateInfo) ? $gateInfo : $this->info;
            $pauseUntil = (int) ($pauseInfo['pause_until'] ?? 0);
            $manual = !empty($pauseInfo['manual_resume']);
            if ($manual || $pauseUntil <= 0) {
                $defer = 60;
            } else {
                $defer = max(30, $pauseUntil - time());
            }
        } elseif ($gate === 'run' && is_file($this->infoFile)) {
            $current = json_decode((string) @file_get_contents($this->infoFile), true);
            if (is_array($current) && BroadcastQueue::rx_broadcast_token($current) === $this->bcToken) {
                $defer = 60;
                $reason = 'broadcast_gate_recheck';
            }
        }
        if ($defer > 0) {
            $queueUncertain = false;
            $remaining = $this->gateQueueRemaining($queueUncertain);
            if ($remaining > 0 || $queueUncertain) {
                $this->extendOccurrence();
                TaskState::rx_cron_task_partial($reason, $defer);
                @unlink($this->lockFile);
                exit;
            }
        }
        $this->complete($reason);
        @unlink($this->lockFile);
        exit;
    }

    private function gateQueueRemaining(bool &$uncertain): int
    {
        $remain = is_file($this->usersFileTxt) ? BroadcastFiles::countLines($this->usersFileTxt) : 0;
        if (!is_file($this->usersFileTxt) && is_file($this->usersFileJson)) {
            $raw = @file_get_contents($this->usersFileJson);
            if ($raw === false) {
                $uncertain = true;
            } else {
                $queue = json_decode($raw);
                if (!is_array($queue)) {
                    $uncertain = true;
                } else {
                    $remain += count($queue);
                }
            }
        }
        foreach (glob($this->usersFileTxt . '.w*.' . $this->bcToken . '.inflight') ?: [] as $inflight) {
            $remain += BroadcastFiles::countLines($inflight);
        }
        return $remain;
    }

    private function extendOccurrence(): void
    {
        $current = $GLOBALS['rxCronTaskCurrent'] ?? null;
        if (is_array($current) && !empty($current['key']) && !empty($current['token'])
            && method_exists(TaskQueue::class, 'rx_cron_task_extend_occurrence') && $this->pdo instanceof PDO) {
            try {
                TaskQueue::rx_cron_task_extend_occurrence($this->pdo, (string) $current['key'], (string) $current['token'], time());
            } catch (Throwable $e) {
            }
        }
    }

    private function infoUpdate(callable $mutator)
    {
        return BroadcastQueue::rx_broadcast_info_update($this->infoFile, $this->bcToken, $mutator);
    }

    private function state(): string
    {
        $c = is_file($this->infoFile) ? json_decode((string) @file_get_contents($this->infoFile), true) : null;
        if (!is_array($c) || BroadcastQueue::rx_broadcast_token($c) !== $this->bcToken || ($c['status'] ?? '') === 'cancelled') {
            return 'cancelled';
        }
        return BroadcastQueue::rx_broadcast_is_paused($c['status'] ?? '') ? 'paused' : 'running';
    }

    private function allInflight(): int
    {
        $t = 0;
        foreach (glob($this->usersFileTxt . '.w*.' . $this->bcToken . '.inflight') ?: [] as $inf) {
            $t += BroadcastFiles::countLines($inf);
        }
        return $t;
    }

    private function purgeSideFiles(): void
    {
        @unlink($this->infoFile);
        @unlink($this->usersFileTxt);
        @unlink($this->usersFileJson);
        @unlink($this->infoLockFile);
        foreach (glob($this->usersFileTxt . '.*') ?: [] as $f) {
            if ($f === $this->reportLockFile) {
                continue;
            }
            @unlink($f);
        }
    }

    private function sendFinalReport(array $stats): void
    {
        if (isset($this->info['id_admin'], $this->info['id_message'])) {
            @deletemessage($this->info['id_admin'], $this->info['id_message']);
            sendmessage($this->info['id_admin'], BroadcastFiles::buildFinalReport($stats), null, 'HTML');
        }
    }

    private function tryFinish(): bool
    {
        $clf = @fopen($this->claimLockFile, 'c');
        if ($clf) {
            @flock($clf, LOCK_EX);
        }
        if ($this->state() !== 'running') {
            if ($clf) {
                @flock($clf, LOCK_UN);
                @fclose($clf);
            }
            return false;
        }
        $remain = (is_file($this->usersFileTxt) ? BroadcastFiles::countLines($this->usersFileTxt) : 0) + $this->allInflight();
        $done = ($remain === 0);
        if ($done) {
            $rl = @fopen($this->reportLockFile, 'x');
            if ($rl !== false) {
                @fclose($rl);
                $st = json_decode((string) @file_get_contents($this->infoFile), true);
                $st = (is_array($st) && isset($st['stats']) && is_array($st['stats'])) ? $st['stats'] : [];
                $this->log('FINISHED — کل عملیات تمام شد | total=' . (int) ($st['total'] ?? 0) . ' | موفق=' . (int) ($st['success'] ?? 0));
                $this->sendFinalReport($st);
                $this->purgeSideFiles();
            } else {
                $this->log('DONE — این worker تمام شد؛ گزارش نهایی را worker دیگری می‌فرستد');
                $this->purgeSideFiles();
            }
        }
        if ($clf) {
            @flock($clf, LOCK_UN);
            @fclose($clf);
        }
        return $done;
    }

    private function announceStart(): void
    {
        $linesNow = is_file($this->usersFileTxt) ? BroadcastFiles::countLines($this->usersFileTxt) : -1;
        $statsTotal = (isset($this->info['stats']['total'])) ? (int) $this->info['stats']['total'] : 0;
        $this->log('START — type=' . ($this->info['type'] ?? '?')
            . ' | admin=' . ($this->info['id_admin'] ?? '?')
            . ' | خطوط users.txt الان=' . $linesNow
            . ' | total ذخیره‌شده در info=' . $statsTotal);
    }

    private function resetStaleSideFiles(): void
    {
        $statsTotal = (isset($this->info['stats']['total'])) ? (int) $this->info['stats']['total'] : 0;
        if ($statsTotal !== 0) {
            return;
        }
        foreach (glob($this->usersFileTxt . '.*') ?: [] as $f) {
            if (strpos($f, '.' . $this->bcToken . '.') !== false) {
                continue;
            }
            if (preg_match('/\.(inflight|sent|claim|report)$/', $f)) {
                @unlink($f);
            }
        }
        $this->log('FRESH — شروع تمیز: فایل‌های جانبیِ ارسال‌های قبلی پاک شد');
    }

    private function migrateLegacyQueue(): void
    {
        if (!is_file($this->usersFileTxt) && is_file($this->usersFileJson)) {
            $migrated = BroadcastFiles::migrateLegacyJsonToTxt($this->usersFileJson, $this->usersFileTxt);
            if ($migrated >= 0) {
                @unlink($this->usersFileJson);
            }
        }
    }

    private function initTotals(): void
    {
        $totals = isset($this->info['stats']) && is_array($this->info['stats']) ? $this->info['stats'] : [];
        $totals += [
            'total'         => 0,
            'success'       => 0,
            'blocked'       => 0,
            'deleted'       => 0,
            'failed'        => 0,
            'chat_not_found' => 0,
            'started_at'    => time(),
        ];
        $this->totals = $totals;
    }

    private function lockedClaim(callable $fn)
    {
        $clf = @fopen($this->claimLockFile, 'c');
        if ($clf) {
            @flock($clf, LOCK_EX);
        }
        try {
            return $fn();
        } finally {
            if ($clf) {
                @flock($clf, LOCK_UN);
                @fclose($clf);
            }
        }
    }

    private function claimBatch(): array
    {
        $batch = [];
        if (is_file($this->usersFileTxt) || !is_file($this->usersFileJson)) {
            $recovered = $this->lockedClaim(function () {
                return BroadcastFiles::recoverBroadcastInflight($this->baseDir, $this->usersFileTxt, $this->bcToken, $this->workerId, BroadcastFiles::loadBroadcastSentSet($this->usersFileTxt, $this->bcToken));
            });
            if ($recovered > 0) {
                $this->log('RECOVER — ' . $recovered . ' users returned from inflight to queue');
            }
        }
        $this->usingTxtMode = is_file($this->usersFileTxt);
        $this->usingJsonMode = !$this->usingTxtMode && is_file($this->usersFileJson);
        $this->batchSource = 'none';

        if (is_file($this->inflightFile) && BroadcastFiles::countLines($this->inflightFile) > 0) {
            $batch = BroadcastFiles::loadLinesFromTxt($this->inflightFile, self::BATCH_SIZE);
            $this->batchSource = 'inflight-recover';
        } elseif ($this->usingTxtMode) {
            $batch = $this->lockedClaim(function () {
                $this->infoUpdate(function (array &$c) {
                    if (empty($c['status']) || $c['status'] === 'queued') {
                        $c['status'] = 'running';
                    }
                    if (!isset($c['stats']) || !is_array($c['stats'])) {
                        $c['stats'] = [];
                    }
                    if ((int) ($c['stats']['total'] ?? 0) > 0) {
                        return;
                    }
                    $c['stats']['total'] = (is_file($this->usersFileTxt) ? BroadcastFiles::countLines($this->usersFileTxt) : 0) + $this->allInflight();
                    if (empty($c['stats']['started_at'])) {
                        $c['stats']['started_at'] = time();
                    }
                });
                return BroadcastFiles::claimBatchToInflight($this->usersFileTxt, $this->inflightFile, self::BATCH_SIZE);
            });
            $this->batchSource = 'txt-claim';
        } elseif ($this->usingJsonMode) {
            $batch = $this->claimLegacyJsonBatch();
            $this->batchSource = 'json-legacy';
        } else {
            $this->log('SKIP — نه users.txt و نه users.json موجود است (صف خالی)');
            $this->abandon('broadcast_queue_empty');
        }
        return $batch;
    }

    private string $batchSource = 'none';

    private function claimLegacyJsonBatch(): array
    {
        $batch = [];
        $jsonRaw = @file_get_contents($this->usersFileJson);
        $jsonArr = $jsonRaw !== false ? json_decode($jsonRaw) : null;
        if (is_array($jsonArr)) {
            $remaining = [];
            $i = 0;
            foreach ($jsonArr as $row) {
                if ($i++ < self::BATCH_SIZE) {
                    if (isset($row->id)) {
                        $batch[] = (string) $row->id;
                    } elseif (is_array($row) && isset($row['id'])) {
                        $batch[] = (string) $row['id'];
                    }
                } else {
                    $remaining[] = $row;
                }
            }
            $tmp = $this->usersFileJson . '.tmp';
            file_put_contents($tmp, json_encode($remaining, JSON_UNESCAPED_UNICODE));
            @rename($tmp, $this->usersFileJson);
        }
        return $batch;
    }

    private function refreshTotals(): void
    {
        $fresh = json_decode((string) @file_get_contents($this->infoFile), true);
        if (is_array($fresh) && isset($fresh['stats']) && is_array($fresh['stats'])) {
            $this->totals = $fresh['stats'] + $this->totals;
            $this->totals['total'] = (int) ($fresh['stats']['total'] ?? $this->totals['total']);
        }
    }

    private function logBatch(array $batch): void
    {
        $this->log('BATCH — mode=' . ($this->usingTxtMode ? 'txt' : ($this->usingJsonMode ? 'json' : 'none'))
            . ' | w=' . $this->workerId . '/' . $this->workersTotal
            . ' | source=' . $this->batchSource
            . ' | اندازه بچ=' . self::BATCH_SIZE
            . ' | خوانده‌شده در این اجرا=' . count($batch)
            . ' | inflight=' . (is_file($this->inflightFile) ? BroadcastFiles::countLines($this->inflightFile) : 0)
            . ' | باقی‌مانده users.txt=' . (is_file($this->usersFileTxt) ? BroadcastFiles::countLines($this->usersFileTxt) : 0)
            . ' | total=' . (int) $this->totals['total']);
    }

    private function finishEmptyBatch(): void
    {
        if ($this->usingTxtMode && is_file($this->usersFileTxt) && BroadcastFiles::countLines($this->usersFileTxt) > 0) {
            $this->infoUpdate(static function (array &$c) {
                if (($c['status'] ?? '') !== 'running') {
                    return false;
                }
                BroadcastQueue::rx_broadcast_apply_pause($c, ['class' => 'system_error', 'temporary' => true, 'code' => 0, 'retry_after' => 0, 'detail' => 'queue_unavailable', 'manual' => false]);
            });
            @error_log('[sendmessage.php] broadcast paused: queue could not be claimed from ' . $this->usersFileTxt);
            @unlink($this->lockFile);
            exit;
        }
        $this->tryFinish();
        @unlink($this->inflightFile);
        @unlink($this->sentFile);
        $this->complete('broadcast_batch_empty');
        @unlink($this->lockFile);
        exit;
    }

    private function keyboards(): array
    {
        $t = $this->texts;
        return [
            'none'         => null,
            'buy'          => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_buy_service', ['text' => $t['text_sell'],     'callback_data' => 'buy'])]]]),
            'start'        => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_start_bot',   ['text' => "شروع",                  'callback_data' => 'start'])]]]),
            'usertestbtn'  => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_usertest',    ['text' => $t['text_usertest'], 'callback_data' => 'usertestbtn'])]]]),
            'helpbtn'      => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_help',        ['text' => $t['text_help'],     'callback_data' => 'helpbtn'])]]]),
            'affiliatesbtn' => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_affiliates',  ['text' => $t['text_affiliates'], 'callback_data' => 'affiliatesbtn'])]]]),
            'addbalance'   => json_encode(['inline_keyboard' => [[rx_cron_btn('cron_addbalance',  ['text' => $t['text_Add_Balance'], 'callback_data' => 'Add_Balance'])]]]),
        ];
    }

    private function applyStatsDelta(): void
    {
        $d = [
            'success'        => $this->bSuccess      - $this->applied['success'],
            'blocked'        => $this->bBlocked      - $this->applied['blocked'],
            'deleted'        => $this->bDeleted      - $this->applied['deleted'],
            'failed'         => $this->bFailed       - $this->applied['failed'],
            'chat_not_found' => $this->bChatNotFound - $this->applied['chat_not_found'],
        ];
        if (!array_filter($d)) {
            return;
        }
        $this->infoUpdate(function (array &$c) use ($d) {
            $c['temp_streak'] = 0;
            if (!isset($c['stats']) || !is_array($c['stats'])) {
                $c['stats'] = [];
            }
            foreach ($d as $k => $v) {
                $c['stats'][$k] = (int) ($c['stats'][$k] ?? 0) + $v;
            }
        });
        $this->applied = ['success' => $this->bSuccess, 'blocked' => $this->bBlocked, 'deleted' => $this->bDeleted, 'failed' => $this->bFailed, 'chat_not_found' => $this->bChatNotFound];
    }

    private function sendBatch(array $batch): void
    {
        $info = $this->info;
        $keyboards = $this->keyboards();
        $this->batchStart = microtime(true);
        $this->processed = 0;
        $this->bSuccess = $this->bBlocked = $this->bDeleted = $this->bFailed = $this->bChatNotFound = 0;
        $this->applied = ['success' => 0, 'blocked' => 0, 'deleted' => 0, 'failed' => 0, 'chat_not_found' => 0];

        $deleteStmt = $this->pdo->prepare("DELETE FROM user WHERE id = :id");
        $orphanCheckStmt = $this->pdo->prepare(
            "SELECT (SELECT COUNT(*) FROM invoice WHERE id_user = :id) AS invoice_count, Balance
             FROM user WHERE id = :id2 LIMIT 1"
        );

        $sentSet = BroadcastFiles::loadBroadcastSentSet($this->usersFileTxt, $this->bcToken) + BroadcastFiles::loadSentSet($this->sentFile);
        $rxBcLog = function (string $line): void {
            $this->log($line);
        };

        $this->stop = 'running';
        $idx = 0;
        foreach ($batch as $userId) {
            TaskState::rx_cron_task_heartbeat();
            if ((microtime(true) - $this->batchStart) >= self::SOFT_TIME_LIMIT) {
                if ($this->usingJsonMode && !empty(array_slice($batch, $idx))) {
                    BroadcastFiles::prependEntriesToJson($this->usersFileJson, array_slice($batch, $idx));
                }
                break;
            }
            $this->stop = $this->state();
            if ($this->stop !== 'running') {
                if ($this->usingJsonMode && $this->stop === 'paused') {
                    BroadcastFiles::prependEntriesToJson($this->usersFileJson, array_slice($batch, $idx));
                }
                break;
            }
            $idx++;
            $userId = trim((string) $userId);
            if ($userId === '' || !is_numeric($userId)) {
                continue;
            }

            if (isset($sentSet[$userId])) {
                if (!$this->usingJsonMode) {
                    BroadcastFiles::syncInflight($this->inflightFile, array_slice($batch, $idx));
                }
                continue;
            }

            $this->processed++;

            $resp = null;
            $attempted = true;
            if ($info['type'] === 'unpinmessage') {
                $unpinResult = BroadcastFiles::runRateLimitedUnpin($userId, $this->baseDir, $this->batchStart, self::SOFT_TIME_LIMIT, $rxBcLog);
                if (!empty($unpinResult['deferred'])) {
                    $idx--;
                    $this->processed--;
                    if ($this->usingJsonMode) {
                        BroadcastFiles::prependEntriesToJson($this->usersFileJson, array_slice($batch, $idx));
                    }
                    break;
                }
                $resp = $unpinResult['response'];
            } elseif ($info['type'] === 'sendmessage' || $info['type'] === 'xdaynotmessage') {
                $kb = $keyboards[$info['btnmessage'] ?? 'none'] ?? null;
                $resp = sendmessage($userId, $info['message'], $kb, 'HTML');
            } elseif ($info['type'] === 'forwardmessage') {
                $resp = forwardMessage($info['id_admin'], $info['message'], $userId);
            } else {
                $attempted = false;
            }

            if ($attempted) {
                $cls = BroadcastQueue::rx_broadcast_classify($resp);
                if (!empty($cls['temporary'])) {
                    $idx--;
                    $this->processed--;
                    if ($this->usingJsonMode) {
                        BroadcastFiles::prependEntriesToJson($this->usersFileJson, array_slice($batch, $idx));
                    }
                    $this->infoUpdate(static function (array &$c) use ($cls) {
                        BroadcastQueue::rx_broadcast_apply_pause($c, $cls);
                    });
                    $this->stop = 'paused';
                    break;
                }
            }

            @file_put_contents($this->sentFile, $userId . "\n", FILE_APPEND | LOCK_EX);
            $sentSet[$userId] = true;

            if ($attempted) {
                BroadcastFiles::handleResponse($resp, $userId, $info, $orphanCheckStmt, $deleteStmt,
                    $this->bSuccess, $this->bBlocked, $this->bDeleted, $this->bFailed, $this->bChatNotFound, $rxBcLog);
            }

            if (!$this->usingJsonMode && ($this->processed % self::CHECKPOINT_EVERY) === 0) {
                $this->applyStatsDelta();
                BroadcastFiles::syncInflight($this->inflightFile, array_slice($batch, $idx));
            }
        }
        $this->idx = $idx;
    }

    private int $idx = 0;

    private function discardAndExit(): void
    {
        @unlink($this->inflightFile);
        @unlink($this->inflightFile . '.tmp');
        @unlink($this->sentFile);
        @unlink($this->lockFile);
        exit;
    }

    private function closeBatch(array $batch): void
    {
        if ($this->stop === 'cancelled') {
            $this->discardAndExit();
        }

        if (!$this->usingJsonMode) {
            BroadcastFiles::syncInflight($this->inflightFile, array_slice($batch, $this->idx));
        }

        $this->applyStatsDelta();
        if ($this->stop === 'running') {
            $this->stop = $this->state();
        }
        if ($this->stop === 'cancelled') {
            $this->discardAndExit();
        }
        $infoNow = json_decode((string) @file_get_contents($this->infoFile), true);
        $agg = (is_array($infoNow) && isset($infoNow['stats']) && is_array($infoNow['stats'])) ? $infoNow['stats'] : $this->totals;

        $batchExecutionTime = microtime(true) - $this->batchStart;
        $messagesPerSecond = $this->processed > 0 ? $this->processed / max(0.001, $batchExecutionTime) : 0;

        $inflightRemain = $this->allInflight();
        $usersRemain = $this->usingTxtMode
            ? (is_file($this->usersFileTxt) ? BroadcastFiles::countLines($this->usersFileTxt) : 0)
            : (is_file($this->usersFileJson) ? (count((array) json_decode((string) @file_get_contents($this->usersFileJson)))) : 0);
        $countRemain = $usersRemain + $inflightRemain;
        $totalSent = (int) ($agg['success'] ?? 0) + (int) ($agg['blocked'] ?? 0)
                   + (int) ($agg['failed'] ?? 0) + (int) ($agg['chat_not_found'] ?? 0);

        $this->log('RUN-DONE — w=' . $this->workerId . ' | پردازش این اجرا=' . $this->processed
            . ' (موفق=' . $this->bSuccess . ' بلاک=' . $this->bBlocked . ' حذف=' . $this->bDeleted
            . ' خطا=' . $this->bFailed . ' بدون‌چت=' . $this->bChatNotFound . ')'
            . ' | باقی‌مانده=' . $countRemain . ' (inflight=' . $inflightRemain . ')'
            . ' | مجموع ارسال‌شده تاکنون=' . $totalSent . ' از ' . (int) ($agg['total'] ?? 0));

        if ($countRemain > 0 && $this->stop === 'running') {
            $this->continuationDelay = $this->processed > 0 ? 30 : 300;
            $this->continuationReason = $this->processed > 0 ? 'broadcast_continuing' : 'broadcast_stalled';
        } elseif ($countRemain > 0 && $this->stop === 'paused') {
            $pauseUntil = is_array($infoNow) ? (int) ($infoNow['pause_until'] ?? 0) : 0;
            $this->continuationDelay = $pauseUntil > time()
                ? min(1800, max(30, $pauseUntil - time() + 5))
                : 120;
            $this->continuationReason = 'broadcast_paused';
        }
        if ($this->continuationDelay > 0) {
            $this->extendOccurrence();
        }

        if ($countRemain === 0 && $this->stop === 'running') {
            $this->finishRun($agg);
        }

        $this->reportProgress($agg, $infoNow, $countRemain, $totalSent, $batchExecutionTime, $messagesPerSecond);
    }

    private function finishRun(array $agg): void
    {
        if ($this->usingJsonMode) {
            $this->log('FINISHED — کل عملیات تمام شد | total=' . (int) ($agg['total'] ?? 0));
            $this->sendFinalReport($agg);
            @unlink($this->infoFile);
            @unlink($this->usersFileTxt);
            @unlink($this->usersFileJson);
            @unlink($this->inflightFile);
            @unlink($this->sentFile);
        } else {
            $this->tryFinish();
        }
        $this->complete('broadcast_finished');
        @unlink($this->lockFile);
        exit;
    }

    private function reportProgress(array $agg, $infoNow, int $countRemain, int $totalSent, float $batchExecutionTime, $messagesPerSecond): void
    {
        $info = $this->info;
        $text = "✏️ عملیات ارسال پیام درحال انجام...\n\n";
        $text .= "📊 باقی‌مانده: " . number_format($countRemain) . " نفر\n";
        $text .= "🚀 ارسال‌شده: " . number_format($totalSent) . " از " . number_format((int) ($agg['total'] ?? 0)) . "\n";
        $text .= "✅ موفق: " . number_format((int) ($agg['success'] ?? 0));
        if ((int) ($agg['blocked'] ?? 0) > 0) {
            $text .= " | 🚫 بلاک: " . number_format((int) $agg['blocked']);
        }
        if ((int) ($agg['chat_not_found'] ?? 0) > 0) {
            $text .= " | 📵 بدون چت: " . number_format((int) $agg['chat_not_found']);
        }
        if ((int) ($agg['deleted'] ?? 0) > 0) {
            $text .= " | 🗑 حذف‌شده: " . number_format((int) $agg['deleted']);
        }
        if ((int) ($agg['failed'] ?? 0) > 0) {
            $text .= " | ❌ خطا: " . number_format((int) $agg['failed']);
        }
        $text .= "\n\n⏱ این بچ: " . round($batchExecutionTime, 1) . "s | 🔥 سرعت: " . round($messagesPerSecond, 1) . " پیام/ثانیه";

        if ($this->stop === 'paused' && is_array($infoNow)) {
            $text = "⏸️ عملیات ارسال پیام موقتاً متوقف شده است\n\n"
                . substr($text, (int) strpos($text, "📊"))
                . "\n" . BroadcastQueue::rx_broadcast_pause_text($infoNow);
            $pauseKb = json_encode(['inline_keyboard' => [
                [BroadcastQueue::rx_broadcast_resume_button($this->bcToken)],
                [['text' => "🔄 بروزرسانی", 'callback_data' => 'broadcast_status_refresh']],
                [['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage']],
            ]]);
            if (isset($info['id_admin'], $info['id_message'])) {
                Editmessagetext($info['id_admin'], $info['id_message'], $text, $pauseKb);
            }
        } elseif ($this->workerId === 0 && isset($info['id_admin'], $info['id_message'])) {
            $cancelKb = json_encode(['inline_keyboard' => [
                [['text' => "🔄 بروزرسانی", 'callback_data' => 'broadcast_status_refresh']],
                [['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage']],
            ]], JSON_UNESCAPED_UNICODE);
            Editmessagetext($info['id_admin'], $info['id_message'], $text, $cancelKb);
        }
    }
}
