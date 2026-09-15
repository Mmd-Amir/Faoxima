<?php


if (!function_exists('nmSellableCategoryRows')) {
function nmSellableCategoryRows($location, $agent)
{
    global $pdo;
    $out = [];
    if (!($pdo instanceof PDO)) return $out;
    $norm = static function ($v) {
        return function_exists('nmNormalizeText') ? nmNormalizeText($v) : trim((string)$v);
    };
    // 1) Collect the (normalised) category tokens that products for this panel use.
    $prodSet = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT category FROM product "
            . "WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') "
            . "AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) "
            . "AND category IS NOT NULL AND TRIM(category) <> ''"
        );
        $stmt->execute([':loc' => $location, ':agent' => $agent]);
        foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $pc) {
            foreach (explode(',', (string) $pc) as $piece) {
                $n = $norm($piece);
                if ($n !== '' && $n !== '0') $prodSet[$n] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('nmSellableCategoryRows products failed: ' . $e->getMessage());
        return $out;
    }
    if (!$prodSet) return $out;
    // 2) Keep the category rows whose remark/id/name/title matches a product token.
    try {
        $stmt = $pdo->query("SELECT * FROM category ORDER BY id ASC");
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
}

function nmHasSellableCategories($location, $agent)
{
    return count(nmSellableCategoryRows($location, $agent)) > 0;
}
