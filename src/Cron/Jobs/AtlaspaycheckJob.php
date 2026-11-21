<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\PaymentJob;
use Faoxima\Cron\Services\AtlaspayPoller;
use Faoxima\Payments\Gateways\Atlaspay\Client;
use Faoxima\Payments\Gateways\Atlaspay\Recovery;

final class AtlaspaycheckJob
{
    public static function run()
    {
        global $ManagePanel, $pdo, $setting, $text;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        $ctx = PaymentJob::begin('atlaspaycheck', 60);
        if ($ctx === null) {
            return;
        }
        $ManagePanel = $ctx['managePanel'];
        $setting = $ctx['setting'];

        if (!PaymentJob::requireGateway('atlaspaycheck', 'atlaspay', [Client::class, 'checkOrder'], 'atlaspay_functions_unavailable')) {
            return;
        }
        if (is_callable([Recovery::class, 'runCron'])) {
            Recovery::runCron($pdo, $setting);
        }

        (new AtlaspayPoller($pdo, $setting, $ctx['paymentreports'] ?? null))->run();
    }
}
