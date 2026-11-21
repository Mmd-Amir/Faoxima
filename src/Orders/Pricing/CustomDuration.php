<?php

namespace Faoxima\Orders\Pricing;

use InvalidArgumentException;
use Throwable;

final class CustomDuration
{
    public static function fx_duration_config(array $panel, string $agent): array
    {
        $map = json_decode((string) ($panel['custom_duration_pricing'] ?? ''), true);
        $config = is_array($map) && in_array($agent, ['f', 'n', 'n2'], true) ? ($map[$agent] ?? []) : [];
        $tariffs = [];
        foreach ((array) ($config['tariffs'] ?? []) as $days => $price) {
            $amount = \Faoxima\Orders\Pricing\ExchangePricing::fxNormalizeAmount($price);
            if (ctype_digit((string) $days) && (int) $days > 0 && $amount !== null) {
                $tariffs[(int) $days] = $amount;
            }
        }
        ksort($tariffs, SORT_NUMERIC);
        $surchargeDays = [];
        foreach ((array) ($config['surcharge_days'] ?? []) as $days) {
            if (is_numeric($days) && isset($tariffs[(int)$days])) {
                $surchargeDays[] = (int)$days;
            }
        }
        return ['enabled' => !empty($config['enabled']), 'tariffs' => $tariffs, 'surcharge_days' => array_values(array_unique($surchargeDays))];
    }

