<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('plisio', 180);

ini_set('error_log', 'error_log');

$ctx = rx_cron_require_payment_context();
if ($ctx === null) {
    return;
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $connect, $pdo, $setting;
$connect = function_exists('getMysqliConnection') ? getMysqliConnection() : null;
if (!($connect instanceof mysqli)) {
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('mysqli_unavailable', 120);
    }
    return;
}
$setting = $ctx['setting'];
$paymentreports = $ctx['paymentreports'];
$ManagePanel = $ctx['managePanel'];


function statusplisio($tx_id){
    global $connect;
    static $api_key = null;
    if ($api_key === null) {
        $rowPlisio = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'api_plisio'"));
        $api_key = is_array($rowPlisio) ? trim((string)($rowPlisio['ValuePay'] ?? '')) : '';
        if ($api_key === '' || $api_key === '0') {
            $rowLegacy = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'apinowpayment'"));
            $api_key = is_array($rowLegacy) ? trim((string)($rowLegacy['ValuePay'] ?? '')) : '';
        }
    }
    $url = 'https://api.plisio.net/api/v1/operations?';
    $url .= '&api_key=' . urlencode($api_key);
    $url .= '&search='.$tx_id;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

list($rxW, $rxN) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$rxShard = ($rxN > 1) ? " AND MOD(id, $rxN) = $rxW " : "";
$rxPaymentWindow = rx_pf_cron_window_sql();
$rxPlisioCursorKey = 'plisio_poll:' . $rxN . ':' . $rxW;
$rxPlisioSql = "SELECT id, id_order FROM Payment_report WHERE $rxPaymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'plisio'$rxShard AND id > ? AND id <= ? ORDER BY id ASC LIMIT 15";
try {
    $stmt = $connect->prepare($rxPlisioSql);
    if ($stmt === false) {
        throw new RuntimeException('Plisio payment selection could not be prepared');
    }
    try {
        $rxPlisioRows = rx_cron_payment_cursor_page($pdo instanceof PDO ? $pdo : null, $rxPlisioCursorKey, static function (string $after, $ceiling) use ($stmt): array {
            $through = (string) $ceiling;
            $stmt->bind_param('ss', $after, $through);
            if (!$stmt->execute() || !($result = $stmt->get_result())) {
                throw new RuntimeException('Plisio payment selection failed');
            }
            try {
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                return $rows;
            } finally {
                $result->free();
            }
        });
    } finally {
        $stmt->close();
    }
} catch (Throwable $e) {
    error_log('[plisio] poll selection failed: ' . get_class($e));
    rx_cron_task_defer('select_failed', 120);
    return;
}
rx_cron_run_poll_batch(
    $rxPlisioRows,
    static function (array $row) use (&$connect, $paymentreports): void {
        $reportStmt = $connect->prepare("SELECT id_order, price, dec_not_confirmed, payment_Status FROM Payment_report WHERE id_order = ? LIMIT 1");
        $reportStmt->bind_param('s', $row['id_order']);
        $reportStmt->execute();
        $Payment_report = $reportStmt->get_result()->fetch_assoc();
        $reportStmt->close();
        if (!is_array($Payment_report)) return;
        if ($Payment_report['payment_Status'] == 'paid') return;
        if (!isset($Payment_report['dec_not_confirmed']) || $Payment_report['dec_not_confirmed'] == null) return;

        $StatusPayment = statusplisio($Payment_report['id_order']);

        if (!is_array($StatusPayment) || !isset($StatusPayment['data']['operations'][0]['status'])) {
            return;
        }
        $opStatus = $StatusPayment['data']['operations'][0]['status'];

        if ($opStatus === 'cancelled' || $opStatus === 'expired') {
            $textexpire = "❌ تراکنش زیر بدلیل عدم پرداخت منقضی شد، لطفا وجهی بابت این تراکنش پرداخت نکنید\n\n🛒 کد سفارش: {$Payment_report['id_order']}\n💰 مبلغ:  " . rxFormatToman($Payment_report['price']) . " تومان";
            payment_mark_expired($Payment_report['id_order'], $textexpire);
            return;
        }

        if ($opStatus === 'completed') {
            $op         = $StatusPayment['data']['operations'][0] ?? [];
            $invoiceUrl = (string)($op['invoice_url']        ?? ($StatusPayment['invoice_url']        ?? ''));
            $sourceAmt  = trim((string)($op['source_amount'] ?? ''));
            $invoiceAmt = trim((string)($op['amount']         ?? ($StatusPayment['invoice_total_sum'] ?? '')));
            $sourceCur  = trim((string)($op['source_currency']?? ''));
            $invoiceCur = trim((string)($op['currency']       ?? ''));
            $paidAmount = $sourceAmt !== '' && $sourceAmt !== '0' ? $sourceAmt : $invoiceAmt;
            $paidCurrency = $sourceCur !== '' ? $sourceCur : $invoiceCur;
            $txUrl0     = (string)($op['tx_url'][0] ?? ($StatusPayment['tx_url'][0] ?? ''));

            if ($paidAmount !== '') {
                try {
                    update('Payment_report', 'crypto_amount', $paidAmount, 'id_order', $Payment_report['id_order']);
                } catch (\Throwable $e) {
                    error_log('[plisio] crypto amount persistence failed: ' . get_class($e));
                }
            }
            if ($paidCurrency !== '') {
                try {
                    update('Payment_report', 'crypto_currency', strtoupper($paidCurrency), 'id_order', $Payment_report['id_order']);
                } catch (\Throwable $e) {
                    error_log('[plisio] crypto currency persistence failed: ' . get_class($e));
                }
            }

            $paidLine = rx_plisio_paid_line($paidAmount, $paidCurrency);
            $extraLines = [$paidLine];
            if ($txUrl0 !== '') {
                $extraLines[] = "🔗 <a href=\"{$txUrl0}\">لینک تراکنش </a>";
            }

            payment_confirm_paid(
                $Payment_report['id_order'],
                'chashbackplisio',
                [
                    'method'      => 'plisio',
                    'link_label'  => 'لینک پرداخت plisio',
                    'link_url'    => $invoiceUrl,
                    'thread_id'   => $paymentreports,
                    'extra_lines' => $extraLines,
                ]
            );
        }
    },
    static function (array $row, Throwable $e): void {
        error_log('[plisio] poll error for order ' . (string) ($row['id_order'] ?? '') . ': ' . $e->getMessage());
    },
    static function (array $row) use ($rxPlisioCursorKey): void {
        rx_cron_invoice_cursor($rxPlisioCursorKey, (string) $row['id']);
    }
);
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('plisio_poll_finished');
}
