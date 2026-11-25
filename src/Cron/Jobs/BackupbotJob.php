<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Backup\BackupRun;

final class BackupbotJob
{
    public static function run()
    {
        global $logFilePath;

        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        $root = dirname(__DIR__, 3);
        BackupRun::authorizeRequest($root);
        BackupRun::configureEnvironment();
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_attach('backupbot', 600);
        BackupRun::acquireLock($root);
        BackupRun::detachFromRequest();
        $logFilePath = BackupRun::configureLogging($root);
        BackupRun::execute($root);
    }
}
