<?php


if (!function_exists('nmStockSendQr')) {
/**
 * Send a QR-code photo (of $qrPayload) with a short caption, then the full
 * text separately so long configs/links never hit Telegram's 1024-char caption
 * limit. Falls back to a plain text message if QR generation isn't available.
 */
function nmStockSendQr($chatId, $qrPayload, $fullText, $shortCaption = '📥 کیو‌آر کد')
{
    $qrPayload = trim((string)$qrPayload);
    $sent = false;
    if ($qrPayload !== '' && function_exists('createqrcode') && function_exists('telegram')) {
        try {
            $urlimage = $chatId . bin2hex(random_bytes(3)) . '.png';
            $qrCode = createqrcode($qrPayload);
            file_put_contents($urlimage, $qrCode->getString());
            if (function_exists('addBackgroundImage')) @addBackgroundImage($urlimage, $qrCode, 'images.jpg');
            telegram('sendphoto', ['chat_id' => $chatId, 'photo' => new CURLFile($urlimage), 'caption' => $shortCaption, 'parse_mode' => 'HTML']);
            @unlink($urlimage);
            $sent = true;
        } catch (Throwable $e) {
            error_log('nmStockSendQr failed: ' . $e->getMessage());
        }
    }
    if (trim((string)$fullText) !== '') {
        if (function_exists('nmSendLongStockMessage')) nmSendLongStockMessage($chatId, $fullText);
        else sendmessage($chatId, $fullText, null, 'HTML');
    }
    return $sent;
}
}

function nmStockDeliveryKeyboard($content, $invoiceId = '', $subLink = '')
{
    $buttons = [];
    $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$invoiceId);
    if ($invoiceId !== '') {
        $buttons[] = [
            ['text' => '🔄 تمدید سرویس', 'callback_data' => 'nmstockextend_' . $invoiceId],
            ['text' => '💎 بازگشت وجه', 'callback_data' => 'nmstockrefund_' . $invoiceId],
        ];
        $buttons[] = [
            ['text' => '📥 دریافت کانفیگ', 'callback_data' => 'nmstockcfg_' . $invoiceId],
            ['text' => '🔗 لینک اشتراک', 'callback_data' => 'nmstocksub_' . $invoiceId],
        ];
    } else {
        $buttons[] = [['text' => '📥 دریافت کانفیگ', 'callback_data' => 'none']];
        $link = trim((string)$subLink) !== '' ? trim((string)$subLink) : trim((string)$content);
        if (preg_match('/^https?:\/\//i', $link)) $buttons[] = [['text' => '🔗 لینک اشتراک', 'url' => $link]];
        else $buttons[] = [['text' => '🔗 لینک اشتراک', 'callback_data' => 'none']];
    }
    $buttons[] = [['text' => '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']];
    return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmPanelBool(array $panel = null, $field = 'national_net_status', $onValue = 'on_national_net')
{
    if (!$panel) return false;
    return (($panel[$field] ?? '') === $onValue || ($panel[$field] ?? '') === 'on' || ($panel[$field] ?? '') === 'active');
}
function nmPanelNationalEnabled(array $panel = null) { return nmPanelBool($panel, 'national_net_status', 'on_national_net'); }
function nmPanelEmergencyEnabled(array $panel = null) { return nmPanelBool($panel, 'emergency_panel_status', 'on_emergency_panel'); }
function nmPanelResolveStockCode(array $panel = null)
{
    if (!$panel) return 'auto';
    $code = trim((string)($panel['stock_source_panel'] ?? ''));
    if ($code !== '') return $code;
    return trim((string)($panel['code_panel'] ?? '')) !== '' ? $panel['code_panel'] : 'auto';
}
function nmPanelEmergencyPanel(array $panel = null)
{
    if (!$panel || !nmPanelEmergencyEnabled($panel)) return false;
    $code = trim((string)($panel['emergency_source_panel'] ?? ''));
    if ($code === '') return false;
    try { return select('marzban_panel', '*', 'code_panel', $code, 'select'); } catch (Throwable $e) { return false; }
}


function nmEmergencyReplacementMap()
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = ['by_code' => [], 'by_name' => []];
    try {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM marzban_panel WHERE emergency_panel_status = 'on_emergency_panel' AND status = 'active' AND emergency_source_panel IS NOT NULL AND emergency_source_panel <> ''");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $srcCode = trim((string)($row['emergency_source_panel'] ?? ''));
                if ($srcCode === '') continue;
                $cache['by_code'][$srcCode] = $row;
                try {
                    $srcRow = select('marzban_panel', '*', 'code_panel', $srcCode, 'select');
                    if (is_array($srcRow) && !empty($srcRow['name_panel'])) {
                        $cache['by_name'][(string)$srcRow['name_panel']] = $row;
                    }
                } catch (Throwable $_) {}
            }
        }
    } catch (Throwable $e) {
        error_log('nmEmergencyReplacementMap failed: ' . $e->getMessage());
    }
    return $cache;
}


