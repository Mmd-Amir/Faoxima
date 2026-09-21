<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/remaining.php';
require_once __DIR__ . '/lib/item_parser.php';
require_once __DIR__ . '/../re/rx/function/nm_stock.php';

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
    } catch (\Throwable $e) {
    }
}
$tableMissing = !($tables['nm_stock_shelves'] && $tables['nm_config_stock']);

$invoiceTableMissing = true;
try {
    $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice' LIMIT 1");
    $r->execute();
    $invoiceTableMissing = !(bool)$r->fetchColumn();
} catch (\Throwable $e) {
}

$flash = ['ok' => '', 'err' => ''];

function faoxima_stock_days_qs(): string
{
    $raw = isset($_POST['days']) ? trim((string)$_POST['days']) : '';
    if ($raw === '' || !ctype_digit($raw)) return '';
    return '&days=' . (int)$raw . '&dmode=' . urlencode(faoxima_remaining_dmode($_POST['dmode'] ?? ''));
}


$panels = [];
try {
    $r = $pdo->query("SELECT code_panel, name_panel, type, national_net_status FROM marzban_panel ORDER BY name_panel ASC");
    if ($r) $panels = $r->fetchAll(PDO::FETCH_ASSOC);
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

$nationalPanels = array_values(array_filter($panels, 'nmPanelNationalEnabled'));
$nationalNetActive = !empty($nationalPanels);

$productByCode = [];
foreach ($products as $p) {
    $code = (string)($p['code_product'] ?? '');
    if ($code !== '') $productByCode[$code] = $p;
}


function faoxima_product_name(array $p): string
{
    return (string)($p['name_product'] ?? $p['Name_product'] ?? '');
}
function faoxima_product_volume(array $p): float
{
    if (isset($p['Volume_constraint'])) return (float)$p['Volume_constraint'];
    if (isset($p['volume_product']))    return (float)$p['volume_product'];
    return 0.0;
}
function faoxima_product_days(array $p): int
{
    if (isset($p['Service_time'])) return (int)$p['Service_time'];
    if (isset($p['time_product'])) return (int)$p['time_product'];
    return 0;
}
function faoxima_stock_delete_linked_invoices(PDO $pdo, array $where): void
{
    $sql = "SELECT assigned_invoice FROM nm_config_stock WHERE status IN ('reserved','delivered') AND assigned_invoice IS NOT NULL AND assigned_invoice <> ''";
    $params = [];
    if (isset($where['shelf_id'])) {
        $sql .= " AND shelf_id = :shelf_id";
        $params[':shelf_id'] = (int)$where['shelf_id'];
    }
    if (isset($where['id'])) {
        $sql .= " AND id = :id";
        $params[':id'] = (int)$where['id'];
    }
    if (isset($where['ids']) && is_array($where['ids']) && $where['ids']) {
        $ph = implode(',', array_fill(0, count($where['ids']), '?'));
        $sql .= " AND id IN ({$ph})";
    }
    if (isset($where['status'])) {
        $sql .= " AND status = :status";
        $params[':status'] = (string)$where['status'];
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
        $invoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (\Throwable $e) {
        return;
    }
    $invoiceIds = array_values(array_unique(array_filter(array_map('trim', $invoiceIds), static function ($v) {
        return $v !== '';
    })));
    if (!$invoiceIds) return;
    $ph = implode(',', array_fill(0, count($invoiceIds), '?'));
    try {
        $del = $pdo->prepare("DELETE FROM invoice WHERE id_invoice IN ({$ph})");
        $del->execute($invoiceIds);
    } catch (\Throwable $e) {
    }
}
function faoxima_product_price(array $p): int
{
    if (isset($p['Price_product'])) return (int)$p['Price_product'];
    if (isset($p['price_product'])) return (int)$p['price_product'];
    return 0;
}


if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add_shelf') {
        $name          = trim((string)($_POST['name']             ?? ''));
        $selectedPanel = trim((string)($_POST['source_codepanel'] ?? ''));
        $codeProduct   = trim((string)($_POST['codeproduct']      ?? ''));

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
        if (empty($nationalPanels))                  $errors[] = 'هیچ پنلی با وضعیت شبکه ملی فعال یافت نشد. امکان افزودن انبار وجود ندارد.';
        if ($name === '')                            $errors[] = 'نام انبار خالی است.';
        elseif (mb_strlen($name, 'UTF-8') > 190)     $errors[] = 'نام انبار بیش از حد طولانی است.';
        if ($volumeGb < 0    || $volumeGb > 100000)  $errors[] = 'حجم نامعتبر است.';
        if ($serviceDays < 0 || $serviceDays > 36500) $errors[] = 'مدت سرویس نامعتبر است.';
        if ($price < 0)                              $errors[] = 'قیمت نمی‌تواند منفی باشد.';

        $validNationalCodes = [];
        foreach ($nationalPanels as $pp) $validNationalCodes[(string)$pp['code_panel']] = true;
        if (!isset($validNationalCodes[$selectedPanel])) $errors[] = 'پنل انتخاب‌شده نامعتبر است.';

        if ($codeProduct === '') {
            $errors[] = 'انتخاب محصول الزامی است.';
        } elseif (isset($validNationalCodes[$selectedPanel])) {
            $selectedPanelName = $panelNameOf[$selectedPanel] ?? '';
            if (!isset($productByCode[$codeProduct])) {
                $errors[] = 'محصول انتخاب‌شده نامعتبر است.';
            } else {
                $prodLocation = (string)($productByCode[$codeProduct]['Location'] ?? '');
                if ($prodLocation !== '/all' && $prodLocation !== $selectedPanelName) {
                    $errors[] = 'محصول انتخاب‌شده متعلق به پنل انتخابی نیست.';
                }
            }
        }

        $sourcePanel = $selectedPanel;
        $stockPanel  = $selectedPanel;

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
        } else {
            try {
                $dup = $pdo->prepare("SELECT 1 FROM nm_stock_shelves WHERE name = :n AND source_codepanel = :sp LIMIT 1");
                $dup->execute([':n' => $name, ':sp' => $sourcePanel]);
                if ($dup->fetchColumn()) {
                    $flash['err'] = 'انباری با همین نام و پنل مبدأ از قبل وجود دارد.';
                }
            } catch (\Throwable $e) {
            }

            if (!$flash['err']) {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO nm_stock_shelves
                         (name, source_codepanel, stock_codepanel, codeproduct, product_name,
                          volume_gb, service_days, price, status, created_at)
                         VALUES (:n, :sp, :tp, :cp, :pn, :v, :d, :p, 'active', :t)"
                    );
                    $stmt->execute([
                        ':n'  => $name,
                        ':sp' => $sourcePanel,
                        ':tp' => $stockPanel,
                        ':cp' => $codeProduct,
                        ':pn' => $productName,
                        ':v'  => $volumeGb,
                        ':d'  => $serviceDays,
                        ':p' => $price,
                        ':t'  => time(),
                    ]);
                    $flash['ok'] = 'انبار جدید ثبت شد.';
                } catch (\Throwable $e) {
                    $flash['err'] = 'افزودن ناموفق: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete_shelf') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                faoxima_stock_delete_linked_invoices($pdo, ['shelf_id' => $id]);
                $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM nm_stock_shelves WHERE id = :id")->execute([':id' => $id]);
                $flash['ok'] = 'انبار و کانفیگ‌های آن حذف شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'add_configs') {
        $shelfId    = (int)($_POST['shelf_id'] ?? 0);
        $configsRaw = (string)($_POST['configs']   ?? '');
        $itemsJson  = (string)($_POST['items_json'] ?? '');
        $entryMode  = (string)($_POST['entry_mode'] ?? 'single');
        $entryMode  = ($entryMode === 'bulk') ? 'bulk' : 'single';
        $payload    = ($itemsJson !== '') ? $itemsJson : $configsRaw;

        $shelf = null;
        try {
            $sh = $pdo->prepare("SELECT * FROM nm_stock_shelves WHERE id = :id");
            $sh->execute([':id' => $shelfId]);
            $shelf = $sh->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
        }

        if (!$shelf) {
            $flash['err'] = 'انبار انتخابی پیدا نشد.';
        } elseif (trim($payload) === '') {
            $flash['err'] = 'هیچ کانفیگی وارد نشده است.';
        } else {
            $entries = faoxima_pair_configs_and_links($payload);

            if (empty($entries)) {
                $flash['err'] = 'هیچ کانفیگی وارد نشده است.';
            } else {
                $tooManyInSingleMode = false;
                if ($entryMode === 'single' && count($entries) > 1) {
                    $tooManyInSingleMode = true;
                    $flash['err'] = 'در حالت «تکی» فقط یک کانفیگ (یا یک کانفیگ همراه با لینکِ اشتراکش) مجاز است. شما ' . count($entries) . ' آیتم وارد کرده‌اید. برای ثبت چند کانفیگ با هم، از حالت «دسته‌ای» استفاده کنید.';
                }

                if (!$tooManyInSingleMode) {
                    $ok = 0;
                    $duplicate = 0;
                    $bad = 0;
                    $badSub = 0;
                    foreach ($entries as $entry) {
                        $cfg = $entry['cfg'];
                        $sub = $entry['sub'] !== '' ? $entry['sub'] : null;

                        if ($cfg === '' && $sub === null) {
                            $bad++;
                            continue;
                        }
                        if ($cfg !== '' && mb_strlen($cfg, 'UTF-8') < 8) {
                            $bad++;
                            continue;
                        }

                        try {
                            $fmt = 'text';
                            if ($cfg === '' && $sub !== null)                                                                      $fmt = 'subscription';
                            elseif (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard):\/\//i', $cfg))           $fmt = 'single';
                            elseif (stripos($cfg, '[Interface]') !== false || stripos($cfg, 'PrivateKey') !== false)                 $fmt = 'wireguard';

                            $tier = 'auto';
                            $extractedVol = null;
                            $targetText = $cfg !== '' ? $cfg : ($sub ?? '');
                            if (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*(?:gb|g|گیگ|گیگابایت)(?:[^0-9]|$)/iu', $targetText, $m)) {
                                $extractedVol = (int)$m[1];
                            } elseif (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*[-_]\s*([1-9][0-9]{0,2})(?:[^0-9]|$)/u', $targetText, $m)) {
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

                    $parts = ["ثبت‌شده: {$ok}"];
                    if ($duplicate > 0) $parts[] = "تکراری: {$duplicate}";
                    if ($bad       > 0) $parts[] = "نامعتبر: {$bad}";
                    if ($badSub    > 0) $parts[] = "لینک اشتراک نامعتبر (کانفیگ ثبت شد): {$badSub}";
                    $msg = 'نتیجه ورود کانفیگ‌ها — ' . implode(' · ', $parts);
                    if ($ok === 0 && $duplicate > 0) {
                        $flash['err'] = $msg . ' (این کانفیگ‌ها از قبل در انبار موجودند)';
                    } else {
                        $flash['ok'] = $msg;
                    }
                    header('Location: stock.php?shelf=' . $shelfId);
                    exit;
                }
            }
        }
    } elseif ($action === 'delete_config_hard') {

        $configId = (int)($_POST['config_id'] ?? 0);
        $shelfId  = (int)($_POST['shelf_id']  ?? 0);
        $configDeleted = false;
        if ($configId > 0) {
            try {
                faoxima_stock_delete_linked_invoices($pdo, ['id' => $configId]);
                $del = $pdo->prepare("DELETE FROM nm_config_stock WHERE id = :id");
                $del->execute([':id' => $configId]);
                $configDeleted = $del->rowCount() > 0;
                $flash['ok'] = $configDeleted ? 'کانفیگ برای همیشه از دیتابیس حذف شد.' : 'کانفیگ پیدا نشد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
        $redir = 'stock.php?shelf=' . $shelfId;
        if (!empty($_POST['cstatus'])) $redir .= '&cstatus=' . urlencode((string)$_POST['cstatus']);
        if (!empty($_POST['cpage']))   $redir .= '&cpage=' . (int)$_POST['cpage'];
        $redir .= faoxima_stock_days_qs();
        if ($configDeleted) $redir .= '&bulkclear=1';
        if ($shelfId > 0) {
            header('Location: ' . $redir);
            exit;
        }
    } elseif ($action === 'stock_bulk_delete') {

        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array)($_POST['ids'] ?? [])),
            static function ($v) {
                return $v > 0;
            }
        )));
        $ids = array_slice($ids, 0, 500);

        if (!$ids) {
            $flash['err'] = 'موردی برای حذف انتخاب نشده بود.';
        } elseif ($shelfId <= 0) {
            $flash['err'] = 'انبار مشخص نشده است.';
        } else {
            try {
                faoxima_stock_delete_linked_invoices($pdo, ['shelf_id' => $shelfId, 'ids' => $ids]);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $del = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = ? AND id IN ({$ph})");
                $del->execute(array_merge([$shelfId], $ids));
                $n = $del->rowCount();
                $flash['ok'] = $n > 0
                    ? $n . ' کانفیگ برای همیشه از دیتابیس حذف شد.'
                    : 'هیچ کانفیگی حذف نشد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف گروهی ناموفق: ' . $e->getMessage();
            }
        }

        $redir = 'stock.php?shelf=' . $shelfId;
        if (!empty($_POST['cstatus'])) $redir .= '&cstatus=' . urlencode((string)$_POST['cstatus']);
        if (!empty($_POST['cpage']))   $redir .= '&cpage=' . (int)$_POST['cpage'];
        $redir .= faoxima_stock_days_qs();
        if (!empty($n)) $redir .= '&bulkclear=1';
        if ($shelfId > 0) {
            header('Location: ' . $redir);
            exit;
        }
    } elseif ($action === 'delete_filtered_configs') {
        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        $cfgFilter = (string)($_POST['cstatus'] ?? 'all');
        $allowed = ['active', 'reserved', 'delivered', 'disabled'];
        $filteredDeleted = 0;
        if ($shelfId > 0) {
            try {
                if ($cfgFilter !== 'all' && in_array($cfgFilter, $allowed, true)) {
                    faoxima_stock_delete_linked_invoices($pdo, ['shelf_id' => $shelfId, 'status' => $cfgFilter]);
                    $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id AND status = :st");
                    $stmt->execute([':id' => $shelfId, ':st' => $cfgFilter]);
                } else {
                    faoxima_stock_delete_linked_invoices($pdo, ['shelf_id' => $shelfId]);
                    $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id");
                    $stmt->execute([':id' => $shelfId]);
                }
                $filteredDeleted = $stmt->rowCount();
                $flash['ok'] = $filteredDeleted . ' کانفیگ برای همیشه از دیتابیس حذف شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
            $redir = 'stock.php?shelf=' . $shelfId . '&cstatus=' . urlencode($cfgFilter);
            if ($filteredDeleted > 0) $redir .= '&bulkclear=1';
            header('Location: ' . $redir);
            exit;
        }
    } elseif ($action === 'delete_active_configs') {

        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        $activeDeleted = 0;
        if ($shelfId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id AND status = 'active'");
                $stmt->execute([':id' => $shelfId]);
                $activeDeleted = $stmt->rowCount();
                $flash['ok'] = $activeDeleted . ' کانفیگ فعال برای همیشه از دیتابیس حذف شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
            $redir = 'stock.php?shelf=' . $shelfId . faoxima_stock_days_qs();
            if ($activeDeleted > 0) $redir .= '&bulkclear=1';
            header('Location: ' . $redir);
            exit;
        }
    }
}


