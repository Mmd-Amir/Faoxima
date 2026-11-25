<?php

namespace Faoxima\Cron\Dispatch;

use Faoxima\Cron\Runtime\TaskState;
use PDO;
use Throwable;

final class Wake
{
    private static function stateFile(): string
    {
        return TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'wake_state.json';
    }

    public static function publish(array $deferredJobs): void
    {
        $payload = json_encode([
            'ts' => time(),
            'deferred' => array_values(array_filter($deferredJobs, 'is_string')),
        ]);
        if (!is_string($payload)) {
            return;
        }
        $file = self::stateFile();
        $tmp = $file . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            @unlink($tmp);
            return;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    public static function deferredJobs(int $now): array
    {
        $raw = @file_get_contents(self::stateFile());
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || (int) ($data['ts'] ?? 0) < $now - 180 || !is_array($data['deferred'] ?? null)) {
            return [];
        }
        return array_values(array_filter($data['deferred'], 'is_string'));
    }

    public static function request(PDO $pdo): void
    {
        if (getenv('RX_CRON_NOWAKE') === '1') {
            return;
        }
        $gate = @fopen(TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'wake_gate.lock', 'c');
        if ($gate === false) {
            return;
        }
        if (!@flock($gate, LOCK_EX | LOCK_NB)) {
            @fclose($gate);
            return;
        }
        try {
            $now = time();
            $last = (int) trim((string) @stream_get_contents($gate));
            if ($last > 0 && $last <= $now && $now - $last < 1) {
                return;
            }
            $deferred = self::deferredJobs($now);
            $excluded = $deferred ? ' AND job_key NOT IN (' . implode(',', array_fill(0, count($deferred), '?')) . ')' : '';
            $stmt = $pdo->prepare("SELECT 1 FROM cron_task_state WHERE state = 'pending' AND next_attempt_at <= ? AND scheduled_for BETWEEN ? AND ?" . $excluded . ' LIMIT 1');
            $stmt->execute(array_merge([$now, $now - TaskState::rx_cron_catchup_seconds(), $now], $deferred));
            if ($stmt->fetchColumn() === false) {
                return;
            }
            @ftruncate($gate, 0);
            @rewind($gate);
            @fwrite($gate, (string) $now);
            @fflush($gate);
            self::fire();
        } catch (Throwable $e) {
        } finally {
            @flock($gate, LOCK_UN);
            @fclose($gate);
        }
    }

    private static function fire(): void
    {
        if (Transport::cliDispatcher()) {
            return;
        }
        $domain = $GLOBALS['domainhosts'] ?? null;
        if (!is_string($domain) || trim($domain) === '') {
            return;
        }
        $probe = (Host::resolveBaseUrl($domain))('x');
        $url = str_replace('/cronbot/x', '/cron/cron.php', $probe);
        if ($url === $probe) {
            return;
        }
        $task = [['key' => '__wake__', 'script' => 'cron.php', 'worker' => 0, 'workers' => 1, 'url' => $url . '?wake=1']];
        $result = Transport::http($task, false, '', 1200);
        if (!empty($result['failed'])) {
            Transport::http($task, true, '', 1200);
        }
    }
}
