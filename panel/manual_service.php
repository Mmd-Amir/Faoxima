<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_httponly', '1');
    @session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/remaining.php';
require_once __DIR__ . '/lib/item_parser.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

$tableMissing = true;
try {
    $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manualsell' LIMIT 1");
    $r->execute();
    $tableMissing = !(bool)$r->fetchColumn();
} catch (\Throwable $e) {
}

$invoiceTableMissing = true;
try {
    $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice' LIMIT 1");
    $r->execute();
    $invoiceTableMissing = !(bool)$r->fetchColumn();
} catch (\Throwable $e) {
}

$flash = ['ok' => '', 'err' => ''];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS manual_sales_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("ALTER TABLE manualsell MODIFY contentrecord LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    try {
        @$pdo->exec("ALTER TABLE manualsell ADD COLUMN sub_link MEDIUMTEXT NULL");
    } catch (\Throwable $e) {
    }
} catch (\Throwable $e) {
}

if (!function_exists('faoxima_get_ms_setting')) {
    function faoxima_get_ms_setting(\PDO $pdo, string $key, string $default = ''): string
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
}

if (!function_exists('faoxima_set_ms_setting')) {
    function faoxima_set_ms_setting(\PDO $pdo, string $key, string $value): bool
    {
        try {
            $stmt = $pdo->prepare("INSERT INTO manual_sales_settings (setting_key, setting_value) VALUES (:k, :v)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            return $stmt->execute([':k' => $key, ':v' => $value]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('faoxima_get_total_storage_used_bytes')) {
    function faoxima_get_total_storage_used_bytes(\PDO $pdo): int
    {
        try {
            $stmt = $pdo->query("SELECT COALESCE(SUM(OCTET_LENGTH(contentrecord)), 0) FROM manualsell");
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

$maxStorageGb = (float)faoxima_get_ms_setting($pdo, 'max_storage_gb', '5');
if ($maxStorageGb <= 0) $maxStorageGb = 5.0;

$totalStorageUsedBytes = faoxima_get_total_storage_used_bytes($pdo);
$maxStorageBytes = (int)round($maxStorageGb * 1073741824);

try {
    $packetBytes = max(67108864, min(1073741824, $maxStorageBytes));
    @$pdo->exec("SET SESSION max_allowed_packet = {$packetBytes}");
    @$pdo->exec("SET GLOBAL max_allowed_packet = {$packetBytes}");
} catch (\Throwable $e) {
}

$panels = [];
try {
    $r = $pdo->prepare("SELECT code_panel, name_panel, type FROM marzban_panel WHERE type = 'Manualsale' ORDER BY name_panel ASC");
    $r->execute();
    $panels = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
}

$products = [];
try {
    $r = $pdo->query("SELECT * FROM product ORDER BY id ASC");
    if ($r) $products = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
}

$panelNameOf = [];
foreach ($panels as $p) $panelNameOf[(string)$p['code_panel']] = (string)$p['name_panel'];

$productByCode = [];
$productNameOf = [];
foreach ($products as $p) {
    $code = (string)($p['code_product'] ?? '');
    $name = (string)($p['name_product'] ?? $p['Name_product'] ?? '');
    if ($code !== '') {
        $productByCode[$code] = $p;
        $productNameOf[$code] = $name;
    }
}

$allowedExt = ['conf', 'ovpn', 'pcf', 'npv'];

function faoxima_read_uploaded_file(string $tmpPath): string
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
    $localTmp = __DIR__ . '/../logs/tmp_upload_' . md5($tmpPath . microtime(true)) . '.tmp';
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

function faoxima_ms_normalize_ext(string $ext): string
{
    return strtolower(ltrim(trim($ext), '.'));
}


function faoxima_ms_delete_linked_invoices(PDO $pdo, array $where): void
{
    $sql = "SELECT username FROM manualsell WHERE status = 'selled' AND username IS NOT NULL AND username <> ''";
    $params = [];
    if (isset($where['id'])) {
        $sql .= " AND id = :id";
        $params[':id'] = (int)$where['id'];
    }
    if (isset($where['ids']) && is_array($where['ids']) && $where['ids']) {
        $ph = implode(',', array_fill(0, count($where['ids']), '?'));
        $sql .= " AND id IN ({$ph})";
    }
    try {
        $stmt = $pdo->prepare($sql);
        if (isset($where['ids']) && is_array($where['ids']) && $where['ids']) {
            $i = 1;
            foreach ($params as $v) {
                $stmt->bindValue($i, $v);
                $i++;
            }
            foreach ($where['ids'] as $v) {
                $stmt->bindValue($i, (int)$v, PDO::PARAM_INT);
                $i++;
            }
            $stmt->execute();
        } else {
            $stmt->execute($params);
        }
        $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (\Throwable $e) {
        return;
    }
    $usernames = array_values(array_unique(array_filter(array_map('trim', $usernames), static function ($v) {
        return $v !== '';
    })));
    if (!$usernames) return;
    $ph = implode(',', array_fill(0, count($usernames), '?'));
    try {
        $del = $pdo->prepare("DELETE FROM invoice WHERE username IN ({$ph})");
        $del->execute($usernames);
    } catch (\Throwable $e) {
    }
}

if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $flash['err'] = 'حجم فایل آپلود شده بیشتر از حد مجاز سرور (post_max_size / upload_max_filesize) است.';
    }

    $action = $_POST['_action'] ?? '';

    if ($action === 'update_storage_limit') {
        $newGb = (float)($_POST['max_storage_gb'] ?? 5);
        if ($newGb <= 0) $newGb = 1.0;
        if ($newGb > 1000) $newGb = 1000.0;

        if (faoxima_set_ms_setting($pdo, 'max_storage_gb', (string)$newGb)) {
            $flash['ok'] = "حداکثر حجم ذخیره‌سازی با موفقیت به {$newGb} گیگابایت تغییر یافت و پیکربندی دیتابیس بروزرسانی شد.";
            $maxStorageGb = $newGb;
            $maxStorageBytes = (int)round($maxStorageGb * 1073741824);
            try {
                $packetBytes = max(67108864, min(1073741824, $maxStorageBytes));
                @$pdo->exec("SET SESSION max_allowed_packet = {$packetBytes}");
                @$pdo->exec("SET GLOBAL max_allowed_packet = {$packetBytes}");
            } catch (\Throwable $e) {
            }
        } else {
            $flash['err'] = 'خطا در ذخیره‌سازی تنظیمات حداکثر حجم دیتابیس.';
        }
    }

    if ($action === 'ms_add_config') {
        $codePanel    = trim((string)($_POST['codepanel']   ?? ''));
        $codeProduct  = trim((string)($_POST['codeproduct'] ?? ''));
        $nameRecord   = trim((string)($_POST['namerecord']  ?? ''));
        $configSource = (string)($_POST['config_source']   ?? 'text');
        $pasteText    = (string)($_POST['contentrecord']    ?? '');
        $itemsJson    = (string)($_POST['items_json']        ?? '');
        $customExt    = faoxima_ms_normalize_ext((string)($_POST['custom_ext'] ?? ''));
        $sendAsFile   = (($_POST['send_as_file'] ?? '') === '1');
        $entryMode    = (string)($_POST['entry_mode'] ?? 'single');
        $entryMode    = in_array($entryMode, ['bulk', 'multi'], true) ? $entryMode : 'single';
        $payload      = ($itemsJson !== '') ? $itemsJson : $pasteText;

        $items      = [];
        $pasteExt   = ($customExt !== '' && preg_match('/^[a-z0-9]{1,10}$/i', $customExt)) ? $customExt : '';
        $nonLinkExt = ($pasteExt !== '') ? $pasteExt : ($sendAsFile ? 'conf' : 'text');

        if ($configSource === 'file') {
            if ($entryMode === 'single') {
                $singleFile = $_FILES['single_file'] ?? $_FILES['config_file'] ?? null;
                if ($singleFile) {
                    $errCode = (int)($singleFile['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($errCode === UPLOAD_ERR_OK) {
                        $tmp   = (string)($singleFile['tmp_name'] ?? '');
                        $fname = (string)($singleFile['name'] ?? '');
                        $isUploaded = ($tmp !== '' && is_uploaded_file($tmp));
                        if ($isUploaded) {
                            $ext = faoxima_ms_normalize_ext((string)pathinfo($fname, PATHINFO_EXTENSION));
                            if ($ext === '') $ext = 'bin';
                            $raw = faoxima_read_uploaded_file($tmp);
                            $hasSub = (($_POST['file_has_sub_link'] ?? $_POST['has_sub_link'] ?? '') === '1');
                            $sub    = trim((string)($_POST['file_single_sub_link'] ?? $_POST['single_sub_link'] ?? ''));
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
                $bulkFiles = $_FILES['bulk_files'] ?? null;
                $bulkSubs  = $_POST['bulk_file_subs'] ?? [];
                $bulkHasSubs = $_POST['bulk_file_has_subs'] ?? [];

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

                        $ext = faoxima_ms_normalize_ext((string)pathinfo((string)$fname, PATHINFO_EXTENSION));
                        if ($ext === '') $ext = 'bin';
                        $raw = faoxima_read_uploaded_file($tmp);
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
            $items = faoxima_pair_manual_service_items($payload, $nonLinkExt, $pasteExt);
            if (empty($items)) {
                $flash['err'] = 'هیچ کانفیگ معتبری یافت نشد.';
            } elseif ($entryMode === 'single' && count($items) > 1) {
                $flash['err'] = 'در حالت «تکی» فقط یک کانفیگ (یا یک کانفیگ همراه با لینکِ اشتراکش) مجاز است.';
            }
        } else {
            $flash['err'] = 'فایل کانفیگ یا متن آن را وارد کنید.';
        }

        if ($flash['err'] === '' && $codePanel === '') {
            $flash['err'] = 'پنل فروش دستی را انتخاب کنید.';
        }
        if ($flash['err'] === '' && !isset($panelNameOf[$codePanel])) {
            $flash['err'] = 'پنل انتخابی معتبر نیست.';
        }
        if ($flash['err'] === '' && $codeProduct === '') {
            $flash['err'] = 'محصول را انتخاب کنید.';
        }
        if ($flash['err'] === '' && $codeProduct !== 'usertest' && !isset($productByCode[$codeProduct])) {
            $flash['err'] = 'محصول انتخابی معتبر نیست.';
        }
        if ($flash['err'] === '' && $nameRecord === '') {
            $flash['err'] = 'یک نام برای کانفیگ وارد کنید.';
        }
        if ($flash['err'] === '') {
            $items = array_values(array_filter($items, static function ($it) {
                return (string)($it['content'] ?? '') !== '';
            }));
            if (empty($items)) {
                $flash['err'] = 'محتوای فایل کانفیگ خالی است. لطفاً یک فایل معتبر (غیر خالی) آپلود کنید.';
            }
        }
        if ($flash['err'] === '') {
            $incomingBytes = 0;
            foreach ($items as $itm) {
                $incomingBytes += strlen((string)($itm['content'] ?? ''));
            }
            if (($totalStorageUsedBytes + $incomingBytes) > $maxStorageBytes) {
                $usedGbStr = round($totalStorageUsedBytes / 1073741824, 2);
                $flash['err'] = "حجم فایل‌های انتخابی از سقف مجاز کل ذخیره‌سازی دیتابیس ({$maxStorageGb} گیگابایت) فراتر می‌رود. (میزان استفاده فعلی: {$usedGbStr} GB)";
            }
        }
        if ($flash['err'] === '') {
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
                $groupId = function_exists('faoxima_generate_group_id') ? faoxima_generate_group_id() : ('mg_' . bin2hex(random_bytes(8)));
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

            if ($ok > 0) {
                $flash['ok'] = ($isMultiItem
                    ? ($ok . ' مورد به‌صورت یک بستهٔ چندموردی ثبت شد.')
                    : ($ok . ' کانفیگ با موفقیت ثبت شد.'))
                    . ($bad > 0 ? ' (' . $bad . ' مورد نامعتبر)' : '');
            } else {
                $flash['err'] = 'هیچ کانفیگ معتبری برای ثبت یافت نشد.';
            }
        }
    } elseif ($action === 'ms_delete_config_hard') {
        $id = (int)($_POST['config_id'] ?? 0);
        if ($id > 0) {
            try {
                faoxima_ms_delete_linked_invoices($pdo, ['id' => $id]);
                $del = $pdo->prepare("DELETE FROM manualsell WHERE id = :id");
                $del->execute([':id' => $id]);
                $configDeleted = $del->rowCount() > 0;
                $flash['ok'] = $configDeleted ? 'کانفیگ برای همیشه از دیتابیس حذف شد.' : 'کانفیگ پیدا نشد.';
                $msBulkClear = $configDeleted;
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'ms_delete_group_hard') {
        $groupId = trim((string)($_POST['group_id'] ?? ''));
        if ($groupId !== '') {
            try {
                $sel = $pdo->prepare("SELECT id FROM manualsell WHERE group_id = :gid");
                $sel->execute([':gid' => $groupId]);
                $groupIds = array_values(array_filter(array_map('intval', $sel->fetchAll(PDO::FETCH_COLUMN)), function ($v) {
                    return $v > 0;
                }));
                if (count($groupIds) > 0) {
                    faoxima_ms_delete_linked_invoices($pdo, ['ids' => $groupIds]);
                    $del = $pdo->prepare("DELETE FROM manualsell WHERE group_id = :gid");
                    $del->execute([':gid' => $groupId]);
                    $n = $del->rowCount();
                    $flash['ok'] = $n > 0 ? ($n . ' کانفیگ این بستهٔ چندموردی برای همیشه حذف شد.') : 'بستهٔ چندموردی پیدا نشد.';
                    $msBulkClear = $n > 0;
                } else {
                    $flash['ok'] = 'بستهٔ چندموردی پیدا نشد.';
                }
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
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
        $ids = $_POST['ids'] ?? [];
        $deleted = 0;
        if (is_array($ids) && count($ids) > 0) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
                return $v > 0;
            })));
            if (count($ids) > 0) {
                $ids = array_slice($ids, 0, 500);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                try {
                    faoxima_ms_delete_linked_invoices($pdo, ['ids' => $ids]);
                    $del = $pdo->prepare("DELETE FROM manualsell WHERE id IN ($placeholders)");
                    $del->execute($ids);
                    $deleted = $del->rowCount();
                } catch (\Throwable $e) {
                    $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
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
}

$statusFilter = isset($_GET['cstatus']) ? (string)$_GET['cstatus'] : 'all';
$allowedStatuses = ['active', 'selled', 'disabled', 'delete'];
if ($statusFilter !== 'all' && !in_array($statusFilter, $allowedStatuses, true)) $statusFilter = 'all';

$panelFilter = isset($_GET['panel']) ? (string)$_GET['panel'] : '';
if ($panelFilter !== '' && !isset($panelNameOf[$panelFilter])) $panelFilter = '';

$daysRaw = isset($_GET['days']) ? trim((string)$_GET['days']) : '';
$daysFilter = ($daysRaw !== '' && ctype_digit($daysRaw)) ? (int)$daysRaw : null;
$daysMode = faoxima_remaining_dmode($_GET['dmode'] ?? '');
$daysActive = ($daysFilter !== null && !$invoiceTableMissing);

$msQ = trim((string)($_GET['q'] ?? ''));

$perPage = 500;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

function faoxima_ms_qs(array $over = []): string
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

$whereSql = '1=1';
$params = [];
if ($statusFilter !== 'all') {
    $whereSql .= " AND status = :st";
    $params[':st'] = $statusFilter;
}
if ($panelFilter !== '') {
    $whereSql .= " AND codepanel = :cp";
    $params[':cp'] = $panelFilter;
}
if ($msQ !== '') {
    $whereSql .= " AND (namerecord LIKE :q1 OR username LIKE :q2 OR codeproduct LIKE :q3)";
    $msLike = '%' . $msQ . '%';
    $params[':q1'] = $msLike;
    $params[':q2'] = $msLike;
    $params[':q3'] = $msLike;
}

$total = 0;
$configs = [];
$remainingOf = [];
$scanTruncated = false;
$counts = ['total' => 0, 'active' => 0, 'selled' => 0, 'disabled' => 0, 'delete' => 0];

function faoxima_ms_resolve_remaining(PDO $pdo, array $rows, bool $invoiceMissing): array
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
    $inv = $names ? faoxima_remaining_fetch_by_username($pdo, $names) : [];
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
        $out[$id] = faoxima_remaining_days($invRow['time_sell'], $invRow['Service_time'], $uname, $prodName);
    }
    return $out;
}

if (!$tableMissing) {
    try {
        $c = $pdo->query("SELECT status, COUNT(*) AS cnt FROM manualsell GROUP BY status");
        if ($c) {
            foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $st = (string)$row['status'];
                if (isset($counts[$st])) $counts[$st] = (int)$row['cnt'];
                $counts['total'] += (int)$row['cnt'];
            }
        }
    } catch (\Throwable $e) {
    }

    if (!$daysActive) {
        try {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM manualsell WHERE {$whereSql}");
            $cnt->execute($params);
            $total = (int)$cnt->fetchColumn();
        } catch (\Throwable $e) {
        }
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $perPage;
        try {
            $r = $pdo->prepare("SELECT * FROM manualsell WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
            $r->execute($params);
            $configs = $r->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }
        $remainingOf = faoxima_ms_resolve_remaining($pdo, $configs, $invoiceTableMissing);
    } else {
        $scan = [];
        $cap = FAOXIMA_REMAINING_SCAN_CAP;
        try {
            $r = $pdo->prepare("SELECT * FROM manualsell WHERE {$whereSql} ORDER BY id DESC LIMIT " . ($cap + 1));
            $r->execute($params);
            $scan = $r->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }
        if (count($scan) > $cap) {
            $scanTruncated = true;
            $scan = array_slice($scan, 0, $cap);
        }
        $scanRemaining = faoxima_ms_resolve_remaining($pdo, $scan, $invoiceTableMissing);

        $matched = [];
        foreach ($scan as $row) {
            $rem = $scanRemaining[(int)$row['id']] ?? null;
            if (faoxima_remaining_matches($rem, $daysMode, $daysFilter)) $matched[] = $row;
        }
        $total = count($matched);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;
        $configs = array_slice($matched, ($page - 1) * $perPage, $perPage);
        foreach ($configs as $row) {
            $remainingOf[(int)$row['id']] = $scanRemaining[(int)$row['id']] ?? null;
        }
    }
} else {
    $pages = 1;
}

function faoxima_ms_status_label(string $s): array
{
    switch ($s) {
        case 'active':
            return [icon('circle-check', 'svg-icon svg-sm') . ' موجود', 'badge-active'];
        case 'selled':
            return [icon('cart-check', 'svg-icon svg-sm') . ' فروخته شده', 'badge-info'];
            // panels.php stamps status='delete' when a manual-sale service is removed or
            // renewed onto another config. The row is a spent leftover, not a live item.
        case 'delete':
            return [icon('trash', 'svg-icon svg-sm') . ' حذف‌شده', 'badge-block'];
        case 'disabled':
            return [icon('xmark', 'svg-icon svg-sm') . ' غیرفعال', 'badge-block'];
        default:
            return [htmlspecialchars($s, ENT_QUOTES), 'badge-gray'];
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>فروش دستی | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat33">
    <link rel="stylesheet" href="css/components.css?v=flat33">
    <script src="js/theme.js?v=flat50" defer></script>
    <style>
        .ms-row__title .badge {
            font-size: 10.5px;
        }

        .ms-row--group {
            border-color: var(--accent);
        }

        .ms-group-items {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            margin-top: var(--space-3);
            padding-top: var(--space-3);
            border-top: 1px dashed var(--border-color);
        }

        .ms-group-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 8px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: 12px;
        }

        .ms-group-item__name {
            font-weight: 600;
        }

        .ms-cfg-panel {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }

        .ms-head-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .ms-head-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .page-head {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .page-head>div:last-child:not(:only-child) {
                width: 100% !important;
            }

            .ms-head-actions {
                width: 100% !important;
                flex-direction: column;
                gap: 8px;
            }

            .ms-head-actions .btn {
                width: 100% !important;
                box-sizing: border-box;
            }
        }

        .stat-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .stat-chip {
            flex: 1 1 auto;
            min-width: fit-content;
            max-width: 100%;
            box-sizing: border-box;
            white-space: nowrap;
        }

        @media (max-width: 575.98px) {
            .stat-row {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .stat-chip {
                width: 100%;
                justify-content: center;
                text-align: center;
                font-size: 0.8rem;
                padding: 6px 10px;
            }

            .stat-chip--storage {
                grid-column: span 2;
            }
        }
    </style>
</head>

<body>

    <section id="container">
        <?php include("header.php"); ?>

        <section id="main-content">
            <div class="wrapper">

                <div class="page-head">
                    <div>
                        <div class="page-head__title">
                            <?php echo icon('cart-check', 'svg-icon svg-lg'); ?>
                            فروش دستی
                        </div>
                        <div class="page-head__sub">مدیریت کانفیگ‌های فروش دستی — هر رکورد یک پنل و محصول دارد</div>
                    </div>
                    <?php if (!$tableMissing): ?>
                        <div class="ms-head-actions">
                            <button type="button" class="btn btn-outline" onclick="openModal('modal-ms-storage-settings')">
                                <?php echo icon('gear', 'svg-icon svg-sm'); ?>
                                <span>سقف ذخیره‌سازی: <?php echo $maxStorageGb; ?> GB</span>
                            </button>
                            <?php if (!empty($panels)): ?>
                                <button type="button" class="btn btn-primary" onclick="openModal('modal-ms-add')">
                                    <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                                    <span>افزودن کانفیگ</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($tableMissing): ?>
                    <div class="alert alert-warning">
                        <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                        <span>جدول <code>manualsell</code> هنوز ساخته نشده است. یکبار <code>table.php</code> را در مرورگر باز کنید تا جدول‌ها ساخته شوند.</span>
                    </div>
                <?php endif; ?>

                <?php if ($flash['ok']): ?><div class="alert alert-success"><?php echo icon('circle-check', 'svg-icon'); ?><span><?php echo htmlspecialchars($flash['ok'], ENT_QUOTES); ?></span></div><?php endif; ?>
                <?php if ($flash['err']): ?><div class="alert alert-danger"><?php echo icon('circle-exclamation', 'svg-icon'); ?><span><?php echo htmlspecialchars($flash['err'], ENT_QUOTES); ?></span></div><?php endif; ?>
                <?php if (!empty($msBulkClear)): ?><script>try { sessionStorage.removeItem('fx_bulk_sel:' + location.pathname + ':default'); } catch (e) {}</script><?php endif; ?>

                <?php if (!$tableMissing && empty($panels)): ?>
                    <div class="alert alert-warning">
                        <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                        <span>هیچ پنل «فروش دستی» ثبت نشده است. ابتدا از ربات مدیریت یک پنل از نوع فروش دستی بسازید.</span>
                    </div>
                <?php endif; ?>

                <?php if (!$tableMissing): ?>
                    <?php
                    $usedGbVal = round($totalStorageUsedBytes / 1073741824, 2);
                    $percentUsedVal = min(100, round(($totalStorageUsedBytes / max(1, $maxStorageBytes)) * 100, 1));
                    ?>
                    <div class="stat-row">
                        <div class="stat-chip">کل: <strong><?php echo $counts['total']; ?></strong></div>
                        <div class="stat-chip stat-chip--success"><?php echo icon('circle-check', 'svg-icon svg-sm'); ?> موجود: <strong><?php echo $counts['active']; ?></strong></div>
                        <div class="stat-chip stat-chip--accent"><?php echo icon('cart-check', 'svg-icon svg-sm'); ?> فروخته شده: <strong><?php echo $counts['selled']; ?></strong></div>
                        <div class="stat-chip stat-chip--storage" style="cursor: pointer;" onclick="openModal('modal-ms-storage-settings')" title="تنظیم سقف ذخیره‌سازی فایل‌ها">
                            <?php echo icon('hard-drive', 'svg-icon svg-sm'); ?> ذخیره‌سازی: <strong><?php echo $usedGbVal; ?> / <?php echo $maxStorageGb; ?> GB (<?php echo $percentUsedVal; ?>%)</strong>
                        </div>
                    </div>

                    <div class="cfg-filterbar">
                        <form method="GET" action="manual_service.php" class="cfg-filterbar__row">
                            <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES); ?>">
                            <?php if ($panelFilter !== ''): ?><input type="hidden" name="panel" value="<?php echo htmlspecialchars($panelFilter, ENT_QUOTES); ?>"><?php endif; ?>
                            <?php if ($daysFilter !== null): ?>
                                <input type="hidden" name="days" value="<?php echo (int)$daysFilter; ?>">
                                <input type="hidden" name="dmode" value="<?php echo htmlspecialchars($daysMode, ENT_QUOTES); ?>">
                            <?php endif; ?>
                            <input type="search" name="q" class="form-control" style="flex:1 1 240px; min-width:0;" placeholder="جستجو در نام کانفیگ، محصول یا کاربر…" value="<?php echo htmlspecialchars($msQ, ENT_QUOTES); ?>">
                            <button type="submit" class="btn btn-sm btn-primary"><?php echo icon('search', 'svg-icon svg-sm'); ?> جستجو</button>
                            <?php if ($msQ !== ''): ?><a href="manual_service.php?<?php echo htmlspecialchars(faoxima_ms_qs(['q' => null, 'p' => null]), ENT_QUOTES); ?>" class="btn btn-sm btn-outline">حذف فیلتر</a><?php endif; ?>
                        </form>
                        <div class="cfg-filterbar__row">
                            <span class="cfg-filterbar__label"><?php echo icon('filter', 'svg-icon svg-sm'); ?> وضعیت:</span>
                            <div class="cfg-seg" role="group" aria-label="فیلتر وضعیت">
                                <?php
                                $statusFilters = [
                                    'all'      => 'همه',
                                    'active'   => icon('circle-check', 'svg-icon svg-sm') . ' موجود',
                                    'selled'   => icon('cart-check', 'svg-icon svg-sm') . ' فروخته شده',
                                ];
                                foreach ($statusFilters as $fk => $fl):
                                    $isActive = ($statusFilter === $fk);
                                    $qp = faoxima_ms_qs(['cstatus' => $fk, 'p' => null]);
                                ?>
                                    <a href="manual_service.php?<?php echo htmlspecialchars($qp, ENT_QUOTES); ?>"
                                        class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-outline'; ?>"
                                        <?php echo $isActive ? 'aria-current="true"' : ''; ?>><?php echo $fl; ?></a>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!empty($panels)): ?>
                                <div class="cfg-actions">
                                    <span class="cfg-filterbar__label"><?php echo icon('server', 'svg-icon svg-sm'); ?> پنل:</span>
                                    <select class="form-control" style="min-width:190px;" aria-label="فیلتر بر اساس پنل" onchange="location.href='manual_service.php?<?php echo htmlspecialchars(faoxima_ms_qs(['panel' => null, 'p' => null]), ENT_QUOTES); ?>&panel='+encodeURIComponent(this.value);">
                                        <option value="">همه پنل‌ها</option>
                                        <?php foreach ($panels as $p): ?>
                                            <option value="<?php echo htmlspecialchars($p['code_panel'], ENT_QUOTES); ?>" <?php echo $panelFilter === (string)$p['code_panel'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['name_panel'], ENT_QUOTES); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php // Row 2 — remaining-days range filter 
                        ?>
                        <div class="cfg-filterbar__row">
                            <form method="GET" action="manual_service.php" class="cfg-daysfilter">
                                <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES); ?>">
                                <?php if ($panelFilter !== ''): ?><input type="hidden" name="panel" value="<?php echo htmlspecialchars($panelFilter, ENT_QUOTES); ?>"><?php endif; ?>
                                <span class="cfg-filterbar__label"><?php echo icon('hourglass', 'svg-icon svg-sm'); ?> روز مانده:</span>
                                <select name="dmode" class="form-control" aria-label="نوع مقایسه روز مانده">
                                    <option value="lte" <?php echo $daysMode === 'lte' ? 'selected' : ''; ?>>کمتر از</option>
                                    <option value="eq" <?php echo $daysMode === 'eq'  ? 'selected' : ''; ?>>برابر</option>
                                    <option value="gte" <?php echo $daysMode === 'gte' ? 'selected' : ''; ?>>بیشتر از</option>
                                </select>
                                <input type="number" name="days" min="0" step="1" class="form-control" placeholder="روز"
                                    value="<?php echo $daysFilter !== null ? (int)$daysFilter : ''; ?>" aria-label="تعداد روز مانده">
                                <button type="submit" class="btn btn-sm <?php echo $daysFilter !== null ? 'btn-primary' : 'btn-outline'; ?>">
                                    <?php echo icon('filter', 'svg-icon svg-sm'); ?> اعمال
                                </button>
                                <?php if ($daysFilter !== null): ?>
                                    <a class="btn btn-sm btn-outline" href="manual_service.php?<?php echo htmlspecialchars(faoxima_ms_qs(['days' => null, 'dmode' => null, 'p' => null]), ENT_QUOTES); ?>" title="حذف فیلتر روز">
                                        <?php echo icon('xmark', 'svg-icon svg-sm'); ?> پاک کردن
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if ($daysFilter !== null && $invoiceTableMissing): ?>
                        <div class="alert alert-warning">
                            <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                            <span>جدول <code>invoice</code> در دسترس نیست، بنابراین فیلتر «روز مانده» اعمال نشد و همه‌ی موارد نمایش داده می‌شوند.</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($scanTruncated): ?>
                        <div class="alert alert-warning">
                            <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                            <span>برای فیلتر «روز مانده» فقط <?php echo (int)FAOXIMA_REMAINING_SCAN_CAP; ?> مورد آخر بررسی شد. موارد قدیمی‌تر در این نتیجه نیستند — برای بررسی کامل ابتدا با فیلتر وضعیت یا پنل محدوده را کوچک‌تر کنید.</span>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($configs)): ?>
                        <div class="card empty-state">
                            <?php echo icon('cart-check', 'svg-icon svg-2xl'); ?>
                            <div class="empty-state__text">کانفیگی برای نمایش وجود ندارد.</div>
                        </div>
                    <?php else: ?>
                        <?php
                        ?>
                        <form method="POST" action="manual_service.php" id="ms-bulk-form" style="display:none;"
                            onsubmit="return faoximaMsBulkConfirm();">
                            <input type="hidden" name="_action" value="ms_bulk_delete">
                        </form>

                        <div class="bulk-bar" id="ms-bulk-bar">
                            <label class="bulk-bar__all">
                                <input type="checkbox" id="ms-bulk-all">
                                <span>انتخاب همه‌ی این صفحه</span>
                            </label>
                            <span class="bulk-bar__count" id="ms-bulk-count">موردی انتخاب نشده</span>
                            <div class="bulk-bar__actions">
                                <button type="submit" form="ms-bulk-form" class="btn btn-sm btn-outline btn-outline-danger bulk-bar__btn js-bulk-delete-btn" id="ms-bulk-btn" style="display:none;">
                                    <?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف انتخاب‌شده‌ها
                                </button>
                            </div>
                        </div>

                        <div class="ms-list">
                            <?php
                            $displayGroups = [];
                            $groupIndex = [];
                            foreach ($configs as $cfg) {
                                $gid = trim((string)($cfg['group_id'] ?? ''));
                                if ($gid === '') {
                                    $displayGroups[] = ['type' => 'single', 'rows' => [$cfg]];
                                    continue;
                                }
                                if (!isset($groupIndex[$gid])) {
                                    $groupIndex[$gid] = count($displayGroups);
                                    $displayGroups[] = ['type' => 'group', 'group_id' => $gid, 'rows' => []];
                                }
                                $displayGroups[$groupIndex[$gid]]['rows'][] = $cfg;
                            }
                            foreach ($displayGroups as $grp):
                                $rows = $grp['rows'];
                                $isGroup = ($grp['type'] === 'group' && count($rows) > 1);
                                $head = $rows[0];
                                $st  = (string)$head['status'];
                                [$stLabel, $stClass] = faoxima_ms_status_label($st);
                                $pName = $panelNameOf[(string)$head['codepanel']] ?? (string)$head['codepanel'];
                                $prName = $productNameOf[(string)$head['codeproduct']] ?? (string)$head['codeproduct'];
                                $rem = $remainingOf[(int)$head['id']] ?? null;
                                $prodRow  = $productByCode[(string)$head['codeproduct']] ?? null;
                                $prodDays = $prodRow ? (int)($prodRow['Service_time'] ?? $prodRow['time_product'] ?? 0) : 0;

                                if (!$isGroup):
                                    $cfg = $head;
                                    $cid = (int)$cfg['id'];
                                    $fext = trim((string)($cfg['file_ext'] ?? ''));
                            ?>
                                <div class="ms-row<?php echo ($st === 'disabled' || $st === 'delete') ? ' ms-row--disabled' : ''; ?>">
                                    <div class="ms-row__head">
                                        <div class="ms-row__title">
                                            <input type="checkbox" class="row-check" form="ms-bulk-form" name="ids[]"
                                                value="<?php echo $cid; ?>"
                                                <?php echo $st === 'active' ? 'data-live="1"' : ''; ?>
                                                aria-label="انتخاب <?php echo htmlspecialchars((string)$cfg['namerecord'], ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars((string)$cfg['namerecord'], ENT_QUOTES); ?>
                                            <span class="badge <?php echo $stClass; ?>"><?php echo $stLabel; ?></span>
                                            <?php if ($st === 'selled'): ?>
                                                <?php echo faoxima_remaining_badge($rem); ?>
                                            <?php endif; ?>
                                            <?php if ($fext !== ''): ?><span class="ms-ext">.<?php echo htmlspecialchars($fext, ENT_QUOTES); ?></span><?php endif; ?>
                                        </div>
                                        <div class="ms-row__actions">
                                            <form method="POST" action="manual_service.php" onsubmit="return confirm('این کانفیگ برای همیشه از دیتابیس حذف شود؟ این عمل بازگشت‌پذیر نیست.');">
                                                <input type="hidden" name="_action" value="ms_delete_config_hard">
                                                <input type="hidden" name="config_id" value="<?php echo $cid; ?>">
                                                <input type="hidden" name="allow_selled" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline btn-outline-danger"><?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="ms-row__meta">
                                        <span><?php echo icon('server', 'svg-icon svg-sm'); ?> پنل: <b><?php echo htmlspecialchars($pName, ENT_QUOTES); ?></b></span>
                                        <span><?php echo icon('package', 'svg-icon svg-sm'); ?> محصول: <b><?php echo htmlspecialchars($prName, ENT_QUOTES); ?></b></span>
                                        <?php if ($prodDays > 0 && ($st !== 'selled' || $rem === null)): ?>
                                            <span><?php echo icon('hourglass', 'svg-icon svg-sm'); ?> مدت محصول: <b><?php echo $prodDays; ?> روز</b></span>
                                        <?php endif; ?>
                                        <?php if (trim((string)($cfg['username'] ?? '')) !== ''): ?><span><?php echo icon('user', 'svg-icon svg-sm'); ?> <?php echo htmlspecialchars((string)$cfg['username'], ENT_QUOTES); ?></span><?php endif; ?>
                                    </div>
                                </div>
                            <?php else:
                                    $groupName = (string)$head['namerecord'];
                                    $dashPos = strrpos($groupName, '-');
                                    if ($dashPos !== false) {
                                        $groupName = substr($groupName, 0, $dashPos);
                                    }
                            ?>
                                <div class="ms-row ms-row--group<?php echo ($st === 'disabled' || $st === 'delete') ? ' ms-row--disabled' : ''; ?>">
                                    <div class="ms-row__head">
                                        <div class="ms-row__title">
                                            <input type="checkbox" class="row-check group-check" form="ms-bulk-form"
                                                data-group-ids="<?php echo htmlspecialchars(implode(',', array_map(function ($r) { return (int)$r['id']; }, $rows)), ENT_QUOTES); ?>"
                                                <?php echo $st === 'active' ? 'data-live="1"' : ''; ?>
                                                aria-label="انتخاب بستهٔ <?php echo htmlspecialchars($groupName, ENT_QUOTES); ?>">
                                            <?php foreach ($rows as $r): ?>
                                                <input type="checkbox" class="row-check group-member-check" form="ms-bulk-form" name="ids[]"
                                                    value="<?php echo (int)$r['id']; ?>"
                                                    <?php echo ((string)$r['status'] === 'active') ? 'data-live="1"' : ''; ?>
                                                    style="display:none;">
                                            <?php endforeach; ?>
                                            <?php echo htmlspecialchars($groupName, ENT_QUOTES); ?>
                                            <span class="badge badge-active"><?php echo icon('package', 'svg-icon svg-sm'); ?> چندموردی (<?php echo count($rows); ?>)</span>
                                            <span class="badge <?php echo $stClass; ?>"><?php echo $stLabel; ?></span>
                                            <?php if ($st === 'selled'): ?>
                                                <?php echo faoxima_remaining_badge($rem); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ms-row__actions">
                                            <form method="POST" action="manual_service.php" onsubmit="return confirm('کل این بستهٔ چندموردی (<?php echo count($rows); ?> مورد) برای همیشه از دیتابیس حذف شود؟ این عمل بازگشت‌پذیر نیست.');">
                                                <input type="hidden" name="_action" value="ms_delete_group_hard">
                                                <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($grp['group_id'], ENT_QUOTES); ?>">
                                                <input type="hidden" name="allow_selled" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline btn-outline-danger"><?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف بسته</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="ms-row__meta">
                                        <span><?php echo icon('server', 'svg-icon svg-sm'); ?> پنل: <b><?php echo htmlspecialchars($pName, ENT_QUOTES); ?></b></span>
                                        <span><?php echo icon('package', 'svg-icon svg-sm'); ?> محصول: <b><?php echo htmlspecialchars($prName, ENT_QUOTES); ?></b></span>
                                        <?php if ($prodDays > 0 && ($st !== 'selled' || $rem === null)): ?>
                                            <span><?php echo icon('hourglass', 'svg-icon svg-sm'); ?> مدت محصول: <b><?php echo $prodDays; ?> روز</b></span>
                                        <?php endif; ?>
                                        <?php if (trim((string)($head['username'] ?? '')) !== ''): ?><span><?php echo icon('user', 'svg-icon svg-sm'); ?> <?php echo htmlspecialchars((string)$head['username'], ENT_QUOTES); ?></span><?php endif; ?>
                                    </div>
                                    <div class="ms-group-items">
                                        <?php foreach ($rows as $r):
                                            $rFext = trim((string)($r['file_ext'] ?? ''));
                                        ?>
                                            <div class="ms-group-item">
                                                <span class="ms-group-item__name"><?php echo htmlspecialchars((string)$r['namerecord'], ENT_QUOTES); ?></span>
                                                <?php if ($rFext !== ''): ?><span class="ms-ext">.<?php echo htmlspecialchars($rFext, ENT_QUOTES); ?></span><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>

                        <?php if ($pages > 1 && count($configs) > 500): ?>
                            <div class="cfg-pagination">
                                <?php $baseQp = htmlspecialchars(faoxima_ms_qs(['p' => null]), ENT_QUOTES); ?>
                                <?php if ($page > 1): ?>
                                    <a class="btn btn-sm btn-outline" href="manual_service.php?<?php echo $baseQp; ?>&p=<?php echo $page - 1; ?>"><?php echo icon('chevron-right', 'svg-icon svg-sm'); ?></a>
                                <?php endif; ?>
                                <span class="cfg-pagination__info">صفحه <?php echo $page; ?> از <?php echo $pages; ?> — <?php echo (int)$total; ?> مورد</span>
                                <?php if ($page < $pages): ?>
                                    <a class="btn btn-sm btn-outline" href="manual_service.php?<?php echo $baseQp; ?>&p=<?php echo $page + 1; ?>"><?php echo icon('chevron-left', 'svg-icon svg-sm'); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div id="modal-ms-storage-settings" class="modal-overlay">
                        <div class="modal-box" style="max-width: 540px;">
                            <div class="modal-head">
                                <span class="modal-head__title">
                                    <?php echo icon('gear', 'svg-icon svg-sm'); ?>
                                    حداکثر حجم ذخیره‌سازی فایل‌ها (گیگابایت)
                                </span>
                                <button type="button" class="modal-close" onclick="closeModal('modal-ms-storage-settings')">&times;</button>
                            </div>
                            <form method="POST" action="manual_service.php" autocomplete="off">
                                <input type="hidden" name="_action" value="update_storage_limit">
                                <div class="modal-body" style="padding: 1.25rem;">
                                    <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 6px;">
                                            <span>حجم اشغال‌شده: <strong><?php echo $usedGbVal; ?> GB</strong></span>
                                            <span>سقف مجاز: <strong><?php echo $maxStorageGb; ?> GB</strong></span>
                                        </div>
                                        <div style="height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; width: <?php echo $percentUsedVal; ?>%; background: <?php echo $percentUsedVal > 90 ? '#ef4444' : ($percentUsedVal > 75 ? '#f59e0b' : '#10b981'); ?>; transition: width 0.3s ease;"></div>
                                        </div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label class="form-label" for="ms_max_storage_gb_input">حداکثر حجم ذخیره‌سازی دیتابیس (GB)</label>
                                        <input type="number" step="0.5" min="0.5" max="1000" class="form-control" id="ms_max_storage_gb_input" name="max_storage_gb"
                                            value="<?php echo htmlspecialchars((string)$maxStorageGb, ENT_QUOTES); ?>" required>
                                        <small class="form-hint" style="margin-top: 6px; display: block; line-height: 1.5;">
                                            مقدار سقف مجاز را به گیگابایت وارد کنید (مانند 1، 5، 10 یا 50 گیگابایت). دیتابیس به‌طور خودکار پیکربندی و ارتقا می‌یابد.
                                        </small>
                                    </div>

                                    <div style="display: flex; gap: 6px; margin-bottom: 12px;">
                                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('ms_max_storage_gb_input').value='1'">1 GB</button>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('ms_max_storage_gb_input').value='5'">5 GB</button>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('ms_max_storage_gb_input').value='10'">10 GB</button>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('ms_max_storage_gb_input').value='20'">20 GB</button>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('ms_max_storage_gb_input').value='50'">50 GB</button>
                                    </div>
                                </div>
                                <div class="modal-foot">
                                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-ms-storage-settings')">انصراف</button>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo icon('circle-check', 'svg-icon svg-sm'); ?>
                                        <span>ذخیره و اعمال تنظیمات</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($panels)): ?>
                        <div id="modal-ms-add" class="modal-overlay">
                            <div class="modal-box" style="max-width: 640px;">
                                <div class="modal-head">
                                    <span class="modal-head__title">افزودن کانفیگ فروش دستی</span>
                                    <button type="button" class="modal-close" onclick="closeModal('modal-ms-add')">&times;</button>
                                </div>
                                <form method="POST" action="manual_service.php" enctype="multipart/form-data" id="ms-add-form">
                                    <input type="hidden" name="_action" value="ms_add_config">
                                    <input type="hidden" name="items_json" id="ms-bulk-items-json">

                                    <div class="form-group">
                                        <label class="form-label">پنل فروش دستی</label>
                                        <select name="codepanel" class="form-control" id="ms-panel-select" required>
                                            <?php foreach ($panels as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p['code_panel'], ENT_QUOTES); ?>" data-name-panel="<?php echo htmlspecialchars((string)$p['name_panel'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($p['name_panel'], ENT_QUOTES); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">محصول مرتبط</label>
                                        <select name="codeproduct" class="form-control" id="ms-product-select" required>
                                            <option value="usertest" data-location="/all">تست</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p['code_product'] ?? '', ENT_QUOTES); ?>" data-location="<?php echo htmlspecialchars((string)($p['Location'] ?? ''), ENT_QUOTES); ?>"><?php echo htmlspecialchars((string)($p['name_product'] ?? $p['Name_product'] ?? ''), ENT_QUOTES); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">نام کانفیگ</label>
                                        <input type="text" name="namerecord" class="form-control" required>
                                        <small class="form-hint">در حالت دسته‌ای، به هر کانفیگ یک شماره اضافه می‌شود (مثال: name-1، name-2).</small>
                                    </div>

                                    <div class="form-group entry-mode-group">
                                        <div class="entry-mode-group__title">نحوه ورود کانفیگ</div>
                                        <div class="entry-mode-options">
                                            <label class="mode-pill" data-source="file">
                                                <input type="radio" name="config_source" value="file" required>
                                                <div class="mode-pill__text">
                                                    <div class="mode-pill__title"><?php echo icon('package', 'svg-icon svg-sm'); ?> به صورت فایل</div>
                                                    <small class="mode-pill__hint">آپلود فایل‌های کانفیگ</small>
                                                </div>
                                            </label>
                                            <label class="mode-pill" data-source="text">
                                                <input type="radio" name="config_source" value="text" checked>
                                                <div class="mode-pill__text">
                                                    <div class="mode-pill__title"><?php echo icon('text', 'svg-icon svg-sm'); ?> به صورت تایپ / متن</div>
                                                    <small class="mode-pill__hint">وارد کردن متنی کانفیگ یا لینک اشتراک</small>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <div id="ms-after-source-wrap" style="display:none;">

                                        <div class="form-group entry-mode-group">
                                            <div class="entry-mode-group__title">نوع ورود</div>
                                            <div class="entry-mode-options">
                                                <label class="mode-pill" data-mode="single">
                                                    <input type="radio" name="entry_mode" value="single" checked>
                                                    <div class="mode-pill__text">
                                                        <div class="mode-pill__title"><?php echo icon('plus', 'svg-icon svg-sm'); ?> تکی</div>
                                                        <small class="mode-pill__hint">یک فایل، یا یک کانفیگ/لینک</small>
                                                    </div>
                                                </label>
                                                <label class="mode-pill" data-mode="multi">
                                                    <input type="radio" name="entry_mode" value="multi">
                                                    <div class="mode-pill__text">
                                                        <div class="mode-pill__title"><?php echo icon('package', 'svg-icon svg-sm'); ?> چندموردی</div>
                                                        <small class="mode-pill__hint">چند فایل/کانفیگ به‌عنوان یک تحویل واحد</small>
                                                    </div>
                                                </label>
                                                <label class="mode-pill" data-mode="bulk">
                                                    <input type="radio" name="entry_mode" value="bulk">
                                                    <div class="mode-pill__text">
                                                        <div class="mode-pill__title"><?php echo icon('package', 'svg-icon svg-sm'); ?> دسته‌ای</div>
                                                        <small class="mode-pill__hint">چند فایل یا چند کانفیگ/لینک با هم</small>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div id="ms-file-section" style="display:none;">
                                            <div id="ms-file-single-fields">
                                                <div class="form-group">
                                                    <label class="form-label">فایل کانفیگ</label>
                                                    <label class="ms-dropzone" id="ms-dropzone-single">
                                                        <input type="file" name="single_file" id="ms-single-file">
                                                        <span class="ms-dropzone__icon"><?php echo icon('arrow-up', 'svg-icon svg-lg'); ?></span>
                                                        <span class="ms-dropzone__text" id="ms-single-file-name">فایل را اینجا رها کنید یا برای انتخاب کلیک کنید</span>
                                                        <button type="button" class="ms-dropzone__clear" id="ms-single-file-clear" title="حذف فایل" style="display:none;">&times;</button>
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <label class="mode-pill mode-pill--wide">
                                                        <input type="checkbox" name="file_has_sub_link" id="ms-single-file-sub-toggle" value="1">
                                                        <div class="mode-pill__text">
                                                            <div class="mode-pill__title"><?php echo icon('link', 'svg-icon svg-sm'); ?> لینک اشتراک</div>
                                                            <small class="mode-pill__hint">اگر این کانفیگ لینک اشتراک مجزا دارد، فعال کنید.</small>
                                                        </div>
                                                    </label>
                                                </div>
                                                <div class="form-group" id="ms-single-file-sub-wrap" style="display:none;">
                                                    <label class="form-label">آدرس لینک اشتراک</label>
                                                    <input type="text" name="file_single_sub_link" id="ms-single-file-sub-input" class="form-control" placeholder="https://sub.example.com/...">
                                                </div>
                                            </div>

                                            <div id="ms-file-bulk-fields" style="display:none;">
                                                <div class="card" style="padding:15px; background:var(--surface-2); margin-bottom:15px; border:1px solid var(--border-soft);">
                                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                                                        <span class="badge badge-accent" id="ms-file-bulk-step-badge">فایل ۱ از ۱</span>
                                                        <button type="button" class="btn btn-sm btn-outline btn-outline-danger" id="ms-file-bulk-del-step" style="display:none;">حذف این گام</button>
                                                    </div>
                                                    <div id="ms-file-bulk-steps-container"></div>
                                                    <div class="wizard-step-nav">
                                                        <button type="button" class="btn btn-outline btn-sm" id="ms-file-bulk-prev" disabled>قبلی</button>
                                                        <button type="button" class="btn btn-outline btn-sm" id="ms-file-bulk-next">فایل بعدی (+)</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="ms-text-section" style="display:none;">
                                            <div class="form-group">
                                                <label class="mode-pill mode-pill--wide">
                                                    <input type="checkbox" name="send_as_file" id="ms-send-as-file-toggle" value="1">
                                                    <div class="mode-pill__text">
                                                        <div class="mode-pill__title"><?php echo icon('fileText', 'svg-icon svg-sm'); ?> ارسال محتوای متنی به صورت فایل</div>
                                                        <small class="mode-pill__hint">اگر فعال باشد، کانفیگِ متنی به کاربر به شکل فایل تحویل داده می‌شود.</small>
                                                    </div>
                                                </label>
                                            </div>

                                            <div class="form-group" id="ms-custom-ext-wrap" style="display:none;">
                                                <label class="form-label">پسوند سفارشی فایل خروجی</label>
                                                <input type="text" name="custom_ext" id="ms-custom-ext-input" class="form-control" maxlength="10" placeholder="مثال: conf، ovpn، cfg">
                                                <small class="form-hint">پسوند فایلی که به کاربر تحویل داده می‌شود را وارد کنید (پیش‌فرض: conf).</small>
                                            </div>

                                            <div id="ms-single-fields">
                                                <div class="form-group">
                                                    <label class="form-label">محتوای کانفیگ</label>
                                                    <textarea name="contentrecord" rows="4" class="form-control cfg-textarea single-cfg" placeholder="vmess://..."></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label class="mode-pill mode-pill--wide">
                                                        <input type="checkbox" name="has_sub_link" id="ms-single-sub-toggle" value="1">
                                                        <div class="mode-pill__text">
                                                            <div class="mode-pill__title"><?php echo icon('link', 'svg-icon svg-sm'); ?> لینک اشتراک</div>
                                                            <small class="mode-pill__hint">اگر این کانفیگ لینک اشتراک مجزا دارد، فعال کنید.</small>
                                                        </div>
                                                    </label>
                                                </div>
                                                <div class="form-group" id="ms-single-sub-wrap" style="display:none;">
                                                    <label class="form-label">آدرس لینک اشتراک</label>
                                                    <input type="text" name="single_sub_link" id="ms-single-sub-input" class="form-control" placeholder="https://sub.example.com/...">
                                                </div>
                                            </div>

                                            <div id="ms-bulk-fields" style="display:none;">
                                                <div class="card" style="padding:15px; background:var(--surface-2); margin-bottom:15px; border:1px solid var(--border-soft);">
                                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                                                        <span class="badge badge-accent" id="ms-bulk-step-badge">کانفیگ ۱ از ۱</span>
                                                        <button type="button" class="btn btn-sm btn-outline btn-outline-danger" id="ms-bulk-del-step" style="display:none;">حذف این گام</button>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">محتوای کانفیگ</label>
                                                        <textarea rows="4" id="ms-bulk-cfg-input" class="form-control cfg-textarea" placeholder="vless://..."></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="mode-pill mode-pill--wide">
                                                            <input type="checkbox" id="ms-bulk-sub-toggle" value="1">
                                                            <div class="mode-pill__text">
                                                                <div class="mode-pill__title"><?php echo icon('link', 'svg-icon svg-sm'); ?> لینک اشتراک</div>
                                                                <small class="mode-pill__hint">برای این کانفیگ لینک اشتراک ثبت شود.</small>
                                                            </div>
                                                        </label>
                                                    </div>
                                                    <div class="form-group" id="ms-bulk-sub-wrap" style="display:none;">
                                                        <label class="form-label">آدرس لینک اشتراک</label>
                                                        <input type="text" id="ms-bulk-sub-input" class="form-control" placeholder="https://sub.example.com/...">
                                                    </div>
                                                    <div class="wizard-step-nav">
                                                        <button type="button" class="btn btn-outline btn-sm" id="ms-bulk-prev-btn" disabled>قبلی</button>
                                                        <button type="button" class="btn btn-outline btn-sm" id="ms-bulk-next-btn">کانفیگ بعدی (+)</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="modal-foot">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-ms-add')">انصراف</button>
                                        <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('plus', 'svg-icon svg-sm'); ?> ثبت</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </section>
    </section>

    <script src="js/bulk-select.js?v=fx2"></script>
    <script>
        function openModal(id) {
            var m = document.getElementById(id);
            if (!m) return;
            if (typeof window.closeDetailSheet === 'function') window.closeDetailSheet();
            m.classList.add('active');
        }

        function closeModal(id) {
            var m = document.getElementById(id);
            if (m) m.classList.remove('active');
        }

        (function() {
            var form = document.getElementById('ms-add-form');
            if (!form) return;

            var afterSourceWrap = document.getElementById('ms-after-source-wrap');
            var fileSection = document.getElementById('ms-file-section');
            var textSection = document.getElementById('ms-text-section');
            var fileSingleFields = document.getElementById('ms-file-single-fields');
            var fileBulkFields = document.getElementById('ms-file-bulk-fields');
            var singleFields = document.getElementById('ms-single-fields');
            var bulkFields = document.getElementById('ms-bulk-fields');
            var modePills = form.querySelectorAll('.mode-pill');

            var sendAsFileToggle = document.getElementById('ms-send-as-file-toggle');
            var customExtWrap = document.getElementById('ms-custom-ext-wrap');
            var customExtInput = document.getElementById('ms-custom-ext-input');

            if (sendAsFileToggle && customExtWrap) {
                sendAsFileToggle.addEventListener('change', function() {
                    customExtWrap.style.display = sendAsFileToggle.checked ? '' : 'none';
                    if (!sendAsFileToggle.checked && customExtInput) {
                        customExtInput.value = '';
                    }
                });
            }

            var singleFileSubToggle = document.getElementById('ms-single-file-sub-toggle');
            var singleFileSubWrap = document.getElementById('ms-single-file-sub-wrap');
            var singleFileSubInput = document.getElementById('ms-single-file-sub-input');
            if (singleFileSubToggle && singleFileSubWrap) {
                singleFileSubToggle.addEventListener('change', function() {
                    singleFileSubWrap.style.display = singleFileSubToggle.checked ? '' : 'none';
                    if (!singleFileSubToggle.checked && singleFileSubInput) singleFileSubInput.value = '';
                });
            }

            function setupDropzone(dropzoneEl, inputEl, labelEl, clearEl) {
                if (!dropzoneEl || !inputEl) return;
                var placeholder = labelEl ? labelEl.textContent : '';

                function updateLabel() {
                    if (inputEl.files && inputEl.files.length > 0) {
                        if (labelEl) labelEl.textContent = inputEl.files[0].name;
                        if (clearEl) clearEl.style.display = 'flex';
                        dropzoneEl.classList.add('has-file');
                    } else {
                        if (labelEl) labelEl.textContent = placeholder;
                        if (clearEl) clearEl.style.display = 'none';
                        dropzoneEl.classList.remove('has-file');
                    }
                }

                inputEl.addEventListener('change', updateLabel);

                if (clearEl) {
                    clearEl.addEventListener('click', function(e) {
                        e.preventDefault();
                        inputEl.value = '';
                        updateLabel();
                    });
                }

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(evName) {
                    dropzoneEl.addEventListener(evName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }, false);
                });

                ['dragenter', 'dragover'].forEach(function(evName) {
                    dropzoneEl.addEventListener(evName, function() {
                        dropzoneEl.classList.add('is-dragover');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(function(evName) {
                    dropzoneEl.addEventListener(evName, function() {
                        dropzoneEl.classList.remove('is-dragover');
                    }, false);
                });

                dropzoneEl.addEventListener('drop', function(e) {
                    var dt = e.dataTransfer;
                    if (dt && dt.files && dt.files.length > 0) {
                        try {
                            inputEl.files = dt.files;
                        } catch (err) {}
                        updateLabel();
                    }
                }, false);
            }

            var singleFileInp = document.getElementById('ms-single-file');
            var singleFileName = document.getElementById('ms-single-file-name');
            var singleFileClear = document.getElementById('ms-single-file-clear');
            var singleDropzone = document.getElementById('ms-dropzone-single');

            if (singleDropzone && singleFileInp) {
                setupDropzone(singleDropzone, singleFileInp, singleFileName, singleFileClear);
            }

            var fileStepSeq = 0;
            var fileStepsCount = 0;
            var fileCurrentStep = 0;

            function renderFileStep(index) {
                fileCurrentStep = index;
                var container = document.getElementById('ms-file-bulk-steps-container');
                if (!container) return;

                var stepBadge = document.getElementById('ms-file-bulk-step-badge');
                var delBtn = document.getElementById('ms-file-bulk-del-step');
                var prevBtn = document.getElementById('ms-file-bulk-prev');
                var nextBtn = document.getElementById('ms-file-bulk-next');

                if (stepBadge) stepBadge.textContent = 'فایل ' + (fileCurrentStep + 1) + ' از ' + fileStepsCount;

                var allSteps = container.querySelectorAll('.file-step-item');
                allSteps.forEach(function(st, idx) {
                    st.style.display = (idx === fileCurrentStep) ? '' : 'none';
                });

                if (prevBtn) prevBtn.disabled = (fileCurrentStep === 0);
                if (nextBtn) nextBtn.textContent = (fileCurrentStep === fileStepsCount - 1) ? 'فایل بعدی (+)' : 'بعدی';
                if (delBtn) delBtn.style.display = (fileStepsCount > 1) ? '' : 'none';
            }

            function addFileStepElement() {
                var container = document.getElementById('ms-file-bulk-steps-container');
                if (!container) return;
                var idx = fileStepSeq++;
                fileStepsCount++;

                var div = document.createElement('div');
                div.className = 'file-step-item';
                div.setAttribute('data-step-index', idx);
                div.innerHTML = '<div class="form-group">' +
                    '<label class="form-label">فایل کانفیگ</label>' +
                    '<label class="ms-dropzone">' +
                    '<input type="file" name="bulk_files[' + idx + ']" class="file-step-input">' +
                    '<span class="ms-dropzone__icon"><?php echo icon("arrow-up", "svg-icon svg-lg"); ?></span>' +
                    '<span class="ms-dropzone__text file-step-label">فایل را اینجا رها کنید یا برای انتخاب کلیک کنید</span>' +
                    '<button type="button" class="ms-dropzone__clear file-step-clear" style="display:none;">&times;</button>' +
                    '</label>' +
                    '</div>' +
                    '<div class="form-group">' +
                    '<label class="mode-pill mode-pill--wide">' +
                    '<input type="checkbox" name="bulk_file_has_subs[' + idx + ']" value="1" class="file-step-sub-toggle">' +
                    '<div class="mode-pill__text">' +
                    '<div class="mode-pill__title"><?php echo icon("link", "svg-icon svg-sm"); ?> لینک اشتراک</div>' +
                    '<small class="mode-pill__hint">برای این فایل لینک اشتراک ثبت شود.</small>' +
                    '</div>' +
                    '</label>' +
                    '</div>' +
                    '<div class="form-group file-step-sub-wrap" style="display:none;">' +
                    '<label class="form-label">آدرس لینک اشتراک</label>' +
                    '<input type="text" name="bulk_file_subs[' + idx + ']" class="form-control file-step-sub-input" placeholder="https://sub.example.com/...">' +
                    '</div>';

                container.appendChild(div);

                var dropzone = div.querySelector('.ms-dropzone');
                var input = div.querySelector('.file-step-input');
                var label = div.querySelector('.file-step-label');
                var clear = div.querySelector('.file-step-clear');
                var subToggle = div.querySelector('.file-step-sub-toggle');
                var subWrap = div.querySelector('.file-step-sub-wrap');
                var subInput = div.querySelector('.file-step-sub-input');

                if (dropzone && input) {
                    setupDropzone(dropzone, input, label, clear);
                }

                if (subToggle && subWrap) {
                    subToggle.addEventListener('change', function() {
                        subWrap.style.display = subToggle.checked ? '' : 'none';
                        if (!subToggle.checked && subInput) subInput.value = '';
                    });
                }

                return div;
            }

            var filePrevBtn = document.getElementById('ms-file-bulk-prev');
            var fileNextBtn = document.getElementById('ms-file-bulk-next');
            var fileDelBtn = document.getElementById('ms-file-bulk-del-step');

            if (filePrevBtn) {
                filePrevBtn.addEventListener('click', function() {
                    if (fileCurrentStep > 0) {
                        renderFileStep(fileCurrentStep - 1);
                    }
                });
            }

            if (fileNextBtn) {
                fileNextBtn.addEventListener('click', function() {
                    if (fileCurrentStep === fileStepsCount - 1) {
                        addFileStepElement();
                    }
                    renderFileStep(fileCurrentStep + 1);
                });
            }

            if (fileDelBtn) {
                fileDelBtn.addEventListener('click', function() {
                    if (fileStepsCount <= 1) return;
                    var container = document.getElementById('ms-file-bulk-steps-container');
                    var allSteps = container.querySelectorAll('.file-step-item');
                    if (allSteps[fileCurrentStep]) {
                        allSteps[fileCurrentStep].remove();
                    }
                    fileStepsCount--;
                    var nextIdx = fileCurrentStep >= fileStepsCount ? fileStepsCount - 1 : fileCurrentStep;
                    renderFileStep(nextIdx);
                });
            }

            if (fileStepsCount === 0) {
                addFileStepElement();
                renderFileStep(0);
            }

            function currentSource() {
                var checked = form.querySelector('input[name="config_source"]:checked');
                return checked ? checked.value : '';
            }

            function currentMode() {
                var checked = form.querySelector('input[name="entry_mode"]:checked');
                var v = checked ? checked.value : '';
                return (v === 'bulk' || v === 'multi') ? v : 'single';
            }

            function applySource(source) {
                if (!source) {
                    if (afterSourceWrap) afterSourceWrap.style.display = 'none';
                    return;
                }
                if (afterSourceWrap) afterSourceWrap.style.display = '';

                modePills.forEach(function(p) {
                    if (p.hasAttribute('data-source')) {
                        var active = p.getAttribute('data-source') === source;
                        p.style.borderColor = active ? 'var(--accent)' : 'var(--border-color)';
                        p.style.background = active ? 'var(--surface-2)' : '';
                    }
                });

                if (source === 'file') {
                    if (fileSection) fileSection.style.display = '';
                    if (textSection) textSection.style.display = 'none';
                } else {
                    if (fileSection) fileSection.style.display = 'none';
                    if (textSection) textSection.style.display = '';
                }
                applyMode(currentMode());
            }

            function applyMode(mode) {
                var source = currentSource();
                var isSingle = mode === 'single';

                modePills.forEach(function(p) {
                    if (p.hasAttribute('data-mode')) {
                        var active = p.getAttribute('data-mode') === mode;
                        p.style.borderColor = active ? 'var(--accent)' : 'var(--border-color)';
                        p.style.background = active ? 'var(--surface-2)' : '';
                    }
                });

                if (source === 'file') {
                    if (fileSingleFields) fileSingleFields.style.display = isSingle ? '' : 'none';
                    if (fileBulkFields) fileBulkFields.style.display = isSingle ? 'none' : '';
                } else if (source === 'text') {
                    if (singleFields) singleFields.style.display = isSingle ? '' : 'none';
                    if (bulkFields) bulkFields.style.display = isSingle ? 'none' : '';
                }
            }

            form.querySelectorAll('input[name="config_source"]').forEach(function(r) {
                r.addEventListener('change', function() {
                    applySource(r.value);
                });
            });

            form.querySelectorAll('input[name="entry_mode"]').forEach(function(r) {
                r.addEventListener('change', function() {
                    applyMode(r.value);
                });
            });

            modePills.forEach(function(p) {
                if (p.hasAttribute('data-source')) {
                    p.addEventListener('click', function() {
                        var r = p.querySelector('input[type=radio]');
                        if (r) {
                            r.checked = true;
                            applySource(p.getAttribute('data-source'));
                        }
                    });
                }
                if (p.hasAttribute('data-mode')) {
                    p.addEventListener('click', function() {
                        var r = p.querySelector('input[type=radio]');
                        if (r) {
                            r.checked = true;
                            applyMode(p.getAttribute('data-mode'));
                        }
                    });
                }
            });

            applySource(currentSource() || 'text');

            var singleSubToggle = document.getElementById('ms-single-sub-toggle');
            var singleSubWrap = document.getElementById('ms-single-sub-wrap');
            var singleSubInput = document.getElementById('ms-single-sub-input');

            if (singleSubToggle && singleSubWrap) {
                singleSubToggle.addEventListener('change', function() {
                    singleSubWrap.style.display = singleSubToggle.checked ? '' : 'none';
                    if (!singleSubToggle.checked && singleSubInput) {
                        singleSubInput.value = '';
                    }
                });
            }

            var bulkItems = [{
                content: '',
                hasSub: false,
                subLink: ''
            }];
            var currentStep = 0;

            var stepBadge = document.getElementById('ms-bulk-step-badge');
            var bulkCfgInput = document.getElementById('ms-bulk-cfg-input');
            var bulkSubToggle = document.getElementById('ms-bulk-sub-toggle');
            var bulkSubWrap = document.getElementById('ms-bulk-sub-wrap');
            var bulkSubInput = document.getElementById('ms-bulk-sub-input');
            var bulkPrevBtn = document.getElementById('ms-bulk-prev-btn');
            var bulkNextBtn = document.getElementById('ms-bulk-next-btn');
            var bulkDelBtn = document.getElementById('ms-bulk-del-step');
            var itemsJsonInput = document.getElementById('ms-bulk-items-json');

            if (bulkSubToggle && bulkSubWrap) {
                bulkSubToggle.addEventListener('change', function() {
                    bulkSubWrap.style.display = bulkSubToggle.checked ? '' : 'none';
                    if (!bulkSubToggle.checked && bulkSubInput) {
                        bulkSubInput.value = '';
                    }
                });
            }

            function saveCurrentStep() {
                if (currentStep < 0 || currentStep >= bulkItems.length) return;
                bulkItems[currentStep] = {
                    content: (bulkCfgInput ? bulkCfgInput.value : '').trim(),
                    hasSub: (bulkSubToggle ? bulkSubToggle.checked : false),
                    subLink: (bulkSubInput ? bulkSubInput.value : '').trim()
                };
            }

            function renderStep(index) {
                currentStep = index;
                var item = bulkItems[currentStep] || {
                    content: '',
                    hasSub: false,
                    subLink: ''
                };
                if (stepBadge) {
                    stepBadge.textContent = 'کانفیگ ' + (currentStep + 1) + ' از ' + bulkItems.length;
                }
                if (bulkCfgInput) bulkCfgInput.value = item.content;
                if (bulkSubToggle) {
                    bulkSubToggle.checked = item.hasSub;
                    if (bulkSubWrap) bulkSubWrap.style.display = item.hasSub ? '' : 'none';
                }
                if (bulkSubInput) bulkSubInput.value = item.subLink;

                if (bulkPrevBtn) bulkPrevBtn.disabled = (currentStep === 0);
                if (bulkNextBtn) bulkNextBtn.textContent = (currentStep === bulkItems.length - 1) ? 'کانفیگ بعدی (+)' : 'بعدی';
                if (bulkDelBtn) bulkDelBtn.style.display = (bulkItems.length > 1) ? '' : 'none';
            }

            if (bulkPrevBtn) {
                bulkPrevBtn.addEventListener('click', function() {
                    saveCurrentStep();
                    if (currentStep > 0) {
                        renderStep(currentStep - 1);
                    }
                });
            }

            if (bulkNextBtn) {
                bulkNextBtn.addEventListener('click', function() {
                    saveCurrentStep();
                    if (currentStep === bulkItems.length - 1) {
                        bulkItems.push({
                            content: '',
                            hasSub: false,
                            subLink: ''
                        });
                    }
                    renderStep(currentStep + 1);
                });
            }

            if (bulkDelBtn) {
                bulkDelBtn.addEventListener('click', function() {
                    if (bulkItems.length <= 1) return;
                    bulkItems.splice(currentStep, 1);
                    var nextIndex = currentStep >= bulkItems.length ? bulkItems.length - 1 : currentStep;
                    renderStep(nextIndex);
                });
            }

            form.addEventListener('submit', function(ev) {
                var source = currentSource();
                var mode = currentMode();

                if (!source) {
                    ev.preventDefault();
                    alert('لطفاً نحوه ورود کانفیگ (فایل یا متن) را انتخاب کنید.');
                    return false;
                }

                if (source === 'text') {
                    if (mode === 'bulk' || mode === 'multi') {
                        saveCurrentStep();
                        var validItems = bulkItems.filter(function(it) {
                            return it.content !== '' || (it.hasSub && it.subLink !== '');
                        }).map(function(it) {
                            return {
                                content: it.content,
                                sub_link: it.hasSub ? it.subLink : ''
                            };
                        });

                        if (validItems.length === 0) {
                            ev.preventDefault();
                            alert('لطفاً حداقل یک کانفیگ یا لینک وارد کنید.');
                            return false;
                        }
                        var jsonVal = JSON.stringify(validItems);
                        var inputs = form.querySelectorAll('input[name="items_json"]');
                        inputs.forEach(function(inp) {
                            inp.value = jsonVal;
                        });
                        if (itemsJsonInput) itemsJsonInput.value = jsonVal;
                    } else {
                        var singleCfg = form.querySelector('.single-cfg');
                        var cfgVal = (singleCfg ? singleCfg.value : '').trim();
                        var hasSub = singleSubToggle ? singleSubToggle.checked : false;
                        var subVal = (singleSubInput ? singleSubInput.value : '').trim();

                        if (cfgVal === '' && (!hasSub || subVal === '')) {
                            ev.preventDefault();
                            alert('لطفاً کانفیگ یا لینک اشتراک را وارد کنید.');
                            return false;
                        }
                        var singleJson = JSON.stringify([{
                            content: cfgVal,
                            sub_link: hasSub ? subVal : ''
                        }]);
                        var inputs = form.querySelectorAll('input[name="items_json"]');
                        inputs.forEach(function(inp) {
                            inp.value = singleJson;
                        });
                        if (itemsJsonInput) itemsJsonInput.value = singleJson;
                    }
                } else if (source === 'file') {
                    var inputs = form.querySelectorAll('input[name="items_json"]');
                    inputs.forEach(function(inp) {
                        inp.value = '';
                    });
                    var singleCfg = form.querySelector('.single-cfg');
                    if (singleCfg) singleCfg.value = '';

                    if (mode === 'single') {
                        if (!singleFileInp || !singleFileInp.files || singleFileInp.files.length === 0) {
                            ev.preventDefault();
                            alert('لطفاً فایل کانفیگ را انتخاب کنید.');
                            return false;
                        }
                    } else {
                        var container = document.getElementById('ms-file-bulk-steps-container');
                        var stepInputs = container ? container.querySelectorAll('.file-step-input') : [];
                        var hasAnyFile = false;
                        stepInputs.forEach(function(inp) {
                            if (inp.files && inp.files.length > 0) hasAnyFile = true;
                        });
                        if (!hasAnyFile) {
                            ev.preventDefault();
                            alert('لطفاً حداقل یک فایل کانفیگ انتخاب کنید.');
                            return false;
                        }
                    }
                }
            });

            applySource(currentSource());
            renderStep(0);

            var panelSel = document.getElementById('ms-panel-select');
            var productSel = document.getElementById('ms-product-select');
            if (panelSel && productSel) {
                var allOptions = Array.prototype.slice.call(productSel.options);
                var productPicked = false;
                productSel.addEventListener('change', function() { productPicked = true; });

                function filterProducts() {
                    var opt = panelSel.options[panelSel.selectedIndex];
                    var panelName = opt ? (opt.getAttribute('data-name-panel') || '') : '';
                    var firstVisible = null;
                    var firstProduct = null;
                    allOptions.forEach(function(o) {
                        var loc = o.getAttribute('data-location') || '';
                        var locParts = loc.split(',').map(function(s) { return s.trim(); });
                        var show = (loc === '/all' || (panelName !== '' && locParts.indexOf(panelName) !== -1));
                        o.hidden = !show;
                        o.disabled = !show;
                        if (show && firstVisible === null) firstVisible = o;
                        if (show && firstProduct === null && o.value !== 'usertest') firstProduct = o;
                    });
                    var cur = productSel.options[productSel.selectedIndex];
                    if (!cur || cur.hidden || (!productPicked && cur.value === 'usertest' && firstProduct)) {
                        var pick = firstProduct || firstVisible;
                        if (pick) pick.selected = true;
                    }
                }
                panelSel.addEventListener('change', filterProducts);
                filterProducts();
            }
        })();

        (function faoximaMsBulkInit() {
            'use strict';
            var bar = document.getElementById('ms-bulk-bar');
            var allCb = document.getElementById('ms-bulk-all');
            var countSpan = document.getElementById('ms-bulk-count');
            var btn = document.getElementById('ms-bulk-btn');
            var form = document.getElementById('ms-bulk-form');
            if (!bar || !allCb || !countSpan || !btn) return;

            var fxMsHandle = FxBulkSelect.init({
                scope: 'default',
                checkboxSelector: 'input.row-check[name="ids[]"]',
                scopeRoot: document,
                formEl: form,
                deleteButtonSelector: '.js-bulk-delete-btn',
                clearOnQueryFlags: []
            });

            function getAllRows() {
                return Array.prototype.slice.call(document.querySelectorAll('input.row-check[name="ids[]"]'));
            }

            function isRowHidden(cb) {
                var parentRow = cb.closest('.ms-row');
                return parentRow && parentRow.style.display === 'none';
            }

            function getVisibleRows() {
                return getAllRows().filter(function(cb) {
                    return !isRowHidden(cb);
                });
            }

            function update() {
                var vis = getVisibleRows();
                var visChecked = 0;
                vis.forEach(function(cb) {
                    if (cb.checked) visChecked++;
                });

                allCb.checked = (vis.length > 0 && visChecked === vis.length);
                allCb.indeterminate = (visChecked > 0 && visChecked < vis.length);

                var persisted = FxBulkSelect.getPersistedIds(fxMsHandle.key);
                var live = getAllRows().filter(function(cb) {
                    return cb.dataset.live === '1' && persisted.indexOf(cb.value) !== -1;
                }).length;

                var text = persisted.length === 0 ?
                    'موردی انتخاب نشده' :
                    persisted.length + ' مورد انتخاب شد' + (live > 0 ? ' (شامل ' + live + ' فعال)' : '');
                countSpan.textContent = text;
            }

            allCb.addEventListener('change', function() {
                var want = allCb.checked;
                getVisibleRows().forEach(function(cb) {
                    if (cb.checked === want) return;
                    cb.checked = want;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                });
                syncGroupChecks();
                update();
            });

            function syncGroupChecks() {
                var groups = document.querySelectorAll('input.group-check');
                Array.prototype.forEach.call(groups, function(gc) {
                    var members = gc.closest('.ms-row').querySelectorAll('input.group-member-check');
                    if (!members.length) return;
                    var checked = 0;
                    Array.prototype.forEach.call(members, function(m) {
                        if (m.checked) checked++;
                    });
                    gc.checked = (checked === members.length);
                    gc.indeterminate = (checked > 0 && checked < members.length);
                });
            }

            document.addEventListener('change', function(e) {
                if (e.target && e.target.classList && e.target.classList.contains('group-check')) {
                    var want = e.target.checked;
                    var members = e.target.closest('.ms-row').querySelectorAll('input.group-member-check');
                    Array.prototype.forEach.call(members, function(m) {
                        if (m.checked === want) return;
                        m.checked = want;
                        m.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    update();
                }
            });

            document.addEventListener('change', function(e) {
                if (e.target && e.target.classList && e.target.classList.contains('row-check')) {
                    update();
                }
            });

            document.addEventListener('faoxima:pagechange', function() {
                update();
            });

            update();

            window.faoximaMsBulkConfirm = function() {
                var count = fxMsHandle.count();
                if (count === 0) {
                    alert('موردی انتخاب نشده است.');
                    return false;
                }
                var msg = count + ' کانفیگ برای همیشه حذف شود؟ این عمل بازگشت‌پذیر نیست.';
                return confirm(msg);
            };
        })();
    </script>
    <script src="js/datatable.js?v=flat12" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.FaoximaListDT) {
                FaoximaListDT.init('.ms-list', {
                    itemSelector: '.ms-row',
                    onPageChange: function() {
                        if (window.faoximaUpdateMsBulk) window.faoximaUpdateMsBulk(true);
                    }
                });
            }
        });
    </script>
</body>

</html>