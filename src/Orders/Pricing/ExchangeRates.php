<?php

namespace Faoxima\Orders\Pricing;

use PDO;
use Throwable;

final class ExchangeRates
{
    public static function fxRateMemoReset(?string $pair = null): void
    {
        if ($pair === null) {
            $GLOBALS['__fx_rate_memo'] = [];
            $GLOBALS['__fx_context_memo'] = [];
            return;
        }
        unset($GLOBALS['__fx_rate_memo'][\Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair)]);
        $GLOBALS['__fx_context_memo'] = [];
    }

    public static function fxRateRedisKey(string $pair): string
    {
        return 'fx_rate_cache:' . str_replace('/', '_', \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair));
    }

    public static function fxRateRowToState(string $pair, $row): array
    {
        $state = [
            'ok'                => false,
            'pair'              => $pair,
            'rate'              => null,
            'previous_rate'     => null,
            'pending_rate'      => null,
            'pending_count'     => 0,
            'source'            => null,
            'fetched_at'        => 0,
            'status'            => 'missing',
            'last_error'        => null,
            'last_event'        => null,
            'stale_notified_at' => 0,
            'updated_at'        => 0,
        ];
        if (!is_array($row)) {
            return $state;
        }
        $rate = \Faoxima\Orders\Pricing\ExchangePricing::fxPositiveNumber($row['rate'] ?? null);
        $state['rate'] = $rate;
        $state['previous_rate'] = \Faoxima\Orders\Pricing\ExchangePricing::fxPositiveNumber($row['previous_rate'] ?? null);
        $state['pending_rate'] = \Faoxima\Orders\Pricing\ExchangePricing::fxPositiveNumber($row['pending_rate'] ?? null);
        $state['pending_count'] = (int) ($row['pending_count'] ?? 0);
        $state['source'] = isset($row['source']) ? (string) $row['source'] : null;
        $state['fetched_at'] = (int) ($row['fetched_at'] ?? 0);
        $state['status'] = (string) ($row['status'] ?? 'ok');
        $state['last_error'] = isset($row['last_error']) && $row['last_error'] !== '' ? (string) $row['last_error'] : null;
        $state['last_event'] = isset($row['last_event']) && $row['last_event'] !== '' ? (string) $row['last_event'] : null;
        $state['stale_notified_at'] = (int) ($row['stale_notified_at'] ?? 0);
        $state['updated_at'] = (int) ($row['updated_at'] ?? 0);
        $state['ok'] = $rate !== null && $state['fetched_at'] > 0;
        return $state;
    }

    public static function fxRateGetCached(string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair);
        if (isset($GLOBALS['__fx_rate_memo'][$pair])) {
            return $GLOBALS['__fx_rate_memo'][$pair];
        }
        $redisKey = self::fxRateRedisKey($pair);
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_get')) {
            try {
                $cachedRaw = \Faoxima\Core\Cache\RedisCache::rx_redis_get($redisKey);
                if (is_string($cachedRaw) && $cachedRaw !== '') {
                    $cachedRow = json_decode($cachedRaw, true);
                    $state = self::fxRateRowToState($pair, $cachedRow);
                    if ($state['ok']) {
                        $GLOBALS['__fx_rate_memo'][$pair] = $state;
                        return $state;
                    }
                }
            } catch (Throwable $e) {
            }
        }
        $row = null;
        $pdo = \Faoxima\Orders\Pricing\ExchangePricing::fxPdo();
        if ($pdo instanceof PDO && \Faoxima\Orders\Pricing\ExchangePricing::fxEnsureSchema($pdo)) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM fx_rate_cache WHERE pair = ? LIMIT 1');
                $stmt->execute([$pair]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                \Faoxima\Orders\Pricing\ExchangePricing::fxLog('FX_RATE_READ_FAILED', $e->getMessage());
            }
        }
        $state = self::fxRateRowToState($pair, $row);
        if ($state['ok'] && method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_set')) {
            try {
                \Faoxima\Core\Cache\RedisCache::rx_redis_set($redisKey, json_encode($row), 60);
            } catch (Throwable $e) {
            }
        }
        $GLOBALS['__fx_rate_memo'][$pair] = $state;
        return $state;
    }

    public static function fxRateIsStale(int $fetchedAt, int $staleAfterMinutes): bool
    {
        if ($fetchedAt <= 0) {
            return true;
        }
        return (time() - $fetchedAt) > max(1, $staleAfterMinutes) * 60;
    }

    public static function fxRateGetEffective(string $pair = FX_DEFAULT_PAIR, ?array $config = null): array
    {
        $pair = \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair);
        $config = $config === null ? \Faoxima\Orders\Pricing\ExchangePricing::fxDefaultConfig() : \Faoxima\Orders\Pricing\ExchangePricing::fxSanitizeConfig($config);
        $cached = self::fxRateGetCached($pair);
        $manual = $config['manual_rate'];
        if ($manual !== null) {
            return [
                'ok'         => true,
                'pair'       => $pair,
                'rate'       => $manual,
                'source'     => 'manual',
                'manual'     => true,
                'fetched_at' => (int) $cached['fetched_at'],
                'stale'      => false,
                'api'        => $cached,
            ];
        }
        if (!$cached['ok']) {
            return [
                'ok'         => false,
                'pair'       => $pair,
                'rate'       => null,
                'source'     => null,
                'manual'     => false,
                'fetched_at' => 0,
                'stale'      => true,
                'api'        => $cached,
            ];
        }
        return [
            'ok'         => true,
            'pair'       => $pair,
            'rate'       => $cached['rate'],
            'source'     => $cached['source'] ?: 'swapwallet',
            'manual'     => false,
            'fetched_at' => (int) $cached['fetched_at'],
            'stale'      => self::fxRateIsStale((int) $cached['fetched_at'], (int) $config['stale_after_minutes']),
            'api'        => $cached,
        ];
    }

    public static function fxQuoteRateUnchanged($userId, string $contextKey, array $panel, $priceKinds): bool
    {
        $context = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($panel, $priceKinds);
        if ($context === null) {
            return true;
        }
        $quote = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteGet($userId, $contextKey);
        return $quote !== null && hash_equals(\Faoxima\Orders\Pricing\ExchangePricing::fxContextFingerprint($context), (string) ($quote['meta']['f'] ?? ''));
    }

    public static function fxParseRateValue($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } elseif (is_string($value)) {
            $clean = str_replace([',', ' '], '', trim($value));
            if ($clean === '' || !is_numeric($clean)) {
                return null;
            }
            $number = (float) $clean;
        } else {
            return null;
        }
        if (is_nan($number) || is_infinite($number) || $number <= 0) {
            return null;
        }
        return $number;
    }

    public static function fxRateFetchSwapwallet(string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair);
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'rate' => null, 'error' => 'curl_unavailable'];
        }
        $ch = curl_init(FX_SWAPWALLET_ENDPOINT);
        if ($ch === false) {
            return ['ok' => false, 'rate' => null, 'error' => 'curl_init_failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'fx-rate-sync/1.0',
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $errno !== 0) {
            return ['ok' => false, 'rate' => null, 'error' => 'network_error_' . $errno];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'rate' => null, 'error' => 'http_status_' . $httpCode];
        }
        return self::fxRateParseMarketResponse((string) $body, $pair);
    }

    public static function fxRateParseMarketResponse(string $body, string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return ['ok' => false, 'rate' => null, 'error' => 'invalid_json'];
        }
        if (($data['status'] ?? null) !== 'OK' || !isset($data['result']) || !is_array($data['result'])) {
            return ['ok' => false, 'rate' => null, 'error' => 'status_not_ok'];
        }
        $rawValue = null;
        $found = false;
        foreach ($data['result'] as $key => $value) {
            if (strtoupper((string) $key) === $pair) {
                $rawValue = $value;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return ['ok' => false, 'rate' => null, 'error' => 'pair_missing'];
        }
        $rate = self::fxParseRateValue($rawValue);
        if ($rate === null) {
            return ['ok' => false, 'rate' => null, 'error' => 'invalid_rate_value'];
        }
        return ['ok' => true, 'rate' => $rate, 'error' => null];
    }

    public static function fxRateMaxJumpPercent(): float
    {
        $limit = null;
        foreach (\Faoxima\Orders\Pricing\ExchangePricing::fxEnabledPanelConfigs() as $entry) {
            $value = (float) $entry['config']['max_jump_percent'];
            $limit = $limit === null ? $value : min($limit, $value);
        }
        return $limit === null ? 15.0 : $limit;
    }

    public static function fxRateStoreValid(PDO $pdo, string $pair, float $rate, string $source, float $maxJumpPercent): array
    {
        $pair = \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair);
        $now = time();
        $result = ['action' => 'none', 'rate' => $rate, 'previous_rate' => null, 'deviation' => null];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM fx_rate_cache WHERE pair = ? FOR UPDATE');
            $stmt->execute([$pair]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current = is_array($row) ? \Faoxima\Orders\Pricing\ExchangePricing::fxPositiveNumber($row['rate'] ?? null) : null;
            if ($current === null) {
                $pdo->prepare("REPLACE INTO fx_rate_cache (pair, rate, previous_rate, pending_rate, pending_count, source, fetched_at, status, last_error, last_event, stale_notified_at, updated_at) VALUES (?, ?, NULL, NULL, 0, ?, ?, 'ok', NULL, ?, 0, ?)")
                    ->execute([$pair, $rate, $source, $now, 'initialized', $now]);
                $pdo->commit();
                $result['action'] = 'initialized';
                return $result;
            }
            $result['previous_rate'] = $current;
            $deviation = abs($rate - $current) / $current * 100;
            $result['deviation'] = $deviation;
            if ($deviation <= $maxJumpPercent) {
                $previous = abs($rate - $current) > 0.00001 ? $current : \Faoxima\Orders\Pricing\ExchangePricing::fxPositiveNumber($row['previous_rate'] ?? null);
                $pdo->prepare("UPDATE fx_rate_cache SET rate = ?, previous_rate = ?, pending_rate = NULL, pending_count = 0, source = ?, fetched_at = ?, status = 'ok', last_error = NULL, stale_notified_at = 0, updated_at = ? WHERE pair = ?")
                    ->execute([$rate, $previous, $source, $now, $now, $pair]);
                $pdo->commit();
                $result['action'] = 'updated';
                return $result;
            }
            $pending = \Faoxima\Orders\Pricing\ExchangePricing::fxPositiveNumber($row['pending_rate'] ?? null);
            $pendingCount = (int) ($row['pending_count'] ?? 0);
            if ($pending !== null && abs($rate - $pending) / $pending * 100 <= FX_PENDING_TOLERANCE_PERCENT) {
                $pendingCount++;
                if ($pendingCount >= FX_PENDING_CONFIRMATIONS) {
                    $event = sprintf('promoted %s -> %s (%.2f%%)', number_format($current, 2, '.', ''), number_format($rate, 2, '.', ''), $deviation);
                    $pdo->prepare("UPDATE fx_rate_cache SET rate = ?, previous_rate = ?, pending_rate = NULL, pending_count = 0, source = ?, fetched_at = ?, status = 'ok', last_error = NULL, last_event = ?, stale_notified_at = 0, updated_at = ? WHERE pair = ?")
                        ->execute([$rate, $current, $source, $now, $event, $now, $pair]);
                    $pdo->commit();
                    $result['action'] = 'promoted';
                    return $result;
                }
                $pdo->prepare("UPDATE fx_rate_cache SET pending_rate = ?, pending_count = ?, status = 'pending', updated_at = ? WHERE pair = ?")
                    ->execute([$rate, $pendingCount, $now, $pair]);
                $pdo->commit();
                $result['action'] = 'pending';
                return $result;
            }
            $event = sprintf('jump_detected %s -> %s (%.2f%%)', number_format($current, 2, '.', ''), number_format($rate, 2, '.', ''), $deviation);
            $pdo->prepare("UPDATE fx_rate_cache SET pending_rate = ?, pending_count = 1, status = 'pending', last_event = ?, updated_at = ? WHERE pair = ?")
                ->execute([$rate, $event, $now, $pair]);
            $pdo->commit();
            $result['action'] = 'jump_detected';
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function fxRateStoreError(PDO $pdo, string $pair, string $error): void
    {
        $safeError = substr(preg_replace('/[^A-Za-z0-9_\-\.: ]/', '', $error), 0, 200);
        $now = time();
        try {
            $pdo->prepare("UPDATE fx_rate_cache SET last_error = ?, status = CASE WHEN status = 'pending' THEN 'pending' ELSE 'error' END, updated_at = ? WHERE pair = ?")
                ->execute([$safeError . ' @' . $now, $now, \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair)]);
        } catch (Throwable $e) {
            \Faoxima\Orders\Pricing\ExchangePricing::fxLog('FX_RATE_ERROR_STORE_FAILED', $e->getMessage());
        }
    }

    public static function fxRatePublishInvalidate(string $pair): void
    {
        self::fxRateMemoReset($pair);
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
            try {
                \Faoxima\Core\Cache\RedisCache::rx_redis_del(self::fxRateRedisKey($pair));
            } catch (Throwable $e) {
            }
        }
    }

    public static function fxRateCheckStale(PDO $pdo, string $pair): bool
    {
        $enabled = \Faoxima\Orders\Pricing\ExchangePricing::fxEnabledPanelConfigs();
        if (empty($enabled)) {
            return false;
        }
        self::fxRatePublishInvalidate($pair);
        $state = self::fxRateGetCached($pair);
        if (!$state['ok']) {
            return false;
        }
        $staleMinutes = null;
        foreach ($enabled as $entry) {
            if ($entry['config']['manual_rate'] !== null) {
                continue;
            }
            $value = (int) $entry['config']['stale_after_minutes'];
            $staleMinutes = $staleMinutes === null ? $value : min($staleMinutes, $value);
        }
        if ($staleMinutes === null || !self::fxRateIsStale((int) $state['fetched_at'], $staleMinutes)) {
            return false;
        }
        if ((int) $state['stale_notified_at'] > (int) $state['fetched_at']) {
            return true;
        }
        try {
            $pdo->prepare('UPDATE fx_rate_cache SET stale_notified_at = ? WHERE pair = ?')->execute([time(), \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair)]);
        } catch (Throwable $e) {
        }
        $age = (int) floor((time() - (int) $state['fetched_at']) / 60);
        \Faoxima\Orders\Pricing\ExchangePricing::fxNotifyAdmins("⚠️ <b>نرخ USDT/IRT قدیمی شده است</b>\n\nآخرین نرخ معتبر: " . number_format((float) $state['rate']) . " تومان\nزمان آخرین به‌روزرسانی موفق: {$age} دقیقه پیش\n\nقیمت‌ها با آخرین نرخ معتبر ثابت نگه داشته شده‌اند. در صورت نیاز از بخش قیمت‌گذاری دلاری هر پنل، نرخ اضطراری دستی ثبت کنید.");
        return true;
    }

    public static function fxRateSync(string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizePair($pair);
        $pdo = \Faoxima\Orders\Pricing\ExchangePricing::fxPdo();
        if (!($pdo instanceof PDO) || !\Faoxima\Orders\Pricing\ExchangePricing::fxEnsureSchema($pdo)) {
            return ['ok' => false, 'action' => 'db_unavailable'];
        }
        $fetch = self::fxRateFetchSwapwallet($pair);
        if (!$fetch['ok']) {
            self::fxRateStoreError($pdo, $pair, (string) $fetch['error']);
            self::fxRatePublishInvalidate($pair);
            self::fxRateCheckStale($pdo, $pair);
            return ['ok' => false, 'action' => 'fetch_failed', 'error' => $fetch['error']];
        }
        try {
            $stored = self::fxRateStoreValid($pdo, $pair, (float) $fetch['rate'], 'swapwallet', self::fxRateMaxJumpPercent());
        } catch (Throwable $e) {
            \Faoxima\Orders\Pricing\ExchangePricing::fxLog('FX_RATE_STORE_FAILED', $e->getMessage());
            return ['ok' => false, 'action' => 'store_failed'];
        }
        self::fxRatePublishInvalidate($pair);
        if ($stored['action'] === 'jump_detected') {
            \Faoxima\Orders\Pricing\ExchangePricing::fxLog('FX_RATE_JUMP_PENDING', 'Abnormal rate held as pending', ['deviation' => round((float) $stored['deviation'], 2)]);
            \Faoxima\Orders\Pricing\ExchangePricing::fxNotifyAdmins("⚠️ <b>جهش غیرعادی نرخ USDT/IRT</b>\n\nنرخ فعال: " . number_format((float) $stored['previous_rate']) . " تومان\nنرخ دریافتی: " . number_format((float) $stored['rate']) . " تومان\nاختلاف: " . round((float) $stored['deviation'], 2) . "%\n\nاین نرخ فعلاً در حالت انتظار است و تا تایید در پاسخ‌های بعدی اعمال نمی‌شود.");
        } elseif ($stored['action'] === 'promoted') {
            \Faoxima\Orders\Pricing\ExchangePricing::fxLog('FX_RATE_PENDING_PROMOTED', 'Pending rate promoted after consecutive confirmations', ['deviation' => round((float) $stored['deviation'], 2)]);
            \Faoxima\Orders\Pricing\ExchangePricing::fxNotifyAdmins("ℹ️ <b>نرخ جدید USDT/IRT فعال شد</b>\n\nنرخ قبلی: " . number_format((float) $stored['previous_rate']) . " تومان\nنرخ جدید: " . number_format((float) $stored['rate']) . " تومان\n\nاین نرخ پس از تایید در پاسخ‌های متوالی API فعال شد.");
        }
        self::fxRateCheckStale($pdo, $pair);
        return ['ok' => true, 'action' => $stored['action'], 'rate' => $stored['rate']];
    }
}
