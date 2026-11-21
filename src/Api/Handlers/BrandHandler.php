<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use Throwable;

final class BrandHandler extends \Faoxima\Api\Handlers\BaseHandler
{

    public $mode = 'info';

    private const DEFAULT_NAME = 'faoxima';
    public function handle(): void
    {
        switch ($this->mode) {
            case 'info':
                $this->handleInfo();
                return;
            case 'save':
                $this->handleSave();
                return;
            case 'upload':
                $this->handleUpload();
                return;
        }
        \Faoxima\Api\Runtime\Response::badRequest('Brand mode invalid');
    }


    public static function readSetting(string $key, string $default = ''): string
    {
        $row = \Faoxima\Core\Database\Db::select('shopSetting', '*', 'Namevalue', $key, 'select');
        if (!is_array($row)) return $default;
        $v = $row['value'] ?? '';
        return is_string($v) ? $v : $default;
    }


    public static function buildBrandPayload(): array
    {
        $name = trim(self::readSetting('brand_name'));
        $mark = trim(self::readSetting('brand_mark'));
        $title = trim(self::readSetting('brand_title'));
        $logo = trim(self::readSetting('brand_logo'));
        $state = self::readSetting('brand_logo_state', 'default');
        $accent = strtolower(trim(self::readSetting('brand_accent')));
        $mode = strtolower(trim(self::readSetting('brand_mode', 'dark')));

        if ($name === '') $name = self::DEFAULT_NAME;
        if ($mark === '') $mark = 'M';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) $accent = '#7c5cff';
        if ($mode !== 'dark' && $mode !== 'light') $mode = 'dark';
        if (!in_array($state, ['default', 'custom', 'initials'], true)) $state = 'default';

        $forceDark = !in_array(strtolower(trim(self::readSetting('brand_force_dark', '1'))), ['0', 'off', 'false', 'no'], true);
        if ($forceDark) $mode = 'dark';

        $logoUrl = '';
        if ($logo !== '') {
            $candidate = dirname(__DIR__, 3) . '/app/assets/branding/' . basename($logo);
            if (is_file($candidate)) {
                $logoUrl = self::imageDataUri($candidate);
            }
        }

        // A custom logo file always wins the state, regardless of what was persisted.
        if ($logoUrl !== '') {
            $state = 'custom';
        } elseif ($state === 'custom') {
            // Custom logo file is missing/deleted but state says custom: fall back to initials, never default.
            $state = 'initials';
        }

        $isDefaultLogo = ($state === 'default');
        if ($isDefaultLogo) {
            $logoUrl = self::imageDataUri(dirname(__DIR__, 3) . '/app/assets/branding/default_logo.jpg');
            if ($logoUrl === '') {
                $state = 'initials';
                $isDefaultLogo = false;
            }
        }

