<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;
use RuntimeException;

final class StockRequestHelpers
{
    public static function faoxima_stock_days_qs(): string
{
    $raw = isset($_POST['days']) ? trim((string)$_POST['days']) : '';
    if ($raw === '' || !ctype_digit($raw)) return '';
    return '&days=' . (int)$raw . '&dmode=' . urlencode(\Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_dmode($_POST['dmode'] ?? ''));
}

    public static function faoxima_product_name(array $p): string
{
    return (string)($p['name_product'] ?? $p['Name_product'] ?? '');
}

    public static function faoxima_product_volume(array $p): float
{
    if (isset($p['Volume_constraint'])) return (float)$p['Volume_constraint'];
    if (isset($p['volume_product']))    return (float)$p['volume_product'];
    return 0.0;
}

    public static function faoxima_product_days(array $p): int
{
    if (isset($p['Service_time'])) return (int)$p['Service_time'];
    if (isset($p['time_product'])) return (int)$p['time_product'];
    return 0;
}

    public static function faoxima_product_price(array $p): int
{
    if (isset($p['Price_product'])) return (int)$p['Price_product'];
    if (isset($p['price_product'])) return (int)$p['price_product'];
    return 0;
}

    public static function fx_stock_shelf_input(array $post, array $productByCode, array $nationalPanels, array $panelNameOf): array
{
    $name          = trim((string)($post['name']             ?? ''));
    $selectedPanel = trim((string)($post['source_codepanel'] ?? ''));
    $codeProduct   = trim((string)($post['codeproduct']      ?? ''));

    $productName = '';
    $volumeGb    = (float)($post['volume_gb']    ?? 0);
    $serviceDays = (int)($post['service_days']   ?? 0);
    $price       = (int)($post['price']          ?? 0);
    if (isset($productByCode[$codeProduct])) {
        $p = $productByCode[$codeProduct];
        $productName = self::faoxima_product_name($p);
        if ($volumeGb    <= 0) $volumeGb    = self::faoxima_product_volume($p);
        if ($serviceDays <= 0) $serviceDays = self::faoxima_product_days($p);
        if ($price       <= 0) $price       = self::faoxima_product_price($p);
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
    return [$name, $selectedPanel, $codeProduct, $productName, $volumeGb, $serviceDays, $price, $errors];
}

    public static function fx_stock_config_metadata(string $cfg, ?string $sub, array $shelf): array
{
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
    return [$fmt, $tier];
}

    public static function fx_stock_import_configs(PDO $pdo, int $shelfId, array $entries): array
{
    return \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_config_transaction($pdo, 'nm_config_stock', 'stock_import', static function () use ($pdo, $shelfId, $entries): array {
        $lockShelf = $pdo->prepare('SELECT * FROM nm_stock_shelves WHERE id = ? FOR UPDATE');
        $lockShelf->execute([$shelfId]);
        $shelf = $lockShelf->fetch(PDO::FETCH_ASSOC);
        $lockShelf->closeCursor();
        if (!$shelf) {
            throw new RuntimeException('Stock shelf is no longer available');
        }

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
                [$fmt, $tier] = self::fx_stock_config_metadata($cfg, $sub, $shelf);

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
                if (!$pdo->inTransaction()) {
                    throw $e;
                }
                $bad++;
                \Faoxima\Admin\Web\Support\BulkDelete::fx_delete_log_error('stock_import_item', 'nm_config_stock', $e);
            }
        }
        return [$ok, $duplicate, $bad, $badSub];
    });
}

    public static function faoxima_stock_resolve_remaining(PDO $pdo, array $rows, int $shelfDays, bool $invoiceMissing): array
{
    $out = [];
    $sold = ['reserved' => true, 'delivered' => true];

    $invIds = [];
    foreach ($rows as $r) {
        if (!isset($sold[(string)($r['status'] ?? '')])) continue;
        $iv = trim((string)($r['assigned_invoice'] ?? ''));
        if ($iv !== '') $invIds[] = $iv;
    }
    $inv = ($invIds && !$invoiceMissing) ? \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_fetch_by_invoice($pdo, $invIds) : [];

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
        $out[$id] = \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_days($sellTs, $shelfDays, $uname, $prodName);
    }
    return $out;
}

    public static function faoxima_stock_status_label(string $s): array
{
    switch ($s) {
        case 'active':
            return [\Faoxima\Admin\Web\Support\Icons::icon('circle-check', 'svg-icon svg-sm') . ' موجود', 'badge-active'];
        case 'reserved':
            return [\Faoxima\Admin\Web\Support\Icons::icon('lock', 'svg-icon svg-sm') . ' رزرو', 'badge-warning'];
        case 'delivered':
            return [\Faoxima\Admin\Web\Support\Icons::icon('package', 'svg-icon svg-sm') . ' تحویل شده', 'badge-info'];
        case 'disabled':
            return [\Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm') . ' غیرفعال', 'badge-block'];
        default:
            return [htmlspecialchars($s, ENT_QUOTES), 'badge-gray'];
    }
}
}
