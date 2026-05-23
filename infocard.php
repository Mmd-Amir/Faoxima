<?php


declare(strict_types=1);

if (!defined('INFOCARD_FONT_DIR')) {
    define('INFOCARD_FONT_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'infocard_fonts');
}


function infocard_palette(): array
{
    return [
        'yellow' => ['accent' => [232, 191, 102], 'soft' => [110, 86, 32]],
        'green'  => ['accent' => [82, 199, 146],  'soft' => [30, 86, 60]],
        'red'    => ['accent' => [232, 102, 102], 'soft' => [110, 32, 32]],
        'blue'   => ['accent' => [102, 168, 232], 'soft' => [32, 70, 110]],
        'purple' => ['accent' => [178, 130, 232], 'soft' => [70, 38, 110]],
        'orange' => ['accent' => [232, 145, 78],  'soft' => [110, 60, 24]],
    ];
}


function getInfoCardStatus(): bool
{
    if (!function_exists('select')) {
        return false;
    }
    try {
        $row = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    } catch (\Throwable $e) {
        return false;
    }
    if (!is_array($row)) {
        return false;
    }
    return ((string) ($row['value'] ?? '0')) === '1';
}


function getInfoCardColor(): string
{
    if (!function_exists('select')) {
        return 'yellow';
    }
    try {
        $row = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    } catch (\Throwable $e) {
        return 'yellow';
    }
    if (!is_array($row)) {
        return 'yellow';
    }
    $val = strtolower(trim((string) ($row['value'] ?? '')));
    $palette = infocard_palette();
    return isset($palette[$val]) ? $val : 'yellow';
}


function infocard_resolve_font(string $weight = 'regular'): ?string
{
    $weightMap = [
        'regular' => 'JetBrainsMono-Regular.ttf',
        'medium'  => 'JetBrainsMono-Medium.ttf',
        'bold'    => 'JetBrainsMono-Bold.ttf',
        'persian' => 'Arad-MediumDots2.ttf',
    ];
    $filename = $weightMap[$weight] ?? $weightMap['regular'];
    $bundled = INFOCARD_FONT_DIR . DIRECTORY_SEPARATOR . $filename;
    if (is_file($bundled) && is_readable($bundled)) {
        return $bundled;
    }


    if ($weight === 'persian') {
        $candidates = [
            (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
                . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'fonts'
                . DIRECTORY_SEPARATOR . 'Arad-MediumDots2.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        foreach ($candidates as $c) {
            if (is_file($c) && is_readable($c)) return $c;
        }
        return null;
    }


    foreach (['JetBrainsMono-Regular.ttf', 'JetBrainsMono-Medium.ttf', 'JetBrainsMono-Bold.ttf'] as $alt) {
        $altPath = INFOCARD_FONT_DIR . DIRECTORY_SEPARATOR . $alt;
        if (is_file($altPath) && is_readable($altPath)) {
            return $altPath;
        }
    }


    $systemSets = [
        'bold' => [
            '/usr/share/fonts/truetype/cascadia-code/CascadiaMono.ttf',
            '/usr/share/fonts/truetype/firacode/FiraCode-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
        ],
        'medium' => [
            '/usr/share/fonts/truetype/firacode/FiraCode-Medium.ttf',
            '/usr/share/fonts/truetype/cascadia-code/CascadiaMono.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
        ],
        'regular' => [
            '/usr/share/fonts/truetype/cascadia-code/CascadiaMono.ttf',
            '/usr/share/fonts/truetype/firacode/FiraCode-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
        ],
    ];
    foreach ($systemSets[$weight] ?? $systemSets['regular'] as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }


    $arad = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
        . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'fonts'
        . DIRECTORY_SEPARATOR . 'Arad-MediumDots2.ttf';
    if (is_file($arad) && is_readable($arad)) {
        return $arad;
    }
    return null;
}


function infocard_truncate(string $value, int $maxChars): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') <= $maxChars) {
            return $value;
        }
        return mb_substr($value, 0, max(1, $maxChars - 1), 'UTF-8') . '…';
    }
    if (strlen($value) <= $maxChars) {
        return $value;
    }
    return substr($value, 0, max(1, $maxChars - 1)) . '...';
}


