<?php


function nmStockProductsForExtend(array $panel, $agent = 'all', $onlyAvailable = false)
{
    global $pdo;
    $rows = [];
    $params = [':loc' => $panel['name_panel'] ?? ''];
    $sql = "SELECT * FROM product WHERE (Location=:loc OR Location='/all')";
    $agent = trim((string)$agent);
    if ($agent !== '' && $agent !== 'all') { $sql .= " AND (agent=:agent OR agent='all' OR agent='' OR agent IS NULL)"; $params[':agent'] = $agent; }
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
    if (preg_match('/^pid_([0-9]+)$/', $token, $m)) { $sql = "SELECT * FROM product WHERE id=:id AND (Location=:loc OR Location='/all') LIMIT 1"; $params[':id'] = (int)$m[1]; }
    else { $sql = "SELECT * FROM product WHERE code_product=:code AND (Location=:loc OR Location='/all') LIMIT 1"; $params[':code'] = $token; }
    $agent = trim((string)$agent);
    if ($agent !== '' && $agent !== 'all') { $sql = str_replace(' LIMIT 1', " AND (agent=:agent OR agent='all' OR agent='' OR agent IS NULL) LIMIT 1", $sql); $params[':agent'] = $agent; }
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: false; }
    catch (Throwable $e) { error_log('nmStockResolveProductToken failed: ' . $e->getMessage()); return false; }
}

