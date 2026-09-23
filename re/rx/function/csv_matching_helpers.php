<?php

if (!function_exists('nmBuildFindInSetClause')) {
function nmBuildFindInSetClause(string $column, array $candidates, string $paramPrefix): array
{
    $candidates = array_values(array_unique(array_filter(array_map('strval', $candidates), function ($v) {
        return trim($v) !== '';
    })));
    if (!$candidates) {
        return ['', []];
    }
    $parts = [];
    $params = [];
    foreach ($candidates as $i => $value) {
        $key = ":{$paramPrefix}{$i}";
        $parts[] = "FIND_IN_SET({$key}, {$column}) > 0";
        $params[$key] = $value;
    }
    return ['(' . implode(' OR ', $parts) . ')', $params];
}
}

if (!function_exists('nmProductCategoryList')) {
function nmProductCategoryList(array $product): array
{
    $raw = (string) ($product['category'] ?? '');
    return array_values(array_filter(array_map('trim', explode(',', $raw)), function ($v) {
        return $v !== '';
    }));
}
}

if (!function_exists('rxLocationCsvReplaceToken')) {
function rxLocationCsvReplaceToken(string $csv, string $oldName, ?string $newName, ?callable $equals = null): string
{
    $tokens = [];
    foreach (explode(',', $csv) as $token) {
        $bare = trim($token);
        if ($bare === $oldName || ($equals !== null && $bare !== '' && $equals($bare, $oldName))) {
            if ($newName === null) {
                continue;
            }
            $token = $newName;
        }
        if (!in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }
    }
    return implode(',', $tokens);
}
}

if (!function_exists('rxPanelNameCascade')) {
function rxPanelNameCascade(PDO $pdo, string $oldName, ?string $newName): bool
{
    if ($oldName === '' || $oldName === '/all' || $newName === '' || $newName === '/all') {
        return false;
    }
    $run = function (string $sql, array $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        if ($stmt === false || $stmt->execute($params) === false) {
            throw new RuntimeException('panel cascade query failed: ' . $sql);
        }
        return $stmt;
    };
    $optionalRefs = [];
    if ($newName !== null) {
        foreach ([['remnawave_users', 'name_panel'], ['queued_renewal', 'name_panel'], ['sale_ledger', 'Service_location']] as $ref) {
            try {
                $exists = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
                $exists->execute([$ref[0], $ref[1]]);
                if ($exists->fetchColumn() !== false) {
                    $optionalRefs[] = $ref;
                }
            } catch (Throwable $e) {
                error_log('[rxPanelNameCascade] ' . $e->getMessage());
            }
        }
    }
    $equals = null;
    try {
        $collStmt = $pdo->prepare("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marzban_panel' AND COLUMN_NAME = 'name_panel' LIMIT 1");
        $collStmt->execute();
        $collation = (string) $collStmt->fetchColumn();
        if (preg_match('/^utf8mb4_[a-z0-9_]+$/', $collation)) {
            $eqStmt = $pdo->prepare("SELECT CONVERT(? USING utf8mb4) COLLATE {$collation} = CONVERT(? USING utf8mb4) COLLATE {$collation}");
            $equals = function (string $a, string $b) use ($eqStmt) {
                try {
                    $eqStmt->execute([$a, $b]);
                    $result = (int) $eqStmt->fetchColumn();
                    $eqStmt->closeCursor();
                    return $result === 1;
                } catch (Throwable $e) {
                    return false;
                }
            };
        }
    } catch (Throwable $e) {
        error_log('[rxPanelNameCascade] ' . $e->getMessage());
    }
    $hasBotsazHide = rxTableColumnPresent($pdo, 'botsaz', 'hide_panel');
    $ownsTransaction = !$pdo->inTransaction();
    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        if ($hasBotsazHide) {
            $bots = $run("SELECT id_user, hide_panel FROM botsaz FOR UPDATE", [])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bots as $bot) {
                $list = json_decode((string) $bot['hide_panel'], true);
                if (!is_array($list) || !$list) {
                    continue;
                }
                $nextList = [];
                $changed = false;
                foreach ($list as $item) {
                    if (is_string($item) && (trim($item) === $oldName || ($equals !== null && trim($item) !== '' && $equals(trim($item), $oldName)))) {
                        $changed = true;
                        if ($newName === null) {
                            continue;
                        }
                        $item = $newName;
                    }
                    if (!in_array($item, $nextList, true)) {
                        $nextList[] = $item;
                    }
                }
                if ($changed) {
                    $run("UPDATE botsaz SET hide_panel = ? WHERE id_user = ?", [json_encode(array_values($nextList)), $bot['id_user']]);
                }
            }
        }
        if ($newName === null) {
            $run("DELETE FROM marzban_panel WHERE name_panel = ?", [$oldName]);
        } else {
            $run("UPDATE marzban_panel SET name_panel = ? WHERE name_panel = ?", [$newName, $oldName]);
            $run("UPDATE invoice SET Service_location = ? WHERE Service_location = ?", [$newName, $oldName]);
            foreach ($optionalRefs as $ref) {
                $run("UPDATE `{$ref[0]}` SET `{$ref[1]}` = ? WHERE `{$ref[1]}` = ?", [$newName, $oldName]);
            }
        }
        $like = '%' . addcslashes($oldName, '%_\\') . '%';
        $rows = $run("SELECT id, Location FROM product WHERE Location LIKE ? OR FIND_IN_SET(?, Location) > 0 FOR UPDATE", [$like, $oldName])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $current = (string) $row['Location'];
            $next = rxLocationCsvReplaceToken($current, $oldName, $newName, $equals);
            if ($next === $current || $next === '') {
                continue;
            }
            $run("UPDATE product SET Location = ? WHERE id = ?", [$next, $row['id']]);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[rxPanelNameCascade] ' . $e->getMessage());
        return false;
    }
    $touched = ['marzban_panel', 'product'];
    if ($hasBotsazHide) {
        $touched[] = 'botsaz';
    }
    if ($newName !== null) {
        $touched[] = 'invoice';
        foreach ($optionalRefs as $ref) {
            $touched[] = $ref[0];
        }
    }
    foreach ($touched as $table) {
        if (function_exists('clearSelectCache')) {
            clearSelectCache($table);
        }
        if (function_exists('faoxima_bust_bot_selectcache')) {
            faoxima_bust_bot_selectcache($table);
        }
    }
    return true;
}
}

