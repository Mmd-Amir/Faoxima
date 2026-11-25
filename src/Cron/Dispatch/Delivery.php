<?php

namespace Faoxima\Cron\Dispatch;

use Faoxima\Cron\Runtime\TaskQueue;
use Faoxima\Cron\Runtime\TaskState;
use PDO;
use Throwable;

final class Delivery
{
    public int $successful = 0;
    public int $cliDispatched = 0;

    private PDO $pdo;
    private string $authToken;
    private bool $isCli;
    private bool $sharedProfile;
    private $buildUrl;
    private int $lease;
    private int $maxAttempts;
    private int $maxConcurrency;
    private int $batchSize;
    private $releaseScheduler;

    public function __construct(PDO $pdo, string $authToken, bool $isCli, bool $sharedProfile, callable $buildUrl, int $lease, int $maxAttempts, int $maxConcurrency, ?callable $releaseScheduler = null)
    {
        $this->releaseScheduler = $releaseScheduler;
        $this->pdo = $pdo;
        $this->authToken = $authToken;
        $this->isCli = $isCli;
        $this->sharedProfile = $sharedProfile;
        $this->buildUrl = $buildUrl;
        $this->lease = $lease;
        $this->maxAttempts = $maxAttempts;
        $this->maxConcurrency = $maxConcurrency;
        $this->batchSize = $sharedProfile ? 2 : 8;
    }

    private function http(array $tasks, bool $useLoopback): array
    {
        return Transport::http($tasks, $useLoopback, $this->authToken);
    }

    public function dispatchIndexFallback(Planner $planner): void
    {
        $definedScripts = $planner->definedScripts();
        if (!in_array('index.php', $definedScripts, true) && !$planner->scriptExists('index.php')) {
            $planner->skippedMissing++;
            $planner->reportMissingScript('__index__', 'index.php');
            return;
        }
        if (in_array('index.php', $definedScripts, true)) {
            return;
        }
        $dispatched = false;

        if ($this->isCli) {
            $dispatched = Transport::cli('index.php', 0, 1, true);
            if ($dispatched) {
                $this->cliDispatched++;
                $this->successful++;
            }
        }

        if (!$dispatched) {
            $task = [[
                'key' => '__index__',
                'script' => 'index.php',
                'worker' => 0,
                'workers' => 1,
                'url' => ($this->buildUrl)('index.php'),
            ]];

            $result = $this->http($task, false);
            if (!empty($result['failed'])) {
                $result = $this->http($result['failed'], true);
            }

            if (!empty($result['success'])) {
                $this->successful += count($result['success']);
            }
        }
    }

