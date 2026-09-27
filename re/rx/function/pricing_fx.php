<?php

if (!defined('FX_DEFAULT_PAIR')) {
    define('FX_DEFAULT_PAIR', 'USDT/IRT');
}
if (!defined('FX_QUOTE_TTL_SECONDS')) {
    define('FX_QUOTE_TTL_SECONDS', 600);
}
if (!defined('FX_PENDING_TOLERANCE_PERCENT')) {
    define('FX_PENDING_TOLERANCE_PERCENT', 1.0);
}
if (!defined('FX_PENDING_CONFIRMATIONS')) {
    define('FX_PENDING_CONFIRMATIONS', 2);
}
if (!defined('FX_SWAPWALLET_ENDPOINT')) {
    define('FX_SWAPWALLET_ENDPOINT', 'https://swapwallet.app/api/v1/market/prices');
}

if (!function_exists('fx_pricing_kinds')) {
    function fx_pricing_kinds(): array
    {
        return [
            'product'       => 'apply_products',
            'custom_volume' => 'apply_custom_volume',
            'custom_time'   => 'apply_custom_time',
            'extra_volume'  => 'apply_extra_volume',
            'extra_time'    => 'apply_extra_time',
        ];
    }
}

if (!function_exists('fx_pdo')) {
    function fx_pdo(): ?PDO
    {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }
        if (function_exists('getDatabaseConnection')) {
            try {
                $connection = getDatabaseConnection();
                if ($connection instanceof PDO) {
                    return $connection;
                }
            } catch (Throwable $e) {
            }
        }
        if (class_exists('FaoximaDb')) {
            try {
                return FaoximaDb::pdo();
            } catch (Throwable $e) {
            }
        }
        return null;
    }
}

if (!function_exists('fx_log')) {
    function fx_log(string $type, string $message, array $context = []): void
    {
        if (function_exists('rx_log_event')) {
            try {
                rx_log_event($type, $message, $context);
                return;
            } catch (Throwable $e) {
            }
        }
        error_log('[' . $type . '] ' . $message);
    }
}

if (!function_exists('fx_ensure_schema')) {
    function fx_ensure_schema(?PDO $pdo = null): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $pdo = $pdo ?: fx_pdo();
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS fx_rate_cache (
                pair VARCHAR(20) NOT NULL PRIMARY KEY,
                rate DECIMAL(18,4) NOT NULL,
                previous_rate DECIMAL(18,4) NULL,
                pending_rate DECIMAL(18,4) NULL,
                pending_count INT UNSIGNED NOT NULL DEFAULT 0,
                source VARCHAR(50) NOT NULL,
                fetched_at INT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ok',
                last_error VARCHAR(500) NULL,
                last_event VARCHAR(500) NULL,
                stale_notified_at INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS fx_price_quote (
                user_id VARCHAR(64) NOT NULL,
                context VARCHAR(100) NOT NULL,
                amount BIGINT NOT NULL,
                meta TEXT NULL,
                created_at INT UNSIGNED NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                PRIMARY KEY (user_id, context),
                KEY idx_fx_quote_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ready = true;
        } catch (Throwable $e) {
            fx_log('FX_SCHEMA_FAILED', $e->getMessage());
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('fx_table_has_column')) {
    function fx_table_has_column(string $table, string $column): bool
    {
        static $memo = [];
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', $column);
        $key = $table . '.' . $column;
        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO) || $table === '' || $column === '') {
            return false;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
            $memo[$key] = $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable $e) {
            $memo[$key] = false;
        }
        return $memo[$key];
    }
}

if (!function_exists('fx_normalize_pair')) {
    function fx_normalize_pair($pair): string
    {
        $pair = strtoupper(trim((string) $pair));
        return $pair === FX_DEFAULT_PAIR ? $pair : FX_DEFAULT_PAIR;
    }
}

if (!function_exists('fx_ascii_digits')) {
    function fx_ascii_digits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '٬' => '',
        ]);
    }
}

if (!function_exists('fx_normalize_amount')) {
    function fx_normalize_amount($value): ?float
    {
        if (is_bool($value) || $value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } else {
            $text = trim(fx_ascii_digits((string) $value));
            $text = str_replace([',', ' '], '', $text);
            if ($text === '' || !preg_match('/^-?\d+(\.\d+)?$/', $text)) {
                return null;
            }
            $number = (float) $text;
        }
        if (is_nan($number) || is_infinite($number) || $number < 0) {
            return null;
        }
        return $number;
    }
}

if (!function_exists('fx_positive_number')) {
    function fx_positive_number($value): ?float
    {
        $number = fx_normalize_amount($value);
        return ($number !== null && $number > 0) ? $number : null;
    }
}

if (!function_exists('fx_default_config')) {
    function fx_default_config(): array
    {
        return [
            'enabled'             => '0',
            'pair'                => FX_DEFAULT_PAIR,
            'base_rate'           => null,
            'markup_percent'      => 0,
            'round_step'          => 1000,
            'stale_after_minutes' => 360,
            'max_jump_percent'    => 15,
            'manual_rate'         => null,
            'apply_products'      => '1',
            'apply_custom_volume' => '1',
            'apply_custom_time'   => '1',
            'apply_extra_volume'  => '1',
            'apply_extra_time'    => '1',
            'enabled_at'          => null,
        ];
    }
}

