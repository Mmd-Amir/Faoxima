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
                $_rx_acc_styles = $_rx_all_kbs['account'];
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
function rxManualsaleDelivery($username_service)
{
    $result = [
        'ext' => '',
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
    $result['content'] = $content;
    $result['sub_link'] = $subLink;
    $result['is_file'] = $isFile;
    $result['is_text'] = $isText;
    $result['is_link'] = $isLink;
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
            'ext' => $rExt,
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
    $cleanUser = preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace('_', '-', (string) $username_service));
    if ($cleanUser === '') {
        $cleanUser = 'config';
    }
    $fileCount = count($fileItems);
    $namingTotal = ($totalForNaming > 0) ? (int) $totalForNaming : $fileCount;
    $index = 0;
    $sentAny = false;
    foreach ($fileItems as $fi) {
        if (!is_array($fi)) {
            continue;
        }
        $index++;
        $namingIndex = (int) $startIndex + $index;
        $ext = (trim((string) ($fi['ext'] ?? '')) !== '') ? $fi['ext'] : 'bin';
        $baseName = $namingTotal > 1 ? ($cleanUser . '-' . $namingIndex) : $cleanUser;
        $publicName = normalizeConfigFilename($baseName . '.' . $ext, '', strtolower($ext) === 'conf');
        $manualFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $publicName;
        file_put_contents($manualFile, (string) ($fi['content'] ?? ''));
        $isLastFile = ($index === $fileCount);
        $thisCaption = ($index === 1) ? (string) $caption : '';
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
    if ($hasSub) {
        $offerRows[] = [['text' => "🔗 دریافت لینک اشتراک", 'callback_data' => "subscriptionurl_{$invoice_id}"]];
    }
    if (empty($offerRows)) {
        return null;
    }
    array_splice($mergedKb['inline_keyboard'], 0, 0, $offerRows);
    return json_encode($mergedKb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg')
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
            $cleanUser = preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace('_', '-', (string) $username_service));
            if ($cleanUser === '') $cleanUser = 'config';
            $fileCount = count($fileItems);
            $index = 0;
            foreach ($fileItems as $fi) {
                $index++;
                $ext = (trim((string) ($fi['ext'] ?? '')) !== "") ? $fi['ext'] : "bin";
                $baseName = $fileCount > 1 ? ($cleanUser . '-' . $index) : $cleanUser;
                $publicName = normalizeConfigFilename($baseName . "." . $ext, '', strtolower($ext) === 'conf');
                $manualFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $publicName;
                file_put_contents($manualFile, (string) ($fi['content'] ?? ''));
                $isLastFile = ($index === $fileCount);
                $thisCaption = ($index === 1) ? $cleanCaption : '';
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
                return;
            } catch (Throwable $e) {
                @unlink($cardPath);
                error_log('sendMessageService info card send failed: ' . $e->getMessage());
            }
        } elseif (function_exists('nm_sendServiceQrFallback') && is_string($sub_link) && trim($sub_link) !== '') {
            nm_sendServiceQrFallback($user_id, $sub_link, $image);
        }
    }

    sendmessage($user_id, $cleanCaption, $finalKeyboard, 'HTML');
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
function createPayCubepay($price, $order_id)
{
    if (!function_exists('cubepayCreatePayment')) {
        return ['success' => false, 'message' => 'تابع درگاه کیوب‌پی روی این سرور موجود نیست.'];
    }
    return cubepayCreatePayment($order_id, $price, $order_id);
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

    if ($isCallback && $isInlineKbd && !$alreadyHandled && function_exists('Editmessagetext')) {
        try {
            $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);

            if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {

                $GLOBALS['rx_admin_instant_deleted'] = $message_id;

                if (!empty($callback_query_id) && function_exists('telegram')) {
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
