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
function rxManualsaleSendFileItems($user_id, $username_service, $fileItems, $caption = '', $reply_markup = null, $totalForNaming = 0, $startIndex = 0, ?array &$deliveryResults = null)
{
    $unique = [];
    foreach ((array) $fileItems as $item) {
        if (is_array($item)) {
            $key = hash('sha256', serialize($item));
            $unique[$key] = $item;
        }
    }
    $attachments = [];
    $keyCounts = function_exists('rxFileNamingManualKeyCounts') ? rxFileNamingManualKeyCounts($unique) : [];
    foreach ($unique as $item) {
        $ext = trim((string) ($item['ext'] ?? '')) !== '' ? $item['ext'] : 'bin';
        $name = rxManualsalePublicName($item['name'] ?? '', $ext);
        $customCaption = '';
        if (function_exists('rxFileNamingResolveManualItem')) {
            $key = rxFileNamingManualItemKey($item);
            $resolved = rxFileNamingResolveManualItem($item, $name, '', $key !== '' && ($keyCounts[$key] ?? 0) > 1);
            $name = $resolved['filename'];
            $customCaption = $resolved['caption'];
        }
        $content = (string) ($item['content'] ?? '');
        $key = hash('sha256', serialize([$name, $customCaption, $content]));
        $attachments[$key] = ['name' => $name, 'caption' => $customCaption, 'content' => $content];
    }
    $fileCount = count($attachments);
    $index = 0;
    $sentAny = false;
    $files = [];
    $text = [];
    $keyboardState = 'skipped';
    foreach ($attachments as $item) {
        $index++;
        $name = $item['name'];
        $itemCaption = $index === 1 && trim((string) $caption) !== ''
            ? (string) $caption . ($item['caption'] !== '' ? "\n\n" . $item['caption'] : '')
            : $item['caption'];
        $path = rxManualsaleTempPath($name);
        $keyboard = $index === $fileCount ? $reply_markup : null;
        $required = trim($itemCaption) !== '';
        $result = null;
        $mediaAttempted = false;
        try {
            if (file_put_contents($path, (string) ($item['content'] ?? '')) !== false && is_readable($path)) {
                $document = new CURLFile($path, 'application/octet-stream', $name);
                $mediaAttempted = true;
                $result = rxSendMediaWithCaption('sendDocument', [
                    'chat_id' => $user_id,
                    'document' => $document,
                    'caption' => $itemCaption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $keyboard,
                ], null, $detail, true);
            } else {
                $fallback = $required ? rxSendRequiredMediaText(['chat_id' => $user_id, 'reply_markup' => $keyboard], $itemCaption) : ['state' => 'skipped'];
                $detail = [
                    'media_state' => 'failed',
                    'text_state' => $fallback['state'],
                    'text_chunks_confirmed' => $fallback['chunks_confirmed'] ?? 0,
                    'caption_source' => $required ? 'separate_text' : 'none',
                ];
            }
        } catch (Throwable $e) {
            $detail = ['media_state' => $mediaAttempted ? 'unknown' : 'failed', 'text_state' => $required ? 'unknown' : 'skipped'];
        } finally {
            @unlink($path);
        }
        $state = $detail['media_state'];
        $captionState = $detail['text_state'];
        if ($keyboard !== null) {
            $keyboardState = $state;
            if ($state === 'failed' && in_array($captionState, ['confirmed', 'unknown'], true)) {
                $keyboardState = $captionState;
            }
        }
        $itemText = $required ? [['required' => true, 'state' => $captionState]] : [];
        if ($captionState !== 'confirmed' && ($detail['text_chunks_confirmed'] ?? 0) > 0) {
            $itemText[] = ['required' => true, 'state' => 'confirmed'];
        }
        $sentAny = $sentAny || $state === 'confirmed';
        $files[] = [
            'required' => true,
            'state' => $state,
            'caption_state' => $captionState,
            'caption_required' => $required,
            'caption_valid' => $detail['caption_valid'] ?? null,
            'caption_complete' => !$required || $captionState === 'confirmed',
            'caption_source' => $detail['caption_source'] ?? 'none',
            'delivery_status' => rxDeliveryAggregate(array_merge([['required' => true, 'state' => $state]], $itemText)),
        ];
        array_push($text, ...$itemText);
        if (function_exists('rx_richDiag_log')) {
            rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'manual_file_item', $state, [
                'delivery_mode' => 'manualsale_file',
                'attachment_index' => $index,
                'attachment_total' => $fileCount,
                'caption_valid' => $detail['caption_valid'] ?? null,
                'caption_complete' => !$required || $captionState === 'confirmed',
                'caption_source' => $detail['caption_source'] ?? 'none',
                'caption_delivery_state' => $captionState,
                'result' => rx_richDiag_result($result),
            ], $state === 'confirmed' && (!$required || $captionState === 'confirmed') ? 'info' : 'warning');
        }
    }
    $filesStatus = $files ? rxDeliveryAggregate($files) : 'not_attempted';
    $deliveryResults = [
        'files' => $files,
        'text' => $text,
        'files_delivery_status' => $filesStatus,
        'files_state' => ['completed' => 'confirmed', 'unknown' => 'unknown', 'not_attempted' => 'not_attempted'][$filesStatus] ?? 'failed',
        'text_delivery_status' => $text ? rxDeliveryAggregate($text) : 'skipped',
        'keyboard_state' => $keyboardState,
        'delivery_status' => $files ? rxDeliveryAggregate(array_merge($files, $text)) : 'failed',
    ];
    if (function_exists('rx_richDiag_log')) {
        rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'manual_file_collection', $deliveryResults['delivery_status'], [
            'delivery_mode' => 'manualsale_files',
            'files_delivery_status' => $filesStatus,
            'file_states' => $files,
            'text_delivery_status' => $deliveryResults['text_delivery_status'],
        ], $deliveryResults['delivery_status'] === 'completed' ? 'info' : 'warning');
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
        $stmt = $pdo->prepare('SELECT file_key, status, detail FROM auto_file_delivery WHERE id_invoice = ?');
        if (!$stmt->execute([$invoice_id])) {
            throw new RuntimeException('Automatic file delivery state query failed');
        }
        $state = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $state[(string) ($row['file_key'] ?? '')] = [
                    'status' => (string) ($row['status'] ?? ''),
                    'detail' => (string) ($row['detail'] ?? ''),
                ];
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
        return $stmt->execute([$invoice_id, $file_key, $status, $detail, $now, $now]);
    } catch (Throwable $e) {
        error_log('rxAutoFileDeliveryMark failed: ' . get_class($e));
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
function rxAutoFileCaptionProgress(array $row, string $caption): array
{
    $detail = (string) ($row['detail'] ?? '');
    if (preg_match('/^caption_v1 (skipped|not_attempted|confirmed|failed|unknown) (\d+) ([a-f0-9]{64})$/D', $detail, $match)) {
        return hash_equals($match[3], hash('sha256', $caption))
            ? [$match[1], (int) $match[2]]
            : ['unknown', 0];
    }
    if (($row['status'] ?? '') === 'sent') {
        return [trim($caption) === '' && strpos($detail, 'caption=') === false ? 'skipped' : 'unknown', 0];
    }
    if (strpos($detail, 'caption=') !== false) {
        return ['unknown', 0];
    }
    return [trim($caption) === '' ? 'skipped' : 'not_attempted', 0];
}

function rxAutoFileCaptionMark($invoiceId, $fileKey, string $documentStatus, string $caption, string $state, int $cursor): bool
{
    return rxAutoFileDeliveryMark($invoiceId, $fileKey, $documentStatus, 'caption_v1 ' . $state . ' ' . $cursor . ' ' . hash('sha256', $caption));
}

function rxAutoFileFinish($userId, $invoiceId, $fileKey, string $documentStatus, string $caption, string $captionState, int $cursor, ?bool $captionValid, string $source, $result, ?array &$detail): string
{
    $textResult = null;
    if (in_array($documentStatus, ['sent', 'failed'], true) && trim($caption) !== ''
        && in_array($captionState, ['not_attempted', 'failed'], true)) {
        $checkpoint = static function (string $state, int $next) use ($invoiceId, $fileKey, $documentStatus, $caption): bool {
            return rxAutoFileCaptionMark($invoiceId, $fileKey, $documentStatus, $caption, $state, $next);
        };
        $text = rxSendRequiredMediaText(['chat_id' => $userId], $caption, null, $cursor, $checkpoint);
        $captionState = $text['state'];
        $cursor = $text['chunks_confirmed'];
        $textResult = $text['result'];
        $source = 'separate_text';
    }
    $documentState = $documentStatus === 'sent' ? 'confirmed' : $documentStatus;
    $captionRequired = trim($caption) !== '' || $captionState === 'unknown';
    $components = [
        'document' => ['required' => true, 'state' => $documentState],
        'caption' => ['required' => $captionRequired, 'state' => $captionState],
    ];
    if ($captionState !== 'confirmed' && $cursor > 0) {
        $components['caption_chunks'] = ['required' => true, 'state' => 'confirmed'];
    }
    $detail = rxDeliveryComponentContext($components);
    $status = $detail['delivery_status'];
    $detail += [
        'state' => $status === 'completed' ? 'sent' : ($status === 'unknown' ? 'unknown' : 'failed'),
        'caption_required' => $captionRequired,
        'caption_valid' => $captionValid,
        'caption_complete' => !$captionRequired || $captionState === 'confirmed',
        'caption_source' => $source,
        'caption_delivery_state' => $captionState,
        'caption_fallback_reason' => $source === 'separate_text' ? 'separate_caption_text' : null,
    ];
    if (function_exists('rx_richDiag_log')) {
        rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'auto_file_delivery', $status, $detail + [
            'delivery_mode' => 'automatic_document',
            'result' => rx_richDiag_result($result),
            'text_result' => rx_richDiag_result($textResult),
        ], $status === 'completed' ? 'info' : 'warning');
    }
    return $detail['state'];
}