function createServiceInfoCard(array $params, string $color = 'yellow', ?string $outputPath = null)
{
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return false;
    }

    $fontRegular = infocard_resolve_font('regular');
    $fontMedium  = infocard_resolve_font('medium')  ?: $fontRegular;
    $fontBold    = infocard_resolve_font('bold')    ?: ($fontMedium ?: $fontRegular);
    $fontFa      = infocard_resolve_font('persian') ?: $fontRegular;
    if ($fontRegular === null) {
        return false;
    }

    $palette = infocard_palette();
    $color   = strtolower(trim($color));
    if (!isset($palette[$color])) {
        $color = 'yellow';
    }
    $accentRGB = $palette[$color]['accent'];


    $W = 800;
    $H = 400;

    $img = imagecreatetruecolor($W, $H);
    imagesavealpha($img, true);
    imageantialias($img, true);


    $bgOuter   = imagecolorallocate($img, 10, 10, 10);
    $bgInner   = imagecolorallocate($img, 18, 18, 18);
    $border    = imagecolorallocate($img, 38, 38, 38);
    $headerBg  = imagecolorallocate($img, 24, 24, 24);
    $textMain  = imagecolorallocate($img, 235, 235, 235);
    $textDim   = imagecolorallocate($img, 150, 150, 150);
    $textFaint = imagecolorallocate($img, 105, 105, 105);
    $accent    = imagecolorallocate($img, $accentRGB[0], $accentRGB[1], $accentRGB[2]);
    $dotRed    = imagecolorallocate($img, 235, 95, 87);
    $dotYellow = imagecolorallocate($img, 247, 195, 75);
    $dotGreen  = imagecolorallocate($img, 80, 196, 90);
    $statusOK  = imagecolorallocate($img, 80, 196, 120);
    $statusBad = imagecolorallocate($img, 235, 95, 87);


    imagefilledrectangle($img, 0, 0, $W, $H, $bgOuter);


    $px = 26;
    $py = 20;
    $pw = $W - 2 * $px;
    $ph = $H - 2 * $py;
    infocard_filled_rounded_rect($img, $px, $py, $px + $pw, $py + $ph, 14, $bgInner);
    infocard_rounded_rect_outline($img, $px, $py, $px + $pw, $py + $ph, 14, $border);


    $headerH = 36;
    infocard_filled_rounded_rect_top($img, $px + 1, $py + 1, $px + $pw - 1, $py + $headerH, 13, $headerBg);
    imageline($img, $px + 1, $py + $headerH, $px + $pw - 1, $py + $headerH, $border);


    $dotY = $py + $headerH / 2;
    $dotR = 7;
    $dotX = $px + 22;
    infocard_filled_circle($img, $dotX,            (int) $dotY, $dotR, $dotRed);
    infocard_filled_circle($img, $dotX + 22,       (int) $dotY, $dotR, $dotYellow);
    infocard_filled_circle($img, $dotX + 44,       (int) $dotY, $dotR, $dotGreen);


    $bot = (string) ($params['bot_username'] ?? '');
    $bot = ltrim($bot, '@');
    $headerText = $bot !== '' ? ($bot . '/info') : 'service/info';
    infocard_text_centered($img, $W / 2, $py + $headerH / 2 + 4, 13, $fontRegular, $textDim, $headerText);


    $bodyX = $px + 28;
    $bodyY = $py + $headerH + 28;


    $L = [
        'config'    => 'ﮓﯿﻔﻧﺎﮐ',
        'active'    => 'ﻝﺎﻌﻓ',
        'inactive'  => 'ﻝﺎﻌﻓﺮﯿﻏ',
        'usage'     => 'ﯽﻓﺮﺼﻣ ﻢﺠﺣ',
        'time'      => 'ﻩﺪﻧﺎﻣﯽﻗﺎﺑ ﻥﺎﻣﺯ',
        'days'      => 'ﺯﻭﺭ',
        'used'      => 'ﻑﺮﺼﻣ',
        'unlimited' => 'ﺩﻭﺪﺤﻣﺎﻧ',
        'expired'   => 'ﻩﺪﺷ ﯽﻀﻘﻨﻣ',
        'user_id'   => 'ﺮﺑﺭﺎﮐ ﯼﺪﯾﺁ',
    ];


    $configName = (string) ($params['config_name'] ?? '');
    $configName = infocard_truncate($configName, 26);
    $titleY = $bodyY + 18;
    $cursorX = $bodyX;
    $cursorX += imagettftextsafe($img, 18, 0, $cursorX, $titleY, $accent,   $fontBold, '> ');
    $cursorX += imagettftextsafe($img, 18, 0, $cursorX, $titleY, $textMain, $fontFa,   $L['config']);
    $cursorX += imagettftextsafe($img, 18, 0, $cursorX, $titleY, $textMain, $fontBold, ': ');
    imagettftextsafe($img, 18, 0, $cursorX, $titleY, $textMain, $fontBold, $configName);


    $isActive  = !empty($params['active']);
    $statusTxt = $isActive ? $L['active'] : $L['inactive'];
    $statusCol = $isActive ? $statusOK : $statusBad;
    $statusBox = imagettfbboxsafe(14, 0, $fontFa, $statusTxt);
    if ($statusBox !== false) {
        $sw = $statusBox[2] - $statusBox[0];
        $sx = $px + $pw - 28 - $sw;
        imagettftextsafe($img, 14, 0, $sx, $titleY, $statusCol, $fontFa, $statusTxt);
    }


    $usedBytes  = (float) ($params['used_bytes']  ?? 0);
    $totalBytes = (float) ($params['total_bytes'] ?? 0);
    $unlimitedVolume = $totalBytes <= 0;
    if ($unlimitedVolume || $usedBytes <= 0) {
        $percent = 0.0;
    } else {
        $percent = ($usedBytes / $totalBytes) * 100;
        if ($percent > 100) $percent = 100;
        if ($percent < 0)   $percent = 0;
    }


    $gaugeCX = $bodyX + 80;
    $gaugeCY = $bodyY + 130;
    $gaugeR  = 60;
    infocard_draw_gauge($img, $gaugeCX, $gaugeCY, $gaugeR, $percent, $accent, $border, $bgInner, $fontBold, $fontFa, $textMain, $textFaint, $unlimitedVolume, $L);


    $rightX  = $bodyX + 200;
    $lineY   = $bodyY + 56;
    $lineGap = 70;
    $valueDY = 38;


    $cx = $rightX;
    $cx += imagettftextsafe($img, 11, 0, $cx, $lineY, $textFaint, $fontRegular, '$ ');
    $cx += imagettftextsafe($img, 12, 0, $cx, $lineY, $textFaint, $fontFa,      $L['usage']);
    imagettftextsafe($img, 11, 0, $cx, $lineY, $textFaint, $fontRegular, ':');
    if ($unlimitedVolume) {
        imagettftextsafe($img, 19, 0, $rightX, $lineY + $valueDY, $textMain, $fontFa, $L['unlimited']);
    } else {
        $usageStr = infocard_format_bytes($usedBytes) . '  /  ' . infocard_format_bytes($totalBytes);
        imagettftextsafe($img, 19, 0, $rightX, $lineY + $valueDY, $textMain, $fontBold, $usageStr);
    }


    $lineY += $lineGap;
    $cx = $rightX;
    $cx += imagettftextsafe($img, 11, 0, $cx, $lineY, $textFaint, $fontRegular, '# ');
    $cx += imagettftextsafe($img, 12, 0, $cx, $lineY, $textFaint, $fontFa,      $L['time']);
    imagettftextsafe($img, 11, 0, $cx, $lineY, $textFaint, $fontRegular, ':');
    $unlimitedTime = !empty($params['unlimited_time']);
    $daysLeft = (int) ($params['days_left'] ?? 0);
    if ($unlimitedTime) {
        imagettftextsafe($img, 19, 0, $rightX, $lineY + $valueDY, $textMain, $fontFa, $L['unlimited']);
    } elseif ($daysLeft <= 0) {
        imagettftextsafe($img, 19, 0, $rightX, $lineY + $valueDY, $textMain, $fontFa, $L['expired']);
    } else {

        $cx2 = $rightX;
        $cx2 += imagettftextsafe($img, 19, 0, $cx2, $lineY + $valueDY, $textMain, $fontBold, (string) $daysLeft);
        imagettftextsafe($img, 18, 0, $cx2 + 8, $lineY + $valueDY, $textMain, $fontFa, $L['days']);
    }


    if ($bot !== '') {
        $footerY = $py + $ph - 18;
        $botTag = '@' . $bot;
        $bbox = imagettfbboxsafe(13, 0, $fontRegular, $botTag);
        if ($bbox !== false) {
            $bw = $bbox[2] - $bbox[0];
            imagettftextsafe($img, 13, 0, (int) ($W / 2 - $bw / 2), $footerY, $accent, $fontRegular, $botTag);
        }
    }


    infocard_filled_circle($img, $px + 8,        $py + $ph + 4,  60, imagecolorallocatealpha($img, $accentRGB[0], $accentRGB[1], $accentRGB[2], 110));
    infocard_filled_circle($img, $px + $pw - 8,  $py - 4,        50, imagecolorallocatealpha($img, $accentRGB[0], $accentRGB[1], $accentRGB[2], 115));


    infocard_rounded_rect_outline($img, $px, $py, $px + $pw, $py + $ph, 14, $border);


    if ($outputPath === null) {
        $outputPath = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
            . DIRECTORY_SEPARATOR
            . 'infocard_' . bin2hex(random_bytes(4)) . '.png';
    }
    $ok = imagepng($img, $outputPath, 6);
    imagedestroy($img);
    return $ok ? $outputPath : false;
}


