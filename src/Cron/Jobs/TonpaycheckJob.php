<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\PaymentJob;
use Faoxima\Cron\Services\TonpayPoller;
use Faoxima\Payments\Gateways\Tonpay\Client;
use Faoxima\Payments\Gateways\Tonpay\Recovery;

final class TonpaycheckJob
{
    public static function run()
    {
        global $ManagePanel, $pdo, $setting;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        if ((int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0) > 0) {
            return;
        }
        $ctx = PaymentJob::begin('tonpaycheck', 60);
        if ($ctx === null) {
            return;
        }
        $ManagePanel = $ctx['managePanel'];
        $setting = $ctx['setting'];

        if (!PaymentJob::requireGateway('tonpaycheck', 'tonpay', [Client::class, 'checkInvoice'], 'tonpay_functions_unavailable')) {
            return;
        }
        if (is_callable([Recovery::class, 'runCron'])) {
            Recovery::runCron($pdo, $setting);
        }

        (new TonpayPoller($pdo, $setting, $ctx['paymentreports'] ?? null))->run();
    }
}
