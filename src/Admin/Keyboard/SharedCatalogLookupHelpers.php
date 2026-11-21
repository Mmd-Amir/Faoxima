<?php

namespace Faoxima\Admin\Keyboard;

use PDO;

final class SharedCatalogLookupHelpers
{
    public static function rxProductLookupTokens($rawToken)
{
    $tokens = [];
    $addToken = static function ($value) use (&$tokens) {
        if ($value === null) return;
        $value = trim((string) $value);
        if ($value === '') {
            $tokens[''] = '';
            return;
        }
        $decodedHtml = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decodedUrl = rawurldecode($decodedHtml);
        foreach ([$value, $decodedHtml, $decodedUrl] as $candidate) {
            $candidate = trim((string) $candidate);
            foreach (['prodcutservice_', 'prodcutservices_', 'prodcutserviceom_', 'prodcutservicesom_'] as $prefix) {
                if (strpos($candidate, $prefix) === 0) {
                    $candidate = substr($candidate, strlen($prefix));
                    break;
                }
            }
            $tokens[$candidate] = $candidate;
        }
    };

    $addToken($rawToken);
    if (method_exists(\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_resolveInlineButtonText')) {
        $resolved = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_resolveInlineButtonText((string) $rawToken);
        if ($resolved !== null) {
            $addToken($resolved);
        }
    }

    return array_values($tokens);
}

    public static function rxResolveProductForPanel($productToken, $panelName, $agent = null, $category = null, $serviceTime = null)
{
    global $pdo;

    if (!($pdo instanceof PDO)) return false;
    $panelName = trim((string) $panelName);
    if ($panelName === '') return false;
    $agent = $agent === null ? null : trim((string) $agent);
    $tokens = self::rxProductLookupTokens($productToken);

    $queryProduct = static function ($extraSql, array $extraParams = []) use ($pdo, $panelName, $agent, $category, $serviceTime) {
        $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:rx_location_where, Location) > 0 OR Location = '/all')";
        $params = [
            ':rx_location_where' => $panelName,
            ':rx_location_order' => $panelName,
        ];

        if ($agent !== null && $agent !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_agent_where, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $params[':rx_agent_where'] = $agent;
            $params[':rx_agent_order'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_category, category) > 0 OR FIND_IN_SET(:rx_category_id, category) > 0)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }

        $sql .= ' ' . $extraSql;
        $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:rx_location_order, Location) > 0 THEN 0 ELSE 1 END";
        if ($agent !== null && $agent !== '') {
            $sql .= ", CASE WHEN agent = :rx_agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END";
        }
        $sql .= ", id ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params + $extraParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    };

    foreach ($tokens as $token) {
        if (preg_match('/^pid:(\d+)$/', (string) $token, $m)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $m[1]]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (method_exists(\Faoxima\Orders\Catalog\CatalogLookups::class, 'nmProductByCodeForPanel')) {
            $row = \Faoxima\Orders\Catalog\CatalogLookups::nmProductByCodeForPanel($token, $panelName, $agent);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $row = $queryProduct('AND code_product = :rx_code_product', [':rx_code_product' => $token]);
        if ($row) return $row;
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '') continue;
        if (ctype_digit($token)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $token]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (method_exists(\Faoxima\Orders\Catalog\CatalogLookups::class, 'nmProductByNameForPanel')) {
            $row = \Faoxima\Orders\Catalog\CatalogLookups::nmProductByNameForPanel($token, $panelName, $agent, $category);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $cleanName = preg_replace('/\s*-\s*[0-9,\.]+\s*تومان\s*$/u', '', $token);
        foreach (array_unique([$token, trim((string) $cleanName)]) as $nameCandidate) {
            if ($nameCandidate === '') continue;
            $row = $queryProduct('AND name_product = :rx_name_product', [':rx_name_product' => $nameCandidate]);
            if ($row) return $row;
        }
    }

    $nonEmptyTokens = array_filter($tokens, static function ($token) { return trim((string) $token) !== ''; });
    if (empty($nonEmptyTokens)) {
        $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:rx_location_where, Location) > 0 OR Location = '/all')";
        $params = [':rx_location_where' => $panelName];
        if ($agent !== null && $agent !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_agent_where, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $params[':rx_agent_where'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_category, category) > 0 OR FIND_IN_SET(:rx_category_id, category) > 0)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }
        $sql .= " ORDER BY id ASC LIMIT 2";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 1) return $rows[0];
    }

    return false;
}
}
