<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('disableconfig', 120);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('disableconfig', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('disableconfig')) {
    return;
}
$ManagePanel = new ManagePanel();


$stmt = $pdo->prepare("SELECT id FROM user WHERE checkstatus = '2' ORDER BY RAND() LIMIT 10");
$stmt->execute();
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmts = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '{$result['id']}' AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')  ORDER BY RAND() LIMIT 10");
        $stmts->execute();
        $selectinvoice = $stmts->fetchAll();
        if($stmts->rowCount() == 0){
            update("user","checkstatus","0","id",$result['id']);
            continue;
            }
        foreach ($selectinvoice as $invoice){
        $get_username_Check = $ManagePanel->DataUser($invoice['Service_location'],$invoice['username']);
        if($get_username_Check['status'] == "active"){
        $userchengestatus = $ManagePanel->Change_status($invoice['username'],$invoice['Service_location']);
        }
        update("invoice","Status","disablebyadmin","id_invoice",$invoice['id_invoice']);
        }
    }
