<?php

namespace Faoxima\Telegram\Presentation;

use PDO;
use Throwable;

final class Decor
{
    public static function rx_decor_table_ready(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return $ready = false;
        }
        try {
            $pdo->query('SELECT 1 FROM ui_decor LIMIT 0');
            return $ready = true;
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ui_decor (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_user BIGINT NOT NULL,
                message_id BIGINT NOT NULL,
                kind VARCHAR(12) NOT NULL,
                screen VARCHAR(40) NOT NULL DEFAULT '',
                created_at INT NOT NULL,
                UNIQUE KEY uq_user_message (id_user, message_id),
                KEY idx_user_id (id_user, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return $ready = true;
        } catch (Throwable $e) {
            error_log('[ui_decor] table unavailable: ' . $e->getMessage());
            return $ready = false;
        }
    }

    public static function rx_decor_eligible($chatId): bool
    {
        if (!function_exists('rx_richModeOn') || !rx_richModeOn() || !method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_chatIsUserUi')) {
            return false;
        }
        $chatId = trim((string) $chatId);
        if ($chatId === '' || !ctype_digit($chatId) || empty($GLOBALS['update']) || !isset($GLOBALS['from_id'])) {
            return false;
        }
        return $chatId === trim((string) $GLOBALS['from_id']) && \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_chatIsUserUi($chatId);
    }

    public static function rx_decor_boundary($chatId): int
    {
        static $boundaries = [];
        $key = (string) $chatId;
        if (isset($boundaries[$key])) {
            return $boundaries[$key];
        }
        global $pdo;
        $max = 0;
        try {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM ui_decor WHERE id_user = ?');
            $stmt->execute([$key]);
            $max = (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $max = 0;
        }
        return $boundaries[$key] = $max;
    }

    public static function rx_decor_record($chatId, $result, string $kind, string $screen = ''): void
    {
        try {
            if (!is_array($result) || empty($result['ok']) || !self::rx_decor_eligible($chatId) || !self::rx_decor_table_ready()) {
                return;
            }
            $ids = [];
            $primary = (int) ($result['result']['message_id'] ?? 0);
            if ($primary > 0) {
                $ids[$primary] = $kind;
            }
            $control = (int) ($result['rx_control_message_id'] ?? 0);
            if ($control > 0) {
                $ids[$control] = 'screen';
            }
            if (!$ids) {
                return;
            }
            self::rx_decor_boundary($chatId);
            global $pdo;
            $stmt = $pdo->prepare('REPLACE INTO ui_decor (id_user, message_id, kind, screen, created_at) VALUES (?, ?, ?, ?, ?)');
            foreach ($ids as $messageId => $rowKind) {
                $stmt->execute([(string) $chatId, $messageId, $rowKind, substr($screen, 0, 40), time()]);
            }
            $trim = $pdo->prepare('DELETE FROM ui_decor WHERE id_user = ? AND id <= (SELECT cutoff FROM (SELECT id AS cutoff FROM ui_decor WHERE id_user = ? ORDER BY id DESC LIMIT 1 OFFSET 39) t)');
            $trim->execute([(string) $chatId, (string) $chatId]);
        } catch (Throwable $e) {
            error_log('[ui_decor] record failed: ' . $e->getMessage());
        }
    }

    public static function rx_decor_cleanup($chatId, int $keepMessageId = 0): void
    {
        static $done = [];
        try {
            $key = (string) $chatId;
            if (isset($done[$key]) || !self::rx_decor_eligible($chatId) || !self::rx_decor_table_ready()) {
                return;
            }
            $done[$key] = true;
            global $pdo;
            $boundary = self::rx_decor_boundary($chatId);
            if ($boundary > 0) {
                $select = $pdo->prepare('SELECT id, message_id FROM ui_decor WHERE id_user = ? AND id <= ? ORDER BY id');
                $select->execute([$key, $boundary]);
                $rows = $select->fetchAll(PDO::FETCH_ASSOC);
                $claim = $pdo->prepare('DELETE FROM ui_decor WHERE id = ?');
                foreach ($rows as $row) {
                    $claim->execute([(int) $row['id']]);
                    if ($claim->rowCount() !== 1 || (int) $row['message_id'] === $keepMessageId) {
                        continue;
                    }
                    try {
                        deletemessage($chatId, (int) $row['message_id']);
                    } catch (Throwable $e) {
                    }
                }
            }
            $prune = $pdo->prepare('DELETE FROM ui_decor WHERE created_at < ?');
            $prune->execute([time() - 259200]);
        } catch (Throwable $e) {
            error_log('[ui_decor] cleanup failed: ' . $e->getMessage());
        }
    }

    public static function rx_decor_markup_has_buttons($datas): bool
    {
        if (isset($datas['rich_message']) && is_string($datas['rich_message']) && strpos($datas['rich_message'], 'tg-button') !== false) {
            return true;
        }
        $markup = $datas['reply_markup'] ?? null;
        if (is_string($markup) && $markup !== '') {
            $markup = json_decode($markup, true);
        }
        if (!is_array($markup) || empty($markup['inline_keyboard']) || !is_array($markup['inline_keyboard'])) {
            return false;
        }
        foreach ($markup['inline_keyboard'] as $row) {
            if (is_array($row) && $row) {
                return true;
            }
        }
        return false;
    }

    public static function rx_decor_after_send($method, $datas, $result): void
    {
        try {
            if (!is_array($result) || empty($result['ok']) || !is_array($datas)) {
                return;
            }
            $m = strtolower((string) $method);
            $chatId = $datas['chat_id'] ?? null;
            if ($chatId === null || !self::rx_decor_eligible($chatId)) {
                return;
            }
            $keep = 0;
            if ($m === 'editmessagetext') {
                $callbackMessageId = (int) ($GLOBALS['update']['callback_query']['message']['message_id'] ?? 0);
                $target = (int) ($datas['message_id'] ?? 0);
                if ($callbackMessageId <= 0 || $callbackMessageId !== $target) {
                    return;
                }
                $keep = $target;
            } elseif (in_array($m, ['sendmessage', 'sendrichmessage', 'sendphoto', 'sendvideo', 'senddocument', 'sendanimation'], true)) {
                if (!self::rx_decor_markup_has_buttons($datas)) {
                    return;
                }
                $keep = (int) ($result['result']['message_id'] ?? 0);
            } else {
                return;
            }
            self::rx_decor_cleanup($chatId, $keep);
        } catch (Throwable $e) {
            error_log('[ui_decor] after_send failed: ' . $e->getMessage());
        }
    }
}