function imagettftextsafe($img, $size, $angle, $x, $y, $color, $font, string $text)
{
    if (!is_string($font) || $font === '' || !is_file($font)) {
        return imagestring($img, 4, (int) $x, (int) $y - 14, $text, $color);
    }
    $bbox = @imagettftext($img, $size, $angle, (int) $x, (int) $y, $color, $font, $text);
    if ($bbox === false) {
        return 0;
    }
    return $bbox[2] - $bbox[0];
}

function imagettfbboxsafe($size, $angle, $font, string $text)
{
    if (!is_string($font) || $font === '' || !is_file($font)) {
        $w = strlen($text) * imagefontwidth(4);
        return [0, 0, $w, 0, $w, 14, 0, 14];
    }
    $bbox = @imagettfbbox($size, $angle, $font, $text);
    return $bbox === false ? false : $bbox;
}

function infocard_text_centered($img, $cx, $cy, $size, $font, $color, string $text): void
{
    $bbox = imagettfbboxsafe($size, 0, $font, $text);
    if ($bbox === false) {
        return;
    }
    $w = $bbox[2] - $bbox[0];
    imagettftextsafe($img, $size, 0, (int) ($cx - $w / 2), (int) $cy, $color, $font, $text);
}

function infocard_filled_circle($img, int $cx, int $cy, int $r, $color): void
{
    imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
}

