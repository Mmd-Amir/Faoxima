<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class DiscountManagement
{
    public static function dispatch(PDO $pdo, string $method, array $data): void
    {
        switch ($data['actions'] ?? '') {
            case 'discounts':
                self::discounts($pdo, $method, $data);
                return;
            case 'discount':
                self::discount($method, $data);
                return;
            case 'discount_add':
                self::discountAdd($pdo, $method, $data);
                return;
            case 'discount_delete':
                self::discountDelete($pdo, $method, $data);
                return;
            case 'discount_sell_lists':
                self::discountSellLists($pdo, $method, $data);
                return;
            case 'discount_sell':
                self::discountSell($method, $data);
                return;
            case 'discount_sell_delete':
                self::discountSellDelete($pdo, $method, $data);
                return;
            case 'discount_sell_add':
                self::discountSellAdd($pdo, $method, $data);
                return;
            default:
                EndpointSupport::sendJsonResponse(false, "Action Invalid");
                return;
        }
    }

    private static function discounts(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        $limit = 50;
        if (isset($data['limit']) && is_numeric($data['limit']))
            $limit = min(max((int) $data['limit'], 1), 1000);


        $page = isset($data['page']) && is_numeric($data['page']) ? max((int) $data['page'], 1) : 1;
        $offset = ($page - 1) * $limit;
        $q = isset($data['q']) ? $data['q'] : '';

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Discount WHERE (code LIKE :code_discount)");
            $search = "%$q%";
            $stmt->bindParam(':code_discount', $search, PDO::PARAM_STR);
            $stmt->execute();
            $totalDiscount = (int) $stmt->fetchColumn();
            $totalPages = ceil($totalDiscount / $limit);
            $query = "SELECT * FROM Discount WHERE (code  LIKE CONCAT('%', :code_discount, '%')) ORDER BY id LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':code_discount', $q, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'discount' => $discount,
                'pagination' => [
                    'total_discount' => $totalDiscount,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $limit
                ]
            ]);

        } catch (Exception $e) {
            error_log("Database error in discount: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function discount(string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            EndpointSupport::sendJsonResponse(false, "id empty", []);
        }

        try {
            $discount = \Faoxima\Core\Database\Db::select("Discount", "*", "id", $data['id'], "select");
            if (!$discount) {
                EndpointSupport::sendJsonResponse(true, "Successful", [
                    'discount' => [],
                ]);
            }
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'discount' => $discount,
            ]);
        } catch (Exception $e) {
            error_log("Database error in discount: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function discountAdd(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['code', 'price', 'limit_use'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $discount = \Faoxima\Core\Database\Db::select("Discount", "*", "code", $data['code'], "count");
        if ($discount != 0) {
            EndpointSupport::sendJsonResponse(false, "Discount code exits", [], 200);
        }
        if (!preg_match('/^[A-Za-z\d]+$/', $data['code'])) {
            EndpointSupport::sendJsonResponse(false, "invalid code", [], 200);
        }
        try {

            $productData = [
                'code' => $data['code'],
                'price' => $data['price'],
                'limituse' => $data['limit_use'],
                'limitused' => 0
            ];

            $columns = implode(',', array_keys($productData));
            $placeholders = ':' . implode(', :', array_keys($productData));
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO Discount ({$columns}) VALUES ({$placeholders})"
            );

            foreach ($productData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            EndpointSupport::sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in Discount add: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while editing Discount");
        }
    }

    private static function discountDelete(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = \Faoxima\Core\Database\Db::select("Discount", "*", "id", $data['id'], "select");
        if (!$product) {
            EndpointSupport::sendJsonResponse(false, "Discount not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM Discount  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            EndpointSupport::sendJsonResponse(true, "Discount delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in Discount delete : " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while delete Discount");
        }
    }

    private static function discountSellLists(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        $limit = 50;
        if (isset($data['limit']) && is_numeric($data['limit']))
            $limit = min(max((int) $data['limit'], 1), 1000);


        $page = isset($data['page']) && is_numeric($data['page']) ? max((int) $data['page'], 1) : 1;
        $offset = ($page - 1) * $limit;
        $q = isset($data['q']) ? $data['q'] : '';

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM DiscountSell WHERE (codeDiscount LIKE :code_discount)");
            $search = "%$q%";
            $stmt->bindParam(':code_discount', $search, PDO::PARAM_STR);
            $stmt->execute();
            $totalDiscount = (int) $stmt->fetchColumn();
            $totalPages = ceil($totalDiscount / $limit);
            $query = "SELECT * FROM DiscountSell WHERE (codeDiscount  LIKE CONCAT('%', :code_discount, '%')) ORDER BY id LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':code_discount', $q, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $product = \Faoxima\Core\Database\Db::select("product", "code_product as id,name_product", null, null, "fetchAll");
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "code_panel,name_panel", "status", "active", "fetchAll");
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'discount' => $discount,
                'product' => $product,
                'panel' => $panel,
                'pagination' => [
                    'total_discount' => $totalDiscount,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $limit
                ]
            ]);

        } catch (Exception $e) {
            error_log("Database error in discount: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function discountSell(string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            EndpointSupport::sendJsonResponse(false, "id empty", []);
        }

        try {
            $discount = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "id", $data['id'], "select");
            if($discount['code_product'] != "all")
             $discount['code_product'] = \Faoxima\Core\Database\Db::select('product',"*","code_product",$discount['code_product'],"select")['name_product'];
            if($discount['code_panel'] != "/all")
             $discount['code_panel'] = \Faoxima\Core\Database\Db::select('marzban_panel',"*","code_panel",$discount['code_panel'],"select")['name_panel'];
            if (!$discount) {
                EndpointSupport::sendJsonResponse(true, "Successful", [
                    'discount' => [],
                ]);
            }
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'discount' => $discount,
            ]);
        } catch (Exception $e) {
            error_log("Database error in discount: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function discountSellDelete(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "id", $data['id'], "select");
        if (!$product) {
            EndpointSupport::sendJsonResponse(false, "DiscountSell not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM DiscountSell  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            EndpointSupport::sendJsonResponse(true, "DiscountSell delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in DiscountSell delete : " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while delete DiscountSell");
        }
    }

    private static function discountSellAdd(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['code', 'percent', 'limit_use'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $discount = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $data['code'], "count");
        if ($discount != 0) {
            EndpointSupport::sendJsonResponse(false, "Discount code exits", [], 200);
        }
        if (!preg_match('/^[A-Za-z\d]+$/', $data['code'])) {
            EndpointSupport::sendJsonResponse(false, "invalid code", [], 200);
        }
        try {

            $productData = [
                'codeDiscount' => $data['code'],
                'price' => $data['percent'],
                'limitDiscount' => $data['limit_use'],
                'usedDiscount' => 0,
                'agent' => empty($data['agent']) ? "allusers" : $data['agent'],
                'usefirst' => empty($data['usefirst']) ? "0" : $data['usefirst'],
                'useuser' => empty($data['useuser']) ? $data['useuser'] : $data['useuser'],
                'code_product' => empty($data['code_product']) ? "all" : $data['code_product'],
                'code_panel' => empty($data['code_panel']) ? "/all" : $data['code_panel'],
                'time' => empty($data['time']) ? null : $data['time'],
                'type' => empty($data['type']) ? "all" : $data['type'],
            ];

            $columns = implode(',', array_keys($productData));
            $placeholders = ':' . implode(', :', array_keys($productData));
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO DiscountSell ({$columns}) VALUES ({$placeholders})"
            );

            foreach ($productData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            EndpointSupport::sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in Discount add: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while editing Discount");
        }
    }
}
