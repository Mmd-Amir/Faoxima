<?php

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/pagination.php';

if (!function_exists('fx_admin_config_transaction')) {
    function fx_admin_config_transaction(PDO $pdo, string $source, string $operationName, callable $operation)
    {
        $savepoint = null;
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                if (!$pdo->beginTransaction()) {
                    throw new RuntimeException('Inventory transaction could not be started');
                }
            } else {
                $name = 'fx_inventory_' . bin2hex(random_bytes(8));
                $pdo->exec('SAVEPOINT ' . $name);
                $savepoint = $name;
            }

            $result = $operation();
            if ($ownsTransaction) {
                if (!$pdo->commit()) {
                    throw new RuntimeException('Inventory transaction could not be committed');
                }
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $e) {
            try {
                if ($pdo->inTransaction()) {
                    if ($ownsTransaction) {
                        $pdo->rollBack();
                    } elseif ($savepoint !== null) {
                        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    }
                }
            } catch (Throwable $rollbackError) {
                fx_delete_log_error($operationName . '_rollback', $source, $rollbackError);
            }
            fx_delete_log_error($operationName, $source, $e);
            throw $e;
        }
    }
}

if (!function_exists('fx_admin_delete_configs')) {
    function fx_admin_delete_configs(PDO $pdo, string $table, string $where, array $params, ?int $deleteShelf = null): int
    {
        $byUsername = $table === 'manualsell';
        if ((!$byUsername && $table !== 'nm_config_stock') || ($byUsername && $deleteShelf !== null)) {
            throw new InvalidArgumentException('Invalid inventory deletion scope');
        }

        return fx_admin_config_transaction($pdo, $table, 'config_delete', static function () use ($pdo, $table, $where, $params, $deleteShelf, $byUsername): int {
            $lockedShelf = null;
            if ($deleteShelf !== null) {
                $shelf = $pdo->prepare('SELECT id FROM nm_stock_shelves WHERE id = ? FOR UPDATE');
                $shelf->execute([$deleteShelf]);
                $lockedShelf = $shelf->fetchColumn();
                $shelf->closeCursor();
            }
            $column = $byUsername ? 'username' : 'assigned_invoice';
            $linkedStatus = $byUsername ? "status = 'selled'" : "status IN ('reserved','delivered')";
            $select = $pdo->prepare("SELECT id, CASE WHEN $linkedStatus THEN `$column` END AS invoice_key FROM `$table` WHERE $where ORDER BY id FOR UPDATE");
            $select->execute($params);
            $rows = $select->fetchAll(PDO::FETCH_ASSOC);

            $keys = array_values(array_filter(array_map(static function (array $row): string {
                return trim((string) ($row['invoice_key'] ?? ''));
            }, $rows), static function (string $key): bool {
                return $key !== '';
            }));
            if ($keys) {
                $pdo->query('SELECT id_invoice FROM invoice LIMIT 0')->closeCursor();
            }

            $tables = [];
            if ($rows) {
                $tables[] = $table;
            }
            if ($keys) {
                $tables[] = 'invoice';
            }
            if ($lockedShelf !== null && $lockedShelf !== false) {
                $tables[] = 'nm_stock_shelves';
            }
            if ($tables) {
                $placeholders = implode(',', array_fill(0, count($tables), '?'));
                $engines = $pdo->prepare("SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)");
                $engines->execute($tables);
                $enginesByTable = $engines->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($tables as $targetTable) {
                    if (strcasecmp((string) ($enginesByTable[$targetTable] ?? ''), 'InnoDB') !== 0) {
                        throw new RuntimeException('Inventory deletion requires transactional tables');
                    }
                }
            }

            $invoiceColumn = $byUsername ? 'username' : 'id_invoice';
            foreach (array_chunk(array_values(array_unique($keys)), 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $deleteInvoices = $pdo->prepare("DELETE FROM invoice WHERE $invoiceColumn IN ($placeholders)");
                $deleteInvoices->execute($chunk);
            }
            $deleted = 0;
            foreach (array_chunk(array_column($rows, 'id'), 500) as $ids) {
                $deleted += fx_bulk_delete_ids($pdo, $table, 'id', $ids, false);
            }
            if ($deleteShelf !== null && $lockedShelf !== false) {
                $shelfDelete = $pdo->prepare('DELETE FROM nm_stock_shelves WHERE id = ?');
                $shelfDelete->execute([$deleteShelf]);
            }
            return $deleted;
        });
    }
}

if (!function_exists('fx_bulk_string_ids')) {
    function fx_bulk_string_ids($rawIds): array
    {
        return is_array($rawIds) ? array_values(array_unique(array_filter(array_map('strval', $rawIds), static function ($value) {
            return $value !== '';
        }))) : [];
    }
}

if (!function_exists('fx_bulk_positive_ids')) {
    function fx_bulk_positive_ids(array $rawIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $rawIds), static function ($value) {
            return $value > 0;
        })));
    }
}

