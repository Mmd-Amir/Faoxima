<?php

namespace Faoxima\Telegram\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;
use Throwable;

final class QrAndKeys
{
    public static function checkConnection($address, $port)
    {
        $socket = @stream_socket_client("tcp://$address:$port", $errno, $errstr, 5);
        if ($socket) {
            fclose($socket);
            return true;
        } else {
            return false;
        }
    }

    public static function addBackgroundImage($urlimage, $qrCodeResult, $backgroundPath)
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

        $candidates = [];
        $customPath = $projectRoot . DIRECTORY_SEPARATOR . 'custom.jpg';
        if (is_file($customPath) && is_readable($customPath) && (int) @filesize($customPath) > 0 && @getimagesize($customPath) !== false) {
            $candidates[] = $customPath;
        }
        array_push(
            $candidates,
            $projectRoot . DIRECTORY_SEPARATOR . $basenameNoExt . '.jpeg',
            $projectRoot . DIRECTORY_SEPARATOR . $basenameNoExt . '.jpg',
            $projectRoot . DIRECTORY_SEPARATOR . 'images.jpeg',
            $projectRoot . DIRECTORY_SEPARATOR . 'images.jpg'
        );

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

            for ($i = 0; $i < 6; $i++) {
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

    public static function publickey()
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

    public static function createqrcode($contents)
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
}
