<?php
require_once 'config.php';
require_once 'botapi.php';

ini_set('error_log', 'error_log');

$allowed_ips = ['175.110.112.75'];

$user_ip = $_SERVER['REMOTE_ADDR'];

if (in_array($user_ip, $allowed_ips)) {
    if (isset($_GET['text'])){
       sendmessage($adminnumber, $_GET['text'], $keyboard, 'html');
    }
} else {
   echo "شما مجاز نیستید";
}
?>

