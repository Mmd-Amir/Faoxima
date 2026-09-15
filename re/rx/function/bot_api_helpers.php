<?php

/**
 * Resolves the "allowed users" label to show a customer, giving the symbolic
 * (admin-set, panel-agnostic) "Limiet" priority over the real 3x-ui/fail2ban
 * ip_limit. Works for any panel type. Returns null when neither applies.
 */
function faoxima_symbolic_limit_label(array $row): ?string
{
    if (($row['symbolic_limit_enabled'] ?? '0') == '1') {
        $count = (int)($row['symbolic_limit_users'] ?? 0);
        return $count > 0 ? "{$count} کاربر" : null;
    }
    return null;
}

function faoxima_cap_ip_summary(array $summary, int $ipLimit): array
{
    if ($ipLimit <= 0) {
        return $summary;
    }
    if (isset($summary['raw']) && is_array($summary['raw'])) {
        $summary['raw'] = array_slice(array_values($summary['raw']), 0, $ipLimit);
    }
    if (isset($summary['masked']) && is_array($summary['masked'])) {
        $summary['masked'] = array_slice(array_values($summary['masked']), 0, $ipLimit);
    }
    if (isset($summary['count'])) {
        $summary['count'] = min((int)$summary['count'], $ipLimit);
    }
    return $summary;
}

