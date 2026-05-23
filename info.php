<!-- <?php

ini_set('error_log', 'error_log');
$textbotlang = json_decode(file_get_contents('text.json'),true)['fa'];
$allowed_ips = ['175.110.112.75', '212.34.128.12', '185.121.233.240','185.121.234.252','23.88.114.164','77.238.254.190','212.34.136.224','212.34.131.122','91.107.189.30','5.114.190.202','77.105.143.235','212.111.82.229','91.84.108.186','195.200.31.202','176.65.128.181','212.111.81.228','176.65.128.55','176.65.128.80','195.26.224.59','37.49.227.241','92.246.87.146'];

$user_ip = $_SERVER['REMOTE_ADDR'];

if (in_array($user_ip, $allowed_ips)) {
    if (isset($_GET['adddomain'])){
        $data = json_decode(file_get_contents('domains.json'));
        if(!in_array($_GET['adddomain'],$data)){

        $data[] = $_GET['adddomain'];
        file_put_contents('domains.json',json_encode($data,true));
        }
    }
    elseif(isset($_GET['checkupdate'])){
        $datacheck = json_decode(file_get_contents('updatedenide.json'),true);
        if(!in_array($_GET['checkupdate'],$datacheck) or !isset($datacheck[$_GET['checkupdate']])){
            echo null;
            return;
        }
        echo htmlspecialchars((string)$datacheck[$_GET['checkupdate']], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }


} else {
   echo "شما مجاز نیستید";
}
?> -->


