<?php

namespace Faoxima\Wallet\Gifts;

use PDO;
use Throwable;
use RuntimeException;

final class Gifts
{
    public static function giftRedeemAtomic($runtimePdo, $code, $userId)
    {
        $pdo = $runtimePdo;
        $code = trim((string)$code);
        $userId = trim((string)$userId);
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'db_unavailable'];
        }
        if ($code === '' || $userId === '') {
            return ['ok' => false, 'reason' => 'invalid_code'];
        }
        $own = !$pdo->inTransaction();
        $savepoint = 'rx_gift_' . bin2hex(random_bytes(4));
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :c ORDER BY id ASC LIMIT 1 FOR UPDATE");
            $stmt->execute([':c' => $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('not_found');
            }
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($status !== '' && $status !== 'active') {
                throw new RuntimeException('invalid_status');
            }
            $target = trim((string)($row['target_user'] ?? ''));
            if ($target !== '' && $target !== $userId) {
                throw new RuntimeException('invalid_target');
            }
            $expire = (int)($row['expire_at'] ?? 0);
            if ($expire !== 0 && time() >= $expire) {
                throw new RuntimeException('expired');
            }
            $amount = (int) round((float)($row['price'] ?? 0));
            if ($amount <= 0) {
                throw new RuntimeException('invalid_amount');
            }
            $limitUse = (int)($row['limituse'] ?? 0);
            $limitUsed = (int)($row['limitused'] ?? 0);
            if ($limitUse > 0 && $limitUsed >= $limitUse) {
                throw new RuntimeException('capacity');
            }
            $upd = $pdo->prepare(
                "UPDATE Discount SET limitused = CAST(COALESCE(NULLIF(limitused, ''), '0') AS SIGNED) + 1
                  WHERE code = :c
                    AND (CAST(COALESCE(NULLIF(limituse, ''), '0') AS SIGNED) = 0
                         OR CAST(COALESCE(NULLIF(limitused, ''), '0') AS SIGNED) < CAST(COALESCE(NULLIF(limituse, ''), '0') AS SIGNED))"
            );
            $upd->execute([':c' => $code]);
            if ($upd->rowCount() < 1) {
                throw new RuntimeException('capacity');
            }
            $credit = $pdo->prepare("UPDATE user SET Balance = Balance + :a WHERE id = :u");
            $credit->execute([':a' => $amount, ':u' => $userId]);
            if ($credit->rowCount() < 1) {
                throw new RuntimeException('user_missing');
            }
            try {
                $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code, kind, consumed_at) VALUES (?, ?, ?, ?)")
                    ->execute([$userId, $code, 'gift', (string)time()]);
            } catch (Throwable $e) {
                try {
                    $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (?, ?)")
                        ->execute([$userId, $code]);
                } catch (Throwable $e2) {
                }
            }
            if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                \Faoxima\Wallet\Ledger\Ledger::record($runtimePdo, $userId, 'credit', $amount, 'gift_code', 'استفاده از کد هدیه', null, 'Discount', $code);
            }
            $bal = $pdo->prepare("SELECT Balance FROM user WHERE id = :u");
            $bal->execute([':u' => $userId]);
            $newBalance = (float)$bal->fetchColumn();
            if ($own) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $userId);
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Discount', 'code', $code);
            }
            return ['ok' => true, 'amount' => $amount, 'new_balance' => $newBalance];
        } catch (Throwable $e) {
            if ($own) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $rollbackError) {
                    }
                }
            } else {
                try {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                } catch (Throwable $rollbackError) {
                }
            }
            $reason = $e->getMessage();
            if (!in_array($reason, ['not_found', 'invalid_status', 'invalid_target', 'expired', 'invalid_amount', 'capacity', 'user_missing'], true)) {
                error_log('rx_gift_redeem_atomic failed: ' . $reason);
                $reason = 'db_error';
            }
            return ['ok' => false, 'reason' => $reason];
        }
    }

    public static function statePath(string $batchId): string
    {
        $root = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : getcwd();
        return rtrim((string) $root, '/\\') . '/cronbot/bulk_gift_' . preg_replace('/[^a-f0-9]/', '', $batchId) . '.json';
    }

    public static function save(string $path, array $state): bool
    {
        $tmp = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            return false;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    public static function chunk($runtimePdo, PDO $pdo, string $batchId, int $amount, array $chunk, string $description): array
    {
        $out = ['credited' => 0, 'already' => 0, 'missing' => 0, 'failed' => []];
        if (empty($chunk)) {
            return $out;
        }
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        try {
            $pdo->beginTransaction();
            $doneStmt = $pdo->prepare("SELECT id_user FROM wallet_ledger WHERE id_order = ? AND category = 'admin_gift' AND id_user IN ($ph)");
            $doneStmt->execute(array_merge([$batchId], $chunk));
            $already = array_flip(array_map('strval', $doneStmt->fetchAll(PDO::FETCH_COLUMN)));
            $existStmt = $pdo->prepare("SELECT id FROM user WHERE id IN ($ph)");
            $existStmt->execute($chunk);
            $existing = array_flip(array_map('strval', $existStmt->fetchAll(PDO::FETCH_COLUMN)));
            $todo = [];
            foreach ($chunk as $id) {
                if (isset($already[$id])) {
                    $out['already']++;
                } elseif (!isset($existing[$id])) {
                    $out['missing']++;
                } else {
                    $todo[] = $id;
                }
            }
            if (!empty($todo)) {
                $ph2 = implode(',', array_fill(0, count($todo), '?'));
                $up = $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id IN ($ph2)");
                $up->execute(array_merge([$amount], $todo));
                $ins = $pdo->prepare(
                    "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id) "
                    . "SELECT id, 'credit', ?, Balance, 'admin_gift', ?, ?, 'bulk_gift', ? FROM user WHERE id IN ($ph2)"
                );
                $ins->execute(array_merge([$amount, $description, $batchId, $batchId], $todo));
                if ($up->rowCount() !== count($todo) || $ins->rowCount() !== count($todo)) {
                    throw new RuntimeException('bulk gift row count mismatch');
                }
            }
            $pdo->commit();
            $out['credited'] = count($todo);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('rx_log_event')) {
                rx_log_event('BULK_GIFT_CHUNK_FALLBACK', $e->getMessage(), ['batch' => $batchId, 'size' => count($chunk)]);
            }
            $out = ['credited' => 0, 'already' => 0, 'missing' => 0, 'failed' => []];
            foreach ($chunk as $id) {
                try {
                    $pdo->beginTransaction();
                    $one = $pdo->prepare("SELECT COUNT(*) FROM wallet_ledger WHERE id_order = ? AND category = 'admin_gift' AND id_user = ?");
                    $one->execute([$batchId, $id]);
                    if ((int) $one->fetchColumn() > 0) {
                        $pdo->rollBack();
                        $out['already']++;
                        continue;
                    }
                    if (!\Faoxima\Wallet\Balance\Balances::credit($runtimePdo, $id, $amount)) {
                        $pdo->rollBack();
                        $exists = $pdo->prepare("SELECT COUNT(*) FROM user WHERE id = ?");
                        $exists->execute([$id]);
                        if ((int) $exists->fetchColumn() === 0) {
                            $out['missing']++;
                        } else {
                            $out['failed'][$id] = 'credit failed';
                        }
                        continue;
                    }
                    if (!\Faoxima\Wallet\Ledger\Ledger::record($runtimePdo, $id, 'credit', $amount, 'admin_gift', $description, $batchId, 'bulk_gift', $batchId)) {
                        throw new RuntimeException('ledger failed');
                    }
                    $pdo->commit();
                    $out['credited']++;
                } catch (Throwable $inner) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $out['failed'][$id] = $inner->getMessage();
                }
            }
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
        }
        return $out;
    }

    public static function run($runtimePdo, string $batchId, float $timeBudget = 20.0, int $chunkSize = 500): array
    {
        $pdo = $runtimePdo;
        $path = self::statePath($batchId);
        $state = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
        if (!is_array($state) || !isset($state['ids']) || !is_array($state['ids'])) {
            return ['status' => 'missing', 'batch' => $batchId];
        }
        if (($state['status'] ?? '') === 'done') {
            return $state;
        }
        if (!($pdo instanceof PDO)) {
            $state['status'] = 'partial';
            return $state;
        }
        $lock = @fopen($path . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                fclose($lock);
            }
            $state['status'] = 'busy';
            return $state;
        }
        $state = json_decode((string) @file_get_contents($path), true);
        if (!is_array($state) || ($state['status'] ?? '') === 'done') {
            flock($lock, LOCK_UN);
            fclose($lock);
            return is_array($state) ? $state : ['status' => 'missing', 'batch' => $batchId];
        }
        $amount = (int) ($state['amount'] ?? 0);
        $ids = array_map('strval', $state['ids']);
        $total = count($ids);
        $pos = (int) ($state['pos'] ?? 0);
        foreach (['credited', 'already', 'missing'] as $k) {
            $state[$k] = (int) ($state[$k] ?? 0);
        }
        $state['failed'] = is_array($state['failed'] ?? null) ? $state['failed'] : [];
        $description = 'شارژ همگانی توسط ادمین';
        $started = microtime(true);
        $state['status'] = 'running';
        self::save($path, $state);
        while ($pos < $total && $amount > 0) {
            if ((microtime(true) - $started) > $timeBudget) {
                break;
            }
            $chunk = array_slice($ids, $pos, max(1, $chunkSize));
            $res = self::chunk($runtimePdo, $pdo, $batchId, $amount, $chunk, $description);
            $state['credited'] += $res['credited'];
            $state['already'] += $res['already'];
            $state['missing'] += $res['missing'];
            foreach ($res['failed'] as $fid => $reason) {
                $state['failed'][(string) $fid] = (string) $reason;
            }
            $pos += count($chunk);
            $state['pos'] = $pos;
            self::save($path, $state);
            if (function_exists('rx_log_event')) {
                rx_log_event('BULK_GIFT_CHUNK', 'chunk processed', [
                    'batch' => $batchId,
                    'pos' => $pos,
                    'total' => $total,
                    'credited' => $res['credited'],
                    'already' => $res['already'],
                    'missing' => $res['missing'],
                    'failed_ids' => array_keys($res['failed']),
                ]);
            }
        }
        $state['status'] = $pos >= $total ? 'done' : 'partial';
        if ($state['status'] === 'done') {
            $state['finished_at'] = time();
        }
        self::save($path, $state);
        flock($lock, LOCK_UN);
        fclose($lock);
        return $state;
    }

    public static function creditedIds($runtimePdo, string $batchId): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $stmt = $pdo->prepare("SELECT id_user FROM wallet_ledger WHERE id_order = :b AND category = 'admin_gift'");
        $stmt->execute([':b' => $batchId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