function plisio($order_id, $price)
{
    global $domainhosts;
    $rowPlisio = select("PaySetting", "ValuePay", "NamePay", "api_plisio", "select");
    $api_key = is_array($rowPlisio) ? trim((string)($rowPlisio['ValuePay'] ?? '')) : '';
    if ($api_key === '' || $api_key === '0') {
        $rowLegacy = select("PaySetting", "ValuePay", "NamePay", "apinowpayment", "select");
        $api_key = is_array($rowLegacy) ? trim((string)($rowLegacy['ValuePay'] ?? '')) : '';
    }

    $callbackUrl = '';
    $successUrl  = '';
    $failUrl     = '';
    if (isset($domainhosts) && is_string($domainhosts) && $domainhosts !== '') {
        $host = rtrim(preg_replace('#^https?://#', '', $domainhosts), '/');
        if ($host !== '') {
            $callbackUrl = 'https://' . $host . '/payment/plisio.php?json=true';
            $successUrl  = 'https://' . $host . '/payment/plisio_return.php?kind=success&order=' . urlencode($order_id);
            $failUrl     = 'https://' . $host . '/payment/plisio_return.php?kind=fail&order=' . urlencode($order_id);
        }
    }

    $url = 'https://api.plisio.net/api/v1/invoices/new';
    $url .= '?currency=TRX';
    $url .= '&amount=' . urlencode($price);
    $url .= '&order_number=' . urlencode($order_id);
    $url .= '&email=customer@plisio.net';
    $url .= '&order_name=plisio';
    $url .= '&language=fa';
    if ($callbackUrl !== '') {
        $url .= '&callback_url=' . urlencode($callbackUrl);
    }
    if ($successUrl !== '') {
        $url .= '&success_callback_url=' . urlencode($successUrl);
        $url .= '&success_invoice_url='  . urlencode($successUrl);
    }
    if ($failUrl !== '') {
        $url .= '&fail_callback_url=' . urlencode($failUrl);
        $url .= '&fail_invoice_url='  . urlencode($failUrl);
    }
    $url .= '&api_key=' . urlencode($api_key);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response['data'] ?? null;
}
function checkConnection($address, $port)
{
    $socket = @stream_socket_client("tcp://$address:$port", $errno, $errstr, 5);
    if ($socket) {
        fclose($socket);
        return true;
    } else {
        return false;
    }
}
function savedata($type, $namefiled, $valuefiled)
{
    global $from_id, $user;
    if ($type == "clear") {
        $datauser = [];
        $datauser[$namefiled] = $valuefiled;
        $data = json_encode($datauser, JSON_UNESCAPED_UNICODE);
        update("user", "Processing_value", $data, "id", $from_id);
        if (is_array($user)) {
            $user['Processing_value'] = $data;
        }
    } elseif ($type == "save") {
        $userdata = select("user", "*", "id", $from_id, "select");
        $raw = $userdata['Processing_value'] ?? null;

        $dataperevieos = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
        if (!is_array($dataperevieos)) {
            $dataperevieos = [];
        }
        $dataperevieos[$namefiled] = $valuefiled;
        $data = json_encode($dataperevieos, JSON_UNESCAPED_UNICODE);
        update("user", "Processing_value", $data, "id", $from_id);
        if (is_array($user)) {
            $user['Processing_value'] = $data;
        }
    }
}
function addFieldToTable($tableName, $fieldName, $defaultValue = null, $datatype = "VARCHAR(500)")
{
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName"
    );
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tableExists['count'] == 0)
        return;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$pdo->query("SELECT DATABASE()")->fetchColumn(), $tableName, $fieldName]);
    $filedExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($filedExists['count'] != 0)
        return;
    $query = "ALTER TABLE $tableName ADD $fieldName $datatype";
    $statement = $pdo->prepare($query);
    $statement->execute();
    if ($defaultValue != null) {
        $stmt = $pdo->prepare("UPDATE $tableName SET $fieldName= ?");
        $stmt->bindParam(1, $defaultValue);
        $stmt->execute();
    }
    echo "The $fieldName field was added ✅";
}
function rx_send_banner_message($chat_id, $section, $text, $keyboard = null, $parse_mode = 'html')
{
    global $setting;
    $statusCol = "banner_{$section}_status";
    $fileCol = "banner_{$section}_file_id";
    $status = is_array($setting) ? ($setting[$statusCol] ?? '0') : '0';
    $fileId = is_array($setting) ? trim((string)($setting[$fileCol] ?? '')) : '';
    if ($status == '1' && $fileId !== '') {
        return telegram('sendphoto', [
            'chat_id' => $chat_id,
            'photo' => $fileId,
            'caption' => $text,
            'reply_markup' => $keyboard,
            'parse_mode' => $parse_mode,
        ]);
    }
    return sendmessage($chat_id, $text, $keyboard, $parse_mode);
}
function rx_buy_banner_file_id()
{
    global $setting;
    $status = is_array($setting) ? ($setting['banner_buy_status'] ?? '0') : '0';
    $fileId = is_array($setting) ? trim((string)($setting['banner_buy_file_id'] ?? '')) : '';
    return ($status == '1' && $fileId !== '') ? $fileId : '';
}
function rx_send_buy_first_step($chat_id, $message_id, $datain, $text, $keyboard, $isCallback)
{
    $fileId = rx_buy_banner_file_id();
    if ($fileId === '') {
        if ($isCallback) {
            return Editmessagetext($chat_id, $message_id, $text, $keyboard);
        }
        return sendmessage($chat_id, $text, $keyboard, 'HTML');
    }
    if ($isCallback) {
        deletemessage($chat_id, $message_id);
    }
    return telegram('sendphoto', [
        'chat_id' => $chat_id,
        'photo' => $fileId,
        'caption' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => 'HTML',
    ]);
}
function panel_feature_registry()
{
    return [
        'extravolume' => ['shopSetting', 'statusextra', 'onextra'],
        'directbuy' => ['shopSetting', 'statusdirectpabuy', 'ondirectbuy'],
        'timeextra' => ['shopSetting', 'statustimeextra', 'ontimeextraa'],
        'disorder' => ['shopSetting', 'statusdisorder', 'ondisorder'],
        'changeservice' => ['shopSetting', 'statuschangeservice', 'onstatus'],
        'showprice' => ['shopSetting', 'statusshowprice', 'onshowprice'],
        'configbtn' => ['shopSetting', 'configshow', 'onconfig'],
        'refund' => ['shopSetting', 'backserviecstatus', 'on'],
        'categorygeneral' => ['setting', 'statuscategorygenral', 'oncategorys'],
        'categorytime' => ['setting', 'statuscategory', 'oncategory'],
    ];
}
function panel_feature_keys()
{
    return array_keys(panel_feature_registry());
}
function panel_features_all_off_json()
{
    $out = [];
    foreach (panel_feature_keys() as $featureKey) {
        $out[$featureKey] = "0";
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}
function panel_feature_global_value($table, $globalKey)
{
    if ($table === "setting") {
        $row = select("setting", "*");
        return is_array($row) && isset($row[$globalKey]) ? (string) $row[$globalKey] : '';
    }
    $row = select("shopSetting", "*", "Namevalue", $globalKey, "select");
    return is_array($row) && isset($row['value']) ? (string) $row['value'] : '';
}
function panel_features_snapshot_json()
{
    $out = [];
    foreach (panel_feature_registry() as $featureKey => $definition) {
        list($table, $globalKey, $onValue) = $definition;
        $out[$featureKey] = (panel_feature_global_value($table, $globalKey) === $onValue) ? "1" : "0";
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}
function panel_feature_resolve_row($panel)
{
    if (is_array($panel)) {
        return $panel;
    }
    $identifier = trim((string) $panel);
    if ($identifier === '') {
        return null;
    }
    $row = select("marzban_panel", "*", "name_panel", $identifier, "select");
    if (!is_array($row) || empty($row)) {
        $row = select("marzban_panel", "*", "code_panel", $identifier, "select");
    }
    return is_array($row) ? $row : null;
}
function panel_feature_enabled($panel, $key)
{
    $registry = panel_feature_registry();
    if (!isset($registry[$key])) {
        return false;
    }
    $row = panel_feature_resolve_row($panel);
    if (is_array($row) && isset($row['shop_features'])) {
        $features = json_decode((string) $row['shop_features'], true);
        if (is_array($features) && array_key_exists($key, $features)) {
            $result = ((string) $features[$key]) === "1";
            return $result;
        }
    }
    list($table, $globalKey, $onValue) = $registry[$key];
    $globalValue = panel_feature_global_value($table, $globalKey);
    return $globalValue === $onValue;
}
function panel_creation_limit_reached($panel)
{
    global $pdo;

    $row = panel_feature_resolve_row($panel);
    if (!is_array($row) || empty($row['name_panel'])) {
        return true;
    }

    $rawLimit = strtolower(trim((string) ($row['limit_panel'] ?? '')));
    if ($rawLimit === '' || in_array($rawLimit, ['unlimited', 'unlimted'], true)) {
        return false;
    }
    if (!preg_match('/^\d+$/', $rawLimit)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold') AND Service_location = :location");
    $stmt->execute([':location' => (string) $row['name_panel']]);
    return (int) $stmt->fetchColumn() >= (int) $rawLimit;
}
function outtypepanel($typepanel, $message)
{
    global $from_id, $optionMarzban, $optionGuard, $optionX_ui_single, $optionwg, $optioneylanpanel, $option_remnawave, $optionManualsale, $optionRebecca, $optionPasarGuard;
    if ($typepanel == "marzban") {
        sendmessage($from_id, $message, $optionMarzban, 'HTML');
    } elseif ($typepanel == "pasarguard") {
        sendmessage($from_id, $message, $optionPasarGuard, 'HTML');
    } elseif ($typepanel == "guard") {
        sendmessage($from_id, $message, $optionGuard, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        sendmessage($from_id, $message, $optionX_ui_single, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        sendmessage($from_id, $message, $optionwg, 'HTML');
    } elseif ($typepanel == "eylanpanel") {
        sendmessage($from_id, $message, $optioneylanpanel, 'HTML');
    } elseif ($typepanel == "remnawave") {
        sendmessage($from_id, $message, $option_remnawave, 'HTML');
    } elseif ($typepanel == "rebecca") {
        sendmessage($from_id, $message, $optionRebecca, 'HTML');
    } elseif ($typepanel == "Manualsale") {
        sendmessage($from_id, $message, $optionManualsale, 'HTML');
    }
    if (function_exists('step')) {
        step('PanelMenu', $from_id);
    }
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'PanelMenu');
    }
}
function addBackgroundImage($urlimage, $qrCodeResult, $backgroundPath)
{
    if (!is_object($qrCodeResult) || !method_exists($qrCodeResult, 'getString')) {
        error_log('Invalid QR code data provided to addBackgroundImage.');
        return false;
    }

    $projectRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);

    $basename = is_string($backgroundPath) && $backgroundPath !== ''
        ? basename($backgroundPath)
        : 'images.jpeg';
    $basenameNoExt = pathinfo($basename, PATHINFO_FILENAME) ?: 'images';

    $candidates = [
        $projectRoot . DIRECTORY_SEPARATOR . $basenameNoExt . '.jpeg',
        $projectRoot . DIRECTORY_SEPARATOR . $basenameNoExt . '.jpg',
        $projectRoot . DIRECTORY_SEPARATOR . 'images.jpeg',
        $projectRoot . DIRECTORY_SEPARATOR . 'images.jpg',
    ];

    if (is_string($backgroundPath) && $backgroundPath !== '') {
        $candidates[] = $backgroundPath;
        if ($backgroundPath[0] !== DIRECTORY_SEPARATOR && $backgroundPath[0] !== '/') {
            $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . ltrim($backgroundPath, '.\\/' . DIRECTORY_SEPARATOR);
        }
    }

    $resolvedPath = null;
    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $resolvedPath = $candidate;
            break;
        }
    }

    if ($resolvedPath === null) {
        return false;
    }

    $qrCodeImage = @imagecreatefromstring($qrCodeResult->getString());
    if ($qrCodeImage === false) {
        error_log('Unable to create QR code image resource.');
        return false;
    }

    $backgroundData = @file_get_contents($resolvedPath);
    if ($backgroundData === false) {
        imagedestroy($qrCodeImage);
        error_log("Unable to read background image: {$resolvedPath}");
        return false;
    }

    $backgroundImage = @imagecreatefromstring($backgroundData);
    if ($backgroundImage === false) {
        imagedestroy($qrCodeImage);
        error_log("Unable to create background image resource from file: {$resolvedPath}");
        return false;
    }

    $qrCodeWidth = imagesx($qrCodeImage);
    $qrCodeHeight = imagesy($qrCodeImage);
    $backgroundWidth = imagesx($backgroundImage);
    $backgroundHeight = imagesy($backgroundImage);

    $targetRatio  = 0.55;
    $shorterSide  = min($backgroundWidth, $backgroundHeight);
    $targetQrSize = (int) round($shorterSide * $targetRatio);
    if ($targetQrSize < 120) {
        $targetQrSize = min(120, $shorterSide);
    }

    if ($qrCodeWidth !== $targetQrSize || $qrCodeHeight !== $targetQrSize) {
        $resizedQr = imagecreatetruecolor($targetQrSize, $targetQrSize);
        if ($resizedQr !== false) {
            $white = imagecolorallocate($resizedQr, 255, 255, 255);
            imagefill($resizedQr, 0, 0, $white);
            imagecopyresampled(
                $resizedQr, $qrCodeImage,
                0, 0, 0, 0,
                $targetQrSize, $targetQrSize,
                $qrCodeWidth, $qrCodeHeight
            );
            imagedestroy($qrCodeImage);
            $qrCodeImage  = $resizedQr;
            $qrCodeWidth  = $targetQrSize;
            $qrCodeHeight = $targetQrSize;
        }
    }

    $padding    = (int) round($targetQrSize * 0.10);
    $boxSize    = $targetQrSize + ($padding * 2);
    $boxX       = (int) (($backgroundWidth  - $boxSize) / 2);
    $boxY       = (int) (($backgroundHeight - $boxSize) / 2);

    $boxClipX  = max(0, $boxX);
    $boxClipY  = max(0, $boxY);
    $boxClipW  = min($boxSize, $backgroundWidth  - $boxClipX);
    $boxClipH  = min($boxSize, $backgroundHeight - $boxClipY);

    $bgBackup = imagecreatetruecolor($boxClipW, $boxClipH);
    if ($bgBackup !== false) {
        imagecopy($bgBackup, $backgroundImage, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);
    }

    $glass = imagecreatetruecolor($boxClipW, $boxClipH);
    if ($glass !== false) {

        imagecopy($glass, $backgroundImage, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);

        for ($i = 0; $i < 22; $i++) {
            @imagefilter($glass, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $totalLum = 0;
        $samples  = 5;
        for ($sx = 0; $sx < $samples; $sx++) {
            for ($sy = 0; $sy < $samples; $sy++) {
                $px = imagecolorat(
                    $glass,
                    (int) ($boxClipW * ($sx + 0.5) / $samples),
                    (int) ($boxClipH * ($sy + 0.5) / $samples)
                );
                $r = ($px >> 16) & 0xFF;
                $g = ($px >>  8) & 0xFF;
                $b =  $px        & 0xFF;
                $totalLum += (0.299 * $r + 0.587 * $g + 0.114 * $b);
            }
        }
        $avgLum = $totalLum / ($samples * $samples);

        if     ($avgLum < 70)  { $opacity = 55; $bBoost = 16; $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
        elseif ($avgLum < 130) { $opacity = 45; $bBoost = 12; $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
        elseif ($avgLum < 190) { $opacity = 38; $bBoost = 8;  $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
        elseif ($avgLum < 230) { $opacity = 30; $bBoost = 4;  $tintR = 245; $tintG = 248; $tintB = 252; $needInnerLine = true;  }
        else                   { $opacity = 55; $bBoost = -2; $tintR = 220; $tintG = 230; $tintB = 245; $needInnerLine = true;  }

        if ($bBoost !== 0) {
            @imagefilter($glass, IMG_FILTER_BRIGHTNESS, $bBoost);
        }

        $overlay = imagecreatetruecolor($boxClipW, $boxClipH);
        $oTint   = imagecolorallocate($overlay, $tintR, $tintG, $tintB);
        imagefill($overlay, 0, 0, $oTint);
        imagecopymerge($glass, $overlay, 0, 0, 0, 0, $boxClipW, $boxClipH, $opacity);
        imagedestroy($overlay);

        if ($needInnerLine) {

            $inner = imagecolorallocatealpha($glass, 80, 100, 130, 95);
            if ($inner !== false) {
                imagerectangle($glass, 2, 2, $boxClipW - 3, $boxClipH - 3, $inner);
            }
        }

        $radius = (int) round(min($boxClipW, $boxClipH) * 0.10);
        if ($radius > 4 && $bgBackup !== false) {
            $r2 = $radius * $radius;
            $corners = [
                ['cx' => $radius - 1,         'cy' => $radius - 1,         'sx' => 0,                  'sy' => 0,                  'ex' => $radius,    'ey' => $radius],
                ['cx' => $boxClipW - $radius, 'cy' => $radius - 1,         'sx' => $boxClipW - $radius, 'sy' => 0,                  'ex' => $boxClipW,  'ey' => $radius],
                ['cx' => $radius - 1,         'cy' => $boxClipH - $radius, 'sx' => 0,                  'sy' => $boxClipH - $radius, 'ex' => $radius,    'ey' => $boxClipH],
                ['cx' => $boxClipW - $radius, 'cy' => $boxClipH - $radius, 'sx' => $boxClipW - $radius, 'sy' => $boxClipH - $radius, 'ex' => $boxClipW,  'ey' => $boxClipH],
            ];
            foreach ($corners as $c) {
                for ($x = $c['sx']; $x < $c['ex']; $x++) {
                    for ($y = $c['sy']; $y < $c['ey']; $y++) {
                        $dx = $x - $c['cx'];
                        $dy = $y - $c['cy'];
                        if ($dx * $dx + $dy * $dy > $r2) {
                            imagesetpixel($glass, $x, $y, imagecolorat($bgBackup, $x, $y));
                        }
                    }
                }
            }
        }

        imagecopy($backgroundImage, $glass, $boxClipX, $boxClipY, 0, 0, $boxClipW, $boxClipH);
        imagedestroy($glass);
    } else {

        $whiteBox = imagecolorallocate($backgroundImage, 255, 255, 255);
        imagefilledrectangle(
            $backgroundImage,
            $boxX, $boxY,
            $boxX + $boxSize, $boxY + $boxSize,
            $whiteBox
        );
    }
    if ($bgBackup !== false) { imagedestroy($bgBackup); }

    $x = (int) (($backgroundWidth  - $qrCodeWidth)  / 2);
    $y = (int) (($backgroundHeight - $qrCodeHeight) / 2);

    imagecopy($backgroundImage, $qrCodeImage, $x, $y, 0, 0, $qrCodeWidth, $qrCodeHeight);

    $result = imagepng($backgroundImage, $urlimage);

    imagedestroy($qrCodeImage);
    imagedestroy($backgroundImage);

    if ($result === false) {
        error_log("Failed to save QR code with background to {$urlimage}");
    }

    return $result !== false;
}
function getTelegramExpectedSecretToken()
{
    global $APIKEY, $ApiToken;
    require_once REFACTORED_LEGACY_ROOT . '/lib/WebhookAuth.php';
    $child = isset($ApiToken) && $ApiToken !== '';
    return FaoximaWebhookAuth::secret((string) ($child ? $ApiToken : ($APIKEY ?? '')), !$child);
}

function verifyTelegramWebhookSecretToken()
{
    $expected = getTelegramExpectedSecretToken();
    $provided = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    return $expected !== '' && is_string($provided) && $provided !== '' && hash_equals($expected, $provided);
}

function isTrustedReverseProxy($ip)
{
    if (!is_string($ip) || $ip === '') {
        return false;
    }

    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }

    $cloudflareRanges = [
        ['lower' => '173.245.48.0', 'upper' => '173.245.63.255'],
        ['lower' => '103.21.244.0', 'upper' => '103.21.247.255'],
        ['lower' => '103.22.200.0', 'upper' => '103.22.203.255'],
        ['lower' => '103.31.4.0',   'upper' => '103.31.7.255'],
        ['lower' => '141.101.64.0', 'upper' => '141.101.127.255'],
        ['lower' => '108.162.192.0','upper' => '108.162.255.255'],
        ['lower' => '190.93.240.0', 'upper' => '190.93.255.255'],
        ['lower' => '188.114.96.0', 'upper' => '188.114.111.255'],
        ['lower' => '197.234.240.0','upper' => '197.234.243.255'],
        ['lower' => '198.41.128.0', 'upper' => '198.41.255.255'],
        ['lower' => '162.158.0.0',  'upper' => '162.159.255.255'],
        ['lower' => '104.16.0.0',   'upper' => '104.31.255.255'],
        ['lower' => '172.64.0.0',   'upper' => '172.71.255.255'],
        ['lower' => '131.0.72.0',   'upper' => '131.0.75.255'],
        ['lower' => '2400:cb00::',  'upper' => '2400:cb00:ffff:ffff:ffff:ffff:ffff:ffff'],
        ['lower' => '2606:4700::',  'upper' => '2606:4700:ffff:ffff:ffff:ffff:ffff:ffff'],
        ['lower' => '2803:f800::',  'upper' => '2803:f800:ffff:ffff:ffff:ffff:ffff:ffff'],
        ['lower' => '2405:b500::',  'upper' => '2405:b500:ffff:ffff:ffff:ffff:ffff:ffff'],
        ['lower' => '2405:8100::',  'upper' => '2405:8100:ffff:ffff:ffff:ffff:ffff:ffff'],
        ['lower' => '2a06:98c0::',  'upper' => '2a06:98c7:ffff:ffff:ffff:ffff:ffff:ffff'],
        ['lower' => '2c0f:f248::',  'upper' => '2c0f:f24f:ffff:ffff:ffff:ffff:ffff:ffff'],
    ];

    foreach ($cloudflareRanges as $range) {
        if (isClientIpInRange($ip, $range['lower'], $range['upper'])) {
            return true;
        }
    }

    $extraProxies = getenv('TRUSTED_PROXIES') ?: ($_ENV['TRUSTED_PROXIES'] ?? '');
    if ($extraProxies !== '') {
        $allowedList = array_map('trim', explode(',', $extraProxies));
        if (in_array($ip, $allowedList, true)) {
            return true;
        }
    }

    return false;
}

function checktelegramip()
{
    return verifyTelegramWebhookSecretToken() === true;
}

function getClientIpConsideringProxies()
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
    if (is_string($remoteAddr)) {
        $remoteAddr = trim($remoteAddr);
    }

    if (empty($remoteAddr) || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return null;
    }

    if (!isTrustedReverseProxy($remoteAddr)) {
        return $remoteAddr;
    }

    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED',
    ];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header]) || !is_string($_SERVER[$header])) {
            continue;
        }

        $rawValue = trim($_SERVER[$header]);
        if ($rawValue === '') {
            continue;
        }

        $candidateIps = extractClientIpsFromHeader($rawValue, $header);
        foreach ($candidateIps as $candidate) {
            $candidate = normaliseProxyIpCandidate($candidate);
            if ($candidate === null || $candidate === '') {
                continue;
            }

            if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
                continue;
            }

            if (!isPublicIpAddress($candidate)) {
                continue;
            }

            return $candidate;
        }
    }

    return $remoteAddr;
}