$viewShelfId   = isset($_GET['shelf']) ? (int)$_GET['shelf'] : 0;
$selectedShelf = null;
$shelfCounts   = [];
$remainingOf   = [];
$scanTruncated = false;

$daysRaw    = isset($_GET['days']) ? trim((string)$_GET['days']) : '';
$daysFilter = ($daysRaw !== '' && ctype_digit($daysRaw)) ? (int)$daysRaw : null;
$daysMode   = faoxima_remaining_dmode($_GET['dmode'] ?? '');
$daysActive = ($daysFilter !== null && !$invoiceTableMissing);


function faoxima_stock_resolve_remaining(PDO $pdo, array $rows, int $shelfDays, bool $invoiceMissing): array
{
    $out = [];
    $sold = ['reserved' => true, 'delivered' => true];

    $invIds = [];
    foreach ($rows as $r) {
        if (!isset($sold[(string)($r['status'] ?? '')])) continue;
        $iv = trim((string)($r['assigned_invoice'] ?? ''));
        if ($iv !== '') $invIds[] = $iv;
    }
    $inv = ($invIds && !$invoiceMissing) ? faoxima_remaining_fetch_by_invoice($pdo, $invIds) : [];

    foreach ($rows as $r) {
        $id = (int)$r['id'];
        if (!isset($sold[(string)($r['status'] ?? '')])) {
            $out[$id] = null;
            continue;
        }

        $iv        = trim((string)($r['assigned_invoice'] ?? ''));
        $invRow    = ($iv !== '' && isset($inv[$iv])) ? $inv[$iv] : null;
        $delivered = (int)($r['delivered_at'] ?? 0);
        $sellTs    = $delivered > 0 ? $delivered : ($invRow['time_sell'] ?? null);
        if ($sellTs === null || $sellTs === '') {
            $out[$id] = null;
            continue;
        }

        $uname    = $r['assigned_user'] ?? ($invRow['username'] ?? '');
        $prodName = $invRow['name_product'] ?? '';
        $out[$id] = faoxima_remaining_days($sellTs, $shelfDays, $uname, $prodName);
    }
    return $out;
}

