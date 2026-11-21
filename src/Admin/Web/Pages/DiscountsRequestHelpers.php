<?php

namespace Faoxima\Admin\Web\Pages;

final class DiscountsRequestHelpers
{
    public static function faoxima_d_label_targeting($mode, $category, $panelCode, $panelNameByCode) {
    if ($mode === 'category') {
        return ['🎯 دسته: ' . htmlspecialchars((string)$category, ENT_QUOTES), 'badge-purple'];
    }
    if ($mode === 'panel') {
        $codes = array_values(array_filter(array_map('trim', explode(',', (string)$panelCode))));
        $names = array_map(function ($c) use ($panelNameByCode) { return $panelNameByCode[$c] ?? $c; }, $codes);
        return ['🎯 پنل: ' . htmlspecialchars(implode('، ', $names), ENT_QUOTES), 'badge-info'];
    }
    return ['بدون محدودیت هدف', 'badge-gray'];
}

    public static function faoxima_d_label_type($t) {
    switch ($t) {
        case 'percent': return ['درصدی', 'badge-success'];
        case 'amount':  return ['مبلغی', 'badge-info'];
        case 'free':    return ['رایگان', 'badge-purple'];
        default:        return [htmlspecialchars((string)$t, ENT_QUOTES), 'badge-gray'];
    }
}

    public static function faoxima_d_label_agent($a) {
    $map = ['f' => 'کاربر عادی', 'n' => 'نماینده', 'n2' => 'نماینده+', 'all' => 'همه', 'allusers' => 'همه'];
    $values = array_values(array_filter(array_map('trim', explode(',', (string)$a))));
    if (empty($values) || in_array('allusers', $values, true) || in_array('all', $values, true)) {
        return ['همه', 'badge-success'];
    }
    $names = array_map(function ($v) use ($map) { return $map[$v] ?? $v; }, $values);
    return [htmlspecialchars(implode('، ', $names), ENT_QUOTES), count($values) > 1 ? 'badge-purple' : 'badge-info'];
}

    public static function faoxima_d_label_section($s) {
    $map = ['buy' => 'خرید', 'extend' => 'تمدید', 'volume' => 'حجم', 'time' => 'زمان', 'charge' => 'شارژ', 'all' => 'همه بخش‌ها'];
    $values = array_values(array_filter(array_map('trim', explode(',', (string)$s))));
    if (empty($values) || in_array('all', $values, true)) {
        return ['همه بخش‌ها', 'badge-gray'];
    }
    $names = array_map(function ($v) use ($map) { return $map[$v] ?? $v; }, $values);
    return [htmlspecialchars(implode('، ', $names), ENT_QUOTES), count($values) > 1 ? 'badge-purple' : 'badge-warning'];
}
}
