<?php

namespace Faoxima\Telegram\Conversation;

use PDO;

final class RequestThrottle
{
    public static function allows(
        ?PDO $pdo,
        array &$user,
        array $setting,
        int|string $from_id,
        array $admin_ids,
        array $admin_ids_str,
        mixed $callback_query_id,
        array $textbotlang,
        mixed $otherservice,
        int $timebot,
        callable $write,
        callable $telegram,
        callable $send,
    ): bool {
        $rxAntispamStatus = (string)($setting['antispam_status'] ?? '0');
        if ($rxAntispamStatus === '1' && !in_array((string)$from_id, $admin_ids_str, true) && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)) {

            $rxAsMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
            if ($rxAsMsgCount < 1)    { $rxAsMsgCount = 1; }
            if ($rxAsMsgCount > 1000) { $rxAsMsgCount = 1000; }
            $rxAsSeconds = (int)($setting['antispam_seconds'] ?? 3);
            if ($rxAsSeconds < 1)    { $rxAsSeconds = 1; }
            if ($rxAsSeconds > 3600) { $rxAsSeconds = 3600; }
            $rxAsMuteSeconds = (int)($setting['antispam_mute_seconds'] ?? 5);
            if ($rxAsMuteSeconds < 1)     { $rxAsMuteSeconds = 1; }
            if ($rxAsMuteSeconds > 86400) { $rxAsMuteSeconds = 86400; }

            $rxAsShouldDrop = false;
            $rxAsHandledByRedis = false;

            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active()) {
                try {
                    $rxAsLuaScript = "local key = KEYS[1] "
                        . "local now = tonumber(ARGV[1]) "
                        . "local win_seconds = tonumber(ARGV[2]) "
                        . "local msg_count = tonumber(ARGV[3]) "
                        . "local mute_seconds = tonumber(ARGV[4]) "
                        . "local win_start = tonumber(redis.call('HGET', key, 'window_start') or '0') "
                        . "local win_count = tonumber(redis.call('HGET', key, 'window_count') or '0') "
                        . "local muted_until = tonumber(redis.call('HGET', key, 'muted_until') or '0') "
                        . "local should_drop = 0 "
                        . "local sync_needed = 0 "
                        . "if muted_until > 0 and now < muted_until then "
                        . "  should_drop = 1 "
                        . "elseif muted_until > 0 and now >= muted_until then "
                        . "  muted_until = 0 win_start = now win_count = 1 sync_needed = 1 "
                        . "elseif win_start == 0 or (now - win_start) >= win_seconds then "
                        . "  win_start = now win_count = 1 sync_needed = 1 "
                        . "else "
                        . "  win_count = win_count + 1 "
                        . "  if win_count > msg_count then "
                        . "    muted_until = now + mute_seconds should_drop = 1 sync_needed = 1 "
                        . "  end "
                        . "end "
                        . "redis.call('HSET', key, 'window_start', win_start, 'window_count', win_count, 'muted_until', muted_until) "
                        . "local ttl = win_seconds "
                        . "if mute_seconds > ttl then ttl = mute_seconds end "
                        . "redis.call('EXPIRE', key, ttl + 5) "
                        . "return {win_start, win_count, muted_until, should_drop, sync_needed}";

                    $rxAsRedisKey = \Faoxima\Core\Cache\RedisCache::rx_redis_key('faoxima:antispam:' . $from_id);
                    $rxAsRedisClient = \Faoxima\Core\Cache\RedisCache::getRedisConnection();
                    if ($rxAsRedisClient instanceof \Redis) {
                        $rxAsLuaResult = $rxAsRedisClient->eval($rxAsLuaScript, [$rxAsRedisKey, $timebot, $rxAsSeconds, $rxAsMsgCount, $rxAsMuteSeconds], 1);
                    } else {
                        $rxAsLuaResult = $rxAsRedisClient->eval($rxAsLuaScript, 1, $rxAsRedisKey, $timebot, $rxAsSeconds, $rxAsMsgCount, $rxAsMuteSeconds);
                    }

                    if (is_array($rxAsLuaResult) && count($rxAsLuaResult) === 5) {
                        $rxAsNewWinStart   = (int) $rxAsLuaResult[0];
                        $rxAsNewWinCount   = (int) $rxAsLuaResult[1];
                        $rxAsNewMutedUntil = (int) $rxAsLuaResult[2];
                        $rxAsShouldDrop    = ((int) $rxAsLuaResult[3]) === 1;
                        $rxAsSyncNeeded    = ((int) $rxAsLuaResult[4]) === 1;

                        $user['antispam_window_start'] = (string) $rxAsNewWinStart;
                        $user['antispam_window_count'] = (string) $rxAsNewWinCount;
                        $user['antispam_muted_until']  = (string) $rxAsNewMutedUntil;

                        if ($rxAsSyncNeeded) {
                            try {
                                $rxAsSyncStmt = $pdo->prepare(
                                    "UPDATE user SET "
                                    . "antispam_window_start = :ws, "
                                    . "antispam_window_count = :wc, "
                                    . "antispam_muted_until  = :mu "
                                    . "WHERE id = :id"
                                );
                                $rxAsSyncStmt->bindValue(':ws', (string) $rxAsNewWinStart,   PDO::PARAM_STR);
                                $rxAsSyncStmt->bindValue(':wc', (string) $rxAsNewWinCount,   PDO::PARAM_STR);
                                $rxAsSyncStmt->bindValue(':mu', (string) $rxAsNewMutedUntil, PDO::PARAM_STR);
                                $rxAsSyncStmt->bindValue(':id', (string) $from_id,          PDO::PARAM_STR);
                                $rxAsSyncStmt->execute();
                                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                                    \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', (string) $from_id);
                                }
                            } catch (\Throwable $rxAsSyncErr) {
                                error_log('antispam Redis->MySQL sync failed: ' . $rxAsSyncErr->getMessage());
                            }
                        }

                        $rxAsHandledByRedis = true;
                    }
                } catch (\Throwable $rxAsRedisErr) {
                    $rxAsHandledByRedis = false;
                }
            }

            if (!$rxAsHandledByRedis) {
                $rxAsAtomicOk   = false;
                $rxAsTxOpened   = false;
                try {
                    $pdo->beginTransaction();
                    $rxAsTxOpened = true;

                    $rxAsSel = $pdo->prepare(
                        "SELECT antispam_window_start, antispam_window_count, antispam_muted_until "
                        . "FROM user WHERE id = :id FOR UPDATE"
                    );
                    $rxAsSel->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
                    $rxAsSel->execute();
                    $rxAsRow = $rxAsSel->fetch(PDO::FETCH_ASSOC);

                    if (!is_array($rxAsRow)) {

                        $pdo->commit();
                        $rxAsTxOpened = false;
                        $rxAsAtomicOk = true;
                    } else {
                        $rxAsWinStart   = (int)($rxAsRow['antispam_window_start'] ?? 0);
                        $rxAsWinCount   = (int)($rxAsRow['antispam_window_count'] ?? 0);
                        $rxAsMutedUntil = (int)($rxAsRow['antispam_muted_until']  ?? 0);

                        $rxAsNewWinStart   = $rxAsWinStart;
                        $rxAsNewWinCount   = $rxAsWinCount;
                        $rxAsNewMutedUntil = $rxAsMutedUntil;

                        if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {

                            $rxAsShouldDrop = true;
                        } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {

                            $rxAsNewMutedUntil = 0;
                            $rxAsNewWinStart   = $timebot;
                            $rxAsNewWinCount   = 1;
                        } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {

                            $rxAsNewWinStart = $timebot;
                            $rxAsNewWinCount = 1;
                        } else {

                            $rxAsNewWinCount = $rxAsWinCount + 1;
                            if ($rxAsNewWinCount > $rxAsMsgCount) {

                                $rxAsNewMutedUntil = $timebot + $rxAsMuteSeconds;
                                $rxAsShouldDrop    = true;
                            }
                        }

                        $rxAsUpd = $pdo->prepare(
                            "UPDATE user SET "
                            . "antispam_window_start = :ws, "
                            . "antispam_window_count = :wc, "
                            . "antispam_muted_until  = :mu "
                            . "WHERE id = :id"
                        );
                        $rxAsUpd->bindValue(':ws', (string)$rxAsNewWinStart,   PDO::PARAM_STR);
                        $rxAsUpd->bindValue(':wc', (string)$rxAsNewWinCount,   PDO::PARAM_STR);
                        $rxAsUpd->bindValue(':mu', (string)$rxAsNewMutedUntil, PDO::PARAM_STR);
                        $rxAsUpd->bindValue(':id', (string)$from_id,           PDO::PARAM_STR);
                        $rxAsUpd->execute();

                        $pdo->commit();
                        $rxAsTxOpened = false;

                        $user['antispam_window_start'] = (string)$rxAsNewWinStart;
                        $user['antispam_window_count'] = (string)$rxAsNewWinCount;
                        $user['antispam_muted_until']  = (string)$rxAsNewMutedUntil;

                        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                            \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', (string)$from_id);
                        } elseif (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                            \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
                        }

                        $rxAsAtomicOk = true;
                    }
                } catch (\Throwable $rxAsTxErr) {

                    if ($rxAsTxOpened) {
                        try { $pdo->rollBack(); } catch (\Throwable $rxAsRbErr) {  }
                    }
                    $rxAsAtomicOk = false;
                }

                if (!$rxAsAtomicOk) {
                    $rxAsWinStart   = (int)($user['antispam_window_start']  ?? 0);
                    $rxAsWinCount   = (int)($user['antispam_window_count']  ?? 0);
                    $rxAsMutedUntil = (int)($user['antispam_muted_until']   ?? 0);

                    if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {
                        $rxAsShouldDrop = true;
                    } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {
                        $write("user", "antispam_muted_until",  "0",                "id", $from_id);
                        $write("user", "antispam_window_start", (string)$timebot,   "id", $from_id);
                        $write("user", "antispam_window_count", "1",                "id", $from_id);
                        $user['antispam_muted_until']  = "0";
                        $user['antispam_window_start'] = (string)$timebot;
                        $user['antispam_window_count'] = "1";
                    } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {
                        $write("user", "antispam_window_start", (string)$timebot, "id", $from_id);
                        $write("user", "antispam_window_count", "1",              "id", $from_id);
                        $user['antispam_window_start'] = (string)$timebot;
                        $user['antispam_window_count'] = "1";
                    } else {
                        $rxAsNewCount = $rxAsWinCount + 1;
                        $write("user", "antispam_window_count", (string)$rxAsNewCount, "id", $from_id);
                        $user['antispam_window_count'] = (string)$rxAsNewCount;
                        if ($rxAsNewCount > $rxAsMsgCount) {
                            $rxAsMuteEnd = $timebot + $rxAsMuteSeconds;
                            $write("user", "antispam_muted_until", (string)$rxAsMuteEnd, "id", $from_id);
                            $user['antispam_muted_until'] = (string)$rxAsMuteEnd;
                            $rxAsShouldDrop = true;
                        }
                    }
                }
            }

            if ($rxAsShouldDrop) {

                if (!empty($callback_query_id)) {
                    try {
                        $telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time' => 0,
                        ]);
                    } catch (\Throwable $rxAsAckErr2) {  }
                }
                return false;
            }
        } else {

            $TimeLastMessage = $timebot - intval($user['last_message_time']);
            if (floor($TimeLastMessage / 60) >= 1) {
                $write("user", "last_message_time", $timebot, "id", $from_id);
                $write("user", "message_count", "1", "id", $from_id);
            } else {
                if (!in_array($from_id, $admin_ids)) {
                    $addmessage = intval($user['message_count']) + 1;
                    $write("user", "message_count", $addmessage, "id", $from_id);
                    $spamThreshold = 35;
                    if ($addmessage >= $spamThreshold) {
                        $User_Status = "block";
                        $textblok = sprintf($textbotlang['users']['spam']['spamedreport'], $from_id);
                        $Response = json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                                ],
                            ]
                        ]);
                        if (strlen($setting['Channel_Report'] ?? '') > 0) {
                            $telegram('sendmessage', [
                                'chat_id' => $setting['Channel_Report'],
                                'message_thread_id' => $otherservice,
                                'text' => $textblok,
                                'parse_mode' => "HTML",
                                'reply_markup' => $Response
                            ]);
                        }
                        $write("user", "User_Status", $User_Status, "id", $from_id);
                        $write("user", "description_blocking", $textbotlang['users']['spam']['spamed'], "id", $from_id);
                        $send($from_id, $textbotlang['users']['spam']['spamedmessage'], null, 'html');
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