function infocard_filled_rounded_rect($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
{

    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);

    imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

function infocard_filled_rounded_rect_top($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
{
    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2, $color);
    imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
}

function infocard_rounded_rect_outline($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
{

    imageline($img, $x1 + $r, $y1, $x2 - $r, $y1, $color);
    imageline($img, $x1 + $r, $y2, $x2 - $r, $y2, $color);
    imageline($img, $x1, $y1 + $r, $x1, $y2 - $r, $color);
    imageline($img, $x2, $y1 + $r, $x2, $y2 - $r, $color);

    imagearc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color);
    imagearc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color);
    imagearc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color);
    imagearc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color);
}


function infocard_draw_gauge($img, int $cx, int $cy, int $r, float $percent, $accentColor, $trackColor, $bgFill, $fontEN, $fontFA, $textColor, $faintColor, bool $unlimited, array $L = []): void
{
    $scale = 4;
    $pad   = 8 * $scale;
    $ringThickness = 7;
    $size = ($r + $ringThickness) * 2 + $pad * 2 / $scale;
    $W = (int) ($size * $scale);
    $H = (int) ($size * $scale);


    $hi = imagecreatetruecolor($W, $H);
    imagealphablending($hi, false);
    $transparent = imagecolorallocatealpha($hi, 0, 0, 0, 127);
    imagefilledrectangle($hi, 0, 0, $W, $H, $transparent);
    imagealphablending($hi, true);
    imagesavealpha($hi, true);


    $tColor = imagecolorsforindex($img, $trackColor);
    $aColor = imagecolorsforindex($img, $accentColor);
    $bColor = imagecolorsforindex($img, $bgFill);
    $hiTrack  = imagecolorallocate($hi, $tColor['red'], $tColor['green'], $tColor['blue']);
    $hiAccent = imagecolorallocate($hi, $aColor['red'], $aColor['green'], $aColor['blue']);
    $hiBg     = imagecolorallocate($hi, $bColor['red'], $bColor['green'], $bColor['blue']);


    $hiCX = (int) ($W / 2);
    $hiCY = (int) ($H / 2);
    $hiOuter = ($r + intdiv($ringThickness, 2)) * $scale;
    $hiInner = ($r - intdiv($ringThickness, 2) - ($ringThickness % 2)) * $scale;


    imagefilledellipse($hi, $hiCX, $hiCY, $hiOuter * 2, $hiOuter * 2, $hiTrack);
    imagefilledellipse($hi, $hiCX, $hiCY, $hiInner * 2, $hiInner * 2, $hiBg);


    if (!$unlimited && $percent > 0) {

        $start = 270.0;
        $end   = 270.0 + ($percent * 3.6);
        if ($end >= $start + 360.0) $end = $start + 359.999;


        imagefilledarc($hi, $hiCX, $hiCY, $hiOuter * 2, $hiOuter * 2, (int) round($start), (int) round($end), $hiAccent, IMG_ARC_PIE);
        imagefilledellipse($hi, $hiCX, $hiCY, $hiInner * 2, $hiInner * 2, $hiBg);


        $tipR = intdiv(($hiOuter - $hiInner), 2);
        $midR = intdiv(($hiOuter + $hiInner), 2);
        $startRad = deg2rad($start);
        $endRad   = deg2rad($end);
        $sx = (int) round($hiCX + cos($startRad) * $midR);
        $sy = (int) round($hiCY + sin($startRad) * $midR);
        $ex = (int) round($hiCX + cos($endRad)   * $midR);
        $ey = (int) round($hiCY + sin($endRad)   * $midR);
        imagefilledellipse($hi, $sx, $sy, $tipR * 2, $tipR * 2, $hiAccent);
        imagefilledellipse($hi, $ex, $ey, $tipR * 2, $tipR * 2, $hiAccent);
    }


    $dstW = (int) ($W / $scale);
    $dstH = (int) ($H / $scale);
    $dstX = (int) ($cx - $dstW / 2);
    $dstY = (int) ($cy - $dstH / 2);

    imagealphablending($img, true);
    imagecopyresampled($img, $hi, $dstX, $dstY, 0, 0, $dstW, $dstH, $W, $H);
    imagedestroy($hi);


    if ($unlimited) {


        infocard_text_centered($img, $cx, $cy + 6, 14, $fontFA, $textColor, $L['unlimited'] ?? 'ﺩﻭﺪﺤﻣﺎﻧ');
    } else {
        $percentText = number_format($percent, 1) . '%';
        infocard_text_centered($img, $cx, $cy + 4, 16, $fontEN, $textColor, $percentText);
        infocard_text_centered($img, $cx, $cy + 22, 10, $fontFA, $faintColor, $L['used'] ?? 'ﻑﺮﺼﻣ');
    }
}


