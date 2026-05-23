<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
if (extension_loaded('zlib') && empty(ini_get('zlib.output_compression'))) {
    @ini_set('zlib.output_compression', '1');
}

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}


$tables = ['nm_stock_shelves' => false, 'nm_config_stock' => false];
foreach ($tables as $t => $_) {
    try {
        $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
        $r->execute([':t' => $t]);
        $tables[$t] = (bool)$r->fetchColumn();
    } catch (\Throwable $e) {}
}
$tableMissing = !($tables['nm_stock_shelves'] && $tables['nm_config_stock']);

$flash = ['ok' => '', 'err' => ''];


$panels = [];
try {
    $r = $pdo->query("SELECT code_panel, name_panel, type FROM marzban_panel ORDER BY name_panel ASC");
    if ($r) $panels = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

$products = [];
try {
    $r = $pdo->query("SELECT * FROM product ORDER BY id ASC");
    if ($r) $products = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

$panelNameOf = [];
foreach ($panels as $p) $panelNameOf[(string)$p['code_panel']] = (string)$p['name_panel'];

$productByCode = [];
foreach ($products as $p) {
    $code = (string)($p['code_product'] ?? '');
    if ($code !== '') $productByCode[$code] = $p;
}


function faoxima_product_name(array $p): string {
    return (string)($p['name_product'] ?? $p['Name_product'] ?? '');
}
function faoxima_product_volume(array $p): float {
    if (isset($p['Volume_constraint'])) return (float)$p['Volume_constraint'];
    if (isset($p['volume_product']))    return (float)$p['volume_product'];
    return 0.0;
}
function faoxima_product_days(array $p): int {
    if (isset($p['Service_time'])) return (int)$p['Service_time'];
    if (isset($p['time_product'])) return (int)$p['time_product'];
    return 0;
}
function faoxima_product_price(array $p): int {
    if (isset($p['Price_product'])) return (int)$p['Price_product'];
    if (isset($p['price_product'])) return (int)$p['price_product'];
    return 0;
}


if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add_shelf') {
        $name        = trim((string)($_POST['name']             ?? ''));
        $sourcePanel = trim((string)($_POST['source_codepanel'] ?? 'auto'));
        $stockPanel  = trim((string)($_POST['stock_codepanel']  ?? 'auto'));
        $codeProduct = trim((string)($_POST['codeproduct']      ?? 'auto'));

        $productName = '';
        $volumeGb    = (float)($_POST['volume_gb']    ?? 0);
        $serviceDays = (int)($_POST['service_days']   ?? 0);
        $price       = (int)($_POST['price']          ?? 0);
        if (isset($productByCode[$codeProduct])) {
            $p = $productByCode[$codeProduct];
            $productName = faoxima_product_name($p);
            if ($volumeGb    <= 0) $volumeGb    = faoxima_product_volume($p);
            if ($serviceDays <= 0) $serviceDays = faoxima_product_days($p);
            if ($price       <= 0) $price       = faoxima_product_price($p);
        }

        $errors = [];
        if ($name === '')                            $errors[] = 'نام انبار خالی است.';
        elseif (mb_strlen($name, 'UTF-8') > 190)     $errors[] = 'نام انبار بیش از حد طولانی است.';
        if ($volumeGb < 0    || $volumeGb > 100000)  $errors[] = 'حجم نامعتبر است.';
        if ($serviceDays < 0 || $serviceDays > 36500)$errors[] = 'مدت سرویس نامعتبر است.';
        if ($price < 0)                              $errors[] = 'قیمت نمی‌تواند منفی باشد.';

        $validCodes = ['auto' => true];
        foreach ($panels as $pp) $validCodes[(string)$pp['code_panel']] = true;
        if (!isset($validCodes[$sourcePanel])) $sourcePanel = 'auto';
        if (!isset($validCodes[$stockPanel]))  $stockPanel  = 'auto';

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
        } else {
            try {
                $dup = $pdo->prepare("SELECT 1 FROM nm_stock_shelves WHERE name = :n AND source_codepanel = :sp LIMIT 1");
                $dup->execute([':n' => $name, ':sp' => $sourcePanel]);
                if ($dup->fetchColumn()) {
                    $flash['err'] = 'انباری با همین نام و پنل مبدأ از قبل وجود دارد.';
                }
            } catch (\Throwable $e) {}

            if (!$flash['err']) {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO nm_stock_shelves
                         (name, source_codepanel, stock_codepanel, codeproduct, product_name,
                          volume_gb, service_days, price, status, created_at)
                         VALUES (:n, :sp, :tp, :cp, :pn, :v, :d, :p, 'active', :t)"
                    );
                    $stmt->execute([
                        ':n'  => $name, ':sp' => $sourcePanel, ':tp' => $stockPanel,
                        ':cp' => $codeProduct, ':pn' => $productName,
                        ':v'  => $volumeGb, ':d'  => $serviceDays, ':p' => $price,
                        ':t'  => time(),
                    ]);
                    $flash['ok'] = 'انبار جدید ثبت شد.';
                } catch (\Throwable $e) {
                    $flash['err'] = 'افزودن ناموفق: ' . $e->getMessage();
                }
            }
        }
    }
    elseif ($action === 'toggle_shelf') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $cur = $pdo->prepare("SELECT status FROM nm_stock_shelves WHERE id = :id");
                $cur->execute([':id' => $id]);
                $curStatus = (string)$cur->fetchColumn();
                $newStatus = ($curStatus === 'active') ? 'disabled' : 'active';
                $pdo->prepare("UPDATE nm_stock_shelves SET status = :s, updated_at = :u WHERE id = :id")
                    ->execute([':s' => $newStatus, ':u' => time(), ':id' => $id]);
                $flash['ok'] = $newStatus === 'active' ? 'انبار فعال شد.' : 'انبار غیرفعال شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'تغییر ناموفق: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'delete_shelf') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {


                $bad = $pdo->prepare("SELECT COUNT(*) FROM nm_config_stock WHERE shelf_id = :id AND status IN ('reserved','delivered')");
                $bad->execute([':id' => $id]);
                if ((int)$bad->fetchColumn() > 0) {
                    $flash['err'] = 'این انبار دارای کانفیگ‌های رزرو/تحویل‌شده است که به سرویس‌ها وصل هستند. به‌جای حذف، انبار را غیرفعال کنید.';
                } else {

                    $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id")->execute([':id' => $id]);
                    $pdo->prepare("DELETE FROM nm_stock_shelves WHERE id = :id")->execute([':id' => $id]);
                    $flash['ok'] = 'انبار و کانفیگ‌های آن حذف شد.';
                }
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'add_configs') {
        $shelfId    = (int)($_POST['shelf_id'] ?? 0);
        $hasSub     = !empty($_POST['has_sub']);
        $configsRaw = (string)($_POST['configs']   ?? '');
        $subsRaw    = (string)($_POST['sub_links'] ?? '');

        $shelf = null;
        try {
            $sh = $pdo->prepare("SELECT * FROM nm_stock_shelves WHERE id = :id");
            $sh->execute([':id' => $shelfId]);
            $shelf = $sh->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {}

        if (!$shelf) {
            $flash['err'] = 'انبار انتخابی پیدا نشد.';
        } elseif (trim($configsRaw) === '') {
            $flash['err'] = 'هیچ کانفیگی وارد نشده است.';
        } else {
            $configLines = preg_split('/\r\n|\r|\n/', $configsRaw) ?: [];
            $subLines    = $hasSub ? (preg_split('/\r\n|\r|\n/', $subsRaw) ?: []) : [];
            $configLines = array_map('trim', $configLines);
            $subLines    = array_map('trim', $subLines);


            $ok = 0; $duplicate = 0; $bad = 0; $badSub = 0;
            $maxRows = max(count($configLines), count($subLines));
            for ($i = 0; $i < $maxRows; $i++) {
                $cfg = $configLines[$i] ?? '';
                if ($cfg === '' || mb_substr($cfg, 0, 1, 'UTF-8') === '#') continue;
                if (mb_strlen($cfg, 'UTF-8') < 8) { $bad++; continue; }

                $sub = null;
                if ($hasSub) {
                    $candidate = $subLines[$i] ?? '';
                    if ($candidate !== '') {
                        if (!preg_match('#^https?://\S+$#i', $candidate)) {


                            $badSub++;
                            $sub = null;
                        } else {
                            $sub = $candidate;
                        }
                    }
                }

                try {


                    $fmt = 'text';
                    if (preg_match('/^https?:\/\//i', $cfg))                                                                $fmt = 'subscription';
                    elseif (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard):\/\//i', $cfg))           $fmt = 'single';
                    elseif (stripos($cfg, '[Interface]') !== false || stripos($cfg, 'PrivateKey') !== false)                 $fmt = 'wireguard';


                    $tier = 'auto';
                    $extractedVol = null;
                    if (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*(?:gb|g|گیگ|گیگابایت)(?:[^0-9]|$)/iu', $cfg, $m)) {
                        $extractedVol = (int)$m[1];
                    } elseif (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*[-_]\s*([1-9][0-9]{0,2})(?:[^0-9]|$)/u', $cfg, $m)) {
                        $tier = ((int)$m[1]) . '-' . ((int)$m[2]);
                    }
                    if ($tier === 'auto') {
                        $vol = $extractedVol !== null ? $extractedVol : (int)ceil((float)$shelf['volume_gb']);
                        if ($vol <= 0)      $tier = 'auto';
                        elseif ($vol <= 10) $tier = '10-10';
                        else {
                            $lower = (int)(floor(($vol - 1) / 10) * 10);
                            $tier  = $lower . '-' . ($lower + 10);
                        }
                    }

                    $stmt = $pdo->prepare(
                        "INSERT IGNORE INTO nm_config_stock
                            (shelf_id, codepanel, codeproduct, tier, format, content, sub_link, status, created_at)
                         VALUES
                            (:shelf_id, :codepanel, :codeproduct, :tier, :format, :content, :sub_link, 'active', :ct)"
                    );
                    $stmt->execute([
                        ':shelf_id'    => (int)$shelf['id'],
                        ':codepanel'   => (string)$shelf['stock_codepanel'],
                        ':codeproduct' => (string)$shelf['codeproduct'],
                        ':tier'        => $tier,
                        ':format'      => $fmt,
                        ':content'     => $cfg,
                        ':sub_link'    => $sub,
                        ':ct'          => time(),
                    ]);
                    if ($stmt->rowCount() > 0) $ok++;
                    else                       $duplicate++;
                } catch (\Throwable $e) {
                    $bad++;
                    error_log('[panel/stock] insert failed: ' . $e->getMessage());
                }
            }

            $parts = ["✅ ثبت‌شده: {$ok}"];
            if ($duplicate > 0) $parts[] = "♻️ تکراری: {$duplicate}";
            if ($bad       > 0) $parts[] = "⚠️ نامعتبر: {$bad}";
            if ($badSub    > 0) $parts[] = "🔗 لینک اشتراک نامعتبر (کانفیگ ثبت شد): {$badSub}";
            $flash['ok'] = 'نتیجه ورود کانفیگ‌ها — ' . implode(' · ', $parts);
            header('Location: stock.php?shelf=' . $shelfId);
            exit;
        }
    }
    elseif ($action === 'delete_config') {


        $configId = (int)($_POST['config_id'] ?? 0);
        $shelfId  = (int)($_POST['shelf_id']  ?? 0);
        if ($configId > 0) {
            try {
                $cur = $pdo->prepare("SELECT status FROM nm_config_stock WHERE id = :id LIMIT 1");
                $cur->execute([':id' => $configId]);
                $curStatus = (string)$cur->fetchColumn();
                if ($curStatus === '') {
                    $flash['err'] = 'کانفیگ پیدا نشد.';
                } elseif ($curStatus === 'reserved' || $curStatus === 'delivered') {
                    $flash['err'] = 'این کانفیگ به یک فاکتور تخصیص داده شده و قابل حذف نیست. ابتدا سرویس مربوطه را لغو کنید.';
                } else {
                    $pdo->prepare("UPDATE nm_config_stock SET status = 'disabled' WHERE id = :id")
                        ->execute([':id' => $configId]);
                    $flash['ok'] = 'کانفیگ غیرفعال شد.';
                }
            } catch (\Throwable $e) {
                $flash['err'] = 'تغییر ناموفق: ' . $e->getMessage();
            }
        }
        if ($shelfId > 0) { header('Location: stock.php?shelf=' . $shelfId); exit; }
    }
    elseif ($action === 'delete_config_hard') {
        // Hard delete a single config from the DB — allowed even for delivered/reserved
        // ones (request #3). The delivered content is already copied into the user's
        // invoice (invoice.user_info) at delivery time, so the active service keeps
        // working; we only remove the stock bookkeeping row here.
        $configId = (int)($_POST['config_id'] ?? 0);
        $shelfId  = (int)($_POST['shelf_id']  ?? 0);
        if ($configId > 0) {
            try {
                $del = $pdo->prepare("DELETE FROM nm_config_stock WHERE id = :id");
                $del->execute([':id' => $configId]);
                $flash['ok'] = $del->rowCount() > 0 ? 'کانفیگ برای همیشه از دیتابیس حذف شد.' : 'کانفیگ پیدا نشد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
        $redir = 'stock.php?shelf=' . $shelfId;
        if (!empty($_POST['cstatus'])) $redir .= '&cstatus=' . urlencode((string)$_POST['cstatus']);
        if (!empty($_POST['cpage']))   $redir .= '&cpage=' . (int)$_POST['cpage'];
        if ($shelfId > 0) { header('Location: ' . $redir); exit; }
    }
    elseif ($action === 'delete_filtered_configs') {
        // Bulk hard-delete by the currently selected filter (request #3: "یا کل بزن").
        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        $cfgFilter = (string)($_POST['cstatus'] ?? 'all');
        $allowed = ['active', 'reserved', 'delivered', 'disabled'];
        if ($shelfId > 0) {
            try {
                if ($cfgFilter !== 'all' && in_array($cfgFilter, $allowed, true)) {
                    $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id AND status = :st");
                    $stmt->execute([':id' => $shelfId, ':st' => $cfgFilter]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id");
                    $stmt->execute([':id' => $shelfId]);
                }
                $flash['ok'] = $stmt->rowCount() . ' کانفیگ برای همیشه از دیتابیس حذف شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
            header('Location: stock.php?shelf=' . $shelfId . '&cstatus=' . urlencode($cfgFilter));
            exit;
        }
    }
    elseif ($action === 'delete_active_configs') {

        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        if ($shelfId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE nm_config_stock SET status = 'disabled' WHERE shelf_id = :id AND status = 'active'");
                $stmt->execute([':id' => $shelfId]);
                $flash['ok'] = $stmt->rowCount() . ' کانفیگ فعال غیرفعال شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'تغییر ناموفق: ' . $e->getMessage();
            }
            header('Location: stock.php?shelf=' . $shelfId);
            exit;
        }
    }
    elseif ($action === 'purge_disabled') {


        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        if ($shelfId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id AND status = 'disabled'");
                $stmt->execute([':id' => $shelfId]);
                $flash['ok'] = $stmt->rowCount() . ' کانفیگ غیرفعال برای همیشه پاک شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'پاکسازی ناموفق: ' . $e->getMessage();
            }
            header('Location: stock.php?shelf=' . $shelfId);
            exit;
        }
    }
    elseif ($action === 'restore_config') {


        $configId = (int)($_POST['config_id'] ?? 0);
        $shelfId  = (int)($_POST['shelf_id']  ?? 0);
        if ($configId > 0) {
            try {
                $pdo->prepare("UPDATE nm_config_stock SET status = 'active' WHERE id = :id AND status = 'disabled'")
                    ->execute([':id' => $configId]);
                $flash['ok'] = 'کانفیگ فعال شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'تغییر ناموفق: ' . $e->getMessage();
            }
        }
        if ($shelfId > 0) { header('Location: stock.php?shelf=' . $shelfId); exit; }
    }
}


$viewShelfId   = isset($_GET['shelf']) ? (int)$_GET['shelf'] : 0;
$selectedShelf = null;
$shelfCounts   = [];

if (!$tableMissing) {
    try {
        $stmt = $pdo->query(
            "SELECT shelf_id, status, COUNT(*) AS cnt FROM nm_config_stock
             WHERE shelf_id IS NOT NULL GROUP BY shelf_id, status"
        );
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sid = (int)$row['shelf_id'];
                $st  = (string)$row['status'];
                if (!isset($shelfCounts[$sid])) {
                    $shelfCounts[$sid] = ['total'=>0,'active'=>0,'reserved'=>0,'delivered'=>0,'disabled'=>0];
                }
                if (isset($shelfCounts[$sid][$st])) $shelfCounts[$sid][$st] = (int)$row['cnt'];
                $shelfCounts[$sid]['total'] += (int)$row['cnt'];
            }
        }
    } catch (\Throwable $e) {}
}

