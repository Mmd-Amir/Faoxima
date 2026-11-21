<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use PDO;
use Throwable;

final class NotificationHandler extends \Faoxima\Api\Handlers\BaseHandler
{

    public $mode = 'info';

    private const ALLOWED_DURATIONS = [24, 48];

    private const MAX_CUSTOM_MINUTES = 43200; // 30 days

    private const RECENT_LIMIT = 20;

    /** Columns this handler actually reads/writes on the `notification` table. Checked
     * once per request against INFORMATION_SCHEMA so a table created by an older version
     * of table.php (missing a column this code assumes exists) is caught explicitly
     * instead of surfacing as a cryptic SQL error deep inside a query. */
    private const REQUIRED_COLUMNS = ['id', 'message', 'duration_hours', 'created_at', 'expires_at'];

    /** Reads INFORMATION_SCHEMA once per request and returns which of REQUIRED_COLUMNS
     * actually exist on the live `notification` table — the only reliable way to tell
     * "this table predates the current code" apart from "something else is wrong",
     * instead of inferring it from a downstream SQL error's message text. */
    private static function checkSchema(): array
    {
        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
            );
            $stmt->execute([':t' => 'notification']);
            $existing = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'COLUMN_NAME');
            $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $existing));
            return ['existing' => $existing, 'missing' => $missing];
        } catch (Throwable $e) {
            return ['existing' => null, 'missing' => null, 'schema_check_error' => $e->getMessage()];
        }
    }

    /** Backward-compat self-heal: if a required column is missing (table created by an
     * older version of table.php, before this column existed), add it with a safe default
     * instead of failing every request against this handler forever. created_at defaults
     * to 0 for any pre-existing row — those rows simply sort/format as "epoch" until
     * naturally pruned out by RECENT_LIMIT, rather than breaking the whole feature. */
    private static function healSchema(array $missing): void
    {
        if (empty($missing)) {
            return;
        }
        $pdo = \Faoxima\Api\Runtime\Db::pdo();
        foreach ($missing as $col) {
            try {
                if ($col === 'created_at') {
                    $pdo->exec('ALTER TABLE notification ADD COLUMN created_at INT(11) NOT NULL DEFAULT 0');
                } elseif ($col === 'duration_hours') {
                    $pdo->exec('ALTER TABLE notification ADD COLUMN duration_hours TINYINT UNSIGNED NOT NULL DEFAULT 24');
                } elseif ($col === 'expires_at') {
                    $pdo->exec('ALTER TABLE notification ADD COLUMN expires_at INT(11) NOT NULL DEFAULT 0');
                }
            } catch (Throwable $e) {
                // schema heal failed; request continues, downstream query will surface the real error
            }
        }
    }

    public function handle(): void
    {
        $schema = self::checkSchema();

        if (!empty($schema['missing'])) {
            self::healSchema($schema['missing']);
        }

        switch ($this->mode) {
            case 'info':
                $this->handleInfo();
                return;
            case 'recent':
                $this->handleRecent();
                return;
            case 'save':
                $this->handleSave();
                return;
            case 'delete':
                $this->handleDelete();
                return;
            case 'dismiss':
                $this->handleDismiss();
                return;
        }

        \Faoxima\Api\Runtime\Response::badRequest('Notification mode invalid');
    }


    private static function fetchActive(): ?array
    {
        $pdo = \Faoxima\Api\Runtime\Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM notification WHERE expires_at > :now ORDER BY id DESC LIMIT 1');
        $stmt->execute([':now' => time()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }


    private function handleInfo(): void
    {
        $this->requireMethod('GET');

        $row = self::fetchActive();
        if ($row === null) {
            \Faoxima\Api\Runtime\Response::ok(['notification' => null]);
        }

        $lastSeen = (int)($this->user['last_seen_notification_id'] ?? 0);
        $seen = $lastSeen >= (int)$row['id'];

        \Faoxima\Api\Runtime\Response::ok([
            'notification' => [
                'id'         => (int)$row['id'],
                'message'    => (string)$row['message'],
                'expires_at' => (int)$row['expires_at'],
                'seen'       => $seen,
            ],
        ]);
    }


    /** Returns the most recent notifications (not just the single still-active one), each
     * flagged individually as seen/unseen, so the bell icon can show a real history instead
     * of only ever the latest message. */
    private function handleRecent(): void
    {
        $this->requireMethod('GET');

        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare('SELECT * FROM notification ORDER BY id DESC LIMIT :lim');
            $stmt->bindValue(':lim', self::RECENT_LIMIT, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'notification recent query failed');
            \Faoxima\Api\Runtime\Response::fail(500, 'cannot load notifications');
            return;
        }

        $lastSeen = (int)($this->user['last_seen_notification_id'] ?? 0);

        // Defensive against pre-existing rows/schemas that predate a given column (the
        // schema check + healSchema() above should already have fixed this at the table
        // level, but ?? here means a row missing a key degrades to a safe default instead
        // of a PHP warning or a fatal "undefined array key" under strict error reporting).
        $notifications = array_map(static function (array $row) use ($lastSeen): array {
            return [
                'id'         => (int)($row['id'] ?? 0),
                'message'    => (string)($row['message'] ?? ''),
                'created_at' => (int)($row['created_at'] ?? 0),
                'expires_at' => (int)($row['expires_at'] ?? 0),
                'seen'       => $lastSeen >= (int)($row['id'] ?? 0),
            ];
        }, $rows);

        \Faoxima\Api\Runtime\Response::ok(['notifications' => $notifications]);
    }


    private function handleSave(): void
    {
        $this->requireMethod('POST');

        if (!$this->userIsAdmin()) {
            \Faoxima\Api\Runtime\Response::fail(403, 'admin only');
        }

        $message = trim(\Faoxima\Api\Runtime\Input::string($this->data, 'message'));
        $duration = \Faoxima\Api\Runtime\Input::int($this->data, 'duration', 0);
        $customMinutes = \Faoxima\Api\Runtime\Input::int($this->data, 'duration_minutes', 0);

        if ($message === '') {
            \Faoxima\Api\Runtime\Response::badRequest('message is required');
        }
        if (mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 500);
        }

        if ($customMinutes > 0) {
            if ($customMinutes > self::MAX_CUSTOM_MINUTES) {
                \Faoxima\Api\Runtime\Response::badRequest('duration_minutes is too large');
            }
            $durationSeconds = $customMinutes * 60;
            // duration_hours is a TINYINT UNSIGNED display column; expires_at (computed from
            // the exact minute value) remains the authoritative source for actual expiry.
            $durationHoursColumn = min(255, (int)max(1, (int)round($customMinutes / 60)));
        } else {
            if (!in_array($duration, self::ALLOWED_DURATIONS, true)) {
                \Faoxima\Api\Runtime\Response::badRequest('duration must be 24, 48, or a positive duration_minutes');
            }
            $durationSeconds = $duration * 3600;
            $durationHoursColumn = $duration;
        }

        $now = time();
        $expiresAt = $now + $durationSeconds;

        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();

            $stmt = $pdo->prepare(
                'INSERT INTO notification (message, duration_hours, created_at, expires_at) VALUES (:m, :d, :c, :e)'
            );
            $stmt->execute([':m' => $message, ':d' => $durationHoursColumn, ':c' => $now, ':e' => $expiresAt]);
            $newId = (int)$pdo->lastInsertId();

            // Pruning only ever removes rows OUTSIDE the most recent RECENT_LIMIT.
            $pruneStmt = $pdo->prepare(
                'DELETE FROM notification WHERE id NOT IN (SELECT id FROM (SELECT id FROM notification ORDER BY id DESC LIMIT ' . self::RECENT_LIMIT . ') t)'
            );
            $pruneStmt->execute();

            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('notification');
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'notification save failed');
            \Faoxima\Api\Runtime\Response::fail(500, 'cannot save notification');
            return;
        }

        \Faoxima\Api\Runtime\Response::ok([
            'notification' => [
                'id'         => $newId,
                'message'    => $message,
                'expires_at' => $expiresAt,
                'seen'       => false,
            ],
            'message' => 'اعلان با موفقیت ارسال شد',
        ]);
    }


    /** Admin-only: deletes a single notification from the history the recent-list/bell
     * pulls from. Doesn't touch last_seen_notification_id — a deleted notification simply
     * stops appearing in anyone's list; there's nothing to "un-see". */
    private function handleDelete(): void
    {
        $this->requireMethod('POST');

        if (!$this->userIsAdmin()) {
            \Faoxima\Api\Runtime\Response::fail(403, 'admin only');
        }

        $id = \Faoxima\Api\Runtime\Input::int($this->data, 'id', 0);
        if ($id <= 0) {
            \Faoxima\Api\Runtime\Response::badRequest('id is required');
        }

        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare('DELETE FROM notification WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $deleted = $stmt->rowCount() > 0;

            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('notification');
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'notification delete failed');
            \Faoxima\Api\Runtime\Response::fail(500, 'cannot delete notification');
            return;
        }

        if (!$deleted) {
            \Faoxima\Api\Runtime\Response::fail(404, 'notification not found');
        }

        \Faoxima\Api\Runtime\Response::ok(['status' => true]);
    }


    private function handleDismiss(): void
    {
        $this->requireMethod('POST');

        $id = \Faoxima\Api\Runtime\Input::int($this->data, 'id', 0);
        if ($id <= 0) {
            \Faoxima\Api\Runtime\Response::badRequest('id is required');
        }

        // Only ever moves forward: viewing an older notification out of the recent-history
        // list must not un-mark newer ones the user already saw as unseen again.
        $lastSeen = (int)($this->user['last_seen_notification_id'] ?? 0);
        if ($id > $lastSeen) {
            \Faoxima\Core\Database\Db::update('user', 'last_seen_notification_id', $id, 'id', $this->user['id'] ?? '');
        }

        \Faoxima\Api\Runtime\Response::ok(['status' => true]);
    }
}
