<?php

require_once dirname(__DIR__) . '/src/bootstrap.php';

return \Faoxima\Cron\Jobs\XuiInboundSyncJob::run();
