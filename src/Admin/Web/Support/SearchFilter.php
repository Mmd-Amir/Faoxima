<?php

namespace Faoxima\Admin\Web\Support;

final class SearchFilter
{
    public static function fx_search_current(string $param = 'q'): string
    {
        return trim((string)($_GET[$param] ?? ''));
    }

    public static function fx_search_ui(string $baseUrl, string $current, array $keepParams = [], string $placeholder = 'جستجو…', string $param = 'q'): string
    {
        $html = '<div class="card fx-search-filter">';
        $html .= '<form method="GET" action="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '" class="fx-search-filter__form">';
        foreach ($keepParams as $k => $v) {
            if ($v === null || $v === '') continue;
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html .= '<input type="search" name="' . htmlspecialchars($param, ENT_QUOTES, 'UTF-8') . '" class="fx-search-filter__input" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($current, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<button type="submit" class="btn btn-primary fx-search-filter__btn">جستجو</button>';
        if ($current !== '') {
            $qs = \Faoxima\Admin\Web\Support\Pagination::fx_qs($keepParams, [$param => null]);
            $html .= '<a class="btn btn-outline fx-search-filter__btn" href="' . htmlspecialchars($baseUrl . '?' . $qs, ENT_QUOTES, 'UTF-8') . '">حذف فیلتر</a>';
        }
        $html .= '</form>';
        $html .= '</div>';
        return $html;
    }
}
