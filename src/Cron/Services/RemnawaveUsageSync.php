<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\CronBatches;
use Faoxima\Cron\Runtime\TaskState;
use PDO;
use Faoxima\Services\Panels\Remnawave\RemnawaveManager;
use Throwable;

final class RemnawaveUsageSync
{
    private const JOB = 'remnawave_usage';
    private const TAG = '[REMNAMWAVE-CRON-DISCONNECT]';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run(): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $rows = $this->nextBatch();
        if (!is_array($rows) || count($rows) === 0) {
            return;
        }

        $managers = [];
        $lastScanned = 0;
        foreach ($rows as $row) {
            $lastScanned = (int) ($row['id'] ?? 0);
            $namePanel = (string) ($row['name_panel'] ?? '');
            if ($namePanel === '') {
                continue;
            }
            try {
                $panel = Db::select("marzban_panel", "*", "name_panel", $namePanel, "select");
                if (!is_array($panel) || ($panel['type'] ?? '') !== 'remnawave') {
                    continue;
                }
                if (!isset($managers[$namePanel])) {
                    $managers[$namePanel] = new RemnawaveManager($panel);
                }
                $result = $managers[$namePanel]->getUserById($row['panel_user_id'], $this->pdo);
                if (empty($result['ok']) || !is_array($result['data'])) {
                    error_log(self::TAG . " Panel: $namePanel | User: {$row['username']} | could not fetch usage");
                    continue;
                }
                $this->persist($row, $result['data']);
            } catch (Throwable $e) {
                error_log(self::TAG . " Panel: $namePanel | User: " . ($row['username'] ?? '') . " | " . $e->getMessage());
                continue;
            }
        }

        if ($lastScanned > 0) {
            try {
                CronBatches::rx_cron_bulk_cursor($this->pdo, self::JOB, $lastScanned);
            } catch (Throwable $e) {
                error_log(self::TAG . " cursor persist failed | " . $e->getMessage());
            }
        }
        TaskState::rx_cron_task_complete('remnawave_usage_finished');
    }

    private function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'remnawave_users'");
            $stmt->execute();
            return count($stmt->fetchAll()) !== 0;
        } catch (Throwable $e) {
            error_log(self::TAG . " table check failed | " . $e->getMessage());
            return false;
        }
    }

    private function nextBatch(): ?array
    {
        $cursor = 0;
        try {
            $cursor = CronBatches::rx_cron_bulk_cursor($this->pdo, self::JOB);
        } catch (Throwable $e) {
            error_log(self::TAG . ' cursor read failed | ' . get_class($e));
            $cursor = 0;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT id, name_panel, username, status, panel_user_id, subscription_url FROM remnawave_users WHERE status = 'active' AND panel_user_id IS NOT NULL AND panel_user_id <> '' AND id > ? ORDER BY id ASC LIMIT 50");
            $stmt->execute([$cursor]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows && $cursor > 0) {
                $stmt->execute([0]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return $rows;
        } catch (Throwable $e) {
            error_log(self::TAG . " fetch users failed | " . $e->getMessage());
            return null;
        }
    }

    private function persist(array $row, array $data): void
    {
        $remote = strtolower((string) ($data['status'] ?? 'active'));
        $local = ($remote === 'disabled' || $remote === 'expired' || $remote === 'limited') ? $remote : 'active';
        if ($local !== (string) ($row['status'] ?? 'active')) {
            $upd = $this->pdo->prepare("UPDATE remnawave_users SET status = :s WHERE id = :id");
            $upd->execute([':s' => $local, ':id' => $row['id']]);
        }
        $subUrl = (string) ($data['subscriptionUrl'] ?? '');
        if ($subUrl !== '' && $subUrl !== (string) ($row['subscription_url'] ?? '')) {
            $upd = $this->pdo->prepare("UPDATE remnawave_users SET subscription_url = :u WHERE id = :id");
            $upd->execute([':u' => $subUrl, ':id' => $row['id']]);
        }
    }
}
