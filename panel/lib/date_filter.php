<?php

require_once __DIR__ . '/../../jdf.php';
require_once __DIR__ . '/pagination.php';

if (!function_exists('fx_date_filter_resolve')) {

    function fx_date_filter_resolve(string $suffix = ''): array
    {
        $none = ['from' => null, 'to' => null, 'active' => false, 'label' => ''];

        $dfrom = trim((string)($_GET['dfrom' . $suffix] ?? ''));
        $dto   = trim((string)($_GET['dto' . $suffix] ?? ''));
        if ($dfrom !== '' && $dto !== '') {
            $f = preg_split('/[-\/]/', $dfrom);
            $t = preg_split('/[-\/]/', $dto);
            if (count($f) === 3 && count($t) === 3) {
                $from = jmktime(0, 0, 0, (int)$f[1], (int)$f[2], (int)$f[0]);
                $to   = jmktime(23, 59, 59, (int)$t[1], (int)$t[2], (int)$t[0]);
                if ($from !== false && $to !== false && $from <= $to) {
                    return ['from' => $from, 'to' => $to, 'active' => true, 'label' => 'بازه تاریخ سفارشی'];
                }
            }
            return $none;
        }

        $ddays = (int)($_GET['ddays' . $suffix] ?? 0);
        if ($ddays > 0) {
            $to = time();
            $from = $to - ($ddays * 86400);
            return ['from' => $from, 'to' => $to, 'active' => true, 'label' => $ddays . ' روز اخیر'];
        }

        $preset = trim((string)($_GET['dpreset' . $suffix] ?? ''));
        if ($preset !== '') {
            $to = time();
            switch ($preset) {
                case '24h': return ['from' => $to - 86400, 'to' => $to, 'active' => true, 'label' => '۲۴ ساعت اخیر'];
                case '1w':  return ['from' => $to - 7 * 86400, 'to' => $to, 'active' => true, 'label' => '۱ هفته اخیر'];
                case '1m':  return ['from' => $to - 30 * 86400, 'to' => $to, 'active' => true, 'label' => '۱ ماه اخیر'];
                case '3m':  return ['from' => $to - 90 * 86400, 'to' => $to, 'active' => true, 'label' => '۳ ماه اخیر'];
            }
        }

        return $none;
    }
}

if (!function_exists('fx_date_filter_sql_int')) {

    function fx_date_filter_sql_int(string $col, ?int $from, ?int $to, array &$params): string
    {
        if ($from === null || $to === null) return '';
        $params[] = $from;
        $params[] = $to;
        return " AND $col BETWEEN ? AND ?";
    }
}

if (!function_exists('fx_date_filter_sql_mixed')) {

    function fx_date_filter_sql_mixed(string $col, ?int $from, ?int $to, array &$params): string
    {
        if ($from === null || $to === null) return '';
        $params[] = $from;
        $params[] = $to;
        $params[] = $from;
        $params[] = $to;
        return " AND ((`$col` REGEXP '^[0-9]+$' AND CAST(`$col` AS UNSIGNED) BETWEEN ? AND ?)" .
            " OR (`$col` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(`$col`, '/', '-'), '%Y-%m-%d %H:%i:%s') BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)))";
    }
}

if (!function_exists('fx_date_filter_sql_mixed_named')) {

    function fx_date_filter_sql_mixed_named(string $col, ?int $from, ?int $to, array &$params, string $prefix): string
    {
        if ($from === null || $to === null) return '';
        $params[":{$prefix}1"] = $from;
        $params[":{$prefix}2"] = $to;
        $params[":{$prefix}3"] = $from;
        $params[":{$prefix}4"] = $to;
        return " AND ((`$col` REGEXP '^[0-9]+$' AND CAST(`$col` AS UNSIGNED) BETWEEN :{$prefix}1 AND :{$prefix}2)" .
            " OR (`$col` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(`$col`, '/', '-'), '%Y-%m-%d %H:%i:%s') BETWEEN FROM_UNIXTIME(:{$prefix}3) AND FROM_UNIXTIME(:{$prefix}4)))";
    }
}

if (!function_exists('fx_date_filter_matches_ts')) {

    function fx_date_filter_matches_ts(?int $rowTs, ?int $from, ?int $to): bool
    {
        if ($from === null || $to === null) return true;
        if ($rowTs === null) return false;
        return $rowTs >= $from && $rowTs <= $to;
    }
}