if (!$tableMissing) {
    try {
        $staleReservedBefore = time() - 600;
        $pdo->exec("UPDATE nm_config_stock SET status='active', assigned_user='', assigned_invoice='', assigned_mode='', reserved_at=NULL WHERE status='reserved' AND (reserved_at IS NULL OR reserved_at < {$staleReservedBefore})");
    } catch (\Throwable $e) {
    }
}
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
                    $shelfCounts[$sid] = ['total' => 0, 'active' => 0, 'reserved' => 0, 'delivered' => 0, 'disabled' => 0];
                }
                if (isset($shelfCounts[$sid][$st])) $shelfCounts[$sid][$st] = (int)$row['cnt'];
                $shelfCounts[$sid]['total'] += (int)$row['cnt'];
            }
        }
    } catch (\Throwable $e) {
    }
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
            if ((int)$sh['id'] === $viewShelfId) {
                $selectedShelf = $sh;
                break;
            }
        }
        if ($selectedShelf) {
            $allowedStatuses = ['active', 'reserved', 'delivered', 'disabled'];
            $cfgFilter = isset($_GET['cstatus']) ? (string)$_GET['cstatus'] : 'all';
            if ($cfgFilter !== 'all' && !in_array($cfgFilter, $allowedStatuses, true)) $cfgFilter = 'all';
            $cfgPerPage = 500;
            $cfgPage = isset($_GET['cpage']) ? max(1, (int)$_GET['cpage']) : 1;

            $cfgQ = trim((string)($_GET['q'] ?? ''));

            $whereSql = "shelf_id = :id";
            $params = [':id' => $viewShelfId];
            if ($cfgFilter !== 'all') {
                $whereSql .= " AND status = :st";
                $params[':st'] = $cfgFilter;
            }
            if ($cfgQ !== '') {
                $whereSql .= " AND (content LIKE :q1 OR assigned_user LIKE :q2 OR sub_link LIKE :q3)";
                $cfgLike = '%' . $cfgQ . '%';
                $params[':q1'] = $cfgLike;
                $params[':q2'] = $cfgLike;
                $params[':q3'] = $cfgLike;
            }

            $cfgTotal = 0;
            $shelfDays = (int)($selectedShelf['service_days'] ?? 0);

            if (!$daysActive) {
                try {
                    $cnt = $pdo->prepare("SELECT COUNT(*) FROM nm_config_stock WHERE {$whereSql}");
                    $cnt->execute($params);
                    $cfgTotal = (int)$cnt->fetchColumn();
                } catch (\Throwable $e) {
                }
                $cfgPages = max(1, (int)ceil($cfgTotal / $cfgPerPage));
                if ($cfgPage > $cfgPages) $cfgPage = $cfgPages;
                $cfgOffset = ($cfgPage - 1) * $cfgPerPage;

                try {
                    $r = $pdo->prepare("SELECT * FROM nm_config_stock WHERE {$whereSql} ORDER BY id DESC LIMIT {$cfgPerPage} OFFSET {$cfgOffset}");
                    $r->execute($params);
                    $shelfConfigs = $r->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                }
                $remainingOf = faoxima_stock_resolve_remaining($pdo, $shelfConfigs, $shelfDays, $invoiceTableMissing);
            } else {
                $scanLimit = FAOXIMA_REMAINING_SCAN_CAP + 1;
                $scanned = [];
                try {
                    $r = $pdo->prepare("SELECT * FROM nm_config_stock WHERE {$whereSql} ORDER BY id DESC LIMIT {$scanLimit}");
                    $r->execute($params);
                    $scanned = $r->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                }
                if (count($scanned) > FAOXIMA_REMAINING_SCAN_CAP) {
                    $scanTruncated = true;
                    $scanned = array_slice($scanned, 0, FAOXIMA_REMAINING_SCAN_CAP);
                }

                $scannedRem = faoxima_stock_resolve_remaining($pdo, $scanned, $shelfDays, $invoiceTableMissing);
                $matched = [];
                foreach ($scanned as $row) {
                    $rem = $scannedRem[(int)$row['id']] ?? null;
                    if (faoxima_remaining_matches($rem, $daysMode, $daysFilter)) $matched[] = $row;
                }

                $cfgTotal = count($matched);
                $cfgPages = max(1, (int)ceil($cfgTotal / $cfgPerPage));
                if ($cfgPage > $cfgPages) $cfgPage = $cfgPages;
                $shelfConfigs = array_slice($matched, ($cfgPage - 1) * $cfgPerPage, $cfgPerPage);
                foreach ($shelfConfigs as $row) {
                    $remainingOf[(int)$row['id']] = $scannedRem[(int)$row['id']] ?? null;
                }
            }
        }
    }
}

