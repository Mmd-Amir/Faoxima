<?php

namespace Faoxima\Cron\Runtime;

use RuntimeException;

final class NotificationOutbox
{
    private string $file;
    private $sender;
    private bool $checkBudget;
    private bool $recordSentTime;
    private bool $ensureDir;
    private string $persistError;
    private array $entries = [];

    public function __construct(string $file, callable $sender, bool $checkBudget, bool $recordSentTime, bool $ensureDir, string $persistError)
    {
        $this->file = $file;
        $this->sender = $sender;
        $this->checkBudget = $checkBudget;
        $this->recordSentTime = $recordSentTime;
        $this->ensureDir = $ensureDir;
        $this->persistError = $persistError;
    }

    public function load(string $invalidReason): bool
    {
        if (is_file($this->file)) {
            $entries = json_decode((string) file_get_contents($this->file), true);
            if (!is_array($entries)) {
                TaskState::rx_cron_task_defer($invalidReason, 300);
                return false;
            }
            $this->entries = $entries;
        }
        return true;
    }

    public function entries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return !$this->entries;
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    public function save(): void
    {
        if ($this->ensureDir) {
            $dir = dirname($this->file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        $encoded = json_encode($this->entries, JSON_UNESCAPED_UNICODE);
        $tmp = $this->file . '.tmp';
        if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $this->file)) {
            throw new RuntimeException($this->persistError);
        }
    }

    public function deliver($key): bool
    {
        return CronBatches::rx_cron_deliver_pending_notification(
            $this->entries,
            $key,
            function (): void {
                $this->save();
            },
            $this->sender,
            $this->checkBudget,
            $this->recordSentTime
        );
    }

    public function drain(int $limit): void
    {
        CronBatches::rx_cron_drain_notifications($this->entries, $limit, function ($key): bool {
            return $this->deliver($key);
        });
    }

    public function hasPending(): bool
    {
        foreach ($this->entries as $entry) {
            if (is_array($entry) && ($entry['state'] ?? '') === 'pending') {
                return true;
            }
        }
        return false;
    }

    public function add(string $key, array $entry): void
    {
        $this->entries[$key] = $entry;
        $this->save();
    }

    public function enqueue(string $key, array $entry): string
    {
        if (isset($this->entries[$key])) {
            return $key;
        }
        foreach ($this->entries as $existingKey => $existing) {
            if (($entry['kind'] ?? '') === 'report' && (string) ($existing['kind'] ?? '') === 'report'
                && (string) ($existing['chat_id'] ?? '') === (string) ($entry['chat_id'] ?? '')
                && (string) ($existing['thread_id'] ?? '') === (string) ($entry['thread_id'] ?? '')
                && (string) ($existing['bottype'] ?? '') === (string) ($entry['bottype'] ?? '')
                && (string) ($existing['text'] ?? '') === (string) ($entry['text'] ?? '')) {
                return (string) $existingKey;
            }
        }
        if (!isset($this->entries[$key])) {
            $entry['state'] = 'pending';
            $entry['created_at'] = time();
            $entry['attempts'] = 0;
            $entry['next_attempt_at'] = 0;
            $this->add($key, $entry);
        }
        return $key;
    }

    public function sendOnce(string $key, array $entry): bool
    {
        return $this->deliver($this->enqueue($key, $entry));
    }
}
