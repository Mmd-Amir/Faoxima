<?php

namespace Faoxima\Orders\Catalog;

use PDO;
use Throwable;

final class Products
{
    public static function nmResolvePanelFromUserState(array $user = null, $fallbackPanelName = null)
    {
        $candidates = [];
        if ($user) {
            foreach (['Processing_value', 'Processing_value_one', 'Processing_value_tow', 'Processing_value_four'] as $stateField) {
                if (!array_key_exists($stateField, $user)) {
                    continue;
                }
                $state = \Faoxima\Orders\Catalog\CatalogLookups::nmDecodeState($user[$stateField] ?? '');
                foreach (['namepanel', 'name_panel', 'panel', 'panel_name', 'code_panel', 'codepanel', 'stock_codepanel', 'source_codepanel'] as $key) {
                    if (!empty($state[$key])) {
                        $candidates[] = trim((string)$state[$key]);
                    }
                }

                if (!is_array($user[$stateField])) {
                    $raw = trim((string)$user[$stateField]);
                    if ($raw !== '' && $raw !== '0' && $raw !== 'none' && $raw[0] !== '{' && $raw[0] !== '[') {
                        $candidates[] = $raw;
                    }
                }
            }
        }
        if ($fallbackPanelName !== null && trim((string)$fallbackPanelName) !== '') {
            $candidates[] = trim((string)$fallbackPanelName);
        }
        foreach (array_unique(array_filter($candidates)) as $candidate) {
            try {
                $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $candidate, 'select');
                if ($panel) return $panel;
                $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', $candidate, 'select');
                if ($panel) return $panel;
                if (ctype_digit((string)$candidate)) {
                    $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'id', $candidate, 'select');
                    if ($panel) return $panel;
                }
            } catch (Throwable $e) {}
        }
        return false;
        }

    public static function nmResolvePanelNameForUser(array $user = null, $fallback = null)
    {
        if ($user) {
            foreach (['Processing_value', 'Processing_value_one', 'Processing_value_tow', 'Processing_value_four'] as $stateField) {
                if (!array_key_exists($stateField, $user)) continue;
                $raw = $user[$stateField];
                if (is_string($raw)) {
                    $trim = trim($raw);
                    if ($trim !== '' && $trim[0] !== '{' && $trim[0] !== '[' && $trim !== '0' && $trim !== 'none') {
                        return $trim;
                    }
                    $state = \Faoxima\Orders\Catalog\CatalogLookups::nmDecodeState($trim);
                    foreach (['namepanel', 'name_panel', 'panel', 'panel_name'] as $key) {
                        if (!empty($state[$key])) return trim((string)$state[$key]);
                    }
                } elseif (is_array($raw)) {
                    foreach (['namepanel', 'name_panel', 'panel', 'panel_name'] as $key) {
                        if (!empty($raw[$key])) return trim((string)$raw[$key]);
                    }
                }
            }
        }
        if ($fallback !== null) {
            $fallback = trim((string)$fallback);
            if ($fallback !== '') return $fallback;
        }
        $resolved = self::nmResolvePanelFromUserState($user, $fallback);
        if (is_array($resolved) && !empty($resolved['name_panel'])) return (string)$resolved['name_panel'];
        return '';
        }

    public static function nmCategoryLookupValues($category)
    {
        $values = [];
        $category = \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($category);
        if ($category === '' || $category === 'بدون دسته‌بندی') {
            return [];
        }

        $values[] = $category;
        $lookupColumns = [];
        foreach (['id', 'remark', 'name', 'title'] as $column) {
            if (method_exists(\Faoxima\Core\Database\Db::class, 'nmTableHasColumn') && \Faoxima\Core\Database\Db::nmTableHasColumn('category', $column)) {
                $lookupColumns[] = $column;
            }
        }

        foreach ($lookupColumns as $column) {
            if ($column === 'id' && !ctype_digit((string)$category)) {
                continue;
            }
            try {
                $row = \Faoxima\Core\Database\Db::select('category', '*', $column, $category, 'select');
                if (is_array($row) && $row) {
                    foreach (['id', 'remark', 'name', 'title'] as $k) {
                        if (array_key_exists($k, $row) && trim((string)$row[$k]) !== '') {
                            $values[] = \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($row[$k]);
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('nmCategoryLookupValues lookup failed on category.' . $column . ': ' . $e->getMessage());
            }
        }

        if (in_array('remark', $lookupColumns, true) && in_array('id', $lookupColumns, true)) {
            try {
                $row = \Faoxima\Core\Database\Db::select('category', '*', 'remark', $category, 'select');
                if (is_array($row) && isset($row['id'])) {
                    $values[] = \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($row['id']);
                }
            } catch (Throwable $e) {}
        }

        return array_values(array_unique(array_filter($values, static function($v) { return trim((string)$v) !== ''; })));
        }

    public static function nmProductCategoryPredicate(array $catValues): array
    {
        if (!$catValues) {
            return ['', []];
        }

        [$catSql, $params] = \Faoxima\Orders\Catalog\NameCascades::nmBuildFindInSetClause('category', $catValues, 'cat');
        if ($catSql === '') {
            return ['', []];
        }

        if (\Faoxima\Core\Database\Db::nmTableHasColumn('product', 'category_id')) {
            $inId = [];
            foreach ($catValues as $j => $value) {
                $key = ':cat_id' . $j;
                $inId[] = $key;
                $params[$key] = $value;
            }
            $catSql .= ' OR category_id IN (' . implode(',', $inId) . ')';
        }

        return [" AND ({$catSql})", $params];
        }

    public static function nmProductsForPanelCategory($runtimePdo, array $panel, $agent = 'all', $category = null)
    {
        $pdo = $runtimePdo;
        $loc = trim((string)($panel['name_panel'] ?? ''));
        if ($loc === '' || !isset($pdo)) return [];

        $agent = trim((string)($agent ?? ''));
        $filterAgent = !in_array($agent, ['', 'all', '*', 'any'], true);
        $params = [
            ':loc_where' => $loc,
            ':loc_order' => $loc,
        ];
        $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:loc_where, Location) > 0 OR Location = '/all')";
        if ($filterAgent) {
            $sql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $params[':agent'] = $agent;
        }

        $catValues = self::nmCategoryLookupValues($category);
        [$catSql, $catParams] = self::nmProductCategoryPredicate($catValues);
        $sql .= $catSql;
        $params += $catParams;
        $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:loc_order, Location) > 0 THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!$rows && $catValues) {
                $fallbackParams = [
                    ':fallback_loc_where' => $loc,
                    ':fallback_loc_order' => $loc,
                ];
                $fallbackSql = "SELECT * FROM product WHERE (FIND_IN_SET(:fallback_loc_where, Location) > 0 OR Location = '/all')";
                if ($filterAgent) {
                    $fallbackSql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                    $fallbackParams[':agent'] = $agent;
                }
                $fallbackSql .= " ORDER BY CASE WHEN FIND_IN_SET(:fallback_loc_order, Location) > 0 THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
                $stmt = $pdo->prepare($fallbackSql);
                $stmt->execute($fallbackParams);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            return $rows;
        } catch (Throwable $e) {
            error_log('nmProductsForPanelCategory failed: ' . $e->getMessage());
            return [];
        }
        }

    public static function nmProductByCodeForPanel($runtimePdo, $codeProduct, $panelName, $agent = null)
    {
        $pdo = $runtimePdo;

        $codeProduct = trim((string) $codeProduct);
        $panelName = trim((string) $panelName);
        $agent = $agent === null ? null : trim((string) $agent);

        if (!($pdo instanceof PDO) || $codeProduct === '' || $panelName === '') {
            return false;
        }

        try {
            $sql = "SELECT * FROM product
                WHERE code_product = :code_product
                  AND (FIND_IN_SET(:location_where, Location) > 0 OR Location = '/all')";
            $params = [
                ':code_product' => $codeProduct,
                ':location_where' => $panelName,
                ':location_order' => $panelName,
            ];

            if ($agent !== null && $agent !== '') {
                $sql .= " AND (FIND_IN_SET(:agent_where, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $params[':agent_where'] = $agent;
                $params[':agent_order'] = $agent;
                $sql .= " ORDER BY
                        CASE WHEN FIND_IN_SET(:location_order, Location) > 0 THEN 0 ELSE 1 END,
                        CASE WHEN agent = :agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END,
                        id ASC
                      LIMIT 1";
            } else {
                $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:location_order, Location) > 0 THEN 0 ELSE 1 END, id ASC LIMIT 1";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: false;
        } catch (Throwable $e) {
            error_log('nmProductByCodeForPanel failed: ' . $e->getMessage());
            return false;
        }
        }

    public static function nmProductByNameForPanel($runtimePdo, $productName, $panelName, $agent = null, $category = null)
    {
        $pdo = $runtimePdo;
        try {
            $productName = \Faoxima\Orders\Catalog\CatalogLookups::nmProductButtonName($productName);
            $panelName = trim((string)$panelName);
            $params = [
                ':name_product' => $productName,
                ':location_where' => $panelName,
                ':location_order' => $panelName,
            ];
            $sql = "SELECT * FROM product WHERE name_product = :name_product AND (FIND_IN_SET(:location_where, Location) > 0 OR Location = '/all')";
            $catValues = self::nmCategoryLookupValues($category);
            [$catSql, $catParams] = self::nmProductCategoryPredicate($catValues);
            $sql .= $catSql;
            $params += $catParams;
            $agent = trim((string)($agent ?? ''));
            if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
                $sql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $params[':agent'] = $agent;
            }
            $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:location_order, Location) > 0 THEN 0 ELSE 1 END LIMIT 1";
            $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) return $row;

            $rows = self::nmProductsForPanelCategory($runtimePdo, ['name_panel' => $panelName], $agent ?: 'all', $category);
            foreach ($rows as $r) if (\Faoxima\Telegram\Support\AdminInput::nmNormalizeText($r['name_product'] ?? '') === $productName) return $r;
            foreach ($rows as $r) { $name = \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($r['name_product'] ?? ''); if ($name !== '' && (mb_strpos($productName, $name) !== false || mb_strpos($name, $productName) !== false)) return $r; }
            if (count($rows) === 1) return $rows[0];
            return false;
        } catch (Throwable $e) {
            error_log('nmProductByNameForPanel failed: ' . $e->getMessage());
            return false;
        }
        }
}
