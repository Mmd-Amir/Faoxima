<?php
ini_set('error_log', 'error_log');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
$ManagePanel = new ManagePanel();
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$scriptDir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$link        = ltrim(substr($requestPath, strlen($scriptDir)), '/');
header('Content-Type: text/plain; charset=utf-8');
$token = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
try {
if(!isset($token)){
    echo "ERROR!";
}
$nameloc = \Faoxima\Core\Database\Db::select("invoice","*","id_invoice",$token,"select");
$DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'],$nameloc['username']);
$config = "";
foreach ($DataUserOut['links'] as $Links){
    $config .= $Links."\r\r";
}
echo $config;
} catch (Exception $e) {
    echo "Error!";
}

