<?php

if (!function_exists('rx_auth_skip_user')) {
    function rx_auth_skip_user($user)
    {
        return false;
    }
}

if (!function_exists('rx_randomWalletAmounts')) {
    function rx_randomWalletAmounts()
    {
        $default = [50000, 75000, 100000, 150000, 200000, 250000, 500000, 1000000];
        $row = select("PaySetting", "ValuePay", "NamePay", "randomwallet_amounts", "select");
        $raw = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded) || count($decoded) !== 8) {
            return $default;
        }
        return array_map('intval', array_values($decoded));
    }
}

if (!function_exists('rx_promptWalletChargeCustomAmount')) {
    function rx_promptWalletChargeCustomAmount($from_id, $message_id, $user)
    {
        global $textbotlang, $datatextbot, $setting;

        $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
        $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
        $_rx_acc_styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $_rx_all_kbs = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($_rx_all_kbs) && !empty($_rx_all_kbs['account'])) {
                $_rx_acc_styles = array_filter($_rx_all_kbs['account'], static function ($v) { return $v !== 'default'; });
            }
        }
        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $_rx_acc_styles = $_rx_acc_styles + rx_getKeyboardDefaultStyles('account');
        }
        $bakinfos = json_encode([
            'inline_keyboard' => [
                [
                    rx_kb_style(['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"], 'backinfo', $_rx_acc_styles),
                ]
            ]
        ]);
        $rxWalletAmountPromptTpl = $datatextbot['dyn_wallet_prompt_amount_prompt'] ?? "💸 مبلغ را  به تومان وارد کنید:\n✅  حداقل مبلغ %s حداکثر مبلغ %s تومان می باشد";
        Editmessagetext($from_id, $message_id, sprintf($rxWalletAmountPromptTpl, $minbalance, $maxbalance), $bakinfos, 'HTML');
        step('getprice', $from_id);
        update("user", 'Processing_value', $message_id, "id", $from_id);
    }
}

if (!function_exists('rx_walletChargeAmountAccepted')) {
    function rx_walletChargeAmountAccepted($from_id, $user, $amount)
    {
        global $setting, $textbotlang, $datatextbot;

        if (!is_numeric($amount)) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
            return false;
        }

        $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
        $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
        if ($amount > $maxbalance or $amount < $minbalance) {
            $minbalanceFmt = number_format($minbalance);
            $maxbalanceFmt = number_format($maxbalance);
            $rxWalletOutOfRangeTpl = $datatextbot['dyn_wallet_prompt_amount_out_of_range'] ?? "❌ خطا\n💬 مبلغ باید حداقل %s تومان و حداکثر %s تومان باشد";
            sendmessage($from_id, sprintf($rxWalletOutOfRangeTpl, $minbalanceFmt, $maxbalanceFmt), null, 'HTML');
            return false;
        }

        if ($user['Balance'] < 0 and intval($setting['Debtsettlement']) == 1) {
            $balancruser = abs($user['Balance']);
            if ($amount < $balancruser) {
                $rxDebtMustPayTpl = $datatextbot['dyn_wallet_prompt_debt_must_pay_first'] ?? "❌ شما بدهی دارید، باید حداقل %s تومان پرداخت کنید.\n         میبغ خود را مجددا ارسال نمایید";
                sendmessage($from_id, sprintf($rxDebtMustPayTpl, $balancruser), null, 'HTML');
                return false;
            }
        }

        update("user", "Processing_value", $amount, "id", $from_id);
        update("user", "Processing_value_one", "", "id", $from_id);
        update("user", "Processing_value_tow", "", "id", $from_id);
        update("user", "Processing_value_four", "", "id", $from_id);

        $__discEligibleCharge = class_exists('MiniDiscount') ? MiniDiscount::hasEligible('charge', '', '', '', $user) : false;
        if (!$__discEligibleCharge) {
            global $step_payment;
            sendmessage($from_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return true;
        }

        $__askdisc = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $datatextbot['dyn_wallet_prompt_has_discount_yes'] ?? "✅ بله، کد تخفیف دارم", 'callback_data' => "chargehasdiscount"],
                ],
                [
                    ['text' => $datatextbot['dyn_wallet_prompt_has_discount_no'] ?? "➡️ خیر، ادامه به پرداخت", 'callback_data' => "chargenodiscount"],
                ]
            ]
        ]);
        sendmessage($from_id, $datatextbot['dyn_wallet_prompt_ask_has_discount'] ?? "🎁 آیا برای شارژ کیف پول کد تخفیف دارید؟", $__askdisc, 'HTML');
        step('home', $from_id);
        return true;
    }
}

