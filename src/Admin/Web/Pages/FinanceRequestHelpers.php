<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class FinanceRequestHelpers
{
    public static function fx_finance_posted_setting(array $post, array $field, string $key, string $fieldType): ?string
{
    $name = 'f_' . $key;
    if ($fieldType === 'toggle') {
        return isset($post[$name]) ? $field['on'] : $field['off'];
    }
    if (!array_key_exists($name, $post)) {
        return null;
    }
    $value = (string)$post[$name];
    return $fieldType === 'secret' && $value === '' ? null : $value;
}

    public static function fx_finance_save_settings(PDO $pdo, array $groups, array $post, array $paySettings, array $textbotRows): array
{
    $savedCount = 0;
    $edgeRejectedCount = 0;
    foreach ($groups as $group) {
        foreach ($group['fields'] as $f) {
            $key = $f['name'];
            $fieldType = $f['type'] ?? 'text';


            if ($fieldType === 'textbot') {
                if (!array_key_exists('f_' . $key, $post)) continue;
                $new = (string)$post['f_' . $key];
                $cur = $textbotRows[$key] ?? '';
                if ((string)$cur === $new) continue;
                if (\Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_edge_violation($key, $new) !== '') {
                    $edgeRejectedCount++;
                    continue;
                }
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO textbot (id_text, text) VALUES (:k, :v)
                         ON DUPLICATE KEY UPDATE text = VALUES(text)"
                    );
                    $stmt->execute([':k' => $key, ':v' => $new]);
                    $savedCount++;
                    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'faoxima_bust_bot_selectcache')) {
                        \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('textbot');
                    }
                    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                        \Faoxima\Core\Database\SelectCache::clearSelectCache('textbot');
                    }
                } catch (\Throwable $e) {
                    error_log('[panel/finance] save textbot ' . $key . ' failed: ' . $e->getMessage());
                }
                continue;
            }


            $cur = $paySettings[$key] ?? '';
            $new = self::fx_finance_posted_setting($post, $f, $key, $fieldType);
            if ($new === null) continue;

            if ((string)$cur === (string)$new) continue;

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO PaySetting (NamePay, ValuePay) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE ValuePay = VALUES(ValuePay)"
                );
                $stmt->execute([':k' => $key, ':v' => $new]);
                $savedCount++;
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'faoxima_bust_bot_selectcache')) {
                    \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('PaySetting');
                }
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('PaySetting');
                }
            } catch (\Throwable $e) {
                error_log('[panel/finance] save ' . $key . ' failed: ' . $e->getMessage());
            }
        }
    }
    return [$savedCount, $edgeRejectedCount];
}

    public static function faoxima_fin_is_on($cur, $on, $off) {
    if ((string)$cur === (string)$on)  return true;
    if ((string)$cur === (string)$off) return false;
    return in_array(strtolower(trim((string)$cur)), ['1','on','true','yes'], true);
}

    public static function faoxima_fin_mask_secret($v) {
    $v = (string)$v;
    $n = strlen($v);
    if ($n === 0) return '';
    if ($n <= 4) return str_repeat('•', $n);
    return '••••' . substr($v, -4);
}
}