if (!function_exists('fx_sanitize_config')) {
    function fx_sanitize_config(array $input): array
    {
        $config = fx_default_config();
        $flag = static function ($value, string $fallback): string {
            if ($value === true || $value === 1 || $value === '1' || $value === 'on') {
                return '1';
            }
            if ($value === false || $value === 0 || $value === '0' || $value === 'off') {
                return '0';
            }
            return $fallback;
        };
        $config['enabled'] = $flag($input['enabled'] ?? null, '0');
        $config['pair'] = fx_normalize_pair($input['pair'] ?? FX_DEFAULT_PAIR);
        $config['base_rate'] = fx_positive_number($input['base_rate'] ?? null);
        $markup = $input['markup_percent'] ?? 0;
        $markupText = is_string($markup) ? trim(fx_ascii_digits($markup)) : $markup;
        $config['markup_percent'] = (is_numeric($markupText) && is_finite((float) $markupText))
            ? max(-90.0, min(1000.0, (float) $markupText)) : 0;
        $roundStep = fx_normalize_amount($input['round_step'] ?? 1000);
        $config['round_step'] = $roundStep === null ? 1000 : (int) max(1, min(1000000, floor($roundStep)));
        $stale = fx_normalize_amount($input['stale_after_minutes'] ?? 360);
        $config['stale_after_minutes'] = $stale === null ? 360 : (int) max(5, min(10080, floor($stale)));
        $jump = fx_normalize_amount($input['max_jump_percent'] ?? 15);
        $config['max_jump_percent'] = $jump === null ? 15 : max(1.0, min(100.0, $jump));
        $config['manual_rate'] = fx_positive_number($input['manual_rate'] ?? null);
        foreach (fx_pricing_kinds() as $applyKey) {
            $config[$applyKey] = $flag($input[$applyKey] ?? null, '1');
        }
        $enabledAt = $input['enabled_at'] ?? null;
        $config['enabled_at'] = is_numeric($enabledAt) ? (int) $enabledAt : null;
        if ($config['enabled'] === '1' && $config['base_rate'] === null) {
            $config['enabled'] = '0';
        }
        return $config;
    }
}

if (!function_exists('fx_panel_config')) {
    function fx_panel_config(array $panel): array
    {
        $raw = $panel['fx_pricing_config'] ?? null;
        if (is_array($raw)) {
            return fx_sanitize_config($raw);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return fx_default_config();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return fx_default_config();
        }
        return fx_sanitize_config($decoded);
    }
}

if (!function_exists('fx_panel_enabled')) {
    function fx_panel_enabled(array $panel): bool
    {
        $config = fx_panel_config($panel);
        return $config['enabled'] === '1' && $config['base_rate'] !== null;
    }
}

if (!function_exists('fx_kind_enabled')) {
    function fx_kind_enabled(array $panel, string $priceKind): bool
    {
        $kinds = fx_pricing_kinds();
        if (!isset($kinds[$priceKind]) || !fx_panel_enabled($panel)) {
            return false;
        }
        return fx_panel_config($panel)[$kinds[$priceKind]] === '1';
    }
}

if (!function_exists('fx_rate_memo_reset')) {
    function fx_rate_memo_reset(?string $pair = null): void
    {
        if ($pair === null) {
            $GLOBALS['__fx_rate_memo'] = [];
            $GLOBALS['__fx_context_memo'] = [];
            return;
        }
        unset($GLOBALS['__fx_rate_memo'][fx_normalize_pair($pair)]);
        $GLOBALS['__fx_context_memo'] = [];
    }
}

if (!function_exists('fx_rate_redis_key')) {
    function fx_rate_redis_key(string $pair): string
    {
        return 'fx_rate_cache:' . str_replace('/', '_', fx_normalize_pair($pair));
    }
}

if (!function_exists('fx_rate_row_to_state')) {
    function fx_rate_row_to_state(string $pair, $row): array
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
        $rate = fx_positive_number($row['rate'] ?? null);
        $state['rate'] = $rate;
        $state['previous_rate'] = fx_positive_number($row['previous_rate'] ?? null);
        $state['pending_rate'] = fx_positive_number($row['pending_rate'] ?? null);
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
}

if (!function_exists('fx_rate_get_cached')) {
    function fx_rate_get_cached(string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = fx_normalize_pair($pair);
        if (isset($GLOBALS['__fx_rate_memo'][$pair])) {
            return $GLOBALS['__fx_rate_memo'][$pair];
        }
        $redisKey = fx_rate_redis_key($pair);
        if (function_exists('rx_redis_get')) {
            try {
                $cachedRaw = rx_redis_get($redisKey);
                if (is_string($cachedRaw) && $cachedRaw !== '') {
                    $cachedRow = json_decode($cachedRaw, true);
                    $state = fx_rate_row_to_state($pair, $cachedRow);
                    if ($state['ok']) {
                        $GLOBALS['__fx_rate_memo'][$pair] = $state;
                        return $state;
                    }
                }
            } catch (Throwable $e) {
            }
        }
        $row = null;
        $pdo = fx_pdo();
        if ($pdo instanceof PDO && fx_ensure_schema($pdo)) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM fx_rate_cache WHERE pair = ? LIMIT 1');
                $stmt->execute([$pair]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                fx_log('FX_RATE_READ_FAILED', $e->getMessage());
            }
        }
        $state = fx_rate_row_to_state($pair, $row);
        if ($state['ok'] && function_exists('rx_redis_set')) {
            try {
                rx_redis_set($redisKey, json_encode($row), 60);
            } catch (Throwable $e) {
            }
        }
        $GLOBALS['__fx_rate_memo'][$pair] = $state;
        return $state;
    }
}

if (!function_exists('fx_rate_is_stale')) {
    function fx_rate_is_stale(int $fetchedAt, int $staleAfterMinutes): bool
    {
        if ($fetchedAt <= 0) {
            return true;
        }
        return (time() - $fetchedAt) > max(1, $staleAfterMinutes) * 60;
    }
}

if (!function_exists('fx_rate_get_effective')) {
    function fx_rate_get_effective(string $pair = FX_DEFAULT_PAIR, ?array $config = null): array
    {
        $pair = fx_normalize_pair($pair);
        $config = $config === null ? fx_default_config() : fx_sanitize_config($config);
        $cached = fx_rate_get_cached($pair);
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
            'stale'      => fx_rate_is_stale((int) $cached['fetched_at'], (int) $config['stale_after_minutes']),
            'api'        => $cached,
        ];
    }
}

