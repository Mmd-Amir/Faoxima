<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ShopsettingsRequestHelpers
{
    public static function fx_shop_save_settings(PDO $pdo, array $groups, array $post, array $shopRows): int
{
    $savedCount = 0;
    foreach ($groups as $group) {
        foreach ($group['fields'] as $f) {
            $key = $f['name'];
            $cur = $shopRows[$key] ?? '';

            if ($f['type'] === 'toggle') {
                $new = isset($post['f_' . $key]) ? $f['on'] : $f['off'];
            } else {
                if (!array_key_exists('f_' . $key, $post)) continue;
                $new = (string)$post['f_' . $key];
            }

            if ((string)$cur === (string)$new) continue;
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO shopSetting (Namevalue, value) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)"
                );
                $stmt->execute([':k' => $key, ':v' => $new]);
                $savedCount++;
            } catch (\Throwable $e) {
                error_log('[panel/shopsettings] save ' . $key . ' failed: ' . $e->getMessage());
            }
        }
    }
    return $savedCount;
}

    public static function faoxima_shop_is_on($cur, $on, $off) {
    if ((string)$cur === (string)$on)  return true;
    if ((string)$cur === (string)$off) return false;
    return in_array(strtolower(trim((string)$cur)), ['1','on','true','yes'], true);
}
}
