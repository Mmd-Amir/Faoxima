<?php

function nmStockProductsForExtend(array $panel, $agent = 'all', $onlyAvailable = false)
{
    global $pdo;
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
            if ($onlyAvailable && !nmStockHasAvailableForProduct($panel, $row)) continue;
            $rows[] = $row;
        }
    } catch (Throwable $e) { error_log('nmStockProductsForExtend failed: ' . $e->getMessage()); }
    return $rows;
}

function nmStockResolveProductToken($token, array $panel, $agent = 'all')
{
    global $pdo;
    $token = trim((string)$token);
    $params = [':loc' => $panel['name_panel'] ?? ''];
    if (preg_match('/^pid_([0-9]+)$/', $token, $m)) { $sql = "SELECT * FROM product WHERE id=:id AND (FIND_IN_SET(:loc, Location)>0 OR Location='/all') LIMIT 1"; $params[':id'] = (int)$m[1]; }
    else { $sql = "SELECT * FROM product WHERE code_product=:code AND (FIND_IN_SET(:loc, Location)>0 OR Location='/all') LIMIT 1"; $params[':code'] = $token; }
    $agent = trim((string)$agent);
    if ($agent !== '' && $agent !== 'all') { $sql = str_replace(' LIMIT 1', " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1", $sql); $params[':agent'] = $agent; }
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: false; }
    catch (Throwable $e) { error_log('nmStockResolveProductToken failed: ' . $e->getMessage()); return false; }
}

if (!function_exists('nmStockStyles')) {
    function nmStockStyles()
    {
        global $setting;
        $styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $all = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($all) && !empty($all['user_dynamic_lists'])) {
                $styles = $all['user_dynamic_lists'];
            }
        }
        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $styles = $styles + rx_getKeyboardDefaultStyles('user_dynamic_lists');
        }
        return $styles;
    }
}

function nmStockExtendProductKeyboard(array $invoice, array $userRow, array $panel)
{
    $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($invoice['id_invoice'] ?? ''));
    $isNational = nmPanelNationalEnabled($panel);
    $products = nmStockProductsForExtend($panel, $userRow['agent'] ?? 'all', $isNational);
    if (!$products) return false;
    $styles = nmStockStyles();
    $rows = [];
    foreach ($products as $product) {
        $code = trim((string)($product['code_product'] ?? ''));
        $token = ($code !== '' && strlen('nmstocksel_' . $code . '_' . $invoiceId) <= 64) ? $code : 'pid_' . (string)($product['id'] ?? '0');
        $name = trim((string)($product['name_product'] ?? 'محصول'));
        $price = number_format((float)($product['price_product'] ?? 0));
        $vol = trim((string)($product['Volume_constraint'] ?? ''));
        $volLabel = ($vol !== '' && intval($vol) === 0) ? 'نامحدود' : ($vol !== '' ? "{$vol}GB" : '');
        $days = trim((string)($product['Service_time'] ?? ''));
        $label = $name . " - {$price} تومان";
        if ($vol !== '' || $days !== '') $label .= " ({$volLabel} / {$days} روز)";
        $rows[] = [rx_kb_style(['text' => $label, 'callback_data' => 'nmstocksel_' . $token . '_' . $invoiceId], 'nmstock_actions', $styles)];
    }
    $rows[] = [rx_kb_style(['text' => '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder'], 'backorder', $styles)];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmStockSafeUsername($userId, $current = '')
{
    $base = preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', (string)$current));
    if ($base === '' || strlen($base) < 3) $base = preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', (string)$userId)) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    if (strlen($base) > 28) $base = substr($base, 0, 28);
    if (strlen($base) < 3) $base = 'u' . substr(bin2hex(random_bytes(6)), 0, 10);
    return $base;
}

