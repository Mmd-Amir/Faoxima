<?php

require_once __DIR__ . '/TelegramTransport.php';

final class FaoximaTelegramInbox
{
    public function __construct(private PDO $db, private string $botId)
    {
    }

    public function install(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS telegram_transport (
            id TINYINT UNSIGNED PRIMARY KEY,
            proxy_url VARCHAR(1024) NOT NULL,
            polling TINYINT UNSIGNED NOT NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            last_poll_ok BIGINT UNSIGNED NOT NULL DEFAULT 0,
            poll_state VARCHAR(24) NOT NULL DEFAULT 'idle'
        ) ENGINE=InnoDB");
        $this->db->exec("CREATE TABLE IF NOT EXISTS telegram_inbox (
            seq BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bot_id BIGINT UNSIGNED NOT NULL,
            update_id BIGINT UNSIGNED NOT NULL,
            conversation VARCHAR(32) CHARACTER SET ascii NOT NULL,
            payload MEDIUMTEXT NULL,
            state ENUM('pending','running','held','done') NOT NULL DEFAULT 'pending',
            finished_at DATETIME NULL,
            UNIQUE KEY telegram_update (bot_id, update_id),
            KEY telegram_pending (bot_id, state, seq),
            KEY telegram_conversation (bot_id, conversation, seq, state),
            KEY telegram_prune (bot_id, state, finished_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function botId(string $token): string
    {
        if (!preg_match('/^([1-9][0-9]{0,15}):[A-Za-z0-9_-]+$/D', $token, $parts)) {
            throw new InvalidArgumentException('Invalid Telegram bot token.');
        }
        return $parts[1];
    }

    public function configure(string $proxy, bool $polling): void
    {
        $proxy = FaoximaTelegramTransport::validateProxy($proxy);
        if ($polling && $proxy === '') {
            throw new InvalidArgumentException('Polling requires a Telegram proxy.');
        }
        $stmt = $this->db->prepare("INSERT INTO telegram_transport (id, proxy_url, polling) VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE proxy_url = ?, polling = ?, revision = revision + 1, last_poll_ok = 0, poll_state = 'idle'");
        $stmt->execute([$proxy, (int) $polling, $proxy, (int) $polling]);
    }

    public function pollStatus(int $revision, string $state): void
    {
        $stmt = $this->db->prepare('UPDATE telegram_transport SET last_poll_ok = ?, poll_state = ? WHERE id = 1 AND revision = ?');
        $stmt->execute([$state === 'online' ? time() : 0, $state, $revision]);
    }

    private function lockName(string $role): string
    {
        return 'faoxima.telegram.' . $role . '.' . $this->botId;
    }

    public function lock(string $role, int $timeout = 0): bool
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([$this->lockName($role), $timeout]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function ownsDispatch(int $seq, int $updateId, int $workerConnection): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM telegram_inbox
            WHERE bot_id = ? AND seq = ? AND update_id = ? AND state = 'running'
            AND IS_USED_LOCK(?) = ?");
        $stmt->execute([$this->botId, $seq, $updateId, $this->lockName('work'), $workerConnection]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function store(array $updates): int
    {
        if ($updates === []) {
            return 0;
        }
        $insert = $this->db->prepare("INSERT INTO telegram_inbox (bot_id, update_id, conversation, payload)
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE seq = seq");
        $this->db->beginTransaction();
        try {
            $previous = 0;
            foreach ($updates as $update) {
                $id = $update['update_id'] ?? null;
                if (!is_int($id) || $id <= $previous || $id === PHP_INT_MAX) {
                    throw new UnexpectedValueException('Invalid Telegram update sequence.');
                }
                $actor = $update['message']['from']['id'] ?? $update['callback_query']['from']['id']
                    ?? $update['inline_query']['from']['id'] ?? $update['pre_checkout_query']['from']['id']
                    ?? $update['chat_member']['new_chat_member']['user']['id'] ?? 0;
                $body = json_encode($update, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $insert->execute([$this->botId, $id, (string) (int) $actor, $body]);
                $previous = $id;
            }
            $this->db->commit();
            return $previous + 1;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function recoverInterrupted(): void
    {
        // A replacement worker must wait for an orphaned dispatch to exit.
        if (!$this->lock('dispatch', 130)) {
            throw new RuntimeException('The previous Telegram dispatch is still running.');
        }
        try {
            $stmt = $this->db->prepare("UPDATE telegram_inbox SET state = 'held' WHERE bot_id = ? AND state = 'running'");
            $stmt->execute([$this->botId]);
        } finally {
            $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$this->lockName('dispatch')]);
        }
    }

    public function next(): ?array
    {
        $stmt = $this->db->prepare("SELECT q.seq, q.update_id, q.payload FROM telegram_inbox q
            WHERE q.bot_id = ? AND q.state = 'pending' AND NOT EXISTS (
                SELECT 1 FROM telegram_inbox earlier WHERE earlier.bot_id = q.bot_id
                AND earlier.conversation = q.conversation AND earlier.seq < q.seq
                AND earlier.state IN ('pending', 'running', 'held')
            ) ORDER BY q.seq LIMIT 1");
        $stmt->execute([$this->botId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function transition(int $seq, string $from, string $to): bool
    {
        $sets = $to === 'done' ? ', payload = NULL, finished_at = UTC_TIMESTAMP()' : '';
        $stmt = $this->db->prepare("UPDATE telegram_inbox SET state = ?{$sets} WHERE bot_id = ? AND seq = ? AND state = ?");
        $stmt->execute([$to, $this->botId, $seq, $from]);
        return $stmt->rowCount() === 1;
    }

    public function held(): array
    {
        $stmt = $this->db->prepare("SELECT seq, update_id FROM telegram_inbox WHERE bot_id = ? AND state = 'held' ORDER BY seq LIMIT 100");
        $stmt->execute([$this->botId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function counts(): array
    {
        $stmt = $this->db->prepare("SELECT state, COUNT(*) FROM telegram_inbox WHERE bot_id = ? AND state <> 'done' GROUP BY state");
        $stmt->execute([$this->botId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function prune(): int
    {
        $stmt = $this->db->prepare("DELETE FROM telegram_inbox WHERE bot_id = ? AND state = 'done'
            AND finished_at < UTC_TIMESTAMP() - INTERVAL 2 DAY LIMIT 1000");
        $stmt->execute([$this->botId]);
        return $stmt->rowCount();
    }
}