function extractClientIpsFromHeader($value, $header)
{
    switch ($header) {
        case 'HTTP_X_FORWARDED_FOR':
            $parts = preg_split('/\s*,\s*/', $value);
            return $parts !== false ? $parts : [];
        case 'HTTP_FORWARDED':
            $matches = [];
            preg_match_all('/for=([^;,"]+|"[^"]+")/i', $value, $matches);
            $results = [];
            foreach ($matches[1] ?? [] as $match) {
                $results[] = $match;
            }
            return $results;
        default:
            return [$value];
    }
}

function normaliseProxyIpCandidate($candidate)
{
    if (!is_string($candidate)) {
        return null;
    }

    $candidate = trim($candidate);
    if ($candidate === '') {
        return null;
    }

    $candidate = trim($candidate, "\"' ");

    if (stripos($candidate, 'for=') === 0) {
        $candidate = substr($candidate, 4);
        $candidate = ltrim($candidate, '=');
    }

    $candidate = trim($candidate, "\"' ");

    if (strpos($candidate, '[') === 0) {
        $closingBracket = strpos($candidate, ']');
        if ($closingBracket !== false) {
            $candidate = substr($candidate, 1, $closingBracket - 1);
        }
    }

    $candidate = trim($candidate, '[]');

    if (strpos($candidate, ':') !== false && substr_count($candidate, ':') === 1 && strpos($candidate, '.') !== false) {
        [$possibleIp, $possiblePort] = explode(':', $candidate, 2);
        $possiblePort = trim($possiblePort);
        if ($possiblePort === '' || ctype_digit(str_replace([' ', "\t"], '', $possiblePort))) {
            $candidate = $possibleIp;
        }
    }

    if (strpos($candidate, '%') !== false) {
        $candidateWithoutZone = preg_replace('/%.*$/', '', $candidate);
        if (is_string($candidateWithoutZone)) {
            $candidate = $candidateWithoutZone;
        }
    }

    $candidate = trim($candidate);

    return $candidate === '' ? null : $candidate;
}

