<?php

namespace Faoxima\Cron\Dispatch;

use Faoxima\Core\Cron\Schedules;
use Faoxima\Cron\Runtime\TaskQueue;
use Faoxima\Cron\Runtime\TaskState;
use PDO;
use RuntimeException;
use Throwable;

final class Planner
{
    public array $dueTasks = [];
    public array $validTasks = [];
    public array $dueOccurrences = [];
    public array $availability = [];
    public array $definitions = [];
    public int $skippedMissing = 0;
    public array $runtimeState;

    private PDO $pdo;
    private string $root;
    private int $now;
    private int $minute;
    private int $hour;
    private int $dayOfYear;
    private array $jobHours;
    private array $jobWorkerCounts;
    private bool $taskStateEnabled;
    private int $maxAttempts;
    private int $lease;
    private $buildUrl;

    public function __construct(PDO $pdo, string $root, array $runtimeState, array $jobHours, array $jobWorkerCounts, bool $taskStateEnabled, int $maxAttempts, int $lease, callable $buildUrl, int $now)
    {
        $this->pdo = $pdo;
        $this->root = $root;
        $this->runtimeState = $runtimeState;
        $this->jobHours = $jobHours;
        $this->jobWorkerCounts = $jobWorkerCounts;
        $this->taskStateEnabled = $taskStateEnabled;
        $this->maxAttempts = $maxAttempts;
        $this->lease = $lease;
        $this->buildUrl = $buildUrl;
        $this->now = $now;
        $this->minute = (int) date('i', $now);
        $this->hour = (int) date('G', $now);
        $this->dayOfYear = (int) date('z', $now);
    }

    public static function runMaintenance(PDO $pdo, int $now, int $hour, array &$runtimeState): void
    {
        if ($hour < 3) {
            return;
        }
        $key = '__processed_updates_cleanup';
        $due = Schedules::rx_cron_job_due($key, ['unit' => 'day', 'value' => 1], $now, $runtimeState, 3);
        if ($due !== null) {
            try {
                if (Schedules::rx_cron_claim_job_occurrence($pdo, $key, $due)) {
                    $runtimeState[$key] = $due;
                    $pdo->exec("DELETE FROM processed_updates WHERE processed_at < UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY)");
                }
            } catch (Throwable $e) {
            }
        }
    }

    public static function markJobRun(PDO $pdo, string $key, int $occurrence, array &$runtimeState): void
    {
        if (method_exists(Schedules::class, 'rx_cron_claim_job_occurrence')) {
            try {
                Schedules::rx_cron_claim_job_occurrence($pdo, $key, $occurrence);
            } catch (Throwable $e) {
            }
        } elseif (method_exists(Schedules::class, 'setCronJobLastRun')) {
            try {
                Schedules::setCronJobLastRun($pdo, $key, $occurrence);
            } catch (Throwable $e) {
            }
        }
        $runtimeState[$key] = max((int) ($runtimeState[$key] ?? 0), $occurrence);
    }

    public function scriptExists(string $script): bool
    {
        $script = ltrim($script, '/');
        if ($script === '' || strpos($script, '..') !== false) {
            return false;
        }
        return is_file($this->root . '/cron/../cronbot/' . $script);
    }

