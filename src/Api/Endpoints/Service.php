<?php

namespace Faoxima\Api\Endpoints;

use FaoximaApiCredential;

/**
 * Handler stage. Inherited by value: $APIKEY, $headrs, $pdo.
 * Inherited or exported by reference: $data, $method, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Service
{
    public static function handle(
        $APIKEY,
        &$data,
        $headrs,
        &$method,
        $pdo,
        &$stmt
    ) {
        $headrs = array_change_key_case($headrs, CASE_LOWER);
        $tokenOk = FaoximaApiCredential::valid($headrs['token'] ?? null, (string) ($APIKEY ?? ''));
        if (!$tokenOk){
            http_response_code(403);
            echo json_encode(array(
                'status' => false,
                'msg' => "token invalid"
                ));
            return;
        }
        $method = $_SERVER['REQUEST_METHOD'];

        $data = json_decode(file_get_contents("php://input"),true);
        if(!is_array($data)){
            echo json_encode(array(
                'status' => false,
                'msg' => "data invalid",
                'obj' => []
                ));
                return;
        }
        $data = \Faoxima\Core\Support\Formatting::sanitize_recursive($data);
        $stmt = $pdo->prepare("INSERT IGNORE INTO logs_api (header,data,time,ip,actions) VALUES (:header,:data,:time,:ip,:actions)");
        $stmt->execute([
            ':header' => json_encode(FaoximaApiCredential::redactHeaders($headrs)),
            ':data' => json_encode(FaoximaApiCredential::redactAuditFields($data)),
            ':time' => date('Y/m/d H:i:s'),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':actions' => $data['actions'] ?? null,
        ]);
        \Faoxima\Api\Management\ServiceManagement::dispatch($pdo, $method, $data);

        return 1;
    }
}