function deleteFolder($folderPath)
{
    if (!is_dir($folderPath))
        return false;

    $files = array_diff(scandir($folderPath), ['.', '..']);

    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteFolder($filePath);
        } else {
            unlink($filePath);
        }
    }

    return rmdir($folderPath);
}
function isBase64($string)
{
    if (base64_encode(base64_decode($string, true)) === $string) {
        return true;
    }
    return false;
}
function rxStripDeliveryContentFromCaption($caption)
{
    $caption = (string) $caption;
    $markers = [
        "🔗 لینک اتصال:",
        "🔗 لینک اتصال :",
        "لینک اتصال:",
        "لینک اتصال :",
        "🔐 کانفیگ اشتراک :",
        "🔐 کانفیگ اشتراک:",
        "لینک اشتراک:",
        "لینک اشتراک :",
    ];
    foreach ($markers as $marker) {
        $pattern = '/(^|\R)[^\S\r\n]*' . preg_quote($marker, '/') . '[^\S\r\n]*(?:\r?\n)*'
            . '(?:[^\S\r\n]*(?:<code>.*?<\/code>|[A-Za-z][A-Za-z0-9+.\-]*:\/\/\S+)[^\S\r\n]*(?:\r?\n|$))*/us';
        $caption = preg_replace($pattern, '$1', $caption, 1);
    }
    $caption = preg_replace('/(^|\R)[^\S\r\n]*<code>[^<]*:\/\/[^<]*<\/code>/us', '$1', $caption);
    $caption = preg_replace('/(^|\R)[^\S\r\n]*[A-Za-z][A-Za-z0-9+.\-]*:\/\/\S+[^\S\r\n]*($|\R)/u', '$1', $caption);
    $caption = preg_replace('/[^\S\r\n]+$/mu', '', $caption);
    $caption = preg_replace("/\n{3,}/u", "\n\n", $caption);
    return trim($caption);
}
function rxSettingFlag($key, $default = true)
{
    global $setting;
    $value = null;
    if (is_array($setting ?? null) && array_key_exists($key, $setting)) {
        $value = $setting[$key];
    } elseif (function_exists('select')) {
        try {
            $row = select('setting', $key, null, null, 'select');
            if (is_array($row) && array_key_exists($key, $row)) {
                $value = $row[$key];
            } elseif (is_string($row) || is_numeric($row)) {
                $value = $row;
            }
        } catch (Throwable $e) {
            $value = null;
        }
    }
    if ($value === null || $value === '') {
        return (bool)$default;
    }
    return !in_array(strtolower(trim((string)$value)), ['0', 'off', 'false', 'no'], true);
}
function rxReceiptDeliveryRoute($topicKey = 'receiptreport')
{
    global $pdo, $setting;
    $row = null;
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->query("SELECT receipt_topic_reporting, Channel_Report FROM setting ORDER BY CASE WHEN TRIM(COALESCE(Channel_Report, '')) NOT IN ('', '0') THEN 0 ELSE 1 END LIMIT 1");
            $fresh = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (is_array($fresh)) {
                $row = $fresh;
            }
        } catch (Throwable $e) {
            $row = null;
        }
    }
    if (!is_array($row)) {
        $row = is_array($setting ?? null) ? $setting : [];
    }
    $enabled = !array_key_exists('receipt_topic_reporting', $row)
        || !in_array(strtolower(trim((string)$row['receipt_topic_reporting'])), ['0', 'off', 'false', 'no'], true);
    $chatId = $enabled ? trim((string)($row['Channel_Report'] ?? '')) : '';
    if ($chatId === '0') {
        $chatId = '';
    }
    $threadId = null;
    if ($enabled && $chatId !== '' && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT idreport FROM topicid WHERE report = ? LIMIT 1');
            $stmt->execute([(string)$topicKey]);
            $candidate = (int)$stmt->fetchColumn();
            if ($candidate > 0) {
                $threadId = $candidate;
            }
        } catch (Throwable $e) {
            $threadId = null;
        }
    }
    return ['topic_enabled' => $enabled, 'chat_id' => $chatId, 'thread_id' => $threadId];
}
function rxSubscriptionLinkButtonEnabled($panel_info)
{
    if (($panel_info['type'] ?? '') === 'Manualsale') {
        return true;
    }
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel_info)) {
        return true;
    }
    return rxSettingFlag('subscription_link_button', true);
}
function rxAppendSubscriptionLinkLine($caption, $sub_link, $panel_info)
{
    $caption = trim((string)$caption);
    $subLink = trim((string)$sub_link);
    if ($subLink === '' || rxSubscriptionLinkButtonEnabled($panel_info)) {
        if (function_exists('mb_strlen') && mb_strlen($caption, 'UTF-8') > 1024) {
            $plainCaption = trim(html_entity_decode(strip_tags($caption), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return htmlspecialchars(mb_substr($plainCaption, 0, 1024, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        }
        return $caption;
    }
    $lineTemplate = faoxima_textbot_get('dyn_purchase_subscription_link_line', '🔗 لینک اتصال: {link}');
    $line = trim(faoxima_render_text($lineTemplate, [
        'link' => htmlspecialchars($subLink, ENT_QUOTES, 'UTF-8'),
    ]));
    if ($line === '') {
        return $caption;
    }
    $combined = $caption === '' ? $line : $caption . "\n\n" . $line;
    if (function_exists('mb_strlen') && mb_strlen($combined, 'UTF-8') > 1024) {
        $plainCaption = trim(html_entity_decode(strip_tags($caption), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $allowance = max(0, 1022 - mb_strlen($line, 'UTF-8'));
        $plainCaption = mb_substr($plainCaption, 0, $allowance, 'UTF-8');
        $safeCaption = htmlspecialchars($plainCaption, ENT_QUOTES, 'UTF-8');
        $combined = $safeCaption === '' ? $line : $safeCaption . "\n\n" . $line;
    }
    return $combined;
}
function rxManualsalePublicName($recordName, $ext)
{
    $ext = strtolower(ltrim(trim((string) $ext), '.'));
    if ($ext === '') {
        $ext = 'bin';
    }
    $stem = trim((string) $recordName);
    $suffix = '.' . $ext;
    if ($stem !== '' && strtolower(substr($stem, -strlen($suffix))) === $suffix) {
        $stem = substr($stem, 0, -strlen($suffix));
    }
    return normalizeConfigFilename($stem . $suffix, '', $ext === 'conf');
}
function rxManualsaleTempPath($publicName)
{
    $tempExtension = strtolower((string) pathinfo((string) $publicName, PATHINFO_EXTENSION));
    $tempExtension = preg_match('/^[a-z0-9]{1,10}$/', $tempExtension) ? '.' . $tempExtension : '.tmp';
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxmanual-' . bin2hex(random_bytes(8)) . $tempExtension;
}
function rxManualsaleDelivery($username_service)
{
    $result = [
        'ext' => '',
        'name' => '',
        'content' => '',
        'sub_link' => '',
        'is_file' => false,
        'is_text' => false,
        'is_link' => false,
        'sub' => '',
        'configs' => [],
        'items' => [],
    ];
    if (!function_exists('select') || trim((string) $username_service) === "") {
        return $result;
    }
    $rows = select("manualsell", "*", "username", $username_service, "fetchAll");
    if (!is_array($rows) || empty($rows)) {
        return $result;
    }
    $row = $rows[0];
    if (!is_array($row)) {
        return $result;
    }
    $ext = strtolower(ltrim(trim((string) ($row['file_ext'] ?? "")), '.'));
    $content = (string) ($row['contentrecord'] ?? "");
    $subLink = trim((string) ($row['sub_link'] ?? ""));
    $isFile = function_exists('rxManualsaleExtIsFile') ? rxManualsaleExtIsFile($ext) : ($ext !== '' && $ext !== 'sub' && $ext !== 'text');
    $isText = ($ext === 'text');
    $isLink = ($ext === 'sub');
    $result['ext'] = $ext;
    $result['name'] = trim((string) ($row['namerecord'] ?? ''));
    $result['content'] = $content;
    $result['sub_link'] = $subLink;
    $result['is_file'] = $isFile;
    $result['is_text'] = $isText;
    $result['is_link'] = $isLink;
    $result['codepanel'] = trim((string) ($row['codepanel'] ?? ''));
    $rxManualPanelRef = $result['codepanel'] !== '' ? 'code:' . $result['codepanel'] : '';
    if ($isLink) {
        $result['sub'] = trim($content) !== '' ? $content : $subLink;
        $result['configs'] = [];
    } else {
        $result['sub'] = $subLink;
        $result['configs'] = (trim($content) !== '') ? [$content] : [];
    }
    $items = [];
    $unifiedSub = trim($subLink);
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $rExt = strtolower(ltrim(trim((string) ($r['file_ext'] ?? "")), '.'));
        $rContent = (string) ($r['contentrecord'] ?? "");
        $rSub = trim((string) ($r['sub_link'] ?? ""));
        if ($unifiedSub === '' && $rSub !== '') {
            $unifiedSub = $rSub;
        }
        $rIsFile = function_exists('rxManualsaleExtIsFile') ? rxManualsaleExtIsFile($rExt) : ($rExt !== '' && $rExt !== 'sub' && $rExt !== 'text');
        $rIsText = ($rExt === 'text');
        $rIsLink = ($rExt === 'sub');
        if ($rIsLink) {
            $linkVal = trim($rContent) !== '' ? $rContent : $rSub;
            if ($unifiedSub === '' && trim((string) $linkVal) !== '') {
                $unifiedSub = trim((string) $linkVal);
            }
            continue;
        }
        if (trim($rContent) === '') {
            continue;
        }
        $items[] = [
            'id' => (int) ($r['id'] ?? 0),
            'panel_ref' => $rxManualPanelRef,
            'ext' => $rExt,
            'name' => trim((string) ($r['namerecord'] ?? '')),
            'content' => $rContent,
            'sub_link' => $rSub,
            'is_file' => $rIsFile,
            'is_text' => $rIsText,
        ];
    }
    $result['items'] = $items;
    if ($unifiedSub !== '') {
        $result['sub'] = $isLink && trim($content) !== '' ? $result['sub'] : $unifiedSub;
    }
    $allConfigs = [];
    foreach ($items as $it) {
        if (!$it['is_file'] && trim((string) $it['content']) !== '') {
            $allConfigs[] = $it['content'];
        }
    }
    if (!empty($allConfigs)) {
        $result['configs'] = $allConfigs;
    }
    return $result;
}
function rxManualsaleFileItems($items)
{
    $fileItems = [];
    if (!is_array($items)) {
        return $fileItems;
    }
    foreach ($items as $it) {
        if (is_array($it) && !empty($it['is_file'])) {
            $fileItems[] = $it;
        }
    }
    return $fileItems;
}
function rxManualsaleSendFileItems($user_id, $username_service, $fileItems, $caption = '', $reply_markup = null, $totalForNaming = 0, $startIndex = 0)
{
    if (!is_array($fileItems) || empty($fileItems)) {
        return false;
    }
    $fileCount = count($fileItems);
    $index = 0;
    $sentAny = false;
    $rxManualKeyCounts = function_exists('rxFileNamingManualKeyCounts') ? rxFileNamingManualKeyCounts($fileItems) : [];
    foreach ($fileItems as $fi) {
        if (!is_array($fi)) {
            continue;
        }
        $index++;
        $ext = (trim((string) ($fi['ext'] ?? '')) !== '') ? $fi['ext'] : 'bin';
        $publicName = rxManualsalePublicName($fi['name'] ?? '', $ext);
        $isLastFile = ($index === $fileCount);
        $thisCaption = ($index === 1) ? (string) $caption : '';
        if (function_exists('rxFileNamingResolveManualItem')) {
            $rxManualKey = rxFileNamingManualItemKey($fi);
            $rxManualAmbiguous = ($rxManualKey !== '' && (($rxManualKeyCounts[$rxManualKey] ?? 0) > 1));
            $rxManualResolved = rxFileNamingResolveManualItem($fi, $publicName, $thisCaption, $rxManualAmbiguous);
            $publicName = $rxManualResolved['filename'];
            $thisCaption = $rxManualResolved['caption'];
        }
        $manualFile = rxManualsaleTempPath($publicName);
        file_put_contents($manualFile, (string) ($fi['content'] ?? ''));
        $thisMarkup = $isLastFile ? $reply_markup : null;
        if (is_file($manualFile) && is_readable($manualFile)) {
            telegram('sendDocument', [
                'chat_id' => $user_id,
                'document' => new CURLFile($manualFile, 'application/octet-stream', $publicName),
                'caption' => $thisCaption,
                'reply_markup' => $thisMarkup,
                'parse_mode' => 'HTML',
            ]);
            @unlink($manualFile);
            $sentAny = true;
        } elseif (trim($thisCaption) !== '') {
            sendmessage($user_id, $thisCaption, $thisMarkup, 'HTML');
        }
    }
    return $sentAny;
}
function rxRefundHardDeleteService($panelName, $usernameService, $idInvoice)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return false;
    }
    $usernameService = trim((string) $usernameService);
    $idInvoice = trim((string) $idInvoice);
    $deleted = false;
    $panelType = '';
    if (trim((string) $panelName) !== '') {
        $panelRow = select('marzban_panel', '*', 'name_panel', $panelName, 'select');
        if (is_array($panelRow)) {
            $panelType = (string) ($panelRow['type'] ?? '');
        }
    }
    if ($panelType === 'Manualsale' && $usernameService !== '') {
        try {
            $stmt = $pdo->prepare("DELETE FROM manualsell WHERE username = :u");
            $stmt->bindValue(':u', $usernameService, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $deleted = true;
            }
        } catch (Throwable $e) {
            error_log('rxRefundHardDeleteService manualsell failed: ' . $e->getMessage());
        }
    }
    if ($idInvoice !== '') {
        try {
            $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE assigned_invoice = :iid");
            $stmt->bindValue(':iid', $idInvoice, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $deleted = true;
                if (function_exists('nmStockLog')) {
                    try {
                        nmStockLog(null, '', $idInvoice, 'refund_stock_deleted', ['username' => $usernameService]);
                    } catch (Throwable $e) {
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('rxRefundHardDeleteService nm_config_stock failed: ' . $e->getMessage());
        }
    }
    return $deleted;
}
function rxDeliveryAvailability($panel_info, $config, $sub_link)
{
    if (($panel_info['type'] ?? '') == "Manualsale") {
        $manual = is_array($config) && isset($config['__manual__']) ? $config['__manual__'] : null;
        if (!is_array($manual)) {
            $manual = [
                'sub' => is_string($sub_link) ? trim($sub_link) : '',
                'configs' => is_array($config) ? $config : [],
            ];
        }
        $hasSub = isset($manual['sub']) && trim((string) $manual['sub']) !== '';
        $validConfigs = isset($manual['configs']) && is_array($manual['configs']) ? array_values(array_filter($manual['configs'], function ($item) {
            return is_string($item) && trim($item) !== '';
        })) : [];
        return ['sub' => $hasSub, 'config' => !empty($validConfigs)];
    }
    $hasSub = (($panel_info['sublink'] ?? '') == "onsublink") && is_string($sub_link) && trim($sub_link) !== '';
    $validConfigs = is_array($config) ? array_values(array_filter($config, function ($item) {
        return is_string($item) && trim($item) !== '';
    })) : [];
    $hasConfig = (($panel_info['config'] ?? '') == "onconfig") && !empty($validConfigs);
    return ['sub' => $hasSub, 'config' => $hasConfig];
}
function rxBuildDeliveryOffer($panel_info, $invoice_id, $hasConfig, $hasSub, $reply_markup)
{
    global $textbotlang;
    $mergedKb = json_decode((string) $reply_markup, true);
    if (!is_array($mergedKb) || !isset($mergedKb['inline_keyboard']) || !is_array($mergedKb['inline_keyboard'])) {
        $mergedKb = ['inline_keyboard' => []];
    }
    $offerRows = [];
    if ($hasConfig) {
        $offerRows[] = [['text' => "🔐 دریافت کانفیگ", 'callback_data' => "config_{$invoice_id}"]];
    }
    if ($hasSub && rxSubscriptionLinkButtonEnabled($panel_info)) {
        $offerRows[] = [['text' => "🔗 دریافت لینک اشتراک", 'callback_data' => "subscriptionurl_{$invoice_id}"]];
    }
    if (empty($offerRows)) {
        return null;
    }
    array_splice($mergedKb['inline_keyboard'], 0, 0, $offerRows);
    return json_encode($mergedKb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
function rxAutoFilePanelRow($panel_info)
{
    if (is_array($panel_info)) {
        if (array_key_exists('auto_file', $panel_info)) {
            return $panel_info;
        }
        $panel_name = trim((string) ($panel_info['name_panel'] ?? ''));
        $panel_code = trim((string) ($panel_info['code_panel'] ?? ''));
        if (function_exists('select')) {
            $fresh = $panel_name !== '' ? select('marzban_panel', '*', 'name_panel', $panel_name, 'select') : false;
            if (!is_array($fresh) && $panel_code !== '') {
                $fresh = select('marzban_panel', '*', 'code_panel', $panel_code, 'select');
            }
            if (is_array($fresh)) {
                return $fresh;
            }
        }
        return $panel_info;
    }
    if (function_exists('panel_feature_resolve_row')) {
        $row = panel_feature_resolve_row($panel_info);
        return is_array($row) ? $row : null;
    }
    if (function_exists('select')) {
        $row = select('marzban_panel', '*', 'name_panel', (string) $panel_info, 'select');
        return is_array($row) ? $row : null;
    }
    return null;
}
function rxPanelAutoFileEnabled($panel_info)
{
    $row = rxAutoFilePanelRow($panel_info);
    return is_array($row) && (string) ($row['auto_file'] ?? 'offautofile') === 'onautofile';
}
function rxAutoFileDeliveryState($invoice_id)
{
    $invoice_id = trim((string) $invoice_id);
    if ($invoice_id === '') {
        return [];
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('Automatic file delivery state database is unavailable');
    }
    try {
        $stmt = $pdo->prepare('SELECT file_key, status FROM auto_file_delivery WHERE id_invoice = ?');
        $stmt->execute([$invoice_id]);
        $state = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $state[(string) ($row['file_key'] ?? '')] = (string) ($row['status'] ?? '');
            }
        }
        return $state;
    } catch (Throwable $e) {
        throw new RuntimeException('Automatic file delivery state could not be read', 0, $e);
    }
}
function rxAutoFileDeliveryMark($invoice_id, $file_key, $status, $detail = '')
{
    $invoice_id = trim((string) $invoice_id);
    $file_key = trim((string) $file_key);
    $status = (string) $status;
    if ($invoice_id === '' || $file_key === '' || !in_array($status, ['sent', 'failed', 'unknown'], true)) {
        return false;
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) {
        return false;
    }
    $detail = trim((string) $detail);
    if (function_exists('mb_substr')) {
        $detail = mb_substr($detail, 0, 500, 'UTF-8');
    } else {
        $detail = substr($detail, 0, 500);
    }
    $now = time();
    try {
        $stmt = $pdo->prepare('INSERT INTO auto_file_delivery (id_invoice, file_key, status, detail, attempts, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE status = IF(status = \'sent\', \'sent\', VALUES(status)), detail = VALUES(detail), attempts = attempts + 1, updated_at = VALUES(updated_at)');
        $stmt->execute([$invoice_id, $file_key, $status, $detail, $now, $now]);
        return true;
    } catch (Throwable $e) {
        error_log('rxAutoFileDeliveryMark failed: ' . $e->getMessage());
        return false;
    }
}
function rxAutoFileKey($prefix, $value)
{
    return hash('sha256', trim((string) $prefix) . '|' . (string) $value);
}
function rxAutoFileTempPath($public_name)
{
    $ext = strtolower((string) pathinfo((string) $public_name, PATHINFO_EXTENSION));
    $ext = preg_match('/^[a-z0-9]{1,10}$/', $ext) ? '.' . $ext : '.tmp';
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rxauto-' . bin2hex(random_bytes(8)) . $ext;
}
function rxAutoFileResolveNaming($panel, $source, $key, $name, $caption, $ambiguous = false)
{
    if (function_exists('rxFileNamingResolve')) {
        return rxFileNamingResolve($panel, $source, $key, $name, $caption, $ambiguous);
    }
    return ['filename' => (string) $name, 'caption' => (string) $caption];
}
function rxAutoFileSendDocument($user_id, $invoice_id, $file_key, $path, $public_name, $caption)
{
    if (!is_file($path) || !is_readable($path)) {
        rxAutoFileDeliveryMark($invoice_id, $file_key, 'failed', 'temporary file unavailable');
        return 'failed';
    }
    try {
        $result = telegram('sendDocument', [
            'chat_id' => $user_id,
            'document' => new CURLFile($path, 'application/octet-stream', $public_name),
            'caption' => (string) $caption,
            'parse_mode' => 'HTML',
        ]);
    } catch (Throwable $e) {
        rxAutoFileDeliveryMark($invoice_id, $file_key, 'unknown', $e->getMessage());
        return 'unknown';
    }
    $status = is_array($result) ? (!empty($result['ok']) ? 'sent' : 'failed') : 'unknown';
    $detail = is_array($result) ? (string) ($result['description'] ?? '') : '';
    rxAutoFileDeliveryMark($invoice_id, $file_key, $status, $detail);
    return $status;
}
function rxAutoFileSendContent($user_id, $invoice_id, $file_key, $content, $public_name, $caption)
{
    $temp = rxAutoFileTempPath($public_name);
    try {
        if (file_put_contents($temp, (string) $content) === false) {
            rxAutoFileDeliveryMark($invoice_id, $file_key, 'failed', 'temporary file write failed');
            return 'failed';
        }
        return rxAutoFileSendDocument($user_id, $invoice_id, $file_key, $temp, $public_name, $caption);
    } finally {
        @unlink($temp);
    }
}
function rxAutoDeliverPurchaseFiles($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id)
{
    $empty = ['ok' => true, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
    $panel = rxAutoFilePanelRow($panel_info);
    if (!rxPanelAutoFileEnabled($panel) || ($panel['type'] ?? '') === 'Manualsale') return $empty;
    if (trim((string) $invoice_id) === '' || trim((string) $user_id) === '') return $empty;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) throw new RuntimeException('Automatic file delivery database is unavailable');
    $lock = 'rxauto:' . substr(hash('sha256', (string) ($GLOBALS['dbname'] ?? '') . '|' . (string) $invoice_id), 0, 56);
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute([$lock]);
    $acquired = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ((string) $acquired !== '1') {
        $empty['ok'] = false;
        return $empty;
    }
    try {
        return rxAutoDeliverPurchaseFilesUnlocked($panel, $config, $sub_link, $username_service, $user_id, $invoice_id);
    } finally {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lock]);
            $release->closeCursor();
        } catch (Throwable $e) {
            error_log('Automatic file delivery lock release failed: ' . $e->getMessage());
        }
    }
}
function rxAutoDeliverPurchaseFilesUnlocked($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id)
{
    global $ManagePanel;
    $result = ['ok' => true, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
    $panel = rxAutoFilePanelRow($panel_info);
    if (!rxPanelAutoFileEnabled($panel)) {
        return $result;
    }
    $user_id = trim((string) $user_id);
    $invoice_id = trim((string) $invoice_id);
    $username_service = trim((string) $username_service);
    if (!is_array($panel) || $user_id === '' || $invoice_id === '' || $username_service === '') {
        return $result;
    }
    if ((string) ($panel['type'] ?? '') === 'Manualsale') {
        return $result;
    }
    $autoManager = is_object($ManagePanel) ? $ManagePanel : (class_exists('ManagePanel') ? new ManagePanel() : null);
    $state = rxAutoFileDeliveryState($invoice_id);
    if ((string) ($panel['type'] ?? '') === 'jovadpanel') {
        if (function_exists('jovadpanelAutoDeliverFiles')) {
            $jovadResult = jovadpanelAutoDeliverFiles($user_id, $panel, $username_service, [
                'should_send' => function ($fileKey) use ($state) {
                    return (string) ($state[$fileKey] ?? '') !== 'sent';
                },
                'on_result' => function ($fileKey, $status, $detail) use ($invoice_id) {
                    rxAutoFileDeliveryMark($invoice_id, $fileKey, $status, $detail);
                },
            ]);
            if (is_array($jovadResult)) {
                $result['ok'] = !empty($jovadResult['ok']);
                $result['sent'] = (int) ($jovadResult['sent'] ?? 0);
                $result['failed'] = (int) ($jovadResult['failed'] ?? 0);
                $result['unknown'] = (int) ($jovadResult['unknown'] ?? 0);
                $result['skipped'] = (int) ($jovadResult['skipped'] ?? 0);
                $result['total'] = (int) ($jovadResult['total'] ?? 0);
            }
        }
        return $result;
    }
    if ((string) ($panel['type'] ?? '') === 'WGDashboard') {
        $content = (string) $sub_link;
        if (trim($content) === '' && is_object($autoManager)) {
            $wgUser = $autoManager->DataUser($panel['name_panel'], $username_service);
            if (is_array($wgUser)) {
                $content = (string) ($wgUser['subscription_url'] ?? '');
            }
        }
        if (trim($content) === '') {
            return $result;
        }
        $defaultName = normalizeConfigFilename("{$panel['inboundid']}-{$username_service}.conf", $panel['namecustom'] ?? '', true);
        $key = function_exists('rxFileNamingKeySubscription') ? rxFileNamingKeySubscription($panel['inboundid'] ?? '') : '';
        $resolved = rxAutoFileResolveNaming($panel, 'wgdashboard', $key, $defaultName, '');
        $fileKey = rxAutoFileKey('wgdashboard', $content);
        if ((string) ($state[$fileKey] ?? '') === 'sent') {
            $result['skipped']++;
            return $result;
        }
        $result['total']++;
        $status = rxAutoFileSendContent($user_id, $invoice_id, $fileKey, $content, $resolved['filename'], $resolved['caption']);
        $result[$status] = (int) $result[$status] + 1;
        $result['ok'] = $status === 'sent';
        return $result;
    }
    $links = [];
    if (is_array($config)) {
        foreach ($config as $item) {
            if (is_string($item) && trim($item) !== '') {
                $links[] = (string) $item;
            }
        }
    } elseif (is_string($config) && trim($config) !== '') {
        $links[] = $config;
    }
    if ((string) ($panel['type'] ?? '') === 'x-ui_single' && trim((string) $sub_link) !== '') {
        $subContent = (string) $sub_link;
        if (preg_match('/\[Interface\]/i', $subContent) && preg_match('/\[Peer\]/i', $subContent)) {
            array_unshift($links, $subContent);
        }
    }
    $lazyDataUser = null;
    if (($panel['type'] ?? '') === 'x-ui_single' && is_object($autoManager)) {
        $hasFileSource = false;
        foreach ($links as $candidateLink) {
            if (preg_match('~^(?:xuifile|xuiawgfile|wireguard|wg|vpn|awg|amneziawg)://~i', trim($candidateLink))
                || (preg_match('/^\h*\[Interface\]\h*\r?$/mi', $candidateLink) && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $candidateLink))) {
                $hasFileSource = true;
                break;
            }
        }
        if (!$hasFileSource) {
            $lazyDataUser = $autoManager->DataUser($panel['name_panel'], $username_service);
            $singleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
            if (is_array($singleFile) && trim((string) ($singleFile['value'] ?? '')) !== '') {
                $scheme = ($singleFile['protocol'] ?? '') === 'amneziawg' ? 'xuiawgfile' : 'xuifile';
                array_unshift($links, $scheme . '://' . rawurlencode((string) ($singleFile['filename'] ?? 'wireguard.conf')));
            }
        }
    }
    if (empty($links)) {
        return $result;
    }
    $namingLinks = [];
    foreach ($links as $namingLink) {
        $namingLinks[] = ($panel['type'] ?? '') === 'x-ui_single'
            && preg_match('/^\h*\[Interface\]\h*\r?$/mi', $namingLink)
            && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $namingLink)
            ? 'vpn://' . base64_encode($namingLink) : $namingLink;
    }
    $keyCounts = function_exists('rxFileNamingLinkKeyCounts') ? rxFileNamingLinkKeyCounts($namingLinks, $panel) : [];
    $deliveredKeys = [];
    foreach ($links as $index => $link) {
        $rawLink = (string) $link;
        $link = trim((string) $link);
        if ($link === '' || (function_exists('jovadIsProtocolMarker') && jovadIsProtocolMarker($link))) {
            continue;
        }
        $fileKey = '';
        $content = '';
        $publicName = '';
        $caption = '';
        if (($panel['type'] ?? '') === 'x-ui_single' && preg_match('/^\h*\[Interface\]\h*\r?$/mi', $rawLink) && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $rawLink)) {
            $content = $rawLink;
            $protocol = xui_wg_conf_protocol($content) ?? 'wireguard';
            if ($lazyDataUser === null && is_object($autoManager)) {
                $lazyDataUser = $autoManager->DataUser($panel['name_panel'], $username_service);
            }
            $singleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
            if (is_array($singleFile) && (string) ($singleFile['value'] ?? '') === $content) {
                $defaultName = normalizeConfigFilename($singleFile['filename'] ?? 'wireguard.conf', '', true);
                $namingKey = rxFileNamingScopedKey(rxFileNamingKeyFile($singleFile['filename'] ?? ''), $protocol);
                $resolved = rxAutoFileResolveNaming($panel, 'xui_file', $namingKey, $defaultName, '');
            } else {
                $encodedLink = 'vpn://' . base64_encode($content);
                $descriptor = rxFileNamingDescriptorFromLink($encodedLink);
                $namingKey = is_array($descriptor) ? $descriptor['key'] : '';
                $defaultName = normalizeConfigFilename(xui_wg_conf_filename($encodedLink, $protocol, (int) $index), '', true);
                $resolved = rxAutoFileResolveNaming($panel, 'xui_wg', $namingKey, $defaultName, '', $namingKey !== '' && (($keyCounts[$namingKey] ?? 0) > 1));
            }
            $publicName = $resolved['filename'];
            $caption = $resolved['caption'];
            $fileKey = rxAutoFileKey('config_content', $protocol . '|' . $content);
        } elseif (preg_match('~^(xuifile|xuiawgfile)://~i', $link, $fileMatch)) {
            if ($lazyDataUser === null && is_object($autoManager)) {
                $lazyDataUser = $autoManager->DataUser($panel['name_panel'], $username_service);
            }
            $singleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
            if (!is_array($singleFile) || trim((string) ($singleFile['value'] ?? '')) === '') {
                continue;
            }
            $content = (string) $singleFile['value'];
            $protocol = (string) ($singleFile['protocol'] ?? (strtolower($fileMatch[1]) === 'xuiawgfile' ? 'amneziawg' : 'wireguard'));
            $defaultName = normalizeConfigFilename($singleFile['filename'] ?? 'config.conf', '', true);
            $namingKey = function_exists('rxFileNamingScopedKey') && function_exists('rxFileNamingKeyFile')
                ? rxFileNamingScopedKey(rxFileNamingKeyFile($singleFile['filename'] ?? ''), $protocol)
                : '';
            $resolved = rxAutoFileResolveNaming($panel, 'xui_file', $namingKey, $defaultName, '');
            $publicName = $resolved['filename'];
            $caption = $resolved['caption'];
            $fileKey = rxAutoFileKey('config_content', $protocol . '|' . $content);
        } elseif (function_exists('xui_wg_conf_from_link')) {
            $wg = xui_wg_conf_from_link($link);
            if (is_array($wg) && isset($wg['conf']) && (string) $wg['conf'] !== '') {
                $content = (string) $wg['conf'];
                $protocol = (string) ($wg['protocol'] ?? 'wireguard');
                $defaultName = normalizeConfigFilename(xui_wg_conf_filename($link, $protocol, (int) $index), '', true);
                $descriptor = function_exists('rxFileNamingDescriptorFromLink') ? rxFileNamingDescriptorFromLink($link) : null;
                $namingKey = is_array($descriptor) ? (string) ($descriptor['key'] ?? '') : '';
                $ambiguous = $namingKey !== '' && (($keyCounts[$namingKey] ?? 0) > 1);
                $resolved = rxAutoFileResolveNaming($panel, 'xui_wg', $namingKey, $defaultName, '', $ambiguous);
                $publicName = $resolved['filename'];
                $caption = $resolved['caption'];
                $fileKey = rxAutoFileKey('config_content', $protocol . '|' . $content);
            }
        }
        if ($fileKey === '' && function_exists('rebeccaConfigDownloadInfo')) {
            $rebeccaInfo = rebeccaConfigDownloadInfo($link);
            if (is_array($rebeccaInfo)) {
                $descriptor = function_exists('rxFileNamingDescriptorFromLink') ? rxFileNamingDescriptorFromLink($link) : null;
                $namingKey = is_array($descriptor) ? (string) ($descriptor['key'] ?? '') : '';
                $ambiguous = $namingKey !== '' && (($keyCounts[$namingKey] ?? 0) > 1);
                $defaultName = normalizeConfigFilename($rebeccaInfo['filename'], '', ($rebeccaInfo['kind'] ?? '') === 'wireguard');
                $resolved = rxAutoFileResolveNaming($panel, 'rebecca', $namingKey, $defaultName, '', $ambiguous);
                $fileKey = rxAutoFileKey('rebecca', (string) ($rebeccaInfo['kind'] ?? '') . '|' . (string) ($rebeccaInfo['url'] ?? ''));
                if (!empty($deliveredKeys[$fileKey]) || (string) ($state[$fileKey] ?? '') === 'sent') {
                    $result['skipped']++;
                    continue;
                }
                $deliveredKeys[$fileKey] = true;
                $result['total']++;
                $tempExt = strtolower((string) pathinfo($resolved['filename'], PATHINFO_EXTENSION));
                $tempExt = preg_match('/^[a-z0-9]{1,10}$/', $tempExt) ? '.' . $tempExt : '.tmp';
                $temp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rxauto-' . bin2hex(random_bytes(8)) . $tempExt;
                $status = 'failed';
                try {
                    if (rebeccaDownloadConfigFile($rebeccaInfo['url'], $temp)) {
                        $status = rxAutoFileSendDocument($user_id, $invoice_id, $fileKey, $temp, $resolved['filename'], $resolved['caption']);
                    } else {
                        rxAutoFileDeliveryMark($invoice_id, $fileKey, 'failed', 'configuration download failed');
                    }
                } finally {
                    @unlink($temp);
                }
                $result[$status] = (int) $result[$status] + 1;
                continue;
            }
        }
        if ($fileKey === '') {
            continue;
        }
        if ($content !== '') {
            $legacyKeys = [rxAutoFileKey('xui_wg', $content)];
            $previousSingleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
            if (is_array($previousSingleFile) && (string) ($previousSingleFile['value'] ?? '') === $content) {
                $legacyKeys[] = rxAutoFileKey('xui_file', $protocol . '|' . (string) ($previousSingleFile['filename'] ?? '') . '|' . $content);
            }
            foreach ($legacyKeys as $legacyKey) {
                if (($state[$legacyKey] ?? '') === 'sent') {
                    $state[$fileKey] = 'sent';
                    break;
                }
            }
        }
        if (!empty($deliveredKeys[$fileKey]) || (string) ($state[$fileKey] ?? '') === 'sent') {
            $result['skipped']++;
            continue;
        }
        $deliveredKeys[$fileKey] = true;
        $result['total']++;
        $status = rxAutoFileSendContent($user_id, $invoice_id, $fileKey, $content, $publicName, $caption);
        $result[$status] = (int) $result[$status] + 1;
    }
    $result['ok'] = $result['total'] === 0 || ($result['sent'] === $result['total'] && $result['failed'] === 0 && $result['unknown'] === 0);
    return $result;
}
function rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id)
{
    try {
        return rxAutoDeliverPurchaseFiles($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id);
    } catch (Throwable $e) {
        error_log('rxAutoDeliverPurchaseFiles failed: ' . $e->getMessage());
        return ['ok' => false, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
    }
}
function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg', $auto_files = false)
{
    global $setting, $from_id;
    $config = normalizeServiceConfigs($config);
    if (!check_active_btn($setting['keyboardmain'], "text_help"))
        $reply_markup = null;
    $user_id = $user_id == null ? $from_id : $user_id;

    if (($panel_info['type'] ?? '') == "Manualsale") {
        $manual = rxManualsaleDelivery($username_service);
        if ($manual['content'] === "" && is_string($sub_link) && trim($sub_link) !== '') {
            $manual['configs'] = [$sub_link];
        }
        $items = isset($manual['items']) && is_array($manual['items']) ? $manual['items'] : [];
        $fileItems = [];
        $textItems = [];
        foreach ($items as $it) {
            if (!empty($it['is_file'])) {
                $fileItems[] = $it;
            } elseif (trim((string) ($it['content'] ?? '')) !== '') {
                $textItems[] = $it;
            }
        }
        $cleanCaption = rxStripDeliveryContentFromCaption($caption);
        $hasSubOffer = rxDeliveryAvailability($panel_info, ['__manual__' => $manual], $manual['sub'])['sub'];
        $hasConfigOffer = !empty($textItems);

        if (!empty($fileItems)) {
            $fileCount = count($fileItems);
            $index = 0;
            $rxManualKeyCounts = function_exists('rxFileNamingManualKeyCounts') ? rxFileNamingManualKeyCounts($fileItems) : [];
            foreach ($fileItems as $fi) {
                $index++;
                $ext = (trim((string) ($fi['ext'] ?? '')) !== "") ? $fi['ext'] : "bin";
                $publicName = rxManualsalePublicName($fi['name'] ?? '', $ext);
                $isLastFile = ($index === $fileCount);
                $thisCaption = ($index === 1) ? $cleanCaption : '';
                if (function_exists('rxFileNamingResolveManualItem')) {
                    $rxManualKey = rxFileNamingManualItemKey($fi);
                    $rxManualAmbiguous = ($rxManualKey !== '' && (($rxManualKeyCounts[$rxManualKey] ?? 0) > 1));
                    $rxManualResolved = rxFileNamingResolveManualItem($fi, $publicName, $thisCaption, $rxManualAmbiguous);
                    $publicName = $rxManualResolved['filename'];
                    $thisCaption = $rxManualResolved['caption'];
                }
                $manualFile = rxManualsaleTempPath($publicName);
                file_put_contents($manualFile, (string) ($fi['content'] ?? ''));
                $offerConfig = ($isLastFile && $hasConfigOffer);
                $offerSub = ($isLastFile && $hasSubOffer);
                $fileKeyboard = rxBuildDeliveryOffer($panel_info, $invoice_id, $offerConfig, $offerSub, $reply_markup);
                $fileReplyMarkup = ($fileKeyboard === null) ? ($isLastFile ? $reply_markup : null) : $fileKeyboard;
                if (is_file($manualFile) && is_readable($manualFile)) {
                    telegram('sendDocument', [
                        'chat_id' => $user_id,
                        'document' => new CURLFile($manualFile, 'application/octet-stream', $publicName),
                        'caption' => $thisCaption,
                        'reply_markup' => $fileReplyMarkup,
                        'parse_mode' => 'HTML',
                    ]);
                    @unlink($manualFile);
                } else {
                    sendmessage($user_id, $thisCaption, $fileReplyMarkup, 'HTML');
                }
            }
            return;
        }

        $availability = rxDeliveryAvailability($panel_info, ['__manual__' => $manual], $manual['sub']);
        $offerKeyboard = rxBuildDeliveryOffer($panel_info, $invoice_id, $availability['config'], $availability['sub'], $reply_markup);
        if ($offerKeyboard === null) {
            sendmessage($user_id, $cleanCaption, $reply_markup, 'HTML');
            return;
        }
        sendmessage($user_id, $cleanCaption, $offerKeyboard, 'HTML');
        return;
    }

    $availability = rxDeliveryAvailability($panel_info, $config, $sub_link);
    $cleanCaption = rxStripDeliveryContentFromCaption($caption);
    $cleanCaption = rxAppendSubscriptionLinkLine($cleanCaption, $availability['sub'] ? $sub_link : '', $panel_info);
    $offerKeyboard = rxBuildDeliveryOffer($panel_info, $invoice_id, $availability['config'], $availability['sub'], $reply_markup);
    $finalKeyboard = $offerKeyboard === null ? $reply_markup : $offerKeyboard;

    if (function_exists('nm_renderInfoCardForInvoice')) {
        $cardPath = nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id);
        if ($cardPath !== null) {
            $kb = json_decode((string) $finalKeyboard, true);
            if (!is_array($kb) || !isset($kb['inline_keyboard']) || !is_array($kb['inline_keyboard'])) {
                $kb = ['inline_keyboard' => []];
            }
            if (!function_exists('isQrDisabled') || !isQrDisabled()) {
                array_unshift($kb['inline_keyboard'], [['text' => faoxima_textbot_get('dyn_purchase_qr_code_btn', '📷 دریافت QR Code'), 'callback_data' => 'infocard_qr_' . $invoice_id]]);
            }
            $cardKeyboard = json_encode($kb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            try {
                telegram('sendphoto', [
                    'chat_id'      => $user_id,
                    'photo'        => new CURLFile($cardPath),
                    'caption'      => $cleanCaption,
                    'parse_mode'   => 'HTML',
                    'reply_markup' => $cardKeyboard,
                ]);
                @unlink($cardPath);
                if ($auto_files) {
                    rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id);
                }
                return;
            } catch (Throwable $e) {
                @unlink($cardPath);
                error_log('sendMessageService info card send failed: ' . $e->getMessage());
            }
        } elseif (function_exists('nm_sendServiceQrFallback') && is_string($sub_link) && trim($sub_link) !== '') {
            if (nm_sendServiceQrFallback($user_id, $sub_link, $image, $cleanCaption, $finalKeyboard)) {
                if ($auto_files) {
                    rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id);
                }
                return;
            }
        }
    }

    sendmessage($user_id, $cleanCaption, $finalKeyboard, 'HTML');
    if ($auto_files) {
        rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id);
    }
}
function isValidInvitationCode($setting, $fromId, $verfy_status)
{
    global $textbotlang, $datatextbot;

    if ($setting['verifybucodeuser'] == "onverify" && $verfy_status != 1) {
        sendmessage($fromId, $datatextbot['dyn_wallet_prompt_auth_success'] ?? "حساب کاربری شما با موفقیت احرازهویت گردید", null, 'html');
        update("user", "verify", "1", "id", $fromId);
        update("user", "cardpayment", "1", "id", $fromId);
    }
}
function createPayZarinpal($price, $order_id)
{
    global $domainhosts;
    $marchent_zarinpal = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/request.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        "merchant_id" => $marchent_zarinpal,
        "currency" => "IRT",
        "amount" => $price,
        "callback_url" => "https://$domainhosts/payment/zarinpal.php",
        "description" => $order_id,
        "metadata" => array(
            "order_id" => $order_id
        )
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
if (!function_exists('nm_replyOrEdit')) {

function nm_replyOrEdit($chatId, $text, $keyboard = null, $parseMode = 'HTML')
{
    global $message_id, $callback_query_id;
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    if ($isCallback && function_exists('Editmessagetext')) {

        $isInlineKbd = false;
        if (is_string($keyboard) && $keyboard !== '') {
            $decoded = json_decode($keyboard, true);
            if (is_array($decoded) && isset($decoded['inline_keyboard'])) $isInlineKbd = true;
        } elseif (is_array($keyboard) && isset($keyboard['inline_keyboard'])) {
            $isInlineKbd = true;
        } elseif ($keyboard === null) {
            $isInlineKbd = true;
        }
        if ($isInlineKbd) {
            try {
                $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);

                if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {
                    return true;
                }
                if (is_array($rx_edit_result) && isset($rx_edit_result['description'])) {
                    error_log('nm_replyOrEdit Editmessagetext not ok: ' . $rx_edit_result['description']);
                }
            } catch (Throwable $e) {
                error_log('nm_replyOrEdit Editmessagetext failed: ' . $e->getMessage());
            }
        }
    }
    sendmessage($chatId, $text, $keyboard, $parseMode);
    return false;
}}