function nmStockConvertInvoiceToPanelService($chatId, array $userRow, array $invoice, array $panel, array $product)
{
    global $ManagePanel, $setting, $errorreport, $keyboard;
    $price = (float)($product['price_product'] ?? 0);
    if ((float)($userRow['Balance'] ?? 0) < $price && ($userRow['agent'] ?? '') !== 'n2') { sendmessage($chatId, '❌ موجودی کیف پول برای تمدید کافی نیست.', null, 'HTML'); return false; }
    $username = nmStockSafeUsername($chatId, $invoice['username'] ?? '');
    try { $check = $ManagePanel->DataUser($panel['name_panel'], $username); if (is_array($check) && isset($check['username'])) $username = nmStockSafeUsername($chatId, $chatId . '-' . substr(bin2hex(random_bytes(4)), 0, 8)); } catch (Throwable $e) { }
    $days = (int)($product['Service_time'] ?? 0);
    $expire = $days > 0 ? strtotime('+' . $days . ' days') : 0;
    $datac = ['expire' => $expire, 'data_limit' => (float)($product['Volume_constraint'] ?? 0) * pow(1024, 3), 'from_id' => $chatId, 'username' => '', 'type' => 'buy'];
    $dataoutput = $ManagePanel->createUser($panel['name_panel'], $product['code_product'], $username, $datac);
    if (!is_array($dataoutput) || empty($dataoutput['username'])) {
        $msg = isset($dataoutput['msg']) ? json_encode($dataoutput['msg'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'unknown';
        error_log('nmStockConvertInvoiceToPanelService createUser failed: ' . $msg);
        sendmessage($chatId, '❌ تمدید از پنل اصلی انجام نشد. لطفاً گزارش خطا را بررسی کنید.', null, 'HTML');
        if (!empty($setting['Channel_Report'])) telegram('sendmessage', ['chat_id' => $setting['Channel_Report'], 'message_thread_id' => $errorreport ?? null, 'text' => "خطا در تبدیل سرویس انبار به پنل اصلی
پنل: {$panel['name_panel']}
کاربر: {$chatId}
خطا: {$msg}", 'parse_mode' => 'HTML']);
        return false;
    }
    if (function_exists('balance_atomic_charge')) {
        $__allowNegBl = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
        balance_atomic_charge($chatId, (float)$price, $__allowNegBl);
    } else {
        update('user', 'Balance', (float)($userRow['Balance'] ?? 0) - $price, 'id', $chatId);
    }
    update('invoice', 'username', $dataoutput['username'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Service_location', $panel['name_panel'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'name_product', $product['name_product'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'price_product', $product['price_product'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Volume', $product['Volume_constraint'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Service_time', $product['Service_time'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'time_sell', time(), 'id_invoice', $invoice['id_invoice']);
    try { update('invoice', 'user_info', '', 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}
    try { update('invoice', 'source_panel_code', '', 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}
    $subLink = $dataoutput['subscription_url'] ?? '';
    $configs = $dataoutput['configs'] ?? [];

    $volumeLabel = intval($product['Volume_constraint'] ?? 0) === 0 ? 'نامحدود' : ($product['Volume_constraint'] . ' گیگ');
    $caption = "✅ وضعیت نت ملی خاموش است؛ سرویس شما از پنل اصلی تمدید و جایگزین شد.

👤 نام سرویس: <code>{$dataoutput['username']}</code>
🛍 محصول: {$product['name_product']}
⏳ مدت: {$product['Service_time']} روز
🗜 حجم: {$volumeLabel}";
    if (trim((string)$subLink) !== '') {
        $caption .= "\n\n🔗 لینک اشتراک:\n<code>" . htmlspecialchars((string)$subLink, ENT_QUOTES, 'UTF-8') . "</code>";
    }
    if (is_array($configs) && count($configs) > 0) {
        $cfgClean = array_values(array_filter($configs, static function ($v) { return is_string($v) && trim($v) !== ''; }));
        if (!empty($cfgClean)) {
            $caption .= "\n\n⚙️ کانفیگ‌ها:";
            foreach ($cfgClean as $idx => $cfg) {
                $caption .= "\n\n<b>" . ($idx + 1) . ".</b>\n<code>" . htmlspecialchars($cfg, ENT_QUOTES, 'UTF-8') . "</code>";
            }
        }
    }
    if (function_exists('sendMessageService')) sendMessageService($panel, $configs, $subLink, $dataoutput['username'], null, $caption, $invoice['id_invoice'], $chatId); else sendmessage($chatId, $caption, $keyboard ?? null, 'HTML');

    nmStockNotifyExtend($chatId, $userRow, $invoice, $product, $panel, 'panel', $dataoutput['username'] ?? '', $price);
    return true;
}

if (!function_exists('nmStockNotifyExtend')) {


function nmStockNotifyExtend($chatId, array $userRow, array $invoice, array $product, array $panel, $mode = 'stock', $newUsername = '', $price = 0, $action = 'extend')
{
    global $admin_ids, $setting, $buyreport, $otherreport;
    try {
        $modeLabel = $mode === 'panel' ? '📡 از پنل اصلی' : '📦 از انبار شبکه‌ملی';
        $usernameTg = trim((string)($userRow['username'] ?? ''));
        if ($usernameTg === '') $usernameTg = '---';
        $price = (float)$price;
        $title = ($action === 'buy') ? '🛒 خرید سرویس انجام شد' : '♻️ تمدید سرویس انجام شد';
        $msg = "{$title} ({$modeLabel})\n\n"
            . "<blockquote>👤 آیدی کاربر: <code>{$chatId}</code></blockquote>\n"
            . "<blockquote>🔖 یوزرنیم تلگرام: @" . htmlspecialchars($usernameTg, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
            . "<blockquote>🪪 یوزرنیم سرویس: <code>" . htmlspecialchars((string)($newUsername !== '' ? $newUsername : ($invoice['username'] ?? '')), ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
            . "<blockquote>🛍 محصول: " . htmlspecialchars((string)($product['name_product'] ?? ''), ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
            . "<blockquote>📍 پنل: " . htmlspecialchars((string)($panel['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
            . "<blockquote>🗜 حجم: " . htmlspecialchars((string)($product['Volume_constraint'] ?? ''), ENT_QUOTES, 'UTF-8') . " گیگ</blockquote>\n"
            . "<blockquote>⏳ مدت: " . htmlspecialchars((string)($product['Service_time'] ?? ''), ENT_QUOTES, 'UTF-8') . " روز</blockquote>\n"
            . "<blockquote>💰 مبلغ: " . number_format($price) . " تومان</blockquote>\n"
            . "<blockquote>🧾 کد سرویس: <code>" . htmlspecialchars((string)($invoice['id_invoice'] ?? ''), ENT_QUOTES, 'UTF-8') . "</code></blockquote>";
        if (!empty($setting['Channel_Report'])) {
            $payload = ['chat_id' => $setting['Channel_Report'], 'text' => $msg, 'parse_mode' => 'HTML'];

            $threadId = !empty($buyreport) ? $buyreport : (!empty($otherreport) ? $otherreport : null);
            if ($threadId) $payload['message_thread_id'] = $threadId;
            telegram('sendmessage', $payload);
        }
        if (is_array($admin_ids)) {
            foreach ($admin_ids as $adminId) {
                if ((string)$adminId === (string)$chatId) continue;
                telegram('sendmessage', ['chat_id' => $adminId, 'text' => $msg, 'parse_mode' => 'HTML']);
            }
        }
    } catch (Throwable $e) { error_log('nmStockNotifyExtend failed: ' . $e->getMessage()); }
}}


if (!function_exists('nmJalaliDate')) {
function nmJalaliDate($format='Y/m/d',$ts=null){
    if($ts===null||$ts===''||$ts===false)$ts=time();
    if(!is_numeric($ts)){ $x=strtotime((string)$ts); $ts=$x!==false?$x:time(); }
    if(function_exists('jdate')){ try{return jdate($format,(int)$ts);}catch(Throwable $e){} }
    return date($format,(int)$ts);
}}
if (!function_exists('nmInvoiceTimestamp')) {
function nmInvoiceTimestamp(array $invoice){
    $raw=$invoice['time_sell']??null;
    if(is_numeric($raw)) return (int)$raw;
    if(is_string($raw)&&trim($raw)!==''){ $x=strtotime($raw); if($x!==false) return $x; }
    return time();
}}
if (!function_exists('nmStockForInvoice')) {
function nmStockForInvoice(array $invoice){
    global $pdo;
    if(!nmStockEnsureSchema()) return false;
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
        return ['id'=>null,'content'=>$content,'sub_link'=>'','tier'=>nmStockTierFromVolume($invoice['Volume']??0),'format'=>nmStockDetectFormat($content),'status'=>'delivered','assigned_invoice'=>$iid];
    }
    return false;
}}
if (!function_exists('nmStockSubLinkForInvoice')) {
function nmStockSubLinkForInvoice(array $invoice,array $stock=null){
    $stock=$stock?:nmStockForInvoice($invoice);
    $content=is_array($stock)?trim((string)($stock['content']??'')):trim((string)($invoice['user_info']??''));
    $sub=is_array($stock)?trim((string)($stock['sub_link']??'')):'';
    if($sub!=='') return $sub;
    return preg_match('/^https?:\/\//i',$content)?$content:'';
}}
if (!function_exists('nmStockInvoiceText')) {
function nmStockInvoiceText(array $invoice,array $stock=null){
    $stock=$stock?:nmStockForInvoice($invoice);
    $content=is_array($stock)?trim((string)($stock['content']??'')):trim((string)($invoice['user_info']??''));
    $sub=nmStockSubLinkForInvoice($invoice,is_array($stock)?$stock:null);
    $startTs=nmInvoiceTimestamp($invoice); $days=(int)($invoice['Service_time']??0);
    $vol=trim((string)($invoice['Volume']??'')); if($vol!==''&&is_numeric($vol))$vol=formatInvoiceVolume($invoice);


    $shelfName='';
    if(is_array($stock) && !empty($stock['shelf_id']) && function_exists('nmStockShelfById')){
        $shelf=nmStockShelfById($stock['shelf_id']);
        if(is_array($shelf)) $shelfName=(string)($shelf['name']??'');
    }
    $shelfNameSafe=htmlspecialchars($shelfName!==''?$shelfName:'انبار شبکه‌ملی',ENT_QUOTES,'UTF-8');
    $txt="✅ وضعیت نت ملی فعال است؛ این سرویس از انبار شبکه‌ملی تحویل شده است.\n\n";
    $txt.="👤 نام کاربری: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="🛍 محصول: <code>".htmlspecialchars((string)($invoice['name_product']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📍 پنل: <code>".htmlspecialchars((string)($invoice['Service_location']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📦 انبار: <code>{$shelfNameSafe}</code>\n";
    if($vol!=='')$txt.="🗜 حجم سرویس: <code>".htmlspecialchars($vol,ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📅 شروع: <code>".nmJalaliDate('Y/m/d',$startTs)."</code>\n";
    $txt.="⏳ پایان: <code>".($days>0?nmJalaliDate('Y/m/d',$startTs+$days*86400):'نامحدود')."</code>\n";
    $txt.="📌 وضعیت: <code>".htmlspecialchars((string)($invoice['Status']??'active'),ENT_QUOTES,'UTF-8')."</code>\n\n";
    $txt.="⚠️ در حالت انبار، حجم باقی‌مانده از داخل ربات نمایش داده نمی‌شود؛ حجم را از لینک اشتراک مشاهده کنید.\n\n";
    $txt.="برای دریافت کانفیگ و لینک اشتراک، از دکمه‌های زیر استفاده کنید.";
    return $txt;
}}
if (!function_exists('nmMaybeShowStockInvoiceDetails')) {
function nmMaybeShowStockInvoiceDetails($chatId,$messageId,$invoice){
    if(!is_array($invoice))return false; $stock=nmStockForInvoice($invoice); if(!$stock)return false;
    $content=trim((string)($stock['content']??($invoice['user_info']??''))); $sub=nmStockSubLinkForInvoice($invoice,$stock);
    $panel=select('marzban_panel','*','name_panel',$invoice['Service_location']??'','select'); if(!is_array($panel)&&!empty($invoice['source_panel_code']))$panel=select('marzban_panel','*','code_panel',$invoice['source_panel_code'],'select'); if(!is_array($panel))$panel=null;
    $kbd=nmStockDeliveryKeyboard($content,$invoice['id_invoice']??'',$sub,$panel); $txt=nmStockInvoiceText($invoice,$stock);
    try{ if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$txt,$kbd,'HTML'); else sendmessage($chatId,$txt,$kbd,'HTML'); }catch(Throwable $e){ sendmessage($chatId,$txt,$kbd,'HTML'); }
    return true;
}}
if (!function_exists('nmSendLongStockMessage')) {
function nmSendLongStockMessage($chatId,$text,$keyboard=null){
    $text=(string)$text; if(mb_strlen($text,'UTF-8')<=3900){sendmessage($chatId,$text,$keyboard,'HTML');return;}
    foreach(str_split($text,3800) as $part) sendmessage($chatId,$part,null,'HTML');
}}
if (!function_exists('nmMaybeHandleStockCallback')) {
function nmMaybeHandleStockCallback($datain,$chatId,$messageId=null,$callbackQueryId=null){
    global $user, $keyboard;
    $datain=(string)$datain; $action=null; $iid=null; $token=null;
    if(preg_match('/^nmstockcfg_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='config'; $iid=$m[1]; }
    elseif(preg_match('/^nmstocksub_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='sub'; $iid=$m[1]; }
    elseif(preg_match('/^nmstockextend_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='extend'; $iid=$m[1]; }
    elseif(preg_match('/^nmstockrefund_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='refund'; $iid=$m[1]; }
    elseif(preg_match('/^nmstocksel_([A-Za-z0-9_\-]+)_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='select_extend'; $token=$m[1]; $iid=$m[2]; }
    elseif(preg_match('/^nmstockok_([A-Za-z0-9_\-]+)_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='confirm_extend'; $token=$m[1]; $iid=$m[2]; }
    elseif(preg_match('/^configget_([A-Za-z0-9_\-]+)_(1520|sub)$/',$datain,$m)){ $action=$m[2]==='sub'?'sub':'config'; $iid=$m[1]; }
    else return false;

    try{$invoice=select('invoice','*','id_invoice',$iid,'select');}catch(Throwable $e){$invoice=false;}
    if(!is_array($invoice)||(string)($invoice['id_user']??'')!==(string)$chatId){
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'سرویس مورد نظر یافت نشد.','show_alert'=>true,'cache_time'=>3]);
        return true;
    }
    $stock=nmStockForInvoice($invoice);

    if($action==='config' || $action==='sub'){
        if(!$stock)return false;
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'درخواست دریافت شد.','show_alert'=>false,'cache_time'=>2]);
        $content=trim((string)($stock['content']??($invoice['user_info']??''))); $sub=nmStockSubLinkForInvoice($invoice,$stock);
        if($action==='sub'){
            if($sub===''){
                sendmessage($chatId,'⚠️ برای این سرویس لینک اشتراک جداگانه ثبت نشده است؛ کانفیگ تکی ارسال می‌شود.',null,'HTML');
                nmSendLongStockMessage($chatId, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>");
            } else {
                nmSendLongStockMessage($chatId, "🔗 لینک اشتراک:\n<code>".htmlspecialchars($sub,ENT_QUOTES,'UTF-8')."</code>");
            }
        } else {
            nmSendLongStockMessage($chatId, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>");
        }
        return true;
    }

    if($action==='refund'){

        if(!$stock || (string)($invoice['Status']??'')==='removebyuser' || (string)($invoice['Status']??'')==='nm_refund_pending' || (string)($invoice['Status']??'')==='removedbyadmin'){
            sendmessage($chatId,'❌ امکان بازگشت وجه برای این سرویس وجود ندارد یا قبلاً درخواست داده‌اید.',$keyboard??null,'HTML');
            if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'قابل انجام نیست','show_alert'=>false,'cache_time'=>2]);
            return true;
        }
        $defaultRefund=(float)($invoice['price_product']??0);

        update('invoice','Status','nm_refund_pending','id_invoice',$invoice['id_invoice']);
        try{ nmStockLog($stock['id']??null,$chatId,$invoice['id_invoice'],'refund_request_by_user',['default_refund'=>$defaultRefund]); }catch(Throwable $e){}
        sendmessage($chatId,'⏳ درخواست بازگشت وجه شما برای ادمین ارسال شد. پس از بررسی و تأیید ادمین، مبلغ به کیف‌پول شما اضافه خواهد شد.',$keyboard??null,'HTML');

        global $admin_ids, $username, $setting, $otherreport;
        $usernameAdminMsg = isset($username) ? $username : '';
        $invoiceId = (string)($invoice['id_invoice']??'');
        $adminText = "📌 درخواست بازگشت وجه سرویس انبار شبکه ملی\n\n".
            "<blockquote>👤 آیدی عددی کاربر: <code>{$chatId}</code></blockquote>\n".
            "<blockquote>🔖 یوزرنیم تلگرام: @".htmlspecialchars((string)$usernameAdminMsg,ENT_QUOTES,'UTF-8')."</blockquote>\n".
            "<blockquote>🪪 یوزرنیم سرویس: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code></blockquote>\n".
            "<blockquote>🛍 محصول: ".htmlspecialchars((string)($invoice['name_product']??''),ENT_QUOTES,'UTF-8')."</blockquote>\n".
            "<blockquote>🔋 حجم: ".htmlspecialchars((string)($invoice['Volume']??0),ENT_QUOTES,'UTF-8')." گیگ</blockquote>\n".
            "<blockquote>⏳ مدت: ".htmlspecialchars((string)($invoice['Service_time']??0),ENT_QUOTES,'UTF-8')." روز</blockquote>\n".
            "<blockquote>💰 مبلغ پرداختی: ".number_format($defaultRefund)." تومان</blockquote>\n".
            "<blockquote>🧾 کد سرویس: <code>{$invoiceId}</code></blockquote>\n\n".
            "ادمین گرامی: می‌توانید مبلغ پیش‌فرض را تأیید کنید یا با زدن «✏️ ورود مبلغ دلخواه» مبلغ دیگری وارد نمایید.";
        $adminKbd = json_encode(['inline_keyboard'=>[
            [
                ['text'=>'✅ تأیید مبلغ پیش‌فرض','callback_data'=>'nmrefokdef_'.$invoiceId],
                ['text'=>'✏️ ورود مبلغ دلخواه','callback_data'=>'nmrefcustom_'.$invoiceId],
            ],
            [
                ['text'=>'❌ رد درخواست','callback_data'=>'nmrefreject_'.$invoiceId],
            ],
        ]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(is_array($admin_ids)) foreach($admin_ids as $admin) sendmessage($admin,$adminText,$adminKbd,'HTML');
        if(!empty($setting['Channel_Report'])){
            $payload=['chat_id'=>$setting['Channel_Report'],'text'=>$adminText,'parse_mode'=>'HTML','reply_markup'=>$adminKbd];
            if(!empty($otherreport)) $payload['message_thread_id']=$otherreport;
            telegram('sendmessage',$payload);
        }
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'درخواست ارسال شد','show_alert'=>false,'cache_time'=>2]);
        return true;
    }

    $panel=nmStockPanelForInvoice($invoice);
    if(!is_array($panel)){ sendmessage($chatId,'❌ پنل اصلی این سرویس پیدا نشد.',$keyboard??null,'HTML'); return true; }

    if($action==='extend'){
        $userRow=is_array($user)?$user:select('user','*','id',$chatId,'select');

        // Always let the user pick a product for renewal (no silent auto-renew on
        // the previous product) — for both national-net and main-panel services.
        $kbd=nmStockExtendProductKeyboard($invoice,$userRow,$panel);
        if(!$kbd){
            sendmessage($chatId,'❌ محصولی برای تمدید این سرویس پیدا نشد.',$keyboard??null,'HTML');
            if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'محصولی پیدا نشد','show_alert'=>true,'cache_time'=>2]);
            return true;
        }
        $title = nmPanelNationalEnabled($panel)
            ? '📦 وضعیت نت ملی فعال است؛ محصول موردنظر برای تمدید را از لیست زیر انتخاب کنید:'
            : '🛍 محصول موردنظر برای تمدید را از لیست محصولات موجود انتخاب کنید:';
        if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$title,$kbd,'HTML'); else sendmessage($chatId,$title,$kbd,'HTML');
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'محصول تمدید را انتخاب کنید','show_alert'=>false,'cache_time'=>2]);
        return true;
    }

    if($action==='select_extend' || $action==='confirm_extend'){
        $userRow=is_array($user)?$user:select('user','*','id',$chatId,'select');
        $product=nmStockResolveProductToken($token,$panel,$userRow['agent']??'all');
        if(!$product){ sendmessage($chatId,'❌ محصول تمدید پیدا نشد.',$keyboard??null,'HTML'); return true; }
        if($action==='select_extend'){
            $price=number_format((float)($product['price_product']??0));
            $txt="📜 فاکتور تمدید سرویس

👤 سرویس: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>
🛍 محصول: {$product['name_product']}
💰 مبلغ: {$price} تومان
⏳ مدت: {$product['Service_time']} روز
🗜 حجم: {$product['Volume_constraint']} گیگ";
            $kbd=json_encode(['inline_keyboard'=>[[['text'=>'✅ تایید تمدید','callback_data'=>'nmstockok_'.$token.'_'.$iid]],[['text'=>'🏠 بازگشت به لیست سرویس ها','callback_data'=>'backorder']]]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$txt,$kbd,'HTML'); else sendmessage($chatId,$txt,$kbd,'HTML');
            return true;
        }
        $price=(float)($product['price_product']??0);
        if((float)($userRow['Balance']??0)<$price && ($userRow['agent']??'')!=='n2'){ sendmessage($chatId,'❌ موجودی کیف پول برای تمدید کافی نیست.',$keyboard??null,'HTML'); return true; }
        if(nmPanelNationalEnabled($panel)){
            $stockNew=nmStockReserveForProduct($panel,$product,$chatId,$invoice['id_invoice'],'stock_service_extend');
            if(!$stockNew){ sendmessage($chatId,'❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.',$keyboard??null,'HTML'); return true; }
            if (function_exists('balance_atomic_charge')) {
                $__allowNegRb=(($userRow['agent']??'')==='n2')?(int)($userRow['maxbuyagent']??0):0;
                balance_atomic_charge($chatId,(float)$price,$__allowNegRb);
            } else {
                update('user','Balance',(float)($userRow['Balance']??0)-$price,'id',$chatId);
            }
            update('invoice','name_product',$product['name_product'],'id_invoice',$invoice['id_invoice']);
            update('invoice','price_product',$product['price_product'],'id_invoice',$invoice['id_invoice']);
            update('invoice','Volume',$product['Volume_constraint'],'id_invoice',$invoice['id_invoice']);
            update('invoice','Service_time',$product['Service_time'],'id_invoice',$invoice['id_invoice']);
            update('invoice','Status','active','id_invoice',$invoice['id_invoice']);
            update('invoice','time_sell',time(),'id_invoice',$invoice['id_invoice']);
            update('invoice','user_info',$stockNew['content'],'id_invoice',$invoice['id_invoice']);
            try{ update('invoice','source_panel_code',$panel['code_panel']??'','id_invoice',$invoice['id_invoice']); }catch(Throwable $e){}
            $invoiceNew=array_merge($invoice,['name_product'=>$product['name_product'],'price_product'=>$product['price_product'],'Volume'=>$product['Volume_constraint'],'Service_time'=>$product['Service_time'],'time_sell'=>time(),'user_info'=>$stockNew['content'],'source_panel_code'=>$panel['code_panel']??'']);
            nmStockDeliverConfig($stockNew,$invoiceNew,'✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
            sendmessage($chatId,'✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.',$keyboard??null,'HTML');

            if(function_exists('nmStockNotifyExtend')) nmStockNotifyExtend($chatId,$userRow,$invoice,$product,$panel,'stock',(string)($invoice['username']??''),$price);
        }else{
            nmStockConvertInvoiceToPanelService($chatId,$userRow,$invoice,$panel,$product);
        }
        return true;
    }
    return false;
}}

function nmStockDeliverConfig(array $stock, array $invoice, $captionPrefix = '✅ کانفیگ جایگزین از انبار شبکه‌ملی تحویل شد')
{
    global $from_id;
    $userId = $invoice['id_user'] ?? $from_id;
    $invoiceId = $invoice['id_invoice'] ?? '';
    $content = trim((string)($stock['content'] ?? ''));
    $subLink = trim((string)($stock['sub_link'] ?? ''));
    if ($subLink === '' && preg_match('/^https?:\/\//i', $content)) $subLink = $content;

    $caption = $captionPrefix . "\n\nبرای دریافت کانفیگ و لینک اشتراک، از دکمه‌های زیر استفاده کنید.";
    $panel = null;
    if (!empty($invoice['Service_location'])) { $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select'); }
    if ((!is_array($panel)) && !empty($invoice['source_panel_code'])) { $panel = select('marzban_panel', '*', 'code_panel', $invoice['source_panel_code'], 'select'); }
    if (!is_array($panel)) { $panel = null; }
    $deliveryKeyboard = nmStockDeliveryKeyboard($content, $invoiceId, $subLink, $panel);
    try {
        sendmessage($userId, $caption, $deliveryKeyboard, 'HTML');
        nmStockMarkDelivered($stock['id'], $userId, $invoiceId, ['caption' => $captionPrefix]);
    } catch (Throwable $e) {
        error_log('nmStockDeliverConfig failed: ' . $e->getMessage());
        sendmessage($userId, $caption, $deliveryKeyboard, 'HTML');
        nmStockMarkDelivered($stock['id'], $userId, $invoiceId, ['fallback_send' => true]);
    }
}

function nmStockFallbackForInvoice(array $invoice, array $product = null, $mode = 'panel_fallback')
{
    $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'] ?? '', 'select');
    if (!$product) $product = nmStockProductForInvoice($invoice);
    if (is_array($panel) && $panel) {
        $stock = nmStockReserveForProduct($panel, $product, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
    } else {
        $volume = $product['Volume_constraint'] ?? ($invoice['Volume'] ?? 0);
        $productCode = $product['code_product'] ?? 'auto';
        $stock = nmStockReserveOne('auto', $productCode, $volume, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
        if (!$stock && $productCode !== 'auto') $stock = nmStockReserveOne('auto', 'auto', $volume, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
    }
    if (!$stock) return false;
    nmStockDeliverConfig($stock, $invoice, $mode === 'config_button' ? '✅ کانفیگ مطابق حجم سرویس از انبار شبکه‌ملی تحویل شد' : '✅ تمدید از پنل انجام نشد؛ کانفیگ جایگزین از انبار شبکه‌ملی تحویل شد');
    try { update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']); update('invoice', 'user_info', $stock['content'], 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) { error_log('nmStockFallbackForInvoice invoice update failed: ' . $e->getMessage()); }
    return $stock;
}

function nmStockStatusText($panelCode = null)
{
    $rows = nmStockCounts($panelCode);
    if (!$rows) return "📦 انبار شبکه‌ملی\n\n❌ موجودی فعالی ثبت نشده است.";
    $lines = ["📦 وضعیت موجودی انبار شبکه‌ملی", "", "سطح / فرمت / تعداد:"];
    foreach ($rows as $row) $lines[] = "• {$row['tier']} / {$row['format']} : {$row['cnt']} عدد";
    return implode("\n", $lines);
}

function nmStockCompleteExtendFallback($userId, array $userRow, array $invoice, array $product, $priceToCharge = 0, $mode = 'extend_fallback')
{
    global $pdo;
    $stock = nmStockFallbackForInvoice($invoice, $product, $mode);
    if (!$stock) return false;
    try {
        $priceToCharge = (float)$priceToCharge;
        if ($priceToCharge > 0 && isset($userRow['Balance'])) {
            if (function_exists('balance_atomic_charge')) {
                $__allowNegFb = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
                balance_atomic_charge($userId, $priceToCharge, $__allowNegFb);
            } else {
                $newBalance = (float)$userRow['Balance'] - $priceToCharge;
                update('user', 'Balance', $newBalance, 'id', $userId);
            }
        }
        $value = json_encode([
            'volumebuy' => $product['Volume_constraint'] ?? ($invoice['Volume'] ?? 0),
            'Service_time' => $product['Service_time'] ?? ($invoice['Service_time'] ?? 0),
            'code_product' => $product['code_product'] ?? 'auto',
            'source' => 'nm_stock',
            'stock_id' => $stock['id'],
            'tier' => $stock['tier']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $output = json_encode(['status' => true, 'source' => 'nm_stock', 'stock_id' => $stock['id'], 'tier' => $stock['tier']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output, status) VALUES (:id_user,:username,:value,'extend_user',:time,:price,:output,'paid')");
        $stmt->execute([
            ':id_user' => $userId,
            ':username' => $invoice['username'] ?? '',
            ':value' => $value,
            ':time' => date('Y/m/d H:i:s'),
            ':price' => $priceToCharge,
            ':output' => $output,
        ]);
        update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
    } catch (Throwable $e) {
        error_log('nmStockCompleteExtendFallback failed: ' . $e->getMessage());
    }
    sendmessage($userId, "✅ پنل در دسترس نبود؛ تمدید با کانفیگ جایگزین انبار شبکه‌ملی تکمیل شد.", null, 'HTML');
    return $stock;
}
function nmStockEnsureSchema()
{
    global $pdo;
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

function nmStockTierFromVolume($volume)
{
    $volume = (int)ceil((float)$volume);
    if ($volume <= 0) return 'auto';
    if ($volume <= 10) return '10-10';
    $lower = (int)(floor(($volume - 1) / 10) * 10);
    return $lower . '-' . ($lower + 10);
}

function nmStockDetectFormat($content)
{
    $content = trim((string)$content);
    if (preg_match('/^https?:\/\//i', $content)) return 'subscription';
    if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard|wg|vpn|awg|amneziawg|tg):\/\//i', $content)) return 'single';
    if (stripos($content, '[Interface]') !== false || stripos($content, 'PrivateKey') !== false) return 'wireguard';
    return 'text';
}

function nmStockLog($stockId, $userId, $invoiceId, $action, $payload = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO nm_config_stock_log (stock_id,id_user,id_invoice,action,payload,created_at) VALUES (:stock_id,:id_user,:id_invoice,:action,:payload,:created_at)");
        $payloadText = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->execute([':stock_id' => $stockId, ':id_user' => (string)$userId, ':id_invoice' => (string)$invoiceId, ':action' => (string)$action, ':payload' => $payloadText, ':created_at' => time()]);
    } catch (Throwable $e) { error_log('nmStockLog failed: ' . $e->getMessage()); }
}

function nmStockCounts($panelCode = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return [];
    $where = "status = 'active'";
    $params = [];
    if ($panelCode !== null && trim((string)$panelCode) !== '') { $where .= " AND (codepanel = :codepanel OR codepanel = 'auto')"; $params[':codepanel'] = (string)$panelCode; }
    $stmt = $pdo->prepare("SELECT tier, format, COUNT(*) AS cnt FROM nm_config_stock WHERE $where GROUP BY tier, format ORDER BY tier, format");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function nmStockReserveOne($panelCode, $productCode, $volume, $userId, $invoiceId, $mode = 'fallback')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $panelCode = trim((string)$panelCode) !== '' ? trim((string)$panelCode) : 'auto';
    $productCode = trim((string)$productCode) !== '' ? trim((string)$productCode) : 'auto';
    $tier = nmStockTierFromVolume($volume);
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
        nmStockLog($row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'tier' => $tier, 'product' => $productCode]);
        return $row;
    } catch (Throwable $e) { error_log('nmStockReserveOne failed: ' . $e->getMessage()); return false; }
}

function nmStockReleaseReservation($stock)
{
    global $pdo;
    $id = is_array($stock) ? ($stock['id'] ?? null) : $stock;
    if ($id === null || $id === '' || !$pdo) return false;
    try {
        $upd = $pdo->prepare("UPDATE nm_config_stock SET status='active', assigned_user='', assigned_invoice='', assigned_mode='', reserved_at=NULL WHERE id=:id AND status='reserved'");
        $upd->execute([':id' => $id]);
        $released = $upd->rowCount() > 0;
        if ($released && function_exists('nmStockLog')) {
            nmStockLog($id, '', '', 'released', ['reason' => 'reservation_rolled_back']);
        }
        return $released;
    } catch (Throwable $e) { error_log('nmStockReleaseReservation failed: ' . $e->getMessage()); return false; }
}

function nmStockReserveByShelfMatch(array $panel, array $product, $userId, $invoiceId, $mode = 'fallback')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $stockPanel = function_exists('nmPanelResolveStockCode') ? nmPanelResolveStockCode($panel) : (trim((string)($panel['code_panel'] ?? '')) ?: 'auto');
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function ($v) {
        return trim((string)$v) !== '';
    })));
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $productName = nmNormalizeText($product['name_product'] ?? '');
    $volume = (float)($product['Volume_constraint'] ?? 0);
    $days = (int)($product['Service_time'] ?? 0);
    $tier = nmStockTierFromVolume($volume);

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
        nmStockLog($row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'tier' => $tier, 'product' => $productCode, 'shelf_match' => true]);
        return $row;
    } catch (Throwable $e) { error_log('nmStockReserveByShelfMatch failed: ' . $e->getMessage()); return false; }
}

function nmStockMarkDelivered($stockId, $userId, $invoiceId, $payload = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return;
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
        nmStockLog($stockId, $userId, $invoiceId, 'delivered', $payload);
    } catch (Throwable $e) {
        error_log('nmStockMarkDelivered failed: ' . $e->getMessage());
        try {
            $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='disabled', assigned_user=:user, assigned_invoice=:invoice, delivered_at=:delivered_at WHERE id=:id AND status='active'");
            $stmt->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':delivered_at' => time(), ':id' => $stockId]);
            nmStockLog($stockId, $userId, $invoiceId, 'delivered_disabled_fallback', $payload);
        } catch (Throwable $inner) {
            error_log('nmStockMarkDelivered fallback failed: ' . $inner->getMessage());
        }
    }
}

function nmStockProductForInvoice(array $invoice)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product=:name AND (FIND_IN_SET(:loc, Location)>0 OR Location='/all') LIMIT 1");
        $stmt->execute([':name' => $invoice['name_product'] ?? '', ':loc' => $invoice['Service_location'] ?? '']);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) return $product;
    } catch (Throwable $e) { error_log('nmStockProductForInvoice failed: ' . $e->getMessage()); }
    return ['code_product' => 'auto', 'name_product' => $invoice['name_product'] ?? '', 'Volume_constraint' => $invoice['Volume'] ?? 0, 'Service_time' => $invoice['Service_time'] ?? 0, 'price_product' => $invoice['price_product'] ?? 0];
}

function nmStockPanelForInvoice(array $invoice)
{
    $sourceCode = trim((string)($invoice['source_panel_code'] ?? ''));
    if ($sourceCode !== '') {
        try { $panel = select('marzban_panel', '*', 'code_panel', $sourceCode, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { error_log('nmStockPanelForInvoice source lookup failed: ' . $e->getMessage()); }
    }
    $location = trim((string)($invoice['Service_location'] ?? ''));
    if ($location !== '') {
        try { $panel = select('marzban_panel', '*', 'name_panel', $location, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { error_log('nmStockPanelForInvoice name lookup failed: ' . $e->getMessage()); }
        try { $panel = select('marzban_panel', '*', 'code_panel', $location, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { }
    }
    return false;
}

function nmStockHasAvailableForProduct(array $panel, array $product)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $stockPanel = nmPanelResolveStockCode($panel);
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $productName = function_exists('nmNormalizeText') ? nmNormalizeText($product['name_product'] ?? '') : (string)($product['name_product'] ?? '');
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

function nmStockSendQr($chatId, $qrPayload, $fullText, $shortCaption = '📥 کیو‌آر کد')
{
    $qrPayload = trim((string)$qrPayload);
    $sent = false;
    if (!(function_exists('isQrDisabled') && isQrDisabled()) && $qrPayload !== '' && function_exists('createqrcode') && function_exists('telegram')) {
        try {
            $qrCode = createqrcode($qrPayload);
            if ($qrCode === null) {
                if (function_exists('sendmessage')) {
                    sendmessage($chatId, "❌ ساخت QR کد برای این محتوا ممکن نیست — طولانی‌تر از ظرفیت QR کد است. از متن زیر کپی کنید.", null, 'HTML');
                }
            } else {
                $urlimage = $chatId . bin2hex(random_bytes(3)) . '.png';
                file_put_contents($urlimage, $qrCode->getString());
                if (function_exists('addBackgroundImage')) @addBackgroundImage($urlimage, $qrCode, 'images.jpg');
                telegram('sendphoto', ['chat_id' => $chatId, 'photo' => new CURLFile($urlimage), 'caption' => $shortCaption, 'parse_mode' => 'HTML']);
                @unlink($urlimage);
                $sent = true;
            }
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

function nmStockDeliveryKeyboard($content, $invoiceId = '', $subLink = '', array $panel = null)
{
    $styles = nmStockStyles();
    $buttons = [];
    $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$invoiceId);
    if ($invoiceId !== '' && $panel !== null) {
        $sub = trim((string)$subLink);
        $cfg = trim((string)$content);
        $hasLink = ($sub !== '');
        $hasConfig = ($cfg !== '' && $cfg !== $sub);
        $row1 = [];
        if ((string)($panel['status_extend'] ?? 'on_extend') !== 'off_extend') {
            $row1[] = rx_kb_style(['text' => '🔄 تمدید سرویس', 'callback_data' => 'nmstockextend_' . $invoiceId], 'nmstock_actions', $styles);
        }
        if (!function_exists('panel_feature_enabled') || panel_feature_enabled($panel, 'refund')) {
            $row1[] = rx_kb_style(['text' => '💎 بازگشت وجه', 'callback_data' => 'nmstockrefund_' . $invoiceId], 'nmstock_actions', $styles);
        }
        if ($row1) $buttons[] = $row1;
        $row2 = [];
        if ((!function_exists('panel_feature_enabled') || panel_feature_enabled($panel, 'configbtn')) && $hasConfig) {
            $row2[] = rx_kb_style(['text' => '📥 دریافت کانفیگ', 'callback_data' => 'nmstockcfg_' . $invoiceId], 'nmstock_actions', $styles);
        }
        if ($hasLink) {
            $row2[] = rx_kb_style(['text' => '🔗 لینک اشتراک', 'callback_data' => 'nmstocksub_' . $invoiceId], 'nmstock_actions', $styles);
        }
        if ($row2) $buttons[] = $row2;
    } elseif ($invoiceId !== '') {
        $buttons[] = [
            rx_kb_style(['text' => '🔄 تمدید سرویس', 'callback_data' => 'nmstockextend_' . $invoiceId], 'nmstock_actions', $styles),
            rx_kb_style(['text' => '💎 بازگشت وجه', 'callback_data' => 'nmstockrefund_' . $invoiceId], 'nmstock_actions', $styles),
        ];
        $buttons[] = [
            rx_kb_style(['text' => '📥 دریافت کانفیگ', 'callback_data' => 'nmstockcfg_' . $invoiceId], 'nmstock_actions', $styles),
            rx_kb_style(['text' => '🔗 لینک اشتراک', 'callback_data' => 'nmstocksub_' . $invoiceId], 'nmstock_actions', $styles),
        ];
    } else {
        $buttons[] = [['text' => '📥 دریافت کانفیگ', 'callback_data' => 'none']];
        $link = trim((string)$subLink) !== '' ? trim((string)$subLink) : trim((string)$content);
        if (preg_match('/^https?:\/\//i', $link)) $buttons[] = [['text' => '🔗 لینک اشتراک', 'url' => $link]];
        else $buttons[] = [['text' => '🔗 لینک اشتراک', 'callback_data' => 'none']];
    }
    $buttons[] = [rx_kb_style(['text' => '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder'], 'backorder', $styles)];
    return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmPanelBool(array $panel = null, $field = 'national_net_status', $onValue = 'on_national_net')
{
    if (!$panel) return false;
    return (($panel[$field] ?? '') === $onValue || ($panel[$field] ?? '') === 'on' || ($panel[$field] ?? '') === 'active');
}

function nmPanelNationalEnabled(array $panel = null) { return nmPanelBool($panel, 'national_net_status', 'on_national_net'); }

function nmPanelResolveStockCode(array $panel = null)
{
    if (!$panel) return 'auto';
    $code = trim((string)($panel['stock_source_panel'] ?? ''));
    if ($code !== '') return $code;
    return trim((string)($panel['code_panel'] ?? '')) !== '' ? $panel['code_panel'] : 'auto';
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

        $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s INNER JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND sh.status='active' AND (s.codepanel IN (" . implode(',', $panelWhereSqlS) . ") OR sh.source_codepanel IN (" . implode(',', $panelWhereSqlSrc) . ") OR sh.stock_codepanel IN (" . implode(',', $panelWhereSqlSt) . ")) AND (sh.codeproduct=:product_code_shelf_where OR s.codeproduct=:product_code_stock_where OR sh.codeproduct='auto' OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :volume_where) < 0.001 AND sh.service_days=:days_where)) ORDER BY s.id ASC LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
        $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
        if ($upd->rowCount() < 1) return false;
        if (function_exists('nmStockLog')) nmStockLog($row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'product' => $productCode, 'shelf_loose' => true]);
        return $row;
    } catch (Throwable $e) { error_log('nmStockReserveByShelfLoose failed: ' . $e->getMessage()); return false; }
}

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

function nmAnyNationalNetEnabled()
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marzban_panel WHERE status = 'active' AND national_net_status = 'on_national_net'");
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}


function nmServiceRestrictedNotice()
{
    return '🌐 وضعیت نت ملی این پنل روشن است؛ این سرویس فعلاً از ربات نمایش داده نمی‌شود. وقتی نت ملی خاموش شود، سرویس‌های قبلی دوباره در دسترس قرار می‌گیرند.';
}

function nmServicePanelAccessBlocked(array $invoice = null)
{
    if (!$invoice) return false;
    $serviceLocation = trim((string)($invoice['Service_location'] ?? ''));
    if ($serviceLocation === '') return false;
    try {
        $panel = select('marzban_panel', '*', 'name_panel', $serviceLocation, 'select');
        if (!$panel && !empty($invoice['source_panel_code'])) $panel = select('marzban_panel', '*', 'code_panel', $invoice['source_panel_code'], 'select');
        if (!$panel) return false;
        return nmPanelNationalEnabled($panel);
    } catch (Throwable $e) {
        error_log('nmServicePanelAccessBlocked failed: ' . $e->getMessage());
        return false;
    }
}

function nmStopIfServicePanelBlocked($invoice, $userId = null, $keyboard = null)
{
    if (!is_array($invoice) || !nmServicePanelAccessBlocked($invoice)) return false;
    if ($keyboard === null) {
        $back = $GLOBALS['textbotlang']['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها';
        $keyboard = json_encode(['inline_keyboard' => [[['text' => $back, 'callback_data' => 'backorder']]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if (function_exists('sendmessage') && $userId !== null) {
        sendmessage($userId, nmServiceRestrictedNotice(), $keyboard, 'HTML');
    }
    return true;
}
