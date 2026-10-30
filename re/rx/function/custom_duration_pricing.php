<?php

function fx_duration_config(array $panel, string $agent): array
{
    $map = json_decode((string) ($panel['custom_duration_pricing'] ?? ''), true);
    $config = is_array($map) && in_array($agent, ['f', 'n', 'n2'], true) ? ($map[$agent] ?? []) : [];
    $tariffs = [];
    foreach ((array) ($config['tariffs'] ?? []) as $days => $price) {
        $amount = fx_normalize_amount($price);
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

function fx_duration_tariffs(array $panel, string $agent): array
{
    $min = fx_panel_raw_unit_price($panel, 'maintime', $agent) ?? 0;
    $max = fx_panel_raw_unit_price($panel, 'maxtime', $agent) ?? 0;
    return array_filter(fx_duration_config($panel, $agent)['tariffs'], static function ($days) use ($min, $max) {
        return $days >= $min && $days <= $max;
    }, ARRAY_FILTER_USE_KEY);
}

function fx_duration_public_tariffs(array $panel, string $agent): array
{
    $prices = [];
    foreach (fx_duration_tariffs($panel, $agent) as $days => $price) {
        $prices[$days] = (float)fx_adjust_base_toman(fx_duration_unit_price($panel, $agent, $price, $days), $panel, 'custom_volume');
    }
    return $prices;
}

function fx_duration_unit_price(array $panel, string $agent, float $tariff, int $days): float
{
    $base = fx_panel_raw_unit_price($panel, 'pricecustomvolume', $agent) ?? 0.0;
    return in_array($days, fx_duration_config($panel, $agent)['surcharge_days'], true) ? $base + $tariff : ($tariff > 0 ? $tariff : $base);
}

function fx_custom_resolve(array $panel, string $agent, $volume, $days): array
{
    $config = fx_duration_config($panel, $agent);
    $volumeUnit = fx_panel_raw_unit_price($panel, 'pricecustomvolume', $agent) ?? 0.0;
    $timeUnit = fx_panel_raw_unit_price($panel, 'pricecustomtime', $agent) ?? 0.0;
    if ($config['enabled']) {
        if (!in_array($agent, ['f', 'n', 'n2'], true) || !is_numeric($volume) || !is_numeric($days)
            || !is_finite((float) $volume) || !is_finite((float) $days)
            || (float) $volume != (int) $volume || (float) $days != (int) $days
            || $volume < (fx_panel_raw_unit_price($panel, 'mainvolume', $agent) ?? 0)
            || $volume > (fx_panel_raw_unit_price($panel, 'maxvolume', $agent) ?? 0)) {
            throw new InvalidArgumentException('❌ حجم نامعتبر است.');
        }
        $tariffs = fx_duration_tariffs($panel, $agent);
        if (!isset($tariffs[(int) $days])) {
            throw new InvalidArgumentException('❌ برای این مدت تعرفه‌ای تعریف نشده است؛ دوباره مدت سرویس را انتخاب کنید.');
        }
        $volumeUnit = fx_duration_unit_price($panel, $agent, $tariffs[(int) $days], (int)$days);
        $timeUnit = 0.0;
    }
    $price = (float) $volume * (float) fx_adjust_base_toman($volumeUnit, $panel, 'custom_volume')
        + (float) $days * (float) fx_adjust_base_toman($timeUnit, $panel, 'custom_time');
    return [
        'mode' => $config['enabled'] ? 'duration' : 'legacy',
        'volume_gb' => (int) $volume,
        'time_days' => (int) $days,
        'price_per_gb' => (float) fx_adjust_base_toman($volumeUnit, $panel, 'custom_volume'),
        'base_price' => (float) $volume * $volumeUnit + (float) $days * $timeUnit,
        'price' => $price,
    ];
}

function fx_custom_price(array $panel, string $agent, $volume, $days): float
{
    return fx_custom_resolve($panel, $agent, $volume, $days)['price'];
}

function fx_custom_renew_panel(array $panel, string $agent): array
{
    if (fx_duration_config($panel, $agent)['enabled']) {
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

function fx_custom_quote_context(array $panel, $kinds): ?array
{
    $context = fx_context($panel, $kinds);
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

function fx_custom_api_quote_panel(array $panel, array $user, array $product, string $flow, string $discountCode = ''): array
{
    $kinds = ['custom_volume', 'custom_time'];
    $price = $flow === 'buy' ? (float)$product['price_product'] : (float)fx_finalize_amount($product['price_product'], $panel, $kinds);
    $discount = (int)($user['pricediscount'] ?? 0);
    if ($flow === 'buy') {
        $price = fx_apply_user_discount($price, min(100, max(0, $discount)));
    }
    if ($discountCode !== '') {
        $validation = MiniDiscount::validateSellForCategories($discountCode, $flow, (string)$product['code_product'],
            (string)$panel['code_panel'], nmProductCategoryList($product), $user);
        if (empty($validation['ok'])) {
            throw new InvalidArgumentException((string)($validation['reason'] ?? 'کد تخفیف نامعتبر است.'));
        }
        $price = fx_finalize_amount(MiniDiscount::applyToPrice($validation['row'], (float)$price), $panel, $kinds);
    }
    if ($flow !== 'buy') {
        $price = (int)round(fx_apply_user_discount($price, $discount));
    }
    $panel['_custom_quote_user'] = [(string)$user['id'], (string)($user['agent'] ?? 'f'), $discount];
    $panel['_custom_quote_total'] = fx_finalize_amount($price, $panel, $kinds);
    $panel['_custom_quote_selection'] = [$flow, (int)$product['Volume_constraint'], (int)$product['Service_time'], $discountCode];
    return $panel;
}

function fx_custom_snapshot(array $panel, string $agent, $volume, $days, $total, ?array $snapshot): ?array
{
    if (!fx_duration_config($panel, $agent)['enabled']) {
        return $snapshot;
    }
    $pricing = fx_custom_resolve($panel, $agent, $volume, $days);
    $pricing['final_price'] = (float)$total;
    $pricing['group'] = $agent;
    return array_merge($snapshot ?? ['mode' => 'duration', 'created_at' => time(), 'final_price' => (float)$total], ['custom_pricing' => $pricing]);
}

function fx_custom_price_text(array $panel, string $agent, $volume, $days, $total): string
{
    if (!fx_duration_config($panel, $agent)['enabled']) {
        return '';
    }
    $resolved = fx_custom_resolve($panel, $agent, $volume, $days);
    return "\n📦 " . $volume . ' GB · ' . $days . " روز\n💰 " . number_format($resolved['price_per_gb'])
        . ' تومان/GB · مبلغ کل: ' . number_format((float) $total) . ' تومان';
}

function fx_duration_keyboard(array $panel, string $agent): string
{
    $rows = [];
    foreach (fx_duration_public_tariffs($panel, $agent) as $days => $price) {
        $rows[] = [['text' => $days . ' روز · ' . number_format($price) . ' تومان/GB',
            'callback_data' => 'cdpick_' . $days]];
    }
    $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'backuser']];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}
