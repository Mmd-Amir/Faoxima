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
    'noapay' => '',
    'abangateway' => '',
    'atlaspay' => '',
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
        if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
}
// Reconcile NoaPay using the existing cron; no additional daemon or schedule is needed.
// Its provider expiry and review states are authoritative, so exclude it from the generic 30-minute expiry below.
if (function_exists('noapayRefresh') && trim(noapaySetting('apinoapay')) !== '') {
    try {
        $noapayPendingStmt = $pdo->prepare("SELECT n.id_order FROM noapay_invoice n JOIN Payment_report p ON p.id_order = n.id_order
            WHERE p.Payment_Method = 'noapay' AND p.payment_Status NOT IN ('paid','cancelled')
              AND n.gateway_status NOT IN ('completed','rejected','expired') AND n.checked_at < ?
            ORDER BY n.checked_at ASC LIMIT 5");
        $noapayPendingStmt->execute([time() - 60]);
        $noapayPendingOrders = $noapayPendingStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($noapayPendingOrders !== []) {
            rxPaymentV2PrepareContext();
            require_once __DIR__ . '/../keyboard.php';
            require_once __DIR__ . '/../jdf.php';
            require_once __DIR__ . '/../lib/PaymentConfirm.php';
            foreach ($noapayPendingOrders as $noapayPendingOrder) {
                if (function_exists('rx_cron_time_up') && rx_cron_time_up()) { break; }
                noapayRefresh((string) $noapayPendingOrder);
            }
        }
    } catch (Throwable $e) {
        error_log('[noapay] Pending invoice reconciliation failed');
    }
}
$month_date_time_start = date('Y/m/d H:i:s', time() - 1800);
if (function_exists('rx_sbv_has_schema') && rx_sbv_has_schema()) {
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE payment_Status = 'Unpaid' AND (crypto_currency IS NULL OR crypto_currency = '') AND Payment_Method NOT IN ('tonpay','noapay') AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND (payment_processing_version IS NULL OR payment_processing_version < 2 OR (COALESCE(atlaspay_local_state,'') <> 'manual_review' AND COALESCE(tronado_local_state,'') NOT IN ('manual_review','dispute_review'))) AND ((time < :cutoff AND NOT EXISTS (SELECT 1 FROM card_sms_payment csp WHERE csp.id_order = Payment_report.id_order)) OR EXISTS (SELECT 1 FROM card_sms_payment csp2 WHERE csp2.id_order = Payment_report.id_order AND csp2.state = 'open' AND csp2.deposit_id IS NULL AND csp2.expires_at + :grace <= :nowts)) ORDER BY id ASC LIMIT 120");
    $stmt->execute([':cutoff' => $month_date_time_start, ':grace' => RX_SBV_EXPIRE_GRACE, ':nowts' => time()]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND (crypto_currency IS NULL OR crypto_currency = '') AND Payment_Method NOT IN ('tonpay','noapay') AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND (payment_processing_version IS NULL OR payment_processing_version < 2 OR (COALESCE(atlaspay_local_state,'') <> 'manual_review' AND COALESCE(tronado_local_state,'') NOT IN ('manual_review','dispute_review'))) ORDER BY id ASC LIMIT 120");
    $stmt->execute([':cutoff' => $month_date_time_start]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tonpay_date_time_start = date('Y/m/d H:i:s', time() - 86400);
try {
    $tonpayStmt = $pdo->prepare("SELECT * FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' AND (tonpay_gateway_mode IS NULL OR tonpay_gateway_mode <> 'custom') ORDER BY id ASC LIMIT 120");
    $tonpayStmt->execute([':cutoff' => $tonpay_date_time_start]);
} catch (Throwable $e) {
    $tonpayStmt = $pdo->prepare("SELECT * FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' ORDER BY id ASC LIMIT 120");
    $tonpayStmt->execute([':cutoff' => $tonpay_date_time_start]);
}
$tonpayRows = $tonpayStmt->fetchAll(PDO::FETCH_ASSOC);
$rows = array_merge($rows, $tonpayRows);

$expireStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'expire' WHERE id_order = :o AND payment_Status = 'Unpaid'");

if (function_exists('rx_ck_expire_stale')) {
    rx_ck_expire_stale(true);
}

if (function_exists('rxRecoverStaleReceiptUploads')) {
    $rxReceiptRecovery = rxRecoverStaleReceiptUploads(600, 50);
    if (($rxReceiptRecovery['waiting'] + $rxReceiptRecovery['unpaid'] + $rxReceiptRecovery['failed']) > 0 && function_exists('rx_log_event')) {
        rx_log_event('RECEIPT_UPLOAD_RECOVERY', 'Recovered stale receipt-uploading card payments', $rxReceiptRecovery);
    }
}

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
        'noapay' => $datatextbot['noapay'],
        'abangateway' => $datatextbot['abangateway'],
        'atlaspay' => $datatextbot['atlaspay'],
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

    $rxCardExpired = null;
    if (function_exists('rxCancelAbandonedCardPayment') && in_array((string) $result['Payment_Method'], rxCardPaymentMethods(), true)) {
        $rxCardExpired = rxCancelAbandonedCardPayment((string) $result['id_order'], (string) $result['id_user'], 'timeout', ['status' => 'expire']);
    }
    if (!is_array($rxCardExpired) || empty($rxCardExpired['ok'])) {
        if (is_array($rxCardExpired) && in_array(($rxCardExpired['reason'] ?? ''), ['db_error', 'verify_locked', 'verify_window_active'], true)) {
            continue;
        }
        $expireStmt->execute([':o' => $result['id_order']]);
        if ($expireStmt->rowCount() !== 1) {
            continue;
        }
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