    public function reportMissingScript(string $key, string $script): void
    {
        $script = ltrim($script, '/');
        $marker = rtrim(sys_get_temp_dir(), '/' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'faoxima_cron_missing_' . md5($this->root . '/cron|' . $key . '|' . $script);
        if (is_file($marker) && (time() - (int) @filemtime($marker)) < 86400) {
            return;
        }
        @touch($marker);
        error_log(sprintf('[cron] skipped job "%s": script cronbot/%s does not exist', $key, $script));
    }

    private static function taskKeyFor(string $jobKey, int $workers, int $worker): string
    {
        return TaskState::rx_cron_task_key($jobKey, $workers, $worker);
    }

    public function plan(): void
    {
        $pdo = $this->pdo;
        $definitions = Schedules::getCronJobDefinitions();
        $this->definitions = $definitions;
        $schedules = Schedules::loadCronSchedules();
        $queuedWorkerCounts = [];
        $queuedJobKeys = [];
        $queuedTaskKeys = [];
        $this->availability = [];

        if ($this->taskStateEnabled) {
            [$queuedWorkerCounts, $queuedJobKeys, $queuedTaskKeys] = $this->readQueuedTasks();
        }

        $preserveOccurrenceJobs = ['statusday' => true, 'lottery' => true, 'logs_cleanup' => true];
        foreach ($definitions as $key => $definition) {
            if (empty($definition['script'])) {
                continue;
            }

            if (!$this->scriptExists((string) $definition['script'])) {
                $this->skippedMissing++;
                $this->reportMissingScript((string) $key, (string) $definition['script']);
                continue;
            }

            $defaultConfig = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
            $schedule      = $schedules[$key] ?? $defaultConfig;
            if (strtolower((string) ($schedule['unit'] ?? 'minute')) === 'disabled') {
                continue;
            }

            $occurrence = Schedules::rx_cron_job_due($key, $schedule, $this->now, $this->runtimeState, $this->jobHours[$key] ?? 0);
            $skipNoWork = false;
            if ($occurrence !== null || isset($queuedJobKeys[$key])) {
                $skipNoWork = ($this->probeWork((string) $key) === false);
            }
            $requested = max(1, (int) ($this->jobWorkerCounts[$key] ?? 1));
            $n = max(1, (int) ($queuedWorkerCounts[$key] ?? $requested));
            $base = ($this->buildUrl)($definition['script']);
            $sep = (strpos($base, '?') === false) ? '?' : '&';
            $taskList = [];

            for ($i = 0; $i < $n; $i++) {
                $task = [
                    'key' => $key,
                    'script' => $definition['script'],
                    'worker' => $i,
                    'workers' => $n,
                    'occurrence' => (int) ($occurrence ?? 0),
                    'url' => $n > 1
                        ? $base . $sep . 'worker=' . $i . '&workers=' . $n
                        : $base,
                ];
                $taskList[] = $task;
                $this->validTasks[self::taskKeyFor($key, $n, $i)] = $task;
            }

            if ($occurrence === null) {
                continue;
            }

            if ($this->taskStateEnabled) {
                $enqueuedAll = true;
                $effective = $occurrence;
                if (!$skipNoWork) {
                    $effective = null;
                    foreach ($taskList as $task) {
                        try {
                            $enqueued = TaskQueue::rx_cron_task_enqueue(
                                $pdo,
                                $key,
                                (string) $definition['script'],
                                (int) $task['workers'],
                                (int) $task['worker'],
                                $occurrence,
                                isset($preserveOccurrenceJobs[$key])
                            );
                        } catch (Throwable $e) {
                            $enqueued = 0;
                        }
                        if ($enqueued <= 0) {
                            $enqueuedAll = false;
                            break;
                        }
                        $effective = $effective === null ? $enqueued : min($effective, $enqueued);
                    }
                }
                if ($enqueuedAll) {
                    self::markJobRun($pdo, $key, max(1, (int) $effective), $this->runtimeState);
                    $this->dueOccurrences[$key] = $occurrence;
                }
                continue;
            }

            if ($skipNoWork) {
                self::markJobRun($pdo, $key, $occurrence, $this->runtimeState);
                continue;
            }
            foreach ($taskList as $task) {
                $this->dueTasks[] = $task;
            }
            $this->dueOccurrences[$key] = $occurrence;
        }

        if ($this->taskStateEnabled) {
            $this->reconcileQueue($queuedTaskKeys, $schedules);
        }
    }

    private function probeWork(string $jobKey): ?bool
    {
        if (array_key_exists($jobKey, $this->availability)) {
            return $this->availability[$jobKey];
        }
        $state = null;
        if (method_exists(Schedules::class, 'rx_cron_job_work_available')) {
            try {
                $state = Schedules::rx_cron_job_work_available($jobKey);
            } catch (Throwable $e) {
                $state = null;
            }
        }
        $this->availability[$jobKey] = $state;
        return $state;
    }

    private function readQueuedTasks(): array
    {
        $workerCounts = [];
        $jobKeys = [];
        $taskKeys = [];
        try {
            $rows = $this->pdo->query("SELECT task_key, job_key, script, worker, workers, state, scheduled_for FROM cron_task_state WHERE state IN ('pending','claimed','running','uncertain') ORDER BY scheduled_for ASC, task_key ASC");
            if ($rows === false) {
                throw new RuntimeException('Cannot read queued worker counts');
            }
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $job = (string) ($row['job_key'] ?? '');
                if ($job !== '') {
                    $jobKeys[$job] = true;
                }
                if (isset($this->jobWorkerCounts[$job]) && !isset($workerCounts[$job])) {
                    $workerCounts[$job] = max(1, min(8, (int) $row['workers']));
                }
                $taskKeys[(string) ($row['task_key'] ?? '')] = $row;
            }
        } catch (Throwable $e) {
            error_log('[cron] queued worker counts unavailable');
            echo "SKIP (task queue unavailable)\n";
            exit;
        }
        return [$workerCounts, $jobKeys, $taskKeys];
    }

    private function reconcileQueue(array $queuedTaskKeys, array $schedules): void
    {
        $pdo = $this->pdo;
        $definitions = $this->definitions;
        foreach ($queuedTaskKeys as $queuedKey => $queuedRow) {
            if ($queuedKey === '' || isset($this->validTasks[$queuedKey])) {
                continue;
            }
            $job = (string) ($queuedRow['job_key'] ?? '');
            if (!isset($definitions[$job]) || empty($definitions[$job]['script'])) {
                continue;
            }
            if (strtolower((string) ($schedules[$job]['unit'] ?? $definitions[$job]['default']['unit'] ?? 'minute')) === 'disabled') {
                continue;
            }
            if (!$this->scriptExists((string) $definitions[$job]['script'])) {
                continue;
            }
            $workers = max(1, (int) ($queuedRow['workers'] ?? 1));
            $worker = (int) ($queuedRow['worker'] ?? 0);
            if ($worker < 0 || $worker >= $workers) {
                continue;
            }
            $this->validTasks[$queuedKey] = [
                'key' => $job,
                'script' => (string) $definitions[$job]['script'],
                'worker' => $worker,
                'workers' => $workers,
                'occurrence' => (int) ($queuedRow['scheduled_for'] ?? 0),
                'url' => '',
            ];
        }
        try {
            TaskQueue::rx_cron_task_cancel_obsolete($pdo, $this->validTasks);
        } catch (Throwable $e) {
        }
        try {
            TaskQueue::rx_cron_task_prune($pdo, $this->now);
        } catch (Throwable $e) {
        }
        try {
            TaskState::rx_cron_task_scan_markers($pdo, $this->lease, $this->now);
        } catch (Throwable $e) {
        }
        try {
            TaskQueue::rx_cron_task_reconcile($pdo, $this->maxAttempts, $this->now);
        } catch (Throwable $e) {
        }
    }

    public function definedScripts(): array
    {
        $scripts = [];
        foreach ($this->definitions as $definition) {
            if (isset($definition['script']) && is_string($definition['script'])) {
                $scripts[] = ltrim($definition['script'], '/');
            }
        }
        return $scripts;
    }
}