function faoxima_stock_status_label(string $s): array
{
    switch ($s) {
        case 'active':
            return [icon('circle-check', 'svg-icon svg-sm') . ' موجود', 'badge-active'];
        case 'reserved':
            return [icon('lock', 'svg-icon svg-sm') . ' رزرو', 'badge-warning'];
        case 'delivered':
            return [icon('package', 'svg-icon svg-sm') . ' تحویل شده', 'badge-info'];
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
    <title>انبار شبکه ملی | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat33">
    <link rel="stylesheet" href="css/components.css?v=flat33">
    <script src="js/money-input.js?v=fx1" defer></script>
    <script src="js/theme.js?v=flat50" defer></script>
    <style>
        .stock-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
            gap: var(--space-4);
        }

        .shelf-card {
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius);
            overflow: hidden;
            transition: box-shadow .15s, border-color .15s;
        }

        .shelf-card:hover {
            border-color: var(--border-mid);
            box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
        }

        .shelf-card__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            background: rgba(0, 0, 0, .05);
            border-bottom: 1px solid var(--border-soft);
        }

        .shelf-card__title {
            font-size: var(--text-md);
            font-weight: 700;
            line-height: 1.5;
        }

        .shelf-card__body {
            display: flex;
            flex-direction: column;
            gap: 7px;
            padding: var(--space-4);
            font-size: var(--text-sm);
            line-height: 1.6;
        }

        .shelf-card__body>div {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .shelf-card__body .svg-icon {
            margin-inline-start: 0;
            flex-shrink: 0;
        }

        .shelf-card__body .svg-icon+* {
            margin-inline-start: 0;
        }

        .shelf-card__key {
            color: var(--text-muted);
        }

        .shelf-card__stats {
            margin: var(--space-2) 0;
        }

        .shelf-card__foot {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            padding-top: var(--space-3);
            border-top: 1px dashed var(--border-soft);
        }

        .shelf-card__foot form {
            margin: 0;
        }

        .shelf-card__manage {
            flex: 1 1 auto;
            justify-content: center;
        }

        .config-row {
            padding: var(--space-4);
            margin-bottom: var(--space-3);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius);
            background: var(--surface-2);
            transition: border-color .15s;
        }

        .config-row:hover {
            border-color: var(--border-mid);
        }

        .config-row--disabled {
            opacity: .62;
        }

        .config-row__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: var(--space-3);
            margin-bottom: var(--space-3);
        }

        .config-row__info {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-2);
            flex: 1 1 auto;
            min-width: 0;
            font-size: var(--text-md);
            font-weight: 700;
            line-height: 1.5;
            word-break: break-word;
        }

        .config-row__actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-2);
            flex-shrink: 0;
        }

        .config-row__actions form {
            margin: 0;
        }

        .config-meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border: 1px solid var(--border-soft);
            border-radius: 100px;
            background: var(--surface-3);
            color: var(--text-muted);
            font-size: var(--text-xs);
            font-weight: 500;
            line-height: 1.5;
            white-space: nowrap;
        }

        .config-meta-tag .svg-icon,
        .config-meta-tag svg {
            width: 12px;
            height: 12px;
            flex-shrink: 0;
            margin-inline-start: 0;
        }

        .config-meta-tag .svg-icon+* {
            margin-inline-start: 0;
        }

        .config-meta-tag--accent {
            color: var(--accent);
            border-color: var(--accent-mid);
            background: var(--accent-soft);
        }

        .config-content-wrap {
            margin-top: var(--space-1);
            padding: var(--space-2) var(--space-3);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-sm);
            background: var(--surface-3);
        }

        .config-content {
            max-height: 56px;
            overflow: hidden;
            direction: ltr;
            text-align: left;
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            line-height: 1.6;
            word-break: break-all;
        }

        .config-sub-link {
            margin-top: 7px;
            padding-top: 7px;
            border-top: 1px dashed var(--border-soft);
            direction: ltr;
            text-align: left;
            color: var(--accent);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            word-break: break-all;
        }

        .entry-mode-error {
            align-items: flex-start;
        }

        .entry-mode-error span {
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            .stock-grid {
                grid-template-columns: 1fr;
            }

            .config-row {
                padding: var(--space-3);
            }

            .config-row__head {
                flex-direction: column;
                align-items: stretch;
                gap: var(--space-3);
            }

            .config-row__actions {
                width: 100%;
                justify-content: flex-end;
                gap: var(--space-2);
            }

            .config-row__actions form {
                flex: 1 1 auto;
                margin: 0;
            }

            .config-row__actions .btn {
                width: 100%;
                justify-content: center;
            }

            .config-row__meta {
                gap: 6px var(--space-3);
            }

            .shelf-card__foot .btn {
                flex: 1 1 auto;
                justify-content: center;
            }
        }
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
                                <a href="stock.php" class="page-head__back"><?php echo icon('arrow-left', 'svg-icon svg-sm'); ?></a>
                                <?php echo icon('package', 'svg-icon svg-lg'); ?>
                                <?php echo htmlspecialchars($selectedShelf['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="page-head__sub">
                                محصول: <b><?php echo htmlspecialchars($selectedShelf['product_name'] ?? '—', ENT_QUOTES); ?></b>
                                · حجم: <?php echo ((float)$selectedShelf['volume_gb'] === 0.0) ? 'نامحدود' : (float)$selectedShelf['volume_gb'] . ' گیگ'; ?>
                                · مدت: <?php echo (int)$selectedShelf['service_days']; ?> روز
                                · قیمت: <?php echo number_format((int)$selectedShelf['price']); ?> T
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

                    <?php $counts = $shelfCounts[$viewShelfId] ?? ['total' => 0, 'active' => 0, 'reserved' => 0, 'delivered' => 0, 'disabled' => 0]; ?>
                    <div class="stat-row">
                        <div class="stat-chip">کل: <strong><?php echo $counts['total']; ?></strong></div>
                        <div class="stat-chip stat-chip--success"><?php echo icon('circle-check', 'svg-icon svg-sm'); ?> موجود: <strong><?php echo $counts['active']; ?></strong></div>
                        <div class="stat-chip stat-chip--accent"><?php echo icon('package', 'svg-icon svg-sm'); ?> تحویل شده: <strong><?php echo $counts['delivered']; ?></strong></div>
                        <?php if ($counts['reserved'] > 0): ?><div class="stat-chip stat-chip--warning"><?php echo icon('lock', 'svg-icon svg-sm'); ?> رزرو: <strong><?php echo $counts['reserved']; ?></strong></div><?php endif; ?>
                    </div>

                    <?php $cfgFilter = $cfgFilter ?? 'all';
                    $cfgPage = $cfgPage ?? 1;
                    $cfgPages = $cfgPages ?? 1;
                    $cfgTotal = $cfgTotal ?? 0;
                    $shelfDays = $shelfDays ?? 0; ?>
                    <?php

                    $cfgQ = $cfgQ ?? '';
                    $stockQs = function (array $over = []) use ($viewShelfId, $cfgFilter, $daysFilter, $daysMode, $cfgQ) {
                        $qp = ['shelf' => $viewShelfId, 'cstatus' => $cfgFilter];
                        if ($cfgQ !== '') $qp['q'] = $cfgQ;
                        if ($daysFilter !== null) {
                            $qp['days'] = $daysFilter;
                            $qp['dmode'] = $daysMode;
                        }
                        foreach ($over as $k => $v) {
                            if ($v === null || $v === '') unset($qp[$k]);
                            else $qp[$k] = $v;
                        }
                        return htmlspecialchars(http_build_query($qp), ENT_QUOTES);
                    };
                    ?>
                    <?php if ($daysFilter !== null && $invoiceTableMissing): ?>
                        <div class="alert alert-warning"><?php echo icon('circle-exclamation', 'svg-icon'); ?><span>جدول فاکتورها (invoice) در دسترس نیست، بنابراین فیلتر روزهای باقی‌مانده اعمال نشد.</span></div>
                    <?php endif; ?>
                    <?php if ($scanTruncated): ?>
                        <div class="alert alert-warning"><?php echo icon('circle-exclamation', 'svg-icon'); ?><span>برای این فیلتر فقط <?php echo (int)FAOXIMA_REMAINING_SCAN_CAP; ?> کانفیگ آخر بررسی شد. ممکن است موارد قدیمی‌تری هم مطابق فیلتر باشند که در این لیست دیده نمی‌شوند.</span></div>
                    <?php endif; ?>
                    <?php
                    $daysHiddenBar = '';
                    if ($daysFilter !== null) {
                        $daysHiddenBar = '<input type="hidden" name="days" value="' . (int)$daysFilter . '">'
                            . '<input type="hidden" name="dmode" value="' . htmlspecialchars($daysMode, ENT_QUOTES) . '">';
                    }
                    ?>
                    <div class="cfg-filterbar">
                        <?php // Row 0 — text search
                        ?>
                        <form method="GET" action="stock.php" class="cfg-filterbar__row">
                            <input type="hidden" name="shelf" value="<?php echo $viewShelfId; ?>">
                            <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($cfgFilter, ENT_QUOTES); ?>">
                            <?php if ($daysFilter !== null): ?>
                                <input type="hidden" name="days" value="<?php echo (int)$daysFilter; ?>">
                                <input type="hidden" name="dmode" value="<?php echo htmlspecialchars($daysMode, ENT_QUOTES); ?>">
                            <?php endif; ?>
                            <input type="search" name="q" class="form-control" style="flex:1 1 240px; min-width:0;" placeholder="جستجو در محتوا، کاربر یا لینک سابسکریپشن…" value="<?php echo htmlspecialchars($cfgQ, ENT_QUOTES); ?>">
                            <button type="submit" class="btn btn-sm btn-primary"><?php echo icon('search', 'svg-icon svg-sm'); ?> جستجو</button>
                            <?php if ($cfgQ !== ''): ?><a href="stock.php?<?php echo $stockQs(['q' => null, 'cpage' => null]); ?>" class="btn btn-sm btn-outline">حذف فیلتر</a><?php endif; ?>
                        </form>
                        <?php // Row 1 — status segments
                        ?>
                        <div class="cfg-filterbar__row">
                            <span class="cfg-filterbar__label"><?php echo icon('filter', 'svg-icon svg-sm'); ?> وضعیت:</span>
                            <div class="cfg-seg" role="group" aria-label="فیلتر وضعیت">
                                <?php
                                $cfgFilters = [
                                    'all'       => 'همه',
                                    'active'    => icon('circle-check', 'svg-icon svg-sm') . ' موجود',
                                    'delivered' => icon('package', 'svg-icon svg-sm') . ' تحویل شده',
                                ];
                                foreach ($cfgFilters as $fk => $fl):
                                    $isActive = ($cfgFilter === $fk);
                                ?>
                                    <a href="stock.php?<?php echo $stockQs(['cstatus' => $fk, 'cpage' => null]); ?>"
                                        class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-outline'; ?>"
                                        <?php echo $isActive ? 'aria-current="true"' : ''; ?>><?php echo $fl; ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php // Row 2 — remaining-days range filter 
                        ?>
                        <div class="cfg-filterbar__row">
                            <form method="GET" action="stock.php" class="cfg-daysfilter">
                                <input type="hidden" name="shelf" value="<?php echo $viewShelfId; ?>">
                                <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($cfgFilter, ENT_QUOTES); ?>">
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
                                    <a class="btn btn-sm btn-outline" title="حذف فیلتر روز"
                                        href="stock.php?<?php echo $stockQs(['days' => null, 'dmode' => null, 'cpage' => null]); ?>">
                                        <?php echo icon('xmark', 'svg-icon svg-sm'); ?> پاک کردن
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <?php // Row 3 — bulk maintenance actions, isolated from the filters 
                        ?>
                        <div class="cfg-filterbar__row cfg-filterbar__row--split">
                            <span class="cfg-filterbar__label"><?php echo icon('trash', 'svg-icon svg-sm'); ?> عملیات گروهی:</span>
                            <div class="cfg-actions">
                                <?php if ($counts['active'] > 0): ?>
                                    <form method="POST" action="stock.php"
                                        onsubmit="return confirm('همه <?php echo $counts['active']; ?> کانفیگ فعال این انبار برای همیشه از دیتابیس حذف شوند؟ این عمل بازگشت‌پذیر نیست.');">
                                        <input type="hidden" name="_action" value="delete_active_configs">
                                        <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                                        <?php echo $daysHiddenBar; ?>
                                        <button type="submit" class="btn btn-sm btn-soft-danger">
                                            <?php echo icon('trash', 'svg-icon svg-sm'); ?>
                                            حذف فعال‌ها
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($daysFilter !== null): ?>
                                    <span class="btn btn-sm btn-outline btn-outline-danger is-disabled"
                                        title="با فعال بودن فیلتر روز غیرفعال است — برای حذف موارد فیلترشده از انتخاب گروهی استفاده کنید.">
                                        <?php echo icon('trash', 'svg-icon svg-sm'); ?>
                                        حذف دائمی این فیلتر از دیتابیس
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="stock.php"
                                        onsubmit="return confirm('<?php echo $cfgFilter === 'all' ? 'همه کانفیگ‌های این انبار' : 'کانفیگ‌های فیلترشده'; ?> برای همیشه از دیتابیس حذف شوند؟ این عمل بازگشت‌پذیر نیست.');">
                                        <input type="hidden" name="_action" value="delete_filtered_configs">
                                        <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                                        <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($cfgFilter, ENT_QUOTES); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline btn-outline-danger">
                                            <?php echo icon('trash', 'svg-icon svg-sm'); ?>
                                            حذف دائمی <?php echo $cfgFilter === 'all' ? 'کل' : 'این فیلتر'; ?> از دیتابیس
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($shelfConfigs)): ?>
                        <div class="card empty-state">
                            <?php echo icon('package', 'svg-icon svg-2xl'); ?>
                            <div class="empty-state__text"><?php echo ($cfgTotal === 0 && $cfgFilter !== 'all') ? 'کانفیگی با این فیلتر یافت نشد.' : 'هنوز کانفیگی در این انبار ثبت نشده است.'; ?></div>
                            <button class="btn btn-primary empty-state__action" onclick="openModal('modal-add-configs')">افزودن اولین کانفیگ</button>
                        </div>
                    <?php else: ?>
                        <?php  ?>
                        <form method="POST" action="stock.php" id="stock-bulk-form" style="display:none;"
                            onsubmit="return faoximaStockBulkConfirm();">
                            <input type="hidden" name="_action" value="stock_bulk_delete">
                            <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                            <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($cfgFilter, ENT_QUOTES); ?>">
                            <input type="hidden" name="cpage" value="<?php echo (int)$cfgPage; ?>">
                            <?php if ($daysFilter !== null): ?>
                                <input type="hidden" name="days" value="<?php echo (int)$daysFilter; ?>">
                                <input type="hidden" name="dmode" value="<?php echo htmlspecialchars($daysMode, ENT_QUOTES); ?>">
                            <?php endif; ?>
                        </form>
                        <div class="bulk-bar" id="stock-bulk-bar">
                            <label class="bulk-bar__all"><input type="checkbox" id="stock-bulk-all"><span>انتخاب همه‌ی این صفحه</span></label>
                            <span class="bulk-bar__count" id="stock-bulk-count">موردی انتخاب نشده</span>
                            <div class="bulk-bar__actions">
                                <button type="submit" form="stock-bulk-form" class="btn btn-sm btn-outline btn-outline-danger bulk-bar__btn js-bulk-delete-btn" id="stock-bulk-btn" style="display:none;">
                                    <?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف انتخاب‌شده‌ها
                                </button>
                            </div>
                        </div>
                        <div class="stock-configs-list">
                            <?php foreach ($shelfConfigs as $cfg):
                                [$stLabel, $stClass] = faoxima_stock_status_label((string)$cfg['status']);
                                $cfgStatus = (string)$cfg['status'];
                                $cfgId = (int)$cfg['id'];
                                $rem = $remainingOf[$cfgId] ?? null;
                            ?>
                                <div class="config-row<?php echo $cfgStatus === 'disabled' ? ' config-row--disabled' : ''; ?>">
                                    <div class="config-row__head">
                                        <div class="config-row__info">
                                            <input type="checkbox" class="row-check" form="stock-bulk-form" name="ids[]"
                                                value="<?php echo $cfgId; ?>"
                                                <?php echo $cfgStatus === 'delivered' || $cfgStatus === 'reserved' ? 'data-live="1"' : ''; ?>
                                                aria-label="انتخاب این کانفیگ برای حذف گروهی">
                                            <span class="badge <?php echo $stClass; ?>"><?php echo $stLabel; ?></span>
                                            <?php if ($rem !== null): ?>
                                                <?php echo faoxima_remaining_badge($rem); ?>
                                            <?php elseif ($shelfDays > 0): ?>
                                                <span class="config-meta-tag"><?php echo icon('hourglass', 'svg-icon svg-sm'); ?> مدت: <?php echo (int)$shelfDays; ?> روز</span>
                                            <?php endif; ?>
                                            <?php if (!empty($cfg['sub_link'])): ?>
                                                <span class="config-meta-tag config-meta-tag--accent"><?php echo icon('link', 'svg-icon svg-sm'); ?> سابسکریپشن دارد</span>
                                            <?php endif; ?>
                                            <?php if (!empty($cfg['assigned_user'])): ?>
                                                <span class="config-meta-tag"><?php echo icon('user', 'svg-icon svg-sm'); ?> <?php echo htmlspecialchars((string)$cfg['assigned_user'], ENT_QUOTES); ?></span>
                                            <?php endif; ?>
                                            <?php if ($cfgStatus === 'reserved' || $cfgStatus === 'delivered'): ?>
                                                <span class="config-meta-tag"><?php echo icon('lock', 'svg-icon svg-sm'); ?> متصل به سرویس</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="config-row__actions">
                                            <?php
                                            $daysHidden = '';
                                            if ($daysFilter !== null) {
                                                $daysHidden = '<input type="hidden" name="days" value="' . (int)$daysFilter . '">'
                                                    . '<input type="hidden" name="dmode" value="' . htmlspecialchars($daysMode, ENT_QUOTES) . '">';
                                            }
                                            ?>
                                            <form method="POST" action="stock.php" onsubmit="return confirm('این کانفیگ برای همیشه از دیتابیس حذف شود؟ این عمل بازگشت‌پذیر نیست.');">
                                                <input type="hidden" name="_action" value="delete_config_hard">
                                                <input type="hidden" name="config_id" value="<?php echo $cfgId; ?>">
                                                <input type="hidden" name="shelf_id" value="<?php echo $viewShelfId; ?>">
                                                <input type="hidden" name="cstatus" value="<?php echo htmlspecialchars($cfgFilter, ENT_QUOTES); ?>">
                                                <input type="hidden" name="cpage" value="<?php echo (int)$cfgPage; ?>">
                                                <?php echo $daysHidden; ?>
                                                <button type="submit" class="btn btn-sm btn-outline btn-outline-danger" title="حذف دائمی از دیتابیس">
                                                    <?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف دائمی
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="config-content-wrap">
                                        <?php if (!empty($cfg['content'])): ?>
                                            <div class="config-content"><?php echo htmlspecialchars($cfg['content'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($cfg['sub_link'])): ?>
                                            <div class="config-sub-link">SUB: <?php echo htmlspecialchars($cfg['sub_link'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($cfgPages > 1 && count($shelfConfigs) > 500): ?>
                            <div class="cfg-pagination">
                                <?php if ($cfgPage > 1): ?>
                                    <a class="btn btn-sm btn-outline" href="stock.php?<?php echo $stockQs(['cpage' => $cfgPage - 1]); ?>">‹ قبل</a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-outline is-disabled">‹ قبل</span>
                                <?php endif; ?>
                                <span class="cfg-pagination__info">صفحه <strong><?php echo $cfgPage; ?></strong> از <strong><?php echo $cfgPages; ?></strong> — <?php echo $cfgTotal; ?> مورد</span>
                                <?php if ($cfgPage < $cfgPages): ?>
                                    <a class="btn btn-sm btn-outline" href="stock.php?<?php echo $stockQs(['cpage' => $cfgPage + 1]); ?>">بعد ›</a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-outline is-disabled">بعد ›</span>
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


                                <div class="form-group entry-mode-group">
                                    <div class="entry-mode-group__title">نوع ورود</div>
                                    <div class="entry-mode-options">
                                        <label class="mode-pill" data-mode="single">
                                            <input type="radio" name="entry_mode" value="single" checked>
                                            <div class="mode-pill__text">
                                                <div class="mode-pill__title"><?php echo icon('plus', 'svg-icon svg-sm'); ?> تکی</div>
                                                <small class="mode-pill__hint">یک کانفیگ یا لینک</small>
                                            </div>
                                        </label>
                                        <label class="mode-pill" data-mode="bulk">
                                            <input type="radio" name="entry_mode" value="bulk">
                                            <div class="mode-pill__text">
                                                <div class="mode-pill__title"><?php echo icon('package', 'svg-icon svg-sm'); ?> دسته‌ای</div>
                                                <small class="mode-pill__hint">چند کانفیگ/لینک با هم</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div id="entry-mode-error" class="alert alert-danger entry-mode-error" style="display:none;">
                                    <?php echo icon('xmark', 'svg-icon svg-sm'); ?>
                                    <span></span>
                                </div>


                                <div id="mode-single-fields">
                                    <div class="form-group">
                                        <label class="form-label">کانفیگ یا لینک</label>
                                        <textarea name="configs" rows="4" class="form-control single-cfg cfg-textarea"
                                            placeholder="vmess://..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="mode-pill mode-pill--wide">
                                            <input type="checkbox" name="has_sub_link" id="stock-single-sub-toggle" value="1">
                                            <div class="mode-pill__text">
                                                <div class="mode-pill__title"><?php echo icon('link', 'svg-icon svg-sm'); ?> لینک اشتراک</div>
                                                <small class="mode-pill__hint">اگر این کانفیگ لینک اشتراک مجزا دارد، فعال کنید.</small>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="form-group" id="stock-single-sub-wrap" style="display:none;">
                                        <label class="form-label">آدرس لینک اشتراک</label>
                                        <input type="text" name="single_sub_link" id="stock-single-sub-input" class="form-control" placeholder="https://sub.example.com/...">
                                    </div>
                                </div>

                                <div id="mode-bulk-fields" style="display:none;">
                                    <input type="hidden" name="items_json" id="stock-bulk-items-json">
                                    <div class="card" style="padding:15px; background:var(--surface-2); margin-bottom:15px; border:1px solid var(--border-soft);">
                                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                                            <span class="badge badge-accent" id="stock-bulk-step-badge">کانفیگ ۱ از ۱</span>
                                            <button type="button" class="btn btn-sm btn-outline btn-outline-danger" id="stock-bulk-del-step" style="display:none;">حذف این گام</button>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">محتوای کانفیگ</label>
                                            <textarea rows="4" id="stock-bulk-cfg-input" class="form-control cfg-textarea" placeholder="vless://..."></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="mode-pill mode-pill--wide">
                                                <input type="checkbox" id="stock-bulk-sub-toggle" value="1">
                                                <div class="mode-pill__text">
                                                    <div class="mode-pill__title"><?php echo icon('link', 'svg-icon svg-sm'); ?> لینک اشتراک</div>
                                                    <small class="mode-pill__hint">برای این کانفیگ لینک اشتراک ثبت شود.</small>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="form-group" id="stock-bulk-sub-wrap" style="display:none;">
                                            <label class="form-label">آدرس لینک اشتراک</label>
                                            <input type="text" id="stock-bulk-sub-input" class="form-control" placeholder="https://sub.example.com/...">
                                        </div>
                                        <div class="wizard-step-nav">
                                            <button type="button" class="btn btn-outline btn-sm" id="stock-bulk-prev-btn" disabled>قبلی</button>
                                            <button type="button" class="btn btn-outline btn-sm" id="stock-bulk-next-btn">بعدی (+)</button>
                                        </div>
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
                        <div class="card empty-state">
                            <?php echo icon('package', 'svg-icon svg-2xl'); ?>
                            <div class="empty-state__text">هنوز انباری ثبت نشده است.</div>
                            <button class="btn btn-primary empty-state__action" onclick="openModal('modal-add-shelf')">افزودن اولین انبار</button>
                        </div>
                    <?php elseif (!$tableMissing): ?>
                        <div class="stock-grid">
                            <?php foreach ($shelves as $sh):
                                $sid       = (int)$sh['id'];
                                $isActive  = ($sh['status'] === 'active');
                                $sourceTxt = $panelNameOf[(string)$sh['source_codepanel']] ?? ($sh['source_codepanel'] === 'auto' ? 'خودکار' : (string)$sh['source_codepanel']);
                                $cnt = $shelfCounts[$sid] ?? ['total' => 0, 'active' => 0, 'reserved' => 0, 'delivered' => 0, 'disabled' => 0];
                            ?>
                                <div class="shelf-card">
                                    <div class="shelf-card__head">
                                        <div class="shelf-card__title"><?php echo htmlspecialchars($sh['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ($isActive): ?><span class="badge badge-active">فعال</span>
                                        <?php else: ?><span class="badge badge-block">غیرفعال</span><?php endif; ?>
                                    </div>
                                    <div class="shelf-card__body">
                                        <div><span class="shelf-card__key">محصول:</span> <b><?php echo htmlspecialchars($sh['product_name'] ?? '—', ENT_QUOTES); ?></b></div>
                                        <div><span class="shelf-card__key">پنل مبدأ:</span> <?php echo htmlspecialchars($sourceTxt, ENT_QUOTES); ?></div>
                                        <div><span class="shelf-card__key">حجم / مدت:</span> <?php echo ((float)$sh['volume_gb'] === 0.0) ? 'نامحدود' : (float)$sh['volume_gb'] . ' گیگ'; ?> / <?php echo (int)$sh['service_days']; ?> روز</div>
                                        <div><span class="shelf-card__key">قیمت:</span> <?php echo number_format((int)$sh['price']); ?> T</div>

                                        <div class="stat-row shelf-card__stats">
                                            <div class="stat-chip stat-chip--success"><?php echo icon('circle-check', 'svg-icon svg-sm'); ?> موجود <strong><?php echo $cnt['active']; ?></strong></div>
                                            <?php if ($cnt['delivered'] > 0): ?><div class="stat-chip stat-chip--accent"><?php echo icon('package', 'svg-icon svg-sm'); ?> تحویل <strong><?php echo $cnt['delivered']; ?></strong></div><?php endif; ?>
                                            <?php if ($cnt['reserved'] > 0): ?><div class="stat-chip stat-chip--warning"><?php echo icon('lock', 'svg-icon svg-sm'); ?> رزرو <strong><?php echo $cnt['reserved']; ?></strong></div><?php endif; ?>
                                        </div>

                                        <div class="shelf-card__foot">
                                            <a href="stock.php?shelf=<?php echo $sid; ?>" class="btn btn-sm btn-primary shelf-card__manage">
                                                <?php echo icon('package', 'svg-icon svg-sm'); ?>
                                                مدیریت کانفیگ‌ها
                                            </a>
                                            <form method="POST" action="stock.php" onsubmit="return confirm('انبار «<?php echo htmlspecialchars($sh['name'], ENT_QUOTES); ?>» و <?php echo $cnt['total']; ?> کانفیگ آن حذف شوند؟');">
                                                <input type="hidden" name="_action" value="delete_shelf">
                                                <input type="hidden" name="id" value="<?php echo $sid; ?>">
                                                <button type="submit" class="btn btn-sm btn-soft-danger">
                                                    <?php echo icon('trash', 'svg-icon svg-sm'); ?>
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
                                <?php if (empty($nationalPanels)): ?>
                                    <div class="alert alert-danger">
                                        <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                                        <span>هیچ پنلی با وضعیت شبکه ملی فعال یافت نشد. امکان افزودن انبار وجود ندارد.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="form-group">
                                        <label class="form-label">انتخاب پنل</label>
                                        <select name="source_codepanel" class="form-control" id="panel-select">
                                            <?php foreach ($nationalPanels as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p['code_panel'], ENT_QUOTES); ?>"
                                                    data-name="<?php echo htmlspecialchars($p['name_panel'], ENT_QUOTES); ?>">
                                                    <?php echo htmlspecialchars($p['name_panel'], ENT_QUOTES); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label">محصول مرتبط</label>
                                    <select name="codeproduct" class="form-control" id="product-select" required>
                                        <?php foreach ($products as $p):
                                            $v = faoxima_product_volume($p);
                                            $d = faoxima_product_days($p);
                                            $pr = faoxima_product_price($p);
                                            $loc = (string)($p['Location'] ?? '');
                                        ?>
                                            <option value="<?php echo htmlspecialchars($p['code_product'] ?? '', ENT_QUOTES); ?>"
                                                data-volume="<?php echo $v; ?>" data-days="<?php echo $d; ?>" data-price="<?php echo $pr; ?>"
                                                data-location="<?php echo htmlspecialchars($loc, ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars(faoxima_product_name($p), ENT_QUOTES); ?>
                                                (<?php echo ($v == 0) ? 'نامحدود' : $v . 'گیگ'; ?> — <?php echo $d; ?>روز)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-hint">با انتخاب محصول، فیلدهای زیر خودکار پر می‌شوند.</small>
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
                                        <label class="form-label">قیمت (T)</label>
                                        <input type="text" name="price" id="f-price" data-money class="form-control" value="0">
                                    </div>
                                </div>

                                <div class="modal-foot">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-shelf')">انصراف</button>
                                    <button type="submit" class="btn btn-primary btn-sm" <?php echo empty($nationalPanels) ? 'disabled' : ''; ?>>
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

        var sel = document.getElementById('product-select');
        if (sel) {
            sel.addEventListener('change', function() {
                var opt = sel.options[sel.selectedIndex];
                if (!opt) return;
                document.getElementById('f-volume').value = opt.dataset.volume || 0;
                document.getElementById('f-days').value = opt.dataset.days || 0;
                document.getElementById('f-price').value = window.FaoximaMoneyInput ? window.FaoximaMoneyInput.format(opt.dataset.price || 0) : (opt.dataset.price || 0);
            });
        }

        var panelSel = document.getElementById('panel-select');
        if (sel && panelSel) {
            var allProductOptions = Array.prototype.slice.call(sel.options);

            function applyPanelFilter() {
                var panelOpt = panelSel.options[panelSel.selectedIndex];
                var panelName = panelOpt ? (panelOpt.dataset.name || '') : '';
                sel.innerHTML = '';
                allProductOptions.forEach(function(opt) {
                    var loc = opt.dataset.location || '';
                    if (loc === panelName || loc === '/all') {
                        sel.appendChild(opt.cloneNode(true));
                    }
                });
                var opt = sel.options[sel.selectedIndex];
                if (opt) {
                    document.getElementById('f-volume').value = opt.dataset.volume || 0;
                    document.getElementById('f-days').value = opt.dataset.days || 0;
                    document.getElementById('f-price').value = window.FaoximaMoneyInput ? window.FaoximaMoneyInput.format(opt.dataset.price || 0) : (opt.dataset.price || 0);
                }
            }
            panelSel.addEventListener('change', applyPanelFilter);
            applyPanelFilter();
        }


        var addCfgForm = document.getElementById('add-configs-form');
        if (addCfgForm) {
            var singleBlock = document.getElementById('mode-single-fields');
            var bulkBlock = document.getElementById('mode-bulk-fields');
            var singleCfg = addCfgForm.querySelector('.single-cfg');
            var bulkCfg = addCfgForm.querySelector('.bulk-cfg');
            var modePills = addCfgForm.querySelectorAll('.mode-pill');
            var modeError = document.getElementById('entry-mode-error');

            function applyMode(mode) {
                var isSingle = mode === 'single';
                singleBlock.style.display = isSingle ? '' : 'none';
                bulkBlock.style.display = isSingle ? 'none' : '';
                modePills.forEach(function(p) {
                    var active = p.getAttribute('data-mode') === mode;
                    p.style.borderColor = active ? 'var(--accent)' : 'var(--border-color)';
                    p.style.background = active ? 'var(--surface-2)' : '';
                });
                if (modeError) modeError.style.display = 'none';
            }
            addCfgForm.querySelectorAll('input[name="entry_mode"]').forEach(function(r) {
                r.addEventListener('change', function() {
                    applyMode(r.value);
                });
            });
            modePills.forEach(function(p) {
                p.addEventListener('click', function() {
                    var r = p.querySelector('input[type=radio]');
                    if (r) {
                        r.checked = true;
                        applyMode(p.getAttribute('data-mode'));
                    }
                });
            });

            var singleSubToggle = document.getElementById('stock-single-sub-toggle');
            var singleSubWrap = document.getElementById('stock-single-sub-wrap');
            var singleSubInput = document.getElementById('stock-single-sub-input');

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

            var stepBadge = document.getElementById('stock-bulk-step-badge');
            var bulkCfgInput = document.getElementById('stock-bulk-cfg-input');
            var bulkSubToggle = document.getElementById('stock-bulk-sub-toggle');
            var bulkSubWrap = document.getElementById('stock-bulk-sub-wrap');
            var bulkSubInput = document.getElementById('stock-bulk-sub-input');
            var bulkPrevBtn = document.getElementById('stock-bulk-prev-btn');
            var bulkNextBtn = document.getElementById('stock-bulk-next-btn');
            var bulkDelBtn = document.getElementById('stock-bulk-del-step');
            var itemsJsonInput = document.getElementById('stock-bulk-items-json');

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

            var initialMode = addCfgForm.querySelector('input[name="entry_mode"]:checked');
            applyMode(initialMode ? initialMode.value : 'single');

            function showModeError(msg) {
                if (modeError) {
                    var span = modeError.querySelector('span');
                    if (span) span.textContent = msg;
                    else modeError.textContent = msg;
                    modeError.style.display = 'flex';
                } else {
                    alert(msg);
                }
            }

            addCfgForm.addEventListener('submit', function(ev) {
                var mode = (addCfgForm.querySelector('input[name="entry_mode"]:checked') || {}).value || 'single';
                if (mode === 'bulk') {
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
                        showModeError('لطفاً حداقل یک کانفیگ یا لینک وارد کنید.');
                        return false;
                    }
                    if (itemsJsonInput) {
                        itemsJsonInput.value = JSON.stringify(validItems);
                    }
                } else {
                    var singleCfg = addCfgForm.querySelector('.single-cfg');
                    var cfgVal = (singleCfg ? singleCfg.value : '').trim();
                    var hasSub = singleSubToggle ? singleSubToggle.checked : false;
                    var subVal = (singleSubInput ? singleSubInput.value : '').trim();

                    if (cfgVal === '' && (!hasSub || subVal === '')) {
                        ev.preventDefault();
                        showModeError('لطفاً حداقل یک کانفیگ یا لینک وارد کنید.');
                        return false;
                    }
                    if (itemsJsonInput) {
                        itemsJsonInput.value = JSON.stringify([{
                            content: cfgVal,
                            sub_link: hasSub ? subVal : ''
                        }]);
                    }
                }
                if (modeError) modeError.style.display = 'none';
            });

            renderStep(0);
        }

        (function faoximaStockBulkInit() {
            var bar = document.getElementById('stock-bulk-bar');
            if (!bar) return;
            var allCb = document.getElementById('stock-bulk-all');
            var countEl = document.getElementById('stock-bulk-count');
            var form = document.getElementById('stock-bulk-form');

            var fxStockHandle = FxBulkSelect.init({
                scope: 'default',
                checkboxSelector: 'input.row-check[name="ids[]"]',
                scopeRoot: document,
                formEl: form,
                deleteButtonSelector: '.js-bulk-delete-btn',
                clearOnQueryFlags: ['bulkclear']
            });

            function allRows() {
                return Array.prototype.slice.call(
                    document.querySelectorAll('input.row-check[name="ids[]"]')
                );
            }

            function isRowHidden(cb) {
                var parentRow = cb.closest('.config-row');
                return parentRow && parentRow.style.display === 'none';
            }

            function visibleRows() {
                return allRows().filter(function(c) {
                    return !isRowHidden(c);
                });
            }

            function update() {
                var vis = visibleRows();
                var visChecked = vis.filter(function(c) {
                    return c.checked;
                }).length;

                if (allCb) {
                    allCb.checked = vis.length > 0 && visChecked === vis.length;
                    allCb.indeterminate = visChecked > 0 && visChecked < vis.length;
                }

                var persisted = FxBulkSelect.getPersistedIds(fxStockHandle.key);
                var live = allRows().filter(function(c) {
                    return c.dataset.live === '1' && persisted.indexOf(c.value) !== -1;
                }).length;

                if (countEl) {
                    countEl.textContent = persisted.length === 0 ?
                        'موردی انتخاب نشده' :
                        (persisted.length + ' مورد انتخاب شد' + (live > 0 ? ' (شامل ' + live + ' متصل به سرویس)' : ''));
                }
            }

            if (allCb) {
                allCb.addEventListener('change', function() {
                    var want = allCb.checked;
                    visibleRows().forEach(function(c) {
                        if (c.checked === want) return;
                        c.checked = want;
                        c.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    update();
                });
            }

            document.addEventListener('change', function(ev) {
                var t = ev.target;
                if (t && t.classList && t.classList.contains('row-check')) update();
            });

            document.addEventListener('faoxima:pagechange', function() {
                update();
            });

            window.faoximaStockBulkConfirm = function() {
                var count = fxStockHandle.count();
                if (count === 0) {
                    alert('ابتدا کانفیگ‌های مورد نظر را انتخاب کنید.');
                    return false;
                }
                var msg = count + ' کانفیگ برای همیشه از دیتابیس حذف شود؟ این عمل بازگشت‌پذیر نیست.';
                return confirm(msg);
            };

            update();
        })();
    </script>
    <script src="js/datatable.js?v=flat12" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.FaoximaListDT) {
                FaoximaListDT.init('.stock-configs-list', {
                    itemSelector: '.config-row',
                    onPageChange: function() {
                        if (window.faoximaUpdateStockBulk) window.faoximaUpdateStockBulk(true);
                    }
                });
            }
        });
    </script>
</body>

</html>