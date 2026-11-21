<?php

namespace Faoxima\Cron\Jobs;

use PDO;
use Throwable;

final class NowpaymentcheckJob
{
    public static function run()
    {
        global $pdo;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
\Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('nowpaymentcheck', 120);

ini_set('error_log', 'error_log');

$ctx = \Faoxima\Cron\Runtime\CronBatches::rx_cron_require_payment_context();
if ($ctx === null) {
    return;
}
global $pdo;

if (!($pdo instanceof PDO)) {
    error_log('[nowpaymentcheck] no PDO connection');
    return;
}

$logFile = (dirname(__DIR__, 3) . '/cronbot') . '/../logs/nowpayment_poll.log';
$log = static function (string $level, string $msg, array $ctx = []) use ($logFile): void {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] [nowpaymentcheck] ' . $msg;
    if (!empty($ctx)) {
        $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    if ($level === 'ERROR' || $level === 'WARN') {
        error_log($line);
    }
};

try {
    $rxPaymentWindow = \Faoxima\Payments\Fulfillment\PaymentPolicy::cronWindowSql();
    $rxNowPayCursor = method_exists(\Faoxima\Cron\Runtime\CronBatches::class, 'rx_cron_invoice_cursor') ? \Faoxima\Cron\Runtime\CronBatches::rx_cron_invoice_cursor('nowpayment_poll') : '';
    $rxNowPaySql = "SELECT id, id_order, dec_not_confirmed, price, id_user, time
           FROM Payment_report
          WHERE $rxPaymentWindow AND payment_Status = 'Unpaid'
            AND Payment_Method = 'nowpayment'
            AND dec_not_confirmed IS NOT NULL
            AND dec_not_confirmed <> ''
            AND id > ?
          ORDER BY id ASC
          LIMIT 30";
    $stmt = $pdo->prepare($rxNowPaySql);
    $stmt->execute([$rxNowPayCursor]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows && $rxNowPayCursor !== '') {
        $stmt->execute(['']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (!empty($rows) && method_exists(\Faoxima\Cron\Runtime\CronBatches::class, 'rx_cron_invoice_cursor')) {
        $rxNowPayLast = 0;
        foreach ($rows as $rxNowPayRow) {
            $rxNowPayLast = max($rxNowPayLast, (int) ($rxNowPayRow['id'] ?? 0));
        }
        if ($rxNowPayLast > 0) {
            \Faoxima\Cron\Runtime\CronBatches::rx_cron_invoice_cursor('nowpayment_poll', (string) $rxNowPayLast);
        }
    }
} catch (Throwable $e) {
    $log('ERROR', 'select pending failed', ['err' => $e->getMessage()]);
    if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('select_failed', 120);
    }
    return;
}

if (empty($rows)) {
    if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('no_pending_payments');
    }
    return;
}

$apiKeyRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'api_nowpayment', 'select');
$apiKey    = is_array($apiKeyRow) ? trim((string)($apiKeyRow['ValuePay'] ?? '')) : '';
if ($apiKey === '') {

    $apiKeyRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'marchent_tronseller', 'select');
    $apiKey    = is_array($apiKeyRow) ? trim((string)($apiKeyRow['ValuePay'] ?? '')) : '';
}
if ($apiKey === '') {
    $log('WARN', 'no NowPayments api key configured');
    if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('api_key_missing', 3600);
    }
    return;
}

$noticeMarker = (dirname(__DIR__, 3) . '/cronbot') . '/_nowpayment_poll_disabled.flag';
$noticeAge    = is_file($noticeMarker) ? (time() - (int) @filemtime($noticeMarker)) : 99999;
if ($noticeAge > 86400) {
    $log('INFO', 'NowPayments list-by-invoice endpoint requires JWT auth (not available with x-api-key). '
        . 'Polling DISABLED — relying on IPN only. Make sure IPN URL is set in NowPayments dashboard: '
        . 'https://' . ($GLOBALS['domainhosts'] ?? '?') . '/payment/nowpayment.php');
    @touch($noticeMarker);
}
if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('polling_disabled_by_provider');
}
    }
}
