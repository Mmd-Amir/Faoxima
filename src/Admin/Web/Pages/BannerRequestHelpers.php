<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;
use CURLFile;

final class BannerRequestHelpers
{
    public static function faoxima_banner_mint_file_id($tmpPath, $mime, $name)
{
    global $pdo;
    $adminId = '';
    try {
        $stmt = $pdo->query("SELECT id_admin FROM admin ORDER BY id_admin ASC LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $adminId = is_array($row) ? (string)($row['id_admin'] ?? '') : '';
    } catch (\Throwable $e) {
        error_log('[panel/banner] admin lookup failed: ' . $e->getMessage());
    }
    if ($adminId === '') {
        return ['ok' => false, 'error' => 'هیچ ادمینی برای دریافت تصویر آزمایشی یافت نشد.'];
    }
    $cf = new CURLFile($tmpPath, $mime !== '' ? $mime : 'image/jpeg', $name);
    $res = telegram('sendPhoto', [
        'chat_id' => $adminId,
        'photo' => $cf,
    ]);
    if (empty($res['ok'])) {
        return ['ok' => false, 'error' => 'ارسال تصویر به تلگرام ناموفق بود.'];
    }
    $photos = $res['result']['photo'] ?? [];
    $fileId = (is_array($photos) && $photos) ? (end($photos)['file_id'] ?? '') : '';
    $messageId = $res['result']['message_id'] ?? null;
    if ($messageId) {
        deletemessage($adminId, $messageId);
    }
    if ($fileId === '') {
        return ['ok' => false, 'error' => 'دریافت شناسه فایل از تلگرام ناموفق بود.'];
    }
    return ['ok' => true, 'file_id' => $fileId];
}
}
