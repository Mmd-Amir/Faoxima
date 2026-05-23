<?php

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
function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg')
{
    global $setting, $from_id;
    $config = normalizeServiceConfigs($config);
    if (!check_active_btn($setting['keyboardmain'], "text_help"))
        $reply_markup = null;
    $user_id = $user_id == null ? $from_id : $user_id;
    $STATUS_SEND_MESSAGE_PHOTO = $panel_info['config'] == "onconfig" && count($config) != 1 ? false : true;
    $out_put_qrcode = "";
    if ($panel_info['type'] == "Manualsale" || $panel_info['type'] == "ibsng" || $panel_info['type'] == "mikrotik") {
    }
    if ($panel_info['sublink'] == "onsublink" && $panel_info['config']) {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['sublink'] == "onsublink") {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['config'] == "onconfig") {
        $out_put_qrcode = $config[0];
    }
    if ($STATUS_SEND_MESSAGE_PHOTO) {


        $infoCardSent = false;
        if (function_exists('getInfoCardStatus') && getInfoCardStatus()) {
            $cardPath = nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id);
            if ($cardPath !== null) {


                $cardKeyboard = nm_appendInfoCardQrButton($reply_markup, $invoice_id);
                telegram('sendphoto', [
                    'chat_id' => $user_id,
                    'photo' => new CURLFile($cardPath),
                    'reply_markup' => $cardKeyboard,
                    'caption' => $caption,
                    'parse_mode' => "HTML",
                ]);
                @unlink($cardPath);
                $infoCardSent = true;
            }
        }
        if (!$infoCardSent) {

            $urlimage = "$user_id$invoice_id.png";
            $qrCode = createqrcode($out_put_qrcode);
            file_put_contents($urlimage, $qrCode->getString());
            if (!addBackgroundImage($urlimage, $qrCode, $image)) {
                error_log("Unable to apply background image for QR code using path '{$image}'");
            }
            telegram('sendphoto', [
                'chat_id' => $user_id,
                'photo' => new CURLFile($urlimage),
                'reply_markup' => $reply_markup,
                'caption' => $caption,
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
        }
        if ($panel_info['type'] == "WGDashboard") {
            $urlimage = "{$panel_info['inboundid']}_{$username_service}.conf";
            file_put_contents($urlimage, $sub_link);
            sendDocument($user_id, $urlimage, "⚙️ کانفیگ شما");
            unlink($urlimage);
        }
    } else {
        sendmessage($user_id, $caption, $reply_markup, 'HTML');
    }
    if ($panel_info['config'] == "onconfig" && $setting['status_keyboard_config'] == "1") {
        if (is_array($config)) {
            $validConfigs = array_values(array_filter($config, function ($item) {
                return is_string($item) && trim($item) !== '';
            }));

            if (!empty($validConfigs)) {
                $keyboardPayload = keyboard_config($validConfigs, $invoice_id, false);
                $configButtonCount = 0;
                $keyboardData = json_decode($keyboardPayload, true);

                if (is_array($keyboardData) && isset($keyboardData['inline_keyboard']) && is_array($keyboardData['inline_keyboard'])) {
                    foreach ($keyboardData['inline_keyboard'] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        foreach ($row as $button) {
                            if (!is_array($button)) {
                                continue;
                            }

                            $buttonText = $button['text'] ?? '';
                            $callbackData = $button['callback_data'] ?? '';

                            if ($buttonText === 'دریافت کانفیگ' && is_string($callbackData) && strpos($callbackData, 'configget_') === 0) {
                                ++$configButtonCount;
                            }
                        }
                    }
                } else {
                    error_log('Failed to decode keyboard payload for configuration prompt');
                }

                if ($configButtonCount > 1) {
                    sendmessage($user_id, "📌 جهت دریافت کانفیگ روی دکمه دریافت کانفیگ کلیک کنید", $keyboardPayload, 'HTML');
                }
            }
        }
    }
}
function isValidInvitationCode($setting, $fromId, $verfy_status)
{

    if ($setting['verifybucodeuser'] == "onverify" && $verfy_status != 1) {
        sendmessage($fromId, "حساب کاربری شما با موفقیت احرازهویت گردید", null, 'html');
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
        CURLOPT_TIMEOUT => 0,
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
function createPayZarinpey($price, $order_id, $userId)
{
    global $domainhosts;

    $token = getPaySettingValue('token_zarinpey');
    if (empty($token) || $token === '0') {
        return [
            'success' => false,
            'message' => 'توکن زرین پی تنظیم نشده است.',
        ];
    }

    $normalizedPrice = filter_var($price, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
        ],
    ]);

    if ($normalizedPrice === false) {
        return [
            'success' => false,
            'message' => 'مبلغ تراکنش نامعتبر است.',
        ];
    }

    $amountRial = $normalizedPrice * 10;

    $baseHost = trim($domainhosts ?? '');
    $scheme = 'https';
    if ($baseHost === '') {
        $httpsFlag = $_SERVER['HTTPS'] ?? '';
        if ($httpsFlag === '' || strtolower($httpsFlag) === 'off') {
            $scheme = 'http';
        }
    }

    $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($baseHost !== '') {
        $callbackBase = $scheme . '://' . ltrim($baseHost, '/');
    } elseif (!empty($host)) {
        $callbackBase = $scheme . '://' . $host;
    } else {
        return [
            'success' => false,
            'message' => 'امکان تعیین آدرس بازگشت وجود ندارد.',
        ];
    }

    $payload = [
        'amount' => $amountRial,
        'order_id' => $order_id,
        'callback_url' => rtrim($callbackBase, '/') . '/payment/ZarinPay/successful.php',
        'type' => 'card',
        'customer_user_id' => $userId,
        'description' => sprintf('پرداخت فاکتور %s', $order_id),
    ];

    $ch = curl_init('https://zarinpay.me/api/create-payment');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'message' => $error,
        ];
    }

    curl_close($ch);

    $result = json_decode($response, true);
    if (!is_array($result)) {
        return [
            'success' => false,
            'message' => 'پاسخ نامعتبر از زرین پی دریافت شد.',
        ];
    }

    if (empty($result['success'])) {
        return [
            'success' => false,
            'message' => $result['message'] ?? 'خطا در ایجاد پرداخت',
            'http_code' => $httpCode,
        ];
    }

    $data = $result['data'] ?? [];
    $authority = $result['authority'] ?? ($data['authority'] ?? null);
    $paymentLink = $result['payment_link']
        ?? ($result['payment_url'] ?? ($data['payment_link'] ?? ($data['payment_url'] ?? null)));

    if (empty($authority) || empty($paymentLink)) {
        return [
            'success' => false,
            'message' => 'پاسخ نامعتبر از زرین پی دریافت شد.',
        ];
    }

    return [
        'success' => true,
        'authority' => $authority,
        'payment_link' => $paymentLink,
        'amount_rial' => $amountRial,
        'raw_response' => $result,
    ];
}
function createPayaqayepardakht($price, $order_id)
{
    global $domainhosts;
    $merchant_aqayepardakht = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://panel.aqayepardakht.ir/api/v2/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'pin' => $merchant_aqayepardakht,
        'amount' => $price,
        'callback' => $domainhosts . "/payment/aqayepardakht.php",
        'invoice_id' => $order_id,
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
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
        foreach (['emergency_panel_status' => "VARCHAR(50) NOT NULL DEFAULT 'off_emergency_panel'", 'national_net_status' => "VARCHAR(50) NOT NULL DEFAULT 'off_national_net'", 'emergency_source_panel' => "VARCHAR(191) NULL", 'stock_source_panel' => "VARCHAR(191) NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
        foreach (['shelf_id' => "BIGINT UNSIGNED NULL", 'sub_link' => "MEDIUMTEXT NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE nm_config_stock ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
        foreach (['category_id' => "VARCHAR(64) NULL", 'category_name' => "VARCHAR(191) NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE nm_stock_product_map ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
        foreach (['source_panel_code' => "VARCHAR(191) NULL"] as $field => $definition) { try { $pdo->exec("ALTER TABLE invoice ADD COLUMN $field $definition"); } catch (Throwable $e) {} }
        $ready = true;
    } catch (Throwable $e) {
        error_log('nmStockEnsureSchema failed: ' . $e->getMessage());
    }
    return $ready;
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

if (!function_exists('nm_adminInstantReply')) {


function nm_adminInstantReply($chatId, $text, $keyboard = null, $parseMode = 'HTML')
{
    global $message_id, $callback_query_id;
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $isInlineKbd = false;
    if (is_string($keyboard) && $keyboard !== '') {
        $decoded = json_decode($keyboard, true);
        if (is_array($decoded) && isset($decoded['inline_keyboard'])) $isInlineKbd = true;
    } elseif (is_array($keyboard) && isset($keyboard['inline_keyboard'])) {
        $isInlineKbd = true;
    }
    $rx_edit_failed = false;

    if ($isCallback && $isInlineKbd && function_exists('Editmessagetext')) {
        try {
            $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);


            if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {

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
        if (!empty($message_id) && function_exists('deletemessage')) {
            try { @deletemessage($chatId, $message_id); } catch (Throwable $e) {}
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


    if (!empty($message_id) && function_exists('deletemessage')) {
        try { @deletemessage($chatId, $message_id); } catch (Throwable $e) {}
    }

    return sendmessage($chatId, $text, $keyboard, $parseMode);
}}

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
    if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard):\/\//i', $content)) return 'single';
    if (stripos($content, '[Interface]') !== false || stripos($content, 'PrivateKey') !== false) return 'wireguard';
    return 'text';
}

function nmStockDetectTier($content, $fallbackVolume = null)
{
    $content = (string)$content;
    if (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*(?:gb|g|گیگ|گیگابایت)(?:[^0-9]|$)/iu', $content, $m)) {
        return nmStockTierFromVolume((int)$m[1]);
    }
    if (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*[-_]\s*([1-9][0-9]{0,2})(?:[^0-9]|$)/u', $content, $m)) {
        return ((int)$m[1]) . '-' . ((int)$m[2]);
    }
    return nmStockTierFromVolume($fallbackVolume);
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

/**
 * Import subscription links WITHOUT any single config (Image request: "لینک خالی").
 * Each non-empty line must be an http(s) URL. The link is stored as both the
 * stock item's content and its sub_link, so delivery shows it as a subscription
 * and the QR encodes the link.
 */
function nmStockImportLinkOnly($panelCode, $productCode, $rawText, $fallbackVolume = null, $shelfId = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return ['ok' => 0, 'duplicate' => 0, 'bad' => 0];
    $panelCode = trim((string)$panelCode) !== '' ? trim((string)$panelCode) : 'auto';
    $productCode = trim((string)$productCode) !== '' ? trim((string)$productCode) : 'auto';
    $ok = $duplicate = $bad = 0;
    foreach (preg_split('/\r\n|\r|\n/', (string)$rawText) as $line) {
        $link = trim($line);
        if ($link === '' || mb_substr($link, 0, 1, 'UTF-8') === '#') continue;
        if (!preg_match('#^https?://\S+$#i', $link)) { $bad++; continue; }
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO nm_config_stock (shelf_id,codepanel,codeproduct,tier,format,content,sub_link,status,created_at) VALUES (:shelf_id,:codepanel,:codeproduct,:tier,:format,:content,:sub_link,'active',:created_at)");
            $stmt->execute([':shelf_id' => $shelfId, ':codepanel' => $panelCode, ':codeproduct' => $productCode, ':tier' => nmStockDetectTier($link, $fallbackVolume), ':format' => 'subscription', ':content' => $link, ':sub_link' => $link, ':created_at' => time()]);
            $stmt->rowCount() > 0 ? $ok++ : $duplicate++;
        } catch (Throwable $e) { $bad++; error_log('nmStockImportLinkOnly item failed: ' . $e->getMessage()); }
    }
    return ['ok' => $ok, 'duplicate' => $duplicate, 'bad' => $bad];
}

function nmStockImportBatch($panelCode, $productCode, $rawText, $fallbackVolume = null, $shelfId = null, $subLinks = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return ['ok' => 0, 'duplicate' => 0, 'bad' => 0];
    $panelCode = trim((string)$panelCode) !== '' ? trim((string)$panelCode) : 'auto';
    $productCode = trim((string)$productCode) !== '' ? trim((string)$productCode) : 'auto';
    $ok = $duplicate = $bad = 0;
    foreach (preg_split('/\r\n|\r|\n/', (string)$rawText) as $line) {
        $content = trim($line);
        if ($content === '' || mb_substr($content, 0, 1, 'UTF-8') === '#') continue;
        if (mb_strlen($content, 'UTF-8') < 8) { $bad++; continue; }
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO nm_config_stock (shelf_id,codepanel,codeproduct,tier,format,content,sub_link,status,created_at) VALUES (:shelf_id,:codepanel,:codeproduct,:tier,:format,:content,:sub_link,'active',:created_at)");
            $stmt->execute([':shelf_id' => $shelfId, ':codepanel' => $panelCode, ':codeproduct' => $productCode, ':tier' => nmStockDetectTier($content, $fallbackVolume), ':format' => nmStockDetectFormat($content), ':content' => $content, ':sub_link' => is_string($subLinks) ? $subLinks : null, ':created_at' => time()]);
            $stmt->rowCount() > 0 ? $ok++ : $duplicate++;
        } catch (Throwable $e) { $bad++; error_log('nmStockImportBatch item failed: ' . $e->getMessage()); }
    }
    return ['ok' => $ok, 'duplicate' => $duplicate, 'bad' => $bad];
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


        $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s INNER JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND sh.status='active' AND s.codepanel IN (" . implode(',', $panelWhereSql) . ") AND (s.codeproduct=:product_code_stock_where OR sh.codeproduct=:product_code_shelf_where OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :volume_where) < 0.001 AND sh.service_days=:days_where)) ORDER BY (s.codeproduct=:product_code_stock_order OR sh.codeproduct=:product_code_shelf_order) DESC, (sh.product_name=:product_name_order) DESC, (ABS(sh.volume_gb - :volume_order) < 0.001 AND sh.service_days=:days_order) DESC, FIELD(s.codepanel, " . implode(',', $panelOrderSql) . "), s.id ASC LIMIT 1");
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
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product=:name AND (Location=:loc OR Location='/all') LIMIT 1");
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
        $sql = "SELECT COUNT(*) FROM nm_config_stock s LEFT JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND (s.codepanel IN (" . implode(',', $panelParamsS) . ") OR sh.source_codepanel IN (" . implode(',', $panelParamsSrc) . ") OR sh.stock_codepanel IN (" . implode(',', $panelParamsSt) . ")) AND (s.codeproduct=:code_stock OR s.codeproduct='auto' OR sh.codeproduct=:code_shelf OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :vol) < 0.001 AND sh.service_days=:days)) AND (sh.id IS NULL OR sh.status='active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) { error_log('nmStockHasAvailableForProduct failed: ' . $e->getMessage()); return false; }
}