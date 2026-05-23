<?php

use \radiusApi\Modules;

require_once 'ibsng/bootstrap.php';
require_once 'function.php';

function loginIBsng($url,$username,$password){
    try {
    $loginArray = [
    'username' => $username,
    'password' => $password,
    'hostname' => $url,
    'ssl' => false,
    'port' => 80,
    'timeout' => 2
];
$request = new Modules\IBSng($loginArray);
$result = $request->connect();
$request->disconnect();
if(is_bool($result)){
    $status = [
    'status' => true,
    'msg' => 'Successful login'
    ];
}else{
    $status = [
        'status' => false,
        'msg' => $result
    ];
}
return $status;
} catch (\Exception $ex) {
    $status = [
        'status' => false,
        'msg' => $ex->getMessage()
    ];
    return $status;
}
}


function addUserIBsng($name_panel,$username,$password,$group){
    $panel = select("marzban_panel","*","name_panel",$name_panel,"select");
    try {
    $loginArray = [
    'username' => $panel['username_panel'],
    'password' => $panel['password_panel'],
    'hostname' => $panel['url_panel'],
    'ssl' => false,
    'port' => 80,
    'timeout' => 4
];
    $request = new Modules\IBSng($loginArray);
    $result = $request->connect();
    $result = $request->addUser($username, $password, $group, '1');
    $request->disconnect();
    if(is_bool($result)){
    $status = [
    'status' => true,
    'msg' => 'Successful login'
    ];
    }else{
    $status = [
        'status' => false,
        'msg' => $result
    ];
}
    return $status;
} catch (\Exception $ex) {
    $status = [
        'status' => false,
        'msg' => $ex->getMessage()
    ];
    return $status;
}
}

function GetUserIBsng($name_panel,$username){
    $panel = select("marzban_panel","*","name_panel",$name_panel,"select");
    try {
    $loginArray = [
    'username' => $panel['username_panel'],
    'password' => $panel['password_panel'],
    'hostname' => $panel['url_panel'],
    'ssl' => false,
    'port' => 80,
    'timeout' => 4
];
    $request = new Modules\IBSng($loginArray);
    $result = $request->connect();
    $result = $request->getUser($username);
    $request->disconnect();
    if(isset($result['username'])){
    $status = [
    'status' => true,
    'data' => $result,
    'msg' => 'Successful'
    ];
    }else{
    $status = [
        'status' => false,
        'msg' => $result
    ];
}
    return $status;
} catch (\Exception $ex) {
    $status = [
        'status' => false,
        'msg' => $ex->getMessage()
    ];
    return $status;
}
}


function deleteUserIBSng($name_panel,$username){
    $panel = select("marzban_panel","*","name_panel",$name_panel,"select");
    try {
    $loginArray = [
    'username' => $panel['username_panel'],
    'password' => $panel['password_panel'],
    'hostname' => $panel['url_panel'],
    'ssl' => false,
    'port' => 80,
    'timeout' => 4
];
    $request = new Modules\IBSng($loginArray);
    $result = $request->connect();
    $result = $request->deleteUser($username);
    $request->disconnect();
    return $result;
} catch (\Exception $ex) {
    $status = [
        'status' => false,
        'msg' => $ex->getMessage()
    ];
    return $status;
}
}

