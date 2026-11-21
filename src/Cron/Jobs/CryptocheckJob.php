<?php

namespace Faoxima\Cron\Jobs;

use ManagePanel;
use PDO;
use Throwable;
use RuntimeException;

final class CryptocheckJob
{
    public static function run()
    {
        global $ManagePanel, $datatextbot, $from_id, $keyboard, $message_id, $pdo, $setting, $textbotlang, $threadId, $username;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
\Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('cryptocheck', 120);

ini_set('error_log', 'error_log');
if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('cryptocheck', [
    (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../keyboard.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../jdf.php',
])) {
    return;
}
if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('cryptocheck')) {
    return;
}
@require (dirname(__DIR__, 3) . '/cronbot') . '/../vendor/autoload.php';


if (!\Faoxima\Cron\Services\CryptoCheck::isAvailable()) {
    error_log('[cryptocheck] CryptoQuotes::cryptoCheckPayment is unavailable — aborting.');
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('crypto_helpers_unavailable', 300);
    return;
}

$ManagePanel = class_exists('ManagePanel') ? new ManagePanel() : null;
$setting = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', '*') : [];
$paymentreports = \Faoxima\Core\Database\Db::runtimeReady()
    ? (\Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null)
    : null;


if (method_exists(\Faoxima\Telegram\Conversation\ConversationData::class, 'languagechange') && is_file((dirname(__DIR__, 3) . '/cronbot') . '/../text.json')) {
    $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange((dirname(__DIR__, 3) . '/cronbot') . '/../text.json');
}
$datatextbotget = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('textbot', '*', null, null, 'fetchAll') : [];
$datatextbot = [
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'text_wgdashboard' => '',
];
if (is_array($datatextbotget)) {
    foreach ($datatextbotget as $row) {
        if (isset($row['id_text']) && (array_key_exists($row['id_text'], $datatextbot) || trim((string) ($row['text'] ?? '')) !== '')) {
            $datatextbot[$row['id_text']] = (string)($row['text'] ?? '');
        }
    }
}

$keyboard = null;

if (!isset($from_id)) $from_id = null;
if (!isset($message_id)) $message_id = null;

$pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
if (!($pdo instanceof PDO)) {
    error_log('[cryptocheck] no PDO connection');
    if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('pdo_unavailable', 180);
    }
    return;
}


$check = new \Faoxima\Cron\Services\CryptoCheck($pdo, $setting, $paymentreports, dirname(__DIR__, 3));
$check->expireStaleUnpaid();
$check->expireStaleAwaitingHash();
$check->configureRetryLimit();
$check->registerFatalLogger();
$check->refundStuckPaid();
$check->retryStuckServiceCreation();

$rows = $check->fetchPending();
if ($rows === null) {
    return;
}
$check->processPending($rows);

if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('time_budget', 60);
} else {
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('cryptocheck_finished');
}
    }
}