function nmEmergencyResetCache()
{
    static $reset = false;
    $reset = !$reset;
}


function nmFilterPanelsForUser(array $rows)
{
    $map = nmEmergencyReplacementMap();
    $replacedCodes = array_keys($map['by_code'] ?? []);
    if (empty($replacedCodes)) return $rows;
    $replacedSet = array_flip($replacedCodes);
    $out = [];
    foreach ($rows as $row) {
        $code = trim((string)($row['code_panel'] ?? ''));
        if ($code !== '' && isset($replacedSet[$code])) continue;
        $out[] = $row;
    }
    return $out;
}


function nmResolveActivePanelByCode($code)
{
    $code = trim((string)$code);
    if ($code === '') return null;
    $map = nmEmergencyReplacementMap();
    if (isset($map['by_code'][$code])) return $map['by_code'][$code];
    try { return select('marzban_panel', '*', 'code_panel', $code, 'select') ?: null; } catch (Throwable $e) { return null; }
}


function nmEmergencyHidesPanel(array $row)
{
    $code = trim((string)($row['code_panel'] ?? ''));
    if ($code === '') return false;
    $map = nmEmergencyReplacementMap();
    return isset($map['by_code'][$code]);
}


function nmResolveActivePanelByName($name)
{
    $name = trim((string)$name);
    if ($name === '') return null;
    $map = nmEmergencyReplacementMap();
    if (isset($map['by_name'][$name])) return $map['by_name'][$name];
    try { return select('marzban_panel', '*', 'name_panel', $name, 'select') ?: null; } catch (Throwable $e) { return null; }
}
function nmStockPanelProducts($panelCode, $agent = 'all')
{
    global $pdo;
    try {
        $panel = select('marzban_panel', '*', 'code_panel', $panelCode, 'select');
        if (!$panel) return [];
        $agent = trim((string)($agent ?? ''));
        $params = [':loc_where' => $panel['name_panel']];
        $sql = "SELECT * FROM product WHERE (Location = :loc_where OR Location = '/all')";
        if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
            $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $sql .= " ORDER BY CAST(price_product AS UNSIGNED) ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { error_log('nmStockPanelProducts failed: ' . $e->getMessage()); return []; }
}
function nmStockSyncProductMap($sourcePanelCode, $stockPanelCode = null, $agent = 'all')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return 0;
    $stockPanelCode = $stockPanelCode ?: $sourcePanelCode;
    $ok = 0;
    foreach (nmStockPanelProducts($sourcePanelCode, $agent) as $product) {
        try {
            $cat = $product['category'] ?? null;
            $catName = null; $catId = null;
            if ($cat !== null && trim((string)$cat) !== '') {
                $vals = nmCategoryLookupValues($cat);
                $catName = $vals ? end($vals) : $cat;
                $catId = is_numeric($cat) ? (string)$cat : null;
            }
            $stmt = $pdo->prepare("INSERT INTO nm_stock_product_map (source_codepanel,stock_codepanel,codeproduct,category,category_id,category_name,volume_gb,service_days,price,status,created_at,updated_at) VALUES (:source,:stock,:code,:cat,:catid,:catname,:vol,:days,:price,'active',:created_at,:updated_at) ON DUPLICATE KEY UPDATE category=VALUES(category), category_id=VALUES(category_id), category_name=VALUES(category_name), volume_gb=VALUES(volume_gb), service_days=VALUES(service_days), price=VALUES(price), status='active', updated_at=VALUES(updated_at)");
            $stmt->execute([':source'=>$sourcePanelCode, ':stock'=>$stockPanelCode, ':code'=>$product['code_product'] ?? 'auto', ':cat'=>$cat, ':catid'=>$catId, ':catname'=>$catName, ':vol'=>(float)($product['Volume_constraint'] ?? 0), ':days'=>(int)($product['Service_time'] ?? 0), ':price'=>(int)($product['price_product'] ?? 0), ':created_at'=>time(), ':updated_at'=>time()]);
            $ok++;
        } catch (Throwable $e) { error_log('nmStockSyncProductMap item failed: ' . $e->getMessage()); }
    }
    return $ok;
}
function nmStockReserveForProduct(array $panel, array $product, $userId, $invoiceId, $mode = 'national_buy')
{
    $stockPanel = nmPanelResolveStockCode($panel);
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $volume = $product['Volume_constraint'] ?? 0;
    $stock = nmStockReserveOne($stockPanel, $productCode, $volume, $userId, $invoiceId, $mode);
    if (!$stock && $sourcePanel !== $stockPanel) $stock = nmStockReserveOne($sourcePanel, $productCode, $volume, $userId, $invoiceId, $mode);
    if (!$stock) $stock = nmStockReserveByShelfMatch($panel, $product, $userId, $invoiceId, $mode);
    if (!$stock) $stock = nmStockReserveOne($stockPanel, 'auto', $volume, $userId, $invoiceId, $mode);
    if (!$stock && $sourcePanel !== $stockPanel) $stock = nmStockReserveOne($sourcePanel, 'auto', $volume, $userId, $invoiceId, $mode);


    if (!$stock) $stock = nmStockReserveByShelfLoose($panel, $product, $userId, $invoiceId, $mode);
    return $stock;
}

