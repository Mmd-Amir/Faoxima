<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $SECTIONS, $_csrf, $formErrors, $key, $meta, $name, $row, $savedNum, $settingRow, $showSaved, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BannerRequest
{
    public static function handle(
        &$SECTIONS,
        &$_csrf,
        &$formErrors,
        &$key,
        &$meta,
        &$name,
        $pdo,
        &$row,
        &$savedNum,
        &$settingRow,
        &$showSaved,
        &$stmt
    ) {
        if (empty($_SESSION['user']) || !is_string($_SESSION['user']) || $_SESSION['user'] === '') {
            header('Location: login.php');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $_csrf = $_SESSION['csrf_token'];

        $SECTIONS = [
            'start' => ['label' => 'استارت ربات (/start)'],
            'cart'  => ['label' => 'کارت به کارت'],
            'buy'   => ['label' => 'خرید اشتراک'],
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $incoming = $_POST['_csrf'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $incoming)) {
                http_response_code(403);
                exit('درخواست نامعتبر — توکن CSRF اشتباه است');
            }
        }

        $settingRow = [];
        try {
            $stmt = $pdo->query("SELECT * FROM setting LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (is_array($row)) $settingRow = $row;
        } catch (\Throwable $e) {
            error_log('[panel/banner] load failed: ' . $e->getMessage());
        }



        $formErrors = [];
        $savedCount = 0;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
            foreach ($SECTIONS as $key => $meta) {
                $statusCol = "banner_{$key}_status";
                $fileCol = "banner_{$key}_file_id";

                $newStatus = isset($_POST['f_' . $statusCol]) ? '1' : '0';
                $newFileId = trim((string)($_POST['f_' . $fileCol] ?? ''));

                $uploadKey = 'upload_' . $key;
                if (isset($_FILES[$uploadKey]) && ($_FILES[$uploadKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tmp = (string)$_FILES[$uploadKey]['tmp_name'];
                    if ($tmp !== '' && is_uploaded_file($tmp)) {
                        $mime = function_exists('mime_content_type') ? (string)(mime_content_type($tmp) ?: '') : (string)($_FILES[$uploadKey]['type'] ?? '');
                        $name = (string)($_FILES[$uploadKey]['name'] ?? 'banner.jpg');
                        $mint = \Faoxima\Admin\Web\Pages\BannerRequestHelpers::faoxima_banner_mint_file_id($tmp, $mime, $name);
                        if ($mint['ok']) {
                            $newFileId = $mint['file_id'];
                        } else {
                            $formErrors[] = "{$meta['label']}: {$mint['error']}";
                        }
                    }
                }

                if (mb_strlen($newFileId) > 255) {
                    $newFileId = mb_substr($newFileId, 0, 255);
                }

                $curStatus = (string)($settingRow[$statusCol] ?? '0');
                $curFileId = (string)($settingRow[$fileCol] ?? '');

                if ($curStatus !== $newStatus) {
                    $statusSql = "UPDATE setting SET `{$statusCol}` = :v";
                    if ($key === 'start' && $newStatus === '1' && array_key_exists('start_media_status', $settingRow)) {
                        $statusSql .= ", `start_media_status` = '0'";
                    }
                    try {
                        $upd = $pdo->prepare($statusSql);
                        $upd->bindValue(':v', $newStatus, PDO::PARAM_STR);
                        $upd->execute();
                        if ($key === 'buy' && $newStatus === '1') {
                            try {
                                $pdo->exec("UPDATE section_media SET media_status = '0' WHERE section_id = 'buy'");
                            } catch (\Throwable $mediaError) {
                                error_log('[panel/banner] buy media exclusion failed: ' . $mediaError->getMessage());
                            }
                        }
                        $savedCount++;
                    } catch (\Throwable $e) {
                        error_log('[panel/banner] ' . $statusCol . ' failed: ' . $e->getMessage());
                    }
                }
                if ($curFileId !== $newFileId) {
                    try {
                        $upd = $pdo->prepare("UPDATE setting SET `{$fileCol}` = :v");
                        $upd->bindValue(':v', $newFileId, PDO::PARAM_STR);
                        $upd->execute();
                        $savedCount++;
                    } catch (\Throwable $e) {
                        error_log('[panel/banner] ' . $fileCol . ' failed: ' . $e->getMessage());
                    }
                }
            }

            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'faoxima_bust_bot_selectcache')) {
                \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('setting');
            }
            if (empty($formErrors)) {
                header('Location: banner.php?saved=' . $savedCount);
                exit;
            }
            $stmt = $pdo->query("SELECT * FROM setting LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (is_array($row)) $settingRow = $row;
        }

        $showSaved = isset($_GET['saved']);
        $savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

        return true;
    }
}
