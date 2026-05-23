<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';


header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');
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

    case 'keyboard_set':
        validateMethod('POST', $method);

        try {
            $keyboard = $data['keyboard'];
            if ($data['keyboard_reset']) {
                $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
                update("setting", "keyboardmain", $keyboardmain, null, null);
                sendJsonResponse(true, "Successful", [], 200);
            }
            if (!is_array($keyboard)) {
                sendJsonResponse(false, 'keyboard invalid', [], 200);
            }
            $keyboardmain = ['keyboard' => []];
            $keyboardmain['keyboard'] = $keyboard;
            update("setting", "keyboardmain", json_encode($keyboardmain), null, null);
            sendJsonResponse(true, "Successful", [], 200);
            break;
        } catch (Exception $e) {
            error_log("Database error in keyboard: " . $e->getMessage());
            sendJsonResponse(false, "Database error occurred", [], 200);
        }
    case 'setting_info':
        validateMethod('GET', $method);
        try {
            $shopsetting = select("shopSetting", "*", null, null, "fetchAll");
            $shop_setting = [];
            foreach ($shopsetting as $setting) {
                $shop_setting[$setting['Namevalue']] = $setting['value'];
            }
            $setting = select("setting", "*", null, null, "select");
            sendJsonResponse(true, "Successful", [
                'setting_shop' => $shop_setting,
                'setting_General' => $setting
            ]);

        } catch (Exception $e) {
            error_log("Database error in setting: " . $e->getMessage());
            sendJsonResponse(false, "Database error ", [], 500);
        }
        break;
    case 'save_setting_shop':
        validateMethod('POST', $method);
        try {
            $shopsetting = select("shopSetting", "*", null, null, "fetchAll");
            if (empty($data['data']))
                sendJsonResponse(false, "data empty ", [], 200);
            foreach ($data['data'] as $setting) {
                if (!empty(($setting['json'])) && $setting['json'])
                    $setting['value'] = json_encode($setting['value']);
                if ($setting['type'] == "shop") {
                    update("shopSetting", "value", $setting['value'], "Namevalue", $setting['name_value']);
                } else {
                    update("setting", $setting['name_value'], $setting['value'], null, null);
                }
            }
            $setting = select("setting", "*", null, null, "select");
            sendJsonResponse(true, "setting updated successfully", [], 200);
            break;
        } catch (Exception $e) {
            error_log("Database error in setting: " . $e->getMessage());
            sendJsonResponse(false, "Database error ", [], 500);
        }
    default:
        sendJsonResponse(false, "Action Invalid");
        break;
}
