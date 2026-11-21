<?php

namespace Faoxima\Orders\Pricing;

use PDO;
use Throwable;
use Faoxima\Api\Runtime\Db;

final class ExchangePricing
{
    public static function fxPricingKinds(): array
    {
        return [
            'product'       => 'apply_products',
            'custom_volume' => 'apply_custom_volume',
            'custom_time'   => 'apply_custom_time',
            'extra_volume'  => 'apply_extra_volume',
            'extra_time'    => 'apply_extra_time',
        ];
    }

    public static function fxPdo(): ?PDO
    {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $connection = \Faoxima\Core\Database\Db::getDatabaseConnection();
                if ($connection instanceof PDO) {
                    return $connection;
                }
            } catch (Throwable $e) {
            }
        }
        if (class_exists(\Faoxima\Api\Runtime\Db::class)) {
            try {
                return \Faoxima\Api\Runtime\Db::pdo();
            } catch (Throwable $e) {
            }
        }
        return null;
    }

    public static function fxLog(string $type, string $message, array $context = []): void
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

    public static function fxEnsureSchema(?PDO $pdo = null): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $pdo = $pdo ?: self::fxPdo();
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
            self::fxLog('FX_SCHEMA_FAILED', $e->getMessage());
            $ready = false;
        }
        return $ready;
    }

    public static function fxTableHasColumn(string $table, string $column): bool
    {
        static $memo = [];
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', $column);
        $key = $table . '.' . $column;
        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }
        $pdo = self::fxPdo();
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

    public static function fxNormalizePair($pair): string
    {
        $pair = strtoupper(trim((string) $pair));
        return $pair === FX_DEFAULT_PAIR ? $pair : FX_DEFAULT_PAIR;
    }

    public static function fxAsciiDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '٬' => '',
        ]);
    }

    public static function fxNormalizeAmount($value): ?float
    {
        if (is_bool($value) || $value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } else {
            $text = trim(self::fxAsciiDigits((string) $value));
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

    public static function fxPositiveNumber($value): ?float
    {
        $number = self::fxNormalizeAmount($value);
        return ($number !== null && $number > 0) ? $number : null;
    }

    public static function fxDefaultConfig(): array
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

    public static function fxSanitizeConfig(array $input): array
    {
        $config = self::fxDefaultConfig();
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
        $config['pair'] = self::fxNormalizePair($input['pair'] ?? FX_DEFAULT_PAIR);
        $config['base_rate'] = self::fxPositiveNumber($input['base_rate'] ?? null);
        $markup = $input['markup_percent'] ?? 0;
        $markupText = is_string($markup) ? trim(self::fxAsciiDigits($markup)) : $markup;
        $config['markup_percent'] = (is_numeric($markupText) && is_finite((float) $markupText))
            ? max(-90.0, min(1000.0, (float) $markupText)) : 0;
        $roundStep = self::fxNormalizeAmount($input['round_step'] ?? 1000);
        $config['round_step'] = $roundStep === null ? 1000 : (int) max(1, min(1000000, floor($roundStep)));
        $stale = self::fxNormalizeAmount($input['stale_after_minutes'] ?? 360);
        $config['stale_after_minutes'] = $stale === null ? 360 : (int) max(5, min(10080, floor($stale)));
        $jump = self::fxNormalizeAmount($input['max_jump_percent'] ?? 15);
        $config['max_jump_percent'] = $jump === null ? 15 : max(1.0, min(100.0, $jump));
        $config['manual_rate'] = self::fxPositiveNumber($input['manual_rate'] ?? null);
        foreach (self::fxPricingKinds() as $applyKey) {
            $config[$applyKey] = $flag($input[$applyKey] ?? null, '1');
        }
        $enabledAt = $input['enabled_at'] ?? null;
        $config['enabled_at'] = is_numeric($enabledAt) ? (int) $enabledAt : null;
        if ($config['enabled'] === '1' && $config['base_rate'] === null) {
            $config['enabled'] = '0';
        }
        return $config;
    }

    public static function fxPanelConfig(array $panel): array
    {
        $raw = $panel['fx_pricing_config'] ?? null;
        if (is_array($raw)) {
            return self::fxSanitizeConfig($raw);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return self::fxDefaultConfig();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::fxDefaultConfig();
        }
        return self::fxSanitizeConfig($decoded);
    }

    public static function fxPanelEnabled(array $panel): bool
    {
        $config = self::fxPanelConfig($panel);
        return $config['enabled'] === '1' && $config['base_rate'] !== null;
    }

    public static function fxKindEnabled(array $panel, string $priceKind): bool
    {
        $kinds = self::fxPricingKinds();
        if (!isset($kinds[$priceKind]) || !self::fxPanelEnabled($panel)) {
            return false;
        }
        return self::fxPanelConfig($panel)[$kinds[$priceKind]] === '1';
    }

    public static function fxContext(array $panel, $priceKinds): ?array
    {
        $kinds = is_array($priceKinds) ? $priceKinds : [$priceKinds];
        $activeKind = null;
        foreach ($kinds as $kind) {
            if (self::fxKindEnabled($panel, (string) $kind)) {
                $activeKind = (string) $kind;
                break;
            }
        }
        if ($activeKind === null) {
            return null;
        }
        $config = self::fxPanelConfig($panel);
        $memoKey = (string) ($panel['code_panel'] ?? $panel['name_panel'] ?? '') . '|' . md5(json_encode($config));
        if (isset($GLOBALS['__fx_context_memo'][$memoKey])) {
            $cachedContext = $GLOBALS['__fx_context_memo'][$memoKey];
            $cachedContext['kind'] = $activeKind;
            return $cachedContext;
        }
        $effective = \Faoxima\Orders\Pricing\ExchangeRates::fxRateGetEffective($config['pair'], $config);
        $currentRate = $effective['rate'];
        $source = $effective['source'];
        if (!$effective['ok'] || $currentRate === null) {
            self::fxLog('FX_RATE_UNAVAILABLE', 'No valid cached rate; using panel base rate', ['panel' => (string) ($panel['code_panel'] ?? '')]);
            $currentRate = $config['base_rate'];
            $source = 'base_rate_fallback';
        }
        $multiplier = ($currentRate / $config['base_rate']) * (1 + ((float) $config['markup_percent'] / 100));
        if (is_nan($multiplier) || is_infinite($multiplier) || $multiplier <= 0) {
            self::fxLog('FX_MULTIPLIER_INVALID', 'Invalid multiplier', ['panel' => (string) ($panel['code_panel'] ?? '')]);
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

    public static function fxRoundPrice(float $price, int $roundStep): int
    {
        if (is_nan($price) || is_infinite($price) || $price <= 0) {
            return 0;
        }
        $step = max(1, $roundStep);
        return (int) (ceil(round($price / $step, 6)) * $step);
    }

    public static function fxAdjustBaseToman($baseToman, array $panel, string $priceKind)
    {
        $context = self::fxContext($panel, $priceKind);
        if ($context === null) {
            return $baseToman;
        }
        $base = self::fxNormalizeAmount($baseToman);
        if ($base === null) {
            if ($baseToman !== null && $baseToman !== '') {
                self::fxLog('FX_INVALID_BASE_PRICE', 'Base price is not a valid non-negative number', ['panel' => $context['code_panel'], 'kind' => $priceKind]);
            }
            return $baseToman;
        }
        if ($base == 0.0) {
            return 0;
        }
        $step = $priceKind === 'product' ? $context['round_step'] : 1;
        return self::fxRoundPrice($base * $context['multiplier'], $step);
    }

    public static function fxApplyToProduct(array $product, array $panel): array
    {
        if (!empty($product['_fx_applied']) || !array_key_exists('price_product', $product)) {
            return $product;
        }
        if (self::fxContext($panel, 'product') === null) {
            return $product;
        }
        $product['fx_base_price'] = $product['price_product'];
        $product['price_product'] = self::fxAdjustBaseToman($product['price_product'], $panel, 'product');
        $product['_fx_applied'] = 1;
        return $product;
    }

    public static function fxFinalizeAmount($amount, array $panel, $priceKinds)
    {
        $context = self::fxContext($panel, $priceKinds);
        if ($context === null) {
            return $amount;
        }
        if (is_numeric($amount) && (float) $amount < 0) {
            return 0;
        }
        $value = self::fxNormalizeAmount($amount);
        if ($value === null) {
            return $amount;
        }
        return self::fxRoundPrice($value, $context['round_step']);
    }

    public static function fxApplyUserDiscount($amount, $discountPercent)
    {
        if (intval($discountPercent) == 0) {
            return $amount;
        }
        return $amount - (($amount * $discountPercent) / 100);
    }

    public static function fxPricingSnapshot(array $panel, $priceKinds, $basePrice, $finalPrice, $discountAmount = 0, array $extra = []): ?array
    {
        $context = self::fxContext($panel, $priceKinds);
        if ($context === null) {
            return null;
        }
        return array_merge([
            'mode'            => 'usdt_ratio',
            'pair'            => $context['pair'],
            'kind'            => $context['kind'],
            'panel'           => $context['code_panel'],
            'base_price'      => (float) (self::fxNormalizeAmount($basePrice) ?? 0),
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

    public static function fxPanelRawUnitPrice(array $panel, string $column, $agent): ?float
    {
        $map = json_decode((string) ($panel[$column] ?? ''), true);
        if (!is_array($map)) {
            return null;
        }
        return self::fxNormalizeAmount($map[(string) $agent] ?? null);
    }

    public static function fxCustomBasePrice(array $panel, $agent, $volumeGb, $days): float
    {
        if (\Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($panel, (string) $agent)['enabled']) {
            return \Faoxima\Orders\Pricing\CustomDuration::fx_custom_resolve($panel, (string) $agent, $volumeGb, $days)['base_price'];
        }
        $volumeUnit = self::fxPanelRawUnitPrice($panel, 'pricecustomvolume', $agent) ?? 0.0;
        $timeUnit = self::fxPanelRawUnitPrice($panel, 'pricecustomtime', $agent) ?? 0.0;
        return ((float) $volumeGb * $volumeUnit) + ((float) $days * $timeUnit);
    }

    public static function fxStoreInvoiceSnapshot($idInvoice, ?array $snapshot): bool
    {
        $idInvoice = (string) $idInvoice;
        if ($snapshot === null || $idInvoice === '' || !self::fxTableHasColumn('invoice', 'pricing_snapshot')) {
            return false;
        }
        $pdo = self::fxPdo();
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('UPDATE invoice SET pricing_snapshot = ? WHERE id_invoice = ?');
            $stmt->execute([json_encode($snapshot, JSON_UNESCAPED_UNICODE), $idInvoice]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('invoice');
            }
            return true;
        } catch (Throwable $e) {
            self::fxLog('FX_SNAPSHOT_STORE_FAILED', $e->getMessage(), ['invoice' => $idInvoice]);
            return false;
        }
    }

    public static function fxValueWithSnapshot($valueJson, ?array $snapshot)
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

    public static function fxQuoteContextKey(string $flow, string $reference = ''): string
    {
        return substr($flow, 0, 60) . ':' . substr(sha1($reference), 0, 16);
    }

    public static function fxQuotePut($userId, string $contextKey, $amount, array $meta = [], int $ttlSeconds = FX_QUOTE_TTL_SECONDS): bool
    {
        $pdo = self::fxPdo();
        if (!($pdo instanceof PDO) || !self::fxEnsureSchema($pdo)) {
            return false;
        }
        $now = time();
        try {
            $stmt = $pdo->prepare('INSERT INTO fx_price_quote (user_id, context, amount, meta, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount), meta = VALUES(meta), created_at = VALUES(created_at), expires_at = VALUES(expires_at)');
            $stmt->execute([(string) $userId, $contextKey, (int) round((float) $amount), json_encode($meta, JSON_UNESCAPED_UNICODE), $now, $now + max(60, $ttlSeconds)]);
            if (mt_rand(1, 50) === 1) {
                $pdo->prepare('DELETE FROM fx_price_quote WHERE expires_at < ?')->execute([$now - 86400]);
            }
            return true;
        } catch (Throwable $e) {
            self::fxLog('FX_QUOTE_STORE_FAILED', $e->getMessage());
            return false;
        }
    }

    public static function fxQuoteGet($userId, string $contextKey): ?array
    {
        $pdo = self::fxPdo();
        if (!($pdo instanceof PDO) || !self::fxEnsureSchema($pdo)) {
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

    public static function fxQuoteMatches($userId, string $contextKey, $amount): bool
    {
        $quote = self::fxQuoteGet($userId, $contextKey);
        return $quote !== null && $quote['amount'] === (int) round((float) $amount);
    }

    public static function fxQuoteForget($userId, string $contextKey): void
    {
        $pdo = self::fxPdo();
        if (!($pdo instanceof PDO) || !self::fxEnsureSchema($pdo)) {
            return;
        }
        try {
            $pdo->prepare('DELETE FROM fx_price_quote WHERE user_id = ? AND context = ?')->execute([(string) $userId, $contextKey]);
        } catch (Throwable $e) {
        }
    }

    public static function fxQuoteStore($userId, string $contextKey, $amount, array $panel, $priceKinds, array $meta = []): bool
    {
        $context = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($panel, $priceKinds);
        if ($context === null) {
            return false;
        }
        $meta['f'] = self::fxContextFingerprint($context);
        $meta['rate'] = $context['current_rate'];
        $meta['base_rate'] = $context['base_rate'];
        $meta['rate_ts'] = $context['fetched_at'];
        return self::fxQuotePut($userId, $contextKey, $amount, $meta);
    }

    public static function fxQuoteStoreServer($userId, string $contextKey, $amount, array $panel, $priceKinds, array $meta = []): bool
    {
        $context = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($panel, $priceKinds);
        if ($context !== null) {
            $meta['f'] = self::fxContextFingerprint($context);
            $meta['rate'] = $context['current_rate'];
            $meta['base_rate'] = $context['base_rate'];
            $meta['rate_ts'] = $context['fetched_at'];
        }
        return self::fxQuotePut($userId, $contextKey, $amount, $meta, FX_SERVER_QUOTE_TTL_SECONDS);
    }

    public static function fxQuoteServerAmountMatches($userId, string $contextKey, $amount): bool
    {
        $quote = self::fxQuoteGet($userId, $contextKey);
        return $quote !== null && is_numeric($amount) && $quote['amount'] === (int) round((float) $amount);
    }

    public static function fxExtraQuoteVerify($userId, string $contextKey, $unitPrice, $requestedAmount, array $panel, string $priceKind): array
    {
        $quote = self::fxQuoteGet($userId, $contextKey);
        $quantity = is_array($quote) ? (int) ($quote['meta']['qty'] ?? 0) : 0;
        $unit = self::fxNormalizeAmount($unitPrice);
        if ($quantity <= 0 || $unit === null || $unit <= 0) {
            return ['status' => 'missing', 'qty' => 0, 'amount' => 0];
        }
        $fresh = self::fxFinalizeAmount($unit * $quantity, $panel, $priceKind);
        $freshAmount = (int) round((float) $fresh);
        $matches = $quote['amount'] === $freshAmount
            && is_numeric($requestedAmount)
            && (int) round((float) $requestedAmount) === $freshAmount
            && \Faoxima\Orders\Pricing\ExchangeRates::fxQuoteRateUnchanged($userId, $contextKey, $panel, $priceKind);
        if ($matches) {
            return ['status' => 'ok', 'qty' => $quantity, 'amount' => $freshAmount];
        }
        if (self::fxContext($panel, $priceKind) === null) {
            return ['status' => 'missing', 'qty' => 0, 'amount' => 0];
        }
        self::fxQuoteStoreServer($userId, $contextKey, $freshAmount, $panel, $priceKind, ['qty' => $quantity]);
        return ['status' => 'changed', 'qty' => $quantity, 'amount' => $freshAmount];
    }

    public static function fxQuoteCheck($userId, string $contextKey, $amount, array $panel, $priceKinds): bool
    {
        $context = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($panel, $priceKinds);
        if ($context === null) {
            return true;
        }
        $quote = self::fxQuoteGet($userId, $contextKey);
        if ($quote === null || $quote['amount'] !== (int) round((float) $amount)) {
            return false;
        }
        return hash_equals(self::fxContextFingerprint($context), (string) ($quote['meta']['f'] ?? ''));
    }

    public static function fxApplyDiscountRule($price, string $valueType, float $value)
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

    public static function fxPriceChangedText($newAmount): string
    {
        $formatted = number_format((float) $newAmount);
        return "⚠️ به دلیل تغییر تعرفه یا نرخ ارز، مبلغ فاکتور به‌روزرسانی شد.\n\n💰 مبلغ جدید: {$formatted} تومان\n\n✅ در صورت موافقت، دوباره روی دکمه تایید بزنید.";
    }

    public static function fxPriceChangedApiMessage(): string
    {
        return 'به دلیل تغییر تعرفه یا نرخ ارز، قیمت به‌روزرسانی شد. لطفاً مبلغ جدید را تایید کنید.';
    }

    public static function fxPriceChangedApiObj($newPrice, ?string $token): array
    {
        return ['code' => 'price_changed', 'price' => $newPrice, 'fx_quote' => $token];
    }

    public static function fxQuoteSecret(): string
    {
        $material = (string) ($GLOBALS['APIKEY'] ?? '') . '|' . (string) ($GLOBALS['passworddb'] ?? '') . '|' . (string) ($GLOBALS['dbname'] ?? '');
        return hash('sha256', 'fx_quote|' . $material);
    }

    public static function fxBase64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function fxBase64urlDecode(string $data)
    {
        $padded = strtr($data, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode($padded, true);
    }

    public static function fxContextFingerprint(array $context): string
    {
        $parts = [
            $context['code_panel'],
            number_format($context['current_rate'], 4, '.', ''),
            number_format($context['base_rate'], 4, '.', ''),
            number_format($context['markup_percent'], 4, '.', ''),
            (string) $context['round_step'],
        ];
        if (isset($context['custom_fingerprint'])) {
            $parts[] = $context['custom_fingerprint'];
        }
        return hash('sha256', implode('|', $parts));
    }

    public static function fxQuoteToken(array $panel, $priceKinds): ?string
    {
        $context = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($panel, $priceKinds);
        if ($context === null) {
            return null;
        }
        $payload = self::fxBase64urlEncode(json_encode([
            'p' => $context['code_panel'],
            'f' => self::fxContextFingerprint($context),
            't' => $context['fetched_at'],
            'e' => time() + FX_QUOTE_TTL_SECONDS,
        ]));
        return $payload . '.' . self::fxBase64urlEncode(hash_hmac('sha256', $payload, self::fxQuoteSecret(), true));
    }

    public static function fxQuoteTokenValid($token, array $panel, $priceKinds): bool
    {
        $context = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($panel, $priceKinds);
        if ($context === null) {
            return true;
        }
        if (!is_string($token) || strpos($token, '.') === false) {
            return false;
        }
        [$payload, $signature] = explode('.', $token, 2);
        $expected = self::fxBase64urlEncode(hash_hmac('sha256', $payload, self::fxQuoteSecret(), true));
        if (!hash_equals($expected, $signature)) {
            return false;
        }
        $decodedPayload = self::fxBase64urlDecode($payload);
        $data = is_string($decodedPayload) ? json_decode($decodedPayload, true) : null;
        if (!is_array($data) || (int) ($data['e'] ?? 0) < time()) {
            return false;
        }
        return (string) ($data['p'] ?? '') === $context['code_panel']
            && hash_equals(self::fxContextFingerprint($context), (string) ($data['f'] ?? ''));
    }

    public static function fxEnabledPanelConfigs(): array
    {
        $pdo = self::fxPdo();
        if (!($pdo instanceof PDO) || !self::fxTableHasColumn('marzban_panel', 'fx_pricing_config')) {
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
            $config = self::fxPanelConfig($row);
            if ($config['enabled'] === '1') {
                $result[] = ['panel' => $row, 'config' => $config];
            }
        }
        return $result;
    }

    public static function fxNotifyAdmins(string $text): void
    {
        if (!function_exists('telegram')) {
            return;
        }
        try {
            $setting = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', '*') : null;
            $channel = is_array($setting) ? trim((string) ($setting['Channel_Report'] ?? '')) : '';
            if ($channel !== '' && $channel !== '0') {
                $topicRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'errorreport', 'select');
                $topic = is_array($topicRow) ? (string) ($topicRow['idreport'] ?? '') : '';
                $params = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML'];
                if ($topic !== '' && $topic !== '-1') {
                    $params['message_thread_id'] = $topic;
                }
                telegram('sendmessage', $params);
                return;
            }
            $admins = \Faoxima\Core\Database\Db::select('admin', 'id_admin', null, null, 'FETCH_COLUMN');
            if (is_array($admins)) {
                foreach ($admins as $adminId) {
                    if (is_numeric($adminId)) {
                        telegram('sendmessage', ['chat_id' => $adminId, 'text' => $text, 'parse_mode' => 'HTML']);
                    }
                }
            }
        } catch (Throwable $e) {
            self::fxLog('FX_NOTIFY_FAILED', $e->getMessage());
        }
    }

    public static function fxEnsurePanelColumn(): bool
    {
        if (self::fxTableHasColumn('marzban_panel', 'fx_pricing_config')) {
            return true;
        }
        $pdo = self::fxPdo();
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->exec('ALTER TABLE `marzban_panel` ADD COLUMN `fx_pricing_config` TEXT NULL');
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                self::fxLog('FX_PANEL_COLUMN_FAILED', $e->getMessage());
                return false;
            }
        }
        return true;
    }

    public static function fxPanelSaveConfig(array $panel, array $config): bool
    {
        $code = (string) ($panel['code_panel'] ?? '');
        $pdo = self::fxPdo();
        if ($code === '' || !($pdo instanceof PDO) || !self::fxEnsurePanelColumn()) {
            return false;
        }
        $clean = self::fxSanitizeConfig($config);
        try {
            $stmt = $pdo->prepare('UPDATE marzban_panel SET fx_pricing_config = ? WHERE code_panel = ?');
            $stmt->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $code]);
        } catch (Throwable $e) {
            self::fxLog('FX_PANEL_SAVE_FAILED', $e->getMessage(), ['panel' => $code]);
            return false;
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('marzban_panel');
        }
        $GLOBALS['__fx_context_memo'] = [];
        return true;
    }

    public static function fxAdminLoadPanel($code): ?array
    {
        $code = (string) $code;
        $pdo = self::fxPdo();
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

    public static function fxAdminButtonLabel(): string
    {
        return '💵 قیمت‌گذاری دلاری (USDT)';
    }

    public static function fxParseAdminNumber($text, bool $allowNegative = false): ?float
    {
        $clean = str_replace([',', ' ', '%', '٪'], '', trim(self::fxAsciiDigits((string) $text)));
        if ($clean === '' || !preg_match($allowNegative ? '/^-?\d+(\.\d+)?$/' : '/^\d+(\.\d+)?$/', $clean)) {
            return null;
        }
        $number = (float) $clean;
        return (is_nan($number) || is_infinite($number)) ? null : $number;
    }

    public static function fxFormatToman($value): string
    {
        return $value === null ? '—' : number_format((float) $value, (floor((float) $value) == (float) $value) ? 0 : 2) . ' تومان';
    }

    public static function fxAdminMenuRender(array $panel): array
    {
        $config = self::fxPanelConfig($panel);
        $code = (string) $panel['code_panel'];
        $api = \Faoxima\Orders\Pricing\ExchangeRates::fxRateGetCached($config['pair']);
        $effective = \Faoxima\Orders\Pricing\ExchangeRates::fxRateGetEffective($config['pair'], $config);
        $flag = static function (string $value): string {
            return $value === '1' ? '✅' : '❌';
        };
        $lines = [
            '💵 <b>قیمت‌گذاری دلاری (USDT/IRT)</b>',
            '',
            '🖥 پنل: ' . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8'),
            '📌 وضعیت: ' . ($config['enabled'] === '1' ? '✅ فعال' : '❌ غیرفعال'),
            '💱 نرخ فعلی مؤثر: ' . ($effective['ok'] ? self::fxFormatToman($effective['rate']) : '— (نرخ معتبری در دسترس نیست)'),
            '🌐 نرخ API ذخیره‌شده: ' . ($api['ok'] ? self::fxFormatToman($api['rate']) : '—'),
            '🏁 نرخ پایه پنل: ' . self::fxFormatToman($config['base_rate']),
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
        $lines[] = '🚨 نرخ اضطراری دستی: ' . ($config['manual_rate'] !== null ? '✅ فعال — ' . self::fxFormatToman($config['manual_rate']) : '❌ غیرفعال');
        if ($api['pending_rate'] !== null) {
            $lines[] = '⏳ نرخ در انتظار تایید: ' . self::fxFormatToman($api['pending_rate']) . ' (' . (int) $api['pending_count'] . ' پاسخ)';
        }
        if ($api['last_error'] !== null) {
            $lines[] = '⚠️ آخرین خطای API: <code>' . htmlspecialchars($api['last_error'], ENT_QUOTES, 'UTF-8') . '</code>';
        }
        if (!$effective['manual'] && $api['ok'] && \Faoxima\Orders\Pricing\ExchangeRates::fxRateIsStale((int) $api['fetched_at'], (int) $config['stale_after_minutes'])) {
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

    public static function fxAdminEnableRender(array $panel): array
    {
        $config = self::fxPanelConfig($panel);
        $code = (string) $panel['code_panel'];
        $effective = \Faoxima\Orders\Pricing\ExchangeRates::fxRateGetEffective($config['pair'], $config);
        $back = [['text' => '🔙 بازگشت', 'callback_data' => 'fxp_menu_' . $code]];
        if (!$effective['ok']) {
            return [
                'text' => "❌ در حال حاضر نرخ معتبر USDT/IRT در دسترس نیست؛ فعال‌سازی ممکن نیست.\n\nابتدا اجرای کرون «نرخ دلار» را بررسی کنید یا یک نرخ اضطراری دستی ثبت کنید.",
                'keyboard' => json_encode(['inline_keyboard' => [$back]], JSON_UNESCAPED_UNICODE),
            ];
        }
        $text = "💵 <b>فعال‌سازی قیمت‌گذاری دلاری</b>\n\n🖥 پنل: " . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')
            . "\n💱 نرخ فعلی: " . self::fxFormatToman($effective['rate'])
            . ($effective['manual'] ? ' (دستی)' : '')
            . "\n\nبا تایید، این نرخ به‌عنوان «نرخ پایه» پنل ذخیره می‌شود و قیمت‌های فعلی محصولات همین پنل معادل همین نرخ در نظر گرفته می‌شوند. هیچ رکورد محصولی تغییر نمی‌کند.";
        $keyboard = ['inline_keyboard' => [
            [['text' => '✅ تایید و فعال‌سازی', 'callback_data' => 'fxp_onok_' . $code]],
            $back,
        ]];
        return ['text' => $text, 'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)];
    }
}
