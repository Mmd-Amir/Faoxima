<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';


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

    case 'discounts':
        validateMethod('GET', $method);


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
            sendJsonResponse(true, "Successful", [
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
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'discount':
        validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            sendJsonResponse(false, "id empty", []);
        }

        try {
            $discount = select("Discount", "*", "id", $data['id'], "select");
            if (!$discount) {
                sendJsonResponse(true, "Successful", [
                    'discount' => [],
                ]);
            }
            sendJsonResponse(true, "Successful", [
                'discount' => $discount,
            ]);
        } catch (Exception $e) {
            error_log("Database error in discount: " . $e->getMessage());
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'discount_add':
        validateMethod('POST', $method);
        $required_fields = ['code', 'price', 'limit_use'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $discount = select("Discount", "*", "code", $data['code'], "count");
        if ($discount != 0) {
            sendJsonResponse(false, "Discount code exits", [], 200);
        }
        if (!preg_match('/^[A-Za-z\d]+$/', $data['code'])) {
            sendJsonResponse(false, "invalid code", [], 200);
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
            sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in Discount add: " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while editing Discount");
        }
        break;

    case 'discount_delete':
        validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = select("Discount", "*", "id", $data['id'], "select");
        if (!$product) {
            sendJsonResponse(false, "Discount not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM Discount  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            sendJsonResponse(true, "Discount delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in Discount delete : " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while delete Discount");
        }
        break;
    case 'discount_sell_lists':
        validateMethod('GET', $method);


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
            $product = select("product", "code_product as id,name_product", null, null, "fetchAll");
            $panel = select("marzban_panel", "code_panel,name_panel", "status", "active", "fetchAll");
            sendJsonResponse(true, "Successful", [
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
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;
    case 'discount_sell':
        validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            sendJsonResponse(false, "id empty", []);
        }

        try {
            $discount = select("DiscountSell", "*", "id", $data['id'], "select");
            if($discount['code_product'] != "all")
             $discount['code_product'] = select('product',"*","code_product",$discount['code_product'],"select")['name_product'];
            if($discount['code_panel'] != "/all")
             $discount['code_panel'] = select('marzban_panel',"*","code_panel",$discount['code_panel'],"select")['name_panel'];
            if (!$discount) {
                sendJsonResponse(true, "Successful", [
                    'discount' => [],
                ]);
            }
            sendJsonResponse(true, "Successful", [
                'discount' => $discount,
            ]);
        } catch (Exception $e) {
            error_log("Database error in discount: " . $e->getMessage());
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'discount_sell_delete':
        validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = select("DiscountSell", "*", "id", $data['id'], "select");
        if (!$product) {
            sendJsonResponse(false, "DiscountSell not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM DiscountSell  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            sendJsonResponse(true, "DiscountSell delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in DiscountSell delete : " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while delete DiscountSell");
        }
        break;
    case 'discount_sell_add':
        validateMethod('POST', $method);
        $required_fields = ['code', 'percent', 'limit_use'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $discount = select("DiscountSell", "*", "codeDiscount", $data['code'], "count");
        if ($discount != 0) {
            sendJsonResponse(false, "Discount code exits", [], 200);
        }
        if (!preg_match('/^[A-Za-z\d]+$/', $data['code'])) {
            sendJsonResponse(false, "invalid code", [], 200);
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
            sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in Discount add: " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while editing Discount");
        }
        break;
    default:
        sendJsonResponse(false, "Action Invalid");
        break;
}

?>

