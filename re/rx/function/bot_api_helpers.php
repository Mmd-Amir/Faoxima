<?php

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
    global $from_id;
    if ($type == "clear") {
        $datauser = [];
        $datauser[$namefiled] = $valuefiled;
        $data = json_encode($datauser, JSON_UNESCAPED_UNICODE);
        update("user", "Processing_value", $data, "id", $from_id);
    } elseif ($type == "save") {
        $userdata = select("user", "*", "id", $from_id, "select");
        $raw = $userdata['Processing_value'] ?? null;
        // Processing_value can legitimately be a scalar sentinel like "0" or "none"
        // (set by /start, back-button handlers, etc.). json_decode on a scalar
        // returns the same scalar, and writing $scalar[$key]=$value is a silent
        // no-op in PHP — which would lose every key. Coerce to array.
        $dataperevieos = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
        if (!is_array($dataperevieos)) {
            $dataperevieos = [];
        }
        $dataperevieos[$namefiled] = $valuefiled;
        update("user", "Processing_value", json_encode($dataperevieos, JSON_UNESCAPED_UNICODE), "id", $from_id);
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
function outtypepanel($typepanel, $message)
{
    global $from_id, $optionMarzban, $optionGuard, $optionX_ui_single, $optionhiddfy, $optionalireza, $optionalireza_single, $optionmarzneshin, $option_mikrotik, $optionwg, $options_ui, $optioneylanpanel, $optionibsng;
    if ($typepanel == "marzban") {
        sendmessage($from_id, $message, $optionMarzban, 'HTML');
    } elseif ($typepanel == "guard") {
        sendmessage($from_id, $message, $optionGuard, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        sendmessage($from_id, $message, $optionX_ui_single, 'HTML');
    } elseif ($typepanel == "hiddify") {
        sendmessage($from_id, $message, $optionhiddfy, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        sendmessage($from_id, $message, $optionalireza_single, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        sendmessage($from_id, $message, $optionmarzneshin, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        sendmessage($from_id, $message, $optionwg, 'HTML');
    } elseif ($typepanel == "s_ui") {
        sendmessage($from_id, $message, $options_ui, 'HTML');
    } elseif ($typepanel == "ibsng") {
        sendmessage($from_id, $message, $optionibsng, 'HTML');
    } elseif ($typepanel == "mikrotik") {
        sendmessage($from_id, $message, $option_mikrotik, 'HTML');
    } elseif ($typepanel == "eylanpanel") {
        sendmessage($from_id, $message, $optioneylanpanel, 'HTML');
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
        $rxBgNotFoundMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_qr_bg_notfound.flag';
        if (!is_file($rxBgNotFoundMarker) || (time() - (int) @filemtime($rxBgNotFoundMarker)) > 86400) {
            error_log("Background image not found for QR code generation (tried project root for {$basename}).");
            @touch($rxBgNotFoundMarker);
        }
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
function checktelegramip()
{
    global $telegramStrictIpValidation;

    $strictValidation = $telegramStrictIpValidation;
    if (!is_bool($strictValidation)) {
        $strictValidation = true;
    }

    if ($strictValidation === false) {
        return true;
    }

    $clientIp = getClientIpConsideringProxies();
    if ($clientIp === null) {
        return false;
    }

    $telegramIpRanges = [
        ['lower' => '149.154.160.0', 'upper' => '149.154.175.255'],
        ['lower' => '91.108.4.0', 'upper' => '91.108.7.255'],
        ['lower' => '2001:67c:4e8::', 'upper' => '2001:67c:4e8:ffff:ffff:ffff:ffff:ffff'],
    ];

    foreach ($telegramIpRanges as $range) {
        if (isClientIpInRange($clientIp, $range['lower'], $range['upper'])) {
            return true;
        }
    }

    return false;
}

function getClientIpConsideringProxies()
{
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

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
    if (is_string($remoteAddr)) {
        $remoteAddr = trim($remoteAddr);
        if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }
    }

    return null;
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

    // On cPanel / shared hosting, shell_exec is typically disabled and the
    // crontab binary is not on the open_basedir / safe-mode PATH. The admin
    // already registers the single cron entry through the cPanel UI, so we
    // silently no-op here instead of polluting error_log on every save.
    if (!isShellExecAvailable()) {
        return true;
    }

    $crontabBinary = getCrontabBinary();
    if ($crontabBinary === null) {
        return true;
    }

    $existingCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
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

    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($temporaryFile)));
    unlink($temporaryFile);

    return true;
}

function activecron()
{
    global $domainhosts;

    // اگر domainhosts خالی بود، از HTTP_HOST سرور استفاده می‌کنیم
    if (empty($domainhosts)) {
        $domainhosts = $_SERVER['HTTP_HOST'] ?? '';
    }

    // اگر هنوز خالی بود، کرون نمی‌سازیم
    if (empty($domainhosts)) {
        error_log('activecron: $domainhosts is empty, skipping cron setup.');
        return false;
    }

    // فقط یک کرون جاب مجاز است — همیشه و فقط cron/cron.php
    $cronCommands = [
        "*/1 * * * * curl -s https://$domainhosts/cron/cron.php > /dev/null 2>&1",
    ];

    return addCronIfNotExists($cronCommands);
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
function createInvoice($amount)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.ir/api/factor/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('amount' => $amount, 'address' => $walletaddress, 'base' => 'trx'),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return json_decode($response, true);
}
function verifpay($id)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.ir/api/factor/status?id=' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return $response;
}
function createInvoiceiranpay1($amount, $id_invoice)
{
    global $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "marchent_floypay", "select")['ValuePay'];
    $curl = curl_init();
    $amount = intval($amount);
    $data = [
        "ApiKey" => $PaySetting,
        "Hash_id" => $id_invoice,
        "Amount" => $amount . "0",
        "CallbackURL" => "https://$domainhosts/payment/iranpay1.php"
    ];
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://tetra98.ir/api/create_order",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function verifyxvoocher($code)
{
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://bot.donatekon.com/api/transaction/verify/" . $code,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json',
            'Authorization: ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);
    return json_decode($response, true);

    curl_close($curl);
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

    $rx_raw = null;
    foreach ($rx_candidates as $rx_candidate) {
        if (!is_string($rx_candidate) || $rx_candidate === '') continue;
        if (!@file_exists($rx_candidate)) continue;
        $rx_attempt = @file_get_contents($rx_candidate);
        if ($rx_attempt !== false && $rx_attempt !== '') {
            $rx_raw = $rx_attempt;
            break;
        }
    }
    if ($rx_raw === null) {
        return [];
    }

    $rx_decoded = json_decode($rx_raw, true);
    if (!is_array($rx_decoded)) {
        return [];
    }


    $rx_setting = null;
    if (function_exists('select')) {
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


    if (isset($rx_decoded[$rx_lang_key]) && is_array($rx_decoded[$rx_lang_key])) {
        return $rx_decoded[$rx_lang_key];
    }
    if (isset($rx_decoded['fa']) && is_array($rx_decoded['fa'])) {
        return $rx_decoded['fa'];
    }
    return [];
}
function generateAuthStr($length = 10)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
}
function createqrcode($contents)
{
    $builder = new Builder(
        writer: new PngWriter(),
        writerOptions: [],
        data: $contents,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 500,
        margin: 2,
    );

    $result = $builder->build();
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
    $trace_keyboard = json_decode($keyboard, true)['keyboard'];
    $status = false;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == $text_var) {
                $status = true;
                break;
            }
        }
    }
    return $status;
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