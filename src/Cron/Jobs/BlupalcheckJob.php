<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\PaymentJob;
use Faoxima\Cron\Services\BlupalPoller;
use Faoxima\Payments\Gateways\Blupal\Client;

final class BlupalcheckJob
{
    public static function run()
    {
        global $ManagePanel, $pdo;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        $ctx = PaymentJob::begin('blupalcheck', 120);
        if ($ctx === null) {
            return;
        }
        $ManagePanel = $ctx['managePanel'];

        if (!PaymentJob::requireGateway('blupalcheck', 'blupal', [Client::class, 'checkInvoice'], 'blupal_functions_unavailable')) {
            return;
        }

        (new BlupalPoller($pdo, $ctx['paymentreports'] ?? null))->run();
    }
}
