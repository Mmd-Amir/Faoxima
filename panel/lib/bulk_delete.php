<?php

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/pagination.php';

if (!function_exists('fx_bulk_delete_ids')) {

    function fx_bulk_delete_ids(PDO $pdo, string $table, string $pkCol, array $rawIds, bool $castInt = true): int
    {
        if ($castInt) {
            $ids = array_filter(array_map('intval', $rawIds), function ($v) { return $v > 0; });
            $ids = array_values(array_unique($ids));
        } else {
            $ids = array_filter(array_map('strval', $rawIds), function ($v) { return $v !== ''; });
            $ids = array_values(array_unique($ids));
        }
        if (!$ids) return 0;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pkCol` IN ($ph)");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }
}

if (!function_exists('fx_bulk_delete_redirect')) {

    function fx_bulk_delete_redirect(string $baseUrl, int $requested, int $deleted, string $suffix = ''): void
    {
        $param = 'bulk' . $suffix;
        $qs = $requested > 0
            ? ($deleted > 0 ? "$param=ok&{$param}n=$deleted" : "$param=err")
            : "$param=empty";
        header('Location: ' . $baseUrl . '?' . $qs);
        exit;
    }
}

if (!function_exists('fx_bulk_delete_flash_html')) {

    function fx_bulk_delete_flash_html(string $suffix = '', string $label = ''): string
    {
        $param = 'bulk' . $suffix;
        $bulk = (string)($_GET[$param] ?? '');
        if ($bulk === '') return '';

        $prefix = $label !== '' ? $label . ': ' : '';

        if ($bulk === 'ok') {
            $n = (int)($_GET[$param . 'n'] ?? 0);
            $msg = $prefix . $n . ' مورد با موفقیت حذف شد.';
            return '<div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:10px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($bulk === 'empty') {
            return '<div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); padding:12px 16px; border-radius:10px; margin-bottom:18px;">' . htmlspecialchars($prefix . 'هیچ موردی انتخاب نشده بود.', ENT_QUOTES, 'UTF-8') . '</div>';
        }
        return '<div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:10px; margin-bottom:18px;">' . htmlspecialchars($prefix . 'حذف موارد انتخاب‌شده ناموفق بود.', ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

if (!function_exists('fx_filter_delete_date_params')) {

    function fx_filter_delete_date_params(string $suffix, bool $active): array
    {
        if (!$active) return [];
        $out = [];
        foreach (['dpreset', 'ddays', 'dfrom', 'dto'] as $k) {
            $v = trim((string)($_GET[$k . $suffix] ?? ''));
            if ($v !== '') $out[$k . $suffix] = $v;
        }
        return $out;
    }
}

if (!function_exists('fx_filter_delete_criteria')) {

    function fx_filter_delete_criteria(string $statusLabel, array $df, string $q, string $statusTitle = 'وضعیت'): array
    {
        $out = [];
        if ($statusLabel !== '') $out[$statusTitle] = $statusLabel;
        if (!empty($df['active'])) $out['تاریخ'] = (string)$df['label'];
        if ($q !== '') $out['جستجو'] = $q;
        return $out;
    }
}

if (!function_exists('fx_filter_delete_requested')) {

    function fx_filter_delete_requested(string $scope = ''): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
        if ((string)($_POST['action'] ?? '') !== 'filter_delete') return false;
        if ((string)($_POST['_fd_scope'] ?? '') !== $scope) return false;
        fx_csrf_guard();
        return true;
    }
}

if (!function_exists('fx_filter_delete_where')) {

    function fx_filter_delete_where(PDO $pdo, string $table, string $whereSql, array $params): array
    {
        $matched = 0;
        try {
            $c = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE $whereSql");
            $c->execute($params);
            $matched = (int)$c->fetchColumn();
            if ($matched === 0) return [0, 0];
            $d = $pdo->prepare("DELETE FROM `$table` WHERE $whereSql");
            $d->execute($params);
            return [$matched, $d->rowCount()];
        } catch (\Throwable $e) {
            return [$matched, 0];
        }
    }
}

if (!function_exists('fx_filter_delete_collect')) {

    function fx_filter_delete_collect(PDO $pdo, string $table, string $col, string $whereSql, array $params): array
    {
        try {
            $s = $pdo->prepare("SELECT `$col` FROM `$table` WHERE $whereSql");
            $s->execute($params);
            $vals = array_map('strval', $s->fetchAll(PDO::FETCH_COLUMN));
            return array_values(array_unique(array_filter($vals, function ($v) { return $v !== ''; })));
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fx_filter_delete_redirect')) {

    function fx_filter_delete_redirect(string $baseUrl, array $filterParams, int $matched, int $deleted, string $scope = ''): void
    {
        $param = 'fdel' . $scope;
        $state = $matched <= 0 ? 'none' : ($deleted > 0 ? 'ok' : 'err');
        $qs = fx_qs($filterParams, [
            $param => $state,
            $param . 'n' => $matched > 0 ? (string)$deleted : null,
            $param . 't' => $matched > 0 ? (string)$matched : null,
        ]);
        header('Location: ' . $baseUrl . ($qs !== '' ? '?' . $qs : ''));
        exit;
    }
}

if (!function_exists('fx_filter_delete_flash_html')) {

    function fx_filter_delete_flash_html(string $scope = '', string $label = ''): string
    {
        $param = 'fdel' . $scope;
        $state = (string)($_GET[$param] ?? '');
        if ($state === '') return '';

        $prefix = $label !== '' ? $label . ': ' : '';
        $n = max(0, (int)($_GET[$param . 'n'] ?? 0));
        $t = max(0, (int)($_GET[$param . 't'] ?? 0));
        $box = 'padding:12px 16px; border-radius:10px; margin-bottom:18px;';

        if ($state === 'ok' && $n >= $t) {
            $msg = $prefix . number_format($n) . ' نتیجه فیلترشده با موفقیت حذف شد.';
            return '<div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); ' . $box . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($state === 'ok') {
            $msg = $prefix . 'از ' . number_format($t) . ' نتیجه فیلترشده، ' . number_format($n) . ' مورد حذف شد و ' . number_format($t - $n) . ' مورد قابل حذف نبود.';
            return '<div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); ' . $box . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($state === 'err') {
            $msg = $prefix . 'هیچ‌کدام از ' . number_format($t) . ' نتیجه فیلترشده قابل حذف نبود.';
            return '<div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); ' . $box . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $msg = $prefix . 'فیلتر فعالی وجود نداشت یا نتیجه‌ای برای حذف یافت نشد.';
        return '<div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); ' . $box . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

if (!function_exists('fx_filter_delete_parts')) {

    function fx_filter_delete_parts(string $baseUrl, array $filterParams, int $count, array $criteria, string $scope = '', string $note = ''): array
    {
        $criteria = array_filter($criteria, function ($v) { return (string)$v !== ''; });
        if ($count <= 0 || !$criteria) return ['form' => '', 'button' => ''];

        $n = number_format($count);
        if (count($criteria) === 1) {
            $val = (string)reset($criteria);
            $key = (string)key($criteria);
            $btnText = 'حذف همه ' . $n . ' مورد «' . $val . '»';
            $msg = 'آیا از حذف همه ' . $n . ' مورد فیلترشده با ' . $key . ' «' . $val . '» مطمئن هستید؟' . "\n" . 'این عملیات شامل همه صفحات است و قابل بازگشت نیست.';
        } else {
            $parts = [];
            foreach ($criteria as $k => $v) $parts[] = $k . ': «' . $v . '»';
            $btnText = 'حذف همه ' . $n . ' نتیجه فیلترشده';
            $msg = 'آیا از حذف همه ' . $n . ' نتیجه مطابق فیلتر فعلی (' . implode('، ', $parts) . ') مطمئن هستید؟' . "\n" . 'این عملیات محدود به صفحه فعلی نیست، همه نتایج مطابق فیلتر را حذف می‌کند و قابل بازگشت نیست.';
        }
        if ($note !== '') $msg .= "\n" . $note;

        $qs = fx_qs($filterParams);
        $action = $baseUrl . ($qs !== '' ? '?' . $qs : '');
        $onsubmit = 'return confirm(' . json_encode($msg, JSON_UNESCAPED_UNICODE) . ');';
        $shortText = 'حذف همه (' . $n . ')';

        $formId = 'fx-filter-delete-form' . $scope;
        $form = '<form id="' . htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') . '" method="POST" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="fx-filter-delete__form" onsubmit="' . htmlspecialchars($onsubmit, ENT_QUOTES, 'UTF-8') . '" hidden>';
        $form .= fx_csrf_field();
        $form .= '<input type="hidden" name="action" value="filter_delete">';
        $form .= '<input type="hidden" name="_fd_scope" value="' . htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') . '">';
        $form .= '</form>';
        $button = '<button type="submit" form="' . htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') . '" class="btn btn-soft-danger fx-filter-delete__btn" title="' . htmlspecialchars($btnText, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($btnText, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($shortText, ENT_QUOTES, 'UTF-8') . '</button>';
        return ['form' => $form, 'button' => $button];
    }
}
