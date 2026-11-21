<?php

namespace Faoxima\Admin\Web\Support;

use PDO;

final class Pagination
{
    public static function fx_paginate(PDO $pdo, string $countSql, array $countParams = [], int $perPage = 5, string $pageParam = 'p', ?array $query = null): array
    {
        $query ??= $_GET;
        $page = isset($query[$pageParam]) ? max(1, (int)$query[$pageParam]) : 1;

        $total = 0;
        try {
            $c = $pdo->prepare($countSql);
            $c->execute($countParams);
            $total = (int)$c->fetchColumn();
        } catch (\Throwable $e) {
        }

        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $perPage;

        return ['page' => $page, 'perPage' => $perPage, 'offset' => $offset, 'total' => $total, 'pages' => $pages];
    }

    public static function fx_qs(array $params, array $overrides = []): string
    {
        $qp = $params;
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') unset($qp[$k]);
            else $qp[$k] = $v;
        }
        return http_build_query($qp);
    }

    public static function fx_persian_num($n): string
    {
        $ar = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return preg_replace_callback('/\d/', function ($m) use ($ar) {
            return $ar[(int)$m[0]];
        }, (string)$n);
    }

    public static function fx_pager_html(int $page, int $pages, int $total, int $shown, string $baseUrl, array $keepParams = [], string $pageParam = 'p'): string
    {
        if ($total === 0) {
            return '<div class="mdt-foot"><div class="mdt-info">هیچ ردیفی برای نمایش نیست</div></div>';
        }

        $perPage = $shown > 0 ? $shown : 1;
        $start = (($page - 1) * $perPage) + 1;
        $end = min($start + $shown - 1, $total);

        $info = 'نمایش ' . self::fx_persian_num($start) . ' تا ' . self::fx_persian_num($end) . ' از ' . self::fx_persian_num($total) . ' ردیف';

        $html = '<div class="mdt-foot"><div class="mdt-info">' . $info . '</div>';

        if ($pages > 1) {
            $html .= '<div class="mdt-pager">';

            $link = function (int $target) use ($baseUrl, $keepParams, $pageParam) {
                $qs = self::fx_qs($keepParams, [$pageParam => $target]);
                return htmlspecialchars($baseUrl . '?' . $qs, ENT_QUOTES, 'UTF-8');
            };

            if ($page > 1) {
                $html .= '<a class="mdt-page" href="' . $link($page - 1) . '">قبلی</a>';
            } else {
                $html .= '<span class="mdt-page disabled">قبلی</span>';
            }

            $maxBtns = 7;
            $startP = max(1, $page - (int)floor($maxBtns / 2));
            $endP = min($pages, $startP + $maxBtns - 1);
            $startP = max(1, $endP - $maxBtns + 1);

            if ($startP > 1) {
                $html .= '<a class="mdt-page" href="' . $link(1) . '">' . self::fx_persian_num(1) . '</a>';
                if ($startP > 2) $html .= '<span class="mdt-dots">...</span>';
            }

            for ($p = $startP; $p <= $endP; $p++) {
                if ($p === $page) {
                    $html .= '<span class="mdt-page active">' . self::fx_persian_num($p) . '</span>';
                } else {
                    $html .= '<a class="mdt-page" href="' . $link($p) . '">' . self::fx_persian_num($p) . '</a>';
                }
            }

            if ($endP < $pages) {
                if ($endP < $pages - 1) $html .= '<span class="mdt-dots">...</span>';
                $html .= '<a class="mdt-page" href="' . $link($pages) . '">' . self::fx_persian_num($pages) . '</a>';
            }

            if ($page < $pages) {
                $html .= '<a class="mdt-page" href="' . $link($page + 1) . '">بعدی</a>';
            } else {
                $html .= '<span class="mdt-page disabled">بعدی</span>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}
