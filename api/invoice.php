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
    case 'invoices':
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
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM invoice WHERE (id_user LIKE :id_user OR username LIKE :username OR note LIKE :note)");
            $search = "%$q%";
            $stmt->bindParam(':id_user', $search, PDO::PARAM_STR);
            $stmt->bindParam(':username', $search, PDO::PARAM_STR);
            $stmt->bindParam(':note', $search, PDO::PARAM_STR);

            $stmt->execute();
            $total_record = (int) $stmt->fetchColumn();
            $totalPages = ceil($total_record / $limit);
            $stmt = $pdo->prepare("SELECT id_invoice as id, id_user, username, Service_location,name_product,Status FROM invoice WHERE ( id_user  LIKE CONCAT('%', :id_user, '%') OR username  LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :note, '%')) ORDER BY time_sell DESC LIMIT :limit OFFSET :offset");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':id_user', $q, type: PDO::PARAM_INT);
            $stmt->bindParam(':username', $q, type: PDO::PARAM_STR);
            $stmt->bindParam(':note', $q, type: PDO::PARAM_STR);
            $stmt->execute();
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(true, "Successful", [
                'invoices' => $invoices,
                'pagination' => [
                    'total_record' => $total_record,
                    'total_pages' => $totalPages,
                    'per_page' => $limit
                ]
            ]);
        } catch (Exception $e) {
            error_log("Database error in users: " . $e->getMessage());
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;
    case 'services':
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
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM service_other WHERE (id_user LIKE :id_user OR username LIKE :username) AND (status = 'paid' OR status IS NULL)");
            $search = "%$q%";
            $stmt->bindParam(':id_user', $search, PDO::PARAM_STR);
            $stmt->bindParam(':username', $search, PDO::PARAM_STR);

            $stmt->execute();
            $total_record = (int) $stmt->fetchColumn();
            $totalPages = ceil($total_record / $limit);
            $stmt = $pdo->prepare("SELECT * FROM service_other WHERE ( id_user  LIKE CONCAT('%', :id_user, '%') OR username  LIKE CONCAT('%', :username, '%')) AND (status = 'paid' OR status IS NULL) ORDER BY time DESC LIMIT :limit OFFSET :offset");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':id_user', $q, type: PDO::PARAM_INT);
            $stmt->bindParam(':username', $q, type: PDO::PARAM_STR);
            $stmt->execute();
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(true, "Successful", [
                'services' => $invoices,
                'pagination' => [
                    'total_record' => $total_record,
                    'total_pages' => $totalPages,
                    'per_page' => $limit
                ]
            ]);
        } catch (Exception $e) {
            error_log("Database error in users: " . $e->getMessage());
            sendJsonResponse(false, "Database error occurred", [], 500);
        }
        break;

    case 'invoice':
        if ($method !== 'GET') {
            sendJsonResponse(false, "method invalid; must be GET");
        }
        if (!isset($data['id_invoice'])) {
            sendJsonResponse(false, "id_invoice empty", []);
        }

        $stmt = $pdo->prepare("SELECT id_invoice, id_user, username, Service_location, time_sell, name_product, price_product,Service_time,Service_time,Volume, Status, note, refral FROM invoice WHERE id_invoice = :id_invoice");
        $stmt->execute([':id_invoice' => $data['id_invoice']]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice === false) {
            sendJsonResponse(false, "user not found", []);
        } else {
            $user_data = select("user", "*", "id", $invoice['id_user'], "select");
            $stmt = $pdo->prepare("SELECT SUM(price_product) as total_order,COUNT(username) as count_order FROM invoice WHERE name_product != 'سرویس تست' AND  id_user = :user_id AND Status != 'Unpaid'");
            $stmt->bindValue(':user_id', intval($invoice['id_user']), PDO::PARAM_INT);
            $stmt->execute();
            $order_fince = $stmt->fetch(PDO::FETCH_ASSOC);
            $data_user = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
            $invoice['number'] = $user_data['number'];
            $invoice['agent'] = $user_data['agent'];
            $invoice['count_order'] = $order_fince['count_order'];
            $invoice['total_order'] = $order_fince['total_order'];
            $invoice['user_data'] = $data_user;
            sendJsonResponse(true, "Successful", $invoice);
        }
        break;
    case 'remove_service':
        if ($method !== 'POST') {
            sendJsonResponse(false, "method invalid; must be POST");
        }
        if (!isset($data['id_invoice'])) {
            sendJsonResponse(false, "id_invoice empty", []);
        }
        $invoice = select("invoice", "*", "id_invoice", $data['id_invoice'], "select");
        if ($invoice == false) {
            sendJsonResponse(false, "Invalid ID_INVOICE", []);
        }
        if (!in_array($data['type'], ['one', 'tow', 'three'])) {
            sendJsonResponse(false, "type empty", []);
        }
        if ($data['type'] == "one") {
            update("invoice", "Status", "removebyadmin", "id_invoice", $data["id_invoice"]);
            $ManagePanel->RemoveUser($invoice['Service_location'], $invoice['username']);
        } elseif ($data['type'] == "tow") {
            if (!isset($data['amount'])) {
                sendJsonResponse(false, "id_invoice empty", []);
            }
            $stmt = $pdo->prepare("UPDATE user SET Balance =  Balance + :balance WHERE id = '{$invoice['id_user']}'");
            $stmt->execute([':balance' => $data['amount']]);
            update("invoice", "Status", "removebyadmin", "id_invoice", $data["id_invoice"]);
            $ManagePanel->RemoveUser($invoice['Service_location'], $invoice['username']);
        } elseif ($data['type'] == "three") {
            $stmt = $pdo->prepare("DELETE  FROM invoice WHERE id_invoice = :id_invoice");
            $stmt->execute(['id_invoice' => $data['id_invoice']]);
        }
        sendJsonResponse(true, "Successful", $invoice);
        break;

    case 'invoice_add':
        if ($method !== 'POST') {
            sendJsonResponse(false, "method invalid; must be POST");
        }

        $required_fields = ['chat_id', 'username', 'code_product', 'location_code'];
        $missing_fields = array_diff($required_fields, array_keys($data));

        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $data['username'] = strtolower($data['username']);
        if ($data['code_product'] == "customvolume") {
            if (empty($data["time_service"]) || empty($data["volume_service"]))
                sendJsonResponse(false, "invalid value service time or volume", []);
            $product['name_product'] = "⚙️ سرویس دلخواه";
            $product['code_product'] = "customvolume";
            $product['Service_time'] = $data['time_service'];
            $product['price_product'] = 1;
            $product['Volume_constraint'] = $data['volume_service'];
        } else {
            $product = select("product", "*", "code_product", $data['code_product'], "select");
        }
        if (!$product) {
            sendJsonResponse(false, "product not found", []);
        }
        $invoiceCount = select("invoice", "*", "username", $data['username'], "count");
        if ($invoiceCount > 0) {
            sendJsonResponse(false, "User exists in the database.", []);
        }
        $panel = select("marzban_panel", "*", "code_panel", $data['location_code'], "select");
        if (!$panel) {
            sendJsonResponse(false, "panel code not found", []);
        }
        $DataUserOut = $ManagePanel->DataUser($panel['name_panel'], $data['username']);
        if ($DataUserOut['status'] == "Unsuccessful") {
            $datetimestep = strtotime("+" . $product['Service_time'] . "days");
            $datetimestep = $product['Service_time'] == 0 ? 0 : strtotime(date("Y-m-d H:i:s", $datetimestep));
            $datac = array(
                'expire' => $datetimestep,
                'data_limit' => $product['Volume_constraint'] * pow(1024, 3),
                'from_id' => $data['chat_id'],
                'username' => "",
                'type' => 'add order by admin'
            );
            $DataUserOut = $ManagePanel->createUser($panel['name_panel'], $product['code_product'], $data['username'], $datac);
            if ($DataUserOut['username'] == null) {
                sendmessage($data['chat_id'], "❌ خطایی در ساخت اشتراک رخ داده است برای رفع مشکل علت خطا را در گروه گزارش تان بررسی کنید", null, 'HTML');
                $DataUserOut['msg'] = json_encode($DataUserOut['msg']);
                $texterros = "
خطا در ساخت کافنیگ از پنل ادمین
✍️ دلیل خطا :
{$DataUserOut['msg']}
آیدی ادمین : {$data['chat_id']}
نام پنل : {$panel['name_panel']}";
                if (strlen($setting['Channel_Report']) > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $errorreport,
                        'text' => $texterros,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendJsonResponse(false, "error in create Account", [], 200);

            }
        } else {
            $DataUserOut['configs'] = $DataUserOut['links'];
        }
        $notifctions = json_encode(array(
            'volume' => false,
            'time' => false,
        ));

        $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status,notifctions) VALUES (:id_user, :id_invoice, :username, :time_sell, :service_location, :name_product, :price_product, :volume, :service_time, :status, :notifctions)");


        $randomString = bin2hex(random_bytes(4));

        $stmt->execute([
            ':id_user' => $data['chat_id'],
            ':id_invoice' => $randomString,
            ':username' => $data['username'],
            ':time_sell' => time(),
            ':service_location' => $panel['name_panel'],
            ':name_product' => $product['name_product'],
            ':price_product' => $product['price_product'],
            ':volume' => $product['Volume_constraint'],
            ':service_time' => $product['Service_time'],
            ':status' => 'active',
            ':notifctions' => $notifctions
        ]);

        sendJsonResponse(true, "Successful");
        break;
    case 'change_status_config':
        if ($method !== 'POST') {
            sendJsonResponse(false, "method invalid; must be POST");
        }

        $required_fields = ['id_invoice'];
        $missing_fields = array_diff($required_fields, array_keys($data));

        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $invoice = select("invoice", "*", "id_invoice", $data['id_invoice'], "select");
        if (!$invoice)
            sendJsonResponse(false, "invoice  not found", [], 200);
        $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if ($DataUserOut['status'] == "on_hold" || $DataUserOut['status'] == "Unsuccessful") {
            sendJsonResponse(false, "Status config not allowed for change status config", [], 200);
            return;
        }
        $dataoutput = $ManagePanel->Change_status($invoice['username'], $invoice['Service_location']);
        if ($dataoutput['status'] == "Unsuccessful") {
            sendJsonResponse(false, "unsuccessful change status", [], 200);
            return;
        }
        if ($invoice['Status'] == "disablebyadmin") {
            update("invoice", "Status", "active", "id_invoice", $invoice['id_invoice']);
        } else {
            update("invoice", "Status", "disablebyadmin", "id_invoice", $invoice['id_invoice']);
        }
        sendJsonResponse(true, "Successful");
        break;
    case 'extend_service_admin':
        if ($method !== 'POST') {
            sendJsonResponse(false, "method invalid; must be POST");
        }

        $required_fields = ['id_invoice', 'time_service', 'volume_service'];
        $missing_fields = array_diff($required_fields, array_keys($data));

        if (!empty($missing_fields)) {
            sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $invoice = select("invoice", "*", "id_invoice", $data['id_invoice'], "select");
        if (!$invoice)
            sendJsonResponse(false, "invoice  not found", [], 200);
        $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        $panel = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if (!$panel)
            sendJsonResponse(false, "Panel Not Found", [], 200);
        $extend = $ManagePanel->extend($panel['Methodextend'], $data['volume_service'], $data['time_service'], $invoice['username'], "custom_volume", $panel['code_panel']);
        if ($extend['status'] == false) {
            $extend['msg'] = json_encode($extend['msg']);
            $textreports = "
        خطای تمدید سرویس
نام پنل : {$panel['name_panel']}
نام کاربری سرویس : {$invoice['username']}
دلیل خطا : {$extend['msg']}";
            sendmessage($invoice['id_user'], "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            sendJsonResponse(false, "Error in extend service", [[json_decode($extend["msg"], true)]], 200);
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
        $date = date('Y/m/d H:i:s');
        $value = $data['volume_service'] . "_" . $data['time_service'];
        $type = "extend_user_by_admin";
        $price = 0;
        $stmt->bindParam(':id_user', $invoice['id_user'], PDO::PARAM_STR);
        $stmt->bindParam(':username', $invoice['username'], PDO::PARAM_STR);
        $stmt->bindParam(':value', $value, PDO::PARAM_STR);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':time', $date, PDO::PARAM_STR);
        $stmt->bindParam(':price', $price, PDO::PARAM_STR);
        $output_json = json_encode($extend);
        $output_json_var = $output_json;
        $stmt->bindParam(':output', $output_json_var, PDO::PARAM_STR);
        $stmt->execute();
        update("invoice", "Status", "active", "id_invoice", $data['id_invoice']);
        sendJsonResponse(true, "Successful");
        break;
    default:
        sendJsonResponse(false, "Action Invalid");
        break;
}

?>