function createCryptoQrCard(string $qrPngBinary, ?string $outputPath = null, string $currency = '', string $network = '')
{
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    if ($qrPngBinary === '') {
        return false;
    }

    $W = 1024;
    $H = 1024;

    $curUp = strtoupper(trim($currency));
    $netUp = strtoupper(trim($network));
    $isUsdt = (strpos($curUp, 'USDT') === 0);
    $isTron = ($curUp === 'TRX' || $netUp === 'TRON' || $netUp === 'TRC20' || $netUp === 'TRC-20');
    $isTon  = ($curUp === 'TON' || $netUp === 'TON');

    if ($isUsdt) {
        $bgStart = [232, 255, 245];
        $bgEnd   = [40,  155, 100];
        $minor   = [120, 180, 150, 110];
        $major   = [80,  140, 110, 95];
    } elseif ($isTron) {
        $bgStart = [255, 232, 232];
        $bgEnd   = [195, 30,  40];
        $minor   = [200, 90,  100, 110];
        $major   = [160, 50,  60,  95];
    } elseif ($isTon) {
        $bgStart = [232, 244, 255];
        $bgEnd   = [50,  140, 215];
        $minor   = [130, 170, 220, 110];
        $major   = [80,  130, 200, 95];
    } else {
        $bgStart = [245, 248, 255];
        $bgEnd   = [110, 155, 240];
        $minor   = [130, 150, 200, 115];
        $major   = [110, 130, 190, 100];
    }

    $img = imagecreatetruecolor($W, $H);
    imagesavealpha($img, true);
    imagealphablending($img, true);


    for ($y = 0; $y < $H; $y++) {
        $t = $y / max(1, $H - 1);
        $r = (int) round($bgStart[0] + ($bgEnd[0] - $bgStart[0]) * $t);
        $g = (int) round($bgStart[1] + ($bgEnd[1] - $bgStart[1]) * $t);
        $b = (int) round($bgStart[2] + ($bgEnd[2] - $bgStart[2]) * $t);
        $c = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $W, $y, $c);
    }


    $gridMinor = imagecolorallocatealpha($img, $minor[0], $minor[1], $minor[2], $minor[3]);
    $cellMinor = 32;
    for ($x = 0; $x <= $W; $x += $cellMinor) {
        imageline($img, $x, 0, $x, $H, $gridMinor);
    }
    for ($y = 0; $y <= $H; $y += $cellMinor) {
        imageline($img, 0, $y, $W, $y, $gridMinor);
    }


    $gridMajor = imagecolorallocatealpha($img, $major[0], $major[1], $major[2], $major[3]);
    $cellMajor = 128;
    for ($x = 0; $x <= $W; $x += $cellMajor) {
        imageline($img, $x, 0, $x, $H, $gridMajor);
    }
    for ($y = 0; $y <= $H; $y += $cellMajor) {
        imageline($img, 0, $y, $W, $y, $gridMajor);
    }


    $frameSize = 720;
    $frameX1 = (int) (($W - $frameSize) / 2);
    $frameY1 = (int) (($H - $frameSize) / 2);
    $frameX2 = $frameX1 + $frameSize;
    $frameY2 = $frameY1 + $frameSize;


    $shadowColor = imagecolorallocatealpha($img, 50, 80, 160, 95);
    for ($i = 22; $i >= 1; $i--) {
        infocard_filled_rounded_rect(
            $img,
            $frameX1 - $i,
            $frameY1 - $i + 10,
            $frameX2 + $i,
            $frameY2 + $i + 10,
            44 + (int) ($i / 3),
            $shadowColor
        );
    }


    $borderOuter = imagecolorallocate($img, 130, 165, 240);
    for ($t = 0; $t < 14; $t++) {
        infocard_rounded_rect_outline(
            $img,
            $frameX1 - $t,
            $frameY1 - $t,
            $frameX2 + $t,
            $frameY2 + $t,
            44 + $t,
            $borderOuter
        );
    }


    $whiteColor = imagecolorallocate($img, 252, 252, 254);
    infocard_filled_rounded_rect($img, $frameX1, $frameY1, $frameX2, $frameY2, 40, $whiteColor);


    $borderInner = imagecolorallocate($img, 90, 130, 220);
    for ($t = 0; $t < 3; $t++) {
        infocard_rounded_rect_outline(
            $img,
            $frameX1 + $t,
            $frameY1 + $t,
            $frameX2 - $t,
            $frameY2 - $t,
            40 - $t,
            $borderInner
        );
    }


    $qrImg = @imagecreatefromstring($qrPngBinary);
    if ($qrImg) {
        $qrW = imagesx($qrImg);
        $qrH = imagesy($qrImg);
        $innerPad = 56;
        $maxW = $frameSize - $innerPad * 2;
        $scale = min(1.0, $maxW / max($qrW, $qrH));
        $dstW = (int) round($qrW * $scale);
        $dstH = (int) round($qrH * $scale);
        $dstX = (int) ($frameX1 + ($frameSize - $dstW) / 2);
        $dstY = (int) ($frameY1 + ($frameSize - $dstH) / 2);
        imagecopyresampled($img, $qrImg, $dstX, $dstY, 0, 0, $dstW, $dstH, $qrW, $qrH);
        imagedestroy($qrImg);
    }

    if ($outputPath === null) {
        ob_start();
        imagepng($img, null, 6);
        $binary = ob_get_clean();
        imagedestroy($img);
        return $binary;
    }
    $ok = imagepng($img, $outputPath, 6);
    imagedestroy($img);
    return $ok ? $outputPath : false;
}


function infocard_format_bytes($bytes): string
{
    $bytes = (float) $bytes;
    if ($bytes <= 0) {
        return '0.00 GB';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, 2) . ' ' . $units[$i];
}