    public static function fx_duration_tariffs(array $panel, string $agent): array
    {
        $min = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, 'maintime', $agent) ?? 0;
        $max = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, 'maxtime', $agent) ?? 0;
        return array_filter(self::fx_duration_config($panel, $agent)['tariffs'], static function ($days) use ($min, $max) {
            return $days >= $min && $days <= $max;
        }, ARRAY_FILTER_USE_KEY);
    }

    public static function fx_duration_public_tariffs(array $panel, string $agent): array
    {
        $prices = [];
        foreach (self::fx_duration_tariffs($panel, $agent) as $days => $price) {
            $prices[$days] = (float)\Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman(self::fx_duration_unit_price($panel, $agent, $price, $days), $panel, 'custom_volume');
        }
        return $prices;
    }

    public static function fx_duration_unit_price(array $panel, string $agent, float $tariff, int $days): float
    {
        $base = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, 'pricecustomvolume', $agent) ?? 0.0;
        return in_array($days, self::fx_duration_config($panel, $agent)['surcharge_days'], true) ? $base + $tariff : ($tariff > 0 ? $tariff : $base);
    }

    public static function fx_custom_resolve(array $panel, string $agent, $volume, $days): array
    {
        $config = self::fx_duration_config($panel, $agent);
        $volumeUnit = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, 'pricecustomvolume', $agent) ?? 0.0;
        $timeUnit = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, 'pricecustomtime', $agent) ?? 0.0;
        if ($config['enabled']) {
            if (!in_array($agent, ['f', 'n', 'n2'], true) || !is_numeric($volume) || !is_numeric($days)
                || !is_finite((float) $volume) || !is_finite((float) $days)
                || (float) $volume != (int) $volume || (float) $days != (int) $days
                || $volume < (\Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, 'mainvolume', $agent) ?? 0)
                || $volume > (\Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($panel, 'maxvolume', $agent) ?? 0)) {
                throw new InvalidArgumentException('❌ حجم نامعتبر است.');
            }
            $tariffs = self::fx_duration_tariffs($panel, $agent);
            if (!isset($tariffs[(int) $days])) {
                throw new InvalidArgumentException('❌ برای این مدت تعرفه‌ای تعریف نشده است؛ دوباره مدت سرویس را انتخاب کنید.');
            }
            $volumeUnit = self::fx_duration_unit_price($panel, $agent, $tariffs[(int) $days], (int)$days);
            $timeUnit = 0.0;
        }
        $price = (float) $volume * (float) \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($volumeUnit, $panel, 'custom_volume')
            + (float) $days * (float) \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($timeUnit, $panel, 'custom_time');
        return [
            'mode' => $config['enabled'] ? 'duration' : 'legacy',
            'volume_gb' => (int) $volume,
            'time_days' => (int) $days,
            'price_per_gb' => (float) \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($volumeUnit, $panel, 'custom_volume'),
            'base_price' => (float) $volume * $volumeUnit + (float) $days * $timeUnit,
            'price' => $price,
        ];
    }

    public static function fx_custom_price(array $panel, string $agent, $volume, $days): float
    {
        return self::fx_custom_resolve($panel, $agent, $volume, $days)['price'];
    }

    public static function fx_custom_renew_panel(array $panel, string $agent): array
    {
        if (self::fx_duration_config($panel, $agent)['enabled']) {
            return $panel;
        }
        foreach (['pricecustomvolume', 'pricecustomtime', 'mainvolume', 'maxvolume', 'maintime', 'maxtime'] as $column) {
            $raw = $panel[$column] ?? '';
            $map = is_array($raw) ? $raw : json_decode((string)$raw, true);
            if (!is_array($map)) {
                $map = [$agent => is_numeric($raw) ? $raw : 0];
            }
            foreach ([$agent, 'allusers', 'f'] as $key) {
                if (isset($map[$key]) && $map[$key] !== '') {
                    $map[$agent] = $map[$key];
                    break;
                }
            }
            $panel[$column] = json_encode($map);
        }
        return $panel;
    }

    public static function fx_custom_quote_context(array $panel, $kinds): ?array
    {
        $context = \Faoxima\Orders\Pricing\ExchangePricing::fxContext($panel, $kinds);
        $kinds = (array) $kinds;
        if (!in_array('custom_volume', $kinds, true) || !in_array('custom_time', $kinds, true)) {
            return $context;
        }
        $context = $context ?? ['code_panel' => (string) ($panel['code_panel'] ?? ''), 'current_rate' => 0,
            'base_rate' => 0, 'markup_percent' => 0, 'round_step' => 1, 'fetched_at' => 0];
        $values = [];
        foreach (['custom_duration_pricing', 'pricecustomvolume', 'pricecustomtime', 'mainvolume', 'maxvolume', 'maintime', 'maxtime', '_custom_quote_user', '_custom_quote_total', '_custom_quote_selection'] as $key) {
            $values[$key] = $panel[$key] ?? null;
        }
        $context['custom_fingerprint'] = hash('sha256', json_encode($values));
        return $context;
    }

    public static function fx_custom_api_quote_panel(array $panel, array $user, array $product, string $flow, string $discountCode = ''): array
    {
        $kinds = ['custom_volume', 'custom_time'];
        $price = $flow === 'buy' ? (float)$product['price_product'] : (float)\Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($product['price_product'], $panel, $kinds);
        $discount = (int)($user['pricediscount'] ?? 0);
        if ($flow === 'buy') {
            $price = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount($price, min(100, max(0, $discount)));
        }
        if ($discountCode !== '') {
            $validation = \Faoxima\Orders\Discounts\DiscountRules::validateSellForCategories(\Faoxima\Api\Runtime\Db::pdo(), $discountCode, $flow, (string)$product['code_product'],
                (string)$panel['code_panel'], \Faoxima\Orders\Catalog\NameCascades::nmProductCategoryList($product), $user);
            if (empty($validation['ok'])) {
                throw new InvalidArgumentException((string)($validation['reason'] ?? 'کد تخفیف نامعتبر است.'));
            }
            $price = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Discounts\DiscountRules::applyToPrice($validation['row'], (float)$price), $panel, $kinds);
        }
        if ($flow !== 'buy') {
            $price = (int)round(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount($price, $discount));
        }
        $panel['_custom_quote_user'] = [(string)$user['id'], (string)($user['agent'] ?? 'f'), $discount];
        $panel['_custom_quote_total'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($price, $panel, $kinds);
        $panel['_custom_quote_selection'] = [$flow, (int)$product['Volume_constraint'], (int)$product['Service_time'], $discountCode];
        return $panel;
    }

    public static function fx_custom_snapshot(array $panel, string $agent, $volume, $days, $total, ?array $snapshot): ?array
    {
        if (!self::fx_duration_config($panel, $agent)['enabled']) {
            return $snapshot;
        }
        $pricing = self::fx_custom_resolve($panel, $agent, $volume, $days);
        $pricing['final_price'] = (float)$total;
        $pricing['group'] = $agent;
        return array_merge($snapshot ?? ['mode' => 'duration', 'created_at' => time(), 'final_price' => (float)$total], ['custom_pricing' => $pricing]);
    }

    public static function fx_custom_price_text_default(): string
    {
        return "📦 {volume} GB · {days} روز\n💰 {price_per_gb} تومان/GB · مبلغ کل: {total} تومان";
    }

    public static function fx_custom_price_text(array $panel, string $agent, $volume, $days, $total): string
    {
        if (!self::fx_duration_config($panel, $agent)['enabled']) {
            return '';
        }
        $resolved = self::fx_custom_resolve($panel, $agent, $volume, $days);
        global $datatextbot;
        $key = 'text_custom_price_summary';
        $template = self::fx_custom_price_text_default();
        if (is_array($datatextbot) && array_key_exists($key, $datatextbot)) {
            $template = (string) $datatextbot[$key];
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $row = \Faoxima\Core\Database\Db::select('textbot', '*', 'id_text', $key);
            } catch (Throwable $e) {
                $row = null;
            }
            if (is_array($row) && isset($row['text']) && is_string($row['text'])) {
                $template = $row['text'];
            }
        }
        $template = (string) preg_replace('~^\s*<!--\s*rxrtl:[01]\s*-->\s*~i', '', $template);
        if (trim($template) === '') {
            return '';
        }
        return "\n" . strtr($template, [
            '{volume}' => htmlspecialchars((string) $volume, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{days}' => htmlspecialchars((string) $days, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{price_per_gb}' => number_format($resolved['price_per_gb']),
            '{total}' => number_format((float) $total),
        ]);
    }

    public static function fx_duration_keyboard(array $panel, string $agent): string
    {
        $rows = [];
        foreach (self::fx_duration_public_tariffs($panel, $agent) as $days => $price) {
            $rows[] = [['text' => $days . ' روز · ' . number_format($price) . ' تومان/GB',
                'callback_data' => 'cdpick_' . $days, '_rxsk' => 'time_buy']];
        }
        $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'backuser']];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }
}