if (!function_exists('fx_context')) {
    function fx_context(array $panel, $priceKinds): ?array
    {
        $kinds = is_array($priceKinds) ? $priceKinds : [$priceKinds];
        $activeKind = null;
        foreach ($kinds as $kind) {
            if (fx_kind_enabled($panel, (string) $kind)) {
                $activeKind = (string) $kind;
                break;
            }
        }
        if ($activeKind === null) {
            return null;
        }
        $config = fx_panel_config($panel);
        $memoKey = (string) ($panel['code_panel'] ?? $panel['name_panel'] ?? '') . '|' . md5(json_encode($config));
        if (isset($GLOBALS['__fx_context_memo'][$memoKey])) {
            $cachedContext = $GLOBALS['__fx_context_memo'][$memoKey];
            $cachedContext['kind'] = $activeKind;
            return $cachedContext;
        }
        $effective = fx_rate_get_effective($config['pair'], $config);
        $currentRate = $effective['rate'];
        $source = $effective['source'];
        if (!$effective['ok'] || $currentRate === null) {
            fx_log('FX_RATE_UNAVAILABLE', 'No valid cached rate; using panel base rate', ['panel' => (string) ($panel['code_panel'] ?? '')]);
            $currentRate = $config['base_rate'];
            $source = 'base_rate_fallback';
        }
        $multiplier = ($currentRate / $config['base_rate']) * (1 + ((float) $config['markup_percent'] / 100));
        if (is_nan($multiplier) || is_infinite($multiplier) || $multiplier <= 0) {
            fx_log('FX_MULTIPLIER_INVALID', 'Invalid multiplier', ['panel' => (string) ($panel['code_panel'] ?? '')]);
            return null;
        }
        $context = [
            'kind'           => $activeKind,
            'code_panel'     => (string) ($panel['code_panel'] ?? ''),
            'pair'           => $config['pair'],
            'current_rate'   => (float) $currentRate,
            'base_rate'      => (float) $config['base_rate'],
            'markup_percent' => (float) $config['markup_percent'],
            'round_step'     => (int) $config['round_step'],
            'multiplier'     => $multiplier,
            'source'         => $source,
            'manual'         => !empty($effective['manual']),
            'fetched_at'     => (int) $effective['fetched_at'],
            'stale'          => !empty($effective['stale']),
        ];
        $GLOBALS['__fx_context_memo'][$memoKey] = $context;
        return $context;
    }
}

if (!function_exists('fx_round_price')) {
    function fx_round_price(float $price, int $roundStep): int
    {
        if (is_nan($price) || is_infinite($price) || $price <= 0) {
            return 0;
        }
        $step = max(1, $roundStep);
        return (int) (ceil(round($price / $step, 6)) * $step);
    }
}

if (!function_exists('fx_adjust_base_toman')) {
    function fx_adjust_base_toman($baseToman, array $panel, string $priceKind)
    {
        $context = fx_context($panel, $priceKind);
        if ($context === null) {
            return $baseToman;
        }
        $base = fx_normalize_amount($baseToman);
        if ($base === null) {
            if ($baseToman !== null && $baseToman !== '') {
                fx_log('FX_INVALID_BASE_PRICE', 'Base price is not a valid non-negative number', ['panel' => $context['code_panel'], 'kind' => $priceKind]);
            }
            return $baseToman;
        }
        if ($base == 0.0) {
            return 0;
        }
        $step = $priceKind === 'product' ? $context['round_step'] : 1;
        return fx_round_price($base * $context['multiplier'], $step);
    }
}

if (!function_exists('fx_apply_to_product')) {
    function fx_apply_to_product(array $product, array $panel): array
    {
        if (!empty($product['_fx_applied']) || !array_key_exists('price_product', $product)) {
            return $product;
        }
        if (fx_context($panel, 'product') === null) {
            return $product;
        }
        $product['fx_base_price'] = $product['price_product'];
        $product['price_product'] = fx_adjust_base_toman($product['price_product'], $panel, 'product');
        $product['_fx_applied'] = 1;
        return $product;
    }
}

if (!function_exists('fx_finalize_amount')) {
    function fx_finalize_amount($amount, array $panel, $priceKinds)
    {
        $context = fx_context($panel, $priceKinds);
        if ($context === null) {
            return $amount;
        }
        if (is_numeric($amount) && (float) $amount < 0) {
            return 0;
        }
        $value = fx_normalize_amount($amount);
        if ($value === null) {
            return $amount;
        }
        return fx_round_price($value, $context['round_step']);
    }
}

if (!function_exists('fx_apply_user_discount')) {
    function fx_apply_user_discount($amount, $discountPercent)
    {
        if (intval($discountPercent) == 0) {
            return $amount;
        }
        return $amount - (($amount * $discountPercent) / 100);
    }
}

if (!function_exists('fx_pricing_snapshot')) {
    function fx_pricing_snapshot(array $panel, $priceKinds, $basePrice, $finalPrice, $discountAmount = 0, array $extra = []): ?array
    {
        $context = fx_context($panel, $priceKinds);
        if ($context === null) {
            return null;
        }
        return array_merge([
            'mode'            => 'usdt_ratio',
            'pair'            => $context['pair'],
            'kind'            => $context['kind'],
            'panel'           => $context['code_panel'],
            'base_price'      => (float) (fx_normalize_amount($basePrice) ?? 0),
            'base_rate'       => $context['base_rate'],
            'current_rate'    => $context['current_rate'],
            'multiplier'      => round($context['multiplier'], 8),
            'markup_percent'  => $context['markup_percent'],
            'discount_amount' => (float) (is_numeric($discountAmount) ? $discountAmount : 0),
            'round_step'      => $context['round_step'],
            'final_price'     => (float) (is_numeric($finalPrice) ? $finalPrice : 0),
            'source'          => $context['source'],
            'manual_rate'     => $context['manual'],
            'rate_fetched_at' => $context['fetched_at'],
            'rate_stale'      => $context['stale'],
            'created_at'      => time(),
        ], $extra);
    }
}

if (!function_exists('fx_panel_raw_unit_price')) {
    function fx_panel_raw_unit_price(array $panel, string $column, $agent): ?float
    {
        $map = json_decode((string) ($panel[$column] ?? ''), true);
        if (!is_array($map)) {
            return null;
        }
        return fx_normalize_amount($map[(string) $agent] ?? null);
    }
}