function isPublicIpAddress($ipAddress)
{
    return filter_var(
        $ipAddress,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function isClientIpInRange($clientIp, $lowerBound, $upperBound)
{
    $clientPacked = inet_pton($clientIp);
    $lowerPacked = inet_pton($lowerBound);
    $upperPacked = inet_pton($upperBound);

    if ($clientPacked === false || $lowerPacked === false || $upperPacked === false) {
        return false;
    }

    $length = strlen($clientPacked);
    if ($length !== strlen($lowerPacked) || $length !== strlen($upperPacked)) {
        return false;
    }

    return strcmp($clientPacked, $lowerPacked) >= 0 && strcmp($clientPacked, $upperPacked) <= 0;
}
function defaultCronStatusMap()
{
    return [
        'day' => true,
        'volume' => true,
        'remove' => false,
        'remove_volume' => false,
        'test' => false,
        'on_hold' => false,
        'uptime_node' => false,
        'uptime_panel' => false,
    ];
}

function normalizeCronStatus($rawCronStatus = null, $persist = false)
{
    $defaults = defaultCronStatusMap();
    if (is_string($rawCronStatus)) {
        $decoded = json_decode($rawCronStatus, true);
    } elseif (is_array($rawCronStatus)) {
        $decoded = $rawCronStatus;
    } else {
        $decoded = [];
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }
    $normalized = array_merge($defaults, array_intersect_key($decoded, $defaults));
    foreach ($defaults as $key => $defaultValue) {
        $value = $normalized[$key];
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            $normalized[$key] = in_array($lower, ['1', 'true', 'on', 'yes'], true);
        } else {
            $normalized[$key] = (bool) $value;
        }
    }
    if ($persist && function_exists('update')) {
        update('setting', 'cron_status', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }
    return $normalized;
}

function getCronTargetUser()
{
    if (function_exists('rx_redis_docker_env') && rx_redis_docker_env()) {
        return 'www-data';
    }

    $currentUser = function_exists('get_current_user') ? trim((string) get_current_user()) : '';
    if ($currentUser !== '') {
        return $currentUser;
    }

    return 'www-data';
}

function rxCrontabTargetsCurrentProcessUser($cronUser)
{
    $processUser = function_exists('get_current_user') ? trim((string) get_current_user()) : '';
    if ($processUser === '' && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $pw = @posix_getpwuid(posix_geteuid());
        $processUser = is_array($pw) ? trim((string) ($pw['name'] ?? '')) : '';
    }
    return $processUser !== '' && $processUser === $cronUser;
}

function rxCrontabCommand($crontabBinary, $cronUser, $extraArgs = '')
{
    if (rxCrontabTargetsCurrentProcessUser($cronUser)) {
        return trim(sprintf('%s %s', escapeshellarg($crontabBinary), $extraArgs));
    }
    return trim(sprintf('%s -u %s %s', escapeshellarg($crontabBinary), escapeshellarg($cronUser), $extraArgs));
}

function addCronIfNotExists($cronCommand)
{
    $commands = is_array($cronCommand) ? $cronCommand : [$cronCommand];
    $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
        return $command !== '';
    }));

    if (empty($commands)) {
        return true;
    }

    $logContext = implode('; ', $commands);

    if (!isShellExecAvailable()) {
        return true;
    }

    $crontabBinary = getCrontabBinary();
    if ($crontabBinary === null) {
        return true;
    }

    $cronUser = getCronTargetUser();

    $existingCronJobs = runShellCommand(rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
    $existingCronJobs = trim((string) $existingCronJobs);
    $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);
    $cronLines = array_values(array_filter(array_map('trim', $cronLines), static function ($line) {
        return $line !== '' && strpos($line, '#') !== 0;
    }));

    $newLineAdded = false;
    foreach ($commands as $command) {
        if (!in_array($command, $cronLines, true)) {
            $cronLines[] = $command;
            $newLineAdded = true;
        }
    }

    if (!$newLineAdded) {
        return true;
    }

    $cronLines = array_values(array_unique($cronLines));
    $cronContent = implode(PHP_EOL, $cronLines) . PHP_EOL;

    $temporaryFile = tempnam(sys_get_temp_dir(), 'cron');
    if ($temporaryFile === false) {
        error_log('Unable to create temporary file for cron job registration.');
        return false;
    }

    if (file_put_contents($temporaryFile, $cronContent) === false) {
        error_log('Unable to write cron configuration to temporary file: ' . $temporaryFile);
        unlink($temporaryFile);
        return false;
    }

    runShellCommand(rxCrontabCommand($crontabBinary, $cronUser, escapeshellarg($temporaryFile)));
    unlink($temporaryFile);

    return true;
}

