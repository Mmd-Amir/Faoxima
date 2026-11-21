<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $fieldHints, $fields, $key, $label, $query, $row, $stmt, $values.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class MiscsettingsRequest
{
    public static function handle(
        &$fieldHints,
        &$fields,
        &$key,
        &$label,
        $pdo,
        &$query,
        &$row,
        &$stmt,
        &$values
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
        $query->execute([':username' => $_SESSION['user'] ?? '']);
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION['user']) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $fields = [
            'receipt_topic_reporting' => 'ارسال رسید کارت‌به‌کارت به تاپیک گزارش',
            'subscription_link_button' => 'دکمه دریافت لینک',
        ];

        $fieldHints = [
            'receipt_topic_reporting' => 'ارسال رسیدهای کارت‌به‌کارت به تاپیک گزارش را فعال می‌کند.',
            'subscription_link_button' => 'نمایش دکمه دریافت لینک سرویس در ربات را فعال می‌کند.',
        ];

        $values = array_fill_keys(array_keys($fields), '1');
        $row = $pdo->query('SELECT receipt_topic_reporting, subscription_link_button FROM setting LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach ($values as $key => $default) {
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    $values[$key] = (string)$row[$key];
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $incoming = (string)($_POST['_csrf'] ?? '');
            if (!hash_equals($_SESSION['csrf_token'], $incoming)) {
                http_response_code(403);
                exit('درخواست نامعتبر — توکن CSRF اشتباه است');
            }
            $newValues = [];
            foreach ($fields as $key => $label) {
                $newValues[$key] = isset($_POST['f_' . $key]) ? '1' : '0';
            }
            $stmt = $pdo->prepare('UPDATE setting SET receipt_topic_reporting = :receipt, subscription_link_button = :subscription');
            $stmt->execute([
                ':receipt' => $newValues['receipt_topic_reporting'],
                ':subscription' => $newValues['subscription_link_button'],
            ]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'faoxima_bust_bot_selectcache')) {
                \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('setting');
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('setting');
            }
            header('Location: miscsettings.php?saved=1');
            exit;
        }



        return true;
    }
}