        return [
            'name'             => $name,
            'mark'             => $mark,
            'title'            => $title,
            'logo_url'         => $logoUrl,
            'avatar_state'     => $state,
            'is_default_logo'  => $isDefaultLogo,
            'has_custom_logo'  => ($state === 'custom'),
            'accent'           => $accent,
            'mode'             => $mode,
        ];
    }


    private static function imageDataUri(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) return '';
        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || $bytes === '') return '';
        $info = @getimagesize($path);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) return '';
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }


    private function handleInfo(): void
    {
        $this->requireMethod('GET');
        $payload = self::buildBrandPayload();
        $payload['is_admin'] = $this->userIsAdmin();
        \Faoxima\Api\Runtime\Response::ok($payload);
    }


    private function handleSave(): void
    {
        $this->requireMethod('POST');

        if (!$this->userIsAdmin()) {
            \Faoxima\Api\Runtime\Response::fail(403, 'admin only');
        }

        $name = \Faoxima\Api\Runtime\Input::string($this->data, 'name');
        $mark = \Faoxima\Api\Runtime\Input::string($this->data, 'mark');
        $title = \Faoxima\Api\Runtime\Input::string($this->data, 'title');
        $accent = strtolower(trim(\Faoxima\Api\Runtime\Input::string($this->data, 'accent')));

        $name = trim(preg_replace('/\s+/', ' ', $name));
        $mark = trim($mark);
        $title = trim(preg_replace('/\s+/', ' ', $title));

        if (mb_strlen($name) > 40)  $name = mb_substr($name, 0, 40);
        if (mb_strlen($mark) > 2)   $mark = mb_substr($mark, 0, 2);

        $hasData = is_array($this->data);

        if ($name !== '') {
            $this->upsertSetting('brand_name', $name);
        }
        if ($hasData && array_key_exists('mark', $this->data)) {
            $this->upsertSetting('brand_mark', $mark);
        }
        if ($hasData && array_key_exists('title', $this->data)) {
            if (mb_strlen($title) < 1 || mb_strlen($title) > 10) {
                \Faoxima\Api\Runtime\Response::badRequest('title must be 1 to 10 characters');
            }
            $this->upsertSetting('brand_title', $title);
        }
        if ($accent !== '') {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
                \Faoxima\Api\Runtime\Response::badRequest('accent must be a hex color like #7c5cff');
            }
            $this->upsertSetting('brand_accent', $accent);
        }

        $responsePayload = self::buildBrandPayload();
        \Faoxima\Api\Runtime\Response::ok($responsePayload + ['message' => 'برند با موفقیت ذخیره شد']);
    }


    private function handleUpload(): void
    {
        $this->requireMethod('POST');
        if (!$this->userIsAdmin()) {
            \Faoxima\Api\Runtime\Response::fail(403, 'admin only');
        }


        $clear = \Faoxima\Api\Runtime\Input::string($_POST, 'clear');
        if ($clear === '1') {
            $this->deleteOldLogo();
            $this->upsertSetting('brand_logo', '');
            // Deleting a custom logo (or the default) always lands on INITIALS.
            // CUSTOM_IMAGE -> DEFAULT is a disallowed transition; once the user has left
            // DEFAULT the app must never resurrect it.
            $this->upsertSetting('brand_logo_state', 'initials');
            \Faoxima\Api\Runtime\Response::ok(self::buildBrandPayload() + ['message' => 'لوگو حذف شد']);
        }

        if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
            \Faoxima\Api\Runtime\Response::badRequest('logo file is required');
        }
        $f = $_FILES['logo'];
        if ((int)($f['error'] ?? 99) !== UPLOAD_ERR_OK) {
            \Faoxima\Api\Runtime\Response::badRequest('upload error: ' . ($f['error'] ?? 'unknown'));
        }
        if ((int)($f['size'] ?? 0) > 4 * 1024 * 1024) {
            \Faoxima\Api\Runtime\Response::badRequest('logo too large (max 4 MB)');
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            \Faoxima\Api\Runtime\Response::badRequest('logo not accessible on server');
        }


        $info = @getimagesize($tmp);
        if (!is_array($info)) {
            \Faoxima\Api\Runtime\Response::badRequest('invalid image format');
        }
        $mime = (string)($info['mime'] ?? '');
        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            \Faoxima\Api\Runtime\Response::badRequest('image type not supported');
        }


        $brandDir = dirname(__DIR__, 3) . '/app/assets/branding';
        if (!is_dir($brandDir)) {
            @mkdir($brandDir, 0775, true);
        }
        if (!is_dir($brandDir) || !is_writable($brandDir)) {
            \Faoxima\Api\Runtime\Response::fail(503, 'branding folder is not writable on server');
        }

        $outName = 'logo_' . bin2hex(random_bytes(4)) . '.png';
        $outPath = $brandDir . '/' . $outName;

        $resized = $this->resizeToPng($tmp, $mime, $outPath, 256);
        if (!$resized) {
            \Faoxima\Api\Runtime\Response::fail(500, 'image resize failed (GD missing or write error)');
        }


        $this->deleteOldLogo();
        $this->upsertSetting('brand_logo', $outName);
        $this->upsertSetting('brand_logo_state', 'custom');

        \Faoxima\Api\Runtime\Response::ok(self::buildBrandPayload() + ['message' => 'لوگو ذخیره شد']);
    }


    private function upsertSetting(string $name, string $value): void
    {
        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v)
                  ON DUPLICATE KEY UPDATE value = VALUES(value)'
            );
            $stmt->execute([':n' => $name, ':v' => $value]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('shopSetting');
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'brand upsertSetting failed', ['name' => $name]);
            \Faoxima\Api\Runtime\Response::fail(500, 'cannot save brand setting');
        }
    }


    private function deleteOldLogo(): void
    {
        $old = trim(self::readSetting('brand_logo'));
        if ($old === '') return;
        $oldPath = dirname(__DIR__, 3) . '/app/assets/branding/' . basename($old);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }


    private function resizeToPng(string $srcPath, string $mime, string $destPath, int $maxSize): bool
    {
        if (!function_exists('imagecreatetruecolor')) return false;

        $src = null;
        if ($mime === 'image/png')  $src = @imagecreatefrompng($srcPath);
        elseif ($mime === 'image/jpeg') $src = @imagecreatefromjpeg($srcPath);
        elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($srcPath);
        elseif ($mime === 'image/gif')  $src = @imagecreatefromgif($srcPath);
        if (!$src) return false;

        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw <= 0 || $sh <= 0) { imagedestroy($src); return false; }


        $scale = min(1.0, $maxSize / max($sw, $sh));
        $dw = max(1, (int)round($sw * $scale));
        $dh = max(1, (int)round($sh * $scale));

        $dst = imagecreatetruecolor($dw, $dh);
        if (!$dst) { imagedestroy($src); return false; }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dw, $dh, $transparent);
        imagealphablending($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        $ok = imagepng($dst, $destPath, 6);
        imagedestroy($src);
        imagedestroy($dst);

        if (!$ok) return false;
        @chmod($destPath, 0644);
        return true;
    }
}