if (!function_exists('fx_bulk_delete_ids')) {

    function fx_bulk_delete_ids(PDO $pdo, string $table, string $pkCol, array $rawIds, bool $castInt = true): int
    {
        if ($castInt) {
            $ids = fx_bulk_positive_ids($rawIds);
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

    function fx_bulk_delete_redirect(string $baseUrl, int $requested, int $deleted, string $suffix = '', array $keepParams = [], bool $failed = false): void
    {
        $param = 'bulk' . $suffix;
        $qs = $failed
            ? "$param=fail"
            : ($requested > 0
                ? ($deleted > 0 ? "$param=ok&{$param}n=$deleted" : "$param=err")
                : "$param=empty");
        $keepQs = fx_qs($keepParams);
        if ($keepQs !== '') $qs = $keepQs . '&' . $qs;
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
            return '<div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:var(--radius-md); margin-bottom:18px; display:flex; align-items:center; gap:10px;">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($bulk === 'fail') {
            return '<div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:var(--radius-md); margin-bottom:18px;">' . htmlspecialchars($prefix . 'حذف موارد انتخاب‌شده به‌دلیل خطای پایگاه داده انجام نشد و هیچ رکوردی حذف نشد. جزئیات خطا در لاگ سرور ثبت شد.', ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($bulk === 'empty') {
            return '<div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); padding:12px 16px; border-radius:var(--radius-md); margin-bottom:18px;">' . htmlspecialchars($prefix . 'هیچ موردی انتخاب نشده بود.', ENT_QUOTES, 'UTF-8') . '</div>';
        }
        return '<div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:var(--radius-md); margin-bottom:18px;">' . htmlspecialchars($prefix . 'حذف موارد انتخاب‌شده ناموفق بود.', ENT_QUOTES, 'UTF-8') . '</div>';
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
            fx_delete_log_error('filter_delete', $table, $e);
            return [$matched, 0, true];
        }
    }
}

if (!function_exists('fx_delete_log_error')) {

    function fx_delete_log_error(string $op, string $table, \Throwable $e): void
    {
        $driverCode = ($e instanceof \PDOException && is_array($e->errorInfo ?? null)) ? (string)($e->errorInfo[1] ?? '') : '';
        error_log('[panel ' . $op . '] table=' . preg_replace('/[^A-Za-z0-9_]/', '', $table) . ' error=' . get_class($e) . ' code=' . (string)$e->getCode() . ($driverCode !== '' ? ' driver=' . $driverCode : ''));
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
            fx_delete_log_error('filter_collect', $table, $e);
            return [];
        }
    }
}

if (!function_exists('fx_filter_delete_redirect')) {

    function fx_filter_delete_redirect(string $baseUrl, array $filterParams, int $matched, int $deleted, string $scope = '', bool $failed = false): void
    {
        $param = 'fdel' . $scope;
        $state = $failed ? 'fail' : ($matched <= 0 ? 'none' : ($deleted > 0 ? 'ok' : 'err'));
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
        $box = 'padding:12px 16px; border-radius:var(--radius-md); margin-bottom:18px;';

        if ($state === 'ok' && $n >= $t) {
            $msg = $prefix . number_format($n) . ' نتیجه فیلترشده با موفقیت حذف شد.';
            return '<div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); ' . $box . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($state === 'ok') {
            $msg = $prefix . 'از ' . number_format($t) . ' نتیجه فیلترشده، ' . number_format($n) . ' مورد حذف شد و ' . number_format($t - $n) . ' مورد قابل حذف نبود.';
            return '<div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); ' . $box . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($state === 'fail') {
            $msg = $prefix . 'حذف نتایج فیلترشده به‌دلیل خطای پایگاه داده انجام نشد و هیچ رکوردی حذف نشد. جزئیات خطا در لاگ سرور ثبت شد.';
            return '<div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); ' . $box . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
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
