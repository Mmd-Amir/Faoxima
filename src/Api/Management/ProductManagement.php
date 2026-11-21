<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class ProductManagement
{
    public static function dispatch(PDO $pdo, string $method, array $data): void
    {
        switch ($data['actions'] ?? '') {
            case 'products':
                self::products($pdo, $method, $data);
                return;
            case 'product':
                self::product($method, $data);
                return;
            case 'product_add':
                self::productAdd($pdo, $method, $data);
                return;
            case 'product_edit':
                self::productEdit($pdo, $method, $data);
                return;
            case 'product_delete':
                self::productDelete($pdo, $method, $data);
                return;
            case 'set_inbounds':
                self::setInbounds($pdo, $method, $data);
                return;
            case 'remove_inbounds':
                self::removeInbounds($pdo, $method, $data);
                return;
            default:
                EndpointSupport::sendJsonResponse(false, "Action Invalid");
                return;
        }
    }

    private static function products(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        $limit = 50;
        if (isset($data['limit']) && is_numeric($data['limit']))
            $limit = min(max((int) $data['limit'], 1), 1000);


        $page = isset($data['page']) && is_numeric($data['page']) ? max((int) $data['page'], 1) : 1;
        $offset = ($page - 1) * $limit;
        $q = isset($data['q']) ? $data['q'] : '';

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM product WHERE (id LIKE :id_product OR name_product LIKE :name_product)");
            $search = "%$q%";
            $stmt->bindParam(':id_product', $search, PDO::PARAM_STR);
            $stmt->bindParam(':name_product', $search, PDO::PARAM_STR);
            $stmt->execute();
            $totalproduct = (int) $stmt->fetchColumn();
            $totalPages = ceil($totalproduct / $limit);
            $query = "SELECT * FROM product WHERE (id  LIKE CONCAT('%', :id_product, '%') OR name_product  LIKE CONCAT('%', :name_product, '%')) ORDER BY (position = 0) ASC, position ASC, id ASC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':name_product', $q, PDO::PARAM_STR);
            $stmt->bindValue(':id_product', $q, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "name_panel,code_panel", "status", "active", "fetchAll");
            $category = \Faoxima\Core\Database\Db::select("category", "*", null, null, "fetchAll");
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'products' => $products,
                'panels' => $panel,
                'category' => $category,
                'pagination' => [
                    'total_product' => $totalproduct,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $limit
                ]
            ]);

        } catch (Exception $e) {
            error_log("Database error in product: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function product(string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            EndpointSupport::sendJsonResponse(false, "id empty", []);
        }

        try {
            $prodcut = \Faoxima\Core\Database\Db::select("product", "*", "id", $data['id'], "select");
            if (!$prodcut) {
                EndpointSupport::sendJsonResponse(true, "Successful", [
                    'product' => [],
                ]);
            }
            $count_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "name_product", $prodcut['name_product'], "count");
            $sum_invoice = \Faoxima\Core\Database\Db::select("invoice", "SUM(price_product) as sum_price", "name_product", $prodcut['name_product'], "select");
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "name_panel,code_panel", "status", "active", "fetchAll");
            $category = \Faoxima\Core\Database\Db::select("category", "*", null, null, "fetchAll");
            $prodcut['hide_panel'] = json_decode($prodcut['hide_panel'], true);
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'product' => $prodcut,
                'count_invoice' => $count_invoice,
                'sum_invoice' => $sum_invoice['sum_price'] ? $sum_invoice['sum_price'] : 0,
                'panels' => $panel,
                'category' => $category,
            ]);
        } catch (Exception $e) {
            error_log("Database error in product: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function productAdd(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['name', 'price', 'data_limit', 'time', 'location'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $prodcut = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $data['name'], "count");
        if ($prodcut != 0) {
            EndpointSupport::sendJsonResponse(false, "product name exits", [], 200);
        }
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $data['location'], "select");
        if (!$panel & $data['location'] != "/all")
            EndpointSupport::sendJsonResponse(false, "location not found", [], 200);
        $agent = strtolower(trim((string)($data['agent'] ?? 'f')));
        if (!in_array($agent, ['f', 'n', 'n2', 'all'], true)) {
            EndpointSupport::sendJsonResponse(false, "agent invalid", [], 422);
        }
        try {
            $randomString = bin2hex(random_bytes(3));

            $productData = [
                'code_product' => $randomString,
                'name_product' => $data['name'],
                'price_product' => $data['price'],
                'Volume_constraint' => $data['data_limit'],
                'Service_time' => $data['time'],
                'Location' => $panel['name_panel'],
                'agent' => $agent,
                'note' => empty($data['note']) ? "" : $data['note'],
                'data_limit_reset' => empty($data['data_limit_reset']) ? "no_reset" : $data['data_limit_reset'],
                'inbounds' => empty($data['note']) ? null : $data['inbounds'],
                'proxies' => empty($data['proxies']) ? null : $data['proxies'],
                'category' => empty($data['category']) ? null : $data['category'],
                'one_buy_status' => empty($data['one_buy_status']) ? 0 : $data['one_buy_status'],
                'hide_panel' => empty($data['hide_panel']) ? "{}" : $data['hide_panel'],
            ];


            $columns = implode(',', array_keys($productData));
            $placeholders = ':' . implode(', :', array_keys($productData));

            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO product ({$columns}) VALUES ({$placeholders})"
            );

            foreach ($productData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            EndpointSupport::sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in product_add: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while editing product");
        }
    }

    private static function productEdit(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = \Faoxima\Core\Database\Db::select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            EndpointSupport::sendJsonResponse(false, "product not found", [], 200);
        }
        if (isset($data['agent'])) {
            $data['agent'] = strtolower(trim((string)$data['agent']));
            if (!in_array($data['agent'], ['f', 'n', 'n2', 'all'], true)) {
                EndpointSupport::sendJsonResponse(false, "agent invalid", [], 422);
            }
        }
        if (isset($data['name']) && $product['name_product'] != $data['name']) {
            $product_check = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $data['name'], "count");
            if ($product_check != 0)
                EndpointSupport::sendJsonResponse(false, "product name exits", [], 200);
            \Faoxima\Core\Database\Db::update("invoice", "name_product", $data['name'], "name_product", $product['name_product']);
        }

        try {
            $productData = [
                'name_product' => isset($data['name']) ? $data['name'] : $product['name_product'],
                'price_product' => isset($data['price']) ? $data['price'] : $product['price_product'],
                'Volume_constraint' => isset($data['volume']) ? $data['volume'] : $product['Volume_constraint'],
                'Service_time' => isset($data['time']) ? $data['time'] : $product['Service_time'],
                'Location' => isset($data['location']) ? $data['location'] : $product['Location'],
                'agent' => isset($data['agent']) ? $data['agent'] : $product['agent'],
                'note' => isset($data['note']) ? $data['note'] : $product['note'],
                'data_limit_reset' => isset($data['data_limit_reset']) ? $data['data_limit_reset'] : $product['data_limit_reset'],
                'inbounds' => isset($data['inbounds']) ? $data['inbounds'] : $product['inbounds'],
                'proxies' => isset($data['proxies']) ? $data['proxies'] : $product['proxies'],
                'category' => isset($data['category']) ? $data['category'] : $product['category'],
                'one_buy_status' => isset($data['one_buy_status']) ? $data['one_buy_status'] : $product['one_buy_status'],
                'hide_panel' => isset($data['hide_panel']) ? json_encode($data['hide_panel']) : $product['hide_panel'],
            ];
            $setParts = [];
            foreach ($productData as $key => $value) {
                $setParts[] = "{$key} = :{$key}";
            }
            $setClause = implode(", ", $setParts);

            $stmt = $pdo->prepare("UPDATE product SET {$setClause} WHERE id = :id");

            foreach ($productData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);

            $stmt->execute();

            EndpointSupport::sendJsonResponse(true, "product updated successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in product_edit: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while adding product");
        }
    }

    private static function productDelete(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = \Faoxima\Core\Database\Db::select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            EndpointSupport::sendJsonResponse(false, "product not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM product  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            EndpointSupport::sendJsonResponse(true, "product delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in product delete : " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while delete prodcut");
        }
    }

    private static function setInbounds(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id', 'input'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = \Faoxima\Core\Database\Db::select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            EndpointSupport::sendJsonResponse(false, "product not found", [], 200);
        }
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", 'name_panel', $product['Location'], "select");
        if ($panel['type'] == "marzban") {
            if ((string)($panel['version_panel'] ?? '0') === '1') {
                $DataUserOut = \Faoxima\Services\Panels\Drivers\Marzban::getuser($data['input'], $panel['name_panel']);
                if (!empty($DataUserOut['error']))
                    EndpointSupport::sendJsonResponse(false, $DataUserOut['error'], [], 200);
                if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200)
                    EndpointSupport::sendJsonResponse(false, $DataUserOut['msg'], [], 200);
                $DataUserOut = json_decode($DataUserOut['body'], true);
                if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings']))
                    EndpointSupport::sendJsonResponse(false, "User Not Found", [], 200);
                foreach ($DataUserOut['proxy_settings'] as $key => &$value) {
                    if ($key == "shadowsocks") {
                        unset($DataUserOut['proxy_settings'][$key]['password']);
                    } elseif ($key == "trojan") {
                        unset($DataUserOut['proxy_settings'][$key]['password']);
                    } else {
                        unset($DataUserOut['proxy_settings'][$key]['id']);
                    }
                    if (count($DataUserOut['proxy_settings'][$key]) == 0) {
                        $DataUserOut['proxy_settings'][$key] = new stdClass();
                    }
                }
                $datainbound = json_encode($DataUserOut['proxy_settings']);
                $proxy_output = json_encode($DataUserOut['proxy_settings'], true);
            } else {
                $DataUserOut = \Faoxima\Services\Panels\Drivers\Marzban::getuser($data['input'], $panel['name_panel']);
                if (!empty($DataUserOut['error']))
                    EndpointSupport::sendJsonResponse(false, $DataUserOut['error'], [], 200);
                if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200)
                    EndpointSupport::sendJsonResponse(false, $DataUserOut['msg'], [], 200);
                $DataUserOut = json_decode($DataUserOut['body'], true);
                if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                    EndpointSupport::sendJsonResponse(false, "User Not Found", [], 200);
                }
                foreach ($DataUserOut['proxies'] as $key => &$value) {
                    if ($key == "shadowsocks") {
                        unset($DataUserOut['proxies'][$key]['password']);
                    } elseif ($key == "trojan") {
                        unset($DataUserOut['proxies'][$key]['password']);
                    } else {
                        unset($DataUserOut['proxies'][$key]['id']);
                    }
                    if (count($DataUserOut['proxies'][$key]) == 0) {
                        $DataUserOut['proxies'][$key] = new stdClass();
                    }
                }
                $proxy_output = json_encode($DataUserOut['proxies']);
            }
            $stmt = $pdo->prepare("UPDATE product SET proxies = :proxies WHERE id = :id_product");
            $stmt->bindParam(':proxies', $proxy_output);
            $stmt->bindParam(':id_product', $data['id']);
            $stmt->execute();
            $datainbound = json_encode($DataUserOut['inbounds']);
        } elseif ($panel['type'] == "x-ui_single") {
            $user_data = \Faoxima\Services\Panels\Drivers\XuiSingle::get_clinets($data['input'], $panel['name_panel']);
            if (!empty($user_data['error']))
                EndpointSupport::sendJsonResponse(false, $user_data['error'], [], 200);
            if (!empty($user_data['status']) && $user_data['status'] != 200)
                EndpointSupport::sendJsonResponse(false, $user_data['msg'], [], 200);
            $user_data = json_decode($user_data['body'], true)['obj'];
            if ($user_data == null)
                EndpointSupport::sendJsonResponse(false, "User Not Found", [], 200);
            $datainbound = $user_data['inboundId'];
        } else {
            EndpointSupport::sendJsonResponse(false, "panel_not_support_options", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE product SET inbounds = :inbounds WHERE id = :id_product ");
        $stmt->bindParam(':inbounds', $datainbound);
        $stmt->bindParam(':id_product', $data['id']);
        $stmt->execute();
        EndpointSupport::sendJsonResponse(true, "successfully", [], 200);
    }

    private static function removeInbounds(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = \Faoxima\Core\Database\Db::select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            EndpointSupport::sendJsonResponse(false, "product not found", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE product SET inbounds = NULL,proxies = NULL WHERE id = :id_product ");
        $stmt->bindParam(':id_product', $data['id']);
        $stmt->execute();
        EndpointSupport::sendJsonResponse(true, "successfully", [], 200);
    }
}