function rxAutoFileSendDocument($user_id, $invoice_id, $file_key, $path, $public_name, $caption, ?array &$deliveryDetail = null, array $row = [])
{
    $caption = (string) $caption;
    [$captionState, $cursor] = rxAutoFileCaptionProgress($row, $caption);
    $documentStatus = (string) ($row['status'] ?? '');
    if (in_array($documentStatus, ['sent', 'unknown'], true)) {
        return rxAutoFileFinish($user_id, $invoice_id, $file_key, $documentStatus, $caption, $captionState, $cursor, null, 'stored_state', null, $deliveryDetail);
    }
    if (!is_file($path) || !is_readable($path)) {
        rxAutoFileCaptionMark($invoice_id, $file_key, 'failed', $caption, $captionState, $cursor);
        return rxAutoFileFinish($user_id, $invoice_id, $file_key, 'failed', $caption, $captionState, $cursor, null, 'none', null, $deliveryDetail);
    }
    $safeCaption = rxPrepareSafeMediaCaption($caption);
    $attach = $safeCaption !== null && $captionState === 'not_attempted' && $cursor === 0;
    $payload = [
        'chat_id' => $user_id,
        'document' => new CURLFile($path, 'application/octet-stream', $public_name),
        'caption' => $attach ? $safeCaption : '',
        'parse_mode' => 'HTML',
        '_rx_no_network_retry' => true,
    ];
    $result = null;
    $documentStatus = 'unknown';
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $pendingCaption = $attach ? 'unknown' : $captionState;
        if (!rxAutoFileCaptionMark($invoice_id, $file_key, 'unknown', $caption, $pendingCaption, $cursor)) {
            $captionState = $pendingCaption;
            break;
        }
        try {
            $result = function_exists('rx_richUi_plainCall')
                ? rx_richUi_plainCall('sendDocument', $payload, null)
                : telegram('sendDocument', $payload);
            $documentState = rxDeliveryResultState($result);
        } catch (Throwable $e) {
            $documentState = 'unknown';
        }
        $documentStatus = $documentState === 'confirmed' ? 'sent' : $documentState;
        if ($attach && $documentState !== 'failed') {
            $captionState = $documentState;
        }
        if (!rxAutoFileCaptionMark($invoice_id, $file_key, $documentStatus, $caption, $captionState, $cursor)) {
            if (function_exists('rx_richDiag_log')) {
                rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'auto_file_state', 'unknown', [
                    'document_state' => $documentState,
                    'caption_delivery_state' => $captionState,
                    'reason' => 'document_result_not_persisted',
                ], 'warning');
            }
            $deliveryDetail = rxDeliveryComponentContext([
                'document' => ['required' => true, 'state' => $documentState],
                'caption' => ['required' => trim($caption) !== '', 'state' => $captionState],
                'persistence' => ['required' => true, 'state' => 'unknown'],
            ]);
            return 'unknown';
        }
        if (!$attach || !rxMediaCaptionRejected($result)) {
            break;
        }
        $attach = false;
        $payload['caption'] = '';
        $documentStatus = 'unknown';
    }
    return rxAutoFileFinish($user_id, $invoice_id, $file_key, $documentStatus, $caption, $captionState, $cursor, $safeCaption !== null, $attach ? 'document_caption' : 'none', $result, $deliveryDetail);
}

function rxAutoFileSendContent($user_id, $invoice_id, $file_key, $content, $public_name, $caption, ?array &$deliveryDetail = null, array $row = [])
{
    if (in_array($row['status'] ?? '', ['sent', 'unknown'], true)) {
        return rxAutoFileSendDocument($user_id, $invoice_id, $file_key, '', $public_name, $caption, $deliveryDetail, $row);
    }
    $temp = rxAutoFileTempPath($public_name);
    try {
        $path = file_put_contents($temp, (string) $content) === false ? '' : $temp;
        return rxAutoFileSendDocument($user_id, $invoice_id, $file_key, $path, $public_name, $caption, $deliveryDetail, $row);
    } finally {
        @unlink($temp);
    }
}

function rxAutoFileRecord(array &$summary, string $status, array $detail = []): void
{
    $summary[$status]++;
    $summary['total']++;
    $summary['file_states'][] = [
        'document_state' => $detail['document_state'] ?? ($status === 'sent' ? 'confirmed' : $status),
        'caption_state' => $detail['caption_state'] ?? 'not_attempted',
        'caption_required' => $detail['caption_required'] ?? false,
        'caption_chunks_state' => $detail['caption_chunks_state'] ?? 'not_attempted',
        'delivery_status' => $detail['delivery_status'] ?? ($status === 'sent' ? 'completed' : $status),
    ];
    $summary['ok'] = $summary['failed'] === 0 && $summary['unknown'] === 0;
}

