<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $new, $pdo.
 * Inherited or exported by reference: $FIELDS, $_csrf, $cur, $f, $row, $savedNum, $settingRow, $showSaved, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TrustChannelRequest
{
    public static function handle(
        &$FIELDS,
        &$_csrf,
        &$cur,
        &$f,
        $new,
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

        $FIELDS = [
            ['type' => 'toggle', 'col' => 'PublicLog_Status',        'label' => 'فعال‌سازی گزارش عمومی خرید', 'on' => '1', 'off' => '0'],
            ['type' => 'toggle', 'col' => 'PublicLog_NewSub',        'label' => 'خرید اشتراک جدید',            'on' => '1', 'off' => '0'],
            ['type' => 'toggle', 'col' => 'PublicLog_Renewal',       'label' => 'تمدید سرویس',                 'on' => '1', 'off' => '0'],
            ['type' => 'toggle', 'col' => 'PublicLog_VolumeTopup',   'label' => 'افزایش حجم',                  'on' => '1', 'off' => '0'],
            ['type' => 'toggle', 'col' => 'PublicLog_TimeExtra',     'label' => 'افزایش زمان',                 'on' => '1', 'off' => '0'],
            ['type' => 'toggle', 'col' => 'PublicLog_WalletDeposit', 'label' => 'شارژ کیف پول',                'on' => '1', 'off' => '0'],
            ['type' => 'text',   'col' => 'PublicLog_Channel',       'label' => 'آیدی کانال اعتماد',           'placeholder' => '@channel یا -100…'],
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
            error_log('[panel/trust_channel] load failed: ' . $e->getMessage());
        }

        $savedCount = 0;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
            foreach ($FIELDS as $f) {
                $col = $f['col'];
                $cur = $settingRow[$col] ?? null;
                if ($f['type'] === 'toggle') {
                    $new = isset($_POST['f_' . $col]) ? $f['on'] : $f['off'];
                } else {
                    $new = (string)($_POST['f_' . $col] ?? '');
                    if (mb_strlen($new) > 500) {
                        $new = mb_substr($new, 0, 500);
                    }
                }
                if ((string)$cur !== (string)$new) {
                    try {
                        $sanCol = preg_replace('/[^A-Za-z0-9_]/', '', $col);
                        $upd = $pdo->prepare("UPDATE setting SET `{$sanCol}` = :v");
                        $upd->bindValue(':v', $new, PDO::PARAM_STR);
                        $upd->execute();
                        $savedCount++;
                    } catch (\Throwable $e) {
                        error_log('[panel/trust_channel] ' . $col . ' failed: ' . $e->getMessage());
                    }
                }
            }
            header('Location: trust_channel.php?saved=' . $savedCount);
            exit;
        }

        $showSaved = isset($_GET['saved']);
        $savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;



        return true;
    }
}
