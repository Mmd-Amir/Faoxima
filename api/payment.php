<?php


require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');
$ManagePanel = new ManagePanel();

$otherservice = select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
$paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
$reportnight = select("topicid", "idreport", "report", "reportnight", "select")['idreport'];
$reporttest = select("topicid", "idreport", "report", "reporttest", "select")['idreport'];
$errorreport = select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
$porsantreport = select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
$reportcron = select("topicid", "idreport", "report", "reportcron", "select")['idreport'];
$reportbackup = select("topicid", "idreport", "report", "backupfile", "select")['idreport'];
$setting = select("setting", "*");

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
    if (is_file('hash.txt')) {
        $token = file_get_contents('hash.txt');
    } else {
        $token = "";
    }
    $validTokens = [$token, $APIKEY];
    return in_array($headers['Token'], $validTokens, true);
}

$headers = getallheaders();
if (!validateToken($headers)) {
    sendJsonResponse(false, "token invalid", [], 403);
}


$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    sendJsonResponse(false, "data invalid", []);
}

$data = sanitize_recursive($data);
$action = $data['actions'] ?? null;


$stmt = $pdo->prepare("INSERT IGNORE INTO logs_api (header, data, time, ip, actions) VALUES (:header, :data, :time, :ip, :actions)");
$stmt->execute([
    ':header' => json_encode($headers),
    ':data' => json_encode($data),
    ':time' => date('Y/m/d H:i:s'),
    ':ip' => $_SERVER['REMOTE_ADDR'],
    ':actions' => $action
]);


switch ($action) {
    case 'payments':
        if ($method !== 'GET') {
            sendJsonResponse(false, "method invalid; must be GET");
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

            sendJsonResponse(true, "Successful", [
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
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;
    case 'payment':
        if ($method !== 'GET') {
            sendJsonResponse(false, "method invalid; must be GET");
        }


        if (!isset($data['id_order']) || empty($data['id_order'])) {
            sendJsonResponse(false, "id_order empty", []);
        }

        $payment = select("Payment_report", "*", "id_order", $data['id_order'], "select");
        if (!$payment) {
            sendJsonResponse(false, "payment not found",[],200);
        }
        sendJsonResponse(true, "Successful",$payment,200);
        break;

    default:
        sendJsonResponse(false, "Action Invalid");
        break;
}

?>

