<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\NotificationOutbox;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Services\Panels\PanelMessages;
use Faoxima\Telegram\Support\Texts;
use RuntimeException;

final class GiftCampaign
{
    private const BATCH_SIZE = 5;
    private const ERROR_REPORT_TPL = "خطای اضافه شدن هدیه حجم\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>";

    private $panel;
    private $pdo;
    private $setting;
    private $errorReportThread;

    private string $giftFile;
    private string $queueFile;
    private string $notifiedFile;
    private string $pendingNotifyFile;
    private string $failedFile;
    private string $applyingFile;

    private NotificationOutbox $outbox;
    private array $applying = [];
    private array $failedUsers = [];
    private array $notifiedUsers = [];
    private array $queue = [];
    private $info = [];
    private string $campaign = '';
    private int $processed = 0;

    public function __construct($panel, $pdo, $setting, $errorReportThread, string $root)
    {
        $this->panel = $panel;
        $this->pdo = $pdo;
        $this->setting = $setting;
        $this->errorReportThread = $errorReportThread;
        $dir = $root . '/cronbot';
        $this->giftFile = $dir . '/gift';
        $this->queueFile = $dir . '/username.json';
        $this->notifiedFile = $dir . '/gift_notified.json';
        $this->pendingNotifyFile = $dir . '/gift_notify_pending.json';
        $this->outbox = new NotificationOutbox(
            $this->pendingNotifyFile,
            static function (array $entry) {
                return telegram('sendmessage', [
                    'chat_id' => $entry['id_user'],
                    'text' => (string) $entry['text'],
                    'parse_mode' => 'HTML',
                    '_rx_no_network_retry' => true,
                ]);
            },
            false,
            true,
            false,
            'gift_notification_persist_failed'
        );
        $this->failedFile = $dir . '/gift_failed.json';
        $this->applyingFile = $dir . '/gift_applying.json';
    }

    public static function loadButtonTexts(): array
    {
        $rows = Db::select("textbot", "*", null, null, "fetchAll");
        $texts = [
            'text_usertest' => '',
            'text_support' => '',
            'text_help' => '',
            'text_sell' => '',
            'text_affiliates' => '',
            'text_Add_Balance' => '',
        ];
        foreach ($rows as $row) {
            $id = $row['id_text'];
            $text = $row['text'];
            if (array_key_exists($id, $texts) || (is_string($text) && trim($text) !== '')) {
                $texts[$id] = $text;
            }
        }
        return $texts;
    }

    public function loadNotifications(): bool
    {
        return $this->outbox->load('gift_notification_queue_invalid');
    }

    public function drainNotifications(): void
    {
        $this->outbox->drain(5);
    }

    private function deferIfPending(): void
    {
        if ($this->outbox->hasPending()) {
            TaskState::rx_cron_task_defer('gift_notifications_pending', 60);
        }
    }

    public function hasQueue(): bool
    {
        return is_file($this->giftFile) && is_file($this->queueFile);
    }

    public function finishWithoutQueue(): void
    {
        $this->deferIfPending();
        TaskState::rx_cron_task_complete('gift_queue_absent');
    }

    public function loadQueue(): void
    {
        $queue = json_decode(file_get_contents($this->queueFile));
        $this->queue = is_array($queue) ? $queue : [];
        if (is_file($this->giftFile)) {
            $this->info = json_decode(file_get_contents($this->giftFile), true);
        }
    }

    public function isQueueEmpty(): bool
    {
        return count($this->queue) == 0;
    }

    public function finishEmptyQueue(): void
    {
        if (isset($this->info['id_admin'])) {
            deletemessage($this->info['id_admin'], $this->info['id_message']);
            sendmessage($this->info['id_admin'], Texts::faoxima_textbot_get('dyn_gift_all_done', '📌 عملیات برای تمامی سرویس های درخواستی انجام شد.'), null, 'HTML');
            unlink($this->giftFile);
            unlink($this->queueFile);
            @unlink($this->notifiedFile);
        }
        $this->deferIfPending();
        TaskState::rx_cron_task_complete('gift_nothing_to_do');
    }