function rxAutoFileIncludeUnresolved(array $summary, array $state, array $handled): array
{
    foreach (array_diff_key($state, $handled) as $row) {
        $status = $row['status'] ?? '';
        $captionPending = preg_match('/^caption_v1 (?:not_attempted|failed|unknown) |(?:^| )caption=(?:failed|unknown) source=/', (string) ($row['detail'] ?? '')) === 1;
        if ($status !== 'unknown' && !$captionPending) {
            continue;
        }
        rxAutoFileRecord($summary, 'unknown', [
            'document_state' => $status === 'sent' ? 'confirmed' : $status,
            'caption_state' => $captionPending ? 'unknown' : 'not_attempted',
            'caption_required' => $captionPending,
            'delivery_status' => 'unknown',
        ]);
    }
    return $summary;
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
        if (!function_exists('jovadpanelAutoDeliverFiles')) {
            return rxAutoFileIncludeUnresolved($result, $state, []);
        }
        $fileStates = [];
        $handled = [];
        $deliver = static function ($fileKey, $path, $name, $caption) use ($state, $invoice_id, $user_id, &$fileStates, &$handled) {
            $row = $state[$fileKey] ?? [];
            if ($path === null && !in_array($row['status'] ?? '', ['sent', 'unknown'], true)) {
                return null;
            }
            $status = rxAutoFileSendDocument($user_id, $invoice_id, $fileKey, $path ?? '', $name, $caption, $detail, $row);
            $handled[$fileKey] = true;
            $fileStates[] = $detail;
            return $status;
        };
        $result = jovadpanelAutoDeliverFiles($user_id, $panel, $username_service, ['deliver_file' => $deliver]);
        $result['file_states'] = $fileStates;
        return rxAutoFileIncludeUnresolved($result, $state, $handled);
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
            return rxAutoFileIncludeUnresolved($result, $state, []);
        }
        $defaultName = normalizeConfigFilename("{$panel['inboundid']}-{$username_service}.conf", $panel['namecustom'] ?? '', true);
        $key = function_exists('rxFileNamingKeySubscription') ? rxFileNamingKeySubscription($panel['inboundid'] ?? '') : '';
        $resolved = rxAutoFileResolveNaming($panel, 'wgdashboard', $key, $defaultName, '');
        $fileKey = rxAutoFileKey('wgdashboard', $content);
        $status = rxAutoFileSendContent($user_id, $invoice_id, $fileKey, $content, $resolved['filename'], $resolved['caption'], $detail, $state[$fileKey] ?? []);
        rxAutoFileRecord($result, $status, $detail);
        return rxAutoFileIncludeUnresolved($result, $state, [$fileKey => true]);
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
        return rxAutoFileIncludeUnresolved($result, $state, []);
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
                if (isset($deliveredKeys[$fileKey])) {
                    $result['skipped']++;
                    continue;
                }
                $deliveredKeys[$fileKey] = true;
                $row = $state[$fileKey] ?? [];
                if (in_array($row['status'] ?? '', ['sent', 'unknown'], true)) {
                    $status = rxAutoFileSendDocument($user_id, $invoice_id, $fileKey, '', $resolved['filename'], $resolved['caption'], $detail, $row);
                    rxAutoFileRecord($result, $status, $detail);
                    continue;
                }
                $temp = rxAutoFileTempPath($resolved['filename']);
                try {
                    $downloaded = rebeccaDownloadConfigFile($rebeccaInfo['url'], $temp);
                    $status = rxAutoFileSendDocument($user_id, $invoice_id, $fileKey, $downloaded ? $temp : '', $resolved['filename'], $resolved['caption'], $detail, $row);
                    rxAutoFileRecord($result, $status, $detail);
                } finally {
                    @unlink($temp);
                }
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
                if (!in_array($state[$fileKey]['status'] ?? '', ['sent', 'unknown'], true)
                    && in_array($state[$legacyKey]['status'] ?? '', ['sent', 'unknown'], true)) {
                    $fileKey = $legacyKey;
                }
            }
        }
        if (isset($deliveredKeys[$fileKey])) {
            $result['skipped']++;
            continue;
        }
        $deliveredKeys[$fileKey] = true;
        $status = rxAutoFileSendContent($user_id, $invoice_id, $fileKey, $content, $publicName, $caption, $detail, $state[$fileKey] ?? []);
        rxAutoFileRecord($result, $status, $detail);
    }
    return rxAutoFileIncludeUnresolved($result, $state, $deliveredKeys);
}
function rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id)
{
    try {
        $summary = rxAutoDeliverPurchaseFiles($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id);
    } catch (Throwable $e) {
        error_log('rxAutoDeliverPurchaseFiles failed: ' . get_class($e));
        $summary = ['ok' => false, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
    }
    $confirmed = (int) $summary['sent'];
    foreach ($summary['file_states'] ?? [] as $file) {
        if (($file['delivery_status'] ?? '') === 'partial') {
            $confirmed++;
        }
    }
    $summary['delivery_status'] = $summary['ok'] ? 'completed'
        : ($summary['unknown'] > 0 || $summary['failed'] === 0 ? 'unknown' : ($confirmed > 0 ? 'partial' : 'failed'));
    if (function_exists('rx_richDiag_log')) {
        rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'automatic_file_collection', $summary['delivery_status'], [
            'delivery_mode' => 'automatic_file_collection',
            'files_delivery_status' => $summary['delivery_status'],
            'file_states' => $summary['file_states'] ?? [],
        ], $summary['ok'] ? 'info' : 'warning');
    }
    return $summary;
}
if (!function_exists('rxDeliveryResultState')) {
    function rxDeliveryResultState($response): string
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        } elseif (is_object($response)) {
            $vars = get_object_vars($response);
            if (is_array($vars)) {
                $response = $vars;
            }
        }
        if (is_bool($response)) {
            return $response ? 'confirmed' : 'unknown';
        }
        if (!is_array($response) || !array_key_exists('ok', $response) || !empty($response['_rx_invalid_response'])) {
            return 'unknown';
        }
        if ($response['ok'] === true) {
            return 'confirmed';
        }
        if ($response['ok'] !== false) {
            return 'unknown';
        }
        $code = $response['error_code'] ?? null;
        $description = strtolower((string) ($response['description'] ?? ''));
        if ($code === 'RICH_VALIDATION_FAILED') {
            return 'failed';
        }
        if ($code !== null && is_numeric($code)) {
            $numeric = (int) $code;
            if ($numeric === 429 || $numeric >= 500) {
                return 'unknown';
            }
            if ($numeric >= 400 && $numeric < 500) {
                return 'failed';
            }
        }
        foreach (['blocked', 'deactivated', 'chat not found', 'message to edit not found', 'message to delete not found', 'forbidden', 'bad request'] as $needle) {
            if ($description !== '' && strpos($description, $needle) !== false) {
                return 'failed';
            }
        }
        foreach (['timeout', 'timed out', 'curl', 'connection', 'network', 'اتصال'] as $needle) {
            if ($description !== '' && strpos($description, $needle) !== false) {
                return 'unknown';
            }
        }
        return 'unknown';
    }
}

if (!function_exists('rxDeliveryAggregate')) {
    function rxDeliveryAggregate(array $components): string
    {
        $required = [];
        foreach ($components as $component) {
            if (is_array($component) && !empty($component['required'])) {
                $required[] = $component;
            }
        }
        if (!$required) {
            return 'completed';
        }
        $states = [];
        foreach ($required as $component) {
            $states[] = (string) ($component['state'] ?? 'unknown');
        }
        $confirmed = 0;
        $uncertain = false;
        foreach ($states as $state) {
            if ($state === 'confirmed') {
                $confirmed++;
            } elseif ($state === 'unknown' || $state === 'attempted') {
                $uncertain = true;
            }
        }
        if ($confirmed === count($states)) {
            return 'completed';
        }
        if ($uncertain) {
            return 'unknown';
        }
        if ($confirmed > 0) {
            return 'partial';
        }
        return 'failed';
    }
}

