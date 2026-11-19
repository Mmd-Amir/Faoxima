<?php

if (!defined('FAOXIMA_REMAINING_SCAN_CAP')) {
    define('FAOXIMA_REMAINING_SCAN_CAP', 1500);
}

if (!function_exists('faoxima_remaining_filter_page')) {
    function faoxima_remaining_filter_page(array $rows, int $page, int $perPage, string $mode, int $days, callable $resolve): array
    {
        $truncated = count($rows) > FAOXIMA_REMAINING_SCAN_CAP;
        if ($truncated) {
            $rows = array_slice($rows, 0, FAOXIMA_REMAINING_SCAN_CAP);
        }
        $remaining = $resolve($rows);
        $matched = [];
        foreach ($rows as $row) {
            $value = $remaining[(int)$row['id']] ?? null;
            if (faoxima_remaining_matches($value, $mode, $days)) {
                $matched[] = $row;
            }
        }
        $total = count($matched);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $matched = array_slice($matched, ($page - 1) * $perPage, $perPage);
        $remainingOf = [];
        foreach ($matched as $row) {
            $remainingOf[(int)$row['id']] = $remaining[(int)$row['id']] ?? null;
        }
        return [$total, $pages, $page, $matched, $remainingOf, $truncated];
    }
}

if (!function_exists('faoxima_parse_sell_time')) {
    function faoxima_parse_sell_time($raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (is_float($raw)) {
            return $raw > 0 ? (int)$raw : null;
        }
        $raw = trim((string)$raw);
        if ($raw === '') {
            return null;
        }

        $raw = strtr($raw, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        if (ctype_digit($raw)) {
            $ts = (int)$raw;
            return $ts > 0 ? $ts : null;
        }

        if (preg_match('#^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})(?:[\sT]+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?#', $raw, $m)) {
            $y = (int)$m[1];
            if ($y > 0 && $y < 1700 && function_exists('jalali_to_gregorian')) {
                $g = jalali_to_gregorian($y, (int)$m[2], (int)$m[3]);
                if (is_array($g) && count($g) >= 3) {
                    $ts = mktime(
                        isset($m[4]) ? (int)$m[4] : 0,
                        isset($m[5]) ? (int)$m[5] : 0,
                        isset($m[6]) ? (int)$m[6] : 0,
                        (int)$g[1],
                        (int)$g[2],
                        (int)$g[0]
                    );
                    if ($ts !== false) {
                        return $ts;
                    }
                }
            }
        }

        $ts = strtotime(str_replace('/', '-', $raw));
        return $ts === false ? null : $ts;
    }
}

if (!function_exists('faoxima_is_test_account')) {
    function faoxima_is_test_account($username = '', $productName = ''): bool
    {
        $u = strtolower(trim((string)$username));
        $p = strtolower(trim((string)$productName));
        return $u === 'usertest'
            || strpos($u, 'usertest') !== false
            || $p === 'usertest'
            || $p === 'سرویس تست'
            || strpos($p, 'usertest') !== false
            || strpos($p, 'تست') !== false;
    }
}

if (!function_exists('faoxima_remaining_days')) {
    function faoxima_remaining_days($timeSell, $serviceTime, $unitOrContext = 'days', string $productName = ''): array
    {
        $timeVal = (int)$serviceTime;
        if ($timeVal <= 0) {
            return ['state' => 'unlimited', 'days' => null, 'hours' => null, 'unit' => 'days', 'left_seconds' => null];
        }
        $sellTs = faoxima_parse_sell_time($timeSell);
        if ($sellTs === null) {
            return ['state' => 'unknown', 'days' => null, 'hours' => null, 'unit' => 'days', 'left_seconds' => null];
        }

        $unit = 'days';
        if (is_bool($unitOrContext)) {
            $unit = $unitOrContext ? 'hours' : 'days';
        } elseif (is_string($unitOrContext)) {
            if ($unitOrContext === 'hours' || $unitOrContext === 'hour') {
                $unit = 'hours';
            } elseif (faoxima_is_test_account($unitOrContext, $productName)) {
                $unit = 'hours';
            }
        } elseif (is_array($unitOrContext)) {
            $unit = ($unitOrContext['unit'] ?? '') === 'hours' || !empty($unitOrContext['is_test']) ? 'hours' : 'days';
        }

        $multiplier = ($unit === 'hours') ? 3600 : 86400;
        $expireTs = $sellTs + ($timeVal * $multiplier);
        $left = $expireTs - time();

        if ($left <= 0) {
            return [
                'state'        => 'expired',
                'days'         => 0,
                'hours'        => 0,
                'unit'         => $unit,
                'left_seconds' => 0,
            ];
        }

        $leftHours = (int)ceil($left / 3600);
        $leftDays  = (int)ceil($left / 86400);

        return [
            'state'        => 'active',
            'days'         => $leftDays,
            'hours'        => $leftHours,
            'unit'         => $unit,
            'left_seconds' => $left,
        ];
    }
}

if (!function_exists('faoxima_remaining_label')) {
    function faoxima_remaining_label(?array $rem): string
    {
        if ($rem === null) {
            return 'نامشخص';
        }
        if ($rem['state'] === 'unlimited') {
            return 'نامحدود';
        }
        if ($rem['state'] === 'unknown') {
            return 'نامشخص';
        }
        if ($rem['state'] === 'expired') {
            return 'منقضی شده';
        }
        if (($rem['unit'] ?? 'days') === 'hours') {
            $h = (int)($rem['hours'] ?? 0);
            return $h . ' ساعت مانده';
        }
        $d = (int)($rem['days'] ?? 0);
        if ($d <= 0 && isset($rem['hours']) && $rem['hours'] > 0) {
            return (int)$rem['hours'] . ' ساعت مانده';
        }
        return $d . ' روز مانده';
    }
}

if (!function_exists('faoxima_remaining_class')) {
    function faoxima_remaining_class(?array $rem): string
    {
        if ($rem === null || $rem['state'] === 'unknown' || $rem['state'] === 'unlimited') {
            return 'badge-gray';
        }
        if ($rem['state'] === 'expired') {
            return 'badge-danger';
        }
        if (($rem['unit'] ?? 'days') === 'hours') {
            $h = (int)($rem['hours'] ?? 0);
            if ($h <= 3) {
                return 'badge-danger';
            }
            if ($h <= 12) {
                return 'badge-warning';
            }
            return 'badge-active';
        }
        $d = (int)$rem['days'];
        if ($d <= 3) {
            return 'badge-danger';
        }
        if ($d <= 7) {
            return 'badge-warning';
        }
        return 'badge-active';
    }
}

if (!function_exists('faoxima_remaining_badge')) {
    function faoxima_remaining_badge(?array $rem): string
    {
        $ico = function_exists('icon') ? icon('hourglass', 'svg-icon svg-sm') : '';
        return '<span class="badge ' . faoxima_remaining_class($rem) . '">'
            . $ico . ' ' . htmlspecialchars(faoxima_remaining_label($rem), ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('faoxima_remaining_sort_key')) {
    function faoxima_remaining_sort_key(?array $rem): array
    {
        if ($rem === null) {
            return [3, 0];
        }
        if ($rem['state'] === 'expired') {
            return [0, 0];
        }
        if ($rem['state'] === 'active') {
            return [1, (int)$rem['days']];
        }
        if ($rem['state'] === 'unknown') {
            return [2, 0];
        }
        return [3, 0];
    }
}

if (!function_exists('faoxima_remaining_compare')) {
    function faoxima_remaining_compare(?array $a, ?array $b): int
    {
        $ka = faoxima_remaining_sort_key($a);
        $kb = faoxima_remaining_sort_key($b);
        if ($ka[0] !== $kb[0]) {
            return $ka[0] <=> $kb[0];
        }
        return $ka[1] <=> $kb[1];
    }
}

if (!function_exists('faoxima_remaining_matches')) {
    function faoxima_remaining_matches(?array $rem, string $mode, ?int $days): bool
    {
        if ($days === null) {
            return true;
        }
        if ($rem === null || $rem['state'] === 'unknown' || $rem['state'] === 'unlimited') {
            return false;
        }
        $have = (int)$rem['days'];
        if ($mode === 'eq') {
            return $have === $days;
        }
        if ($mode === 'gte') {
            return $have >= $days;
        }
        return $have <= $days;
    }
}

if (!function_exists('faoxima_remaining_dmode')) {
    function faoxima_remaining_dmode($raw): string
    {
        $m = strtolower(trim((string)$raw));
        return in_array($m, ['lte', 'eq', 'gte'], true) ? $m : 'lte';
    }
}

if (!function_exists('faoxima_remaining_fetch_rows')) {
 
    function faoxima_remaining_fetch_rows(PDO $pdo, string $column, array $keys): array
    {
        if (!in_array($column, ['username', 'id_invoice'], true)) {
            return [];
        }

        $clean = [];
        foreach ($keys as $k) {
            if ($k === null) {
                continue;
            }
            $k = trim((string)$k);
            if ($k === '') {
                continue;
            }
            $clean[$k] = true;
        }
        if (!$clean) {
            return [];
        }

        $list = array_slice(array_keys($clean), 0, FAOXIMA_REMAINING_SCAN_CAP);
        $ph = implode(',', array_fill(0, count($list), '?'));

        $sql = "SELECT `{$column}` AS k, time_sell, Service_time, Status, name_product, username
                FROM invoice
                WHERE `{$column}` IN ({$ph})
                ORDER BY CAST(time_sell AS UNSIGNED) ASC";

        $out = [];
        try {
            $st = $pdo->prepare($sql);
            foreach ($list as $i => $v) {
                $st->bindValue($i + 1, (string)$v, PDO::PARAM_STR);
            }
            $st->execute();
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[(string)$r['k']] = [
                    'time_sell'    => $r['time_sell'] ?? null,
                    'Service_time' => $r['Service_time'] ?? null,
                    'Status'       => $r['Status'] ?? null,
                    'name_product' => $r['name_product'] ?? null,
                    'username'     => $r['username'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            return [];
        }

        return $out;
    }
}

if (!function_exists('faoxima_remaining_fetch_by_username')) {
    function faoxima_remaining_fetch_by_username(PDO $pdo, array $usernames): array
    {
        return faoxima_remaining_fetch_rows($pdo, 'username', $usernames);
    }
}

if (!function_exists('faoxima_remaining_fetch_by_invoice')) {
    function faoxima_remaining_fetch_by_invoice(PDO $pdo, array $invoiceIds): array
    {
        return faoxima_remaining_fetch_rows($pdo, 'id_invoice', $invoiceIds);
    }
}
