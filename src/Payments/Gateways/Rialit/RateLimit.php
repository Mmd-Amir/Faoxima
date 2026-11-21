<?php

namespace Faoxima\Payments\Gateways\Rialit;

use Exception;
use Redis;

final class RateLimit
{
    public static function rateLimitStatePath(): string
    {
        $root = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT
            : (defined('APP_ROOT_PATH') ? APP_ROOT_PATH : dirname(__DIR__, 4));
        $runtime = $root . DIRECTORY_SEPARATOR . 'cronbot' . DIRECTORY_SEPARATOR . '.runtime';
        if (@is_dir($runtime) || @mkdir($runtime, 0775, true) || @is_dir($runtime)) {
            return $runtime . DIRECTORY_SEPARATOR . 'rialit_ratelimit.json';
        }
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_rialit_ratelimit_' . md5((string) $root) . '.json';
    }

    public static function rateLimitRedisEval($script, array $keys, array $args)
    {
        if (!method_exists(\Faoxima\Core\Cache\RedisCache::class, 'getRedisConnection')) {
            return null;
        }
        $client = \Faoxima\Core\Cache\RedisCache::getRedisConnection();
        if ($client === null) {
            return null;
        }
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_key')) {
            $keys = array_map([\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_key'], array_values($keys));
        }
        try {
            if ($client instanceof \Redis) {
                $result = $client->eval($script, array_merge($keys, $args), count($keys));
            } else {
                $result = $client->eval($script, count($keys), ...array_merge($keys, $args));
            }
        } catch (\Throwable $e) {
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_mark_unavailable')) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_mark_unavailable('exception');
            }
            return null;
        }
        return ($result === false || $result === null) ? null : (int) $result;
    }

    public static function rateLimitFileMutate(callable $mutator)
    {
        $path = self::rateLimitStatePath();
        $isNew = !is_file($path);
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return null;
        }
        if ($isNew) {
            @chmod($path, 0664);
        }
        if (!@flock($fh, LOCK_EX)) {
            @fclose($fh);
            return null;
        }
        $state = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($state)) {
            $state = [];
        }
        $result = $mutator($state);
        @ftruncate($fh, 0);
        @rewind($fh);
        @fwrite($fh, (string) json_encode($state));
        @fflush($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        return $result;
    }

    public static function rateLimitLimits($bucket): array
    {
        if ($bucket === 'send') {
            return [10, 1000];
        }
        if ($bucket === 'webhook_change') {
            return [20, 3600000];
        }
        return [5, 1000];
    }

    public static function rateLimitAcquire($bucket): bool
    {
        [$limit, $windowMs] = self::rateLimitLimits((string) $bucket);
        $bucket = (string) $bucket;
        $nowMs = (int) floor(microtime(true) * 1000);
        $cacheKey = 'faoxima:rialit:ratelimit:' . $bucket;
        $script = "local now = tonumber(ARGV[1]) "
            . "local blocked = tonumber(redis.call('get', KEYS[2]) or '0') or 0 "
            . "if blocked > now then return 0 end "
            . "redis.call('zremrangebyscore', KEYS[1], '-inf', now - tonumber(ARGV[2])) "
            . "if redis.call('zcard', KEYS[1]) >= tonumber(ARGV[3]) then return 0 end "
            . "redis.call('zadd', KEYS[1], now, ARGV[4]) "
            . "redis.call('pexpire', KEYS[1], tonumber(ARGV[2])) "
            . "return 1";
        $member = $nowMs . ':' . bin2hex(random_bytes(4));
        $redisResult = self::rateLimitRedisEval(
            $script,
            [$cacheKey . ':window', $cacheKey . ':blocked'],
            [$nowMs, $windowMs, $limit, $member]
        );
        if ($redisResult !== null) {
            return $redisResult === 1;
        }
        $fileResult = self::rateLimitFileMutate(function (array &$state) use ($bucket, $limit, $windowMs, $nowMs) {
            if (!isset($state['buckets']) || !is_array($state['buckets'])) {
                $state['buckets'] = [];
            }
            $bucketState = isset($state['buckets'][$bucket]) && is_array($state['buckets'][$bucket]) ? $state['buckets'][$bucket] : [];
            if ((int) ($bucketState['blocked_until'] ?? 0) > $nowMs) {
                return false;
            }
            $hits = [];
            foreach ((array) ($bucketState['hits'] ?? []) as $hit) {
                if ((int) $hit > $nowMs - $windowMs) {
                    $hits[] = (int) $hit;
                }
            }
            if (count($hits) >= $limit) {
                $bucketState['hits'] = $hits;
                $state['buckets'][$bucket] = $bucketState;
                return false;
            }
            $hits[] = $nowMs;
            $bucketState['hits'] = $hits;
            $state['buckets'][$bucket] = $bucketState;
            return true;
        });
        if ($fileResult === null) {
            error_log('Rialit rate limiter storage unavailable; request allowed without local accounting');
            return true;
        }
        return $fileResult;
    }

    public static function rateLimitBlock($bucket, $seconds): void
    {
        $bucket = (string) $bucket;
        $seconds = max(1, min(3600, (int) $seconds));
        $untilMs = (int) floor(microtime(true) * 1000) + ($seconds * 1000);
        $cacheKey = 'faoxima:rialit:ratelimit:' . $bucket;
        $script = "local cur = tonumber(redis.call('get', KEYS[1]) or '0') or 0 "
            . "if tonumber(ARGV[1]) > cur then redis.call('set', KEYS[1], ARGV[1], 'PX', tonumber(ARGV[2])) end "
            . "return 1";
        if (self::rateLimitRedisEval($script, [$cacheKey . ':blocked'], [$untilMs, $seconds * 1000]) !== null) {
            return;
        }
        self::rateLimitFileMutate(function (array &$state) use ($bucket, $untilMs) {
            if (!isset($state['buckets']) || !is_array($state['buckets'])) {
                $state['buckets'] = [];
            }
            $bucketState = isset($state['buckets'][$bucket]) && is_array($state['buckets'][$bucket]) ? $state['buckets'][$bucket] : [];
            if ($untilMs > (int) ($bucketState['blocked_until'] ?? 0)) {
                $bucketState['blocked_until'] = $untilMs;
            }
            $state['buckets'][$bucket] = $bucketState;
            return true;
        });
    }
}