if (!function_exists('nmStockReserveByShelfLoose')) {
function nmStockReserveByShelfLoose(array $panel, array $product, $userId, $invoiceId, $mode = 'fallback_loose')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $stockPanel = function_exists('nmPanelResolveStockCode') ? nmPanelResolveStockCode($panel) : (trim((string)($panel['code_panel'] ?? '')) ?: 'auto');
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function ($v) {
        return trim((string)$v) !== '';
    })));
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $productName = function_exists('nmNormalizeText') ? nmNormalizeText($product['name_product'] ?? '') : (string)($product['name_product'] ?? '');
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

        $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s INNER JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND sh.status='active' AND (s.codepanel IN (" . implode(',', $panelWhereSqlS) . ") OR sh.source_codepanel IN (" . implode(',', $panelWhereSqlSrc) . ") OR sh.stock_codepanel IN (" . implode(',', $panelWhereSqlSt) . ")) AND (sh.codeproduct=:product_code_shelf_where OR s.codeproduct=:product_code_stock_where OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :volume_where) < 0.001 AND sh.service_days=:days_where)) ORDER BY s.id ASC LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
        $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
        if ($upd->rowCount() < 1) return false;
        if (function_exists('nmStockLog')) nmStockLog($row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'product' => $productCode, 'shelf_loose' => true]);
        return $row;
    } catch (Throwable $e) { error_log('nmStockReserveByShelfLoose failed: ' . $e->getMessage()); return false; }
}}
function nmStockCompleteBuyFromInventory($userId, array $userRow, array $panel, array $product, $invoiceId, $usernameAc = '', $chargeBalance = true, $mode = 'national_buy')
{
    $stock = nmStockReserveForProduct($panel, $product, $userId, $invoiceId, $mode);
    if (!$stock) return false;
    try {
        if ($chargeBalance) {
            $__pp3 = (float)($product['price_product'] ?? 0);
            if ($__pp3 > 0) {
                if (function_exists('balance_atomic_charge')) {
                    $__allowNeg3 = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
                    balance_atomic_charge($userId, $__pp3, $__allowNeg3);
                } else {
                    update('user', 'Balance', (float)($userRow['Balance'] ?? 0) - $__pp3, 'id', $userId);
                }
            }
        }
        update('invoice', 'Status', 'active', 'id_invoice', $invoiceId);
        update('invoice', 'user_info', $stock['content'], 'id_invoice', $invoiceId);
        try { update('invoice', 'source_panel_code', $panel['code_panel'] ?? '', 'id_invoice', $invoiceId); } catch (Throwable $e) {}
    } catch (Throwable $e) { error_log('nmStockCompleteBuyFromInventory update failed: ' . $e->getMessage()); }
    $invoice = ['id_user'=>$userId, 'id_invoice'=>$invoiceId, 'username'=>$usernameAc, 'Service_location'=>$panel['name_panel'] ?? '', 'name_product'=>$product['name_product'] ?? '', 'Volume'=>$product['Volume_constraint'] ?? 0, 'Service_time'=>$product['Service_time'] ?? 0];
    nmStockDeliverConfig($stock, $invoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار پشتیبان تحویل شد');

    if (function_exists('nmStockNotifyExtend')) {
        $notifyMode = (strpos((string)$mode, 'emergency') !== false) ? 'emergency' : 'stock';
        nmStockNotifyExtend($userId, $userRow, $invoice, $product, $panel, $notifyMode, (string)$usernameAc, (float)($product['price_product'] ?? 0), 'buy');
    }
    return $stock;
}

function nmStockShelves($panelCode = null, $onlyActive = true)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return [];
    $where = $onlyActive ? "status='active'" : "1=1";
    $params = [];
    if ($panelCode !== null && trim((string)$panelCode) !== '') { $where .= " AND (source_codepanel=:panel_source OR stock_codepanel=:panel_stock OR source_codepanel='auto')"; $params[':panel_source'] = (string)$panelCode; $params[':panel_stock'] = (string)$panelCode; }
    try { $stmt = $pdo->prepare("SELECT * FROM nm_stock_shelves WHERE $where ORDER BY id DESC"); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { error_log('nmStockShelves failed: '.$e->getMessage()); return []; }
}
function nmStockShelfById($id)
{
    global $pdo;
    if (!nmStockEnsureSchema()) { error_log('[STOCK_SHELF_BY_ID] schema_not_ready id=' . var_export($id, true)); return false; }
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

/**
 * --- Durable stock-import session -------------------------------------------
 * The bulk/single import flow used to carry its whole state (shelf id, mode,
 * has_sub flag, pending config, imported count) ONLY inside the shared
 * "Processing_value" JSON. That field is reset by many unrelated handlers —
 * to "0" (/start, back button, Add_Balance, rcc_cancel, recheckcrypto, ...)
 * and to the bare panel name (e.g. "fox") by the "بازگشت به منوی مدیریت"
 * handler. When that happened mid-import the session was wiped, producing:
 *   - "❌ انبار انتخابی معتبر نیست."  (shelf id lost)
 *   - "❌ کانفیگ pending پیدا نشد."   (pending config lost)
 *
 * To make the session immune to those resets we keep it in a dedicated user
 * column ("nm_stock_session") that NO other handler touches. Every stock step
 * reads/writes the session through these helpers. We still mirror the shelf id
 * into "nm_shelf_active" + Processing_value for backward compatibility / migration.
 */
function nmStockEnsureColumn($column, $ddlType)
{
    static $ensured = [];
    if (isset($ensured[$column])) return;
    $ensured[$column] = true;
    global $pdo;
    if (!($pdo instanceof PDO)) return;
    try {
        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'user' AND COLUMN_NAME = ?");
        $stmt->execute([$db, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE user ADD `{$column}` {$ddlType} NULL");
        }
    } catch (Throwable $e) {
        error_log("nmStockEnsureColumn({$column}) failed: " . $e->getMessage());
    }
}
function nmStockEnsureActiveShelfColumn()
{
    nmStockEnsureColumn('nm_shelf_active', 'VARCHAR(32)');
    nmStockEnsureColumn('nm_stock_session', 'TEXT');
}

/**
 * Read the durable import session as an array.
 * Falls back to the legacy Processing_value keys / nm_shelf_active so sessions
 * started under the old code keep working after this update is deployed.
 */
function nmStockSessionGet($user)
{
    nmStockEnsureActiveShelfColumn();
    $session = [];
    $raw = is_array($user) ? ($user['nm_stock_session'] ?? '') : '';
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $session = $decoded;
    }
    // Migration / fallback from the old Processing_value-based state.
    if (is_array($user)) {
        $pv = $user['Processing_value'] ?? '';
        $legacy = (is_string($pv) && $pv !== '') ? json_decode($pv, true) : null;
        if (is_array($legacy)) {
            $map = [
                'nm_shelf_id'          => 'shelf_id',
                'nm_stock_import_mode' => 'mode',
                'nm_stock_has_sub'     => 'has_sub',
                'nm_stock_pending_cfg' => 'pending_cfg',
                'nm_stock_imported'    => 'imported',
            ];
            foreach ($map as $oldKey => $newKey) {
                if (!array_key_exists($newKey, $session) && array_key_exists($oldKey, $legacy)) {
                    $session[$newKey] = $legacy[$oldKey];
                }
            }
        }
        if (empty($session['shelf_id']) && !empty($user['nm_shelf_active'])) {
            $session['shelf_id'] = (int)$user['nm_shelf_active'];
        }
    }
    return $session;
}

/**
 * Merge key/values into the durable session. Always reads the freshest copy
 * from the DB first (cache disabled) so multiple patches within one request
 * accumulate instead of clobbering each other.
 */
function nmStockSessionPatch($fromId, array $kv)
{
    nmStockEnsureActiveShelfColumn();
    $session = [];
    if (function_exists('select')) {
        $row = select("user", "nm_stock_session", "id", $fromId, "select", ['cache' => false]);
        $raw = is_array($row) ? ($row['nm_stock_session'] ?? '') : '';
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $session = $decoded;
        }
    }
    foreach ($kv as $k => $v) { $session[$k] = $v; }
    if (function_exists('update')) {
        update("user", "nm_stock_session", json_encode($session, JSON_UNESCAPED_UNICODE), "id", $fromId);
    }
    return $session;
}

function nmStockSessionClear($fromId)
{
    nmStockEnsureActiveShelfColumn();
    if (function_exists('update')) {
        update("user", "nm_stock_session", "", "id", $fromId);
        update("user", "nm_shelf_active", "0", "id", $fromId);
    }
}

/**
 * Persist the chosen shelf into the durable session (+ legacy mirrors).
 */
function nmStockSetActiveShelf($fromId, $shelfId)
{
    $shelfId = (int)$shelfId;
    if ($shelfId <= 0) return;
    if (function_exists('savedata')) {
        savedata("save", "nm_shelf_id", $shelfId); // legacy mirror
    }
    nmStockEnsureActiveShelfColumn();
    if (function_exists('update')) {
        update("user", "nm_shelf_active", (string)$shelfId, "id", $fromId);
    }
    nmStockSessionPatch($fromId, ['shelf_id' => $shelfId]);
}

/**
 * Backward-compatible alias kept for the session-end clears.
 */
function nmStockClearActiveShelf($fromId)
{
    nmStockSessionClear($fromId);
}

/**
 * Resolve the shelf currently being imported into.
 * Priority: explicit callback id -> durable session -> legacy
 * Processing_value['nm_shelf_id'] -> nm_shelf_active column.
 * Returns the shelf row, or false if none can be resolved.
 */
function nmStockActiveShelf($user, array $data = [], $callbackShelfId = null)
{
    $candidates = [];
    if ($callbackShelfId !== null && (int)$callbackShelfId > 0) {
        $candidates[] = (int)$callbackShelfId;
    }
    $session = nmStockSessionGet($user);
    if (!empty($session['shelf_id'])) {
        $candidates[] = (int)$session['shelf_id'];
    }
    if (!empty($data['nm_shelf_id'])) {
        $candidates[] = (int)$data['nm_shelf_id'];
    }
    if (is_array($user) && !empty($user['nm_shelf_active'])) {
        $candidates[] = (int)$user['nm_shelf_active'];
    }
    foreach (array_values(array_unique($candidates)) as $cid) {
        if ($cid <= 0) continue;
        $shelf = nmStockShelfById($cid);
        if ($shelf) return $shelf;
    }
    return false;
}

/**
 * Re-show the shelf-selection list and step back into the import flow.
 * Used instead of bouncing to the admin menu when a shelf can't be resolved,
 * so the admin stays inside the stock flow and can simply re-pick.
 */
function nmStockRepromptShelf($fromId, $user)
{
    global $setting;
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : false;
    $kb = nmStockShelfKeyboard(is_array($panel) ? ($panel['code_panel'] ?? null) : null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decoded = json_decode($kb, true);
        if (isset($decoded['keyboard'])) $kb = rx_keyboardToInline($decoded);
    }
    $msg = "❌ انبار انتخابی پیدا نشد یا اطلاعات آن از بین رفته است.\n📌 لطفاً دوباره از لیست زیر انبار موردنظر را انتخاب کنید.";
    if (function_exists('nm_replyOrEdit')) {
        nm_replyOrEdit($fromId, $msg, $kb, 'HTML');
    } else {
        sendmessage($fromId, $msg, $kb, 'HTML');
    }
    if (function_exists('step')) step('nm_stock_select_shelf_import', $fromId);
}

/**
 * True when at least one product at this location/agent belongs to a category
 * that actually exists in the `category` table — i.e. the shop is set up to
 * sell via categories. Used to decide whether the national-net / emergency
 * flow should show the category picker or fall back to a direct product list.
 */
/**
 * Return the category rows that actually have at least one sellable product for
 * the given panel + agent.
 *
 * This deliberately matches in PHP (not via a SQL JOIN) because:
 *   - product.category is utf8mb4_unicode_ci while category.remark is utf8mb4_bin,
 *     so a direct "p.category = c.remark" JOIN throws "illegal mix of collations"
 *     and silently returned no categories (the national-net "empty category list"
 *     bug). Comparing in PHP avoids the collation clash entirely.
 *   - products may reference a category by its remark OR its id OR name/title, and
 *     with stray whitespace/case differences. We normalise both sides so they match.
 */
if (!function_exists('nmSellableCategoryRows')) {
function nmSellableCategoryRows($location, $agent)
{
    global $pdo;
    $out = [];
    if (!($pdo instanceof PDO)) return $out;
    $norm = static function ($v) {
        return function_exists('nmNormalizeText') ? nmNormalizeText($v) : trim((string)$v);
    };
    // 1) Collect the (normalised) category tokens that products for this panel use.
    $prodSet = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT category FROM product "
            . "WHERE (Location = :loc OR Location = '/all') "
            . "AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL) "
            . "AND category IS NOT NULL AND TRIM(category) <> ''"
        );
        $stmt->execute([':loc' => $location, ':agent' => $agent]);
        foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $pc) {
            $n = $norm($pc);
            if ($n !== '' && $n !== '0') $prodSet[$n] = true;
        }
    } catch (Throwable $e) {
        error_log('nmSellableCategoryRows products failed: ' . $e->getMessage());
        return $out;
    }
    if (!$prodSet) return $out;
    // 2) Keep the category rows whose remark/id/name/title matches a product token.
    try {
        $stmt = $pdo->query("SELECT * FROM category ORDER BY id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (['remark', 'id', 'name', 'title'] as $k) {
                if (!isset($row[$k]) || trim((string)$row[$k]) === '') continue;
                if (isset($prodSet[$norm($row[$k])])) { $out[] = $row; break; }
            }
        }
    } catch (Throwable $e) {
        error_log('nmSellableCategoryRows categories failed: ' . $e->getMessage());
    }
    return $out;
}
}

