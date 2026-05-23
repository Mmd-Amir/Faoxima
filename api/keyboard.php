<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');


$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'accountwallet' => '',
    'text_sell' => '',
    'text_Tariff_list' => '',
    'text_affiliates' => '',
    'text_wheel_luck' => '',
    'text_extend' => ''

);
$textdatabot =  select("textbot", "*", null, null,"fetchAll");
$data_text_bot = array();
foreach ($textdatabot as $row) {
    $data_text_bot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
foreach ($data_text_bot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
$keyboardmain = json_decode(select("setting","keyboardmain",null,null,"select")['keyboardmain'],true);

$list_keyboard = array(
    'text_sell',
    'text_extend',
    'text_usertest',
    'text_wheel_luck',
    'text_Purchased_services',
    'accountwallet',
    'text_affiliates',
    'text_Tariff_list',
    'text_support',
    'text_help',
    );
foreach ($keyboardmain['keyboard'] as $keyboard){
    foreach ($keyboard as $arrkey){
            if(in_array($arrkey['text'],$list_keyboard)){
                $index_number = array_search($arrkey['text'],$list_keyboard);
                unset($list_keyboard[$index_number]);
        }
    }
}
$list_keyboard = array_values($list_keyboard);
$keyboard = [];
foreach($list_keyboard as $key){
    $keyboard[] = [['text' => $key]];
}

$list_data = [
    'keylist' => $keyboard,
    'userlist' => $keyboardmain['keyboard'],
    'text' => $datatextbot
];
echo json_encode($list_data);
