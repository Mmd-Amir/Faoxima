<?php

namespace Faoxima\Core\Database;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class TextbotTemporary
{
    public const MIN_SECONDS = 60;
    public const MAX_SECONDS = 259200;
    public const MAX_REVISIONS = 60;
    private const CHECK_INTERVAL = 2.0;
    private const RECENT_END_WINDOW = 21600;
    private const PURGE_AFTER = 2592000;

    private static bool $schemaReady = false;
    private static bool $unavailable = false;
    private static bool $busy = false;
    private static float $lastCheck = 0.0;

    public static function ddl(): array
    {
        return [
            'textbot_temp_session' => "CREATE TABLE IF NOT EXISTS textbot_temp_session (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slot TINYINT UNSIGNED NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                admin VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                updated_at INT UNSIGNED NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                duration_seconds INT UNSIGNED NOT NULL,
                current_rev INT UNSIGNED NOT NULL DEFAULT 0,
                max_rev INT UNSIGNED NOT NULL DEFAULT 0,
                ended_at INT UNSIGNED NULL,
                ended_by VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                end_reason VARCHAR(32) NULL,
                end_note TEXT NULL,
                UNIQUE KEY uniq_tts_slot (slot),
                KEY idx_tts_status_exp (status, expires_at),
                KEY idx_tts_ended (ended_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'textbot_temp_item' => "CREATE TABLE IF NOT EXISTS textbot_temp_item (
                session_id BIGINT UNSIGNED NOT NULL,
                id_text VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                perm_exists TINYINT(1) NOT NULL DEFAULT 1,
                perm_text MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                applied_hash CHAR(40) NOT NULL,
                PRIMARY KEY (session_id, id_text)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'textbot_temp_revision' => "CREATE TABLE IF NOT EXISTS textbot_temp_revision (
                session_id BIGINT UNSIGNED NOT NULL,
                rev_no INT UNSIGNED NOT NULL,
                admin VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                key_count INT UNSIGNED NOT NULL DEFAULT 0,
                overlay MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                PRIMARY KEY (session_id, rev_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    public static function ensureSchema(PDO $pdo): bool
    {
        if (self::$schemaReady) {
            return true;
        }
        try {
            $present = 0;
            foreach (array_keys(self::ddl()) as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '" . $table . "'");
                $present += $stmt && $stmt->fetchColumn() ? 1 : 0;
            }
            if ($present < count(self::ddl())) {
                foreach (self::ddl() as $statement) {
                    $pdo->exec($statement);
                }
            }
            self::$schemaReady = true;
            self::$unavailable = false;
            return true;
        } catch (Throwable $e) {
            self::$unavailable = true;
            error_log('[textbot_temp] schema unavailable: ' . $e->getMessage());
            return false;
        }
    }

    public static function peek(PDO $pdo, string $key): ?string
    {
        $stmt = $pdo->prepare('SELECT text FROM textbot WHERE id_text = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return (string) ($row['text'] ?? '');
    }

    public static function putRow(PDO $pdo, string $key, ?string $text): void
    {
        if ($text === null) {
            $pdo->prepare('DELETE FROM textbot WHERE id_text = ?')->execute([$key]);
            return;
        }
        $pdo->prepare('INSERT INTO textbot (id_text, text) VALUES (?, ?) ON DUPLICATE KEY UPDATE text = VALUES(text)')->execute([$key, $text]);
    }

    public static function bustCaches(): void
    {
        try {
            SelectCache::faoxima_bust_bot_selectcache('textbot');
            SelectCache::clearSelectCache('textbot');
        } catch (Throwable $e) {
        }
    }

    public static function durationFromInput($preset, $customMinutes = null): ?int
    {
        $preset = is_string($preset) ? trim($preset) : (is_int($preset) ? (string) $preset : '');
        if ($preset === 'custom') {
            if (!is_numeric($customMinutes)) {
                return null;
            }
            $seconds = (int) round((float) $customMinutes * 60);
        } elseif ($preset !== '' && ctype_digit($preset)) {
            $seconds = (int) $preset;
        } else {
            return null;
        }
        if ($seconds < self::MIN_SECONDS || $seconds > self::MAX_SECONDS) {
            return null;
        }
        return $seconds;
    }

    public static function enforceExpiry(PDO $pdo, bool $force = false): bool
    {
        if (self::$unavailable || self::$busy) {
            return false;
        }
        $clock = microtime(true);
        if (!$force && ($clock - self::$lastCheck) < self::CHECK_INTERVAL) {
            return false;
        }
        self::$lastCheck = $clock;
        if ($pdo->inTransaction()) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('SELECT id FROM textbot_temp_session WHERE slot = 1 AND expires_at <= ? LIMIT 1');
            $stmt->execute([time()]);
            $id = $stmt->fetchColumn();
        } catch (Throwable $e) {
            self::$unavailable = true;
            return false;
        }
        if (!$id) {
            return false;
        }
        return self::expire($pdo, (int) $id);
    }

    public static function purge(PDO $pdo): void
    {
        try {
            $ids = $pdo->prepare('SELECT id FROM textbot_temp_session WHERE slot IS NULL AND ended_at IS NOT NULL AND ended_at < ?');
            $ids->execute([time() - self::PURGE_AFTER]);
            foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $pdo->prepare('DELETE FROM textbot_temp_revision WHERE session_id = ?')->execute([(int) $id]);
                $pdo->prepare('DELETE FROM textbot_temp_item WHERE session_id = ?')->execute([(int) $id]);
                $pdo->prepare('DELETE FROM textbot_temp_session WHERE id = ?')->execute([(int) $id]);
            }
        } catch (Throwable $e) {
        }
    }

    private static function expire(PDO $pdo, int $sessionId): bool
    {
        self::$busy = true;
        try {
            $pdo->beginTransaction();
            $session = self::lockById($pdo, $sessionId);
            if ($session === null || $session['status'] !== 'active' || (int) $session['expires_at'] > time()) {
                $pdo->rollBack();
                return false;
            }
            self::finishLocked($pdo, $session, 'expired', null, 'deadline', true);
            $pdo->commit();
            self::bustCaches();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[textbot_temp] expiry failed: ' . $e->getMessage());
            return false;
        } finally {
            self::$busy = false;
        }
    }

    public static function activeSession(PDO $pdo): ?array
    {
        try {
            $stmt = $pdo->query('SELECT * FROM textbot_temp_session WHERE slot = 1 LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            return null;
        }
        return is_array($row) ? $row : null;
    }

    public static function deletedSnapshots(PDO $pdo): array
    {
        $out = [];
        try {
            $session = self::activeSession($pdo);
            if ($session === null) {
                return $out;
            }
            $stmt = $pdo->prepare('SELECT id_text, perm_text FROM textbot_temp_item WHERE session_id = ? AND perm_exists = 1 AND applied_hash = ?');
            $stmt->execute([(int) $session['id'], self::hashOf(null)]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(string) $row['id_text']] = (string) $row['perm_text'];
            }
        } catch (Throwable $e) {
        }
        return $out;
    }

    public static function status(PDO $pdo, string $admin): array
    {
        $now = time();
        $base = ['available' => !self::$unavailable, 'active' => false, 'server_time' => $now, 'last_ended' => null];
        if (self::$unavailable) {
            return $base;
        }
        $session = self::activeSession($pdo);
        if ($session !== null) {
            $items = [];
            $deleted = [];
            try {
                $stmt = $pdo->prepare('SELECT id_text, applied_hash FROM textbot_temp_item WHERE session_id = ?');
                $stmt->execute([(int) $session['id']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                    $items[] = (string) $item['id_text'];
                    if ((string) $item['applied_hash'] === self::hashOf(null)) {
                        $deleted[] = (string) $item['id_text'];
                    }
                }
            } catch (Throwable $e) {
            }
            $remaining = max(0, (int) $session['expires_at'] - $now);
            $currentRev = (int) $session['current_rev'];
            $maxRev = (int) $session['max_rev'];
            return [
                'available' => true,
                'active' => $remaining > 0,
                'session_id' => (int) $session['id'],
                'owner' => (string) $session['admin'],
                'is_owner' => (string) $session['admin'] === $admin,
                'created_at' => (int) $session['created_at'],
                'updated_at' => (int) $session['updated_at'],
                'expires_at' => (int) $session['expires_at'],
                'duration' => (int) $session['duration_seconds'],
                'remaining' => $remaining,
                'revision' => $currentRev,
                'max_revision' => $maxRev,
                'can_undo' => $currentRev > 0,
                'can_redo' => $currentRev < $maxRev,
                'item_ids' => $items,
                'deleted_ids' => $deleted,
                'server_time' => $now,
                'last_ended' => null,
            ];
        }
        try {
            $stmt = $pdo->prepare('SELECT status, admin, ended_by, ended_at, end_reason, end_note FROM textbot_temp_session WHERE slot IS NULL AND ended_at IS NOT NULL AND ended_at >= ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$now - self::RECENT_END_WINDOW]);
            $last = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($last)) {
                $base['last_ended'] = [
                    'status' => (string) $last['status'],
                    'owner' => (string) $last['admin'],
                    'ended_by' => $last['ended_by'] === null ? null : (string) $last['ended_by'],
                    'ended_at' => (int) $last['ended_at'],
                    'reason' => (string) $last['end_reason'],
                    'skipped' => $last['end_note'] === null || $last['end_note'] === '' ? [] : (json_decode((string) $last['end_note'], true) ?: []),
                ];
            }
        } catch (Throwable $e) {
        }
        return $base;
    }

    public static function saveTemporary(PDO $pdo, string $admin, array $rows, int $duration, ?int $sessionId, ?int $expectedRev): array
    {
        if ($rows === []) {
            return self::fail('nothing_to_save', 'تغییری برای ذخیره موقت وجود ندارد.', 422);
        }
        if ($duration < self::MIN_SECONDS || $duration > self::MAX_SECONDS) {
            return self::fail('invalid_duration', 'مدت انتخاب‌شده برای ذخیره موقت معتبر نیست.', 422);
        }
        if (!self::ensureSchema($pdo)) {
            return self::fail('unavailable', 'جدول‌های ذخیره موقت در دسترس نیستند؛ به‌روزرسانی دیتابیس را اجرا کنید.', 503);
        }
        self::enforceExpiry($pdo, true);
        try {
            $pdo->beginTransaction();
            $now = time();
            $session = self::lockActive($pdo);
            $created = false;
            if ($session === null) {
                if ($sessionId !== null && $sessionId > 0) {
                    $pdo->rollBack();
                    return self::fail('session_gone', 'نشست موقت قبلی پایان یافته است؛ صفحه را تازه کنید.', 409);
                }
                try {
                    $insert = $pdo->prepare("INSERT INTO textbot_temp_session (slot, status, admin, created_at, updated_at, expires_at, duration_seconds, current_rev, max_rev) VALUES (1, 'active', ?, ?, ?, ?, ?, 0, 0)");
                    $insert->execute([$admin, $now, $now, $now + $duration, $duration]);
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    return self::fail('session_conflict', 'یک نشست موقت دیگر هم‌اکنون فعال است؛ صفحه را تازه کنید.', 409);
                }
                $session = self::lockById($pdo, (int) $pdo->lastInsertId());
                $created = true;
            } else {
                if ((string) $session['admin'] !== $admin) {
                    $pdo->rollBack();
                    return self::fail('not_owner', 'نشست موقت فعال متعلق به مدیر دیگری است و فقط او می‌تواند آن را تغییر دهد.', 403);
                }
                if ($sessionId !== (int) $session['id'] || $expectedRev !== (int) $session['current_rev']) {
                    $pdo->rollBack();
                    return self::fail('stale', 'نسخهٔ نشست موقت تغییر کرده است؛ صفحه را تازه کنید.', 409);
                }
            }
            $id = (int) $session['id'];
            $currentRev = (int) $session['current_rev'];
            $newRev = $currentRev + 1;
            if ($newRev > self::MAX_REVISIONS) {
                $pdo->rollBack();
                return self::fail('revision_limit', 'به سقف نسخه‌های ذخیره موقت رسیده‌اید؛ تغییرات را دائمی کنید یا نسخهٔ دائمی را بازگردانید.', 422);
            }
            $overlay = $currentRev > 0 ? self::loadOverlay($pdo, $id, $currentRev) : [];
            $items = self::loadItems($pdo, $id);
            $drift = [];
            foreach ($rows as $key => $text) {
                $key = (string) $key;
                $current = self::peek($pdo, $key);
                if (isset($items[$key])) {
                    if (self::hashOf($current) !== $items[$key]) {
                        $drift[] = $key;
                    }
                    continue;
                }
                $pdo->prepare('INSERT INTO textbot_temp_item (session_id, id_text, perm_exists, perm_text, applied_hash) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$id, $key, $current === null ? 0 : 1, $current, self::hashOf($current)]);
                $items[$key] = self::hashOf($current);
            }
            if ($drift) {
                $pdo->rollBack();
                return self::fail('drift', 'برخی متن‌ها هم‌زمان توسط مدیر دیگری تغییر کرده‌اند و ذخیره نشدند.', 409, ['keys' => $drift]);
            }
            foreach ($rows as $key => $text) {
                $key = (string) $key;
                self::putRow($pdo, $key, $text);
                $pdo->prepare('UPDATE textbot_temp_item SET applied_hash = ? WHERE session_id = ? AND id_text = ?')->execute([self::hashOf($text), $id, $key]);
                $overlay[$key] = $text;
            }
            $pdo->prepare('DELETE FROM textbot_temp_revision WHERE session_id = ? AND rev_no > ?')->execute([$id, $currentRev]);
            $pdo->prepare('INSERT INTO textbot_temp_revision (session_id, rev_no, admin, created_at, key_count, overlay) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $newRev, $admin, $now, count($rows), json_encode($overlay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT)]);
            $pdo->prepare('UPDATE textbot_temp_session SET current_rev = ?, max_rev = ?, expires_at = ?, duration_seconds = ?, updated_at = ? WHERE id = ?')
                ->execute([$newRev, $newRev, $now + $duration, $duration, $now, $id]);
            $pdo->commit();
            self::bustCaches();
            return ['ok' => true, 'affected' => array_map('strval', array_keys($rows)), 'created' => $created, 'session_id' => $id, 'revision' => $newRev];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[textbot_temp] save failed: ' . $e->getMessage());
            return self::fail('failed', 'ذخیره موقت انجام نشد؛ نسخهٔ قبلی حفظ شد.', 500);
        }
    }

    public static function move(PDO $pdo, string $admin, int $sessionId, int $expectedRev, int $delta): array
    {
        if (!self::ensureSchema($pdo)) {
            return self::fail('unavailable', 'جدول‌های ذخیره موقت در دسترس نیستند.', 503);
        }
        self::enforceExpiry($pdo, true);
        try {
            $pdo->beginTransaction();
            $session = self::lockActive($pdo);
            $guard = self::guardOwned($session, $admin, $sessionId, $expectedRev);
            if ($guard !== null) {
                $pdo->rollBack();
                return $guard;
            }
            $id = (int) $session['id'];
            $target = (int) $session['current_rev'] + ($delta < 0 ? -1 : 1);
            if ($target < 0 || $target > (int) $session['max_rev']) {
                $pdo->rollBack();
                return self::fail('no_history', $delta < 0 ? 'نسخهٔ قبلی برای واگرد وجود ندارد.' : 'نسخهٔ بعدی برای انجام دوباره وجود ندارد.', 409);
            }
            $overlay = $target > 0 ? self::loadOverlay($pdo, $id, $target) : [];
            $stmt = $pdo->prepare('SELECT id_text, perm_exists, perm_text, applied_hash FROM textbot_temp_item WHERE session_id = ?');
            $stmt->execute([$id]);
            $affected = [];
            $drift = [];
            $plan = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $key = (string) $item['id_text'];
                $desired = array_key_exists($key, $overlay)
                    ? ($overlay[$key] === null ? null : (string) $overlay[$key])
                    : ((int) $item['perm_exists'] === 1 ? (string) $item['perm_text'] : null);
                if (self::hashOf($desired) === (string) $item['applied_hash']) {
                    continue;
                }
                if (self::hashOf(self::peek($pdo, $key)) !== (string) $item['applied_hash']) {
                    $drift[] = $key;
                    continue;
                }
                $plan[$key] = $desired;
            }
            if ($drift) {
                $pdo->rollBack();
                return self::fail('drift', 'برخی متن‌ها هم‌زمان توسط مدیر دیگری تغییر کرده‌اند؛ واگرد/انجام دوباره انجام نشد.', 409, ['keys' => $drift]);
            }
            foreach ($plan as $key => $desired) {
                self::putRow($pdo, (string) $key, $desired);
                $pdo->prepare('UPDATE textbot_temp_item SET applied_hash = ? WHERE session_id = ? AND id_text = ?')->execute([self::hashOf($desired), $id, (string) $key]);
                $affected[] = (string) $key;
            }
            $pdo->prepare('UPDATE textbot_temp_session SET current_rev = ?, updated_at = ? WHERE id = ?')->execute([$target, time(), $id]);
            $pdo->commit();
            self::bustCaches();
            return ['ok' => true, 'affected' => $affected, 'session_id' => $id, 'revision' => $target];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[textbot_temp] move failed: ' . $e->getMessage());
            return self::fail('failed', 'عملیات انجام نشد؛ وضعیت قبلی حفظ شد.', 500);
        }
    }

    public static function restore(PDO $pdo, string $admin, int $sessionId, int $expectedRev): array
    {
        if (!self::ensureSchema($pdo)) {
            return self::fail('unavailable', 'جدول‌های ذخیره موقت در دسترس نیستند.', 503);
        }
        self::enforceExpiry($pdo, true);
        try {
            $pdo->beginTransaction();
            $session = self::lockActive($pdo);
            $guard = self::guardOwned($session, $admin, $sessionId, $expectedRev);
            if ($guard !== null) {
                $pdo->rollBack();
                return $guard;
            }
            $before = self::itemKeys($pdo, (int) $session['id']);
            $skipped = self::finishLocked($pdo, $session, 'restored', $admin, 'manual_restore', true);
            $pdo->commit();
            self::bustCaches();
            return ['ok' => true, 'affected' => $before, 'skipped' => $skipped];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[textbot_temp] restore failed: ' . $e->getMessage());
            return self::fail('failed', 'بازگردانی نسخهٔ دائمی انجام نشد؛ وضعیت قبلی حفظ شد.', 500);
        }
    }

    public static function commit(PDO $pdo, string $admin, int $sessionId, int $expectedRev, array $rows = []): array
    {
        if (!self::ensureSchema($pdo)) {
            return self::fail('unavailable', 'جدول‌های ذخیره موقت در دسترس نیستند.', 503);
        }
        self::enforceExpiry($pdo, true);
        try {
            $pdo->beginTransaction();
            $session = self::lockActive($pdo);
            $guard = self::guardOwned($session, $admin, $sessionId, $expectedRev);
            if ($guard !== null) {
                $pdo->rollBack();
                return $guard;
            }
            $id = (int) $session['id'];
            $items = self::loadItems($pdo, $id);
            $drift = [];
            foreach ($rows as $key => $text) {
                $key = (string) $key;
                if (isset($items[$key]) && self::hashOf(self::peek($pdo, $key)) !== $items[$key]) {
                    $drift[] = $key;
                }
            }
            if ($drift) {
                $pdo->rollBack();
                return self::fail('drift', 'برخی متن‌ها هم‌زمان توسط مدیر دیگری تغییر کرده‌اند و ثبت دائمی انجام نشد.', 409, ['keys' => $drift]);
            }
            foreach ($rows as $key => $text) {
                self::putRow($pdo, (string) $key, $text);
            }
            self::finishLocked($pdo, $session, 'committed', $admin, 'manual_commit', false);
            $pdo->commit();
            self::bustCaches();
            return ['ok' => true, 'affected' => array_map('strval', array_keys($rows))];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[textbot_temp] commit failed: ' . $e->getMessage());
            return self::fail('failed', 'ثبت دائمی انجام نشد؛ نسخهٔ موقت حفظ شد.', 500);
        }
    }

    private static function guardOwned(?array $session, string $admin, int $sessionId, int $expectedRev): ?array
    {
        if ($session === null || (int) $session['id'] !== $sessionId) {
            return self::fail('session_gone', 'نشست موقت پایان یافته است؛ صفحه را تازه کنید.', 409);
        }
        if ((string) $session['admin'] !== $admin) {
            return self::fail('not_owner', 'نشست موقت فعال متعلق به مدیر دیگری است و فقط او می‌تواند آن را تغییر دهد.', 403);
        }
        if ((int) $session['current_rev'] !== $expectedRev) {
            return self::fail('stale', 'نسخهٔ نشست موقت تغییر کرده است؛ صفحه را تازه کنید.', 409);
        }
        return null;
    }

    private static function finishLocked(PDO $pdo, array $session, string $status, ?string $actor, string $reason, bool $restore): array
    {
        $id = (int) $session['id'];
        $skipped = [];
        if ($restore) {
            $stmt = $pdo->prepare('SELECT id_text, perm_exists, perm_text, applied_hash FROM textbot_temp_item WHERE session_id = ?');
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $key = (string) $item['id_text'];
                if (self::hashOf(self::peek($pdo, $key)) !== (string) $item['applied_hash']) {
                    $skipped[] = $key;
                    continue;
                }
                self::putRow($pdo, $key, (int) $item['perm_exists'] === 1 ? (string) $item['perm_text'] : null);
            }
        }
        $pdo->prepare('UPDATE textbot_temp_session SET status = ?, slot = NULL, ended_at = ?, ended_by = ?, end_reason = ?, end_note = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, time(), $actor, $reason, $skipped ? json_encode($skipped, JSON_UNESCAPED_UNICODE) : null, time(), $id]);
        $pdo->prepare('DELETE FROM textbot_temp_item WHERE session_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE textbot_temp_revision SET overlay = NULL WHERE session_id = ?')->execute([$id]);
        return $skipped;
    }

    private static function lockActive(PDO $pdo): ?array
    {
        $stmt = $pdo->query('SELECT * FROM textbot_temp_session WHERE slot = 1 LIMIT 1 FOR UPDATE');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return is_array($row) ? $row : null;
    }

    private static function lockById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM textbot_temp_session WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function loadItems(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare('SELECT id_text, applied_hash FROM textbot_temp_item WHERE session_id = ?');
        $stmt->execute([$sessionId]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[(string) $row['id_text']] = (string) $row['applied_hash'];
        }
        return $items;
    }

    private static function itemKeys(PDO $pdo, int $sessionId): array
    {
        return array_map('strval', array_keys(self::loadItems($pdo, $sessionId)));
    }

    private static function loadOverlay(PDO $pdo, int $sessionId, int $rev): array
    {
        $stmt = $pdo->prepare('SELECT overlay FROM textbot_temp_revision WHERE session_id = ? AND rev_no = ?');
        $stmt->execute([$sessionId, $rev]);
        $raw = $stmt->fetchColumn();
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new RuntimeException('temporary revision overlay is unavailable');
        }
        $overlay = [];
        foreach ($decoded as $key => $value) {
            $overlay[(string) $key] = $value === null ? null : (string) $value;
        }
        return $overlay;
    }

    private static function hashOf(?string $text): string
    {
        return $text === null ? sha1('N:') : sha1('T:' . $text);
    }

    private static function fail(string $code, string $message, int $http, array $extra = []): array
    {
        return ['ok' => false, 'error' => $code, 'message' => $message, 'http' => $http] + $extra;
    }
}
