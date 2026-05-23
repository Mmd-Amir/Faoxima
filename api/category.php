<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';


header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');
$topic_id = select("topicid", "*", null, null, "fetchAll");
foreach ($topic_id as $topic) {
    if ($topic['report'] == "reportnight")
        $reportnight = $topic['idreport'];
    if ($topic['report'] == 'reporttest')
        $reporttest = $topic['idreport'];
    if ($topic['report'] == 'errorreport')
        $errorreport = $topic['idreport'];
    if ($topic['report'] == 'porsantreport')
        $porsantreport = $topic['idreport'];
    if ($topic['report'] == 'reportcron')
        $reportcron = $topic['idreport'];
    if ($topic['report'] == 'backupfile')
        $reportbackup = $topic['idreport'];
    if ($topic['report'] == 'buyreport')
        $buyreport = $topic['idreport'];
    if ($topic['report'] == 'otherservice')
        $otherservice = $topic['idreport'];
    if ($topic['report'] == 'paymentreport')
        $paymentreports = $topic['idreport'];

}
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');


function sendJsonResponse($status, $message, $data = [], $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'msg' => $message,
        'obj' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendReport($text, $groupid, $topic_id, $reply_markup = null)
{
    if (strlen($groupid) > 0) {
        telegram('sendmessage', [
            'chat_id' => $groupid,
            'message_thread_id' => $topic_id,
            'text' => $text,
            'parse_mode' => "HTML",
            'reply_markup' => $reply_markup
        ]);
    }
}

function validateToken($headers)
{
    global $APIKEY;
    if (!isset($headers['Token'])) {
        return false;
    }


    $headerToken = (string) $headers['Token'];
    if ($headerToken === '') return false;
    if (is_file('hash.txt')) {
        $token = trim((string) file_get_contents('hash.txt'));
    } else {
        $token = "";
    }
    $validTokens = array_values(array_filter([$token, (string) ($APIKEY ?? '')], 'strlen'));
    foreach ($validTokens as $candidate) {
        if (hash_equals($candidate, $headerToken)) return true;
    }
    return false;
}

function sanitizeRecursive($data)
{
    if (is_array($data)) {
        return array_map('sanitizeRecursive', $data);
    }
    return is_string($data) ? htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8') : $data;
}

function validateMethod($expected, $actual)
{
    if (strtoupper($expected) !== strtoupper($actual)) {
        sendJsonResponse(false, "method invalid; method must be {$expected}");
    }
}

function logApiRequest($headers, $data, $action)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO logs_api (header, data, time, ip, actions) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            json_encode($headers),
            json_encode($data),
            date('Y/m/d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $action
        ]);
    } catch (Exception $e) {
        error_log("API logging error: " . $e->getMessage());
    }
}


$headers = getallheaders();
if (!validateToken($headers)) {
    sendJsonResponse(false, "token invalid", [], 403);
}


$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);


if (!is_array($data)) {
    sendJsonResponse(false, "data invalid", []);
}


$data = sanitizeRecursive($data);


logApiRequest($headers, $data, $data['actions'] ?? 'unknown');


$setting = select("setting", "*");


switch ($data['actions'] ?? '') {

    case 'categorys':
        validateMethod('GET', $method);


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
            $query = "SELECT * FROM category WHERE remark  LIKE CONCAT('%', :remark, '%') ORDER BY id LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':remark', $q, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $categorys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(true, "Successful", [
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
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'category':
        validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            sendJsonResponse(false, "id empty", []);
        }

        try {
            $prodcut = select("category", "*", "id", $data['id'], "select");
            if (!$categorys) {
                sendJsonResponse(true, "Successful", [
                    'category' => [],
                ]);
            }
            sendJsonResponse(true, "Successful", [
                'category' => $category,
            ]);
        } catch (Exception $e) {
            error_log("Database error in category: " . $e->getMessage());
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'category_add':
        validateMethod('POST', $method);
        $required_fields = ['remark'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
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
            sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in category_add: " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while editing category");
        }
        break;


    case 'category_edit':
        validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $category = select("category", "*", "id", $data['id'], "select");
        if (!$category) {
            sendJsonResponse(false, "category not found", [], 200);
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

            sendJsonResponse(true, "category updated successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in category_edit: " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while adding category");
        }
        break;
    case 'category_delete':
        validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $category = select("category", "*", "id", $data['id'], "select");
        if (!$category) {
            sendJsonResponse(false, "category not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM category  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            sendJsonResponse(true, "category delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in category delete : " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while delete prodcut");
        }
        break;
    default:
        sendJsonResponse(false, "Action Invalid");
        break;
}

?>

