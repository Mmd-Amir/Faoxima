<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class PaymentManagement
{
    public static function dispatch(PDO $pdo, string $method, array $data): void
    {
        $action = $data['actions'] ?? null;
        switch ($action) {
            case 'payments':
                self::payments($pdo, $method, $data);
                return;
            case 'payment':
                self::payment($method, $data);
                return;
            default:
                EndpointSupport::sendJsonResponse(false, "Action Invalid");
                return;
        }
    }

    private static function payments(PDO $pdo, string $method, array $data): void
    {
        if ($method !== 'GET') {
            EndpointSupport::sendJsonResponse(false, "method invalid; must be GET");
        }

        $limit = 50;
        if (isset($data['limit']) && is_numeric($data['limit'])) {
            $limit = min(max((int) $data['limit'], 1), 1000);
        }


        $page = isset($data['page']) && is_numeric($data['page']) ? max((int) $data['page'], 1) : 1;
        $offset = ($page - 1) * $limit;
        $q = isset($data['q']) ? $data['q'] : '';


        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Payment_report WHERE (id_user LIKE :id_user)");
            $search = "%$q%";
            $stmt->bindParam(':id_user', $search, PDO::PARAM_STR);
            $stmt->execute();
            $total_record = (int) $stmt->fetchColumn();
            $total_record = ceil($total_record / $limit);
            $stmt = $pdo->prepare("SELECT id_order as id,id_user,time,price,payment_status,Payment_Method FROM Payment_report WHERE id_user  LIKE CONCAT('%', :id_user, '%') OR id_order  LIKE CONCAT('%', :id_order, '%') ORDER BY time DESC LIMIT :limit OFFSET :offset");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':id_user', $q, type: PDO::PARAM_INT);
            $stmt->bindParam(':id_order', $q, type: PDO::PARAM_INT);
            $stmt->execute();
            $payment = $stmt->fetchAll(PDO::FETCH_ASSOC);

            EndpointSupport::sendJsonResponse(true, "Successful", [
                'payments' => $payment,
                'pagination' => [
                    'total_record' => $total_record,
                    'total_pages' => $limit,
                    'current_page' => $page,
                    'per_page' => $limit
                ]
            ]);
        } catch (Exception $e) {
            error_log("Database error in users: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function payment(string $method, array $data): void
    {
        if ($method !== 'GET') {
            EndpointSupport::sendJsonResponse(false, "method invalid; must be GET");
        }


        if (!isset($data['id_order']) || empty($data['id_order'])) {
            EndpointSupport::sendJsonResponse(false, "id_order empty", []);
        }

        $payment = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $data['id_order'], "select");
        if (!$payment) {
            EndpointSupport::sendJsonResponse(false, "payment not found",[],200);
        }
        EndpointSupport::sendJsonResponse(true, "Successful",$payment,200);
    }
}