    public function prepareCampaign(): bool
    {
        if (!isset($this->info['typegift'])) {
            TaskState::rx_cron_task_defer('gift_metadata_invalid', 600);
            return false;
        }

        $this->failedUsers = [];
        if (is_file($this->failedFile)) {
            $this->failedUsers = json_decode(file_get_contents($this->failedFile), true) ?: [];
        }
        $this->notifiedUsers = [];
        if (is_file($this->notifiedFile)) {
            $this->notifiedUsers = json_decode(file_get_contents($this->notifiedFile), true) ?: [];
        }

        if (empty($this->info['cron_campaign_id'])) {
            $this->info['cron_campaign_id'] = bin2hex(random_bytes(16));
            $tmp = $this->giftFile . '.tmp';
            $encoded = json_encode($this->info, JSON_UNESCAPED_UNICODE);
            if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $this->giftFile)) {
                TaskState::rx_cron_task_defer('gift_campaign_persist_failed', 120);
                return false;
            }
        }
        $this->campaign = (string) $this->info['cron_campaign_id'];

        $this->applying = [];
        if (is_file($this->applyingFile)) {
            $applying = json_decode(file_get_contents($this->applyingFile), true);
            if (!is_array($applying)) {
                TaskState::rx_cron_task_defer('gift_operation_guards_invalid', 300);
                return false;
            }
            $this->applying = $applying;
        }
        return true;
    }

    private function logFailedUser($username): void
    {
        $this->failedUsers[] = $username;
        $this->failedUsers = array_values(array_unique($this->failedUsers));
        file_put_contents($this->failedFile, json_encode($this->failedUsers, JSON_UNESCAPED_UNICODE));
    }

    private function notifyUserOnce($userId, $text): bool
    {
        if ($userId === null || $userId === '') {
            return false;
        }
        $userKey = (string) $userId;
        if (in_array($userKey, $this->notifiedUsers, true)) {
            return true;
        }
        $key = $this->campaign . ':' . $userKey;
        if (!$this->outbox->has($key)) {
            $this->outbox->add($key, ['id_user' => $userKey, 'text' => (string) $text, 'state' => 'pending', 'created_at' => time(), 'attempts' => 0, 'next_attempt_at' => 0]);
        }
        if (!$this->outbox->deliver($key)) {
            return false;
        }
        $this->notifiedUsers[] = $userKey;
        file_put_contents($this->notifiedFile, json_encode(array_values(array_unique($this->notifiedUsers)), JSON_UNESCAPED_UNICODE), LOCK_EX);
        return true;
    }

    private function saveApplying(): void
    {
        $encoded = json_encode($this->applying, JSON_UNESCAPED_UNICODE);
        $tmp = $this->applyingFile . '.tmp';
        if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $this->applyingFile)) {
            throw new RuntimeException('gift_operation_guard_persist_failed');
        }
    }

    private function persistQueue(): void
    {
        $encoded = json_encode(array_values($this->queue), JSON_UNESCAPED_UNICODE);
        $tmp = $this->queueFile . '.tmp';
        if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $this->queueFile)) {
            throw new RuntimeException('gift_queue_persist_failed');
        }
    }

    public function processBatch(): void
    {
        $this->processed = 0;
        while (!empty($this->queue) && $this->processed < self::BATCH_SIZE) {
            if (CronRuntime::rx_cron_time_up()) {
                TaskState::rx_cron_task_defer('time_budget', 60);
                break;
            }
            $entry = array_shift($this->queue);
            try {
                $this->giftOne($entry);
            } finally {
                $this->persistQueue();
            }
        }
    }

    private function giftOne($entry): void
    {
        if (!isset($entry->username)) {
            return;
        }

        $this->processed++;
        $username = $entry->username;
        $info = $this->info;
        $userData = $this->panel->DataUser($info['name_panel'], $username);

        $operation = $this->campaign . ':' . (string) $username;
        $applying = array_map('strval', $this->applying);
        if (in_array('done:' . $operation, $applying, true)) {
            return;
        }
        if (in_array($operation, $applying, true) || in_array((string) $username, $applying, true)) {
            $this->logFailedUser($username);
            return;
        }

        if (!is_array($userData)) {
            $this->logFailedUser($username);
            return;
        }
        if (($userData['status'] ?? '') === 'Unsuccessful') {
            $this->logFailedUser($username);
            return;
        }
        $hasExpire = array_key_exists('expire', $userData) && $userData['expire'] !== null && $userData['expire'] !== '';
        if (!$hasExpire) {
            $this->logFailedUser($username);
            return;
        }
        $hasDataLimit = array_key_exists('data_limit', $userData) && $userData['data_limit'] !== null && $userData['data_limit'] !== '';
        if (!$hasDataLimit) {
            $this->logFailedUser($username);
            return;
        }

        $invoice = Db::select("invoice", "*", "username", $username, "select");
        if (!is_array($invoice) || !isset($invoice['id_user']) || empty($invoice['id_user'])) {
            $this->logFailedUser($username);
            return;
        }
        $panelRow = Db::select("marzban_panel", "*", "name_panel", $info['name_panel'], "select");

        $this->applying[] = $operation;
        $this->applying = array_values(array_unique($this->applying));
        $this->saveApplying();

        if ($info['typegift'] == "volume") {
            $result = $this->panel->extra_volume($invoice['username'], $panelRow['code_panel'], $info['value']);
            $hadPersistentError = $this->recordOutcome($result, $panelRow, $username, $invoice, 'volume_value', 'gift_volume', $userData);
        } else {
            $result = $this->panel->extra_time($userData['username'], $panelRow['code_panel'], intval($info['value']));
            $hadPersistentError = $this->recordOutcome($result, $panelRow, $username, $invoice, 'time_value', 'gift_time', $userData);
        }

        if (!$hadPersistentError) {
            $this->applying = array_values(array_diff($this->applying, [$operation]));
            $this->applying[] = 'done:' . $operation;
            $this->saveApplying();
        }
        if ($hadPersistentError) {
            $this->logFailedUser($username);
        }
    }

    private function recordOutcome(array $result, $panelRow, $username, array $invoice, string $valueKey, string $type, array $userData): bool
    {
        $info = $this->info;
        $hadPersistentError = false;
        if ($result['status'] == false) {
            $hadPersistentError = true;
            $result['msg'] = PanelMessages::rx_panel_error_text($result['msg'] ?? null, $result['detail'] ?? null);
            $report = Texts::faoxima_render_text(Texts::faoxima_textbot_get('dyn_gift_error_report_tpl', self::ERROR_REPORT_TPL), [
                'panel_name' => $panelRow['name_panel'],
                'username' => $username,
                'reason' => $result['msg'],
            ]);
            if (strlen($this->setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $this->setting['Channel_Report'],
                    'message_thread_id' => $this->errorReportThread,
                    'text' => $report,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            $this->notifyUserOnce($invoice['id_user'], $info['text']);
        }

        $payload = json_encode(array(
            $valueKey => $info['value'],
            'old_volume' => $userData['data_limit'],
            'expire_old' => $userData['expire']
        ));
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
        $stmt->execute([
            ':id_user' => $invoice['id_user'],
            ':username' => $invoice['username'],
            ':value' => $payload,
            ':type' => $type,
            ':time' => date('Y/m/d H:i:s'),
            ':price' => 0,
            ':output' => json_encode($result)
        ]);
        return $hadPersistentError;
    }

    public function finishBatch(): void
    {
        $info = $this->info;
        if (empty($this->queue)) {
            if (isset($info['id_admin'])) {
                deletemessage($info['id_admin'], $info['id_message']);
                $successText = Texts::faoxima_textbot_get('dyn_gift_all_done', '📌 عملیات برای تمامی سرویس های درخواستی انجام شد.');
                if (!empty($this->failedUsers)) {
                    $failedList = implode("\n", $this->failedUsers);
                    $successText .= Texts::faoxima_render_text(Texts::faoxima_textbot_get('dyn_gift_failed_users_suffix_tpl', "\n⚠️ کاربران با خطای اعمال هدیه:\n{list}"), ['list' => $failedList]);
                }
                sendmessage($info['id_admin'], $successText, null, 'HTML');
                unlink($this->giftFile);
                unlink($this->queueFile);
                @unlink($this->notifiedFile);
                if ($this->outbox->isEmpty()) {
                    @unlink($this->pendingNotifyFile);
                }
                if (!$this->applying) {
                    @unlink($this->applyingFile);
                }
                if (empty($this->failedUsers) && is_file($this->failedFile)) {
                    unlink($this->failedFile);
                }
            }
        }
        $this->deferIfPending();
        TaskState::rx_cron_task_complete('gift_batch_finished');
    }
}
