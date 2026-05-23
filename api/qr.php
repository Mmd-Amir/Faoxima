<?php


declare(strict_types=1);


$__projectRoot = dirname(__DIR__);
$__bgMtime = 0;
foreach (['/images.jpeg', '/images.jpg', '/custom.jpg', '/custom.jpeg'] as $__bgRel) {
    $__bgFull = $__projectRoot . $__bgRel;
    if (is_file($__bgFull)) {
        $__bgMtime = max($__bgMtime, (int) @filemtime($__bgFull));
    }
}
$__etag = '"qr-' . md5(($_SERVER['QUERY_STRING'] ?? '') . '|' . $__bgMtime) . '"';
header('Cache-Control: public, max-age=300, must-revalidate');
header('ETag: ' . $__etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $__etag) {
    http_response_code(304);
    exit;
}

$payload = isset($_GET['d']) ? (string) $_GET['d'] : '';
$payload = substr($payload, 0, 2048);
if ($payload === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'd (data) is required';
    exit;
}

$size = isset($_GET['s']) ? (int) $_GET['s'] : 320;
if ($size < 80) $size = 80;
if ($size > 800) $size = 800;

$style = isset($_GET['style']) ? strtolower((string)$_GET['style']) : 'plain';


$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];
foreach ($autoloadCandidates as $auto) {
    if (is_file($auto)) {
        require_once $auto;
        break;
    }
}

if (!class_exists('\\Endroid\\QrCode\\Builder\\Builder')) {


    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
         . '&data=' . rawurlencode($payload);
    header('Location: ' . $url, true, 302);
    exit;
}

try {

    $qrSize = $style === 'fancy' ? 560 : $size;
    $builder = new \Endroid\QrCode\Builder\Builder(
        writer: new \Endroid\QrCode\Writer\PngWriter(),
        writerOptions: [],
        data: $payload,
        encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
        errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
        size: $qrSize,
        margin: 2,
    );
    $result = $builder->build();
    $qrBinary = $result->getString();


    if ($style === 'fancy') {
        $infocardPath = __DIR__ . '/../infocard.php';
        if (is_file($infocardPath)) {
            require_once $infocardPath;
            if (function_exists('createCryptoQrCard')) {
                $cur = isset($_GET['cur']) ? substr((string)$_GET['cur'], 0, 16) : '';
                $net = isset($_GET['net']) ? substr((string)$_GET['net'], 0, 16) : '';
                $composite = @createCryptoQrCard($qrBinary, null, $cur, $net);
                if (is_string($composite) && $composite !== '') {
                    header('Content-Type: image/png');
                    echo $composite;
                    exit;
                }
            }
        }

    }


    $disableBg  = isset($_GET['bg']) && $_GET['bg'] === '0';
    if (!$disableBg) {
        $projectRoot = dirname(__DIR__);
        $bgCandidates = [
            $projectRoot . '/images.jpeg',
            $projectRoot . '/images.jpg',
            $projectRoot . '/custom.jpg',
            $projectRoot . '/custom.jpeg',
        ];
        $bgPath = null;
        foreach ($bgCandidates as $cand) {
            if (is_file($cand) && is_readable($cand)) { $bgPath = $cand; break; }
        }
        if ($bgPath !== null) {
            $qrImg = @imagecreatefromstring($qrBinary);
            $bgImg = @imagecreatefromstring((string) @file_get_contents($bgPath));
            if ($qrImg !== false && $bgImg !== false) {
                $bgW = imagesx($bgImg); $bgH = imagesy($bgImg);
                $qrW = imagesx($qrImg); $qrH = imagesy($qrImg);


                $shorter   = min($bgW, $bgH);
                $targetQr  = (int) round($shorter * 0.55);
                if ($targetQr < 200) {
                    $targetQr = min(200, $shorter);
                }

                if ($qrW !== $targetQr || $qrH !== $targetQr) {
                    $resized = imagecreatetruecolor($targetQr, $targetQr);
                    $w = imagecolorallocate($resized, 255, 255, 255);
                    imagefill($resized, 0, 0, $w);
                    imagecopyresampled(
                        $resized, $qrImg,
                        0, 0, 0, 0,
                        $targetQr, $targetQr,
                        $qrW, $qrH
                    );
                    imagedestroy($qrImg);
                    $qrImg = $resized;
                    $qrW = $qrH = $targetQr;
                }


                $padding = (int) round($targetQr * 0.10);
                $boxSize = $targetQr + ($padding * 2);
                $boxX    = (int) (($bgW - $boxSize) / 2);
                $boxY    = (int) (($bgH - $boxSize) / 2);


                $boxClipX = max(0, $boxX);
                $boxClipY = max(0, $boxY);
                $boxClipW = min($boxSize, $bgW - $boxClipX);
                $boxClipH = min($boxSize, $bgH - $boxClipY);


                $bgBackup = imagecreatetruecolor($boxClipW, $boxClipH);
                if ($bgBackup !== false) {
                    imagecopy($bgBackup, $bgImg, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);
                }

                $glass = imagecreatetruecolor($boxClipW, $boxClipH);
                if ($glass !== false) {
                    imagecopy($glass, $bgImg, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);


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


                    imagecopy($bgImg, $glass, $boxClipX, $boxClipY, 0, 0, $boxClipW, $boxClipH);
                    imagedestroy($glass);
                } else {
                    $whiteBox = imagecolorallocate($bgImg, 255, 255, 255);
                    imagefilledrectangle($bgImg, $boxX, $boxY, $boxX + $boxSize, $boxY + $boxSize, $whiteBox);
                }
                if ($bgBackup !== false) { imagedestroy($bgBackup); }

                $x = (int) (($bgW - $qrW) / 2);
                $y = (int) (($bgH - $qrH) / 2);
                imagecopy($bgImg, $qrImg, $x, $y, 0, 0, $qrW, $qrH);

                ob_start();
                imagepng($bgImg);
                $merged = ob_get_clean();
                imagedestroy($qrImg);
                imagedestroy($bgImg);
                if ($merged !== false && $merged !== '') {
                    header('Content-Type: image/png');
                    echo $merged;
                    exit;
                }
            }
            if ($qrImg) imagedestroy($qrImg);
            if ($bgImg) imagedestroy($bgImg);
        }
    }

    header('Content-Type: ' . $result->getMimeType());
    echo $qrBinary;
} catch (Throwable $e) {

    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
         . '&data=' . rawurlencode($payload);
    header('Location: ' . $url, true, 302);
    exit;
}

