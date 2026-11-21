<?php

namespace Faoxima\Api\Endpoints;

use FaoximaApiCredential;

/**
 * Handler stage. Inherited by value: $APIKEY, $data, $pdo.
 * Inherited or exported by reference: $headrs, $setting, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Statbot
{
    public static function handle(
        $APIKEY,
        $data,
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

        $stmt = $pdo->prepare("INSERT IGNORE INTO logs_api (header,data,time,ip,actions) VALUES (:header,:data,:time,:ip,:actions)");
        $stmt->execute([
            ':header' => json_encode(FaoximaApiCredential::redactHeaders($headrs)),
            ':data' => json_encode(is_array($data ?? null) ? FaoximaApiCredential::redactAuditFields($data) : ($data ?? null)),
            ':time' => date('Y/m/d H:i:s'),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':actions' => $data['actions'] ?? null,
        ]);


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