function replaceCronJobsMatchingStatus($pattern, $newCommands)
{
    $commands = is_array($newCommands) ? $newCommands : [$newCommands];
    $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
        return $command !== '';
    }));

    if (!isShellExecAvailable()) {
        return ['status' => 'disabled', 'user' => null];
    }

    $crontabBinary = getCrontabBinary();
    if ($crontabBinary === null) {
        return ['status' => 'no_binary', 'user' => null];
    }

    $cronUser = getCronTargetUser();

    $existingCronJobs = runShellCommand(rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
    $existingCronJobs = rtrim((string) $existingCronJobs, "\r\n");
    $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);

    $finalLines = [];
    $registeredCommands = [];
    foreach ($cronLines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || strpos($trimmedLine, '#') === 0) {
            $finalLines[] = $line;
            continue;
        }

        if (in_array($trimmedLine, $commands, true)) {
            if (!isset($registeredCommands[$trimmedLine])) {
                $finalLines[] = $trimmedLine;
                $registeredCommands[$trimmedLine] = true;
            }
            continue;
        }

        if (!preg_match($pattern, $trimmedLine)) {
            $finalLines[] = $line;
        }
    }

    foreach ($commands as $command) {
        if (!isset($registeredCommands[$command])) {
            $finalLines[] = $command;
            $registeredCommands[$command] = true;
        }
    }

    if ($finalLines === $cronLines) {
        return ['status' => 'success', 'user' => $cronUser];
    }

    $cronContent = implode(PHP_EOL, $finalLines) . PHP_EOL;

    $temporaryFile = tempnam(sys_get_temp_dir(), 'cron');
    if ($temporaryFile === false) {
        error_log('Unable to create temporary file for cron job registration.');
        return ['status' => 'error', 'user' => $cronUser];
    }

    if (file_put_contents($temporaryFile, $cronContent) === false) {
        error_log('Unable to write cron configuration to temporary file: ' . $temporaryFile);
        unlink($temporaryFile);
        return ['status' => 'error', 'user' => $cronUser];
    }

    runShellCommand(rxCrontabCommand($crontabBinary, $cronUser, escapeshellarg($temporaryFile)));
    unlink($temporaryFile);

    $verifyCronJobs = runShellCommand(rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
    $verifyCronJobs = rtrim((string) $verifyCronJobs, "\r\n");
    $verifyLines = $verifyCronJobs === '' ? [] : preg_split('/\r?\n/', $verifyCronJobs);
    $verifyOk = true;
    foreach ($commands as $command) {
        $commandCount = 0;
        foreach ($verifyLines as $verifyLine) {
            if (trim($verifyLine) === $command) {
                $commandCount++;
            }
        }
        if ($commandCount !== 1) {
            $verifyOk = false;
            break;
        }
    }
    if ($verifyOk) {
        foreach ($verifyLines as $verifyLine) {
            $trimmedLine = trim($verifyLine);
            if ($trimmedLine !== '' && strpos($trimmedLine, '#') !== 0 && !in_array($trimmedLine, $commands, true) && preg_match($pattern, $trimmedLine)) {
                $verifyOk = false;
                break;
            }
        }
    }

    return ['status' => $verifyOk ? 'success' : 'error', 'user' => $cronUser];
}