if (!function_exists('rx_resolveAgentGroupFromReplyButton')) {
    function rx_resolveAgentGroupFromReplyButton($text, array $allowed)
    {
        $t = trim((string) $text);
        $map = rx_agentGroupButtonMap(true);

        if (isset($map[$t]) && in_array($map[$t], $allowed, true)) {
            return $map[$t];
        }

        $fallbackMap = [
            'f' => 'f',
            'n' => 'n',
            'n2' => 'n2',
            'all' => 'all',
            'allusers' => 'allusers',
            'کاربر عادی' => 'f',
            'عادی' => 'f',
            'نماینده عادی' => 'n',
            'نماینده پیشرفته' => 'n2',
            'نماینده با قابلیت های بیشتر' => 'n2',
            'همه' => 'all',
            'همه گروه‌ها' => 'all',
            'همه گروه ها' => 'all',
            'همه کاربران' => 'all',
        ];

        if (isset($fallbackMap[$t]) && in_array($fallbackMap[$t], $allowed, true)) {
            return $fallbackMap[$t];
        }

        return null;
    }
}

if (!function_exists('rx_product_allows_agent')) {
    function rx_product_allows_agent(array $product, $agent)
    {
        $agent = strtolower(trim((string) $agent));
        if (!in_array($agent, ['f', 'n', 'n2'], true)) {
            return false;
        }

        if (!array_key_exists('agent', $product) || $product['agent'] === null) {
            return false;
        }

        $raw = $product['agent'];
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return false;
            }
            $decoded = json_decode($raw, true);
            $values = is_array($decoded) ? $decoded : preg_split('/[,|]/', $raw);
        }

        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $value = strtolower(trim((string) $value));
            if ($value === $agent || in_array($value, ['all', 'allusers'], true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('rx_resolveAgentGroupFromCallbackData')) {
    function rx_resolveAgentGroupFromCallbackData($callbackData, array $allowed)
    {
        $parts = explode(':', trim((string) $callbackData), 2);

        if (count($parts) === 2 && $parts[0] === 'set_agent') {
            $token = trim($parts[1]);
            if (in_array($token, $allowed, true)) {
                return $token;
            }
        }

        return null;
    }
}

if (!function_exists('rx_resolveAgentGroup')) {
    function rx_resolveAgentGroup($raw, array $allowed)
    {
        $t = trim((string) $raw);
        $stripped = preg_replace('/^(?:[\s\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}])+/u', '', $t);
        $t = is_string($stripped) ? trim($stripped) : $t;
        $low = strtolower($t);

        $map = [
            'f' => 'f', 'کاربر عادی' => 'f', 'عادی' => 'f',
            'n' => 'n', 'نماینده عادی' => 'n',
            'n2' => 'n2', 'نماینده پیشرفته' => 'n2', 'نماینده با قابلیت های بیشتر' => 'n2',
        ];

        $token = null;
        if (isset($map[$low])) {
            $token = $map[$low];
        } elseif (isset($map[$t])) {
            $token = $map[$t];
        } else {
            $allWords = ['all', 'allusers', 'همه', 'همه گروه‌ها', 'همه گروه ها', 'همه کاربران'];
            if (in_array($low, $allWords, true) || in_array($t, $allWords, true)) {
                if (in_array('all', $allowed, true)) {
                    $token = 'all';
                } elseif (in_array('allusers', $allowed, true)) {
                    $token = 'allusers';
                }
            }
        }

        if ($token !== null && in_array($token, $allowed, true)) {
            return $token;
        }
        return null;
    }
}


if (!function_exists('rx_agentGroupButtonMap')) {
    function rx_agentGroupButtonMap($allowAll = false)
    {
        $map = [
            '👤 کاربر عادی' => 'f',
            '🤝 نماینده عادی' => 'n',
            '💎 نماینده پیشرفته' => 'n2',
        ];
        if ($allowAll) {
            $map['📊 همه گروه‌ها'] = 'all';
        }
        return $map;
    }
}


if (!function_exists('rx_agentGroupKeyboard')) {
    function rx_agentGroupKeyboard($allowAll = false)
    {
        global $textbotlang;
        $back = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

        $rows = [
            [['text' => '👤 کاربر عادی'], ['text' => '🤝 نماینده عادی']],
            [['text' => '💎 نماینده پیشرفته']],
        ];
        if ($allowAll) {
            $rows[1][] = ['text' => '📊 همه گروه‌ها'];
        }
        $rows[] = [['text' => $back]];

        return json_encode([
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_cashbackTargetOptionMap')) {
    function rx_cashbackTargetOptionMap()
    {
        return [
            '🆕 ۱ روز گذشته' => '1',
            '📅 ۳ روز گذشته' => '3',
            '📅 ۷ روز گذشته' => '7',
            '📅 ۳۰ روز گذشته' => '30',
            '⏳ زمان دلخواه' => 'custom',
            '🎯 فقط اولین خرید' => 'firstpurchase',
            '👥 کل کاربران' => 'all',
        ];
    }
}

if (!function_exists('rx_cashbackTargetKeyboard')) {
    function rx_cashbackTargetKeyboard()
    {
        global $textbotlang;
        $back = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

        return json_encode([
            'keyboard' => [
                [['text' => '🆕 ۱ روز گذشته'], ['text' => '📅 ۳ روز گذشته']],
                [['text' => '📅 ۷ روز گذشته'], ['text' => '📅 ۳۰ روز گذشته']],
                [['text' => '⏳ زمان دلخواه']],
                [['text' => '🎯 فقط اولین خرید']],
                [['text' => '👥 کل کاربران']],
                [['text' => $back]],
            ],
            'resize_keyboard' => true,
        ], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_normalizeCashbackButtonText')) {
    function rx_normalizeCashbackButtonText($text)
    {
        $t = trim((string) $text);
        $t = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $t);
        $t = is_string($t) ? trim($t) : '';
        $stripped = preg_replace('/^(?:[\s\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}])+/u', '', $t);
        $t = is_string($stripped) ? trim($stripped) : $t;
        $t = strtr($t, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        return $t;
    }
}

if (!function_exists('rx_resolveCashbackTargetFromReplyButton')) {
    function rx_resolveCashbackTargetFromReplyButton($text)
    {
        $map = rx_cashbackTargetOptionMap();
        $raw = trim((string) $text);
        if (isset($map[$raw])) {
            return $map[$raw];
        }

        $normalizedInput = rx_normalizeCashbackButtonText($raw);
        foreach ($map as $label => $value) {
            if (rx_normalizeCashbackButtonText($label) === $normalizedInput) {
                return $value;
            }
        }

        return null;
    }
}

if (!function_exists('rx_cashbackIsFirstPaidPurchase')) {
    function rx_cashbackIsFirstPaidPurchase($userId, $currentOrderId = null)
    {
        global $pdo;
        $userId = (string) $userId;
        if ($userId === '' || !($pdo instanceof PDO)) {
            return true;
        }
        $sql = "SELECT COUNT(*) FROM Payment_report WHERE id_user = :uid AND payment_Status = 'paid'";
        $params = [':uid' => $userId];
        if ($currentOrderId !== null && $currentOrderId !== '') {
            $sql .= " AND id_order <> :oid";
            $params[':oid'] = (string) $currentOrderId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) === 0;
    }
}

if (!function_exists('rx_cashbackTargetEligible')) {
    function rx_cashbackTargetEligible($target, $userRegisterTs, $userId = null, $currentOrderId = null)
    {
        $target = (string) $target;
        if ($target === '' || $target === 'all') {
            return true;
        }

        if ($target === 'firstpurchase') {
            if ($userId === null) {
                return true;
            }
            return rx_cashbackIsFirstPaidPurchase($userId, $currentOrderId);
        }

        $registerTs = (is_numeric($userRegisterTs)) ? (int) $userRegisterTs : null;
        if ($registerTs === null || $registerTs <= 0) {
            return true;
        }

        $days = null;
        if (strpos($target, 'custom:') === 0) {
            $days = (int) substr($target, 7);
        } elseif (ctype_digit($target)) {
            $days = (int) $target;
        }

        if ($days === null || $days <= 0) {
            return true;
        }

        $windowStart = time() - ($days * 86400);
        return $registerTs >= $windowStart;
    }
}

if (!function_exists('rx_cashbackScopeOptionMap')) {
    function rx_cashbackScopeOptionMap()
    {
        return [
            'purchase' => '🛒 خرید اشتراک',
            'extend'   => '🔄 تمدید سرویس',
            'volume'   => '📦 خرید حجم',
            'time'     => '⏱ خرید روز',
            'topup'    => '💰 شارژ کیف پول',
        ];
    }
}

if (!function_exists('rx_cashbackScopeDefault')) {
    function rx_cashbackScopeDefault()
    {
        return array_keys(rx_cashbackScopeOptionMap());
    }
}

if (!function_exists('rx_cashbackScopeFromInvoice')) {
    function rx_cashbackScopeFromInvoice($idInvoice)
    {
        $prefix = explode('|', (string) $idInvoice, 2)[0];
        $map = [
            'getconfigafterpay'  => 'purchase',
            'getextenduser'      => 'extend',
            'getextravolumeuser' => 'volume',
            'getextratimeuser'   => 'time',
        ];
        return $map[$prefix] ?? 'topup';
    }
}

if (!function_exists('rx_cashbackScopeKeyboardRows')) {
    function rx_cashbackScopeKeyboardRows($callbackPrefix, array $selectedScopes)
    {
        $rows = [];
        foreach (rx_cashbackScopeOptionMap() as $scopeKey => $label) {
            $isSelected = in_array($scopeKey, $selectedScopes, true);
            $checkIcon = $isSelected ? '✅' : '❌';
            $rows[] = [[
                'text' => "{$label} {$checkIcon}",
                'callback_data' => $callbackPrefix . 'toggle#' . $scopeKey,
            ]];
        }
        $allSelected = count(array_diff(rx_cashbackScopeDefault(), $selectedScopes)) === 0 && count($selectedScopes) === count(rx_cashbackScopeDefault());
        $rows[] = [[
            'text' => $allSelected ? '👥 همه موارد ✅' : '👥 همه موارد',
            'callback_data' => $callbackPrefix . 'all',
        ]];
        $rows[] = [[
            'text' => '✅ تایید و ذخیره',
            'callback_data' => $callbackPrefix . 'confirm',
        ]];
        return $rows;
    }
}

if (!function_exists('rx_cashbackScopeEligible')) {
    function rx_cashbackScopeEligible($scopeCsv, $idInvoice)
    {
        $scopeCsv = trim((string) $scopeCsv);
        if ($scopeCsv === '' || $scopeCsv === 'all') {
            return true;
        }
        $selected = array_filter(array_map('trim', explode(',', $scopeCsv)));
        if (empty($selected)) {
            return true;
        }
        $actionScope = rx_cashbackScopeFromInvoice($idInvoice);
        return in_array($actionScope, $selected, true);
    }
}

if (!function_exists('rx_cashbackEligibleForKey')) {
    function rx_cashbackEligibleForKey($cashbackKey, $userRegisterTs, $idInvoice = null, $userId = null, $currentOrderId = null)
    {
        if ($cashbackKey === '') {
            return true;
        }
        $row = select('PaySetting', 'ValuePay', 'NamePay', $cashbackKey . '_target', 'select');
        $target = is_array($row) ? (string) ($row['ValuePay'] ?? 'all') : 'all';
        if (!rx_cashbackTargetEligible($target, $userRegisterTs, $userId, $currentOrderId)) {
            return false;
        }
        if ($idInvoice === null) {
            return true;
        }
        $scopeRow = select('PaySetting', 'ValuePay', 'NamePay', $cashbackKey . '_scope', 'select');
        $scopeCsv = is_array($scopeRow) ? (string) ($scopeRow['ValuePay'] ?? 'all') : 'all';
        return rx_cashbackScopeEligible($scopeCsv, $idInvoice);
    }
}

if (!function_exists('rx_shopCashbackEligible')) {
    function rx_shopCashbackEligible($shopSettingKey, $userRegisterTs, $idInvoice = null, $userId = null, $currentOrderId = null)
    {
        if ($shopSettingKey === '') {
            return true;
        }
        $row = select('shopSetting', 'value', 'Namevalue', $shopSettingKey . '_target', 'select');
        $target = is_array($row) ? (string) ($row['value'] ?? 'all') : 'all';
        if (!rx_cashbackTargetEligible($target, $userRegisterTs, $userId, $currentOrderId)) {
            return false;
        }
        if ($idInvoice === null) {
            return true;
        }
        $scopeRow = select('shopSetting', 'value', 'Namevalue', $shopSettingKey . '_scope', 'select');
        $scopeCsv = is_array($scopeRow) ? (string) ($scopeRow['value'] ?? 'all') : 'all';
        return rx_cashbackScopeEligible($scopeCsv, $idInvoice);
    }
}

if (!function_exists('nm_adminInstantReply')) {

function nm_adminInstantReply($chatId, $text, $keyboard = null, $parseMode = 'HTML')
{
    global $message_id, $callback_query_id;

    $alreadyHandled = !empty($message_id)
                      && isset($GLOBALS['rx_admin_instant_deleted'])
                      && $GLOBALS['rx_admin_instant_deleted'] === $message_id;

    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $isInlineKbd = false;
    if (is_string($keyboard) && $keyboard !== '') {
        $decoded = json_decode($keyboard, true);
        if (is_array($decoded) && isset($decoded['inline_keyboard'])) $isInlineKbd = true;
    } elseif (is_array($keyboard) && isset($keyboard['inline_keyboard'])) {
        $isInlineKbd = true;
    }
    if (function_exists('rxNavTrackKeyboard') && (string) $chatId === (string) ($GLOBALS['from_id'] ?? '') && function_exists('rx_isAdminChat') && rx_isAdminChat($chatId)) {
        rxNavTrackKeyboard($chatId, $keyboard);
    }
    $rx_edit_failed = false;
    $rxCallbackAnswered = false;

    if ($isCallback && $isInlineKbd && !$alreadyHandled && function_exists('Editmessagetext')) {
        try {
            if (class_exists('FxTelegramCallbackScope', false)) {
                $rx_edit_result = FxTelegramCallbackScope::run((string) $callback_query_id,
                    static function () use ($chatId, $message_id, $text, $keyboard, $parseMode) {
                        return Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);
                    }, $rxCallbackAnswered);
            } else {
                $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);
            }

            if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {

                $GLOBALS['rx_admin_instant_deleted'] = $message_id;

                if (!$rxCallbackAnswered && !empty($callback_query_id) && function_exists('telegram')) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time' => 1,
                        ]);
                    } catch (Throwable $e) {}
                }
                return true;
            }
            $rx_edit_failed = true;
            if (is_array($rx_edit_result) && isset($rx_edit_result['description'])) {
                $rx_edit_desc = (string) $rx_edit_result['description'];
                $rx_edit_benign = ['message to edit not found','message to delete not found','message is not modified','query is too old','MESSAGE_ID_INVALID'];
                $rx_edit_skip_log = false;
                foreach ($rx_edit_benign as $rx_edit_n) {
                    if (stripos($rx_edit_desc, $rx_edit_n) !== false) { $rx_edit_skip_log = true; break; }
                }
                if (!$rx_edit_skip_log) {
                    error_log('nm_adminInstantReply Editmessagetext not ok: ' . $rx_edit_desc);
                }
            }
        } catch (Throwable $e) {
            $rx_edit_failed = true;
            error_log('nm_adminInstantReply Editmessagetext failed: ' . $e->getMessage());
        }
    }

    if ($rx_edit_failed) {
        if (!$rxCallbackAnswered && !empty($callback_query_id) && function_exists('telegram')) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 1,
                ]);
            } catch (Throwable $e) {}
        }
        return sendmessage($chatId, $text, $keyboard, $parseMode);
    }

    if ($isCallback) {
        if (!empty($message_id) && !$alreadyHandled && function_exists('deletemessage')) {
            try { @deletemessage($chatId, $message_id); } catch (Throwable $e) {}
            $GLOBALS['rx_admin_instant_deleted'] = $message_id;
        }
        if (!empty($callback_query_id) && function_exists('telegram')) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 1,
                ]);
            } catch (Throwable $e) {}
        }
        return sendmessage($chatId, $text, $keyboard, $parseMode);
    }

    return sendmessage($chatId, $text, $keyboard, $parseMode);
}}