    private function taskStarted(string $taskKey, string $token): bool
    {
        if ($taskKey === '' || $token === '') {
            return false;
        }
        try {
            TaskState::rx_cron_task_scan_markers($this->pdo, $this->lease, time());
        } catch (Throwable $e) {
        }
        try {
            return TaskQueue::rx_cron_task_has_started($this->pdo, $taskKey, $token);
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function httpOutcome(array $result, callable $hasStarted): ?string
    {
        if (!empty($result['success']) || $hasStarted()) {
            return 'accepted';
        }
        return !empty($result['uncertain']) ? 'uncertain' : null;
    }

    public function dispatchTask(array $task): string
    {
        $jobKey  = (string) ($task['job_key'] ?? $task['key'] ?? '');
        $script  = (string) ($task['script'] ?? '');
        $worker  = (int) ($task['worker'] ?? 0);
        $workers = max(1, (int) ($task['workers'] ?? 1));
        $taskKey = (string) ($task['task_key'] ?? '');
        $token   = (string) ($task['token'] ?? '');
        $occurrence = (int) ($task['occurrence'] ?? ($task['scheduled_for'] ?? 0));
        $isStarted = function () use ($taskKey, $token): bool {
            return $this->taskStarted($taskKey, $token);
        };

        if ($this->isCli) {
            $background = ($jobKey === 'backupbot') ? true : !$this->sharedProfile;
            if (Transport::cli($script, $worker, $workers, $background, $taskKey, $token, $occurrence)) {
                return 'accepted';
            }
            if ($isStarted()) {
                return 'accepted';
            }
        }

        if (function_exists('curl_multi_init')) {
            $base = ($this->buildUrl)($script);
            $sep = (strpos($base, '?') === false) ? '?' : '&';
            $single = [[
                'key' => $jobKey,
                'script' => $script,
                'worker' => $worker,
                'workers' => $workers,
                'url' => $workers > 1 ? $base . $sep . 'worker=' . $worker . '&workers=' . $workers : $base,
                'task_key' => $taskKey,
                'token' => $token,
                'occurrence' => $occurrence,
            ]];
            foreach ([false, true] as $loopback) {
                $outcome = self::httpOutcome($this->http($single, $loopback), $isStarted);
                if ($outcome !== null) {
                    return $outcome;
                }
            }
        }

        if (!$this->isCli && Transport::cli($script, $worker, $workers, true, $taskKey, $token, $occurrence)) {
            return 'accepted';
        }
        if ($isStarted()) {
            return 'accepted';
        }

        return 'failed';
    }

    public function deliver(Planner $planner, bool $taskStateEnabled, int $now): void
    {
        if ($taskStateEnabled) {
            $this->runQueue($planner);
        } elseif ($this->isCli) {
            $this->deliverViaCli($planner, $now);
        } elseif (!empty($planner->dueTasks)) {
            $this->deliverViaHttp($planner, $now);
        }
    }

    private function runQueue(Planner $planner): void
    {
        $deadline = microtime(true) + ($this->isCli ? ($this->sharedProfile ? 240 : 45) : ($this->sharedProfile ? 8 : 15));
        $validTasks = $planner->validTasks;
        $validate = static function (array $row) use ($validTasks): bool {
            $taskKey = (string) ($row['task_key'] ?? '');
            if ($taskKey === '' || !isset($validTasks[$taskKey])) {
                return false;
            }
            $valid = $validTasks[$taskKey];
            return (int) ($valid['worker'] ?? -1) === (int) ($row['worker'] ?? -2)
                && (int) ($valid['workers'] ?? -1) === (int) ($row['workers'] ?? -2);
        };
        $stats = ['dispatched' => 0];
        $sequential = $this->isCli && $this->sharedProfile;
        $deferredJobs = [];
        foreach ($planner->availability as $jobKey => $state) {
            if ($state === false) {
                $deferredJobs[] = (string) $jobKey;
            }
        }
        try {
            $stats = TaskQueue::rx_cron_task_run_pending($this->pdo, [
                'concurrency' => $this->maxConcurrency,
                'max_attempts' => $this->maxAttempts,
                'lease_seconds' => $this->lease,
                'deadline' => $deadline,
                'claim_batch' => $sequential ? 1 : 0,
                'sequential' => $sequential,
                'refill_seconds' => $sequential ? 45 : 0,
                'after_claim' => $this->releaseScheduler,
                'deferred_jobs' => $deferredJobs,
            ], function (array $task): string {
                return $this->dispatchTask($task);
            }, $validate);
        } catch (Throwable $e) {
            error_log('[cron] pending task dispatch failed: ' . get_class($e));
            $stats = ['dispatched' => 0];
        }
        $this->successful += (int) ($stats['dispatched'] ?? 0);
    }

    private function deliverViaCli(Planner $planner, int $now): void
    {
        $successfulJobs = [];
        foreach ($planner->dueTasks as $task) {
            $jobKey = (string) $task['key'];
            $background = ($jobKey === 'backupbot') ? true : !$this->sharedProfile;
            if (Transport::cli($task['script'], (int) $task['worker'], (int) $task['workers'], $background, '', '', (int) ($task['occurrence'] ?? 0))) {
                $this->cliDispatched++;
                $this->successful++;
                $successfulJobs[$jobKey] = true;
                continue;
            }
            $result = $this->http([$task], false);
            if (!empty($result['failed'])) {
                $result = $this->http($result['failed'], true);
            }
            if (!empty($result['success'])) {
                $this->successful += count($result['success']);
                $successfulJobs[$jobKey] = true;
            }
            if (!empty($result['uncertain'])) {
                $successfulJobs[$jobKey] = true;
            }
        }
        $this->markRuns($planner, array_keys($successfulJobs), $now);
    }

    private function deliverViaHttp(Planner $planner, int $now): void
    {
        $primarySuccess = [];
        $primaryFailed = [];
        $deliveredUncertain = [];

        foreach (array_chunk($planner->dueTasks, $this->batchSize) as $batch) {
            $result = $this->http($batch, false);
            $primarySuccess = array_merge($primarySuccess, $result['success']);
            $primaryFailed = array_merge($primaryFailed, $result['failed']);
            $deliveredUncertain = array_merge($deliveredUncertain, $result['uncertain']);
        }

        $loopbackSuccess = [];
        $loopbackFailed = [];

        if (!empty($primaryFailed)) {
            foreach (array_chunk($primaryFailed, $this->batchSize) as $batch) {
                $result = $this->http($batch, true);
                $loopbackSuccess = array_merge($loopbackSuccess, $result['success']);
                $loopbackFailed = array_merge($loopbackFailed, $result['failed']);
                $deliveredUncertain = array_merge($deliveredUncertain, $result['uncertain']);
            }
        }

        $cliFallbackSuccess = [];
        if (!empty($loopbackFailed)) {
            foreach ($loopbackFailed as $task) {
                $cliOk = Transport::cli(
                    $task['script'],
                    (int) $task['worker'],
                    (int) $task['workers'],
                    true,
                    '',
                    '',
                    (int) ($task['occurrence'] ?? 0)
                );
                if ($cliOk) {
                    $cliFallbackSuccess[] = $task;
                    $this->cliDispatched++;
                }
            }
        }

        $allSuccess = array_merge($primarySuccess, $loopbackSuccess, $cliFallbackSuccess);
        $this->successful += count($allSuccess);

        $successfulJobs = [];
        foreach (array_merge($allSuccess, $deliveredUncertain) as $task) {
            $successfulJobs[$task['key']] = true;
        }
        $this->markRuns($planner, array_keys($successfulJobs), $now);
    }

    private function markRuns(Planner $planner, array $jobKeys, int $now): void
    {
        foreach ($jobKeys as $jobKey) {
            Planner::markJobRun($this->pdo, $jobKey, (int) ($planner->dueOccurrences[$jobKey] ?? $now), $planner->runtimeState);
        }
    }
}
