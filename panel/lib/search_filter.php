<?php

require_once __DIR__ . '/pagination.php';

if (!function_exists('fx_search_current')) {

    function fx_search_current(string $param = 'q'): string
    {
        return trim((string)($_GET[$param] ?? ''));
    }
}

if (!function_exists('fx_search_ui')) {

    function fx_search_ui(string $baseUrl, string $current, array $keepParams = [], string $placeholder = 'جستجو…', string $param = 'q', bool $privateSearch = false): string
    {
        $html = '<div class="card fx-search-filter">';
        $html .= '<form method="' . ($privateSearch ? 'POST' : 'GET') . '" action="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '" class="fx-search-filter__form">';
        $hiddenLookup = $privateSearch && !empty($keepParams['lookup']);
        if ($privateSearch) {
            unset($keepParams['lookup']);
            $html .= fx_csrf_field() . '<input type="hidden" name="action" value="service_lookup">';
        }
        foreach ($keepParams as $k => $v) {
            if ($v === null || $v === '') continue;
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html .= '<input type="search" autocomplete="off" spellcheck="false" name="' . htmlspecialchars($param, ENT_QUOTES, 'UTF-8') . '" class="fx-search-filter__input" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($hiddenLookup ? '' : $current, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<button type="submit" class="btn btn-primary fx-search-filter__btn">جستجو</button>';
        if ($current !== '' || $hiddenLookup) {
            $qs = fx_qs($keepParams, [$param => null]);
            $html .= '<a class="btn btn-outline fx-search-filter__btn" href="' . htmlspecialchars($baseUrl . '?' . $qs, ENT_QUOTES, 'UTF-8') . '">حذف فیلتر</a>';
        }
        $html .= '</form>';
        $html .= '</div>';
        return $html;
    }
}
