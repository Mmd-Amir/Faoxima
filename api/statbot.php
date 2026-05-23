<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');

$headrs = getallheaders();
$setting = select("setting", "*");
if(!isset($headrs['Token']) or $APIKEY != $headrs['Token']){
    http_response_code(403);
    echo json_encode(array(
        'status' => false,
        'msg' => "token invalid"
        ));
    return;
}

$stmt = $pdo->prepare("INSERT IGNORE INTO logs_api (header,data,time,ip,actions) VALUES (:header,:data,:time,:ip,:actions)");
$stmt->bindParam(':header',json_encode($headrs));
$stmt->bindParam(':data',json_encode($data));
$stmt->bindParam(':time',date('Y/m/d H:i:s'));
$stmt->bindParam(':ip',$_SERVER['REMOTE_ADDR']);
$stmt->bindParam(':actions',$data['actions']);
$stmt->execute();


$count_user = select("user","*",null,null,"count");
$stmt = $pdo->prepare("SELECT * FROM user WHERE agent != 'f'");
$stmt->execute();
$count_agent = $stmt->rowCount();
$count_invoice = select("invoice","*",null,null,"count");
echo json_encode(array(
    'count_user' => $count_user,
    'count_invoice' => $count_invoice,
    'count_agent' => $count_agent
    ));
