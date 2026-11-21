<?php

namespace Faoxima\Cron\Jobs;

use ManagePanel;
use RuntimeException;

final class GiftJob
{
    public static function run()
    {
        global $ManagePanel, $datatextbot, $errorreport, $pdo, $setting, $text, $username;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('gift', 240);

        ini_set('error_log', 'error_log');
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('gift', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('gift')) {
            return;
        }
        $ManagePanel = new ManagePanel();

        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        $errorreport = \Faoxima\Core\Database\Db::select("topicid","idreport","report","errorreport","select")['idreport'];
        $datatextbot = \Faoxima\Cron\Services\GiftCampaign::loadButtonTexts();

        $campaign = new \Faoxima\Cron\Services\GiftCampaign($ManagePanel, $pdo, $setting, $errorreport, dirname(__DIR__, 3));
        if (!$campaign->loadNotifications()) {
            return;
        }
        $campaign->drainNotifications();
        if (!$campaign->hasQueue()) {
            $campaign->finishWithoutQueue();
            return;
        }

        $campaign->loadQueue();
        if ($campaign->isQueueEmpty()) {
            $campaign->finishEmptyQueue();
            return;
        }
        if (!$campaign->prepareCampaign()) {
            return;
        }
        $campaign->processBatch();
        $campaign->finishBatch();
    }
}
