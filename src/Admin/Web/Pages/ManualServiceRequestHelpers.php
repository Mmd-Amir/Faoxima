<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ManualServiceRequestHelpers
{
    public static function faoxima_read_uploaded_file(string $tmpPath): string
{
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return '';
    }

    // Method 1: direct file_get_contents
    $data = @file_get_contents($tmpPath);
    if ($data !== false && strlen($data) > 0) {
        return $data;
    }

    // Method 2: fopen binary stream fread
    $fp = @fopen($tmpPath, 'rb');
    if ($fp !== false) {
        $size = @filesize($tmpPath);
        if ($size > 0) {
            $data = @fread($fp, $size);
        } else {
            $data = @stream_get_contents($fp);
        }
        @fclose($fp);
        if (is_string($data) && strlen($data) > 0) {
            return $data;
        }
    }

    // Method 3: local copy & read (bypasses open_basedir / temp dir stream restrictions)
    $localTmp = (dirname((dirname(__DIR__, 4) . '/src/Admin/Web/Pages'), 4) . '/panel') . '/../logs/tmp_upload_' . md5($tmpPath . microtime(true)) . '.tmp';
    $dir = dirname($localTmp);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (@copy($tmpPath, $localTmp) || @move_uploaded_file($tmpPath, $localTmp)) {
        $data = @file_get_contents($localTmp);
        @unlink($localTmp);
        if (is_string($data)) {
            return $data;
        }
    }

    return is_string($data) ? $data : '';
}

    public static function faoxima_ms_normalize_ext(string $ext): string
{
    return strtolower(ltrim(trim($ext), '.'));
}

    public static function fx_ms_prepare_items(array $post, array $files, string $configSource, string $entryMode, string $payload, string $customExt, bool $sendAsFile, array &$flash): array
{
    $items      = [];
    $pasteExt   = ($customExt !== '' && preg_match('/^[a-z0-9]{1,10}$/i', $customExt)) ? $customExt : '';
    $nonLinkExt = ($pasteExt !== '') ? $pasteExt : ($sendAsFile ? 'conf' : 'text');

    if ($configSource === 'file') {
        if ($entryMode === 'single') {
            $singleFile = $files['single_file'] ?? $files['config_file'] ?? null;
            if ($singleFile) {
                $errCode = (int)($singleFile['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($errCode === UPLOAD_ERR_OK) {
                    $tmp   = (string)($singleFile['tmp_name'] ?? '');
                    $fname = (string)($singleFile['name'] ?? '');
                    $isUploaded = ($tmp !== '' && is_uploaded_file($tmp));
                    if ($isUploaded) {
                        $ext = self::faoxima_ms_normalize_ext((string)pathinfo($fname, PATHINFO_EXTENSION));
                        if ($ext === '') $ext = 'bin';
                        $raw = self::faoxima_read_uploaded_file($tmp);
                        $hasSub = (($post['file_has_sub_link'] ?? $post['has_sub_link'] ?? '') === '1');
                        $sub    = trim((string)($post['file_single_sub_link'] ?? $post['single_sub_link'] ?? ''));
                        $content = $raw;
                        if ($content !== '') {
                            $items[] = ['content' => $content, 'ext' => $ext, 'sub' => ($hasSub && $sub !== '') ? $sub : ''];
                        }
                    }
                }
            }
            if (empty($items) && $flash['err'] === '') {
                $flash['err'] = 'لطفاً یک فایل کانفیگ انتخاب و آپلود کنید.';
            }
        } else {
            $bulkFiles = $files['bulk_files'] ?? null;
            $bulkSubs  = $post['bulk_file_subs'] ?? [];
            $bulkHasSubs = $post['bulk_file_has_subs'] ?? [];

            if ($bulkFiles && isset($bulkFiles['name']) && is_array($bulkFiles['name'])) {
                foreach ($bulkFiles['name'] as $i => $fname) {
                    $errCode = (int)($bulkFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                    if ($errCode !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $tmp = (string)($bulkFiles['tmp_name'][$i] ?? '');
                    if ($tmp === '' || !is_uploaded_file($tmp)) {
                        continue;
                    }

                    $ext = self::faoxima_ms_normalize_ext((string)pathinfo((string)$fname, PATHINFO_EXTENSION));
                    if ($ext === '') $ext = 'bin';
                    $raw = self::faoxima_read_uploaded_file($tmp);
                    $hasSub = (isset($bulkHasSubs[$i]) && (string)$bulkHasSubs[$i] === '1');
                    $sub    = trim((string)($bulkSubs[$i] ?? ''));
                    $content = $raw;
                    if ($content === '') {
                        continue;
                    }
                    $items[] = ['content' => $content, 'ext' => $ext, 'sub' => ($hasSub && $sub !== '') ? $sub : ''];
                }
            }
            if (empty($items) && $flash['err'] === '') {
                $flash['err'] = 'لطفاً حداقل یک فایل کانفیگ معتبر انتخاب و آپلود کنید.';
            }
        }
    } elseif (trim($payload) !== '') {
        $items = \Faoxima\Admin\Web\Support\ItemParser::faoxima_pair_manual_service_items($payload, $nonLinkExt, $pasteExt);
        if (empty($items)) {
            $flash['err'] = 'هیچ کانفیگ معتبری یافت نشد.';
        } elseif ($entryMode === 'single' && count($items) > 1) {
            $flash['err'] = 'در حالت «تکی» فقط یک کانفیگ (یا یک کانفیگ همراه با لینکِ اشتراکش) مجاز است.';
        }
    } else {
        $flash['err'] = 'فایل کانفیگ یا متن آن را وارد کنید.';
    }
    return $items;
}

    public static function fx_ms_store_items(PDO $pdo, array $items, string $entryMode, string $codePanel, string $codeProduct, string $nameRecord): array
{
    try {
        @$pdo->exec("ALTER TABLE manualsell MODIFY contentrecord LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    } catch (\Throwable $e) {
    }
    try {
        @$pdo->exec("ALTER TABLE manualsell ADD COLUMN sub_link MEDIUMTEXT NULL");
    } catch (\Throwable $e) {
    }
    try {
        @$pdo->exec("ALTER TABLE manualsell ADD COLUMN group_id VARCHAR(40) NULL");
    } catch (\Throwable $e) {
    }
    try {
        @$pdo->exec("ALTER TABLE manualsell ADD COLUMN group_size INT NULL");
    } catch (\Throwable $e) {
    }

    $isMultiItem = ($entryMode === 'multi');
    $multi = count($items) > 1 || $entryMode === 'bulk' || $isMultiItem;
    $groupId = null;
    $groupSize = null;
    if ($isMultiItem) {
        $groupId = method_exists(\Faoxima\Admin\Web\Support\ItemParser::class, 'faoxima_generate_group_id') ? \Faoxima\Admin\Web\Support\ItemParser::faoxima_generate_group_id() : ('mg_' . bin2hex(random_bytes(8)));
        $groupSize = count($items);
    }
    $ok = 0;
    $bad = 0;
    $insertStmt = $pdo->prepare("INSERT INTO manualsell (codepanel,codeproduct,namerecord,contentrecord,status,file_ext,sub_link,group_id,group_size) VALUES (:codepanel,:codeproduct,:namerecord,:contentrecord,'active',:file_ext,:sub_link,:group_id,:group_size)");
    foreach ($items as $idx => $item) {
        $content  = (string)($item['content'] ?? '');
        $thisName = $multi ? ($nameRecord . '-' . ($idx + 1)) : $nameRecord;
        $itemSub  = (string)($item['sub'] ?? '');

        try {
            $insertStmt->execute([
                ':codepanel'     => $codePanel,
                ':codeproduct'   => $codeProduct,
                ':namerecord'    => $thisName,
                ':contentrecord' => $content,
                ':file_ext'      => ($item['ext'] !== '' ? $item['ext'] : null),
                ':sub_link'      => ($itemSub !== '' ? $itemSub : null),
                ':group_id'      => $groupId,
                ':group_size'    => $groupSize,
            ]);
            if ($insertStmt->rowCount() > 0) {
                $ok++;
            } else {
                $bad++;
            }
        } catch (\Throwable $e) {
            $bad++;
        }
    }
    return [$ok, $bad, $isMultiItem];
}

    public static function fx_ms_delete_action(PDO $pdo, $action, array $post, array $flash): array
{
    $msBulkClear = null;
    if ($action === 'ms_delete_config_hard') {
        $id = (int)($post['config_id'] ?? 0);
        if ($id > 0) {
            try {
                $configDeleted = \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'manualsell', 'id = ?', [$id]) > 0;
                $flash['ok'] = $configDeleted ? 'کانفیگ برای همیشه از دیتابیس حذف شد.' : 'کانفیگ پیدا نشد.';
                $msBulkClear = $configDeleted;
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.';
            }
        }
    } elseif ($action === 'ms_delete_group_hard') {
        $groupId = trim((string)($post['group_id'] ?? ''));
        if ($groupId !== '') {
            try {
                $n = \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'manualsell', 'group_id = ?', [$groupId]);
                $flash['ok'] = $n > 0 ? ($n . ' کانفیگ این بستهٔ چندموردی برای همیشه حذف شد.') : 'بستهٔ چندموردی پیدا نشد.';
                $msBulkClear = $n > 0;
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.';
            }
        }
    } elseif ($action === 'ms_purge_deleted') {
        try {
            $del = $pdo->prepare("DELETE FROM manualsell WHERE status = 'delete'");
            $del->execute();
            $n = $del->rowCount();
            $flash['ok'] = $n > 0
                ? $n . ' رکورد حذف‌شده برای همیشه از دیتابیس پاک شد.'
                : 'رکورد حذف‌شده‌ای برای پاکسازی وجود نداشت.';
            $msBulkClear = $n > 0;
        } catch (\Throwable $e) {
            $flash['err'] = 'پاکسازی ناموفق: ' . $e->getMessage();
        }
    } elseif ($action === 'ms_bulk_delete') {
        $ids = $post['ids'] ?? [];
        $deleted = 0;
        if (is_array($ids) && count($ids) > 0) {
            $ids = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_positive_ids($ids);
            if (count($ids) > 0) {
                $ids = array_slice($ids, 0, 500);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                try {
                    $deleted = \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'manualsell', "id IN ($placeholders)", $ids);
                } catch (\Throwable $e) {
                    $flash['err'] = 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.';
                }
            }
        }
        if ($flash['err'] === '') {
            $flash['ok'] = $deleted > 0
                ? $deleted . ' کانفیگ برای همیشه حذف شد.'
                : 'موردی برای حذف انتخاب نشده بود.';
        }
        $msBulkClear = $deleted > 0;
    }
    return [$flash, $msBulkClear];
}

    public static function faoxima_ms_qs(array $over = []): string
{
    global $statusFilter, $panelFilter, $daysFilter, $daysMode, $msQ;
    $qp = ['cstatus' => $statusFilter];
    if ($panelFilter !== '') $qp['panel'] = $panelFilter;
    if ($msQ !== '') $qp['q'] = $msQ;
    if ($daysFilter !== null) {
        $qp['days'] = $daysFilter;
        $qp['dmode'] = $daysMode;
    }
    foreach ($over as $k => $v) {
        if ($v === null || $v === '') unset($qp[$k]);
        else $qp[$k] = $v;
    }
    return http_build_query($qp);
}

    public static function faoxima_ms_resolve_remaining(PDO $pdo, array $rows, bool $invoiceMissing): array
{
    $out = [];
    if ($invoiceMissing) {
        foreach ($rows as $r) $out[(int)$r['id']] = null;
        return $out;
    }
    $names = [];
    foreach ($rows as $r) {
        $u = trim((string)($r['username'] ?? ''));
        if ($u !== '' && (string)($r['status'] ?? '') === 'selled') $names[] = $u;
    }
    $inv = $names ? \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_fetch_by_username($pdo, $names) : [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $u  = trim((string)($r['username'] ?? ''));
        if ($u === '' || (string)($r['status'] ?? '') !== 'selled' || !isset($inv[$u])) {
            $out[$id] = null;
            continue;
        }
        $invRow = $inv[$u];
        $prodName = $invRow['name_product'] ?? '';
        $uname    = $invRow['username'] ?? $u;
        $out[$id] = \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_days($invRow['time_sell'], $invRow['Service_time'], $uname, $prodName);
    }
    return $out;
}

    public static function faoxima_ms_status_label(string $s): array
{
    switch ($s) {
        case 'active':
            return [\Faoxima\Admin\Web\Support\Icons::icon('circle-check', 'svg-icon svg-sm') . ' موجود', 'badge-active'];
        case 'selled':
            return [\Faoxima\Admin\Web\Support\Icons::icon('cart-check', 'svg-icon svg-sm') . ' فروخته شده', 'badge-info'];
            // panels.php stamps status='delete' when a manual-sale service is removed or
            // renewed onto another config. The row is a spent leftover, not a live item.
        case 'delete':
            return [\Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm') . ' حذف‌شده', 'badge-block'];
        case 'disabled':
            return [\Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm') . ' غیرفعال', 'badge-block'];
        default:
            return [htmlspecialchars($s, ENT_QUOTES), 'badge-gray'];
    }
}

    public static function faoxima_get_ms_setting(\PDO $pdo, string $key, string $default = ''): string
    {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM manual_sales_settings WHERE setting_key = :k LIMIT 1");
            $stmt->execute([':k' => $key]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? (string)$val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function faoxima_set_ms_setting(\PDO $pdo, string $key, string $value): bool
    {
        try {
            $stmt = $pdo->prepare("INSERT INTO manual_sales_settings (setting_key, setting_value) VALUES (:k, :v)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            return $stmt->execute([':k' => $key, ':v' => $value]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function faoxima_get_total_storage_used_bytes(\PDO $pdo): int
    {
        try {
            $stmt = $pdo->query("SELECT COALESCE(SUM(OCTET_LENGTH(contentrecord)), 0) FROM manualsell");
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
