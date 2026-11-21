<?php

namespace Faoxima\Admin\Telegram\Support;

/**
 * Handler stage. Inherited by value: $adminExecution.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class OptimizerFunctions
{
    public static function handle(
        $adminExecution
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        if (!method_exists(\Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::class, 'rx_optimizer_state')) {
            if (!defined('RX_OPT_LOCK_KEY')) {
                define('RX_OPT_LOCK_KEY', 'faoxima:optimizer:cleanup:lock');
                define('RX_OPT_PROGRESS_KEY', 'faoxima:optimizer:cleanup:progress');
                define('RX_OPT_CANCEL_PREFIX', 'faoxima:optimizer:cleanup:cancel:');
                define('RX_OPT_LOCK_TTL', 3600);
                define('RX_OPT_ACTIVE_TTL', 7200);
                define('RX_OPT_FINAL_TTL', 86400);
                define('RX_OPT_STALE_AFTER', 60);
                define('RX_OPT_TOTAL_STAGES', 10);
            }

            

            

            

            

            

            

            

            

            

            

            

            
        }

        return true;
    }
}
