<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;

final class ServiceReversal
{
    public static function refundHardDeleteService($runtimePdo, $panelName, $usernameService, $idInvoice)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $usernameService = trim((string) $usernameService);
        $idInvoice = trim((string) $idInvoice);
        $deleted = false;
        $panelType = '';
        if (trim((string) $panelName) !== '') {
            $panelRow = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $panelName, 'select');
            if (is_array($panelRow)) {
                $panelType = (string) ($panelRow['type'] ?? '');
            }
        }
        if ($panelType === 'Manualsale' && $usernameService !== '') {
            try {
                $stmt = $pdo->prepare("DELETE FROM manualsell WHERE username = :u");
                $stmt->bindValue(':u', $usernameService, PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $deleted = true;
                }
            } catch (Throwable $e) {
                error_log('rxRefundHardDeleteService manualsell failed: ' . $e->getMessage());
            }
        }
        if ($idInvoice !== '') {
            try {
                $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE assigned_invoice = :iid");
                $stmt->bindValue(':iid', $idInvoice, PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $deleted = true;
                    if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockLog')) {
                        try {
                            \Faoxima\Services\Fulfillment\Inventory::nmStockLog($runtimePdo, null, '', $idInvoice, 'refund_stock_deleted', ['username' => $usernameService]);
                        } catch (Throwable $e) {
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('rxRefundHardDeleteService nm_config_stock failed: ' . $e->getMessage());
            }
        }
        return $deleted;
        }
}