if (!function_exists('fx_custom_base_price')) {
    function fx_custom_base_price(array $panel, $agent, $volumeGb, $days): float
    {
        $volumeUnit = fx_panel_raw_unit_price($panel, 'pricecustomvolume', $agent) ?? 0.0;
        $timeUnit = fx_panel_raw_unit_price($panel, 'pricecustomtime', $agent) ?? 0.0;
        return ((float) $volumeGb * $volumeUnit) + ((float) $days * $timeUnit);
    }
}

if (!function_exists('fx_store_invoice_snapshot')) {
    function fx_store_invoice_snapshot($idInvoice, ?array $snapshot): bool
    {
        $idInvoice = (string) $idInvoice;
        if ($snapshot === null || $idInvoice === '' || !fx_table_has_column('invoice', 'pricing_snapshot')) {
            return false;
        }
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('UPDATE invoice SET pricing_snapshot = ? WHERE id_invoice = ?');
            $stmt->execute([json_encode($snapshot, JSON_UNESCAPED_UNICODE), $idInvoice]);
            if (function_exists('clearSelectCache')) {
                clearSelectCache('invoice');
            }
            return true;
        } catch (Throwable $e) {
            fx_log('FX_SNAPSHOT_STORE_FAILED', $e->getMessage(), ['invoice' => $idInvoice]);
            return false;
        }
    }
}

if (!function_exists('fx_value_with_snapshot')) {
    function fx_value_with_snapshot($valueJson, ?array $snapshot)
    {
        if ($snapshot === null || !is_string($valueJson)) {
            return $valueJson;
        }
        $decoded = json_decode($valueJson, true);
        if (!is_array($decoded)) {
            return $valueJson;
        }
        $decoded['pricing_snapshot'] = $snapshot;
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? $valueJson : $encoded;
    }
}

if (!function_exists('fx_quote_context_key')) {
    function fx_quote_context_key(string $flow, string $reference = ''): string
    {
        return substr($flow, 0, 60) . ':' . substr(sha1($reference), 0, 16);
    }
}

