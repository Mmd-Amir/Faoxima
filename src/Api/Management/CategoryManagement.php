<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class CategoryManagement
{
    public static function dispatch(PDO $pdo, string $method, array $data): void
    {
        switch ($data['actions'] ?? '') {
            case 'categorys':
                self::categorys($pdo, $method, $data);
                return;
            case 'category':
                self::category($method, $data);
                return;
            case 'category_add':
                self::categoryAdd($pdo, $method, $data);
                return;
            case 'category_edit':
                self::categoryEdit($pdo, $method, $data);
                return;
            case 'category_delete':
                self::categoryDelete($pdo, $method, $data);
                return;
            default:
                EndpointSupport::sendJsonResponse(false, "Action Invalid");
                return;
        }
    }

    private static function categorys(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        $limit = 50;
        if (isset($data['limit']) && is_numeric($data['limit']))
            $limit = min(max((int) $data['limit'], 1), 1000);


        $page = isset($data['page']) && is_numeric($data['page']) ? max((int) $data['page'], 1) : 1;
        $offset = ($page - 1) * $limit;
        $q = isset($data['q']) ? $data['q'] : '';

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM category WHERE (remark LIKE :remark )");
            $search = "%$q%";
            $stmt->bindParam(':remark', $search, PDO::PARAM_STR);
            $stmt->execute();
            $totalcategory = (int) $stmt->fetchColumn();
            $totalPages = ceil($totalcategory / $limit);
            $query = "SELECT * FROM category WHERE remark  LIKE CONCAT('%', :remark, '%') ORDER BY (position = 0) ASC, position ASC, id ASC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':remark', $q, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $categorys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'categorys' => $categorys,
                'pagination' => [
                    'total_categorys' => $totalcategory,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $limit
                ]
            ]);

        } catch (Exception $e) {
            error_log("Database error in category: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function category(string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            EndpointSupport::sendJsonResponse(false, "id empty", []);
        }

        try {
            $prodcut = \Faoxima\Core\Database\Db::select("category", "*", "id", $data['id'], "select");
            if (!$categorys) {
                EndpointSupport::sendJsonResponse(true, "Successful", [
                    'category' => [],
                ]);
            }
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'category' => $category,
            ]);
        } catch (Exception $e) {
            error_log("Database error in category: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function categoryAdd(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['remark'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        try {
            $randomString = bin2hex(random_bytes(3));

            $categoryData = [
                'remark' => $data['remark'],
            ];


            $columns = implode(',', array_keys($categoryData));
            $placeholders = ':' . implode(', :', array_keys($categoryData));

            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO category ({$columns}) VALUES ({$placeholders})"
            );

            foreach ($categoryData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            EndpointSupport::sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in category_add: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while editing category");
        }
    }

    private static function categoryEdit(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $category = \Faoxima\Core\Database\Db::select("category", "*", "id", $data['id'], "select");
        if (!$category) {
            EndpointSupport::sendJsonResponse(false, "category not found", [], 200);
        }

        if (isset($data['remark']) && (string) $data['remark'] !== (string) $category['remark']) {
            if (!\Faoxima\Orders\Catalog\NameCascades::rxCategoryRenameCascade($pdo, (string) $category['remark'], (string) $data['remark'])) {
                EndpointSupport::sendJsonResponse(false, "An error occurred while adding category");
            }
            EndpointSupport::sendJsonResponse(true, "category updated successfully", [], 200);
        }
        try {
            $categoryData = [
                'remark' => isset($data['remark']) ? $data['remark'] : $category['remark'],
            ];

            $setParts = [];
            foreach ($categoryData as $key => $value) {
                $setParts[] = "{$key} = :{$key}";
            }
            $setClause = implode(", ", $setParts);

            $stmt = $pdo->prepare("UPDATE category SET {$setClause} WHERE id = :id");

            foreach ($categoryData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);

            $stmt->execute();

            EndpointSupport::sendJsonResponse(true, "category updated successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in category_edit: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while adding category");
        }
    }

    private static function categoryDelete(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $category = \Faoxima\Core\Database\Db::select("category", "*", "id", $data['id'], "select");
        if (!$category) {
            EndpointSupport::sendJsonResponse(false, "category not found", [], 200);
        }
        try {
            if (!\Faoxima\Orders\Catalog\NameCascades::rxCategoryDeleteGuarded($pdo, (string) $category['remark'], (int) $data['id'])) {
                EndpointSupport::sendJsonResponse(false, "An error occurred while delete prodcut");
            }

            EndpointSupport::sendJsonResponse(true, "category delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in category delete : " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while delete prodcut");
        }
    }
}