$shelves = [];
$shelfConfigs = [];
if (!$tableMissing) {
    try {
        $r = $pdo->query("SELECT * FROM nm_stock_shelves ORDER BY id DESC");
        if ($r) $shelves = $r->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $flash['err'] = $flash['err'] ?: 'بارگذاری ناموفق: ' . $e->getMessage();
    }
    if ($viewShelfId > 0) {
        foreach ($shelves as $sh) {
            if ((int)$sh['id'] === $viewShelfId) { $selectedShelf = $sh; break; }
        }
        if ($selectedShelf) {
            // ----- Filter + pagination (request #3) -----
            $allowedStatuses = ['active', 'reserved', 'delivered', 'disabled'];
            $cfgFilter = isset($_GET['cstatus']) ? (string)$_GET['cstatus'] : 'all';
            if ($cfgFilter !== 'all' && !in_array($cfgFilter, $allowedStatuses, true)) $cfgFilter = 'all';
            $cfgPerPage = 10;
            $cfgPage = isset($_GET['cpage']) ? max(1, (int)$_GET['cpage']) : 1;

            $whereSql = "shelf_id = :id";
            $params = [':id' => $viewShelfId];
            if ($cfgFilter !== 'all') { $whereSql .= " AND status = :st"; $params[':st'] = $cfgFilter; }

            $cfgTotal = 0;
            try {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM nm_config_stock WHERE {$whereSql}");
                $cnt->execute($params);
                $cfgTotal = (int)$cnt->fetchColumn();
            } catch (\Throwable $e) {}
            $cfgPages = max(1, (int)ceil($cfgTotal / $cfgPerPage));
            if ($cfgPage > $cfgPages) $cfgPage = $cfgPages;
            $cfgOffset = ($cfgPage - 1) * $cfgPerPage;

            try {
                $r = $pdo->prepare("SELECT * FROM nm_config_stock WHERE {$whereSql} ORDER BY id DESC LIMIT {$cfgPerPage} OFFSET {$cfgOffset}");
                $r->execute($params);
                $shelfConfigs = $r->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {}
        }
    }
}

function faoxima_stock_status_label(string $s): array {
    switch ($s) {
        case 'active':    return ['✅ موجود', 'badge-active'];
        case 'reserved':  return ['🔒 رزرو', 'badge-warning'];
        case 'delivered': return ['📦 تحویل شده', 'badge-info'];
        case 'disabled':  return ['❌ غیرفعال', 'badge-block'];
        default:          return [htmlspecialchars($s, ENT_QUOTES), 'badge-gray'];
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>انبار شبکه ملی | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-extra.css">
    <script src="js/theme.js" defer>

</script>
    <style>
        .stock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .shelf-card { background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: 12px; overflow: hidden; }
        .shelf-card__head { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; background: rgba(0,0,0,0.06); border-bottom: 1px solid var(--border-soft); }
        .shelf-card__title { font-weight: 700; font-size: 14px; }
        .shelf-card__body { padding: 12px 14px; font-size: 12.5px; }
        .stat-row { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0 10px; }
        .stat-row .stat-chip { background: var(--surface-3); padding: 4px 10px; border-radius: 100px; font-size: 11.5px; }
        .stat-chip strong { color: var(--accent); margin-inline-start: 4px; }
        .config-content { font-family: 'JetBrains Mono', monospace; font-size: 11px; word-break: break-all; max-height: 60px; overflow: hidden; direction: ltr; text-align: left; color: var(--text-muted); }
        .config-row { background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
        .config-row__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 8px; flex-wrap: wrap; }
        .config-row__meta { font-size: 11.5px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .sub-link-input { display: none; }
        .sub-link-input.active { display: block; margin-top: 12px; }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <?php if ($selectedShelf):  ?>

                <div class="page-head">
                    <div>
                        <div class="page-head__title">
                            <a href="stock.php" style="text-decoration:none; color:var(--text-muted); margin-inline-end:8px;">←</a>
                            <?php echo icon('package', 'svg-icon svg-lg'); ?>
                            <?php echo htmlspecialchars($selectedShelf['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="page-head__sub">
                            محصول: <b><?php echo htmlspecialchars($selectedShelf['product_name'] ?? '—', ENT_QUOTES); ?></b>
                            · حجم: <?php echo (float)$selectedShelf['volume_gb']; ?> گیگ
                            · مدت: <?php echo (int)$selectedShelf['service_days']; ?> روز
                            · قیمت: <?php echo number_format((int)$selectedShelf['price']); ?> تومان
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="openModal('modal-add-configs')">
                            <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                            <span>افزودن کانفیگ</span>
                        </button>
                    </div>
                </div>

                <?php if ($flash['ok']): ?><div class="alert alert-success"><?php echo icon('circle-check', 'svg-icon'); ?><span><?php echo htmlspecialchars($flash['ok'], ENT_QUOTES); ?></span></div><?php endif; ?>
                <?php if ($flash['err']): ?><div class="alert alert-danger"><?php echo icon('circle-exclamation', 'svg-icon'); ?><span><?php echo $flash['err']; ?></span></div><?php endif; ?>

                <?php $counts = $shelfCounts[$viewShelfId] ?? ['total'=>0,'active'=>0,'reserved'=>0,'delivered'=>0,'disabled'=>0]; ?>
                <div class="stat-row" style="margin-bottom:16px;">
                    <div class="stat-chip">کل: <strong><?php echo $counts['total']; ?></strong></div>
                    <div class="stat-chip" style="color:var(--color-success)">✅ موجود: <strong><?php echo $counts['active']; ?></strong></div>
                    <div class="stat-chip" style="color:var(--color-warning)">🔒 رزرو: <strong><?php echo $counts['reserved']; ?></strong></div>
                    <div class="stat-chip" style="color:var(--accent)">📦 تحویل شده: <strong><?php echo $counts['delivered']; ?></strong></div>
                    <?php if ($counts['disabled'] > 0): ?><div class="stat-chip" style="color:var(--color-danger)">❌ غیرفعال: <strong><?php echo $counts['disabled']; ?></strong></div><?php endif; ?>
                </div>

                <div style="display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap;">
                    <?php if ($counts['active'] > 0): ?>
                        <form method="POST" action="stock.php" onsubmit="return confirm('همه <?php echo $counts['active']; ?> کانفیگ فعال این انبار غیرفعال شوند؟ (به‌صورت soft انجام می‌شود — کانفیگ‌های رزرو/تحویل‌شده دست‌نخورده می‌مانند.)');" style="margin:0;">
                            <input type="hidden" name="_action" value="delete_active_configs">
                            <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                <?php echo icon('trash', 'svg-icon'); ?>
                                غیرفعال‌سازی همه کانفیگ‌های فعال
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($counts['disabled'] > 0): ?>
                        <form method="POST" action="stock.php" onsubmit="return confirm('همه <?php echo $counts['disabled']; ?> کانفیگ غیرفعال این انبار برای همیشه پاک شوند؟ این عمل بازگشت‌پذیر نیست.');" style="margin:0;">
                            <input type="hidden" name="_action" value="purge_disabled">
                            <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--color-danger); border-color:var(--color-danger);">
                                <?php echo icon('trash', 'svg-icon'); ?>
                                پاکسازی همه کانفیگ‌های غیرفعال
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php $cfgFilter = $cfgFilter ?? 'all'; $cfgPage = $cfgPage ?? 1; $cfgPages = $cfgPages ?? 1; $cfgTotal = $cfgTotal ?? 0; ?>
                <div class="cfg-filterbar" style="display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; align-items:center;">
                    <span style="color:var(--text-muted); font-size:12px;">فیلتر:</span>
                    <?php
                        $cfgFilters = [
                            'all'       => 'همه',
                            'active'    => '✅ موجود',
                            'reserved'  => '🔒 رزرو',
                            'delivered' => '📦 تحویل شده',
                            'disabled'  => '❌ غیرفعال',
                        ];
                        foreach ($cfgFilters as $fk => $fl):
                            $isActive = ($cfgFilter === $fk);
                    ?>
                        <a href="stock.php?shelf=<?php echo $viewShelfId; ?>&cstatus=<?php echo $fk; ?>"
                           class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-outline'; ?>"
                           style="text-decoration:none;"><?php echo $fl; ?></a>
                    <?php endforeach; ?>
                    <form method="POST" action="stock.php" style="margin:0 0 0 auto;"
                          onsubmit="return confirm('<?php echo $cfgFilter === 'all' ? 'همه کانفیگ‌های این انبار' : 'کانفیگ‌های فیلترشده'; ?> برای همیشه از دیتابیس حذف شوند؟ این عمل بازگشت‌پذیر نیست.');">
                        <input type="hidden" name="_action" value="delete_filtered_configs">
                        <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                        <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($cfgFilter, ENT_QUOTES); ?>">
                        <button type="submit" class="btn btn-sm btn-outline" style="color:var(--color-danger); border-color:var(--color-danger);">
                            <?php echo icon('trash', 'svg-icon'); ?>
                            حذف دائمی <?php echo $cfgFilter === 'all' ? 'کل' : 'این فیلتر'; ?> از دیتابیس
                        </button>
                    </form>
                </div>

                <?php if (empty($shelfConfigs)): ?>
                    <div class="card" style="text-align:center; padding:36px 20px;">
                        <?php echo icon('package', 'svg-icon svg-2xl'); ?>
                        <div style="margin-top:14px; color:var(--text-muted);"><?php echo ($cfgTotal === 0 && $cfgFilter !== 'all') ? 'کانفیگی با این فیلتر یافت نشد.' : 'هنوز کانفیگی در این انبار ثبت نشده است.'; ?></div>
                        <button class="btn btn-primary" style="margin-top:14px;" onclick="openModal('modal-add-configs')">افزودن اولین کانفیگ</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($shelfConfigs as $cfg):
                        [$stLabel, $stClass] = faoxima_stock_status_label((string)$cfg['status']);
                        $cfgStatus = (string)$cfg['status'];
                    ?>
                        <div class="config-row" <?php if ($cfgStatus === 'disabled'): ?>style="opacity:0.6"<?php endif; ?>>
                            <div class="config-row__head">
                                <span class="badge <?php echo $stClass; ?>"><?php echo $stLabel; ?></span>
                                <div class="config-row__meta">
                                    <span>
                                    <?php if (!empty($cfg['sub_link'])): ?>
                                        <span style="color:var(--accent)">🔗 سابسکریپشن دارد</span>
                                    <?php endif; ?>
                                    <?php if (!empty($cfg['assigned_user'])): ?>
                                        <span>👤 <?php echo htmlspecialchars((string)$cfg['assigned_user'], ENT_QUOTES); ?></span>
                                    <?php endif; ?>

                                    <?php if ($cfgStatus === 'active'): ?>
                                        <form method="POST" action="stock.php" style="display:inline" onsubmit="return confirm('این کانفیگ غیرفعال شود؟');">
                                            <input type="hidden" name="_action" value="delete_config">
                                            <input type="hidden" name="config_id" value="<?php echo (int)$cfg['id']; ?>">
                                            <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger" title="غیرفعال‌سازی">
                                                <?php echo icon('trash', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                    <?php elseif ($cfgStatus === 'disabled'): ?>
                                        <form method="POST" action="stock.php" style="display:inline">
                                            <input type="hidden" name="_action" value="restore_config">
                                            <input type="hidden" name="config_id" value="<?php echo (int)$cfg['id']; ?>">
                                            <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline" title="فعال‌سازی مجدد" style="color:var(--color-success); border-color:var(--color-success);">
                                                ♻️ فعال‌سازی
                                            </button>
                                        </form>
                                    <?php else:  ?>
                                        <span title="به یک سرویس متصل است" style="color:var(--text-muted); font-size:11px;">🔒 متصل به سرویس</span>
                                    <?php endif; ?>
                                    <form method="POST" action="stock.php" style="display:inline" onsubmit="return confirm('این کانفیگ برای همیشه از دیتابیس حذف شود؟ این عمل بازگشت‌پذیر نیست.<?php echo ($cfgStatus === 'delivered' || $cfgStatus === 'reserved') ? '\n(این کانفیگ تحویل/رزرو شده؛ سرویس کاربر چون کانفیگ در فاکتورش ذخیره شده دست‌نخورده می‌ماند.)' : ''; ?>');">
                                        <input type="hidden" name="_action" value="delete_config_hard">
                                        <input type="hidden" name="config_id" value="<?php echo (int)$cfg['id']; ?>">
                                        <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                                        <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($cfgFilter, ENT_QUOTES); ?>">
                                        <input type="hidden" name="cpage" value="<?php echo (int)$cfgPage; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" title="حذف دائمی از دیتابیس" style="color:var(--color-danger); border-color:var(--color-danger);">
                                            <?php echo icon('trash', 'svg-icon'); ?> حذف دائمی
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="config-content"><?php echo htmlspecialchars($cfg['content'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if (!empty($cfg['sub_link'])): ?>
                                <div class="config-content" style="margin-top:6px; color:var(--accent);">SUB: <?php echo htmlspecialchars($cfg['sub_link'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($cfgPages > 1): ?>
                        <div class="cfg-pagination" style="display:flex; gap:8px; align-items:center; justify-content:center; margin-top:16px; flex-wrap:wrap;">
                            <?php if ($cfgPage > 1): ?>
                                <a class="btn btn-sm btn-outline" style="text-decoration:none;" href="stock.php?shelf=<?php echo $viewShelfId; ?>&cstatus=<?php echo $cfgFilter; ?>&cpage=<?php echo $cfgPage - 1; ?>">‹ صفحه قبل</a>
                            <?php else: ?>
                                <span class="btn btn-sm btn-outline" style="opacity:0.4; pointer-events:none;">‹ صفحه قبل</span>
                            <?php endif; ?>
                            <span style="color:var(--text-muted); font-size:12.5px;">صفحه <strong><?php echo $cfgPage; ?></strong> از <strong><?php echo $cfgPages; ?></strong> — مجموع <?php echo $cfgTotal; ?> مورد</span>
                            <?php if ($cfgPage < $cfgPages): ?>
                                <a class="btn btn-sm btn-outline" style="text-decoration:none;" href="stock.php?shelf=<?php echo $viewShelfId; ?>&cstatus=<?php echo $cfgFilter; ?>&cpage=<?php echo $cfgPage + 1; ?>">صفحه بعد ›</a>
                            <?php else: ?>
                                <span class="btn btn-sm btn-outline" style="opacity:0.4; pointer-events:none;">صفحه بعد ›</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                
                <div id="modal-add-configs" class="modal-overlay">
                    <div class="modal-box" style="max-width: 720px;">
                        <div class="modal-head">
                            <span class="modal-head__title">افزودن کانفیگ به انبار «<?php echo htmlspecialchars($selectedShelf['name'], ENT_QUOTES); ?>»</span>
                            <button type="button" class="modal-close" onclick="closeModal('modal-add-configs')">&times;</button>
                        </div>
                        <form method="POST" action="stock.php" id="add-configs-form">
                            <input type="hidden" name="_action" value="add_configs">
                            <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">

                            
                            <div class="form-group" style="padding:10px 12px; background:var(--surface-3); border-radius:10px;">
                                <div style="font-weight:700; font-size:13px; margin-bottom:8px;">نوع ورود</div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <label class="mode-pill" data-mode="single" style="flex:1; min-width:140px; cursor:pointer; padding:10px 12px; border-radius:8px; border:2px solid var(--border-color); display:flex; align-items:center; gap:8px; transition:all 0.15s;">
                                        <input type="radio" name="entry_mode" value="single" checked style="margin:0;">
                                        <div>
                                            <div style="font-weight:700; font-size:12.5px;">➕ تکی</div>
                                            <small style="color:var(--text-muted); font-size:11px;">یک کانفیگ همراه لینک</small>
                                        </div>
                                    </label>
                                    <label class="mode-pill" data-mode="bulk" style="flex:1; min-width:140px; cursor:pointer; padding:10px 12px; border-radius:8px; border:2px solid var(--border-color); display:flex; align-items:center; gap:8px; transition:all 0.15s;">
                                        <input type="radio" name="entry_mode" value="bulk" style="margin:0;">
                                        <div>
                                            <div style="font-weight:700; font-size:12.5px;">📦 دسته‌ای</div>
                                            <small style="color:var(--text-muted); font-size:11px;">چند کانفیگ + چند لینک</small>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            
                            <div class="form-group" style="display:flex; align-items:center; gap:12px; padding:10px 12px; background:var(--surface-3); border-radius:10px;">
                                <label class="switch">
                                    <input type="checkbox" name="has_sub" id="toggle-has-sub" value="1">
                                    <span class="switch__slot"></span>
                                </label>
                                <div style="flex:1;">
                                    <div style="font-weight:700; font-size:13px;">✅ بله، لینک اشتراک هم دارم</div>
                                    <small style="color:var(--text-muted); font-size:11.5px;">دقیقاً مثل ربات: اول کانفیگ، بعد لینک اشتراکش. این گزینه فیلد لینک را نمایان می‌کند.</small>
                                </div>
                            </div>

                            
                            <div id="mode-single-fields">
                                <div class="form-group">
                                    <label class="form-label">کانفیگ</label>
                                    <textarea name="configs" rows="3" class="form-control single-cfg"
                                        style="direction:ltr; font-family:'JetBrains Mono', monospace; font-size:11.5px; line-height:1.7;"
                                        placeholder="vmess://... یا vless://... یا trojan://..."></textarea>
                                </div>
                                <div class="form-group sub-link-input" style="display:none;">
                                    <label class="form-label">لینک اشتراک (Subscription URL)</label>
                                    <input type="url" name="sub_links" class="form-control single-sub"
                                        style="direction:ltr; font-family:'JetBrains Mono', monospace; font-size:12px;"
                                        placeholder="https://sub.example.com/abc123">
                                    <small style="color:var(--text-muted); font-size:11px;">اگر این کانفیگ لینک اشتراک جداگانه دارد، اینجا وارد کنید. خالی بگذارید = ثبت بدون لینک.</small>
                                </div>
                            </div>

                            
                            <div id="mode-bulk-fields" style="display:none;">
                                <div class="form-group">
                                    <label class="form-label">کانفیگ‌ها (هر خط یک کانفیگ)</label>
                                    <textarea rows="8" class="form-control bulk-cfg"
                                        style="direction:ltr; font-family:'JetBrains Mono', monospace; font-size:11.5px; line-height:1.7;"
                                        placeholder="vmess://...&#10;vless://...&#10;trojan://..."></textarea>
                                </div>
                                <div class="form-group sub-link-input" style="display:none;">
                                    <label class="form-label">لینک‌های اشتراک (هر خط یک لینک، به ترتیب همان کانفیگ‌ها)</label>
                                    <textarea rows="8" class="form-control bulk-sub"
                                        style="direction:ltr; font-family:'JetBrains Mono', monospace; font-size:11.5px; line-height:1.7;"
                                        placeholder="https://sub.example.com/abc123&#10;https://sub.example.com/def456"></textarea>
                                    <small style="color:var(--text-muted); font-size:11px;">خط ۱ لینک با خط ۱ کانفیگ جفت می‌شود. خط لینک خالی = آن کانفیگ بدون لینک ذخیره می‌شود.</small>
                                </div>
                            </div>

                            <div class="modal-foot">
                                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-configs')">انصراف</button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                                    ثبت
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else:  ?>

                <div class="page-head">
                    <div>
                        <div class="page-head__title">
                            <?php echo icon('package', 'svg-icon svg-lg'); ?>
                            انبار شبکه ملی
                        </div>
                        <div class="page-head__sub">مدیریت انبارهای کانفیگ — هر انبار یک ترکیب پنل و محصول دارد</div>
                    </div>
                    <?php if (!$tableMissing): ?>
                    <div>
                        <button class="btn btn-primary" onclick="openModal('modal-add-shelf')">
                            <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                            <span>افزودن انبار</span>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($tableMissing): ?>
                    <div class="alert alert-warning">
                        <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                        <span>جدول‌های انبار هنوز ساخته نشده‌اند. یکبار از این صفحه خارج شده و دوباره وارد شوید — جدول‌ها به‌صورت خودکار توسط <code>lib/schema.php</code> ساخته می‌شوند.</span>
                    </div>
                <?php endif; ?>

                <?php if ($flash['ok']): ?><div class="alert alert-success"><?php echo icon('circle-check', 'svg-icon'); ?><span><?php echo htmlspecialchars($flash['ok'], ENT_QUOTES); ?></span></div><?php endif; ?>
                <?php if ($flash['err']): ?><div class="alert alert-danger"><?php echo icon('circle-exclamation', 'svg-icon'); ?><span><?php echo $flash['err']; ?></span></div><?php endif; ?>

                <?php if (!$tableMissing && empty($shelves)): ?>
                    <div class="card" style="text-align:center; padding:40px 20px;">
                        <?php echo icon('package', 'svg-icon svg-2xl'); ?>
                        <div style="margin-top:16px; color:var(--text-muted);">هنوز انباری ثبت نشده است.</div>
                        <button class="btn btn-primary" style="margin-top:14px;" onclick="openModal('modal-add-shelf')">افزودن اولین انبار</button>
                    </div>
                <?php elseif (!$tableMissing): ?>
                    <div class="stock-grid">
                        <?php foreach ($shelves as $sh):
                            $sid       = (int)$sh['id'];
                            $isActive  = ($sh['status'] === 'active');
                            $sourceTxt = $panelNameOf[(string)$sh['source_codepanel']] ?? ($sh['source_codepanel'] === 'auto' ? 'خودکار' : (string)$sh['source_codepanel']);
                            $cnt = $shelfCounts[$sid] ?? ['total'=>0,'active'=>0,'reserved'=>0,'delivered'=>0,'disabled'=>0];
                        ?>
                            <div class="shelf-card">
                                <div class="shelf-card__head">
                                    <div class="shelf-card__title"><?php echo htmlspecialchars($sh['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php if ($isActive): ?><span class="badge badge-active">فعال</span>
                                    <?php else: ?><span class="badge badge-block">غیرفعال</span><?php endif; ?>
                                </div>
                                <div class="shelf-card__body">
                                    <div style="margin-bottom:6px;"><span style="color:var(--text-muted)">محصول:</span> <b><?php echo htmlspecialchars($sh['product_name'] ?? '—', ENT_QUOTES); ?></b></div>
                                    <div style="margin-bottom:6px;"><span style="color:var(--text-muted)">پنل مبدأ:</span> <?php echo htmlspecialchars($sourceTxt, ENT_QUOTES); ?></div>
                                    <div style="margin-bottom:6px;"><span style="color:var(--text-muted)">حجم/مدت:</span> <?php echo (float)$sh['volume_gb']; ?> گیگ / <?php echo (int)$sh['service_days']; ?> روز</div>
                                    <div style="margin-bottom:6px;"><span style="color:var(--text-muted)">قیمت:</span> <?php echo number_format((int)$sh['price']); ?> تومان</div>

                                    <div class="stat-row">
                                        <div class="stat-chip">✅ موجود <strong><?php echo $cnt['active']; ?></strong></div>
                                        <?php if ($cnt['reserved']  > 0): ?><div class="stat-chip">🔒 <strong><?php echo $cnt['reserved']; ?></strong></div><?php endif; ?>
                                        <?php if ($cnt['delivered'] > 0): ?><div class="stat-chip">📦 <strong><?php echo $cnt['delivered']; ?></strong></div><?php endif; ?>
                                    </div>

                                    <div style="display:flex; gap:6px; flex-wrap:wrap; padding-top:8px; border-top: 1px dashed var(--border-soft);">
                                        <a href="stock.php?shelf=<?php echo $sid; ?>" class="btn btn-sm btn-primary">
                                            <?php echo icon('package', 'svg-icon'); ?>
                                            مدیریت کانفیگ‌ها
                                        </a>
                                        <form method="POST" action="stock.php" style="display:inline">
                                            <input type="hidden" name="_action" value="toggle_shelf">
                                            <input type="hidden" name="id" value="<?php echo $sid; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-warning" title="فعال/غیرفعال">
                                                <?php echo icon('rotate-left', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                        <form method="POST" action="stock.php" style="display:inline" onsubmit="return confirm('انبار «<?php echo htmlspecialchars($sh['name'], ENT_QUOTES); ?>» و <?php echo $cnt['total']; ?> کانفیگ آن حذف شوند؟');">
                                            <input type="hidden" name="_action" value="delete_shelf">
                                            <input type="hidden" name="id" value="<?php echo $sid; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                <?php echo icon('trash', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                
                <div id="modal-add-shelf" class="modal-overlay">
                    <div class="modal-box" style="max-width: 600px;">
                        <div class="modal-head">
                            <span class="modal-head__title">افزودن انبار جدید</span>
                            <button type="button" class="modal-close" onclick="closeModal('modal-add-shelf')">&times;</button>
                        </div>
                        <form method="POST" action="stock.php">
                            <input type="hidden" name="_action" value="add_shelf">

                            <div class="form-group">
                                <label class="form-label">نام انبار</label>
                                <input type="text" name="name" class="form-control" placeholder="مثلاً: انبار ۱۰ گیگ ۳۰ روزه" required maxlength="190">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">پنل مبدأ</label>
                                    <select name="source_codepanel" class="form-control">
                                        <option value="auto">خودکار (همه پنل‌ها)</option>
                                        <?php foreach ($panels as $p): ?>
                                            <option value="<?php echo htmlspecialchars($p['code_panel'], ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($p['name_panel'], ENT_QUOTES); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">پنل ذخیره</label>
                                    <select name="stock_codepanel" class="form-control">
                                        <option value="auto">خودکار</option>
                                        <?php foreach ($panels as $p): ?>
                                            <option value="<?php echo htmlspecialchars($p['code_panel'], ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($p['name_panel'], ENT_QUOTES); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">محصول مرتبط</label>
                                <select name="codeproduct" class="form-control" id="product-select">
                                    <option value="auto">خودکار</option>
                                    <?php foreach ($products as $p):
                                        $v = faoxima_product_volume($p);
                                        $d = faoxima_product_days($p);
                                        $pr = faoxima_product_price($p);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($p['code_product'] ?? '', ENT_QUOTES); ?>"
                                                data-volume="<?php echo $v; ?>" data-days="<?php echo $d; ?>" data-price="<?php echo $pr; ?>">
                                            <?php echo htmlspecialchars(faoxima_product_name($p), ENT_QUOTES); ?>
                                            (<?php echo $v; ?>گیگ — <?php echo $d; ?>روز)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color:var(--text-muted); font-size:11.5px;">با انتخاب محصول، فیلدهای زیر خودکار پر می‌شوند.</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">حجم (GB)</label>
                                    <input type="number" name="volume_gb" id="f-volume" step="0.5" min="0" class="form-control" value="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">مدت (روز)</label>
                                    <input type="number" name="service_days" id="f-days" min="0" class="form-control" value="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">قیمت (تومان)</label>
                                    <input type="number" name="price" id="f-price" min="0" class="form-control" value="0">
                                </div>
                            </div>

                            <div class="modal-foot">
                                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-shelf')">انصراف</button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                                    افزودن انبار
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </section>
</section>

<script>
function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

var sel = document.getElementById('product-select');
if (sel) {
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || opt.value === 'auto') return;
        document.getElementById('f-volume').value = opt.dataset.volume || 0;
        document.getElementById('f-days').value   = opt.dataset.days   || 0;
        document.getElementById('f-price').value  = opt.dataset.price  || 0;
    });
}


var addCfgForm = document.getElementById('add-configs-form');
if (addCfgForm) {
    var singleBlock = document.getElementById('mode-single-fields');
    var bulkBlock   = document.getElementById('mode-bulk-fields');
    var singleCfg   = addCfgForm.querySelector('.single-cfg');
    var singleSub   = addCfgForm.querySelector('.single-sub');
    var bulkCfg     = addCfgForm.querySelector('.bulk-cfg');
    var bulkSub     = addCfgForm.querySelector('.bulk-sub');
    var subToggle   = document.getElementById('toggle-has-sub');
    var subBlocks   = addCfgForm.querySelectorAll('.sub-link-input');
    var modePills   = addCfgForm.querySelectorAll('.mode-pill');

    function applyMode(mode) {
        var isSingle = mode === 'single';
        singleBlock.style.display = isSingle ? '' : 'none';
        bulkBlock.style.display   = isSingle ? 'none' : '';

        modePills.forEach(function (p) {
            var active = p.getAttribute('data-mode') === mode;
            p.style.borderColor = active ? 'var(--accent)' : 'var(--border-color)';
            p.style.background  = active ? 'var(--surface-2)' : '';
        });
    }
    function applySubToggle() {
        var on = !!(subToggle && subToggle.checked);
        subBlocks.forEach(function (b) { b.style.display = on ? '' : 'none'; });
    }
    addCfgForm.querySelectorAll('input[name="entry_mode"]').forEach(function (r) {
        r.addEventListener('change', function () { applyMode(r.value); });
    });
    modePills.forEach(function (p) {
        p.addEventListener('click', function () {
            var r = p.querySelector('input[type=radio]');
            if (r) { r.checked = true; applyMode(p.getAttribute('data-mode')); }
        });
    });
    if (subToggle) subToggle.addEventListener('change', applySubToggle);


    var initialMode = addCfgForm.querySelector('input[name="entry_mode"]:checked');
    applyMode(initialMode ? initialMode.value : 'single');
    applySubToggle();


    addCfgForm.addEventListener('submit', function (ev) {
        var mode = (addCfgForm.querySelector('input[name="entry_mode"]:checked') || {}).value || 'single';
        if (mode === 'bulk') {

            singleCfg.value = bulkCfg ? bulkCfg.value : '';


            if (subToggle && subToggle.checked && bulkSub) {
                var ta = document.createElement('textarea');
                ta.name = 'sub_links';
                ta.style.display = 'none';
                ta.value = bulkSub.value;
                addCfgForm.appendChild(ta);
                if (singleSub) singleSub.disabled = true;
            } else if (singleSub) {
                singleSub.value = '';
            }
        } else {

            if (singleCfg) singleCfg.value = singleCfg.value.trim();
            if (!(subToggle && subToggle.checked) && singleSub) singleSub.value = '';
        }

        var configsVal = (mode === 'bulk' ? (bulkCfg ? bulkCfg.value : '') : (singleCfg ? singleCfg.value : '')).trim();
        if (configsVal === '') {
            ev.preventDefault();
            alert('لطفاً حداقل یک کانفیگ وارد کنید.');
            return false;
        }
    });
}
</script>

</body>
</html>