if (!function_exists('fx_date_filter_ui')) {

    function fx_date_filter_ui(string $baseUrl, string $suffix = '', array $keepParams = [], string $expiryLabel = '', string $actionsExtra = '', string $cardExtra = ''): string
    {
        $df = fx_date_filter_resolve($suffix);
        $title = $expiryLabel !== '' ? 'فیلتر بر اساس تاریخ ' . $expiryLabel : 'فیلتر بر اساس تاریخ';

        $presetLink = function (string $preset) use ($baseUrl, $keepParams, $suffix) {
            $qs = fx_qs($keepParams, ['dpreset' . $suffix => $preset, 'ddays' . $suffix => null, 'dfrom' . $suffix => null, 'dto' . $suffix => null]);
            return htmlspecialchars($baseUrl . '?' . $qs, ENT_QUOTES, 'UTF-8');
        };

        $curDpreset = (string)($_GET['dpreset' . $suffix] ?? '');
        $curDdays   = (string)($_GET['ddays' . $suffix] ?? '');
        $curDfrom   = (string)($_GET['dfrom' . $suffix] ?? '');
        $curDto     = (string)($_GET['dto' . $suffix] ?? '');

        $idDdays = 'fx-ddays' . $suffix;
        $idDfrom = 'fx-dfrom' . $suffix;
        $idDto   = 'fx-dto' . $suffix;

        $html = '<div class="card fx-date-filter">';
        $html .= '<div class="fx-date-filter__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div class="fx-date-filter__presets">';
        $presets = ['24h' => '۲۴ ساعت اخیر', '1w' => '۱ هفته اخیر', '1m' => '۱ ماه اخیر', '3m' => '۳ ماه اخیر'];
        foreach ($presets as $key => $label) {
            $active = ($curDpreset === $key) ? ' btn-primary' : ' btn-outline';
            $html .= '<a class="btn fx-date-filter__preset' . $active . '" href="' . $presetLink($key) . '">' . $label . '</a>';
        }
        $html .= '</div>';

        $html .= '<form method="GET" action="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '" class="fx-date-filter__form">';
        foreach ($keepParams as $k => $v) {
            if ($v === null || $v === '') continue;
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html .= '<div class="fx-date-filter__field"><label for="' . $idDdays . '">تعداد روز اخیر</label>';
        $html .= '<input type="number" id="' . $idDdays . '" name="ddays' . $suffix . '" min="1" value="' . htmlspecialchars($curDdays, ENT_QUOTES, 'UTF-8') . '"></div>';
        $html .= '<div class="fx-date-filter__field"><label for="' . $idDfrom . '">از تاریخ (مثال: ۱۴۰۴/۰۲/۱۰)</label>';
        $html .= '<input type="text" id="' . $idDfrom . '" name="dfrom' . $suffix . '" placeholder="1404/02/10" value="' . htmlspecialchars($curDfrom, ENT_QUOTES, 'UTF-8') . '" style="direction:ltr;"></div>';
        $html .= '<div class="fx-date-filter__field"><label for="' . $idDto . '">تا تاریخ</label>';
        $html .= '<input type="text" id="' . $idDto . '" name="dto' . $suffix . '" placeholder="1404/05/15" value="' . htmlspecialchars($curDto, ENT_QUOTES, 'UTF-8') . '" style="direction:ltr;"></div>';
        $html .= '<div class="fx-date-filter__actions">';
        $html .= '<button type="submit" class="btn btn-primary">اعمال فیلتر</button>';
        if ($df['active']) {
            $qs = fx_qs($keepParams, ['dpreset' . $suffix => null, 'ddays' . $suffix => null, 'dfrom' . $suffix => null, 'dto' . $suffix => null]);
            $html .= '<a class="btn btn-outline" href="' . htmlspecialchars($baseUrl . '?' . $qs, ENT_QUOTES, 'UTF-8') . '">حذف فیلتر</a>';
        }
        $html .= $actionsExtra;
        $html .= '</div>';
        $html .= '</form>';
        $html .= $cardExtra;

        if ($df['active']) {
            $html .= '<div class="fx-date-filter__active">فیلتر فعال: ' . htmlspecialchars($df['label'], ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}
