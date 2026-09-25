<?php

require_once __DIR__ . '/pagination.php';

if (!function_exists('fx_extra_text_param')) {

    function fx_extra_text_param(string $param, int $maxLen = 200): string
    {
        $raw = $_GET[$param] ?? '';
        if (!is_string($raw)) return '';
        $v = trim($raw);
        if ($v === '') return '';
        if (mb_strlen($v, 'UTF-8') > $maxLen) $v = mb_substr($v, 0, $maxLen, 'UTF-8');
        return $v;
    }
}

if (!function_exists('fx_amount_param')) {

    function fx_amount_param(string $param): ?int
    {
        $raw = fx_extra_text_param($param, 40);
        if ($raw === '') return null;
        $raw = strtr($raw, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ',' => '', '٬' => '', '،' => '', ' ' => '',
        ]);
        if (!preg_match('/^[0-9]{1,15}$/', $raw)) return null;
        return (int)$raw;
    }
}

if (!function_exists('fx_amount_filter_resolve')) {

    function fx_amount_filter_resolve(string $minParam = 'min_price', string $maxParam = 'max_price'): array
    {
        $min = fx_amount_param($minParam);
        $max = fx_amount_param($maxParam);
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $label = '';
        if ($min !== null && $max !== null) {
            $label = 'از ' . number_format($min) . ' تا ' . number_format($max);
        } elseif ($min !== null) {
            $label = 'حداقل ' . number_format($min);
        } elseif ($max !== null) {
            $label = 'حداکثر ' . number_format($max);
        }
        return [
            'min' => $min,
            'max' => $max,
            'active' => $min !== null || $max !== null,
            'label' => $label,
            'minParam' => $minParam,
            'maxParam' => $maxParam,
        ];
    }
}

if (!function_exists('fx_amount_filter_sql')) {

    function fx_amount_filter_sql(string $col, array $af, array &$params, string $prefix): string
    {
        if (empty($af['active'])) return '';
        $sql = " AND (`$col` REGEXP '^-?[0-9]+([.][0-9]+)?$'";
        if ($af['min'] !== null) {
            $params[":{$prefix}min"] = (int)$af['min'];
            $sql .= " AND CAST(`$col` AS DECIMAL(30,4)) >= :{$prefix}min";
        }
        if ($af['max'] !== null) {
            $params[":{$prefix}max"] = (int)$af['max'];
            $sql .= " AND CAST(`$col` AS DECIMAL(30,4)) <= :{$prefix}max";
        }
        return $sql . ')';
    }
}

if (!function_exists('fx_amount_filter_keep')) {

    function fx_amount_filter_keep(array $af): array
    {
        return [
            $af['minParam'] => $af['min'],
            $af['maxParam'] => $af['max'],
        ];
    }
}

if (!function_exists('fx_extra_filter_ui')) {

    function fx_extra_filter_ui(string $baseUrl, array $keepParams, array $fields, array $activeCriteria = [], string $title = 'فیلترهای بیشتر'): string
    {
        $html = '<div class="card fx-date-filter fx-extra-filter">';
        $html .= '<div class="fx-date-filter__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<form method="GET" action="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '" class="fx-date-filter__form">';
        foreach ($keepParams as $k => $v) {
            if ($v === null || $v === '') continue;
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
        }

        $resetOverrides = [];
        $anyActive = false;
        foreach ($fields as $f) {
            $name = (string)$f['name'];
            $value = $f['value'] ?? '';
            $value = $value === null ? '' : (string)$value;
            $resetOverrides[$name] = null;
            if ($value !== '') $anyActive = true;
            $id = 'fx-x-' . preg_replace('/[^A-Za-z0-9_-]/', '', $name);

            $html .= '<div class="fx-date-filter__field"><label for="' . $id . '">' . htmlspecialchars((string)$f['label'], ENT_QUOTES, 'UTF-8') . '</label>';
            if (($f['type'] ?? 'text') === 'select') {
                $html .= '<select id="' . $id . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" class="form-control" style="height:38px; padding-top:0; padding-bottom:0; font-size:13px;">';
                $html .= '<option value="">' . htmlspecialchars((string)($f['all_label'] ?? 'همه'), ENT_QUOTES, 'UTF-8') . '</option>';
                foreach (($f['options'] ?? []) as $optValue => $optLabel) {
                    $optValue = (string)$optValue;
                    $sel = ($optValue === $value) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars((string)$optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                }
                $html .= '</select>';
            } elseif (($f['type'] ?? 'text') === 'amount') {
                $html .= '<input type="text" inputmode="numeric" id="' . $id . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars((string)($f['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" style="direction:ltr;">';
            } else {
                $html .= '<input type="text" id="' . $id . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars((string)($f['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
            }
            $html .= '</div>';
        }

        $html .= '<div class="fx-date-filter__actions">';
        $html .= '<button type="submit" class="btn btn-primary">اعمال فیلتر</button>';
        if ($anyActive) {
            $qs = fx_qs($keepParams, $resetOverrides);
            $html .= '<a class="btn btn-outline" href="' . htmlspecialchars($baseUrl . ($qs !== '' ? '?' . $qs : ''), ENT_QUOTES, 'UTF-8') . '">حذف فیلتر</a>';
        }
        $html .= '</div>';
        $html .= '</form>';

        $activeCriteria = array_filter($activeCriteria, function ($v) { return (string)$v !== ''; });
        if ($activeCriteria) {
            $parts = [];
            foreach ($activeCriteria as $k => $v) $parts[] = $k . ': ' . $v;
            $html .= '<div class="fx-date-filter__active">فیلتر فعال: ' . htmlspecialchars(implode('، ', $parts), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}