if (!function_exists('fx_quote_put')) {
    function fx_quote_put($userId, string $contextKey, $amount, array $meta = []): bool
    {
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO) || !fx_ensure_schema($pdo)) {
            return false;
        }
        $now = time();
        try {
            $stmt = $pdo->prepare('INSERT INTO fx_price_quote (user_id, context, amount, meta, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount), meta = VALUES(meta), created_at = VALUES(created_at), expires_at = VALUES(expires_at)');
            $stmt->execute([(string) $userId, $contextKey, (int) round((float) $amount), json_encode($meta, JSON_UNESCAPED_UNICODE), $now, $now + FX_QUOTE_TTL_SECONDS]);
            if (mt_rand(1, 50) === 1) {
                $pdo->prepare('DELETE FROM fx_price_quote WHERE expires_at < ?')->execute([$now - 86400]);
            }
            return true;
        } catch (Throwable $e) {
            fx_log('FX_QUOTE_STORE_FAILED', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('fx_quote_get')) {
    function fx_quote_get($userId, string $contextKey): ?array
    {
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO) || !fx_ensure_schema($pdo)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT amount, meta, created_at, expires_at FROM fx_price_quote WHERE user_id = ? AND context = ? LIMIT 1');
            $stmt->execute([(string) $userId, $contextKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        if (!is_array($row) || (int) $row['expires_at'] < time()) {
            return null;
        }
        $meta = json_decode((string) ($row['meta'] ?? ''), true);
        return [
            'amount'     => (int) $row['amount'],
            'meta'       => is_array($meta) ? $meta : [],
            'created_at' => (int) $row['created_at'],
            'expires_at' => (int) $row['expires_at'],
        ];
    }
}

if (!function_exists('fx_quote_matches')) {
    function fx_quote_matches($userId, string $contextKey, $amount): bool
    {
        $quote = fx_quote_get($userId, $contextKey);
        return $quote !== null && $quote['amount'] === (int) round((float) $amount);
    }
}

if (!function_exists('fx_quote_forget')) {
    function fx_quote_forget($userId, string $contextKey): void
    {
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO) || !fx_ensure_schema($pdo)) {
            return;
        }
        try {
            $pdo->prepare('DELETE FROM fx_price_quote WHERE user_id = ? AND context = ?')->execute([(string) $userId, $contextKey]);
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('fx_quote_store')) {
    function fx_quote_store($userId, string $contextKey, $amount, array $panel, $priceKinds, array $meta = []): bool
    {
        $context = fx_context($panel, $priceKinds);
        if ($context === null) {
            return false;
        }
        $meta['f'] = fx_context_fingerprint($context);
        $meta['rate'] = $context['current_rate'];
        $meta['base_rate'] = $context['base_rate'];
        $meta['rate_ts'] = $context['fetched_at'];
        return fx_quote_put($userId, $contextKey, $amount, $meta);
    }
}

if (!function_exists('fx_quote_check')) {
    function fx_quote_check($userId, string $contextKey, $amount, array $panel, $priceKinds): bool
    {
        $context = fx_context($panel, $priceKinds);
        if ($context === null) {
            return true;
        }
        $quote = fx_quote_get($userId, $contextKey);
        if ($quote === null || $quote['amount'] !== (int) round((float) $amount)) {
            return false;
        }
        return hash_equals(fx_context_fingerprint($context), (string) ($quote['meta']['f'] ?? ''));
    }
}

if (!function_exists('fx_quote_rate_unchanged')) {
    function fx_quote_rate_unchanged($userId, string $contextKey, array $panel, $priceKinds): bool
    {
        $context = fx_context($panel, $priceKinds);
        if ($context === null) {
            return true;
        }
        $quote = fx_quote_get($userId, $contextKey);
        return $quote !== null && hash_equals(fx_context_fingerprint($context), (string) ($quote['meta']['f'] ?? ''));
    }
}

if (!function_exists('fx_apply_discount_rule')) {
    function fx_apply_discount_rule($price, string $valueType, float $value)
    {
        if ($valueType === 'free') {
            return 0;
        }
        if ($valueType === 'amount') {
            $result = $price - $value;
        } else {
            $result = $price - (($value / 100) * $price);
        }
        $result = round($result);
        return $result < 0 ? 0 : $result;
    }
}

if (!function_exists('fx_price_changed_text')) {
    function fx_price_changed_text($newAmount): string
    {
        $formatted = number_format((float) $newAmount);
        return "⚠️ به دلیل تغییر نرخ دلار (USDT)، مبلغ فاکتور به‌روزرسانی شد.\n\n💰 مبلغ جدید: {$formatted} تومان\n\n✅ در صورت موافقت، دوباره روی دکمه تایید بزنید.";
    }
}

if (!function_exists('fx_price_changed_api_message')) {
    function fx_price_changed_api_message(): string
    {
        return 'به دلیل تغییر نرخ دلار (USDT)، قیمت به‌روزرسانی شد. لطفاً مبلغ جدید را تایید کنید.';
    }
}

if (!function_exists('fx_price_changed_api_obj')) {
    function fx_price_changed_api_obj($newPrice, ?string $token): array
    {
        return ['code' => 'price_changed', 'price' => $newPrice, 'fx_quote' => $token];
    }
}

if (!function_exists('fx_quote_secret')) {
    function fx_quote_secret(): string
    {
        $material = (string) ($GLOBALS['APIKEY'] ?? '') . '|' . (string) ($GLOBALS['passworddb'] ?? '') . '|' . (string) ($GLOBALS['dbname'] ?? '');
        return hash('sha256', 'fx_quote|' . $material);
    }
}

if (!function_exists('fx_base64url_encode')) {
    function fx_base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('fx_base64url_decode')) {
    function fx_base64url_decode(string $data)
    {
        $padded = strtr($data, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode($padded, true);
    }
}

if (!function_exists('fx_context_fingerprint')) {
    function fx_context_fingerprint(array $context): string
    {
        return hash('sha256', implode('|', [
            $context['code_panel'],
            number_format($context['current_rate'], 4, '.', ''),
            number_format($context['base_rate'], 4, '.', ''),
            number_format($context['markup_percent'], 4, '.', ''),
            (string) $context['round_step'],
        ]));
    }
}

if (!function_exists('fx_quote_token')) {
    function fx_quote_token(array $panel, $priceKinds): ?string
    {
        $context = fx_context($panel, $priceKinds);
        if ($context === null) {
            return null;
        }
        $payload = fx_base64url_encode(json_encode([
            'p' => $context['code_panel'],
            'f' => fx_context_fingerprint($context),
            't' => $context['fetched_at'],
            'e' => time() + FX_QUOTE_TTL_SECONDS,
        ]));
        return $payload . '.' . fx_base64url_encode(hash_hmac('sha256', $payload, fx_quote_secret(), true));
    }
}

if (!function_exists('fx_quote_token_valid')) {
    function fx_quote_token_valid($token, array $panel, $priceKinds): bool
    {
        $context = fx_context($panel, $priceKinds);
        if ($context === null) {
            return true;
        }
        if (!is_string($token) || strpos($token, '.') === false) {
            return false;
        }
        [$payload, $signature] = explode('.', $token, 2);
        $expected = fx_base64url_encode(hash_hmac('sha256', $payload, fx_quote_secret(), true));
        if (!hash_equals($expected, $signature)) {
            return false;
        }
        $decodedPayload = fx_base64url_decode($payload);
        $data = is_string($decodedPayload) ? json_decode($decodedPayload, true) : null;
        if (!is_array($data) || (int) ($data['e'] ?? 0) < time()) {
            return false;
        }
        return (string) ($data['p'] ?? '') === $context['code_panel']
            && hash_equals(fx_context_fingerprint($context), (string) ($data['f'] ?? ''));
    }
}

if (!function_exists('fx_parse_rate_value')) {
    function fx_parse_rate_value($value): ?float
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
}

if (!function_exists('fx_rate_fetch_swapwallet')) {
    function fx_rate_fetch_swapwallet(string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = fx_normalize_pair($pair);
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
        return fx_rate_parse_market_response((string) $body, $pair);
    }
}

if (!function_exists('fx_rate_parse_market_response')) {
    function fx_rate_parse_market_response(string $body, string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = fx_normalize_pair($pair);
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
        $rate = fx_parse_rate_value($rawValue);
        if ($rate === null) {
            return ['ok' => false, 'rate' => null, 'error' => 'invalid_rate_value'];
        }
        return ['ok' => true, 'rate' => $rate, 'error' => null];
    }
}

if (!function_exists('fx_enabled_panel_configs')) {
    function fx_enabled_panel_configs(): array
    {
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO) || !fx_table_has_column('marzban_panel', 'fx_pricing_config')) {
            return [];
        }
        try {
            $stmt = $pdo->query("SELECT code_panel, name_panel, fx_pricing_config FROM marzban_panel WHERE fx_pricing_config IS NOT NULL AND fx_pricing_config <> ''");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $config = fx_panel_config($row);
            if ($config['enabled'] === '1') {
                $result[] = ['panel' => $row, 'config' => $config];
            }
        }
        return $result;
    }
}

if (!function_exists('fx_rate_max_jump_percent')) {
    function fx_rate_max_jump_percent(): float
    {
        $limit = null;
        foreach (fx_enabled_panel_configs() as $entry) {
            $value = (float) $entry['config']['max_jump_percent'];
            $limit = $limit === null ? $value : min($limit, $value);
        }
        return $limit === null ? 15.0 : $limit;
    }
}

if (!function_exists('fx_rate_store_valid')) {
    function fx_rate_store_valid(PDO $pdo, string $pair, float $rate, string $source, float $maxJumpPercent): array
    {
        $pair = fx_normalize_pair($pair);
        $now = time();
        $result = ['action' => 'none', 'rate' => $rate, 'previous_rate' => null, 'deviation' => null];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM fx_rate_cache WHERE pair = ? FOR UPDATE');
            $stmt->execute([$pair]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current = is_array($row) ? fx_positive_number($row['rate'] ?? null) : null;
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
                $previous = abs($rate - $current) > 0.00001 ? $current : fx_positive_number($row['previous_rate'] ?? null);
                $pdo->prepare("UPDATE fx_rate_cache SET rate = ?, previous_rate = ?, pending_rate = NULL, pending_count = 0, source = ?, fetched_at = ?, status = 'ok', last_error = NULL, stale_notified_at = 0, updated_at = ? WHERE pair = ?")
                    ->execute([$rate, $previous, $source, $now, $now, $pair]);
                $pdo->commit();
                $result['action'] = 'updated';
                return $result;
            }
            $pending = fx_positive_number($row['pending_rate'] ?? null);
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
}

if (!function_exists('fx_rate_store_error')) {
    function fx_rate_store_error(PDO $pdo, string $pair, string $error): void
    {
        $safeError = substr(preg_replace('/[^A-Za-z0-9_\-\.: ]/', '', $error), 0, 200);
        $now = time();
        try {
            $pdo->prepare("UPDATE fx_rate_cache SET last_error = ?, status = CASE WHEN status = 'pending' THEN 'pending' ELSE 'error' END, updated_at = ? WHERE pair = ?")
                ->execute([$safeError . ' @' . $now, $now, fx_normalize_pair($pair)]);
        } catch (Throwable $e) {
            fx_log('FX_RATE_ERROR_STORE_FAILED', $e->getMessage());
        }
    }
}

if (!function_exists('fx_rate_publish_invalidate')) {
    function fx_rate_publish_invalidate(string $pair): void
    {
        fx_rate_memo_reset($pair);
        if (function_exists('rx_redis_del')) {
            try {
                rx_redis_del(fx_rate_redis_key($pair));
            } catch (Throwable $e) {
            }
        }
    }
}

if (!function_exists('fx_notify_admins')) {
    function fx_notify_admins(string $text): void
    {
        if (!function_exists('telegram')) {
            return;
        }
        try {
            $setting = function_exists('select') ? select('setting', '*') : null;
            $channel = is_array($setting) ? trim((string) ($setting['Channel_Report'] ?? '')) : '';
            if ($channel !== '' && $channel !== '0') {
                $topicRow = select('topicid', 'idreport', 'report', 'errorreport', 'select');
                $topic = is_array($topicRow) ? (string) ($topicRow['idreport'] ?? '') : '';
                $params = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML'];
                if ($topic !== '' && $topic !== '-1') {
                    $params['message_thread_id'] = $topic;
                }
                telegram('sendmessage', $params);
                return;
            }
            $admins = select('admin', 'id_admin', null, null, 'FETCH_COLUMN');
            if (is_array($admins)) {
                foreach ($admins as $adminId) {
                    if (is_numeric($adminId)) {
                        telegram('sendmessage', ['chat_id' => $adminId, 'text' => $text, 'parse_mode' => 'HTML']);
                    }
                }
            }
        } catch (Throwable $e) {
            fx_log('FX_NOTIFY_FAILED', $e->getMessage());
        }
    }
}

if (!function_exists('fx_rate_check_stale')) {
    function fx_rate_check_stale(PDO $pdo, string $pair): bool
    {
        $enabled = fx_enabled_panel_configs();
        if (empty($enabled)) {
            return false;
        }
        fx_rate_publish_invalidate($pair);
        $state = fx_rate_get_cached($pair);
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
        if ($staleMinutes === null || !fx_rate_is_stale((int) $state['fetched_at'], $staleMinutes)) {
            return false;
        }
        if ((int) $state['stale_notified_at'] > (int) $state['fetched_at']) {
            return true;
        }
        try {
            $pdo->prepare('UPDATE fx_rate_cache SET stale_notified_at = ? WHERE pair = ?')->execute([time(), fx_normalize_pair($pair)]);
        } catch (Throwable $e) {
        }
        $age = (int) floor((time() - (int) $state['fetched_at']) / 60);
        fx_notify_admins("⚠️ <b>نرخ USDT/IRT قدیمی شده است</b>\n\nآخرین نرخ معتبر: " . number_format((float) $state['rate']) . " تومان\nزمان آخرین به‌روزرسانی موفق: {$age} دقیقه پیش\n\nقیمت‌ها با آخرین نرخ معتبر ثابت نگه داشته شده‌اند. در صورت نیاز از بخش قیمت‌گذاری دلاری هر پنل، نرخ اضطراری دستی ثبت کنید.");
        return true;
    }
}

if (!function_exists('fx_rate_sync')) {
    function fx_rate_sync(string $pair = FX_DEFAULT_PAIR): array
    {
        $pair = fx_normalize_pair($pair);
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO) || !fx_ensure_schema($pdo)) {
            return ['ok' => false, 'action' => 'db_unavailable'];
        }
        $fetch = fx_rate_fetch_swapwallet($pair);
        if (!$fetch['ok']) {
            fx_rate_store_error($pdo, $pair, (string) $fetch['error']);
            fx_rate_publish_invalidate($pair);
            fx_rate_check_stale($pdo, $pair);
            return ['ok' => false, 'action' => 'fetch_failed', 'error' => $fetch['error']];
        }
        try {
            $stored = fx_rate_store_valid($pdo, $pair, (float) $fetch['rate'], 'swapwallet', fx_rate_max_jump_percent());
        } catch (Throwable $e) {
            fx_log('FX_RATE_STORE_FAILED', $e->getMessage());
            return ['ok' => false, 'action' => 'store_failed'];
        }
        fx_rate_publish_invalidate($pair);
        if ($stored['action'] === 'jump_detected') {
            fx_log('FX_RATE_JUMP_PENDING', 'Abnormal rate held as pending', ['deviation' => round((float) $stored['deviation'], 2)]);
            fx_notify_admins("⚠️ <b>جهش غیرعادی نرخ USDT/IRT</b>\n\nنرخ فعال: " . number_format((float) $stored['previous_rate']) . " تومان\nنرخ دریافتی: " . number_format((float) $stored['rate']) . " تومان\nاختلاف: " . round((float) $stored['deviation'], 2) . "%\n\nاین نرخ فعلاً در حالت انتظار است و تا تایید در پاسخ‌های بعدی اعمال نمی‌شود.");
        } elseif ($stored['action'] === 'promoted') {
            fx_log('FX_RATE_PENDING_PROMOTED', 'Pending rate promoted after consecutive confirmations', ['deviation' => round((float) $stored['deviation'], 2)]);
            fx_notify_admins("ℹ️ <b>نرخ جدید USDT/IRT فعال شد</b>\n\nنرخ قبلی: " . number_format((float) $stored['previous_rate']) . " تومان\nنرخ جدید: " . number_format((float) $stored['rate']) . " تومان\n\nاین نرخ پس از تایید در پاسخ‌های متوالی API فعال شد.");
        }
        fx_rate_check_stale($pdo, $pair);
        return ['ok' => true, 'action' => $stored['action'], 'rate' => $stored['rate']];
    }
}

