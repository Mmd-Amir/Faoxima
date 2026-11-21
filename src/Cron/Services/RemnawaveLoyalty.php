<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Services\Panels\Drivers\Remnawave;
use PDO;
use Throwable;

final class RemnawaveLoyalty
{
    private const TIER_GOLD = 5;
    private const TIER_SILVER = 3;
    private const TIER_BRONZE = 1;

    private PDO $pdo;
    private array $counts = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run(): void
    {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'remnawave_users'");
            $stmt->execute();
            if (count($stmt->fetchAll()) === 0) {
                return;
            }
        } catch (Throwable $e) {
            error_log("[REMNAWAVE-LOYALTY] table check failed | " . $e->getMessage());
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM remnawave_users WHERE status = 'active' AND panel_user_id IS NOT NULL AND panel_user_id <> '' ORDER BY id ASC LIMIT 200");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log("[REMNAWAVE-LOYALTY] fetch failed | " . $e->getMessage());
            return;
        }
        if (!is_array($rows) || count($rows) === 0) {
            return;
        }

        foreach ($rows as $row) {
            try {
                $this->retag($row);
            } catch (Throwable $e) {
                error_log("[REMNAWAVE-LOYALTY] " . $e->getMessage());
                continue;
            }
        }
    }

    private function retag(array $row): void
    {
        $namePanel = (string) ($row['name_panel'] ?? '');
        $idOrder = (string) ($row['id_order'] ?? '');
        if ($namePanel === '' || $idOrder === '') {
            return;
        }
        $panel = Db::select("marzban_panel", "*", "name_panel", $namePanel, "select");
        if (!is_array($panel) || ($panel['type'] ?? '') !== 'remnawave') {
            return;
        }
        $invoice = Db::select("invoice", "*", "id_invoice", $idOrder, "select");
        $buyer = is_array($invoice) ? (string) ($invoice['id_user'] ?? '') : '';
        if ($buyer === '') {
            return;
        }
        $tier = $this->tierFor($this->activeCount($buyer));
        if ($tier === '' || (string) ($row['remna_tier'] ?? '') === $tier) {
            return;
        }
        $result = method_exists(Remnawave::class, 'remnawave_set_tag') ? Remnawave::remnawave_set_tag($namePanel, (string) $row['username'], $tier) : ['status' => 'Unsuccessful'];
        if (($result['status'] ?? '') === 'successful') {
            $upd = $this->pdo->prepare("UPDATE remnawave_users SET remna_tier = :t WHERE id = :id");
            $upd->execute([':t' => $tier, ':id' => $row['id']]);
        }
    }

    private function activeCount(string $buyer): int
    {
        if (!isset($this->counts[$buyer])) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM remnawave_users r JOIN invoice i ON i.id_invoice = r.id_order WHERE i.id_user = :b AND r.status = 'active'");
            $stmt->execute([':b' => $buyer]);
            $this->counts[$buyer] = (int) $stmt->fetchColumn();
        }
        return $this->counts[$buyer];
    }

    private function tierFor(int $count): string
    {
        return $count >= self::TIER_GOLD ? 'GOLD' : ($count >= self::TIER_SILVER ? 'SILVER' : ($count >= self::TIER_BRONZE ? 'BRONZE' : ''));
    }
}
