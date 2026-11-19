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
    'rialit' => '',
    'cubepay' => '',
    'blupal' => '',
    'variza' => '',
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
$month_date_time_start = date('Y/m/d H:i:s', time() - 1800);
try {
    $hasCardVerification = function_exists('rx_sbv_has_schema') && rx_sbv_has_schema();
    $expireSql = $hasCardVerification
        ? "SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE payment_Status = 'Unpaid' AND (crypto_currency IS NULL OR crypto_currency = '') AND Payment_Method NOT IN ('tonpay','rialit') AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND (payment_processing_version IS NULL OR payment_processing_version < 2 OR (COALESCE(atlaspay_local_state,'') <> 'manual_review' AND COALESCE(tronado_local_state,'') NOT IN ('manual_review','dispute_review'))) AND ((time < :cutoff AND NOT EXISTS (SELECT 1 FROM card_sms_payment csp WHERE csp.id_order = Payment_report.id_order)) OR EXISTS (SELECT 1 FROM card_sms_payment csp2 WHERE csp2.id_order = Payment_report.id_order AND csp2.state = 'open' AND csp2.deposit_id IS NULL AND csp2.expires_at + :grace <= :nowts)) AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120"
        : "SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND (crypto_currency IS NULL OR crypto_currency = '') AND Payment_Method NOT IN ('tonpay','rialit') AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND (payment_processing_version IS NULL OR payment_processing_version < 2 OR (COALESCE(atlaspay_local_state,'') <> 'manual_review' AND COALESCE(tronado_local_state,'') NOT IN ('manual_review','dispute_review'))) AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120";
    $stmt = $pdo->prepare($expireSql);
    $rows = rx_cron_payment_cursor_page($pdo, 'payment_expire', static function (string $after, $ceiling) use ($stmt, $month_date_time_start, $hasCardVerification): array {
        $params = [':cutoff' => $month_date_time_start, ':cursor' => $after, ':ceiling' => $ceiling];
        if ($hasCardVerification) {
            $params[':grace'] = RX_SBV_EXPIRE_GRACE;
            $params[':nowts'] = time();
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });

    $tonpayCutoff = date('Y/m/d H:i:s', time() - 86400);
    $tonpayStmt = null;
    $tonpayRows = rx_cron_payment_cursor_page($pdo, 'payment_expire_tonpay', static function (string $after, $ceiling) use ($pdo, $tonpayCutoff, &$tonpayStmt): array {
        $params = [':cutoff' => $tonpayCutoff, ':cursor' => $after, ':ceiling' => $ceiling];
        if ($tonpayStmt === null) {
            try {
                $tonpayStmt = $pdo->prepare("SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' AND (tonpay_gateway_mode IS NULL OR tonpay_gateway_mode <> 'custom') AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120");
                $tonpayStmt->execute($params);
            } catch (Throwable $e) {
                $tonpayStmt = $pdo->prepare("SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120");
                $tonpayStmt->execute($params);
            }
        } else {
            $tonpayStmt->execute($params);
        }
        return $tonpayStmt->fetchAll(PDO::FETCH_ASSOC);
    });
    $rows = array_merge($rows, $tonpayRows);
} catch (Throwable $e) {
    error_log('[payment_expire] select pending failed: ' . get_class($e));
    rx_cron_task_defer('select_failed', 120);
    return;
}
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

rx_cron_run_poll_batch(
    $rows,
    static function (array $result) use ($expireStmt): void {

        $rxCardExpired = null;
        if (function_exists('rxCancelAbandonedCardPayment') && in_array((string) $result['Payment_Method'], rxCardPaymentMethods(), true)) {
            $rxCardExpired = rxCancelAbandonedCardPayment((string) $result['id_order'], (string) $result['id_user'], 'timeout', ['status' => 'expire']);
        }
        if (!is_array($rxCardExpired) || empty($rxCardExpired['ok'])) {
            if (is_array($rxCardExpired) && in_array(($rxCardExpired['reason'] ?? ''), ['db_error', 'verify_locked', 'verify_window_active'], true)) {
                return;
            }
            $expireStmt->execute([':o' => $result['id_order']]);
            if ($expireStmt->rowCount() !== 1) {
                return;
            }
        }
        if (function_exists('rx_redis_del') && isset($result['id_user'])) {
            rx_redis_del(rx_payment_status_cache_key($result['id_order'], $result['id_user']));
        }
        if (function_exists('rx_release_unpaid_discount')) {
            $rxRefTime = isValidDate($result['time'] ?? '') ? strtotime(str_replace('/', '-', (string)$result['time'])) : null;
            rx_release_unpaid_discount((string)$result['id_user'], null, $rxRefTime ?: null);
        }
        deletemessage($result['id_user'], $result['message_id']);
    },
    static function (array $result, Throwable $e): void {
        error_log('[payment_expire] order ' . (string) $result['id_order'] . ': ' . $e->getMessage());
    },
    static function (array $result): void {
        $cursorJob = $result['Payment_Method'] === 'tonpay' ? 'payment_expire_tonpay' : 'payment_expire';
        rx_cron_invoice_cursor($cursorJob, (string) $result['id']);
    }
);
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('payment_expire_finished');
}