if (!function_exists('rxTableColumnPresent')) {
function rxTableColumnPresent(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        error_log('[rxTableColumnPresent] ' . $e->getMessage());
        return false;
    }
}
}

if (!function_exists('rxCategoryRenameCascade')) {
function rxCategoryRenameCascade(PDO $pdo, string $oldName, string $newName): bool
{
    $newName = trim($newName);
    if ($oldName === '' || $newName === '' || $oldName === $newName || $oldName === 'all' || $newName === 'all' || strpos($newName, ',') !== false) {
        return false;
    }
    $run = function (string $sql, array $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        if ($stmt === false || $stmt->execute($params) === false) {
            throw new RuntimeException('category cascade query failed: ' . $sql);
        }
        return $stmt;
    };
    $equals = 'rxCategoryNameEquals';
    $hasDiscountCategory = rxTableColumnPresent($pdo, 'DiscountSell', 'code_category');
    $ownsTransaction = !$pdo->inTransaction();
    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        foreach ($run("SELECT remark FROM category WHERE remark <> ? FOR UPDATE", [$oldName])->fetchAll(PDO::FETCH_COLUMN) as $existingRemark) {
            if ($equals((string) $existingRemark, $newName)) {
                throw new RuntimeException('category name already exists: ' . $newName);
            }
        }
        if ($run("UPDATE category SET remark = ? WHERE remark = ?", [$newName, $oldName])->rowCount() < 1) {
            throw new RuntimeException('category not found: ' . $oldName);
        }
        $targets = [['product', 'id', 'category']];
        if ($hasDiscountCategory) {
            $targets[] = ['DiscountSell', 'id', 'code_category'];
        }
        foreach ($targets as [$table, $key, $column]) {
            $rows = $run("SELECT `{$key}` AS k, `{$column}` AS v FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` <> '' FOR UPDATE", [])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $current = (string) $row['v'];
                $next = rxLocationCsvReplaceToken($current, $oldName, $newName, $equals);
                if ($next === $current || $next === '') {
                    continue;
                }
                $run("UPDATE `{$table}` SET `{$column}` = ? WHERE `{$key}` = ?", [$next, $row['k']]);
            }
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[rxCategoryRenameCascade] ' . $e->getMessage());
        return false;
    }
    foreach (['category', 'product', 'DiscountSell'] as $table) {
        if (function_exists('clearSelectCache')) {
            clearSelectCache($table);
        }
        if (function_exists('faoxima_bust_bot_selectcache')) {
            faoxima_bust_bot_selectcache($table);
        }
    }
    return true;
}
}

if (!function_exists('rxCategoryNameEquals')) {
function rxCategoryNameEquals(string $a, string $b): bool
{
    if (function_exists('nmNormalizeText')) {
        return nmNormalizeText($a) === nmNormalizeText($b);
    }
    return trim($a) === trim($b);
}
}

if (!function_exists('rxCategoryDeleteGuarded')) {
function rxCategoryDeleteGuarded(PDO $pdo, string $name, ?int $id = null): bool
{
    if (trim($name) === '') {
        return false;
    }
    $run = function (string $sql, array $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        if ($stmt === false || $stmt->execute($params) === false) {
            throw new RuntimeException('category delete query failed: ' . $sql);
        }
        return $stmt;
    };
    $ownsTransaction = !$pdo->inTransaction();
    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $ids = $id !== null
            ? $run("SELECT id FROM category WHERE id = ? AND remark = ? FOR UPDATE", [$id, $name])->fetchAll(PDO::FETCH_COLUMN)
            : $run("SELECT id FROM category WHERE remark = ? FOR UPDATE", [$name])->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            throw new RuntimeException('category not found: ' . $name);
        }
        $idTokens = array_map('strval', $ids);
        $productCategories = $run("SELECT category FROM product WHERE category IS NOT NULL AND category <> '' LOCK IN SHARE MODE", [])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($productCategories as $csv) {
            foreach (explode(',', (string) $csv) as $token) {
                $token = trim($token);
                if ($token !== '' && (rxCategoryNameEquals($token, $name) || in_array($token, $idTokens, true))) {
                    throw new RuntimeException('category has dependent products: ' . $name);
                }
            }
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $run("DELETE FROM category WHERE id IN ({$placeholders})", $ids);
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[rxCategoryDeleteGuarded] ' . $e->getMessage());
        return false;
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('category');
    }
    if (function_exists('faoxima_bust_bot_selectcache')) {
        faoxima_bust_bot_selectcache('category');
    }
    return true;
}
}
