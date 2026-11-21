<?php

namespace Faoxima\Api\Endpoints;

use FaoximaApiCredential;

/**
 * Handler stage. Inherited by value: $APIKEY, $pdo.
 * Inherited or exported by reference: $data, $headrs, $setting, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Log
{
    public static function handle(
        $APIKEY,
        &$data,
        &$headrs,
        $pdo,
        &$setting,
        &$stmt
    ) {
        header('Content-Type: application/json');
        date_default_timezone_set('Asia/Tehran');
        ini_set('default_charset', 'UTF-8');
        ini_set('error_log', 'error_log');

        $headrs = getallheaders();
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        if (!FaoximaApiCredential::validBotToken($headrs['Token'] ?? null, (string) ($APIKEY ?? ''))) {
            http_response_code(403);
            echo json_encode(array(
                'status' => false,
                'msg' => "token invalid"
                ));
            return;
        }


        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);
        if (!is_array($data)) {
            $data = [];
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO logs_api (header,data,time,ip,actions) VALUES (:header,:data,:time,:ip,:actions)");
        $headerJson  = json_encode(FaoximaApiCredential::redactHeaders($headrs));
        $dataJson    = json_encode(FaoximaApiCredential::redactAuditFields($data));
        $nowStr      = date('Y/m/d H:i:s');
        $ipStr       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $actionStr   = $data['actions'] ?? 'log';
        $stmt->bindParam(':header',  $headerJson);
        $stmt->bindParam(':data',    $dataJson);
        $stmt->bindParam(':time',    $nowStr);
        $stmt->bindParam(':ip',      $ipStr);
        $stmt->bindParam(':actions', $actionStr);
        $stmt->execute();


        $count_user = \Faoxima\Core\Database\Db::select("user","*",null,null,"count");
        $stmt = $pdo->prepare("SELECT * FROM user WHERE agent != 'f'");
        $stmt->execute();
        $count_agent = $stmt->rowCount();
        $count_invoice = \Faoxima\Core\Database\Db::select("invoice","*",null,null,"count");
        echo json_encode(array(
            'count_user' => $count_user,
            'count_invoice' => $count_invoice,
            'count_agent' => $count_agent
            ));

        return 1;
    }
}