function replaceCronJobsMatching($pattern, $newCommands)
{
    $result = replaceCronJobsMatchingStatus($pattern, $newCommands);
    return $result['status'] !== 'error';
}

function rxActivecronPattern($domainhosts)
{
    $projectHost = trim((string) preg_replace('#^https?://#i', '', $domainhosts), '/');
    return '#curl\s+-s\s+["\']?https?://' . preg_quote($projectHost, '#') . '/cron/cron\.php(?:\?[^"\'\s]*)?["\']?(?:\s|$)#i';
}

function activecronStatus()
{
    global $domainhosts;

    if (empty($domainhosts)) {
        $domainhosts = $_SERVER['HTTP_HOST'] ?? '';
    }

    if (empty($domainhosts)) {
        error_log('activecron: $domainhosts is empty, skipping cron setup.');
        return ['status' => 'error', 'user' => null];
    }

    $cronCommands = [
        "*/1 * * * * curl -s \"https://$domainhosts/cron/cron.php?_cron=$(date +%s)\" > /dev/null 2>&1",
    ];

    return replaceCronJobsMatchingStatus(rxActivecronPattern($domainhosts), $cronCommands);
}

function activecron()
{
    $result = activecronStatus();
    return $result['status'] !== 'error';
}

if (!function_exists('rxCronEntryExists')) {
    function rxCronEntryExists($cronCommand)
    {
        if (!isShellExecAvailable()) {
            return null;
        }
        $crontabBinary = getCrontabBinary();
        if ($crontabBinary === null) {
            return null;
        }
        $cronUser = getCronTargetUser();
        $commands = is_array($cronCommand) ? $cronCommand : [$cronCommand];
        $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
            return $command !== '';
        }));
        if (empty($commands)) {
            return null;
        }
        $existingCronJobs = runShellCommand(rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
        $existingCronJobs = trim((string) $existingCronJobs);
        $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);
        $cronLines = array_values(array_filter(array_map('trim', $cronLines), static function ($line) {
            return $line !== '' && strpos($line, '#') !== 0;
        }));
        foreach ($commands as $command) {
            if (!in_array($command, $cronLines, true)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('rxEnsureCronRegistered')) {
    function rxEnsureCronRegistered($originLabel = 'unknown')
    {
        if (!isShellExecAvailable()) {
            return false;
        }
        return activecron();
    }
}
function inlineFixer($str, int $count_button = 1)
{
    $str = trim($str);
    if (preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $str)) {
        if ($count_button >= 1) {
            switch ($count_button) {
                case 1:
                    $maxLength = 56;
                    break;
                case 2:
                    $maxLength = 24;
                    break;
                case 3:
                    $maxLength = 14;
                    break;
                default:
                    $maxLength = 2;
            }
            $visualLength = 2;
            $trimmedString = '';
            foreach (mb_str_split($str) as $char) {
                if (preg_match('/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{1F1E6}-\x{1F1FF}]/u', $char)) {
                    $visualLength += 2;
                } else
                    $visualLength++;

                if ($visualLength > $maxLength)
                    break;

                $trimmedString .= $char;
            }
            if ($visualLength > $maxLength) {
                return trim($trimmedString) . '..';
            }
        }
    }
    return trim($str);
}
function sanitizeUserName($userName)
{
    $forbiddenCharacters = [
        "'",
        "\"",
        "<",
        ">",
        "--",
        "#",
        ";",
        "\\",
        "%",
        "(",
        ")"
    ];

    foreach ($forbiddenCharacters as $char) {
        $userName = str_replace($char, "", $userName);
    }
    return $userName;
}
function publickey()
{
    $randomBytes = static function (int $length) {
        if (function_exists('random_bytes')) {
            try {
                return random_bytes($length);
            } catch (Throwable $exception) {
                error_log('random_bytes failed: ' . $exception->getMessage());
            }
        }

        if (class_exists('\\ParagonIE_Sodium_Compat') && method_exists('\\ParagonIE_Sodium_Compat', 'randombytes_buf')) {
            try {
                return \ParagonIE_Sodium_Compat::randombytes_buf($length);
            } catch (Throwable $exception) {
                error_log('sodium_compat randombytes_buf failed: ' . $exception->getMessage());
            }
        }

        return null;
    };

    if (function_exists('sodium_crypto_box_keypair')) {
        try {
            $privateKey = sodium_crypto_box_keypair();
            $privateKeyEncoded = base64_encode(sodium_crypto_box_secretkey($privateKey));
            $publicKey = sodium_crypto_box_publickey($privateKey);
            $publicKeyEncoded = base64_encode($publicKey);
            $presharedBytes = $randomBytes(32);

            if ($presharedBytes === null) {
                throw new RuntimeException('Unable to generate secure preshared key.');
            }

            return [
                'private_key' => $privateKeyEncoded,
                'public_key' => $publicKeyEncoded,
                'preshared_key' => base64_encode($presharedBytes)
            ];
        } catch (Throwable $exception) {
            error_log('libsodium key generation failed: ' . $exception->getMessage());
        }
    }

    if (!class_exists('\\ParagonIE_Sodium_Compat')) {
        $sodiumCompatAutoloaders = [
            APP_ROOT_PATH . '/vendor/autoload.php',
            APP_ROOT_PATH . '/vendor/paragonie/sodium_compat/autoload.php'
        ];

        foreach ($sodiumCompatAutoloaders as $autoloadPath) {
            if (is_readable($autoloadPath)) {
                require_once $autoloadPath;
            }
        }
        unset($sodiumCompatAutoloaders, $autoloadPath);
    }

    if (class_exists('\\ParagonIE_Sodium_Compat') && method_exists('\\ParagonIE_Sodium_Compat', 'crypto_box_keypair')) {
        try {
            $privateKey = \ParagonIE_Sodium_Compat::crypto_box_keypair();
            $privateKeyEncoded = base64_encode(\ParagonIE_Sodium_Compat::crypto_box_secretkey($privateKey));
            $publicKey = \ParagonIE_Sodium_Compat::crypto_box_publickey($privateKey);
            $publicKeyEncoded = base64_encode($publicKey);
            $presharedBytes = $randomBytes(32);

            if ($presharedBytes === null) {
                throw new RuntimeException('Unable to generate secure preshared key.');
            }

            return [
                'private_key' => $privateKeyEncoded,
                'public_key' => $publicKeyEncoded,
                'preshared_key' => base64_encode($presharedBytes)
            ];
        } catch (Throwable $exception) {
            error_log('sodium_compat key generation failed: ' . $exception->getMessage());
        }
    }

    return [
        'status' => false,
        'msg' => 'Libsodium not available'
    ];
}
function languagechange($path_dir)
{

    $rx_candidates = [];
    if (is_string($path_dir) && $path_dir !== '') {
        $rx_candidates[] = $path_dir;
    }
    if (defined('REFACTORED_LEGACY_ROOT')) {
        $rx_candidates[] = REFACTORED_LEGACY_ROOT . DIRECTORY_SEPARATOR . 'text.json';
    }
    $rx_candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'text.json';
    $rx_candidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'text.json';

    $rx_source = null;
    foreach ($rx_candidates as $rx_candidate) {
        if (!is_string($rx_candidate) || $rx_candidate === '') continue;
        if (!@file_exists($rx_candidate)) continue;
        if (@is_readable($rx_candidate)) {
            $rx_source = $rx_candidate;
            break;
        }
    }
    if ($rx_source === null) {
        return [];
    }

    $rx_decoded = null;
    $rx_cache_key = 'faoxima:language:' . md5((string) @realpath($rx_source) . '|' . (int) @filemtime($rx_source) . '|' . (int) @filesize($rx_source));
    if (function_exists('apcu_fetch') && filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
        $rx_cached = apcu_fetch($rx_cache_key, $rx_cache_hit);
        if ($rx_cache_hit && is_array($rx_cached)) {
            $rx_decoded = $rx_cached;
        }
    }
    if (!is_array($rx_decoded)) {
        $rx_raw = @file_get_contents($rx_source);
        if ($rx_raw === false || $rx_raw === '') {
            return [];
        }
        $rx_decoded = json_decode($rx_raw, true);
        if (is_array($rx_decoded) && function_exists('apcu_store') && filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            @apcu_store($rx_cache_key, $rx_decoded, 3600);
        }
    }
    if (!is_array($rx_decoded)) {
        return [];
    }

    $rx_setting = isset($GLOBALS['setting']) && is_array($GLOBALS['setting']) ? $GLOBALS['setting'] : null;
    if ($rx_setting === null && function_exists('select')) {
        try {
            $rx_setting = select("setting", "*");
        } catch (\Throwable $rx_setting_err) {
            $rx_setting = null;
        }
    }
    $rx_lang_key = 'fa';
    if (is_array($rx_setting)) {
        if (isset($rx_setting['languageen']) && intval($rx_setting['languageen']) === 1) {
            $rx_lang_key = 'en';
        } elseif (isset($rx_setting['languageru']) && intval($rx_setting['languageru']) === 1) {
            $rx_lang_key = 'ru';
        }
    }

    $rx_base = null;
    if (isset($rx_decoded[$rx_lang_key]) && is_array($rx_decoded[$rx_lang_key])) {
        $rx_base = $rx_decoded[$rx_lang_key];
    } elseif (isset($rx_decoded['fa']) && is_array($rx_decoded['fa'])) {
        $rx_base = $rx_decoded['fa'];
        $rx_lang_key = 'fa';
    }
    if ($rx_base === null) {
        return [];
    }

    if ($rx_lang_key === 'fa' && function_exists('select')) {
        try {
            $rx_overlay_rows = select('textbot', '*', null, null, 'fetchAll');
            if (is_array($rx_overlay_rows)) {
                $GLOBALS['_rx_textbot_rows'] = $rx_overlay_rows;
                foreach ($rx_overlay_rows as $rx_overlay_row) {
                    $rx_overlay_id = (string) ($rx_overlay_row['id_text'] ?? '');
                    if (strpos($rx_overlay_id, 'jsontext.') !== 0) {
                        continue;
                    }
                    $rx_overlay_path = substr($rx_overlay_id, strlen('jsontext.'));
                    if ($rx_overlay_path === '') {
                        continue;
                    }
                    $rx_overlay_segments = explode('.', $rx_overlay_path);
                    $rx_overlay_cursor = &$rx_base;
                    foreach ($rx_overlay_segments as $rx_overlay_i => $rx_overlay_seg) {
                        if ($rx_overlay_seg === '') {
                            continue 2;
                        }
                        if ($rx_overlay_i === count($rx_overlay_segments) - 1) {
                            $rx_overlay_cursor[$rx_overlay_seg] = (string) ($rx_overlay_row['text'] ?? '');
                        } else {
                            if (!isset($rx_overlay_cursor[$rx_overlay_seg]) || !is_array($rx_overlay_cursor[$rx_overlay_seg])) {
                                $rx_overlay_cursor[$rx_overlay_seg] = [];
                            }
                            $rx_overlay_cursor = &$rx_overlay_cursor[$rx_overlay_seg];
                        }
                    }
                    unset($rx_overlay_cursor);
                }
            }
        } catch (\Throwable $rx_overlay_err) {
        }
    }

    return $rx_base;
}
function faoxima_render_text($template, array $vars)
{
    if (!is_string($template) || $template === '') {
        return '';
    }
    $replacements = [];
    foreach ($vars as $rx_render_key => $rx_render_val) {
        $rx_render_token = (strpos((string) $rx_render_key, '{') === 0) ? $rx_render_key : '{' . $rx_render_key . '}';
        $replacements[$rx_render_token] = (string) $rx_render_val;
    }
    return strtr($template, $replacements);
}
function faoxima_textbot_get($id_text, $fallback = '')
{
    if (!function_exists('select')) {
        return $fallback;
    }
    try {
        $rx_tb_row = select('textbot', 'text', 'id_text', $id_text);
    } catch (\Throwable $rx_tb_err) {
        return $fallback;
    }
    if (is_string($rx_tb_row)) {
        return $rx_tb_row !== '' ? $rx_tb_row : $fallback;
    }
    if (is_array($rx_tb_row) && isset($rx_tb_row['text']) && is_string($rx_tb_row['text']) && $rx_tb_row['text'] !== '') {
        return $rx_tb_row['text'];
    }
    return $fallback;
}
function faoxima_mask_user_id($userId): string
{
    $userId = (string)$userId;
    $len = strlen($userId);
    if ($len <= 4) {
        return $userId === '' ? '' : str_repeat('*', $len);
    }
    $visible = (int)ceil($len / 2);
    return "\xE2\x80\x8E" . substr($userId, 0, $visible) . str_repeat('*', $len - $visible) . "\xE2\x80\x8E";
}
function faoxima_public_purchase_log_event(string $eventKey, array $vars, ?array $setting = null): void
{
    if ($setting === null) {
        if (!function_exists('select')) return;
        try {
            $setting = select('setting', '*');
        } catch (\Throwable $rx_pl_err) {
            return;
        }
    }
    if (!is_array($setting)) return;
    if ((string)($setting['PublicLog_Status'] ?? '0') !== '1') return;

    $eventCols = [
        'new_sub'       => 'PublicLog_NewSub',
        'renewal'       => 'PublicLog_Renewal',
        'volume_topup'  => 'PublicLog_VolumeTopup',
        'time_extra'    => 'PublicLog_TimeExtra',
        'wallet_deposit'=> 'PublicLog_WalletDeposit',
    ];
    $configEvents = ['new_sub' => true, 'renewal' => true];
    $eventTpl = [
        'new_sub'       => ['✅ گزارش خرید #سفارش_جدید', 'dyn_public_broadcast_new_sub_tpl'],
        'renewal'       => ['✅ گزارش تمدید #تمدید_سرویس', 'dyn_public_broadcast_renewal_tpl'],
        'volume_topup'  => ['✅ گزارش خرید #خرید_حجم', 'dyn_public_broadcast_volume_topup_tpl'],
        'time_extra'    => ['✅ گزارش خرید #خرید_زمان', 'dyn_public_broadcast_time_extra_tpl'],
        'wallet_deposit'=> ['✅ گزارش تراکنش #شارژ_کیف_پول', 'dyn_public_broadcast_wallet_deposit_tpl'],
    ];
    if (!isset($eventCols[$eventKey])) return;
    if ((string)($setting[$eventCols[$eventKey]] ?? '0') !== '1') return;

    $channel = trim((string)($setting['PublicLog_Channel'] ?? ''));
    if ($channel === '') return;

    [$titleFallback, $tplKey] = $eventTpl[$eventKey];
    $footer = "📅 : {date} - ⏰ : {time}";
    $fallbackTpl = isset($configEvents[$eventKey])
        ? "{$titleFallback}\n\n<blockquote>👤 آیدی کاربر : {user_id}</blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n{$footer}"
        : "{$titleFallback}\n\n<blockquote>👤 آیدی کاربر : {user_id}</blockquote>\n<blockquote>📦 مقدار : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n{$footer}";
    $vars['user_id'] = faoxima_mask_user_id($vars['user_id'] ?? '');
    $now = time();
    $vars += [
        'date'        => function_exists('jdate') ? jdate('Y/m/d', $now) : date('Y/m/d', $now),
        'time'        => function_exists('jdate') ? jdate('H:i:s', $now) : date('H:i:s', $now),
        'when'        => function_exists('jdate') ? jdate('Y/m/d H:i:s', $now) : date('Y/m/d H:i:s', $now),
        'panel_name'  => '',
        'category'    => '',
    ];
    $text = faoxima_render_text(faoxima_textbot_get($tplKey, $fallbackTpl), $vars);
    if ($text === '') return;

    global $usernamebot;
    $botUsername = '';
    if (isset($usernamebot) && is_string($usernamebot)) {
        $botUsername = ltrim(trim($usernamebot), '@');
    }
    if ($botUsername === '' && function_exists('telegram')) {
        try {
            $me = @telegram('getMe', []);
            if (is_array($me) && isset($me['result']['username'])) {
                $botUsername = (string)$me['result']['username'];
            }
        } catch (\Throwable $rx_pl_bot_err) {
        }
    }

    $btnLabel = faoxima_textbot_get('dyn_public_log_btn_label', '🛒 خرید خدمات از ربات');
    $reply = null;
    if ($botUsername !== '') {
        $reply = json_encode([
            'inline_keyboard' => [[
                ['text' => $btnLabel, 'url' => 'https://t.me/' . $botUsername, 'style' => 'success'],
            ]],
        ]);
    }

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) return;
    try {
        $stmt = $pdo->prepare('INSERT INTO PublicLog_Queue (chat_id, text, reply_markup) VALUES (:c, :t, :r)');
        $stmt->execute([
            ':c' => $channel,
            ':t' => $text,
            ':r' => $reply,
        ]);
    } catch (\Throwable $rx_pl_enqueue_err) {
    }
}
function generateAuthStr($length = 10)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
}
define('QR_MAX_BYTES', 2048);

