<?php

namespace Faoxima\Orders\Catalog;

use PDO;
use Throwable;

final class Categories
{
    public static function nmSellableCategoryProductTokens(array $values, callable $normalize): array
    {
        $tokens = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $piece) {
                $token = $normalize($piece);
                if ($token !== '' && $token !== '0') {
                    $tokens[$token] = true;
                }
            }
        }
        return $tokens;
        }

    public static function nmSellableCategoryRows($runtimePdo, $location, $agent)
    {
        $pdo = $runtimePdo;
        $out = [];
        if (!($pdo instanceof PDO)) return $out;
        $norm = static function ($v) {
            return method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'nmNormalizeText') ? \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($v) : trim((string)$v);
        };
        // 1) Collect the (normalised) category tokens that products for this panel use.
        try {
            $stmt = $pdo->prepare(
                "SELECT category FROM product "
                . "WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') "
                . "AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) "
                . "AND category IS NOT NULL AND TRIM(category) <> ''"
            );
            $stmt->execute([':loc' => $location, ':agent' => $agent]);
            $prodSet = self::nmSellableCategoryProductTokens($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [], $norm);
        } catch (Throwable $e) {
            error_log('nmSellableCategoryRows products failed: ' . $e->getMessage());
            return $out;
        }
        if (!$prodSet) return $out;
        // 2) Keep the category rows whose remark/id/name/title matches a product token.
        try {
            $stmt = $pdo->query("SELECT * FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                foreach (['remark', 'id', 'name', 'title'] as $k) {
                    if (!isset($row[$k]) || trim((string)$row[$k]) === '') continue;
                    if (isset($prodSet[$norm($row[$k])])) { $out[] = $row; break; }
                }
            }
        } catch (Throwable $e) {
            error_log('nmSellableCategoryRows categories failed: ' . $e->getMessage());
        }
        return $out;
        }

    public static function nmHasSellableCategories($runtimePdo, $location, $agent)
    {
        return count(self::nmSellableCategoryRows($runtimePdo, $location, $agent)) > 0;
        }
}
