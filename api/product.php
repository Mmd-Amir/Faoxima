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

    case 'products':
        validateMethod('GET', $method);


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
            $query = "SELECT * FROM product WHERE (id  LIKE CONCAT('%', :id_product, '%') OR name_product  LIKE CONCAT('%', :name_product, '%')) ORDER BY id LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':name_product', $q, PDO::PARAM_STR);
            $stmt->bindValue(':id_product', $q, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $panel = select("marzban_panel", "name_panel,code_panel", "status", "active", "fetchAll");
            $category = select("category", "*", null, null, "fetchAll");
            sendJsonResponse(true, "Successful", [
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
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'product':
        validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            sendJsonResponse(false, "id empty", []);
        }

        try {
            $prodcut = select("product", "*", "id", $data['id'], "select");
            if (!$prodcut) {
                sendJsonResponse(true, "Successful", [
                    'product' => [],
                ]);
            }
            $count_invoice = select("invoice", "*", "name_product", $prodcut['name_product'], "count");
            $sum_invoice = select("invoice", "SUM(price_product) as sum_price", "name_product", $prodcut['name_product'], "select");
            $panel = select("marzban_panel", "name_panel,code_panel", "status", "active", "fetchAll");
            $category = select("category", "*", null, null, "fetchAll");
            $prodcut['hide_panel'] = json_decode($prodcut['hide_panel'], true);
            sendJsonResponse(true, "Successful", [
                'product' => $prodcut,
                'count_invoice' => $count_invoice,
                'sum_invoice' => $sum_invoice['sum_price'] ? $sum_invoice['sum_price'] : 0,
                'panels' => $panel,
                'category' => $category,
            ]);
        } catch (Exception $e) {
            error_log("Database error in product: " . $e->getMessage());
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'product_add':
        validateMethod('POST', $method);
        $required_fields = ['name', 'price', 'data_limit', 'time', 'location'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $prodcut = select("product", "*", "name_product", $data['name'], "count");
        if ($prodcut != 0) {
            sendJsonResponse(false, "product name exits", [], 200);
        }
        $panel = select("marzban_panel", "*", "code_panel", $data['location'], "select");
        if (!$panel & $data['location'] != "/all")
            sendJsonResponse(false, "location not found", [], 200);
        try {
            $randomString = bin2hex(random_bytes(3));

            $productData = [
                'code_product' => $randomString,
                'name_product' => $data['name'],
                'price_product' => $data['price'],
                'Volume_constraint' => $data['data_limit'],
                'Service_time' => $data['time'],
                'Location' => $panel['name_panel'],
                'agent' => empty($data['agent']) ? "f" : $data['agent'],
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
            sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in product_add: " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while editing product");
        }
        break;


    case 'product_edit':
        validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            sendJsonResponse(false, "product not found", [], 200);
        }
        if (isset($data['name']) && $product['name_product'] != $data['name']) {
            $product_check = select("product", "*", "name_product", $data['name'], "count");
            if ($product_check != 0)
                sendJsonResponse(false, "product name exits", [], 200);
            update("invoice", "name_product", $data['name'], "name_product", $product['name_product']);
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

            sendJsonResponse(true, "product updated successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in product_edit: " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while adding product");
        }
        break;
    case 'product_delete':
        validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            sendJsonResponse(false, "product not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM product  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            sendJsonResponse(true, "product delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in product delete : " . $e->getMessage());
            sendJsonResponse(false, "An error occurred while delete prodcut");
        }
        break;
    case 'set_inbounds':
        validateMethod('POST', $method);
        $required_fields = ['id', 'input'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            sendJsonResponse(false, "product not found", [], 200);
        }
        $panel = select("marzban_panel", "*", 'name_panel', $product['Location'], "select");
        if ($panel['type'] == "marzban") {
            if ((string)($panel['version_panel'] ?? '0') === '1') {
                $DataUserOut = getuser($data['input'], $panel['name_panel']);
                if (!empty($DataUserOut['error']))
                    sendJsonResponse(false, $DataUserOut['error'], [], 200);
                if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200)
                    sendJsonResponse(false, $DataUserOut['msg'], [], 200);
                $DataUserOut = json_decode($DataUserOut['body'], true);
                if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings']))
                    sendJsonResponse(false, "User Not Found", [], 200);
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
                $DataUserOut = getuser($data['input'], $panel['name_panel']);
                if (!empty($DataUserOut['error']))
                    sendJsonResponse(false, $DataUserOut['error'], [], 200);
                if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200)
                    sendJsonResponse(false, $DataUserOut['msg'], [], 200);
                $DataUserOut = json_decode($DataUserOut['body'], true);
                if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                    sendJsonResponse(false, "User Not Found", [], 200);
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
        } elseif ($panel['type'] == "marzneshin") {
            $userdata = json_decode(getuserm($data['input'], $panel['name_panel'])['body'], true);
            if (isset($userdata['detail']) and $userdata['detail'] == "User not found")
                sendJsonResponse(false, "User Not Found", [], 200);
            $datainbound = json_encode($userdata['service_ids'], true);
        } elseif ($panel['type'] == "x-ui_single") {
            $user_data = get_clinets($data['input'], $panel['name_panel']);
            if (!empty($user_data['error']))
                sendJsonResponse(false, $user_data['error'], [], 200);
            if (!empty($user_data['status']) && $user_data['status'] != 200)
                sendJsonResponse(false, $user_data['msg'], [], 200);
            $user_data = json_decode($user_data['body'], true)['obj'];
            if ($user_data == null)
                sendJsonResponse(false, "User Not Found", [], 200);
            $datainbound = $user_data['inboundId'];
        } elseif ($panel['type'] == "s_ui") {
            $user_data = GetClientsS_UI($data['input'], $panel['name_panel']);
            if (count($user_data) == 0) {
                sendJsonResponse(false, "User Not Found", [], 200);
            }
            $servies = [];
            foreach ($user_data['inbounds'] as $service) {
                $servies[] = $service;
            }
            $datainbound = json_encode($servies);
        } elseif ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
            $datainbound = $data['input'];
        } else {
            sendJsonResponse(false, "panel_not_support_options", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE product SET inbounds = :inbounds WHERE id = :id_product ");
        $stmt->bindParam(':inbounds', $datainbound);
        $stmt->bindParam(':id_product', $data['id']);
        $stmt->execute();
        sendJsonResponse(true, "successfully", [], 200);
    case 'remove_inbounds':
        validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $product = select("product", "*", "id", $data['id'], "select");
        if (!$product) {
            sendJsonResponse(false, "product not found", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE product SET inbounds = NULL,proxies = NULL WHERE id = :id_product ");
        $stmt->bindParam(':id_product', $data['id']);
        $stmt->execute();
        sendJsonResponse(true, "successfully", [], 200);
    default:
        sendJsonResponse(false, "Action Invalid");
        break;
}

?>

