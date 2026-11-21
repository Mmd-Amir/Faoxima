<?php

namespace Faoxima\Payments\Gateways\Tonpay;

use Exception;
use Redis;

final class RateLimit
{
    public static function rateLimitStatePath()
    {
        $root = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT
              : (defined('APP_ROOT_PATH') ? APP_ROOT_PATH : dirname(__DIR__, 4));
        $runtime = $root . DIRECTORY_SEPARATOR . 'cronbot' . DIRECTORY_SEPARATOR . '.runtime';
        if (@is_dir($runtime) || @mkdir($runtime, 0775, true) || @is_dir($runtime)) {
            return $runtime . DIRECTORY_SEPARATOR . 'tonpay_ratelimit.json';
        }
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_tonpay_ratelimit_' . md5((string) $root) . '.json';
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
        $keys = array_map([\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_key'], array_values($keys));
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

    public static function rateLimitAcquire()
    {
        $limit = 50;
        $windowMs = 60000;
        $nowMs = (int) floor(microtime(true) * 1000);

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
            ['faoxima:tonpay:ratelimit:window', 'faoxima:tonpay:ratelimit:blocked'],
            [$nowMs, $windowMs, $limit, $member]
        );
        if ($redisResult !== null) {
            return $redisResult === 1;
        }

        $fileResult = self::rateLimitFileMutate(function (array &$state) use ($limit, $windowMs, $nowMs) {
            if ((int) ($state['blocked_until'] ?? 0) > $nowMs) {
                return false;
            }
            $hits = [];
            foreach ((array) ($state['hits'] ?? []) as $hit) {
                if ((int) $hit > $nowMs - $windowMs) {
                    $hits[] = (int) $hit;
                }
            }
            if (count($hits) >= $limit) {
                $state['hits'] = $hits;
                return false;
            }
            $hits[] = $nowMs;
            $state['hits'] = $hits;
            return true;
        });
        if ($fileResult === null) {
            error_log('TonPay rate limiter storage unavailable; request allowed without local accounting');
            return true;
        }
        return $fileResult;
        }

    public static function rateLimitBlock($seconds)
    {
        $seconds = max(1, min(600, (int) $seconds));
        $untilMs = (int) floor(microtime(true) * 1000) + ($seconds * 1000);

        $script = "local cur = tonumber(redis.call('get', KEYS[1]) or '0') or 0 "
            . "if tonumber(ARGV[1]) > cur then redis.call('set', KEYS[1], ARGV[1], 'PX', tonumber(ARGV[2])) end "
            . "return 1";
        if (self::rateLimitRedisEval($script, ['faoxima:tonpay:ratelimit:blocked'], [$untilMs, $seconds * 1000]) !== null) {
            return;
        }

        self::rateLimitFileMutate(function (array &$state) use ($untilMs) {
            if ($untilMs > (int) ($state['blocked_until'] ?? 0)) {
                $state['blocked_until'] = $untilMs;
            }
            return true;
        });
        }

    public static function isRateLimited($response)
    {
        if (!is_array($response)) {
            return false;
        }
        return !empty($response['rate_limited'])
            || (int) ($response['status_code'] ?? 0) === 429
            || \Faoxima\Payments\Gateways\Tonpay\Client::errorCode($response) === 'RATE_LIMIT_EXCEEDED'
            || (string) ($response['error_code'] ?? '') === 'RATE_LIMIT_EXCEEDED';
        }
}
