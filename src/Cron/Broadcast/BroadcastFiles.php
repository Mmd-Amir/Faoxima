<?php

namespace Faoxima\Cron\Broadcast;

use PDO;

use PDOStatement;
use Throwable;

final class BroadcastFiles
{
    public static function handleResponse(
    $resp, string $userId, array $info,
    PDOStatement $orphanCheckStmt, PDOStatement $deleteStmt,
    int &$bSuccess, int &$bBlocked, int &$bDeleted, int &$bFailed, int &$bChatNotFound,
    ?callable $rxBcLog = null
): void {
    if (isset($resp['ok']) && !$resp['ok']) {
        $errorDesc = $resp['description'] ?? 'unknown error';
        if ($errorDesc === "Forbidden: bot was blocked by the user") {
            $bBlocked++;
            try {
                $orphanCheckStmt->execute([':id' => $userId, ':id2' => $userId]);
                $r = $orphanCheckStmt->fetch(PDO::FETCH_ASSOC);
                if ($r && (int) $r['invoice_count'] === 0 && (int) $r['Balance'] === 0) {
                    $deleteStmt->execute([':id' => $userId]);
                    $bDeleted++;
                }
            } catch (Throwable $e) {}
        } elseif (stripos($errorDesc, 'chat not found') !== false
               || stripos($errorDesc, 'user is deactivated') !== false) {
            $bChatNotFound++;
            try { $deleteStmt->execute([':id' => $userId]); $bDeleted++; } catch (Throwable $e) {}
        } else {
            $bFailed++;
        }
    } elseif (isset($resp['ok']) && $resp['ok']) {
        $bSuccess++;
        if (($info['pingmessage'] ?? '') === 'yes' && isset($resp['result']['message_id'])) {
            try {
                pinmessage($userId, $resp['result']['message_id']);
                self::savePinnedMessageRecord($userId, (int) $resp['result']['message_id'], (string) ($info['pinduration'] ?? 'none'));
            } catch (Throwable $e) {
                if ($rxBcLog) {
                    $rxBcLog('PIN FAILED — userId=' . $userId . ' message_id=' . $resp['result']['message_id'] . ' — ' . $e->getMessage());
                }
            }
        }
    }
}

    public static function runRateLimitedUnpin(
    string $userId,
    string $baseDir,
    float $batchStart,
    int $softTimeLimit,
    ?callable $rxBcLog = null
): array {
    $resolvedBaseDir = realpath($baseDir);
    $rateKey = md5($resolvedBaseDir !== false ? $resolvedBaseDir : $baseDir);
    $ratePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'faoxima_unpin_' . $rateKey;
    $lockFh = @fopen($ratePath . '.lock', 'c');

    if ($lockFh !== false) {
        while (!@flock($lockFh, LOCK_EX | LOCK_NB)) {
            if ((microtime(true) - $batchStart) >= $softTimeLimit) {
                @fclose($lockFh);
                return ['deferred' => true, 'response' => null];
            }
            usleep(50000);
        }
    }

    $response = null;
    $isRateLimited = false;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $nextAllowedAt = is_file($ratePath) ? (float) @file_get_contents($ratePath) : 0.0;
        $waitMicros = (int) max(0, ceil(($nextAllowedAt - microtime(true)) * 1000000));
        $remainingMicros = (int) max(0, floor(($softTimeLimit - (microtime(true) - $batchStart)) * 1000000));

        if ($waitMicros >= $remainingMicros) {
            $isRateLimited = true;
            break;
        }

        if ($waitMicros > 0) {
            usleep($waitMicros);
        }

        $response = unpinmessage($userId);
        $isRateLimited = isset($response['error_code']) && (int) $response['error_code'] === 429;

        if (!$isRateLimited) {
            @file_put_contents($ratePath, sprintf('%.6F', microtime(true) + 0.1), LOCK_EX);
            break;
        }

        $retryAfter = max(1, (int) ($response['parameters']['retry_after'] ?? 1));
        @file_put_contents($ratePath, sprintf('%.6F', microtime(true) + $retryAfter + 0.25), LOCK_EX);
        if ($rxBcLog) {
            $rxBcLog('RATE-LIMIT — unpin userId=' . $userId . ' retry_after=' . $retryAfter . 's attempt=' . $attempt);
        }
    }

    if ($lockFh !== false) {
        @flock($lockFh, LOCK_UN);
        @fclose($lockFh);
    }

    if ($isRateLimited) {
        return ['deferred' => true, 'response' => $response];
    }

