<?php

namespace Faoxima\Admin\Web\Support;

final class StatusFilter
{
    public static function fx_status_filter_current(string $param = 'status'): string
    {
        return trim((string)($_GET[$param] ?? ''));
    }

    public static function fx_status_filter_sql(string $col, string $current, array $valueMap, array &$params, string $bindKey): string
    {
        if ($current === '' || !isset($valueMap[$current])) return '';
        $params[$bindKey] = $current;
        return " AND $col = $bindKey";
    }

    public static function fx_status_filter_matches(string $current, string $rowKey): bool
    {
        if ($current === '') return true;
        return $current === $rowKey;
    }

    public static function fx_status_filter_ui(string $baseUrl, array $options, string $current, array $keepParams = [], string $param = 'status', string $title = 'فیلتر بر اساس وضعیت'): string
    {
        $link = function (string $value) use ($baseUrl, $keepParams, $param) {
            $qs = \Faoxima\Admin\Web\Support\Pagination::fx_qs($keepParams, [$param => $value !== '' ? $value : null]);
            return htmlspecialchars($baseUrl . '?' . $qs, ENT_QUOTES, 'UTF-8');
        };

        $html = '<div class="card fx-status-filter">';
        $html .= '<div class="fx-status-filter__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div class="fx-status-filter__pills">';

        $allActive = ($current === '') ? ' btn-primary' : ' btn-outline';
        $html .= '<a class="btn fx-status-filter__pill' . $allActive . '" href="' . $link('') . '">همه</a>';

        foreach ($options as $value => $label) {
            $active = ($current === $value) ? ' btn-primary' : ' btn-outline';
            $html .= '<a class="btn fx-status-filter__pill' . $active . '" href="' . $link($value) . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}