if (!function_exists('rx_copy_button_keyboard')) {
    function rx_copy_text_fits($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return false;
        }
        if (function_exists('mb_strlen')) {
            $units = mb_strlen($value, 'UTF-8');
        } elseif (function_exists('iconv_strlen')) {
            $units = @iconv_strlen($value, 'UTF-8');
            if ($units === false) {
                $units = strlen($value);
            }
        } else {
            $units = strlen($value);
        }
        $astral = @preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $value);
        if ($astral > 0) {
            $units += $astral;
        }
        return $units <= 256;
    }

    function rx_copy_button_keyboard($value, $label = null, $extraRows = null)
    {
        $value = (string) $value;
        $rows = array();
        if (rx_copy_text_fits($value)) {
            $rows[] = array(array(
                'text' => $label !== null ? $label : '📋 کپی',
                'copy_text' => array('text' => $value),
            ));
        }
        if (is_array($extraRows)) {
            foreach ($extraRows as $extraRow) {
                if (is_array($extraRow)) {
                    $rows[] = $extraRow;
                }
            }
        }
        if (empty($rows)) {
            return null;
        }
        return json_encode(array('inline_keyboard' => $rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

function createqrcode($contents)
{
    if (is_array($contents)) {
        $contents = isset($contents[0]) && is_string($contents[0]) ? $contents[0] : '';
    }
    $contents = is_string($contents) ? trim($contents) : (is_scalar($contents) ? (string) $contents : '');
    if ($contents === '') {
        $contents = 'about:blank';
    }
    if (strlen($contents) > QR_MAX_BYTES) {
        return null;
    }
    $builder = new Builder(
        writer: new PngWriter(),
        writerOptions: [],
        data: $contents,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 500,
        margin: 2,
    );

    try {
        $result = $builder->build();
    } catch (\Throwable $e) {
        error_log('createqrcode failed: ' . $e->getMessage());
        return null;
    }
    return $result;
}
function sanitize_recursive(array $data): array
{
    $sanitized_data = [];
    foreach ($data as $key => $value) {
        $sanitized_key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        if (is_array($value)) {
            $sanitized_data[$sanitized_key] = sanitize_recursive($value);
        } elseif (is_string($value)) {
            $sanitized_data[$sanitized_key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } elseif (is_int($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        } elseif (is_float($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        } elseif (is_bool($value) || is_null($value)) {
            $sanitized_data[$sanitized_key] = $value;
        } else {
            $sanitized_data[$sanitized_key] = $value;
        }
    }
    return $sanitized_data;
}

function check_active_btn($keyboard, $text_var)
{
    $decoded = json_decode((string)$keyboard, true);
    $trace_keyboard = is_array($decoded) && isset($decoded['keyboard']) && is_array($decoded['keyboard'])
        ? $decoded['keyboard']
        : [];
    $status = false;
    foreach ($trace_keyboard as $key => $callback_set) {
        if (!is_array($callback_set)) continue;
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if (is_array($keyboard) && ($keyboard['text'] ?? null) == $text_var) {
                $status = true;
                break;
            }
        }
    }
    return $status;
}

function rx_usertest_panel_active()
{
    $count = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    return is_numeric($count) && (int)$count > 0;
}
function CreatePaymentNv($invoice_id, $amount)
{
    global $domainhosts;
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchentpaynotverify", "select")['ValuePay'];
    $data = [
        'api_key' => $PaySetting,
        'amount' => $amount,
        'callback_url' => "https://" . $domainhosts . "/payment/paymentnv/back.php",
        'desc' => $invoice_id
    ];
    $data = json_encode($data);
    $ch = curl_init("https://donatekon.com/pay/api/dargah/create");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        )
    );
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}