    return ['deferred' => false, 'response' => $response];
}

    public static function savePinnedMessageRecord(string $chatId, int $messageId, string $duration): void
    {
        global $pdo;
        $durationSeconds = ['24' => 86400, '48' => 172800][$duration] ?? null;
        $unpinAt = $durationSeconds !== null ? (time() + $durationSeconds) : null;
        $stmt = $pdo->prepare(
            "INSERT INTO pinned_messages (chat_id, message_id, unpin_at, created_at) VALUES (:chat_id, :message_id, :unpin_at, :created_at)"
        );
        $stmt->execute([
            ':chat_id'    => $chatId,
            ':message_id' => $messageId,
            ':unpin_at'   => $unpinAt,
            ':created_at' => time(),
        ]);
    }

    public static function migrateLegacyJsonToTxt(string $jsonPath, string $txtPath): int
    {
        if (!is_file($jsonPath)) return -1;
        $size = @filesize($jsonPath);
        if ($size === false) return -1;


        if ($size <= 32 * 1024 * 1024) {
            $raw = @file_get_contents($jsonPath);
            if ($raw === false) return -1;
            $arr = json_decode($raw);
            if (!is_array($arr)) return -1;
            $tmp = $txtPath . '.tmp';
            $fh = @fopen($tmp, 'w');
            if (!$fh) return -1;
            $count = 0;
            foreach ($arr as $row) {
                $id = null;
                if (isset($row->id)) $id = $row->id;
                elseif (is_array($row) && isset($row['id'])) $id = $row['id'];
                elseif (is_scalar($row)) $id = $row;
                if ($id !== null && $id !== '' && is_numeric($id)) {
                    fwrite($fh, ((string) $id) . "\n");
                    $count++;
                }
            }
            fclose($fh);
            if (!@rename($tmp, $txtPath)) return -1;
            return $count;
        }


        $in  = @fopen($jsonPath, 'r');
        if (!$in) return -1;
        $tmp = $txtPath . '.tmp';
        $out = @fopen($tmp, 'w');
        if (!$out) { fclose($in); return -1; }

        $count  = 0;
        $buffer = '';
        $regex  = '/"id"\s*:\s*"?([0-9]+)"?/';

        while (!feof($in)) {
            $chunk = fread($in, 65536);
            if ($chunk === false) break;
            $buffer .= $chunk;


            $offset = 0;
            while (preg_match($regex, $buffer, $m, PREG_OFFSET_CAPTURE, $offset)) {
                fwrite($out, $m[1][0] . "\n");
                $count++;
                $offset = $m[0][1] + strlen($m[0][0]);
            }


            if ($offset > 64) {
                $buffer = substr($buffer, $offset - 64);
            }
        }
        fclose($in);
        fclose($out);
        if (!@rename($tmp, $txtPath)) return -1;
        return $count;
    }

    public static function claimBatchToInflight(string $src, string $inflight, int $n): array
    {
        if (!is_file($src)) return [];
        $in = @fopen($src, 'r');
        if (!$in) return [];
        $tail = $src . '.tail.tmp';
        $out  = @fopen($tail, 'w');
        if (!$out) { fclose($in); return []; }

        $batch = [];
        $i = 0;
        while (($line = fgets($in)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            if ($i < $n) { $batch[] = $line; $i++; }
            else         { fwrite($out, $line . "\n"); }
        }
        fclose($in);
        fclose($out);

        self::syncInflight($inflight, $batch);
        @rename($tail, $src);
        return $batch;
    }

    public static function syncInflight(string $path, array $remainder): void
    {
        $clean = [];
        foreach ($remainder as $line) {
            $line = trim((string) $line);
            if ($line !== '') $clean[] = $line;
        }

        if (empty($clean)) { @unlink($path); return; }

        $tmp = $path . '.tmp';
        $out = @fopen($tmp, 'w');
        if (!$out) return;
        foreach ($clean as $line) {
            fwrite($out, $line . "\n");
        }
        fclose($out);
        @rename($tmp, $path);
    }

    public static function loadSentSet(string $path): array
    {
        if (!is_file($path)) return [];
        $set = [];
        $in = @fopen($path, 'r');
        if (!$in) return [];
        while (($line = fgets($in)) !== false) {
            $line = trim($line);
            if ($line !== '') $set[$line] = true;
        }
        fclose($in);
        return $set;
    }

    public static function loadBroadcastSentSet(string $usersFileTxt, string $token): array
    {
        $set = [];
        foreach (glob($usersFileTxt . '.w*.' . $token . '.sent') ?: [] as $f) {
            $set += self::loadSentSet($f);
        }
        return $set;
    }

    public static function recoverBroadcastInflight(string $baseDir, string $usersFileTxt, string $token, int $selfWorker, array $sentSet): int
    {
        $files = [];
        $held = [];
        $recovered = [];
        $seen = [];
        foreach (glob($usersFileTxt . '.w*.' . $token . '.inflight') ?: [] as $inf) {
            if (!preg_match('/\.w(\d+)\.' . preg_quote($token, '/') . '\.inflight$/', $inf, $m)) {
                continue;
            }
            $w = (int) $m[1];
            if ($w !== $selfWorker) {
                $lh = @fopen($baseDir . '/broadcast' . ($w > 0 ? "_w{$w}" : '') . '.lock', 'c');
                if ($lh === false) {
                    continue;
                }
                if (!@flock($lh, LOCK_EX | LOCK_NB)) {
                    @fclose($lh);
                    continue;
                }
                $held[] = $lh;
            }
            foreach (self::loadLinesFromTxt($inf, PHP_INT_MAX) as $id) {
                if ($id !== '' && !isset($sentSet[$id]) && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $recovered[] = $id;
                }
            }
            $files[] = $inf;
        }

        $ok = true;
        if (!empty($recovered)) {
            $tmp = $usersFileTxt . '.recover.tmp';
            $out = @fopen($tmp, 'w');
            $ok = ($out !== false);
            if ($ok) {
                foreach ($recovered as $id) {
                    fwrite($out, $id . "\n");
                }
                $in = is_file($usersFileTxt) ? @fopen($usersFileTxt, 'r') : false;
                if ($in) {
                    while (($line = fgets($in)) !== false) {
                        $line = trim($line);
                        if ($line !== '' && !isset($seen[$line])) {
                            fwrite($out, $line . "\n");
                        }
                    }
                    fclose($in);
                }
                fclose($out);
                $ok = @rename($tmp, $usersFileTxt);
                if (!$ok) {
                    @unlink($tmp);
                }
            }
        }
        if ($ok) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        foreach ($held as $lh) {
            @flock($lh, LOCK_UN);
            @fclose($lh);
        }
        return $ok ? count($recovered) : 0;
    }

    public static function loadLinesFromTxt(string $path, int $max): array
    {
        if (!is_file($path)) return [];
        $in = @fopen($path, 'r');
        if (!$in) return [];
        $out = [];
        $i = 0;
        while (($line = fgets($in)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $out[] = $line;
            if (++$i >= $max) break;
        }
        fclose($in);
        return $out;
    }

    public static function prependEntriesToJson(string $path, array $items): void
    {
        $existing = [];
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = $raw !== false ? json_decode($raw) : null;
            if (is_array($decoded)) $existing = $decoded;
        }
        $rebuilt = [];
        foreach ($items as $id) $rebuilt[] = (object) ['id' => (string) $id];
        foreach ($existing as $row) $rebuilt[] = $row;
        $tmp = $path . '.prep.tmp';
        file_put_contents($tmp, json_encode($rebuilt, JSON_UNESCAPED_UNICODE));
        @rename($tmp, $path);
    }

    public static function countLines(string $path): int
    {
        if (!is_file($path)) return 0;
        $fh = @fopen($path, 'r');
        if (!$fh) return 0;
        $count = 0;
        while (!feof($fh)) {
            $chunk = fread($fh, 65536);
            if ($chunk === false) break;
            $count += substr_count($chunk, "\n");
        }
        fclose($fh);
        return $count;
    }

    public static function buildFinalReport(array $totals): string {
    $f  = "✅ عملیات ارسال پیام با موفقیت به پایان رسید.\n\n📊 گزارش نهایی:\n";
    $f .= "👥 مخاطبین: "  . number_format((int) ($totals['total']    ?? 0)) . "\n";
    $f .= "✅ موفق: "    . number_format((int) ($totals['success']  ?? 0)) . "\n";
    if (!empty($totals['blocked']))        $f .= "🚫 بلاک‌شده: "   . number_format((int) $totals['blocked'])        . "\n";
    if (!empty($totals['chat_not_found'])) $f .= "📵 چت ناموجود: ". number_format((int) $totals['chat_not_found']) . "\n";
    if (!empty($totals['deleted']))        $f .= "🗑 حذف‌شده: "   . number_format((int) $totals['deleted'])        . "\n";
    if (!empty($totals['failed']))         $f .= "❌ خطا: "       . number_format((int) $totals['failed'])         . "\n";
    if (!empty($totals['started_at'])) {
        $elapsed = max(0, time() - (int) $totals['started_at']);
        $f .= "⏱ مدت کل: " . gmdate('H:i:s', $elapsed) . "\n";
    }
    $f .= "\n🕒 پایان: " . date('Y-m-d H:i:s') . " (Asia/Tehran)";
    return $f;
}
}
