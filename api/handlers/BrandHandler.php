<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class BrandHandler extends BaseHandler
{

    public $mode = 'info';

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
        FaoximaResponse::badRequest('Brand mode invalid');
    }


    public static function readSetting(string $key, string $default = ''): string
    {
        $row = select('shopSetting', '*', 'Namevalue', $key, 'select');
        if (!is_array($row)) return $default;
        $v = $row['value'] ?? '';
        return is_string($v) ? $v : $default;
    }


    public static function buildBrandPayload(): array
    {
        $name = trim(self::readSetting('brand_name'));
        $mark = trim(self::readSetting('brand_mark'));
        $logo = trim(self::readSetting('brand_logo'));

        if ($name === '') $name = 'Faoxima';
        if ($mark === '') $mark = 'M';

        $logoUrl = '';
        if ($logo !== '') {
            $candidate = __DIR__ . '/../../app/assets/branding/' . basename($logo);
            if (is_file($candidate)) {
                $logoUrl = 'assets/branding/' . basename($logo) . '?v=' . filemtime($candidate);
            }
        }


        if ($logoUrl === '') {
            $defaultLogo = __DIR__ . '/../../app/assets/branding/default_logo.jpg';
            if (is_file($defaultLogo)) {
                $logoUrl = 'assets/branding/default_logo.jpg?v=' . filemtime($defaultLogo);
            }
        }

        return [
            'name'     => $name,
            'mark'     => $mark,
            'logo_url' => $logoUrl,
        ];
    }


    private function handleInfo(): void
    {
        $this->requireMethod('GET');
        $payload = self::buildBrandPayload();
        $payload['is_admin'] = $this->userIsAdmin();
        FaoximaResponse::ok($payload);
    }


    private function handleSave(): void
    {
        $this->requireMethod('POST');
        if (!$this->userIsAdmin()) {
            FaoximaResponse::fail(403, 'admin only');
        }

        $name = FaoximaInput::string($this->data, 'name');
        $mark = FaoximaInput::string($this->data, 'mark');

        $name = trim(preg_replace('/\s+/', ' ', $name));
        $mark = trim($mark);

        if (mb_strlen($name) > 40)  $name = mb_substr($name, 0, 40);
        if (mb_strlen($mark) > 4)   $mark = mb_substr($mark, 0, 4);

        if ($name !== '') {
            $this->upsertSetting('brand_name', $name);
        }
        $this->upsertSetting('brand_mark', $mark);

        FaoximaResponse::ok(self::buildBrandPayload() + ['message' => 'برند با موفقیت ذخیره شد']);
    }


    private function handleUpload(): void
    {
        $this->requireMethod('POST');
        if (!$this->userIsAdmin()) {
            FaoximaResponse::fail(403, 'admin only');
        }


        $clear = FaoximaInput::string($_POST, 'clear');
        if ($clear === '1') {
            $this->deleteOldLogo();
            $this->upsertSetting('brand_logo', '');
            FaoximaResponse::ok(self::buildBrandPayload() + ['message' => 'لوگو حذف شد']);
        }

        if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
            FaoximaResponse::badRequest('logo file is required');
        }
        $f = $_FILES['logo'];
        if ((int)($f['error'] ?? 99) !== UPLOAD_ERR_OK) {
            FaoximaResponse::badRequest('upload error: ' . ($f['error'] ?? 'unknown'));
        }
        if ((int)($f['size'] ?? 0) > 4 * 1024 * 1024) {
            FaoximaResponse::badRequest('logo too large (max 4 MB)');
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            FaoximaResponse::badRequest('logo not accessible on server');
        }


        $info = @getimagesize($tmp);
        if (!is_array($info)) {
            FaoximaResponse::badRequest('invalid image format');
        }
        $mime = (string)($info['mime'] ?? '');
        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            FaoximaResponse::badRequest('image type not supported');
        }


        $brandDir = __DIR__ . '/../../app/assets/branding';
        if (!is_dir($brandDir)) {
            @mkdir($brandDir, 0775, true);
        }
        if (!is_dir($brandDir) || !is_writable($brandDir)) {
            FaoximaResponse::fail(503, 'branding folder is not writable on server');
        }

        $outName = 'logo_' . bin2hex(random_bytes(4)) . '.png';
        $outPath = $brandDir . '/' . $outName;

        $resized = $this->resizeToPng($tmp, $mime, $outPath, 256);
        if (!$resized) {
            FaoximaResponse::fail(500, 'image resize failed (GD missing or write error)');
        }


        $this->deleteOldLogo();
        $this->upsertSetting('brand_logo', $outName);

        FaoximaResponse::ok(self::buildBrandPayload() + ['message' => 'لوگو ذخیره شد']);
    }


    private function upsertSetting(string $name, string $value): void
    {
        try {
            $pdo = FaoximaDb::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v)
                  ON DUPLICATE KEY UPDATE value = VALUES(value)'
            );
            $stmt->execute([':n' => $name, ':v' => $value]);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('brand upsertSetting failed', ['err' => $e->getMessage(), 'name' => $name]);
            FaoximaResponse::fail(500, 'cannot save brand setting');
        }
    }


    private function deleteOldLogo(): void
    {
        $old = trim(self::readSetting('brand_logo'));
        if ($old === '') return;
        $oldPath = __DIR__ . '/../../app/assets/branding/' . basename($old);
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
