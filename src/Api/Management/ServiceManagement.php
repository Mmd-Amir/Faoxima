<?php

namespace Faoxima\Api\Management;

use PDO;

final class ServiceManagement
{
    public static function dispatch(PDO $pdo, string $method, array $data): void
    {
        switch ($data['actions']) {
            case 'services':
                self::services($pdo, $method, $data);
                return;
            default:
                echo json_encode(array(
            'status' => false,
            'msg' => "Action Invalid"
            ));
                return;
        }
    }

    private static function services(PDO $pdo, string $method, array $data): void
    {
        if($method != "GET"){
    echo json_encode(array(
        'status' => false,
        'msg' => "method invalid; is mthod must GET"
        ));
    return;
}


        $limitVal = 0;
        if (isset($data['limit']) && is_numeric($data['limit'])) {
            $limitVal = max(0, min((int) $data['limit'], 10000));
        }
        if ($limitVal > 0) {
            $stmt = $pdo->prepare("SELECT id,id_user,username,time,price,type,status FROM service_other LIMIT :lim");
            $stmt->bindValue(':lim', $limitVal, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("SELECT id,id_user,username,time,price,type,status FROM service_other");
        }
        $stmt->execute();
        $users = $stmt->fetchAll();
        echo json_encode(array(
        'status' => true,
        'msg' => "Successful",
        'obj' => $users
        ));
    }
}
