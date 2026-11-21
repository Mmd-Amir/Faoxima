<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminExecution, $adminrulecheck, $callback_query_id, $datain, $from_id, $liveInvoice, $message_id, $pdo, $rxOptOrphanedBatch, $rxOptScanCount, $rxOptScanStmt, $rxOptTicketCount, $rxOptTicketStmt, $scanResult.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class OptimizerAndCronReports
{
    public static function handle(
        $ManagePanel,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $callback_query_id,
        $datain,
        $from_id,
        $liveInvoice,
        $message_id,
        $pdo,
        $rxOptOrphanedBatch,
        $rxOptScanCount,
        $rxOptScanStmt,
        $rxOptTicketCount,
        $rxOptTicketStmt,
        $scanResult
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($datain == "optimizebot_refresh" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($datain == "optimizebot_cancel" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif ($datain == "optimizebot" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\OptimizerAndCronReports::handle(
            ManagePanel: $ManagePanel ?? null,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            callback_query_id: $callback_query_id ?? null,
            from_id: $from_id ?? null,
            liveInvoice: $liveInvoice ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            rxOptOrphanedBatch: $rxOptOrphanedBatch ?? null,
            rxOptScanCount: $rxOptScanCount ?? null,
            rxOptScanStmt: $rxOptScanStmt ?? null,
            rxOptTicketCount: $rxOptTicketCount ?? null,
            rxOptTicketStmt: $rxOptTicketStmt ?? null,
            scanResult: $scanResult ?? null
        );
    }
}
