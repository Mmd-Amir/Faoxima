<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('plisio', 180);

ini_set('error_log', 'error_log');

$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) { return; }
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $connect, $pdo, $setting;
$setting = $ctx['setting'];
$paymentreports = $ctx['paymentreports'];
$ManagePanel = $ctx['managePanel'];


function statusplisio($tx_id){
    global $connect;
    $rowPlisio = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'api_plisio'"));
    $api_key = is_array($rowPlisio) ? trim((string)($rowPlisio['ValuePay'] ?? '')) : '';
    if ($api_key === '' || $api_key === '0') {
        $rowLegacy = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'apinowpayment'"));
        $api_key = is_array($rowLegacy) ? trim((string)($rowLegacy['ValuePay'] ?? '')) : '';
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

$list_service = mysqli_query($connect, "SELECT * FROM Payment_report WHERE payment_Status = 'Unpaid' AND Payment_Method = 'plisio'");
while ($row = mysqli_fetch_assoc($list_service)) {

    $reportStmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
    $reportStmt->bind_param('s', $row['id_order']);
    $reportStmt->execute();
    $Payment_report = $reportStmt->get_result()->fetch_assoc();
    $reportStmt->close();
    if (!is_array($Payment_report)) continue;
    if ($Payment_report['payment_Status'] == 'paid') continue;
    if (!isset($Payment_report['dec_not_confirmed']) || $Payment_report['dec_not_confirmed'] == null) continue;

    $StatusPayment = statusplisio($Payment_report['id_order']);
    $opStatus = $StatusPayment['data']['operations'][0]['status'] ?? null;

    if ($opStatus === null || $opStatus === 'cancelled') {
        $textexpire = "❌ تراکنش زیر بدلیل عدم پرداخت منقضی شد، لطفا وجهی بابت این تراکنش پرداخت نکنید\n\n🛒 کد سفارش: {$Payment_report['id_order']}\n💰 مبلغ:  {$Payment_report['price']} تومان";
        payment_mark_expired($Payment_report['id_order'], $textexpire);
        continue;
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
            } catch (\Throwable $e) {}
        }
        if ($paidCurrency !== '') {
            try {
                update('Payment_report', 'crypto_currency', strtoupper($paidCurrency), 'id_order', $Payment_report['id_order']);
            } catch (\Throwable $e) {}
        }

        $paidLine = $paidAmount !== ''
            ? ("📥 مبلغ واریز شده : <b>{$paidAmount}</b>" . ($paidCurrency !== '' ? ' ' . strtoupper($paidCurrency) : ''))
            : "📥 مبلغ واریز شده : —";
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
}
