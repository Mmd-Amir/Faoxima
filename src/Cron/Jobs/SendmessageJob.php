<?php

declare(strict_types=1);

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Broadcast\BroadcastWorker;

final class SendmessageJob
{
    public static function run()
    {
        global $datatextbot, $pdo;

        BroadcastWorker::configureRuntime();

        $baseDir = dirname((dirname(__DIR__, 3) . '/cronbot/sendmessage.php'));
        require_once $baseDir . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_verify_web_access();

        $requiredFiles = [
            $baseDir . '/../config.php',
            $baseDir . '/../botapi.php',
            $baseDir . '/../function.php',
        ];
        if (BroadcastWorker::missingRequiredFile($baseDir, $requiredFiles) !== null) {
            return;
        }
        foreach ($requiredFiles as $requiredFile) {
            require_once $requiredFile;
        }

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('sendmessage')) {
            BroadcastWorker::pauseForDatabaseOutage($baseDir);
            return;
        }

        $worker = new BroadcastWorker($baseDir, $pdo);
        $worker->acquireLock();
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_attach('sendmessage', 120);
        $worker->registerFatalHandler();
        $worker->run();
    }
}
