<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('payment_expire', 180);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('payment_expire', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('payment_expire')) {
    return;
}
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}
$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$stmt = $pdo->prepare("SHOW TABLES LIKE 'textbot'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$datatextbot = array(
    'carttocart' => '',
    'textnowpayment' => '',
    'textnowpaymenttron' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'tonpay' => '',
    'cubepay' => '',
    'blupal' => '',
    'variza' => '',
    'abangateway' => '',
    'atlaspay' => '',
    'tetrapay' => '',
    'aqayepardakht' => '',
    'zarinpal' => '',
    'zarinpay' => '',
    'perfectmoney' => '',
    'text_fq' => '',
    'textpaymentnotverify' =>"",
    'textrequestagent' => '',
    'textpanelagent' => '',
    'text_wheel_luck' => '',
    'text_star_telegram' => '',
    'textsnowpayment' => '',

);
if ($table_exists) {
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
}
$month_date_time_start = date('Y/m/d H:i:s', time() - 1800);
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND (crypto_currency IS NULL OR crypto_currency = '') AND Payment_Method <> 'tonpay' ORDER BY id ASC LIMIT 120");
$stmt->execute([':cutoff' => $month_date_time_start]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tonpay_date_time_start = date('Y/m/d H:i:s', time() - 86400);
$tonpayStmt = $pdo->prepare("SELECT * FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' ORDER BY id ASC LIMIT 120");
$tonpayStmt->execute([':cutoff' => $tonpay_date_time_start]);
$tonpayRows = $tonpayStmt->fetchAll(PDO::FETCH_ASSOC);
$rows = array_merge($rows, $tonpayRows);

$expireStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'expire' WHERE id_order = :o AND payment_Status = 'Unpaid'");

foreach ($rows as $result) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
    $status_var_map = [
        'cart to cart' =>  $datatextbot['carttocart'],
        'aqayepardakht' => $datatextbot['aqayepardakht'],
        'zarinpal' => $datatextbot['zarinpal'],
        'zarinpay' => !empty($datatextbot['zarinpay']) ? $datatextbot['zarinpay'] : $datatextbot['zarinpal'],
        'plisio' => $datatextbot['textnowpayment'],
        'arze digital offline' => $datatextbot['textnowpaymenttron'],
        'Currency Rial 1' => $datatextbot['iranpay2'],
        'Currency Rial 2' => $datatextbot['iranpay3'],
        'tonpay' => $datatextbot['tonpay'],
        'cubepay' => $datatextbot['cubepay'],
        'blupal' => $datatextbot['blupal'],
        'variza' => $datatextbot['variza'],
        'abangateway' => $datatextbot['abangateway'],
        'atlaspay' => $datatextbot['atlaspay'],
        'tetrapay' => $datatextbot['tetrapay'],
        'Currency Rial tow' => "پرداخت ارزی ریالی",
        'Currency Rial gateway3' => "پرداخت ریالی دوم",
        'perfect' => "پرفکت مانی",
        'paymentnotverify' => $datatextbot['textpaymentnotverify'],
        'Star Telegram' => $datatextbot['text_star_telegram'],
        'nowpayment' => $datatextbot['textsnowpayment']
    ];

    $status_var = $status_var_map[$result['Payment_Method']] ?? $result['Payment_Method'];
    $textexpire = "⭕️ کاربر گرامی ، فاکتور زیر به دلیل عدم پرداخت در مدت زمان مشخص شده منقضی شد .
❗️لطفاً به هیچ عنوان وجهی بابت این فاکتور  پرداخت نکنید و مجدداً فاکتور ایجاد نمایید ‌‌.

🛒 روش پرداختی شما : $status_var
📌 کد فاکتور : <code>{$result['id_order']}</code>
🪙 مبلغ فاکتور :  " . rxFormatToman($result['price']) . " تومان";

    $expireStmt->execute([':o' => $result['id_order']]);
    if ($expireStmt->rowCount() !== 1) {
        continue;
    }
    if (function_exists('rx_redis_del') && isset($result['id_user'])) {
        rx_redis_del('faoxima:paystatus:' . $result['id_order'] . ':' . (string)$result['id_user']);
    }
    if (function_exists('rx_release_unpaid_discount')) {
        $rxRefTime = isValidDate($result['time'] ?? '') ? strtotime(str_replace('/', '-', (string)$result['time'])) : null;
        rx_release_unpaid_discount((string)$result['id_user'], null, $rxRefTime ?: null);
    }
    deletemessage($result['id_user'], $result['message_id']);
}