function nmHasSellableCategories($location, $agent)
{
    return count(nmSellableCategoryRows($location, $agent)) > 0;
}

function nmStockShelfCreate($name, array $sourcePanel, array $product, $categoryId = null, $categoryName = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $now=time();
    $sourceCode = trim((string)($sourcePanel['code_panel'] ?? '')) ?: 'auto';
    $stockCode = nmPanelResolveStockCode($sourcePanel);
    if ($stockCode === 'auto') $stockCode = $sourceCode;
    if (($categoryName === null || $categoryName === '') && !empty($product['category'])) $categoryName = $product['category'];
    try {
        $stmt=$pdo->prepare("INSERT INTO nm_stock_shelves (name,source_codepanel,stock_codepanel,category_id,category_name,codeproduct,product_name,volume_gb,service_days,price,status,created_at,updated_at) VALUES (:name,:source,:stock,:catid,:catname,:code,:pname,:vol,:days,:price,'active',:created_at,:updated_at) ON DUPLICATE KEY UPDATE stock_codepanel=VALUES(stock_codepanel), category_id=VALUES(category_id), category_name=VALUES(category_name), codeproduct=VALUES(codeproduct), product_name=VALUES(product_name), volume_gb=VALUES(volume_gb), service_days=VALUES(service_days), price=VALUES(price), status='active', updated_at=VALUES(updated_at)");
        $stmt->execute([':name'=>(string)$name, ':source'=>$sourceCode, ':stock'=>$stockCode, ':catid'=>$categoryId, ':catname'=>$categoryName, ':code'=>$product['code_product'] ?? 'auto', ':pname'=>$product['name_product'] ?? '', ':vol'=>(float)($product['Volume_constraint'] ?? 0), ':days'=>(int)($product['Service_time'] ?? 0), ':price'=>(int)($product['price_product'] ?? 0), ':created_at'=>$now, ':updated_at'=>$now]);
        return true;
    } catch (Throwable $e) { error_log('nmStockShelfCreate failed: '.$e->getMessage()); return false; }
}
function nmStockShelfKeyboard($panelCode, $callbackPrefix = null)
{
    $rows=[];
    foreach (nmStockShelves($panelCode) as $shelf) {
        $shelfName = trim((string)($shelf['name'] ?? ''));
        if ($shelfName === '' || $shelfName[0] === '{' || $shelfName[0] === '[') continue;
        $label = '📦 '.$shelfName.' - '.$shelf['product_name'].' ('.$shelf['volume_gb'].'GB/'.$shelf['service_days'].'روز)';
        $rows[] = [['text'=>$label]];
    }
    $rows[] = [['text'=>'➕ افزودن انبار مدنظر']];
    $rows[] = [['text'=>'🔙 بازگشت به انبار']];
    return json_encode(['keyboard'=>$rows,'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Stock management keyboard (main "📦 انبار شبکه ملی" menu).
 * Centralized so back-navigation handlers can rebuild it.
 * Buttons carry per-button "style" hints (success/primary/danger/default)
 * which rx_kb_encode picks up under inline mode for coloured glass buttons.
 */
function nmStockManageKeyboard()
{
    global $setting;
    $stylesAll = [];
    if (function_exists('select')) {
        $row = select('setting', 'keyboard_styles_all', null, null, 'select');
        if (is_array($row) && !empty($row['keyboard_styles_all'])) {
            $decoded = json_decode($row['keyboard_styles_all'], true);
            if (is_array($decoded)) $stylesAll = $decoded;
        }
    }
    $sectionStyles = is_array($stylesAll['admin_panel_stock_manage'] ?? null) ? $stylesAll['admin_panel_stock_manage'] : [];
    if (function_exists('rx_getKeyboardDefaultStyles')) {
        $defaults = rx_getKeyboardDefaultStyles('admin_panel_stock_manage');
        if (is_array($defaults)) $sectionStyles = $sectionStyles + $defaults;
    }
    $useInline = isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline';
    $mk = function(string $text) use ($sectionStyles, $useInline): array {
        $btn = ['text' => $text];
        // Inline mode requires callback_data on every button; reply mode does not.
        if ($useInline) {
            if (function_exists('rx_makeAdminPanelCallback')) {
                $btn['callback_data'] = rx_makeAdminPanelCallback($text);
            } elseif (function_exists('rx_safeCallbackData')) {
                $btn['callback_data'] = rx_safeCallbackData($text, $text);
            } else {
                $btn['callback_data'] = strlen($text) <= 64 ? $text : substr(md5($text), 0, 32);
            }
        }
        if (function_exists('rx_kb_style')) {
            $btn = rx_kb_style($btn, $text, $sectionStyles);
        } elseif (!empty($sectionStyles[$text]) && $sectionStyles[$text] !== 'default') {
            $btn['style'] = $sectionStyles[$text];
        }
        return $btn;
    };
    $backText = $GLOBALS['textbotlang']['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
    $backBtn  = ['text' => $backText];
    if ($useInline) {
        // backadmin has its own callback handled in bootstrap; mirror admin_panels.php.
        $backBtn['callback_data'] = 'admin';
    }
    $rows = [
        [$mk("➕ افزودن انبار مدنظر")],
        [$mk("➕ وارد کردن دسته‌ای انبار"), $mk("➕ افزودن کانفیگ تکی انبار")],
        [$mk("✏️ ویرایش انبار"), $mk("❌ حذف کانفیگ انبار")],
        [$mk("🗑 حذف کامل انبار")],
        [$mk("📊 گزارش موجودی انبار")],
        [$mk("🔄 همگام‌سازی محصولات انبار")],
        [$mk("🚨 پنل اضطراری"), $mk("🌐 وضعیت نت ملی")],
        [$backBtn],
    ];
    if (function_exists('rx_kb_encode')) {
        return rx_kb_encode($rows);
    }
    $arr = ['keyboard' => $rows, 'resize_keyboard' => true];
    if ($useInline && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($arr);
    }
    return json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Slim national-net / emergency keyboard. Shown after a user toggles
 * "🌐 وضعیت نت ملی" or "🚨 پنل اضطراری" — only the buttons that make
 * sense in that context.
 */
function nmNationalEmergencyMenuKeyboard()
{
    global $setting;
    $arr = [
        'keyboard' => [
            [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
            [['text' => "💡 روش ساخت نام کاربری"]],
            [['text' => "📦 انبار شبکه ملی"]],
            [['text' => "📌 ثبت پنل اضطراری"]],
            [['text' => "🚨 پنل اضطراری"], ['text' => "🌐 وضعیت نت ملی"]],
            [['text' => $GLOBALS['textbotlang']['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت'], ['text' => $GLOBALS['textbotlang']['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل']],
        ],
        'resize_keyboard' => true,
    ];
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($arr);
    }
    return json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Paginated inline list of configs for a given shelf.
 * Used by the new "نمایش لیست کانفیگ‌ها" delete flow.
 */
function nmStockConfigInlineList($shelfId, $page = 0, $perPage = 10, $callbackPrefix = 'nm_cfg')
{
    global $pdo;
    $shelfId = (int)$shelfId;
    $page = max(0, (int)$page);
    $perPage = max(1, (int)$perPage);
    $offset = $page * $perPage;
    $rows = [];
    try {
        $stmt = $pdo->prepare("SELECT id, content, status, sub_link FROM nm_config_stock WHERE shelf_id = :sid AND status = 'active' ORDER BY id ASC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':sid', $shelfId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $perPage + 1, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('nmStockConfigInlineList failed: ' . $e->getMessage());
        return ['keyboard' => json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE), 'count' => 0, 'has_next' => false];
    }
    $hasNext = count($rows) > $perPage;
    if ($hasNext) array_pop($rows);

    $inline = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $content = (string)$r['content'];
        $info = nmStockExtractConfigInfo($content);
        $label = '#' . $id . ' • ' . ($info['username'] !== '' ? $info['username'] : '—') . ($info['port'] !== '' ? ' :' . $info['port'] : '');
        if (mb_strlen($label, 'UTF-8') > 60) $label = mb_substr($label, 0, 60, 'UTF-8') . '…';
        $inline[] = [['text' => $label, 'callback_data' => $callbackPrefix . ':view:' . $id]];
    }

    $nav = [];
    if ($page > 0) $nav[] = ['text' => '⬅️ قبلی', 'callback_data' => $callbackPrefix . ':page:' . ($page - 1)];
    if ($hasNext) $nav[] = ['text' => 'بعدی ➡️', 'callback_data' => $callbackPrefix . ':page:' . ($page + 1)];
    if (!empty($nav)) $inline[] = $nav;
    $inline[] = [['text' => '❌ بستن', 'callback_data' => $callbackPrefix . ':close']];

    return [
        'keyboard' => json_encode(['inline_keyboard' => $inline], JSON_UNESCAPED_UNICODE),
        'count'    => count($rows),
        'has_next' => $hasNext,
        'page'     => $page,
    ];
}

/**
 * Best-effort extraction of username/port/host from a config payload
 * (vmess / vless / trojan / ss / wireguard json or text).
 */
function nmStockExtractConfigInfo($raw)
{
    $out = ['username' => '', 'port' => '', 'host' => '', 'type' => ''];
    $s = trim((string)$raw);
    if ($s === '') return $out;

    if (stripos($s, 'vmess://') === 0) {
        $out['type'] = 'vmess';
        $b64 = substr($s, 8);
        $b64 = strtr($b64, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        $decoded = @base64_decode($b64, true);
        if ($decoded !== false) {
            $j = json_decode($decoded, true);
            if (is_array($j)) {
                $out['username'] = (string)($j['ps'] ?? $j['remarks'] ?? $j['id'] ?? '');
                $out['host']     = (string)($j['add'] ?? '');
                $out['port']     = (string)($j['port'] ?? '');
            }
        }
        return $out;
    }

    if (preg_match('#^(?:vless|trojan|ss)://([^@\s]+)@([^:/?#]+):(\d+)(.*)$#i', $s, $m)) {
        $out['type']     = strtolower(strstr($s, ':', true));
        $out['username'] = $m[1];
        $out['host']     = $m[2];
        $out['port']     = $m[3];
        if (preg_match('/#([^#\s]+)$/u', $m[4], $mm)) {
            $out['username'] = rawurldecode($mm[1]);
        }
        return $out;
    }

    if ($s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) {
            $out['username'] = (string)($j['username'] ?? $j['name'] ?? $j['remarks'] ?? '');
            $out['host']     = (string)($j['host']     ?? $j['server'] ?? $j['endpoint'] ?? '');
            $out['port']     = (string)($j['port']     ?? '');
        }
    }
    return $out;
}
function nmStockShelfStatusText($panelCode = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return "❌ خطا در آماده‌سازی انبار";
    $lines=["📦 انبارداری شبکه‌ملی", "", "هر انبار به یک دسته/محصول وصل می‌شود؛ بنابراین ربات می‌فهمد کانفیگ واردشده مربوط به محصول ۱۰ گیگ، ۲۰ گیگ و ... است.", ""];
    $shelves = nmStockShelves($panelCode, false);
    if (!$shelves) return implode("\n", $lines)."❌ هنوز انباری تعریف نشده است.";
    foreach ($shelves as $shelf) {
        $cnt=0; $reserved=0; $delivered=0;
        try {
            $st=$pdo->prepare("SELECT status, COUNT(*) cnt FROM nm_config_stock WHERE shelf_id=:sid GROUP BY status");
            $st->execute([':sid'=>$shelf['id']]);
            while ($row=$st->fetch(PDO::FETCH_ASSOC)) {
                if (($row['status'] ?? '') === 'active') $cnt=(int)$row['cnt'];
                elseif (($row['status'] ?? '') === 'reserved') $reserved=(int)$row['cnt'];
                elseif (($row['status'] ?? '') === 'delivered') $delivered=(int)$row['cnt'];
            }
        } catch (Throwable $e) {}
        $lines[] = "• {$shelf['name']} | {$shelf['product_name']} | {$shelf['volume_gb']}GB | {$shelf['service_days']} روز | موجودی آماده: {$cnt} | تحویل‌شده: {$delivered} | رزرو: {$reserved}";
    }
    return implode("\n", $lines);
}