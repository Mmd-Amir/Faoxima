<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\CronBatches;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use PDO;
use Throwable;

final class TestServiceCleaner
{
    private PDO $pdo;
    private $panel;
    private array $texts;

    public function __construct(PDO $pdo, $panel, array $texts)
    {
        $this->pdo = $pdo;
        $this->panel = $panel;
        $this->texts = $texts;
    }

    public static function buttonTexts($rows): array
    {
        $texts = ['crontest' => ''];
        foreach ($rows as $row) {
            if (array_key_exists($row['id_text'], $texts) || (is_string($row['text']) && trim($row['text']) !== '')) {
                $texts[$row['id_text']] = $row['text'];
            }
        }
        return $texts;
    }

    public function run(): void
    {
        $limit = CronBatches::rx_cron_batch_size(15);
        $invoices = CronBatches::rx_cron_bulk_invoices($this->pdo, 'configtest', "status != 'disabled' AND name_product = ?", ['سرویس تست'], $limit);
        foreach ($invoices as $invoice) {
            if (CronRuntime::rx_cron_time_up()) {
                break;
            }
            try {
                $this->inspect($invoice);
            } catch (Throwable $e) {
                error_log('[cron:configtest] invoice ' . $invoice['id_invoice'] . ': ' . $e->getMessage());
            } finally {
                CronBatches::rx_cron_invoice_cursor('configtest', (string) $invoice['id_invoice']);
            }
        }
        TaskState::rx_cron_task_complete('configtest_finished');
    }

    private function inspect(array $invoice): void
    {
        $username = trim($invoice['username']);
        $panelRow = Db::select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if ($panelRow == false) {
            return;
        }
        $user = Db::select("user", "*", "id", $invoice['id_user'], "select");
        $GLOBALS['user'] = $user;
        $state = $this->panel->DataUser($invoice['Service_location'], $invoice['username']);
        if (($state['paused_by'] ?? '') === 'credit') {
            return;
        }
        if (!is_array($state) || empty($state['status'])) {
            return;
        }
        if (in_array($state['status'], ['active', 'on_hold', "Unsuccessful", "disabled"])) {
            return;
        }
        try {
            $held = $this->pdo->prepare("SELECT id FROM queued_renewal WHERE username = :username AND status IN ('pending', 'processing', 'retry', 'reconciling') LIMIT 1");
            $held->execute([':username' => $username]);
            if ($held->fetch(PDO::FETCH_ASSOC) !== false) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }
        if (CronRuntime::rx_cron_time_up()) {
            return;
        }
        $removed = $this->panel->RemoveUser($invoice['Service_location'], $username);
        if (empty($removed['status']) || $removed['status'] !== 'successful') {
            return;
        }
        Db::update("invoice", "status", "disabled", "id_invoice", $invoice['id_invoice']);
        if (intval($user['status_cron']) != 0) {
            $markup = json_encode([
                'inline_keyboard' => [
                    [
                        rx_cron_btn('cron_buy_service', ['text' => "🛍 خرید سرویس", 'callback_data' => 'buy']),
                    ],
                ]
            ]);
            sendmessage($invoice['id_user'], str_replace('{username}', $username, $this->texts['crontest']), $markup, 'HTML');
        }
    }
}
