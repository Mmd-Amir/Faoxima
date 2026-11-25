<?php

namespace Faoxima\Services\Delivery;

use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Telegram\Support\MessageTemplates;
use ManagePanel;
use PDO;
use RuntimeException;
use Throwable;

final class AutoFileQueue
{
    public static function enqueue(PDO $pdo, array $panel, string $invoice, string $user): ?bool
    {
        if ($invoice === '' || $user === '' || ($panel['type'] ?? '') === 'Manualsale' || ($panel['auto_file'] ?? '') !== 'onautofile') {
            return null;
        }
        try {
            $statement = $pdo->prepare("INSERT INTO auto_file_delivery_queue (id_invoice, id_user, next_attempt_at)
                VALUES (?, ?, UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE next_attempt_at = auto_file_delivery_queue.next_attempt_at");
            if (!$statement->execute([$invoice, $user])) {
                throw new RuntimeException('Could not enqueue file delivery');
            }
            return true;
        } catch (Throwable $e) {
            error_log('rxAutoFileDelivery invoice=' . $invoice . ' stage=enqueue category=' . get_class($e));
            return false;
        }
    }

    public static function drain(PDO $pdo, ManagePanel $panels): void
    {
        $lockName = 'rxafq:' . substr(hash('sha256', (string) $pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 56);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$lockName]);
        $acquired = (string) $lock->fetchColumn() === '1';
        $lock->closeCursor();
        if (!$acquired) {
            return;
        }

        try {
            if ($pdo->query('SELECT 1 FROM auto_file_delivery_queue WHERE blocked_until > UNIX_TIMESTAMP() LIMIT 1')->fetchColumn()) {
                return;
            }
            $rows = $pdo->query("SELECT id_invoice, id_user, attempts FROM auto_file_delivery_queue
                WHERE status = 'pending' AND next_attempt_at <= UNIX_TIMESTAMP()
                ORDER BY next_attempt_at, id_invoice LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $job) {
                if (CronRuntime::rx_cron_time_up()) {
                    break;
                }
                $attempt = (int) $job['attempts'] + 1;
                $delay = min(3600, 60 * (2 ** min(6, $attempt - 1)));
                $blockedUntil = 0;
                try {
                    $status = self::deliver($pdo, $panels, $job);
                } catch (AutoFileRateLimit $e) {
                    $status = 'pending';
                    $delay = max($delay, $e->seconds);
                    $blockedUntil = time() + $e->seconds;
                } catch (Throwable $e) {
                    $status = 'pending';
                    error_log('rxAutoFileDelivery invoice=' . $job['id_invoice'] . ' stage=worker category=' . get_class($e));
                }
                if ($status === 'pending' && $attempt >= 10 && $blockedUntil === 0) {
                    $status = 'failed';
                }
                $update = $pdo->prepare('UPDATE auto_file_delivery_queue
                    SET status = ?, attempts = ?, next_attempt_at = ?, blocked_until = ?
                    WHERE id_invoice = ? AND id_user = ?');
                if (!$update->execute([$status, $blockedUntil > 0 ? (int) $job['attempts'] : $attempt,
                    $status === 'pending' ? time() + $delay : 0, $blockedUntil, $job['id_invoice'], $job['id_user']])) {
                    throw new RuntimeException('Could not save file delivery queue state');
                }
                if ($status === 'failed' || $status === 'unknown') {
                    error_log('rxAutoFileDelivery invoice=' . $job['id_invoice'] . ' stage=worker category=' . $status);
                    try {
                        $notice = $status === 'unknown' ? 'service_files_pending_retry' : 'service_files_incomplete_contact_support';
                        sendmessage($job['id_user'], MessageTemplates::get($notice), null, 'HTML');
                    } catch (Throwable $e) {
                        error_log('rxAutoFileDelivery invoice=' . $job['id_invoice'] . ' stage=notice category=' . get_class($e));
                    }
                }
                if ($blockedUntil > 0) {
                    break;
                }
            }
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
            $release->closeCursor();
        }
    }

    private static function deliver(PDO $pdo, ManagePanel $panels, array $job): string
    {
        $read = $pdo->prepare('SELECT username, Service_location, Status, user_info FROM invoice
            WHERE id_invoice = ? AND id_user = ? LIMIT 1');
        $read->execute([$job['id_invoice'], $job['id_user']]);
        $invoice = $read->fetch(PDO::FETCH_ASSOC);
        if ($invoice && $invoice['Status'] === 'processing') {
            return 'pending';
        }
        if (!$invoice || !in_array($invoice['Status'], ['active', 'sendedwarn', 'send_on_hold'], true)) {
            return 'skipped';
        }
        $panel = ServiceDelivery::rxAutoFilePanelRow($invoice['Service_location']);
        if (!$panel) {
            return 'pending';
        }
        if (($panel['type'] ?? '') === 'Manualsale' || ($panel['auto_file'] ?? '') !== 'onautofile') {
            return 'skipped';
        }
        $saved = json_decode((string) $invoice['user_info'], true);
        if (!is_array($saved) || ($saved['username'] ?? null) !== $invoice['username']) {
            $saved = [];
        }
        $configs = $saved['configs'] ?? $saved['links'] ?? [];
        $link = (string) ($saved['subscription_url'] ?? '');
        $type = $panel['type'] ?? '';
        if ($type !== 'jovadpanel' && ($type === 'WGDashboard' ? $link === '' : empty($configs))) {
            $remote = $panels->DataUser($invoice['Service_location'], $invoice['username']);
            if (!is_array($remote) || empty($remote['username'])) {
                return 'pending';
            }
            $configs = $remote['configs'] ?? $remote['links'] ?? $configs;
            $link = (string) ($remote['subscription_url'] ?? $link);
        }
        $result = ServiceDelivery::rxAutoDeliverPurchaseFiles($panel, $configs, $link, $invoice['username'],
            $job['id_user'], $job['id_invoice'], $pdo);
        if (!empty($result['retry']) || (empty($result['ok']) && empty($result['total']))) {
            return 'pending';
        }
        if (!empty($result['unknown'])) {
            return 'unknown';
        }
        if (!empty($result['failed'])) {
            return 'failed';
        }
        return empty($result['sent']) ? 'skipped' : 'sent';
    }
}