if (!function_exists('rxDeliveryComponentContext')) {
    function rxDeliveryComponentContext(array $components): array
    {
        $required = [];
        $confirmed = [];
        $failed = [];
        $unknown = [];
        $flat = [];
        foreach (['text', 'qr', 'card', 'files'] as $name) {
            $component = isset($components[$name]) && is_array($components[$name]) ? $components[$name] : ['required' => false, 'state' => 'not_attempted'];
            $state = (string) ($component['state'] ?? 'not_attempted');
            $isRequired = !empty($component['required']);
            $flat[$name . '_state'] = $state;
            $flat[$name . '_required'] = $isRequired;
            if (!$isRequired) {
                continue;
            }
            $required[] = $name;
            if ($state === 'confirmed') {
                $confirmed[] = $name;
            } elseif (in_array($state, ['failed', 'not_attempted', 'skipped'], true)) {
                $failed[] = $name;
            } else {
                $unknown[] = $name;
            }
        }
        $flat['required_components'] = $required;
        $flat['confirmed_components'] = $confirmed;
        $flat['failed_components'] = $failed;
        $flat['unknown_components'] = $unknown;
        $flat['delivery_status'] = rxDeliveryAggregate($components);
        return $flat;
    }
}

if (!function_exists('rxPrepareSafeMediaCaption')) {
    function rxPrepareSafeMediaCaption(string $html): ?string
    {
        $caption = trim($html);
        if ($caption === '') {
            return '';
        }
        if (preg_match('~<table\b~i', $caption) === 1) {
            if (!function_exists('rx_richUi_replaceTablesWithText')) {
                return null;
            }
            $caption = (string) rx_richUi_replaceTablesWithText($caption);
            if (preg_match('~</?table\b~i', $caption) === 1) {
                return null;
            }
        }
        if (function_exists('rx_richUi_stripRtlMarker')) {
            $caption = rx_richUi_stripRtlMarker($caption);
        }
        $caption = preg_replace('~<br\s*/?>~i', "\n", $caption);
        $caption = preg_replace('~</p\s*>~i', "\n", $caption);
        $caption = preg_replace('~<p\b[^>]*>~i', '', $caption);
        $caption = trim((string) $caption);
        if (!mb_check_encoding($caption, 'UTF-8')) {
            return null;
        }
        $allowed = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'code', 'pre', 'mark', 'sub', 'sup', 'tg-spoiler', 'tg-emoji', 'a', 'tg-time', 'br', 'p', 'blockquote'];
        if (preg_match_all('~<\s*/?\s*([a-z][a-z0-9-]*)\b~i', $caption, $tags)) {
            foreach ($tags[1] as $tag) {
                if (!in_array(strtolower($tag), $allowed, true)) {
                    return null;
                }
            }
        }
        if (preg_match_all('~<tg-emoji\b([^>]*)>~i', $caption, $emojiTags)) {
            foreach ($emojiTags[1] as $attributes) {
                if (!preg_match('~emoji-id\s*=\s*"\d+"~i', $attributes)) {
                    return null;
                }
            }
        }
        $visible = html_entity_decode(strip_tags($caption), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        if (trim($visible) === '' || strlen(mb_convert_encoding($visible, 'UTF-16LE', 'UTF-8')) > 2048) {
            return null;
        }
        return $caption;
    }
}


function rxServiceDeliveryDiagnosticContext(array $components, string $status, array $extra, ?array $autoFilesSummary): array
{
    $deliveryStatus = rxDeliveryAggregate(array_values($components));
    $summary = rxDeliveryComponentContext($components);
    if ($deliveryStatus === 'completed' && empty($summary['required_components']) && empty($summary['confirmed_components'])) {
        $deliveryStatus = 'failed';
    }
    $summary['delivery_status'] = $deliveryStatus;
    $context = array_merge(['branch_status' => $status], $extra, $summary);
    $context += [
        'delivery_mode' => null,
        'files_delivery_status' => null,
        'file_states' => [],
        'caption_valid' => null,
        'caption_complete' => null,
        'caption_contains_required_content' => null,
        'caption_delivery_confirmed' => null,
        'caption_source' => null,
        'caption_validation_status' => null,
        'caption_fallback_reason' => null,
    ];
    if ($autoFilesSummary !== null) {
        $context['auto_files'] = $autoFilesSummary;
        if (empty($context['files_delivery_status']) && isset($autoFilesSummary['delivery_status'])) {
            $context['files_delivery_status'] = $autoFilesSummary['delivery_status'];
        }
        if (empty($context['file_states'])) {
            $context['file_states'] = $autoFilesSummary['file_states'] ?? [];
        }
    }
    return [$deliveryStatus, $context];
}

