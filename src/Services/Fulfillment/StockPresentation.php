<?php

namespace Faoxima\Services\Fulfillment;

use CURLFile;
use Throwable;

final class StockPresentation
{
    public static function nmStockProductsForExtend(array $panel, $agent = 'all', $onlyAvailable = false)
    {
        global $pdo;
        return \Faoxima\Services\Fulfillment\Inventory::nmStockProductsForExtend($pdo, $panel, $agent, $onlyAvailable);
    }

    public static function nmStockResolveProductToken($token, array $panel, $agent = 'all')
    {
        global $pdo;
        return \Faoxima\Services\Fulfillment\Inventory::nmStockResolveProductToken($pdo, $token, $panel, $agent);
    }

    public static function nmStockStyles()
    {
        global $setting;
        $styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $all = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($all) && !empty($all['user_dynamic_lists'])) {
                $styles = array_filter($all['user_dynamic_lists'], static function ($v) { return $v !== 'default'; });
            }
        }
        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $styles = $styles + rx_getKeyboardDefaultStyles('user_dynamic_lists');
        }
        return $styles;
    }

    public static function nmStockExtendProductKeyboard(array $invoice, array $userRow, array $panel)
    {
        $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($invoice['id_invoice'] ?? ''));
        $isNational = \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel);
        $products = self::nmStockProductsForExtend($panel, $userRow['agent'] ?? 'all', $isNational);
        if (!$products) return false;
        $styles = self::nmStockStyles();
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
            $rows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $label, 'callback_data' => 'nmstocksel_' . $token . '_' . $invoiceId, '_rxcolor' => 'stock_product:' . $product['id']], 'nmstock_actions', $styles)];
        }
        $rows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_back_to_list'), 'callback_data' => 'backorder'], 'backorder', $styles)];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function nmStockConvertInvoiceToPanelService($chatId, array $userRow, array $invoice, array $panel, array $product)
    {
        global $ManagePanel, $errorreport, $keyboard, $pdo, $setting;
        return \Faoxima\Services\Fulfillment\InventoryProvisioning::nmStockConvertInvoiceToPanelService($ManagePanel, $errorreport, $keyboard, $pdo, $setting, $chatId, $userRow, $invoice, $panel, $product);
    }

    public static function nmStockNotifyExtend($chatId, array $userRow, array $invoice, array $product, array $panel, $mode = 'stock', $newUsername = '', $price = 0, $action = 'extend')
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
    }

    public static function nmJalaliDate($format='Y/m/d',$ts=null){
    if($ts===null||$ts===''||$ts===false)$ts=time();
    if(!is_numeric($ts)){ $x=strtotime((string)$ts); $ts=$x!==false?$x:time(); }
    if(function_exists('jdate')){ try{return jdate($format,(int)$ts);}catch(Throwable $e){} }
    return date($format,(int)$ts);
}

    public static function nmStockForInvoice(array $invoice){
    global $pdo;
    return \Faoxima\Services\Fulfillment\Inventory::nmStockForInvoice($pdo, $invoice);
}

    public static function nmStockSubLinkForInvoice(array $invoice,array $stock=null){
    global $pdo;
    return \Faoxima\Services\Fulfillment\Inventory::nmStockSubLinkForInvoice($pdo, $invoice, $stock);
}

    public static function nmStockInvoiceText(array $invoice,array $stock=null){
    $stock=$stock?:self::nmStockForInvoice($invoice);
    $content=is_array($stock)?trim((string)($stock['content']??'')):trim((string)($invoice['user_info']??''));
    $sub=self::nmStockSubLinkForInvoice($invoice,is_array($stock)?$stock:null);
    $startTs=\Faoxima\Services\Fulfillment\Inventory::nmInvoiceTimestamp($invoice); $days=(int)($invoice['Service_time']??0);
    $vol=trim((string)($invoice['Volume']??'')); if($vol!==''&&is_numeric($vol))$vol=\Faoxima\Core\Support\Formatting::formatInvoiceVolume($invoice);


    $shelfName='';
    if(is_array($stock) && !empty($stock['shelf_id']) && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockShelfById')){
        $shelf=self::nmStockShelfById($stock['shelf_id']);
        if(is_array($shelf)) $shelfName=(string)($shelf['name']??'');
    }
    $shelfNameSafe=htmlspecialchars($shelfName!==''?$shelfName:'انبار شبکه‌ملی',ENT_QUOTES,'UTF-8');
    $txt="✅ وضعیت نت ملی فعال است؛ این سرویس از انبار شبکه‌ملی تحویل شده است.\n\n";
    $txt.="👤 نام کاربری: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="🛍 محصول: <code>".htmlspecialchars((string)($invoice['name_product']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📍 پنل: <code>".htmlspecialchars((string)($invoice['Service_location']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📦 انبار: <code>{$shelfNameSafe}</code>\n";
    if($vol!=='')$txt.="🗜 حجم سرویس: <code>".htmlspecialchars($vol,ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📅 شروع: <code>".self::nmJalaliDate('Y/m/d',$startTs)."</code>\n";
    $txt.="⏳ پایان: <code>".($days>0?self::nmJalaliDate('Y/m/d',$startTs+$days*86400):'نامحدود')."</code>\n";
    $txt.="📌 وضعیت: <code>".htmlspecialchars((string)($invoice['Status']??'active'),ENT_QUOTES,'UTF-8')."</code>\n\n";
    $txt.="⚠️ در حالت انبار، حجم باقی‌مانده از داخل ربات نمایش داده نمی‌شود؛ حجم را از لینک اشتراک مشاهده کنید.\n\n";
    $txt.="برای دریافت کانفیگ و لینک اشتراک، از دکمه‌های زیر استفاده کنید.";
    return $txt;
}

    public static function nmMaybeShowStockInvoiceDetails($chatId,$messageId,$invoice){
    if(!is_array($invoice))return false; $stock=self::nmStockForInvoice($invoice); if(!$stock)return false;
    $content=trim((string)($stock['content']??($invoice['user_info']??''))); $sub=self::nmStockSubLinkForInvoice($invoice,$stock);
    $panel=\Faoxima\Core\Database\Db::select('marzban_panel','*','name_panel',$invoice['Service_location']??'','select'); if(!is_array($panel)&&!empty($invoice['source_panel_code']))$panel=\Faoxima\Core\Database\Db::select('marzban_panel','*','code_panel',$invoice['source_panel_code'],'select'); if(!is_array($panel))$panel=null;
    $kbd=self::nmStockDeliveryKeyboard($content,$invoice['id_invoice']??'',$sub,$panel); $txt=self::nmStockInvoiceText($invoice,$stock);
    try{ if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$txt,$kbd,'HTML'); else sendmessage($chatId,$txt,$kbd,'HTML'); }catch(Throwable $e){ sendmessage($chatId,$txt,$kbd,'HTML'); }
    return true;
}

    public static function nmSendLongStockMessage($chatId,$text,$keyboard=null){
    $text = (string) $text;
    if (mb_strlen($text, 'UTF-8') <= 3900) {
        $result = sendmessage($chatId, $text, $keyboard, 'HTML');
        if (!rxMediaCaptionRejected($result) || !empty($result['_rx_confirmed_text_chunks'])) {
            return;
        }
    }
    rxSendRequiredMediaText(['chat_id' => $chatId, 'reply_markup' => $keyboard], $text);
}

    public static function nmMaybeHandleStockCallback($datain,$chatId,$messageId=null,$callbackQueryId=null){
    global $pdo;
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

    try{$invoice=\Faoxima\Core\Database\Db::select('invoice','*','id_invoice',$iid,'select');}catch(Throwable $e){$invoice=false;}
    if(!is_array($invoice)||(string)($invoice['id_user']??'')!==(string)$chatId){
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'سرویس مورد نظر یافت نشد.','show_alert'=>true,'cache_time'=>3]);
        return true;
    }
    $stock=self::nmStockForInvoice($invoice);

    if($action==='config' || $action==='sub'){
        if(!$stock)return false;
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'درخواست دریافت شد.','show_alert'=>false,'cache_time'=>2]);
        $content=trim((string)($stock['content']??($invoice['user_info']??''))); $sub=self::nmStockSubLinkForInvoice($invoice,$stock);
        if($action==='sub'){
            if($sub===''){
                sendmessage($chatId,'⚠️ برای این سرویس لینک اشتراک جداگانه ثبت نشده است؛ کانفیگ تکی ارسال می‌شود.',null,'HTML');
                self::nmSendLongStockMessage($chatId, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>");
            } else {
                self::nmSendLongStockMessage($chatId, "🔗 لینک اشتراک:\n<code>".htmlspecialchars($sub,ENT_QUOTES,'UTF-8')."</code>");
            }
        } else {
            self::nmSendLongStockMessage($chatId, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>");
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

        \Faoxima\Core\Database\Db::update('invoice','Status','nm_refund_pending','id_invoice',$invoice['id_invoice']);
        try{ self::nmStockLog($stock['id']??null,$chatId,$invoice['id_invoice'],'refund_request_by_user',['default_refund'=>$defaultRefund]); }catch(Throwable $e){}
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

    $panel=\Faoxima\Services\Fulfillment\Inventory::nmStockPanelForInvoice($invoice);
    if(!is_array($panel)){ sendmessage($chatId,'❌ پنل اصلی این سرویس پیدا نشد.',$keyboard??null,'HTML'); return true; }

    if($action==='extend'){
        $userRow=is_array($user)?$user:\Faoxima\Core\Database\Db::select('user','*','id',$chatId,'select');

        // Always let the user pick a product for renewal (no silent auto-renew on
        // the previous product) — for both national-net and main-panel services.
        $kbd=self::nmStockExtendProductKeyboard($invoice,$userRow,$panel);
        if(!$kbd){
            sendmessage($chatId,'❌ محصولی برای تمدید این سرویس پیدا نشد.',$keyboard??null,'HTML');
            if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'محصولی پیدا نشد','show_alert'=>true,'cache_time'=>2]);
            return true;
        }
        $title = \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel)
            ? '📦 وضعیت نت ملی فعال است؛ محصول موردنظر برای تمدید را از لیست زیر انتخاب کنید:'
            : '🛍 محصول موردنظر برای تمدید را از لیست محصولات موجود انتخاب کنید:';
        if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$title,$kbd,'HTML'); else sendmessage($chatId,$title,$kbd,'HTML');
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'محصول تمدید را انتخاب کنید','show_alert'=>false,'cache_time'=>2]);
        return true;
    }

    if($action==='select_extend' || $action==='confirm_extend'){
        $userRow=is_array($user)?$user:\Faoxima\Core\Database\Db::select('user','*','id',$chatId,'select');
        $product=self::nmStockResolveProductToken($token,$panel,$userRow['agent']??'all');
        if(!$product){ sendmessage($chatId,'❌ محصول تمدید پیدا نشد.',$keyboard??null,'HTML'); return true; }
        if($action==='select_extend'){
            $price=number_format((float)($product['price_product']??0));
            $txt="📜 فاکتور تمدید سرویس

👤 سرویس: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>
🛍 محصول: {$product['name_product']}
💰 مبلغ: {$price} تومان
⏳ مدت: {$product['Service_time']} روز
🗜 حجم: {$product['Volume_constraint']} گیگ";
            $kbd=json_encode(['inline_keyboard'=>[[['text'=>'✅ تایید تمدید','callback_data'=>'nmstockok_'.$token.'_'.$iid]],[['text'=>\Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_back_to_list'),'callback_data'=>'backorder']]]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$txt,$kbd,'HTML'); else sendmessage($chatId,$txt,$kbd,'HTML');
            return true;
        }
        $price=(float)($product['price_product']??0);
        if((float)($userRow['Balance']??0)<$price && ($userRow['agent']??'')!=='n2'){ sendmessage($chatId,'❌ موجودی کیف پول برای تمدید کافی نیست.',$keyboard??null,'HTML'); return true; }
        if(\Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel)){
            $stockNew=self::nmStockReserveForProduct($panel,$product,$chatId,$invoice['id_invoice'],'stock_service_extend');
            if(!$stockNew){ sendmessage($chatId,\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_stock_depleted_no_charge','❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.'),$keyboard??null,'HTML'); return true; }
            if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge'])) {
                $__allowNegRb=(($userRow['agent']??'')==='n2')?(int)($userRow['maxbuyagent']??0):0;
                $__chargeRb=\Faoxima\Wallet\Balance\Balances::charge($pdo, $chatId,(float)$price,$__allowNegRb);
                if (!empty($__chargeRb['ok']) && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $chatId, 'debit', $price, 'service_action', 'تمدید سرویس (انبار ملی)', null, 'invoice', (string)$invoice['id_invoice']);
                }
            } else {
                \Faoxima\Core\Database\Db::update('user','Balance',(float)($userRow['Balance']??0)-$price,'id',$chatId);
            }
            \Faoxima\Core\Database\Db::update('invoice','name_product',$product['name_product'],'id_invoice',$invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice','price_product',$product['price_product'],'id_invoice',$invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice','Volume',$product['Volume_constraint'],'id_invoice',$invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice','Service_time',$product['Service_time'],'id_invoice',$invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice','Status','active','id_invoice',$invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice','time_sell',time(),'id_invoice',$invoice['id_invoice']);
            \Faoxima\Core\Database\Db::update('invoice','user_info',$stockNew['content'],'id_invoice',$invoice['id_invoice']);
            try{ \Faoxima\Core\Database\Db::update('invoice','source_panel_code',$panel['code_panel']??'','id_invoice',$invoice['id_invoice']); }catch(Throwable $e){}
            $invoiceNew=array_merge($invoice,['name_product'=>$product['name_product'],'price_product'=>$product['price_product'],'Volume'=>$product['Volume_constraint'],'Service_time'=>$product['Service_time'],'time_sell'=>time(),'user_info'=>$stockNew['content'],'source_panel_code'=>$panel['code_panel']??'']);
            self::nmStockDeliverConfig($stockNew,$invoiceNew,'✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
            sendmessage($chatId,\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_stock_extend_success','✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.'),$keyboard??null,'HTML');

            if(method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockNotifyExtend')) self::nmStockNotifyExtend($chatId,$userRow,$invoice,$product,$panel,'stock',(string)($invoice['username']??''),$price);
        }else{
            self::nmStockConvertInvoiceToPanelService($chatId,$userRow,$invoice,$panel,$product);
        }
        return true;
    }
    return false;
}

    public static function nmStockDeliverConfig(array $stock, array $invoice, $captionPrefix = '✅ کانفیگ جایگزین از انبار شبکه‌ملی تحویل شد')
    {
        global $from_id;
        $userId = $invoice['id_user'] ?? $from_id;
        $invoiceId = $invoice['id_invoice'] ?? '';
        $content = trim((string)($stock['content'] ?? ''));
        $subLink = trim((string)($stock['sub_link'] ?? ''));
        if ($subLink === '' && preg_match('/^https?:\/\//i', $content)) $subLink = $content;

        $caption = $captionPrefix . "\n\nبرای دریافت کانفیگ و لینک اشتراک، از دکمه‌های زیر استفاده کنید.";
        $panel = null;
        if (!empty($invoice['Service_location'])) { $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select'); }
        if ((!is_array($panel)) && !empty($invoice['source_panel_code'])) { $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', $invoice['source_panel_code'], 'select'); }
        if (!is_array($panel)) { $panel = null; }
        $deliveryKeyboard = self::nmStockDeliveryKeyboard($content, $invoiceId, $subLink, $panel);
        try {
            sendmessage($userId, $caption, $deliveryKeyboard, 'HTML');
            self::nmStockMarkDelivered($stock['id'], $userId, $invoiceId, ['caption' => $captionPrefix]);
        } catch (Throwable $e) {
            error_log('nmStockDeliverConfig failed: ' . $e->getMessage());
            sendmessage($userId, $caption, $deliveryKeyboard, 'HTML');
            self::nmStockMarkDelivered($stock['id'], $userId, $invoiceId, ['fallback_send' => true]);
        }
    }

    public static function nmStockLog($stockId, $userId, $invoiceId, $action, $payload = null)
    {
        global $pdo;
        return \Faoxima\Services\Fulfillment\Inventory::nmStockLog($pdo, $stockId, $userId, $invoiceId, $action, $payload);
    }

    public static function nmStockMarkDelivered($stockId, $userId, $invoiceId, $payload = null)
    {
        global $pdo;
        return \Faoxima\Services\Fulfillment\Inventory::nmStockMarkDelivered($pdo, $stockId, $userId, $invoiceId, $payload);
    }

    public static function nmStockHasAvailableForProduct(array $panel, array $product)
    {
        global $pdo;
        return \Faoxima\Services\Fulfillment\Inventory::nmStockHasAvailableForProduct($pdo, $panel, $product);
    }

    public static function nmStockSendQr($chatId, $qrPayload, $fullText, $shortCaption = '📥 کیو‌آر کد')
    {
        $qrPayload = trim((string)$qrPayload);
        $sent = false;
        if (!(method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled()) && $qrPayload !== '' && method_exists(\Faoxima\Telegram\Support\QrAndKeys::class, 'createqrcode') && function_exists('telegram')) {
            try {
                $qrCode = \Faoxima\Telegram\Support\QrAndKeys::createqrcode($qrPayload);
                if ($qrCode === null) {
                    if (function_exists('sendmessage')) {
                        sendmessage($chatId, "❌ ساخت QR کد برای این محتوا ممکن نیست — طولانی‌تر از ظرفیت QR کد است. از متن زیر کپی کنید.", null, 'HTML');
                    }
                } else {
                    $urlimage = $chatId . bin2hex(random_bytes(3)) . '.png';
                    file_put_contents($urlimage, $qrCode->getString());
                    if (method_exists(\Faoxima\Telegram\Support\QrAndKeys::class, 'addBackgroundImage')) @\Faoxima\Telegram\Support\QrAndKeys::addBackgroundImage($urlimage, $qrCode, 'images.jpg');
                    rxSendMediaWithCaption('sendphoto', ['chat_id' => $chatId, 'photo' => new CURLFile($urlimage), 'caption' => $shortCaption, 'parse_mode' => 'HTML'], null, $stockDelivery);
                    $sent = $stockDelivery['delivery_status'] === 'completed';
                    @unlink($urlimage);
                }
            } catch (Throwable $e) {
                error_log('nmStockSendQr failed: ' . $e->getMessage());
            }
        }
        if (trim((string)$fullText) !== '') {
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmSendLongStockMessage')) self::nmSendLongStockMessage($chatId, $fullText);
            else sendmessage($chatId, $fullText, null, 'HTML');
        }
        return $sent;
    }

    public static function nmStockDeliveryKeyboard($content, $invoiceId = '', $subLink = '', array $panel = null)
    {
        $styles = self::nmStockStyles();
        $buttons = [];
        $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$invoiceId);
        if ($invoiceId !== '' && $panel !== null) {
            $sub = trim((string)$subLink);
            $cfg = trim((string)$content);
            $hasLink = ($sub !== '');
            $hasConfig = ($cfg !== '' && $cfg !== $sub);
            $row1 = [];
            if ((string)($panel['status_extend'] ?? 'on_extend') !== 'off_extend') {
                $row1[] = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_extend'), 'callback_data' => 'nmstockextend_' . $invoiceId], 'nmstock_actions', $styles);
            }
            if (!method_exists(\Faoxima\Services\Panels\PanelFeatures::class, 'panel_feature_enabled') || \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, 'refund')) {
                $row1[] = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_refund'), 'callback_data' => 'nmstockrefund_' . $invoiceId], 'nmstock_actions', $styles);
            }
            if ($row1) $buttons[] = $row1;
            $row2 = [];
            if ((!method_exists(\Faoxima\Services\Panels\PanelFeatures::class, 'panel_feature_enabled') || \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, 'configbtn')) && $hasConfig) {
                $row2[] = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_get_config'), 'callback_data' => 'nmstockcfg_' . $invoiceId], 'nmstock_actions', $styles);
            }
            if ($hasLink) {
                $row2[] = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_sub_link'), 'callback_data' => 'nmstocksub_' . $invoiceId], 'nmstock_actions', $styles);
            }
            if ($row2) $buttons[] = $row2;
        } elseif ($invoiceId !== '') {
            $buttons[] = [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_extend'), 'callback_data' => 'nmstockextend_' . $invoiceId], 'nmstock_actions', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_refund'), 'callback_data' => 'nmstockrefund_' . $invoiceId], 'nmstock_actions', $styles),
            ];
            $buttons[] = [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_get_config'), 'callback_data' => 'nmstockcfg_' . $invoiceId], 'nmstock_actions', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_sub_link'), 'callback_data' => 'nmstocksub_' . $invoiceId], 'nmstock_actions', $styles),
            ];
        } else {
            $buttons[] = [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_get_config'), 'callback_data' => 'none', '_rxcolor' => 'service_template:stock_config']];
            $link = trim((string)$subLink) !== '' ? trim((string)$subLink) : trim((string)$content);
            if (preg_match('/^https?:\/\//i', $link)) $buttons[] = [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_sub_link'), 'url' => $link, '_rxcolor' => 'service_template:stock_subscription']];
            else $buttons[] = [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_sub_link'), 'callback_data' => 'none', '_rxcolor' => 'service_template:stock_subscription']];
        }
        $buttons[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_stock_back_to_list'), 'callback_data' => 'backorder'], 'backorder', $styles)];
        return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function nmPanelNationalEnabled(array $panel = null) {
    return \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel);
}

    public static function nmStockReserveForProduct(array $panel, array $product, $userId, $invoiceId, $mode = 'national_buy')
    {
        global $pdo;
        return \Faoxima\Services\Fulfillment\Inventory::nmStockReserveForProduct($pdo, $panel, $product, $userId, $invoiceId, $mode);
    }

    public static function nmStockShelfById($id)
    {
        global $pdo;
        return \Faoxima\Services\Fulfillment\Inventory::nmStockShelfById($pdo, $id);
    }

    public static function nmServiceRestrictedNotice()
    {
        return '🌐 وضعیت نت ملی این پنل روشن است؛ این سرویس فعلاً از ربات نمایش داده نمی‌شود. وقتی نت ملی خاموش شود، سرویس‌های قبلی دوباره در دسترس قرار می‌گیرند.';
    }

    public static function nmStopIfServicePanelBlocked($invoice, $userId = null, $keyboard = null)
    {
        if (!is_array($invoice) || !\Faoxima\Services\Fulfillment\Inventory::nmServicePanelAccessBlocked($invoice)) return false;
        if ($keyboard === null) {
            $back = $GLOBALS['textbotlang']['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها';
            $keyboard = json_encode(['inline_keyboard' => [[['text' => $back, 'callback_data' => 'backorder']]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (function_exists('sendmessage') && $userId !== null) {
            sendmessage($userId, self::nmServiceRestrictedNotice(), $keyboard, 'HTML');
        }
        return true;
    }
}
