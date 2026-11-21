<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Services\Panels\Drivers\Rebecca;
use Faoxima\Services\Panels\Drivers\XuiSingle;
use Faoxima\Telegram\Support\Texts;
use PDO;
use Throwable;

final class IpLimitNotifier
{
    private PDO $pdo;
    private string $cursorFile;
    private string $cursor = '';
    private array $panels = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->cursorFile = CronRuntime::rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'ip_block_notify.cursor';
    }

    public function run(): void
    {
        $this->loadCursor();
        $this->loadPanels();

        $invoices = $this->batchAfter($this->cursor);
        if (!$invoices && $this->cursor !== '') {
            $invoices = $this->batchAfter('');
        }
        foreach ($invoices as $invoice) {
            $this->inspect($invoice);
        }
        $this->saveCursor($invoices);
        TaskState::rx_cron_task_complete('ip_block_notify_finished');
    }

    private function loadCursor(): void
    {
        if (is_file($this->cursorFile)) {
            $raw = trim((string) @file_get_contents($this->cursorFile));
            if ($raw !== '' && strlen($raw) <= 200) {
                $this->cursor = $raw;
            }
        }
    }

    private function loadPanels(): void
    {
        $this->panels = [];
        try {
            $stmt = $this->pdo->query("SELECT * FROM marzban_panel WHERE type IN ('x-ui_single', 'rebecca') AND ip_limit_guard = 'onipguard'");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $this->panels[strtolower((string) $row['name_panel'])] = $row;
            }
        } catch (Throwable $e) {
            $this->panels = [];
        }
    }

    private function batchAfter(string $bound): array
    {
        $stmt = $this->pdo->prepare("
            SELECT invoice.id_invoice, invoice.id_user, invoice.username, invoice.Service_location, invoice.ip_limit, invoice.ip_last_seen
            FROM invoice
            INNER JOIN marzban_panel ON marzban_panel.name_panel = invoice.Service_location
            WHERE marzban_panel.type IN ('x-ui_single', 'rebecca')
              AND marzban_panel.ip_limit_guard = 'onipguard'
              AND invoice.Status = 'active'
              AND invoice.ip_limit IS NOT NULL
              AND invoice.ip_limit != '0'
              AND invoice.id_invoice > ?
            ORDER BY invoice.id_invoice ASC
            LIMIT 25
        ");
        $stmt->execute([$bound]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function inspect(array $invoice): void
    {
        $panel = $this->panels[strtolower((string) $invoice['Service_location'])] ?? null;
        if (!is_array($panel)) {
            return;
        }
        $limit = (int) $invoice['ip_limit'];
        if ($limit <= 0) {
            return;
        }

        $summary = $panel['type'] === 'rebecca'
            ? Rebecca::rebeccaClientIpSummary($panel['name_panel'], $invoice['username'])
            : XuiSingle::xui_client_ip_summary($panel, $invoice['username']);
        if (empty($summary['status'])) {
            return;
        }
        $summary = Texts::faoxima_cap_ip_summary($summary, $limit);

        $current = is_array($summary['raw'] ?? null) ? array_values($summary['raw']) : [];
        $previous = json_decode((string) ($invoice['ip_last_seen'] ?? ''), true);
        if (!is_array($previous)) {
            $previous = [];
        }
        $dropped = array_values(array_diff($previous, $current));
        $added = array_values(array_diff($current, $previous));

        if (!empty($previous) && !empty($dropped) && !empty($added)) {
            $text = "⚠️ چون یک دستگاه/IP جدید به کانفیگ شما وصل شد، اتصال قبلی از IP <code>" . implode(', ', $dropped) . "</code> قطع شد.\n"
                . "این IP بعد از مدتی خودکار آزاد می‌شود.\n"
                . "اگر این تغییر از سمت شما نبوده، کانفیگ خود را با کسی به اشتراک نگذارید.";
            sendmessage($invoice['id_user'], $text, null, 'HTML');
        }

        if ($current !== $previous) {
            Db::update("invoice", "ip_last_seen", json_encode($current), "id_invoice", $invoice['id_invoice']);
        }
    }

    private function saveCursor(array $invoices): void
    {
        if (empty($invoices)) {
            return;
        }
        $next = (string) $invoices[count($invoices) - 1]['id_invoice'];
        if ($next !== '' && $next !== $this->cursor) {
            $tmp = $this->cursorFile . '.tmp';
            if (@file_put_contents($tmp, $next, LOCK_EX) !== false) {
                @rename($tmp, $this->cursorFile);
            }
        }
    }
}