if (!function_exists('rx_replyGroupIdCommand')) {

function rx_replyGroupIdCommand($update, $from_id, $Chat_type)
{
    global $textbotlang, $datatextbot;
    if ($Chat_type !== 'supergroup') return false;
    if (!isset($update['message']) || !is_array($update['message'])) return false;

    $rxText = (string) ($update['message']['text'] ?? '');
    $rxCmd = trim($rxText);
    $rxAtPos = strpos($rxCmd, '@');
    if ($rxAtPos !== false) $rxCmd = substr($rxCmd, 0, $rxAtPos);
    $rxCmd = mb_strtolower($rxCmd);

    $rxTriggers = ['آیدی', 'ایدی', '/getid', '/getgroupid'];
    if (!in_array($rxCmd, $rxTriggers, true)) return false;

    $chat = $update['message']['chat'] ?? [];
    $rxChatId = isset($chat['id']) ? (string) $chat['id'] : '';
    if ($rxChatId === '' || $rxChatId === '0') return true;
    $rxThreadId = $update['message']['message_thread_id'] ?? null;

    $rxIsBotAdmin = function_exists('rx_isAdminChat') && rx_isAdminChat($from_id);
    if (!$rxIsBotAdmin) {
        $rxSenderStatus = telegram('getChatMember', [
            'chat_id' => $rxChatId,
            'user_id' => $from_id,
        ]);
        $rxSenderRole = $rxSenderStatus['result']['status'] ?? '';
        if (!in_array($rxSenderRole, ['creator', 'administrator'], true)) {
            return true;
        }
    }

    $rxIsForum = !empty($chat['is_forum']);
    if (!$rxIsForum) {
        $rxPayload = [
            'chat_id' => $rxChatId,
            'text' => $datatextbot['dyn_group_setup_topic_not_active'] ?? "⚠️ تاپیک گروه فعال نیست\n\nلطفاً ابتدا از بخش تنظیمات گروه، گزینه تاپیک را فعال کنید؛ سپس مجدداً عبارت «آیدی» یا «ایدی» را داخل گروه ارسال نمایید.",
        ];
        if ($rxThreadId) $rxPayload['message_thread_id'] = $rxThreadId;
        telegram('sendmessage', $rxPayload);
        return true;
    }

    global $APIKEY;
    $rxBotUserId = (int) explode(':', (string) $APIKEY)[0];
    $rxBotMember = $rxBotUserId > 0 ? telegram('getChatMember', [
        'chat_id' => $rxChatId,
        'user_id' => $rxBotUserId,
    ]) : null;
    $rxBotRole = $rxBotMember['result']['status'] ?? '';
    if (!in_array($rxBotRole, ['creator', 'administrator'], true)) {
        $rxPayload = [
            'chat_id' => $rxChatId,
            'text' => $datatextbot['dyn_group_setup_insufficient_access'] ?? "⚠️ دسترسی ربات کافی نیست\n\nلطفاً ابتدا ربات را در این گروه ادمین کنید و دسترسی‌های موردنیاز را در اختیار آن قرار دهید؛ سپس دوباره عبارت «آیدی» یا «ایدی» را ارسال نمایید.",
        ];
        if ($rxThreadId) $rxPayload['message_thread_id'] = $rxThreadId;
        telegram('sendmessage', $rxPayload);
        return true;
    }

    $rxPayload = [
        'chat_id' => $rxChatId,
        'text' => sprintf($datatextbot['dyn_group_setup_chat_id_received'] ?? "✅ آیدی عددی گروه با موفقیت دریافت شد\n\nآیدی عددی این گروه:\n%s\n\nلطفاً این شناسه را کپی کرده و در بخش تنظیم گروه گزارشات ربات ارسال نمایید. 📋", $rxChatId),
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '📋 کپی آیدی گروه', 'copy_text' => ['text' => $rxChatId]]],
            ],
        ]),
    ];
    if ($rxThreadId) $rxPayload['message_thread_id'] = $rxThreadId;
    telegram('sendmessage', $rxPayload);
    return true;
}}
