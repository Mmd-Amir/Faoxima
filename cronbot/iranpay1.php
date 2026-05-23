<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('iranpay1', 180);

ini_set('error_log', 'error_log');

$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) { return; }
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $connect, $pdo, $setting;
$setting = $ctx['setting'];
$paymentreports = $ctx['paymentreports'];
$ManagePanel = $ctx['managePanel'];
$textbotlang = languagechange('../text.json');

$list_service = mysqli_query($connect, "SELECT * FROM Payment_report WHERE payment_Status = 'Unpaid' AND Payment_Method = 'Currency Rial 3' ORDER BY RAND() LIMIT 10");
while ($Payment_report = mysqli_fetch_assoc($list_service)) {

    if ($Payment_report['payment_Status'] == 'paid') continue;
    $StatusPayment = verifpay($Payment_report['dec_not_confirmed']);
    if (!is_string($StatusPayment)) continue;
    $StatusPayment = json_decode($StatusPayment, true);
    if (!is_array($StatusPayment)) continue;
    if (empty($StatusPayment['success'])) continue;
    if (($StatusPayment['data']['status'] ?? '') !== 'approved') continue;


    $atomicIranpay = $pdo->prepare(
        "UPDATE Payment_report SET dec_not_confirmed = :dec WHERE id_order = :id"
    );
    $atomicIranpay->bindValue(':dec', json_encode($StatusPayment), PDO::PARAM_STR);
    $atomicIranpay->bindValue(':id', $Payment_report['id_order'], PDO::PARAM_STR);
    $atomicIranpay->execute();

    payment_confirm_paid(
        $Payment_report['id_order'],
        'chashbackiranpay1',
        [
            'method'    => 'ارزی ریالی سوم',
            'thread_id' => $paymentreports,
        ]
    );
}