function nmStockExtendProductKeyboard(array $invoice, array $userRow, array $panel)
{
    $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($invoice['id_invoice'] ?? ''));
    $isNational = nmPanelNationalEnabled($panel);
    $products = nmStockProductsForExtend($panel, $userRow['agent'] ?? 'all', $isNational);
    if (!$products) return false;
    $rows = [];
    foreach ($products as $product) {
        $code = trim((string)($product['code_product'] ?? ''));
        $token = ($code !== '' && strlen('nmstocksel_' . $code . '_' . $invoiceId) <= 64) ? $code : 'pid_' . (string)($product['id'] ?? '0');
        $name = trim((string)($product['name_product'] ?? 'محصول'));
        $price = number_format((float)($product['price_product'] ?? 0));
        $vol = trim((string)($product['Volume_constraint'] ?? ''));
        $days = trim((string)($product['Service_time'] ?? ''));
        $label = $name . " - {$price} تومان";
        if ($vol !== '' || $days !== '') $label .= " ({$vol}GB / {$days} روز)";
        $rows[] = [['text' => $label, 'callback_data' => 'nmstocksel_' . $token . '_' . $invoiceId]];
    }
    $rows[] = [['text' => '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmStockSafeUsername($userId, $current = '')
{
    $base = preg_replace('/[^A-Za-z0-9_]/', '', (string)$current);
    if ($base === '' || strlen($base) < 3) $base = preg_replace('/[^A-Za-z0-9_]/', '', (string)$userId) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
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
    try { $check = $ManagePanel->DataUser($panel['name_panel'], $username); if (is_array($check) && isset($check['username'])) $username = nmStockSafeUsername($chatId, $chatId . '_' . substr(bin2hex(random_bytes(4)), 0, 8)); } catch (Throwable $e) { }
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


    $caption = "✅ وضعیت نت ملی خاموش است؛ سرویس شما از پنل اصلی تمدید و جایگزین شد.

👤 نام سرویس: <code>{$dataoutput['username']}</code>
🛍 محصول: {$product['name_product']}
⏳ مدت: {$product['Service_time']} روز
🗜 حجم: {$product['Volume_constraint']} گیگ";
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
        $modeLabel = $mode === 'panel' ? '📡 از پنل اصلی' : ($mode === 'emergency' ? '🚨 از پنل اضطراری' : '📦 از انبار شبکه‌ملی');
        $usernameTg = trim((string)($userRow['username'] ?? ''));
        if ($usernameTg === '') $usernameTg = '---';
        $price = (float)$price;
        $title = ($action === 'buy') ? '🛒 خرید سرویس انجام شد' : '♻️ تمدید سرویس انجام شد';
        $msg = "{$title} ({$modeLabel})\n\n"
            . "👤 آیدی کاربر: <code>{$chatId}</code>\n"
            . "🔖 یوزرنیم تلگرام: @" . htmlspecialchars($usernameTg, ENT_QUOTES, 'UTF-8') . "\n"
            . "🪪 یوزرنیم سرویس: <code>" . htmlspecialchars((string)($newUsername !== '' ? $newUsername : ($invoice['username'] ?? '')), ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "🛍 محصول: " . htmlspecialchars((string)($product['name_product'] ?? ''), ENT_QUOTES, 'UTF-8') . "\n"
            . "📍 پنل: " . htmlspecialchars((string)($panel['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8') . "\n"
            . "🗜 حجم: " . htmlspecialchars((string)($product['Volume_constraint'] ?? ''), ENT_QUOTES, 'UTF-8') . " گیگ\n"
            . "⏳ مدت: " . htmlspecialchars((string)($product['Service_time'] ?? ''), ENT_QUOTES, 'UTF-8') . " روز\n"
            . "💰 مبلغ: " . number_format($price) . " تومان\n"
            . "🧾 کد سرویس: <code>" . htmlspecialchars((string)($invoice['id_invoice'] ?? ''), ENT_QUOTES, 'UTF-8') . "</code>";
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
    $vol=trim((string)($invoice['Volume']??'')); if($vol!==''&&is_numeric($vol))$vol=rtrim(rtrim(number_format((float)$vol,2,'.',''),'0'),'.').'GB';


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
    if($content!=='')$txt.="🔗 کانفیگ/اشتراک:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>";
    if($sub!==''&&$sub!==$content)$txt.="\n\n🔗 لینک اشتراک:\n<code>".htmlspecialchars($sub,ENT_QUOTES,'UTF-8')."</code>";
    return $txt;
}}
if (!function_exists('nmMaybeShowStockInvoiceDetails')) {
function nmMaybeShowStockInvoiceDetails($chatId,$messageId,$invoice){
    if(!is_array($invoice))return false; $stock=nmStockForInvoice($invoice); if(!$stock)return false;
    $content=trim((string)($stock['content']??($invoice['user_info']??''))); $sub=nmStockSubLinkForInvoice($invoice,$stock);
    $kbd=nmStockDeliveryKeyboard($content,$invoice['id_invoice']??'',$sub); $txt=nmStockInvoiceText($invoice,$stock);
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
                nmStockSendQr($chatId, $content, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>", '📥 کیو‌آر کد کانفیگ');
            } else {
                nmStockSendQr($chatId, $sub, "🔗 لینک اشتراک:\n<code>".htmlspecialchars($sub,ENT_QUOTES,'UTF-8')."</code>", '🔗 کیو‌آر کد لینک اشتراک');
            }
        } else {
            // "📥 دریافت کانفیگ": deliver the single config together with its QR. (Image-1 request.)
            nmStockSendQr($chatId, $content, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>", '📥 کیو‌آر کد کانفیگ');
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
            "👤 آیدی عددی کاربر: <code>{$chatId}</code>\n".
            "🔖 یوزرنیم تلگرام: @".htmlspecialchars((string)$usernameAdminMsg,ENT_QUOTES,'UTF-8')."\n".
            "🪪 یوزرنیم سرویس: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>\n".
            "🛍 محصول: ".htmlspecialchars((string)($invoice['name_product']??''),ENT_QUOTES,'UTF-8')."\n".
            "🔋 حجم: ".htmlspecialchars((string)($invoice['Volume']??0),ENT_QUOTES,'UTF-8')." گیگ\n".
            "⏳ مدت: ".htmlspecialchars((string)($invoice['Service_time']??0),ENT_QUOTES,'UTF-8')." روز\n".
            "💰 مبلغ پرداختی: ".number_format($defaultRefund)." تومان\n".
            "🧾 کد سرویس: <code>{$invoiceId}</code>\n\n".
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
    $days = (int)($invoice['Service_time'] ?? 0);
    $startTs = nmInvoiceTimestamp($invoice);
    $started = nmJalaliDate('Y/m/d', $startTs);
    $expires = $days > 0 ? nmJalaliDate('Y/m/d', $startTs + ($days * 86400)) : 'نامحدود';

    $shelfName = '';
    if (!empty($stock['shelf_id']) && function_exists('nmStockShelfById')) {
        $shelf = nmStockShelfById($stock['shelf_id']);
        if (is_array($shelf)) $shelfName = (string)($shelf['name'] ?? '');
    }
    $shelfNameSafe = htmlspecialchars($shelfName !== '' ? $shelfName : 'انبار شبکه‌ملی', ENT_QUOTES, 'UTF-8');
    $caption = $captionPrefix . "\n\n📦 انبار: <code>{$shelfNameSafe}</code>\n📅 شروع: <code>{$started}</code>\n⏳ پایان: <code>{$expires}</code>\n\n⚠️ در حالت انبار، حجم باقی‌مانده از داخل ربات نمایش داده نمی‌شود؛ حجم را از لینک اشتراک مشاهده کنید.\n\n🔗 کانفیگ/اشتراک:\n<code>" . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . "</code>";
    if ($subLink !== '' && $subLink !== $content) $caption .= "\n\n🔗 لینک اشتراک برای مشاهده حجم:\n<code>" . htmlspecialchars($subLink, ENT_QUOTES, 'UTF-8') . "</code>";
    $deliveryKeyboard = nmStockDeliveryKeyboard($content, $invoiceId, $subLink);
    try {
        if ($content !== '' && function_exists('createqrcode') && function_exists('telegram')) {
            $urlimage = $userId . bin2hex(random_bytes(3)) . '.png';
            // QR encodes the subscription link when the admin provided one; otherwise
            // the single config link. (Image-1 request 1/2.)
            $qrPayload = ($subLink !== '') ? $subLink : $content;
            $qrCode = createqrcode($qrPayload);
            file_put_contents($urlimage, $qrCode->getString());
            if (function_exists('addBackgroundImage')) @addBackgroundImage($urlimage, $qrCode, 'images.jpg');
            telegram('sendphoto', ['chat_id' => $userId, 'photo' => new CURLFile($urlimage), 'caption' => $caption, 'parse_mode' => 'HTML', 'reply_markup' => $deliveryKeyboard]);
            @unlink($urlimage);
        } else {
            sendmessage($userId, $caption, $deliveryKeyboard, 'HTML');
        }
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