function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg', $auto_files = false)
{
    global $setting, $from_id;
    $config = normalizeServiceConfigs($config);
    if (!check_active_btn($setting['keyboardmain'], "text_help"))
        $reply_markup = null;
    $user_id = $user_id == null ? $from_id : $user_id;

    $rxDeliveryComponents = [
        'text' => ['required' => false, 'state' => 'not_attempted'],
        'qr' => ['required' => false, 'state' => 'not_attempted'],
        'card' => ['required' => false, 'state' => 'not_attempted'],
        'files' => ['required' => false, 'state' => 'not_attempted'],
    ];
    $rxSetDelivery = static function (string $name, string $state, bool $required = true) use (&$rxDeliveryComponents): void {
        if (!isset($rxDeliveryComponents[$name]) || !is_array($rxDeliveryComponents[$name])) {
            $rxDeliveryComponents[$name] = ['required' => $required, 'state' => $state];
            return;
        }
        $rxDeliveryComponents[$name]['required'] = $required;
        $rxDeliveryComponents[$name]['state'] = $state;
    };
    $rxSendServiceText = static function ($keyboard) use ($user_id, &$cleanCaption, $rxSetDelivery): array {
        $result = sendmessage($user_id, $cleanCaption, $keyboard, 'HTML');
        $state = rxDeliveryResultState($result);
        $chunks = (int) ($result['_rx_confirmed_text_chunks'] ?? 0);
        if ($chunks === 0 && (rxMediaCaptionRejected($result) || !empty($result['_rx_validation_failed']) || ($result['error_code'] ?? null) === 'RICH_VALIDATION_FAILED')) {
            $fallback = rxSendRequiredMediaText(['chat_id' => $user_id, 'reply_markup' => $keyboard], $cleanCaption);
            $result = $fallback['result'];
            $state = $fallback['state'];
            $chunks = $fallback['chunks_confirmed'];
        }
        if ($state !== 'confirmed' && $chunks > 0) {
            $rxSetDelivery('text_chunks', 'confirmed');
        }
        return [$result, $state, $chunks];
    };
    unset($GLOBALS['rx_rich_diag_last_result'], $GLOBALS['rx_rich_diag_validation_reason'], $GLOBALS['rx_rich_diag_fallback_reason']);

    $rxDiagOwn = false;
    if (function_exists('rx_richDiag_enabled') && rx_richDiag_enabled() && function_exists('rx_richDiag_active') && function_exists('rx_richDiag_begin') && !rx_richDiag_active()) {
        rx_richDiag_begin((string) ($GLOBALS['rx_rich_diag_service_key'] ?? 'service_message'), 're/rx/function/business_logic_1.php', 'service_send');
        $rxDiagOwn = true;
    }
    $rxAutoFilesSummary = null;
    $rxDiagDone = static function (string $status, array $extra = []) use (&$rxDiagOwn, &$rxDeliveryComponents, &$rxAutoFilesSummary) {
        [$deliveryStatus, $context] = rxServiceDeliveryDiagnosticContext($rxDeliveryComponents, $status, $extra, $rxAutoFilesSummary);
        if (!array_key_exists('fallback_reason', $context)) {
            $context['fallback_reason'] = $GLOBALS['rx_rich_diag_validation_reason'] ?? ($GLOBALS['rx_rich_diag_fallback_reason'] ?? null);
        }
        if (isset($GLOBALS['rx_rich_diag_last_result']) && is_array($GLOBALS['rx_rich_diag_last_result'])) {
            $lastDeliveryResult = $GLOBALS['rx_rich_diag_last_result'];
            $context['telegram_error_code'] = (int) ($lastDeliveryResult['error_code'] ?? 0);
            $context['telegram_error_description'] = (string) ($lastDeliveryResult['description'] ?? '');
        } else {
            $context['telegram_error_code'] = null;
            $context['telegram_error_description'] = null;
        }
        $level = $deliveryStatus === 'failed' ? 'error' : ($deliveryStatus === 'partial' ? 'warning' : 'info');
        if ($deliveryStatus === 'unknown') {
            $level = 'warning';
        }
        if (function_exists('rx_richDiag_log')) {
            rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'service_delivery', $deliveryStatus, $context, $level);
        }
        if ($rxDiagOwn && function_exists('rx_richDiag_finish')) {
            rx_richDiag_finish($deliveryStatus, ['_function' => 'sendMessageService', '_source_file' => 're/rx/function/business_logic_1.php'] + $context);
            $rxDiagOwn = false;
        }
    };
    $rxAutoFilesAttempt = static function () use ($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id, &$rxSetDelivery, &$rxAutoFilesSummary): void {
        $rxAutoFilesSummary = ['ok' => false, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0, 'delivery_status' => 'unknown', 'state' => 'unknown', 'counts' => []];
        if (!function_exists('rxAutoDeliverPurchaseFilesSafe')) {
            $rxAutoFilesSummary['delivery_status'] = 'skipped';
            $rxAutoFilesSummary['state'] = 'skipped';
            $rxSetDelivery('files', 'skipped', false);
            return;
        }
        $rxAutoFilesResult = rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id);
        $rxAutoFilesSummary = is_array($rxAutoFilesResult) ? $rxAutoFilesResult : [];
        $rxSent = (int) ($rxAutoFilesSummary['sent'] ?? 0);
        $rxFailed = (int) ($rxAutoFilesSummary['failed'] ?? 0);
        $rxUnknown = (int) ($rxAutoFilesSummary['unknown'] ?? 0);
        $rxSkipped = (int) ($rxAutoFilesSummary['skipped'] ?? 0);
        $rxTotal = (int) ($rxAutoFilesSummary['total'] ?? 0);
        $rxAutoFilesSummary['counts'] = [
            'sent' => $rxSent,
            'failed' => $rxFailed,
            'unknown' => $rxUnknown,
            'skipped' => $rxSkipped,
            'total' => $rxTotal,
        ];
        $rxFileStates = $rxAutoFilesSummary['file_states'] ?? [];
        foreach ($rxFileStates as $index => $file) {
            $rxSetDelivery('auto_document_' . $index, $file['document_state']);
            if (!empty($file['caption_required'])) {
                $rxSetDelivery('auto_caption_' . $index, $file['caption_state']);
            }
            if (($file['caption_chunks_state'] ?? '') === 'confirmed') {
                $rxSetDelivery('auto_caption_chunks_' . $index, 'confirmed');
            }
            if (($file['delivery_status'] ?? '') === 'unknown') {
                $rxSetDelivery('auto_confirmation_' . $index, 'unknown');
            }
        }
        $unreported = $rxTotal > count($rxFileStates) || (empty($rxAutoFilesSummary['ok']) && $rxFailed + $rxUnknown === 0);
        if ($unreported) {
            $rxSetDelivery('files', $rxUnknown > 0 || $rxFailed === 0 ? 'unknown' : 'failed');
        }
    };
    if (function_exists('rx_richDiag_log')) {
        rx_richDiag_log('RICH_TEMPLATE_SELECTED', 'service_template', 'received', [
            'template_key' => (string) ($GLOBALS['rx_rich_diag_service_key'] ?? 'service_message'),
            'panel_type' => (string) ($panel_info['type'] ?? ''),
            'caption' => rx_richDiag_text($caption),
            'config_count' => is_array($config) ? count($config) : 0,
            'sub_link_present' => is_string($sub_link) && trim($sub_link) !== '',
            'reply_markup_present' => !empty($reply_markup),
            'auto_files' => (bool) $auto_files,
            'chat_hash' => substr(hash('sha256', (string) $user_id), 0, 12),
        ], 'info');
    }

    if (($panel_info['type'] ?? '') == "Manualsale") {
        if (function_exists('rx_richDiag_log')) {
            rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                'delivery_mode' => 'manualsale',
                'panel_type' => 'Manualsale',
                'rich_bypass' => true,
                'caption' => rx_richDiag_text($caption),
            ], 'info');
        }
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
            if (function_exists('rx_richDiag_log')) {
                rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                    'delivery_mode' => 'manualsale_files',
                    'file_items' => count($fileItems),
                    'text_items' => count($textItems),
                    'caption' => rx_richDiag_text($cleanCaption),
                ], 'info');
            }
            $fileKeyboard = rxBuildDeliveryOffer($panel_info, $invoice_id, $hasConfigOffer, $hasSubOffer, $reply_markup) ?? $reply_markup;
            rxManualsaleSendFileItems($user_id, $username_service, $fileItems, $cleanCaption, $fileKeyboard, 0, 0, $fileDelivery);
            foreach ($fileDelivery['files'] as $index => $item) {
                $rxSetDelivery('file_' . $index, $item['state']);
            }
            foreach ($fileDelivery['text'] as $index => $item) {
                $rxSetDelivery('file_caption_' . $index, $item['state']);
            }
            if ($fileDelivery['keyboard_state'] === 'failed') {
                $keyboardResult = sendmessage($user_id, '⚠️ ارسال همه فایل‌های سرویس کامل نشد. برای پیگیری با پشتیبانی تماس بگیرید.', $fileKeyboard, 'HTML');
                $rxSetDelivery('file_keyboard_notice', rxDeliveryResultState($keyboardResult), false);
            }
            $rxDiagDone($fileDelivery['delivery_status'], [
                'delivery_mode' => 'manualsale_files',
                'files' => count($fileDelivery['files']),
                'file_states' => $fileDelivery['files'],
                'files_delivery_status' => $fileDelivery['files_delivery_status'],
                'caption_complete' => in_array($fileDelivery['text_delivery_status'], ['completed', 'skipped'], true),
            ]);
            return;
        }

        $availability = rxDeliveryAvailability($panel_info, ['__manual__' => $manual], $manual['sub']);
        $offerKeyboard = rxBuildDeliveryOffer($panel_info, $invoice_id, $availability['config'], $availability['sub'], $reply_markup);
        if ($offerKeyboard === null) {
            if (function_exists('rx_richDiag_log')) {
                rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                    'delivery_mode' => 'manual_text_plain',
                    'rich_bypass' => false,
                    'buttons_detached' => false,
                    'caption' => rx_richDiag_text($cleanCaption),
                ], 'info');
            }
            $rxManualPlainResult = sendmessage($user_id, $cleanCaption, $reply_markup, 'HTML');
            $GLOBALS['rx_rich_diag_last_result'] = $rxManualPlainResult;
            $rxSetDelivery('text', rxDeliveryResultState($rxManualPlainResult), true);
            $rxDiagDone('completed', ['delivery_mode' => 'manual_text_plain']);
            return;
        }
        if (function_exists('rx_richDiag_log')) {
            rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                'delivery_mode' => 'manual_text_offer',
                'rich_bypass' => false,
                'buttons_detached' => false,
                'caption' => rx_richDiag_text($cleanCaption),
            ], 'info');
        }
        $rxManualOfferResult = sendmessage($user_id, $cleanCaption, $offerKeyboard, 'HTML');
        $GLOBALS['rx_rich_diag_last_result'] = $rxManualOfferResult;
        $rxSetDelivery('text', rxDeliveryResultState($rxManualOfferResult), true);
        $rxDiagDone('completed', ['delivery_mode' => 'manual_text_offer']);
        return;
    }

    $availability = rxDeliveryAvailability($panel_info, $config, $sub_link);
    $cleanCaption = rxStripDeliveryContentFromCaption($caption);
    $cleanCaption = rxAppendSubscriptionLinkLine($cleanCaption, $availability['sub'] ? $sub_link : '', $panel_info);
    $offerKeyboard = rxBuildDeliveryOffer($panel_info, $invoice_id, $availability['config'], $availability['sub'], $reply_markup);
    $finalKeyboard = $offerKeyboard === null ? $reply_markup : $offerKeyboard;
    $rxServiceCaptionHasTable = is_string($cleanCaption) && preg_match('~<table\b~i', $cleanCaption) === 1;
    $rxSafeMediaCaption = function_exists('rxPrepareSafeMediaCaption') ? rxPrepareSafeMediaCaption($cleanCaption) : $cleanCaption;
    $rxServiceCaptionNeedsSplit = $rxSafeMediaCaption === null;
    $rxTextRequired = trim((string) $cleanCaption) !== '';
    $rxServiceCaptionDelivered = false;
    $rxQrKeyboardDelivered = false;
    if (!$rxTextRequired) {
        $rxSetDelivery('text', 'skipped', false);
    }
    if (function_exists('rx_richDiag_log')) {
        rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'evaluated', [
            'panel_type' => (string) ($panel_info['type'] ?? ''),
            'config_available' => !empty($availability['config']),
            'sub_available' => !empty($availability['sub']),
            'offer_keyboard' => $offerKeyboard !== null,
            'info_card_renderer' => function_exists('nm_renderInfoCardForInvoice'),
            'qr_fallback' => function_exists('nm_sendServiceQrFallback'),
            'caption' => rx_richDiag_text($cleanCaption),
            'caption_has_table' => $rxServiceCaptionHasTable,
            'caption_needs_split' => $rxServiceCaptionNeedsSplit,
            'caption_safe_length' => $rxSafeMediaCaption !== null ? mb_strlen($rxSafeMediaCaption, 'UTF-8') : null,
            'text_required' => $rxTextRequired,
            'rich_bypass' => false,
        ], 'info');
    }

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
            if (function_exists('rx_richDiag_log')) {
                rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                    'delivery_mode' => 'info_card_photo',
                    'api_method' => 'sendphoto',
                    'rich_bypass' => true,
                    'rich_bypass_reason' => 'photo_caption_delivery',
                    'caption' => rx_richDiag_text($cleanCaption),
                    'infocard_rendered' => true,
                ], 'info');
            }
            $rxCardPhotoResult = null;
            $rxCardTextResult = null;
            $rxCardTextState = 'not_attempted';
            $rxCardTextAttempted = false;
            try {
                if ($rxServiceCaptionNeedsSplit) {
                    if (function_exists('rx_richDiag_log')) {
                        rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                            'delivery_mode' => 'info_card_plus_rich_text',
                            'api_method' => 'sendphoto+sendmessage',
                            'rich_bypass' => false,
                            'rich_bypass_reason' => $rxServiceCaptionHasTable ? 'table_moved_out_of_media_caption' : 'caption_requires_separate_text',
                            'caption' => rx_richDiag_text($cleanCaption),
                            'infocard_rendered' => true,
                        ], 'info');
                    }
                    $rxCardPhotoResult = telegram('sendphoto', [
                        'chat_id' => $user_id,
                        'photo'   => new CURLFile($cardPath),
                    ]);
                    $rxCardPhotoState = rxDeliveryResultState($rxCardPhotoResult);
                    $rxSetDelivery('card', $rxCardPhotoState, true);
                    @unlink($cardPath);
                    $rxCardTextAttempted = true;
                    [$rxCardTextResult, $rxCardTextState, $rxCardTextChunks] = $rxSendServiceText($cardKeyboard);
                    $rxSetDelivery('text', $rxCardTextState, true);
                    $rxServiceCaptionDelivered = $rxCardTextState === 'confirmed';
                    $rxCardQrState = 'not_attempted';
                    if ($rxCardTextState === 'failed' && $rxCardTextChunks === 0 && function_exists('nm_sendServiceQrFallback') && is_string($sub_link) && trim($sub_link) !== '') {
                        $rxCardQrDetail = null;
                        $rxCardQrFallback = nm_sendServiceQrFallback($user_id, $sub_link, $image, '', $cardKeyboard, $rxCardQrDetail);
                        $rxCardQrState = is_array($rxCardQrDetail) ? (string) ($rxCardQrDetail['state'] ?? rxDeliveryResultState($rxCardQrFallback)) : rxDeliveryResultState($rxCardQrFallback);
                        $rxSetDelivery('qr', $rxCardQrState, $rxCardQrState !== 'skipped');
                    }
                    if (function_exists('rx_richDiag_log')) {
                        rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'service_delivery', rxDeliveryAggregate(array_values($rxDeliveryComponents)), [
                            'delivery_mode' => 'info_card_plus_rich_text',
                            'rich_bypass' => false,
                            'caption' => rx_richDiag_text($cleanCaption),
                            'photo_result' => rx_richDiag_result($rxCardPhotoResult),
                            'result' => rx_richDiag_result($rxCardTextResult),
                            'qr_fallback_state' => $rxCardQrState,
                            'caption_complete' => $rxServiceCaptionDelivered,
                            'caption_validation_status' => $rxServiceCaptionDelivered ? 'text_message_confirmed' : 'text_not_confirmed',
                        ], $rxCardTextState === 'confirmed' ? 'info' : 'warning');
                    }
                    $GLOBALS['rx_rich_diag_last_result'] = ($rxCardQrState !== 'not_attempted' && $rxCardQrState !== 'confirmed' && isset($rxCardQrDetail['result']) && is_array($rxCardQrDetail['result']))
                        ? $rxCardQrDetail['result']
                        : $rxCardTextResult;
                    if ($auto_files) {
                        $rxAutoFilesAttempt();
                    }
                    $rxDiagDone(rxDeliveryAggregate($rxDeliveryComponents), [
                        'delivery_mode' => 'info_card_plus_rich_text',
                        'caption_valid' => $rxSafeMediaCaption !== null,
                        'caption_complete' => $rxServiceCaptionDelivered,
                        'caption_contains_required_content' => $rxServiceCaptionDelivered,
                        'caption_delivery_confirmed' => $rxServiceCaptionDelivered,
                        'caption_source' => 'text_message',
                        'caption_validation_status' => $rxServiceCaptionDelivered ? 'text_message_confirmed' : 'text_not_confirmed',
                        'caption_fallback_reason' => $rxServiceCaptionDelivered ? null : 'caption_incomplete',
                    ]);
                    return;
                }
                $rxCardResult = rxSendMediaWithCaption('sendphoto', [
                    'chat_id'      => $user_id,
                    'photo'        => new CURLFile($cardPath),
                    'caption'      => $cleanCaption,
                    'parse_mode'   => 'HTML',
                    'reply_markup' => $cardKeyboard,
                ], null, $rxCardDelivery);
                $rxCardState = $rxCardDelivery['media_state'];
                $rxSetDelivery('card', $rxCardState, true);
                if ($rxTextRequired) {
                    $rxSetDelivery('text', $rxCardDelivery['text_state'], true);
                    if ($rxCardDelivery['text_state'] !== 'confirmed' && $rxCardDelivery['text_chunks_confirmed'] > 0) {
                        $rxSetDelivery('text_chunks', 'confirmed');
                    }
                } else {
                    $rxSetDelivery('text', 'skipped', false);
                }
                if (function_exists('rx_richDiag_log')) {
                    rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'service_delivery', $rxCardState, [
                        'delivery_mode' => 'info_card_photo',
                        'rich_bypass' => true,
                        'caption' => rx_richDiag_text($cleanCaption),
                        'result' => rx_richDiag_result($rxCardResult),
                    ], $rxCardState === 'confirmed' ? 'info' : ($rxCardState === 'unknown' ? 'warning' : 'error'));
                }
                $GLOBALS['rx_rich_diag_last_result'] = $rxCardResult;
                @unlink($cardPath);
                if ($rxCardState === 'confirmed' || ($rxCardDelivery['caption_source'] === 'separate_text'
                    && ($rxCardDelivery['text_state'] !== 'failed' || $rxCardDelivery['text_chunks_confirmed'] > 0))) {
                    $rxServiceCaptionDelivered = $rxCardDelivery['caption_complete'];
                    if ($auto_files) {
                        $rxAutoFilesAttempt();
                    }
                    $rxDiagDone($rxCardDelivery['delivery_status'], ['delivery_mode' => 'info_card_photo', 'caption_complete' => $rxCardDelivery['caption_complete']]);
                    return;
                }
                if ($rxCardState === 'unknown') {
                    if ($auto_files) {
                        $rxAutoFilesAttempt();
                    }
                    $rxDiagDone('unknown', ['delivery_mode' => 'info_card_photo', 'fallback_reason' => 'card_delivery_ambiguous']);
                    return;
                }
            } catch (Throwable $e) {
                $rxCatchCardState = isset($rxCardResult) ? rxDeliveryResultState($rxCardResult) : (isset($rxCardPhotoResult) ? rxDeliveryResultState($rxCardPhotoResult) : 'failed');
                $rxCatchTextState = isset($rxCardTextResult) ? rxDeliveryResultState($rxCardTextResult) : 'not_attempted';
                if ($rxCatchTextState === 'not_attempted') {
                    $rxCatchTextState = $rxCatchCardState === 'failed' ? 'failed' : 'unknown';
                }
                $rxSetDelivery('card', $rxCatchCardState, true);
                $rxSetDelivery('text', $rxCatchTextState, true);
                @unlink($cardPath);
                if ($rxCardTextAttempted && $rxCardTextResult === null) {
                    if ($auto_files) {
                        $rxAutoFilesAttempt();
                    }
                    $rxDiagDone('unknown', ['delivery_mode' => 'info_card_plus_rich_text', 'fallback_reason' => 'text_delivery_exception']);
                    return;
                }
                if (function_exists('rx_richDiag_log')) {
                    rx_richDiag_log('RICH_RENDER_FAILED', 'service_delivery', 'exception', [
                        'delivery_mode' => 'info_card_photo',
                        'exception' => get_class($e),
                    ], 'error');
                }
                error_log('sendMessageService info card send failed: ' . get_class($e));
            }
        } else {
            $rxSetDelivery('card', 'skipped', false);
        }
    } else {
        $rxSetDelivery('card', 'skipped', false);
    }
    if (function_exists('nm_sendServiceQrFallback') && is_string($sub_link) && trim($sub_link) !== '') {
            if ($rxServiceCaptionNeedsSplit) {
                if (function_exists('rx_richDiag_log')) {
                    rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                        'delivery_mode' => 'qr_plus_rich_text',
                        'api_method' => 'sendmessage+sendphoto',
                        'rich_bypass' => false,
                        'rich_bypass_reason' => $rxServiceCaptionHasTable ? 'table_moved_out_of_qr_caption' : 'caption_requires_separate_text',
                        'caption' => rx_richDiag_text($cleanCaption),
                    ], 'info');
                }
                try {
                    [$rxQrTextResult, $rxQrTextState, $rxQrTextChunks] = $rxSendServiceText($finalKeyboard);
                } catch (Throwable $e) {
                    $rxQrTextResult = null;
                    $rxQrTextState = 'unknown';
                    $rxQrTextChunks = 0;
                    error_log('sendMessageService QR text send failed: ' . get_class($e));
                }
                $rxSetDelivery('text', $rxQrTextState, true);
                $rxServiceCaptionDelivered = $rxQrTextState === 'confirmed';
                $rxQrKeyboard = $rxQrTextState === 'failed' && $rxQrTextChunks === 0 ? $finalKeyboard : null;
                $rxQrDetail = null;
                $rxQrDelivered = nm_sendServiceQrFallback($user_id, $sub_link, $image, '', $rxQrKeyboard, $rxQrDetail);
                $rxQrState = is_array($rxQrDetail) ? (string) ($rxQrDetail['state'] ?? rxDeliveryResultState($rxQrDelivered)) : rxDeliveryResultState($rxQrDelivered);
                $rxSetDelivery('qr', $rxQrState, $rxQrState !== 'skipped');
                if ($rxQrState !== 'confirmed' && isset($rxQrDetail['result']) && is_array($rxQrDetail['result'])) {
                    $GLOBALS['rx_rich_diag_last_result'] = $rxQrDetail['result'];
                }
                if (function_exists('rx_richDiag_log')) {
                    rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'service_delivery', rxDeliveryAggregate(array_values($rxDeliveryComponents)), [
                        'delivery_mode' => 'qr_plus_rich_text',
                        'rich_bypass' => false,
                        'caption' => rx_richDiag_text($cleanCaption),
                        'caption_valid' => $rxSafeMediaCaption !== null,
                        'caption_complete' => $rxServiceCaptionDelivered,
                        'caption_contains_required_content' => $rxServiceCaptionDelivered,
                        'caption_delivery_confirmed' => $rxServiceCaptionDelivered,
                        'caption_source' => 'text_message',
                        'caption_validation_status' => $rxServiceCaptionDelivered ? 'text_message_confirmed' : 'text_not_confirmed',
                        'caption_fallback_reason' => $rxServiceCaptionDelivered ? null : 'caption_incomplete',
                        'qr_caption_used_for_text' => false,
                        'text_result' => rx_richDiag_result($rxQrTextResult),
                        'qr_detail' => is_array($rxQrDetail) ? $rxQrDetail : null,
                    ], $rxQrTextState === 'confirmed' && $rxQrState === 'confirmed' ? 'info' : 'warning');
                }
                $GLOBALS['rx_rich_diag_last_result'] = ($rxQrState !== 'confirmed' && isset($rxQrDetail['result']) && is_array($rxQrDetail['result']))
                    ? $rxQrDetail['result']
                    : $rxQrTextResult;
                if ($auto_files) {
                    $rxAutoFilesAttempt();
                }
                $rxDiagDone(rxDeliveryAggregate($rxDeliveryComponents), [
                    'delivery_mode' => 'qr_plus_rich_text',
                    'caption_valid' => $rxSafeMediaCaption !== null,
                    'caption_complete' => $rxServiceCaptionDelivered,
                    'caption_contains_required_content' => $rxServiceCaptionDelivered,
                    'caption_delivery_confirmed' => $rxServiceCaptionDelivered,
                    'caption_source' => 'text_message',
                    'caption_validation_status' => $rxServiceCaptionDelivered ? 'text_message_confirmed' : 'text_not_confirmed',
                ]);
                return;
            }
            if (function_exists('rx_richDiag_log')) {
                rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                    'delivery_mode' => 'qr_fallback',
                    'rich_bypass' => true,
                    'rich_bypass_reason' => 'qr_image_delivery',
                    'caption' => rx_richDiag_text($cleanCaption),
                ], 'info');
            }
            $rxQrDetail = null;
            $rxQrCaption = $cleanCaption;
            $rxQrDelivered = nm_sendServiceQrFallback($user_id, $sub_link, $image, $rxQrCaption, $finalKeyboard, $rxQrDetail);
            $rxQrState = is_array($rxQrDetail) ? (string) ($rxQrDetail['state'] ?? rxDeliveryResultState($rxQrDelivered)) : rxDeliveryResultState($rxQrDelivered);
            $rxQrCaptionComplete = $rxTextRequired && ($rxQrDetail['caption_delivery_state'] ?? null) === 'confirmed';
            $rxQrCaptionSeparate = ($rxQrDetail['caption_source'] ?? '') === 'separate_text';
            if ($rxQrState === 'confirmed') {
                $rxQrKeyboardDelivered = true;
            }
            if ($rxQrState === 'skipped') {
                $rxSetDelivery('qr', 'skipped', false);
                if ($rxTextRequired) {
                    $rxSetDelivery('text', 'not_attempted', true);
                }
            } else {
                $rxSetDelivery('qr', $rxQrState, true);
                if (!$rxTextRequired) {
                    $rxSetDelivery('text', 'skipped', false);
                } elseif ($rxQrCaptionComplete) {
                    $rxSetDelivery('text', 'confirmed', true);
                    $rxServiceCaptionDelivered = true;
                } elseif ($rxQrCaptionSeparate) {
                    $rxSetDelivery('text', $rxQrDetail['caption_delivery_state'], true);
                    if ($rxQrDetail['text_chunks_confirmed'] > 0) {
                        $rxSetDelivery('text_chunks', 'confirmed');
                    }
                } elseif ($rxQrState === 'unknown') {
                    $rxSetDelivery('text', 'unknown', true);
                } else {
                    $rxSetDelivery('text', 'not_attempted', true);
                }
            }
            if ($rxQrState !== 'confirmed' && isset($rxQrDetail['result']) && is_array($rxQrDetail['result'])) {
                $GLOBALS['rx_rich_diag_last_result'] = $rxQrDetail['result'];
            }
            if (function_exists('rx_richDiag_log')) {
                rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'service_delivery', rxDeliveryAggregate(array_values($rxDeliveryComponents)), [
                    'delivery_mode' => 'qr_fallback',
                    'rich_bypass' => true,
                    'caption' => rx_richDiag_text($cleanCaption),
                    'caption_valid' => $rxSafeMediaCaption !== null,
                    'caption_complete' => $rxQrCaptionComplete,
                    'caption_contains_required_content' => $rxQrCaptionComplete,
                    'caption_delivery_confirmed' => $rxQrCaptionComplete,
                    'caption_source' => $rxQrCaptionSeparate ? 'separate_text' : ($rxQrCaptionComplete ? 'qr_caption' : ($rxTextRequired ? 'pending' : 'none')),
                    'caption_validation_status' => $rxQrCaptionComplete ? 'validated' : 'generic_or_unsafe',
                    'caption_fallback_reason' => $rxQrCaption === '' ? 'no_safe_caption' : (($rxQrDetail['reason'] ?? '') === 'caption_unsafe' ? 'caption_unsafe' : null),
                    'qr_detail' => is_array($rxQrDetail) ? $rxQrDetail : null,
                ], $rxQrState === 'confirmed' ? 'info' : 'warning');
            }
            if ($rxQrState === 'confirmed' || ($rxQrCaptionSeparate
                && ($rxQrDetail['caption_delivery_state'] !== 'failed' || $rxQrDetail['text_chunks_confirmed'] > 0))) {
                if (!$rxTextRequired) {
                    $rxSetDelivery('text', 'skipped', false);
                }
                $rxServiceCaptionDelivered = !$rxTextRequired || $rxQrCaptionComplete;
                if ($auto_files) {
                    $rxAutoFilesAttempt();
                }
                $rxDiagDone(rxDeliveryAggregate($rxDeliveryComponents), ['delivery_mode' => 'qr_fallback', 'caption_complete' => $rxServiceCaptionDelivered]);
                return;
            }
            if ($rxQrState === 'unknown') {
                if ($auto_files) {
                    $rxAutoFilesAttempt();
                }
                $rxDiagDone('unknown', ['delivery_mode' => 'qr_fallback', 'fallback_reason' => 'qr_delivery_ambiguous']);
                return;
            }
        } else {
            $rxSetDelivery('qr', 'skipped', false);
        }

    $rxFinalDeliveryMode = 'qr_only_no_text';
    if ($rxTextRequired && !$rxServiceCaptionDelivered) {
        if (function_exists('rx_richDiag_log')) {
            rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'service_branch', 'selected', [
                'delivery_mode' => 'plain_text',
                'api_method' => 'sendmessage',
                'rich_bypass' => false,
                'caption' => rx_richDiag_text($cleanCaption),
            ], 'info');
        }
        try {
            [$rxServiceTextResult, $rxServiceTextState] = $rxSendServiceText($rxQrKeyboardDelivered ? null : $finalKeyboard);
        } catch (Throwable $e) {
            $rxServiceTextResult = null;
            $rxServiceTextState = 'unknown';
            error_log('sendMessageService text send failed: ' . get_class($e));
        }
        $rxSetDelivery('text', $rxServiceTextState, true);
        $rxServiceCaptionDelivered = $rxServiceTextState === 'confirmed';
        $GLOBALS['rx_rich_diag_last_result'] = $rxServiceTextResult;
        $rxFinalDeliveryMode = 'plain_text';
    }
    if ($auto_files) {
        $rxAutoFilesAttempt();
    }
    $rxDiagDone(rxDeliveryAggregate($rxDeliveryComponents), [
        'delivery_mode' => $rxFinalDeliveryMode,
        'rich_bypass' => false,
        'caption_valid' => $rxSafeMediaCaption !== null,
        'caption_complete' => $rxServiceCaptionDelivered,
        'caption_contains_required_content' => $rxServiceCaptionDelivered || !$rxTextRequired,
        'caption_delivery_confirmed' => $rxServiceCaptionDelivered,
        'caption_source' => $rxTextRequired ? 'text_message' : 'none',
        'caption_validation_status' => $rxServiceCaptionDelivered ? 'text_confirmed' : 'text_not_confirmed',
    ]);
}
function isValidInvitationCode($setting, $fromId, $verfy_status)
{
    global $datatextbot;

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
                $rx_edit_description = is_array($rx_edit_result) ? strtolower((string) ($rx_edit_result['description'] ?? '')) : '';
                if ($rx_edit_description !== '' && strpos($rx_edit_description, 'message is not modified') !== false) {
                    return true;
                }
                if (function_exists('rxDeliveryResultState') && rxDeliveryResultState($rx_edit_result) === 'unknown') {
                    error_log('nm_replyOrEdit Editmessagetext outcome unknown; fallback suppressed to avoid duplicate delivery');
                    return false;
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
