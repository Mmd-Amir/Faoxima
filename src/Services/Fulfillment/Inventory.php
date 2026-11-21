<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;
use Exception;

final class Inventory
{
    public static function nmStockProductsForExtend($runtimePdo, array $panel, $agent = 'all', $onlyAvailable = false)
    {
        $pdo = $runtimePdo;
        $rows = [];
        $params = [':loc' => $panel['name_panel'] ?? ''];
        $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:loc, Location)>0 OR Location='/all')";
        $agent = trim((string)$agent);
        if ($agent !== '' && $agent !== 'all') { $sql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))"; $params[':agent'] = $agent; }
        $sql .= " ORDER BY CAST(price_product AS UNSIGNED) ASC, id ASC";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($onlyAvailable && !self::nmStockHasAvailableForProduct($runtimePdo, $panel, $row)) continue;
                $rows[] = $row;
            }
        } catch (Throwable $e) { error_log('nmStockProductsForExtend failed: ' . $e->getMessage()); }
        return $rows;
        }

    public static function nmStockResolveProductToken($runtimePdo, $token, array $panel, $agent = 'all')
    {
        $pdo = $runtimePdo;
        $token = trim((string)$token);
        $params = [':loc' => $panel['name_panel'] ?? ''];
        if (preg_match('/^pid_([0-9]+)$/', $token, $m)) { $sql = "SELECT * FROM product WHERE id=:id AND (FIND_IN_SET(:loc, Location)>0 OR Location='/all') LIMIT 1"; $params[':id'] = (int)$m[1]; }
        else { $sql = "SELECT * FROM product WHERE code_product=:code AND (FIND_IN_SET(:loc, Location)>0 OR Location='/all') LIMIT 1"; $params[':code'] = $token; }
        $agent = trim((string)$agent);
        if ($agent !== '' && $agent !== 'all') { $sql = str_replace(' LIMIT 1', " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1", $sql); $params[':agent'] = $agent; }
        try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: false; }
        catch (Throwable $e) { error_log('nmStockResolveProductToken failed: ' . $e->getMessage()); return false; }
        }

    public static function nmStockSafeUsername($userId, $current = '')
    {
        $base = preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', (string)$current));
        if ($base === '' || strlen($base) < 3) $base = preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', (string)$userId)) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        if (strlen($base) > 28) $base = substr($base, 0, 28);
        if (strlen($base) < 3) $base = 'u' . substr(bin2hex(random_bytes(6)), 0, 10);
        return $base;
        }

    public static function nmInvoiceTimestamp(array $invoice){
    $raw=$invoice['time_sell']??null;
    if(is_numeric($raw)) return (int)$raw;
    if(is_string($raw)&&trim($raw)!==''){ $x=strtotime($raw); if($x!==false) return $x; }
    return time();
    }

    public static function nmStockForInvoice($runtimePdo, array $invoice){
    $pdo = $runtimePdo;
    if(!self::nmStockEnsureSchema($runtimePdo)) return false;
    $iid=trim((string)($invoice['id_invoice']??''));
    $content=trim((string)($invoice['user_info']??''));
    $sourcePanel=trim((string)($invoice['source_panel_code']??''));


    if($content==='' && $sourcePanel==='') return false;
    try{
        if($iid!=='' && ($content!=='' || $sourcePanel!=='')){
            $st=$pdo->prepare("SELECT * FROM nm_config_stock WHERE assigned_invoice=:i ORDER BY CASE status WHEN 'delivered' THEN 0 WHEN 'reserved' THEN 1 WHEN 'disabled' THEN 2 ELSE 3 END, COALESCE(delivered_at,reserved_at,created_at,0) DESC, id DESC LIMIT 1");
            $st->execute([':i'=>$iid]); $r=$st->fetch(PDO::FETCH_ASSOC); if($r) return $r;
        }
        if($content!==''){
            $st=$pdo->prepare("SELECT * FROM nm_config_stock WHERE content=:c ORDER BY CASE status WHEN 'delivered' THEN 0 WHEN 'reserved' THEN 1 WHEN 'disabled' THEN 2 ELSE 3 END, COALESCE(delivered_at,reserved_at,created_at,0) DESC, id DESC LIMIT 1");
            $st->execute([':c'=>$content]); $r=$st->fetch(PDO::FETCH_ASSOC); if($r) return $r;
        }
    }catch(Throwable $e){ error_log('nmStockForInvoice failed: '.$e->getMessage()); }
    if($content!=='' && $sourcePanel!==''){
        return ['id'=>null,'content'=>$content,'sub_link'=>'','tier'=>self::nmStockTierFromVolume($invoice['Volume']??0),'format'=>self::nmStockDetectFormat($content),'status'=>'delivered','assigned_invoice'=>$iid];
    }
    return false;
    }

    public static function nmStockSubLinkForInvoice($runtimePdo, array $invoice,array $stock=null){
    $stock=$stock?:self::nmStockForInvoice($runtimePdo, $invoice);
    $content=is_array($stock)?trim((string)($stock['content']??'')):trim((string)($invoice['user_info']??''));
    $sub=is_array($stock)?trim((string)($stock['sub_link']??'')):'';
    if($sub!=='') return $sub;
    return preg_match('/^https?:\/\//i',$content)?$content:'';
    }

    public static function nmStockEnsureSchema($runtimePdo)
    {
        $pdo = $runtimePdo;
        static $ready = false;
        if ($ready || !isset($pdo) || !($pdo instanceof PDO)) {
            return $ready;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS nm_config_stock (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, shelf_id BIGINT UNSIGNED NULL, codepanel VARCHAR(191) NOT NULL DEFAULT 'auto', codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto', tier VARCHAR(32) NOT NULL DEFAULT 'auto', format VARCHAR(32) NOT NULL DEFAULT 'link', content MEDIUMTEXT NOT NULL, sub_link MEDIUMTEXT NULL, status ENUM('active','reserved','delivered','disabled') NOT NULL DEFAULT 'active', assigned_user VARCHAR(64) NULL, assigned_invoice VARCHAR(64) NULL, assigned_mode VARCHAR(64) NULL, created_at INT UNSIGNED NOT NULL DEFAULT 0, reserved_at INT UNSIGNED NULL, delivered_at INT UNSIGNED NULL, UNIQUE KEY uq_nm_config_stock_content (content(191)), KEY idx_nm_stock_lookup (status, codepanel, codeproduct, tier), KEY idx_nm_stock_shelf (status, shelf_id), KEY idx_nm_stock_invoice (assigned_invoice)) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS nm_stock_shelves (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, source_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto', stock_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto', category_id VARCHAR(64) NULL, category_name VARCHAR(191) NULL, codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto', product_name VARCHAR(191) NULL, volume_gb DECIMAL(10,2) NOT NULL DEFAULT 0, service_days INT NOT NULL DEFAULT 0, price BIGINT NOT NULL DEFAULT 0, status ENUM('active','disabled') NOT NULL DEFAULT 'active', created_at INT UNSIGNED NOT NULL DEFAULT 0, updated_at INT UNSIGNED NULL, UNIQUE KEY uq_nm_stock_shelf_name_panel (name, source_codepanel), KEY idx_nm_stock_shelf_lookup (status, source_codepanel, codeproduct)) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS nm_config_stock_log (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, stock_id BIGINT UNSIGNED NULL, id_user VARCHAR(64) NULL, id_invoice VARCHAR(64) NULL, action VARCHAR(64) NOT NULL, payload MEDIUMTEXT NULL, created_at INT UNSIGNED NOT NULL DEFAULT 0, KEY idx_nm_stock_log_invoice (id_invoice), KEY idx_nm_stock_log_user (id_user)) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS nm_stock_product_map (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, source_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto', stock_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto', codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto', category VARCHAR(191) NULL, volume_gb DECIMAL(10,2) NOT NULL DEFAULT 0, service_days INT NOT NULL DEFAULT 0, price BIGINT NOT NULL DEFAULT 0, status ENUM('active','disabled') NOT NULL DEFAULT 'active', created_at INT UNSIGNED NOT NULL DEFAULT 0, updated_at INT UNSIGNED NULL, UNIQUE KEY uq_nm_stock_product_map (source_codepanel, stock_codepanel, codeproduct), KEY idx_nm_stock_product_lookup (status, source_codepanel, codeproduct)) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            foreach (['national_net_status' => "VARCHAR(50) NOT NULL DEFAULT 'off_national_net'", 'stock_source_panel' => "VARCHAR(191) NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
            foreach (['shelf_id' => "BIGINT UNSIGNED NULL", 'sub_link' => "MEDIUMTEXT NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE nm_config_stock ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
            foreach (['category_id' => "VARCHAR(64) NULL", 'category_name' => "VARCHAR(191) NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE nm_stock_product_map ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
            foreach (['source_panel_code' => "VARCHAR(191) NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE invoice ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
            $ready = true;
        } catch (Throwable $e) {
            error_log('nmStockEnsureSchema failed: ' . $e->getMessage());
        }
        return $ready;
        }

    public static function nmStockTierFromVolume($volume)
    {
        $volume = (int)ceil((float)$volume);
        if ($volume <= 0) return 'auto';
        if ($volume <= 10) return '10-10';
        $lower = (int)(floor(($volume - 1) / 10) * 10);
        return $lower . '-' . ($lower + 10);
        }

    public static function nmStockDetectFormat($content)
    {
        $content = trim((string)$content);
        if (preg_match('/^https?:\/\//i', $content)) return 'subscription';
        if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard|wg|vpn|awg|amneziawg|tg):\/\//i', $content)) return 'single';
        if (stripos($content, '[Interface]') !== false || stripos($content, 'PrivateKey') !== false) return 'wireguard';
        return 'text';
        }

    public static function nmStockLog($runtimePdo, $stockId, $userId, $invoiceId, $action, $payload = null)
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) return;
        try {
            $stmt = $pdo->prepare("INSERT INTO nm_config_stock_log (stock_id,id_user,id_invoice,action,payload,created_at) VALUES (:stock_id,:id_user,:id_invoice,:action,:payload,:created_at)");
            $payloadText = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt->execute([':stock_id' => $stockId, ':id_user' => (string)$userId, ':id_invoice' => (string)$invoiceId, ':action' => (string)$action, ':payload' => $payloadText, ':created_at' => time()]);
        } catch (Throwable $e) { error_log('nmStockLog failed: ' . $e->getMessage()); }
        }

    public static function nmStockCounts($runtimePdo, $panelCode = null)
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) return [];
        $where = "status = 'active'";
        $params = [];
        if ($panelCode !== null && trim((string)$panelCode) !== '') { $where .= " AND (codepanel = :codepanel OR codepanel = 'auto')"; $params[':codepanel'] = (string)$panelCode; }
        $stmt = $pdo->prepare("SELECT tier, format, COUNT(*) AS cnt FROM nm_config_stock WHERE $where GROUP BY tier, format ORDER BY tier, format");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    public static function nmStockReserveOne($runtimePdo, $panelCode, $productCode, $volume, $userId, $invoiceId, $mode = 'fallback')
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) return false;
        $panelCode = trim((string)$panelCode) !== '' ? trim((string)$panelCode) : 'auto';
        $productCode = trim((string)$productCode) !== '' ? trim((string)$productCode) : 'auto';
        $tier = self::nmStockTierFromVolume($volume);
        try {
            $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s LEFT JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND s.tier=:tier AND (s.codepanel=:codepanel_where OR s.codepanel='auto') AND (s.codeproduct=:codeproduct_where OR s.codeproduct='auto') AND (sh.id IS NULL OR sh.status='active') ORDER BY (s.codeproduct=:codeproduct_order) DESC, (s.codepanel=:codepanel_order) DESC, s.shelf_id IS NULL ASC, s.id ASC LIMIT 1");
            $stmt->execute([
                ':tier' => $tier,
                ':codepanel_where' => $panelCode,
                ':codepanel_order' => $panelCode,
                ':codeproduct_where' => $productCode,
                ':codeproduct_order' => $productCode,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return false;
            $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
            $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
            if ($upd->rowCount() < 1) return false;
            self::nmStockLog($runtimePdo, $row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'tier' => $tier, 'product' => $productCode]);
            return $row;
        } catch (Throwable $e) { error_log('nmStockReserveOne failed: ' . $e->getMessage()); return false; }
        }

    public static function nmStockReleaseReservation($runtimePdo, $stock)
    {
        $pdo = $runtimePdo;
        $id = is_array($stock) ? ($stock['id'] ?? null) : $stock;
        if ($id === null || $id === '' || !$pdo) return false;
        try {
            $upd = $pdo->prepare("UPDATE nm_config_stock SET status='active', assigned_user='', assigned_invoice='', assigned_mode='', reserved_at=NULL WHERE id=:id AND status='reserved'");
            $upd->execute([':id' => $id]);
            $released = $upd->rowCount() > 0;
            if ($released && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockLog')) {
                self::nmStockLog($runtimePdo, $id, '', '', 'released', ['reason' => 'reservation_rolled_back']);
            }
            return $released;
        } catch (Throwable $e) { error_log('nmStockReleaseReservation failed: ' . $e->getMessage()); return false; }
        }

    public static function nmStockReserveByShelfMatch($runtimePdo, array $panel, array $product, $userId, $invoiceId, $mode = 'fallback')
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) return false;
        $stockPanel = is_callable([\Faoxima\Services\Fulfillment\Inventory::class, 'nmPanelResolveStockCode']) ? self::nmPanelResolveStockCode($panel) : (trim((string)($panel['code_panel'] ?? '')) ?: 'auto');
        $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
        $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function ($v) {
            return trim((string)$v) !== '';
        })));
        $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
        $productName = \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($product['name_product'] ?? '');
        $volume = (float)($product['Volume_constraint'] ?? 0);
        $days = (int)($product['Service_time'] ?? 0);
        $tier = self::nmStockTierFromVolume($volume);

        $panelWhereSql = [];
        $panelOrderSql = [];
        $params = [
            ':product_code_stock_where' => $productCode,
            ':product_code_shelf_where' => $productCode,
            ':product_code_stock_order' => $productCode,
            ':product_code_shelf_order' => $productCode,
            ':product_name_where' => $productName,
            ':product_name_order' => $productName,
            ':volume_where' => $volume,
            ':volume_order' => $volume,
            ':days_where' => $days,
            ':days_order' => $days,
        ];
        foreach ($panelCandidates as $i => $candidate) {
            $whereKey = ':panel_where_' . $i;
            $orderKey = ':panel_order_' . $i;
            $panelWhereSql[] = $whereKey;
            $panelOrderSql[] = $orderKey;
            $params[$whereKey] = (string)$candidate;
            $params[$orderKey] = (string)$candidate;
        }

        try {

            $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s INNER JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND sh.status='active' AND s.codepanel IN (" . implode(',', $panelWhereSql) . ") AND (s.codeproduct=:product_code_stock_where OR sh.codeproduct=:product_code_shelf_where OR sh.codeproduct='auto' OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :volume_where) < 0.001 AND sh.service_days=:days_where)) ORDER BY (s.codeproduct=:product_code_stock_order OR sh.codeproduct=:product_code_shelf_order) DESC, (sh.product_name=:product_name_order) DESC, (ABS(sh.volume_gb - :volume_order) < 0.001 AND sh.service_days=:days_order) DESC, FIELD(s.codepanel, " . implode(',', $panelOrderSql) . "), s.id ASC LIMIT 1");
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return false;
            $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
            $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
            if ($upd->rowCount() < 1) return false;
            self::nmStockLog($runtimePdo, $row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'tier' => $tier, 'product' => $productCode, 'shelf_match' => true]);
            return $row;
        } catch (Throwable $e) { error_log('nmStockReserveByShelfMatch failed: ' . $e->getMessage()); return false; }
        }

    public static function nmStockMarkDelivered($runtimePdo, $stockId, $userId, $invoiceId, $payload = null)
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) return;
        try {
            $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='delivered', assigned_user=COALESCE(NULLIF(assigned_user,''), :user), assigned_invoice=COALESCE(NULLIF(assigned_invoice,''), :invoice), delivered_at=:delivered_at WHERE id=:id AND status IN ('active','reserved','delivered')");
            $stmt->execute([
                ':user' => (string)$userId,
                ':invoice' => (string)$invoiceId,
                ':delivered_at' => time(),
                ':id' => $stockId,
            ]);
            if ($stmt->rowCount() < 1) {
                $fallback = $pdo->prepare("UPDATE nm_config_stock SET status='disabled', assigned_user=COALESCE(NULLIF(assigned_user,''), :user), assigned_invoice=COALESCE(NULLIF(assigned_invoice,''), :invoice), delivered_at=:delivered_at WHERE id=:id AND status='active'");
                $fallback->execute([
                    ':user' => (string)$userId,
                    ':invoice' => (string)$invoiceId,
                    ':delivered_at' => time(),
                    ':id' => $stockId,
                ]);
            }
            self::nmStockLog($runtimePdo, $stockId, $userId, $invoiceId, 'delivered', $payload);
        } catch (Throwable $e) {
            error_log('nmStockMarkDelivered failed: ' . $e->getMessage());
            try {
                $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='disabled', assigned_user=:user, assigned_invoice=:invoice, delivered_at=:delivered_at WHERE id=:id AND status='active'");
                $stmt->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':delivered_at' => time(), ':id' => $stockId]);
                self::nmStockLog($runtimePdo, $stockId, $userId, $invoiceId, 'delivered_disabled_fallback', $payload);
            } catch (Throwable $inner) {
                error_log('nmStockMarkDelivered fallback failed: ' . $inner->getMessage());
            }
        }
        }

    public static function nmStockProductForInvoice($runtimePdo, array $invoice)
    {
        $pdo = $runtimePdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product=:name AND (FIND_IN_SET(:loc, Location)>0 OR Location='/all') LIMIT 1");
            $stmt->execute([':name' => $invoice['name_product'] ?? '', ':loc' => $invoice['Service_location'] ?? '']);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) return $product;
        } catch (Throwable $e) { error_log('nmStockProductForInvoice failed: ' . $e->getMessage()); }
        return ['code_product' => 'auto', 'name_product' => $invoice['name_product'] ?? '', 'Volume_constraint' => $invoice['Volume'] ?? 0, 'Service_time' => $invoice['Service_time'] ?? 0, 'price_product' => $invoice['price_product'] ?? 0];
        }

    public static function nmStockPanelForInvoice(array $invoice)
    {
        $sourceCode = trim((string)($invoice['source_panel_code'] ?? ''));
        if ($sourceCode !== '') {
            try { $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', $sourceCode, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { error_log('nmStockPanelForInvoice source lookup failed: ' . $e->getMessage()); }
        }
        $location = trim((string)($invoice['Service_location'] ?? ''));
        if ($location !== '') {
            try { $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $location, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { error_log('nmStockPanelForInvoice name lookup failed: ' . $e->getMessage()); }
            try { $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', $location, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { }
        }
        return false;
        }

    public static function nmStockHasAvailableForProduct($runtimePdo, array $panel, array $product)
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) return false;
        $stockPanel = self::nmPanelResolveStockCode($panel);
        $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
        $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
        $productName = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'nmNormalizeText') ? \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($product['name_product'] ?? '') : (string)($product['name_product'] ?? '');
        $volume = (float)($product['Volume_constraint'] ?? 0);
        $days = (int)($product['Service_time'] ?? 0);
        $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function($v){ return trim((string)$v) !== ''; })));
        $panelParamsS = [];
        $panelParamsSrc = [];
        $panelParamsSt = [];

        $params = [
            ':code_stock' => $productCode,
            ':code_shelf' => $productCode,
            ':product_name_where' => $productName,
            ':vol' => $volume,
            ':days' => $days,
        ];
        foreach ($panelCandidates as $idx => $candidate) {
            $kS = ':panels_' . $idx;
            $kSrc = ':panelsrc_' . $idx;
            $kSt = ':panelst_' . $idx;
            $panelParamsS[] = $kS;
            $panelParamsSrc[] = $kSrc;
            $panelParamsSt[] = $kSt;
            $params[$kS] = (string)$candidate;
            $params[$kSrc] = (string)$candidate;
            $params[$kSt] = (string)$candidate;
        }
        try {
            $sql = "SELECT COUNT(*) FROM nm_config_stock s LEFT JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND (s.codepanel IN (" . implode(',', $panelParamsS) . ") OR sh.source_codepanel IN (" . implode(',', $panelParamsSrc) . ") OR sh.stock_codepanel IN (" . implode(',', $panelParamsSt) . ")) AND (s.codeproduct=:code_stock OR s.codeproduct='auto' OR sh.codeproduct=:code_shelf OR sh.codeproduct='auto' OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :vol) < 0.001 AND sh.service_days=:days)) AND (sh.id IS NULL OR sh.status='active')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) { error_log('nmStockHasAvailableForProduct failed: ' . $e->getMessage()); return false; }
        }

    public static function nmPanelBool(array $panel = null, $field = 'national_net_status', $onValue = 'on_national_net')
    {
        if (!$panel) return false;
        return (($panel[$field] ?? '') === $onValue || ($panel[$field] ?? '') === 'on' || ($panel[$field] ?? '') === 'active');
        }

        public static function nmPanelNationalEnabled(array $panel = null) { return self::nmPanelBool($panel, 'national_net_status', 'on_national_net'); }

    public static function nmPanelResolveStockCode(array $panel = null)
    {
        if (!$panel) return 'auto';
        $code = trim((string)($panel['stock_source_panel'] ?? ''));
        if ($code !== '') return $code;
        return trim((string)($panel['code_panel'] ?? '')) !== '' ? $panel['code_panel'] : 'auto';
        }

    public static function nmStockReserveForProduct($runtimePdo, array $panel, array $product, $userId, $invoiceId, $mode = 'national_buy')
    {
        $stockPanel = self::nmPanelResolveStockCode($panel);
        $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
        $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
        $volume = $product['Volume_constraint'] ?? 0;
        $stock = self::nmStockReserveOne($runtimePdo, $stockPanel, $productCode, $volume, $userId, $invoiceId, $mode);
        if (!$stock && $sourcePanel !== $stockPanel) $stock = self::nmStockReserveOne($runtimePdo, $sourcePanel, $productCode, $volume, $userId, $invoiceId, $mode);
        if (!$stock) $stock = self::nmStockReserveByShelfMatch($runtimePdo, $panel, $product, $userId, $invoiceId, $mode);
        if (!$stock) $stock = self::nmStockReserveOne($runtimePdo, $stockPanel, 'auto', $volume, $userId, $invoiceId, $mode);
        if (!$stock && $sourcePanel !== $stockPanel) $stock = self::nmStockReserveOne($runtimePdo, $sourcePanel, 'auto', $volume, $userId, $invoiceId, $mode);


        if (!$stock) $stock = self::nmStockReserveByShelfLoose($runtimePdo, $panel, $product, $userId, $invoiceId, $mode);
        return $stock;
        }

    public static function nmStockReserveByShelfLoose($runtimePdo, array $panel, array $product, $userId, $invoiceId, $mode = 'fallback_loose')
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) return false;
        $stockPanel = is_callable([\Faoxima\Services\Fulfillment\Inventory::class, 'nmPanelResolveStockCode']) ? self::nmPanelResolveStockCode($panel) : (trim((string)($panel['code_panel'] ?? '')) ?: 'auto');
        $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
        $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function ($v) {
            return trim((string)$v) !== '';
        })));
        $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
        $productName = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'nmNormalizeText') ? \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($product['name_product'] ?? '') : (string)($product['name_product'] ?? '');
        $volume = (float)($product['Volume_constraint'] ?? 0);
        $days = (int)($product['Service_time'] ?? 0);
        $panelWhereSqlS = [];
        $panelWhereSqlSrc = [];
        $panelWhereSqlSt = [];
        $params = [
            ':product_code_stock_where' => $productCode,
            ':product_code_shelf_where' => $productCode,
            ':product_name_where' => $productName,
            ':volume_where' => $volume,
            ':days_where' => $days,
        ];
        foreach ($panelCandidates as $i => $candidate) {
            $kS = ':panel_where_s_' . $i;
            $kSrc = ':panel_where_src_' . $i;
            $kSt = ':panel_where_st_' . $i;
            $panelWhereSqlS[] = $kS;
            $panelWhereSqlSrc[] = $kSrc;
            $panelWhereSqlSt[] = $kSt;
            $params[$kS] = (string)$candidate;
            $params[$kSrc] = (string)$candidate;
            $params[$kSt] = (string)$candidate;
        }
        try {

            $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s INNER JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND sh.status='active' AND (s.codepanel IN (" . implode(',', $panelWhereSqlS) . ") OR sh.source_codepanel IN (" . implode(',', $panelWhereSqlSrc) . ") OR sh.stock_codepanel IN (" . implode(',', $panelWhereSqlSt) . ")) AND (sh.codeproduct=:product_code_shelf_where OR s.codeproduct=:product_code_stock_where OR sh.codeproduct='auto' OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :volume_where) < 0.001 AND sh.service_days=:days_where)) ORDER BY s.id ASC LIMIT 1");
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return false;
            $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
            $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
            if ($upd->rowCount() < 1) return false;
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockLog')) self::nmStockLog($runtimePdo, $row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'product' => $productCode, 'shelf_loose' => true]);
            return $row;
        } catch (Throwable $e) { error_log('nmStockReserveByShelfLoose failed: ' . $e->getMessage()); return false; }
        }

    public static function nmStockShelfById($runtimePdo, $id)
    {
        $pdo = $runtimePdo;
        if (!self::nmStockEnsureSchema($runtimePdo)) { error_log('[STOCK_SHELF_BY_ID] schema_not_ready id=' . var_export($id, true)); return false; }
        try {
            $stmt = $pdo->prepare("SELECT * FROM nm_stock_shelves WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => (int)$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                error_log(sprintf('[STOCK_SHELF_BY_ID] not_found requested_id=%s casted_int=%d', var_export($id, true), (int)$id));
                return false;
            }
            return $row;
        } catch (Throwable $e) {
            error_log('[STOCK_SHELF_BY_ID] exception id=' . var_export($id, true) . ' err=' . $e->getMessage());
            return false;
        }
        }

    public static function nmAnyNationalNetEnabled($runtimePdo)
    {
        $pdo = $runtimePdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM marzban_panel WHERE status = 'active' AND national_net_status = 'on_national_net'");
            $stmt->execute();
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return false;
        }
        }

    public static function nmServicePanelAccessBlocked(array $invoice = null)
    {
        if (!$invoice) return false;
        $serviceLocation = trim((string)($invoice['Service_location'] ?? ''));
        if ($serviceLocation === '') return false;
        try {
            $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $serviceLocation, 'select');
            if (!$panel && !empty($invoice['source_panel_code'])) $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', $invoice['source_panel_code'], 'select');
            if (!$panel) return false;
            return self::nmPanelNationalEnabled($panel);
        } catch (Throwable $e) {
            error_log('nmServicePanelAccessBlocked failed: ' . $e->getMessage());
            return false;
        }
        }
}