if (!function_exists('fx_ensure_panel_column')) {
    function fx_ensure_panel_column(): bool
    {
        if (fx_table_has_column('marzban_panel', 'fx_pricing_config')) {
            return true;
        }
        $pdo = fx_pdo();
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->exec('ALTER TABLE `marzban_panel` ADD COLUMN `fx_pricing_config` TEXT NULL');
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                fx_log('FX_PANEL_COLUMN_FAILED', $e->getMessage());
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('fx_panel_save_config')) {
    function fx_panel_save_config(array $panel, array $config): bool
    {
        $code = (string) ($panel['code_panel'] ?? '');
        $pdo = fx_pdo();
        if ($code === '' || !($pdo instanceof PDO) || !fx_ensure_panel_column()) {
            return false;
        }
        $clean = fx_sanitize_config($config);
        try {
            $stmt = $pdo->prepare('UPDATE marzban_panel SET fx_pricing_config = ? WHERE code_panel = ?');
            $stmt->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $code]);
        } catch (Throwable $e) {
            fx_log('FX_PANEL_SAVE_FAILED', $e->getMessage(), ['panel' => $code]);
            return false;
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('marzban_panel');
        }
        $GLOBALS['__fx_context_memo'] = [];
        return true;
    }
}

if (!function_exists('fx_admin_load_panel')) {
    function fx_admin_load_panel($code): ?array
    {
        $code = (string) $code;
        $pdo = fx_pdo();
        if ($code === '' || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $code) || !($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM marzban_panel WHERE code_panel = ? LIMIT 1');
            $stmt->execute([$code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('fx_admin_button_label')) {
    function fx_admin_button_label(): string
    {
        return '💵 قیمت‌گذاری دلاری (USDT)';
    }
}

if (!function_exists('fx_parse_admin_number')) {
    function fx_parse_admin_number($text, bool $allowNegative = false): ?float
    {
        $clean = str_replace([',', ' ', '%', '٪'], '', trim(fx_ascii_digits((string) $text)));
        if ($clean === '' || !preg_match($allowNegative ? '/^-?\d+(\.\d+)?$/' : '/^\d+(\.\d+)?$/', $clean)) {
            return null;
        }
        $number = (float) $clean;
        return (is_nan($number) || is_infinite($number)) ? null : $number;
    }
}

if (!function_exists('fx_format_toman')) {
    function fx_format_toman($value): string
    {
        return $value === null ? '—' : number_format((float) $value, (floor((float) $value) == (float) $value) ? 0 : 2) . ' تومان';
    }
}

if (!function_exists('fx_admin_menu_render')) {
    function fx_admin_menu_render(array $panel): array
    {
        $config = fx_panel_config($panel);
        $code = (string) $panel['code_panel'];
        $api = fx_rate_get_cached($config['pair']);
        $effective = fx_rate_get_effective($config['pair'], $config);
        $flag = static function (string $value): string {
            return $value === '1' ? '✅' : '❌';
        };
        $lines = [
            '💵 <b>قیمت‌گذاری دلاری (USDT/IRT)</b>',
            '',
            '🖥 پنل: ' . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8'),
            '📌 وضعیت: ' . ($config['enabled'] === '1' ? '✅ فعال' : '❌ غیرفعال'),
            '💱 نرخ فعلی مؤثر: ' . ($effective['ok'] ? fx_format_toman($effective['rate']) : '— (نرخ معتبری در دسترس نیست)'),
            '🌐 نرخ API ذخیره‌شده: ' . ($api['ok'] ? fx_format_toman($api['rate']) : '—'),
            '🏁 نرخ پایه پنل: ' . fx_format_toman($config['base_rate']),
        ];
        if ($config['base_rate'] !== null && $effective['ok']) {
            $multiplier = ($effective['rate'] / $config['base_rate']) * (1 + $config['markup_percent'] / 100);
            $lines[] = '✖️ ضریب فعلی: ' . number_format($multiplier, 4);
        } else {
            $lines[] = '✖️ ضریب فعلی: —';
        }
        $lines[] = '📈 درصد افزایش (Markup): ' . rtrim(rtrim(number_format((float) $config['markup_percent'], 2, '.', ''), '0'), '.') . '%';
        $lines[] = '🔢 گام گرد کردن: ' . number_format($config['round_step']) . ' تومان';
        $lines[] = '⏱ آستانه قدیمی‌شدن نرخ: ' . $config['stale_after_minutes'] . ' دقیقه';
        $lines[] = '📊 حداکثر جهش مجاز: ' . rtrim(rtrim(number_format((float) $config['max_jump_percent'], 2, '.', ''), '0'), '.') . '%';
        $lines[] = '🕒 آخرین به‌روزرسانی موفق: ' . ($api['fetched_at'] > 0 ? (function_exists('jdate') ? jdate('Y/m/d H:i', $api['fetched_at']) : date('Y-m-d H:i', $api['fetched_at'])) : '—');
        $lines[] = '🔌 منبع نرخ فعلی: ' . ($effective['manual'] ? 'دستی (اضطراری)' : ($effective['source'] ?: '—'));
        $lines[] = '🚨 نرخ اضطراری دستی: ' . ($config['manual_rate'] !== null ? '✅ فعال — ' . fx_format_toman($config['manual_rate']) : '❌ غیرفعال');
        if ($api['pending_rate'] !== null) {
            $lines[] = '⏳ نرخ در انتظار تایید: ' . fx_format_toman($api['pending_rate']) . ' (' . (int) $api['pending_count'] . ' پاسخ)';
        }
        if ($api['last_error'] !== null) {
            $lines[] = '⚠️ آخرین خطای API: <code>' . htmlspecialchars($api['last_error'], ENT_QUOTES, 'UTF-8') . '</code>';
        }
        if (!$effective['manual'] && $api['ok'] && fx_rate_is_stale((int) $api['fetched_at'], (int) $config['stale_after_minutes'])) {
            $lines[] = '';
            $lines[] = '⚠️ <b>هشدار:</b> نرخ API قدیمی است؛ قیمت‌ها با آخرین نرخ معتبر ثابت مانده‌اند.';
        }
        $lines[] = '';
        $lines[] = 'اعمال روی: محصولات ' . $flag($config['apply_products'])
            . ' | حجم دلخواه ' . $flag($config['apply_custom_volume'])
            . ' | زمان دلخواه ' . $flag($config['apply_custom_time'])
            . ' | حجم اضافه ' . $flag($config['apply_extra_volume'])
            . ' | زمان اضافه ' . $flag($config['apply_extra_time']);
        $lines[] = '';
        $lines[] = 'قیمت‌های ثبت‌شده محصولات و تعرفه‌ها «قیمت پایه تومانی» هستند و تغییر نمی‌کنند؛ قیمت نهایی = قیمت پایه × نرخ فعلی ÷ نرخ پایه × (۱ + درصد افزایش).';
        $toggleRow = $config['enabled'] === '1'
            ? [['text' => '⛔️ غیرفعال‌سازی', 'callback_data' => 'fxp_off_' . $code]]
            : [['text' => '✅ فعال‌سازی', 'callback_data' => 'fxp_on_' . $code]];
        $keyboard = ['inline_keyboard' => [
            $toggleRow,
            [
                ['text' => '📌 ثبت نرخ فعلی به‌عنوان پایه', 'callback_data' => 'fxp_rebase_' . $code],
                ['text' => '✍️ نرخ پایه دستی', 'callback_data' => 'fxp_base_' . $code],
            ],
            [
                ['text' => '📈 درصد افزایش', 'callback_data' => 'fxp_markup_' . $code],
                ['text' => '🔢 گام گرد کردن', 'callback_data' => 'fxp_round_' . $code],
            ],
            [
                ['text' => '⏱ آستانه قدیمی‌شدن', 'callback_data' => 'fxp_stale_' . $code],
                ['text' => '📊 حداکثر جهش', 'callback_data' => 'fxp_jump_' . $code],
            ],
            [
                ['text' => $flag($config['apply_products']) . ' محصولات', 'callback_data' => 'fxp_tp' . ($config['apply_products'] === '1' ? '0' : '1') . '_' . $code],
                ['text' => $flag($config['apply_custom_volume']) . ' حجم دلخواه', 'callback_data' => 'fxp_tcv' . ($config['apply_custom_volume'] === '1' ? '0' : '1') . '_' . $code],
            ],
            [
                ['text' => $flag($config['apply_custom_time']) . ' زمان دلخواه', 'callback_data' => 'fxp_tct' . ($config['apply_custom_time'] === '1' ? '0' : '1') . '_' . $code],
                ['text' => $flag($config['apply_extra_volume']) . ' حجم اضافه', 'callback_data' => 'fxp_tev' . ($config['apply_extra_volume'] === '1' ? '0' : '1') . '_' . $code],
            ],
            [
                ['text' => $flag($config['apply_extra_time']) . ' زمان اضافه', 'callback_data' => 'fxp_tet' . ($config['apply_extra_time'] === '1' ? '0' : '1') . '_' . $code],
            ],
            [
                ['text' => '🚨 ثبت نرخ اضطراری', 'callback_data' => 'fxp_manual_' . $code],
                ['text' => '🗑 حذف نرخ اضطراری', 'callback_data' => 'fxp_unmanual_' . $code],
            ],
            [
                ['text' => '🧮 تست محاسبه قیمت', 'callback_data' => 'fxp_test_' . $code],
                ['text' => '🔄 بروزرسانی', 'callback_data' => 'fxp_menu_' . $code],
            ],
            [
                ['text' => '🔙 بازگشت به مدیریت پنل', 'callback_data' => 'fxp_back_' . $code],
            ],
        ]];
        return ['text' => implode("\n", $lines), 'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)];
    }
}

if (!function_exists('fx_admin_enable_render')) {
    function fx_admin_enable_render(array $panel): array
    {
        $config = fx_panel_config($panel);
        $code = (string) $panel['code_panel'];
        $effective = fx_rate_get_effective($config['pair'], $config);
        $back = [['text' => '🔙 بازگشت', 'callback_data' => 'fxp_menu_' . $code]];
        if (!$effective['ok']) {
            return [
                'text' => "❌ در حال حاضر نرخ معتبر USDT/IRT در دسترس نیست؛ فعال‌سازی ممکن نیست.\n\nابتدا اجرای کرون «نرخ دلار» را بررسی کنید یا یک نرخ اضطراری دستی ثبت کنید.",
                'keyboard' => json_encode(['inline_keyboard' => [$back]], JSON_UNESCAPED_UNICODE),
            ];
        }
        $text = "💵 <b>فعال‌سازی قیمت‌گذاری دلاری</b>\n\n🖥 پنل: " . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')
            . "\n💱 نرخ فعلی: " . fx_format_toman($effective['rate'])
            . ($effective['manual'] ? ' (دستی)' : '')
            . "\n\nبا تایید، این نرخ به‌عنوان «نرخ پایه» پنل ذخیره می‌شود و قیمت‌های فعلی محصولات همین پنل معادل همین نرخ در نظر گرفته می‌شوند. هیچ رکورد محصولی تغییر نمی‌کند.";
        $keyboard = ['inline_keyboard' => [
            [['text' => '✅ تایید و فعال‌سازی', 'callback_data' => 'fxp_onok_' . $code]],
            $back,
        ]];
        return ['text' => $text, 'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)];
    }
}
