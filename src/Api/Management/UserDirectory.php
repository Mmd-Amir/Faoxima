<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class UserDirectory
{
    public static function users(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method, [], 403);


        $limit = 50;
        if (isset($data['limit']) && is_numeric($data['limit'])) {
            $limit = min(max((int) $data['limit'], 1), 1000);
        }


        $page = isset($data['page']) && is_numeric($data['page']) ? max((int) $data['page'], 1) : 1;
        $offset = ($page - 1) * $limit;
        $q = isset($data['q']) ? $data['q'] : '';


        $agentFilter = null;
        if (isset($data['agent']) && $data['agent'] !== '') {
            $candidate = (string) $data['agent'];


            $candidate = html_entity_decode($candidate, ENT_QUOTES, 'UTF-8');
            if (preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $candidate)) {
                $agentFilter = $candidate;
            } else {
                error_log('[api/users] users action: rejected malformed agent filter: ' . substr($candidate, 0, 32));
                EndpointSupport::sendJsonResponse(false, "agent invalid", [], 400);
            }
        }
        $agentClause = $agentFilter !== null ? ' AND agent = :agent_filter' : '';

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user WHERE (id LIKE :id_user OR username LIKE :username) $agentClause");
            $search = "%$q%";
            $stmt->bindParam(':id_user', $search, PDO::PARAM_STR);
            $stmt->bindParam(':username', $search, PDO::PARAM_STR);
            if ($agentFilter !== null) {
                $stmt->bindValue(':agent_filter', $agentFilter, PDO::PARAM_STR);
            }
            $stmt->execute();
            $totalUsers = (int) $stmt->fetchColumn();
            $totalPages = ceil($totalUsers / $limit);
            $query = "SELECT id as user_id,username,limit_usertest,roll_Status,number,Balance,User_Status,agent,affiliatescount,affiliates,cardpayment,register as time_join,verify,pricediscount,last_message_time,limit_usertest,score,joinchannel,status_cron,expire,maxbuyagent FROM user WHERE (id  LIKE CONCAT('%', :user_id, '%') OR username  LIKE CONCAT('%', :username, '%')) $agentClause ORDER BY register DESC,Balance DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':username', $q, PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $q, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            if ($agentFilter !== null) {
                $stmt->bindValue(':agent_filter', $agentFilter, PDO::PARAM_STR);
            }
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'users' => $users,
                'pagination' => [
                    'total_users' => $totalUsers,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $limit
                ]
            ]);

        } catch (Exception $e) {
            error_log("Database error in users: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    public static function user(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "chat_id empty", []);
        }

        try {
            $stmt = $pdo->prepare("SELECT id as user_id,username,limit_usertest,roll_Status,number,Balance,User_Status,agent,affiliatescount,affiliates,cardpayment,register as time_join,verify,pricediscount,last_message_time,limit_usertest,score,joinchannel,status_cron,expire,maxbuyagent,limitchangeloc,description_blocking FROM user WHERE id = :user_id");
            $stmt->bindValue(':user_id', intval($data['chat_id']), PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($users)) {
                EndpointSupport::sendJsonResponse(true, "Successful", [

                    'users' => [],
                    'pagination' => [
                        'total_users' => 1,
                        'total_pages' => 1,
                        'current_page' => 1,
                        'per_page' => 10
                    ]
                ]);
            }
            $stmt = $pdo->prepare("SELECT SUM(price_product) as sum_price,COUNT(username) as count_invoice FROM invoice WHERE name_product != 'سرویس تست' AND  id_user = :user_id AND Status != 'Unpaid'");
            $stmt->bindValue(':user_id', intval($users[0]['user_id']), PDO::PARAM_INT);
            $stmt->execute();
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            $users[0]['count_invoice'] = $invoice['count_invoice'];
            $users[0]['sum_invoice'] = $invoice['sum_price'];
            $stmt = $pdo->prepare("SELECT SUM(price) as sum_price,COUNT(*) as count_payment FROM Payment_report WHERE id_user = :user_id AND Payment_Method not in ('Unpaid','reject','expire')");
            $stmt->bindValue(':user_id', intval($users[0]['user_id']), PDO::PARAM_INT);
            $stmt->execute();
            $payment_report = $stmt->fetch(PDO::FETCH_ASSOC);
            $users[0]['count_payment'] = $payment_report['count_payment'];
            $users[0]['sum_payment'] = $payment_report['sum_price'];
            $stmt = $pdo->prepare("SELECT SUM(price) as sum_price,COUNT(*) as count_service FROM service_other WHERE id_user = :user_id AND (status = 'paid' OR status IS NULL)");
            $stmt->bindValue(':user_id', intval($users[0]['user_id']), PDO::PARAM_INT);
            $stmt->execute();
            $service_report = $stmt->fetch(PDO::FETCH_ASSOC);
            $users[0]['count_service'] = $service_report['count_service'];
            $users[0]['sum_service'] = $service_report['sum_price'];
            $bot_agent = \Faoxima\Core\Database\Db::select("botsaz", "*", "id_user", $data['chat_id'], "select");
            $list_panel = [];
            if ($bot_agent) {
                $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' ORDER BY (position = 0) ASC, position ASC, id ASC");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $list_panel[] = $row['name_panel'];
                }
            }
            $users[0]['agent_bot'] = $bot_agent;
            $users[0]['panels'] = $list_panel;
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "code_panel,name_panel", null, null, "fetchAll");
            $product = \Faoxima\Core\Database\Db::select("product", "code_product,name_product", null, null, "fetchAll");
            EndpointSupport::sendJsonResponse(true, "Successful", [

                'users' => $users,
                'panel' => $panel,
                'product' => $product,
                'pagination' => [
                    'total_users' => 1,
                    'total_pages' => 1,
                    'current_page' => 1,
                    'per_page' => 10
                ]
            ]);
        } catch (Exception $e) {
            error_log("Database error in user: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    public static function affiliatesUsers(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method, [], 403);

        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }

        try {
            $stmt = $pdo->prepare("SELECT id as user_id FROM user WHERE affiliates = :affiliates_id");
            $stmt->bindValue(':affiliates_id', $data['chat_id']);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'users' => $users
            ]);

        } catch (Exception $e) {
            error_log("Database error in users: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }
}
