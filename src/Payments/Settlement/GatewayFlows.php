<?php

namespace Faoxima\Payments\Settlement;

use PDO;
use Throwable;

final class GatewayFlows
{
    public static function paymentV2Is($report)
    {
        return is_array($report) && (int) ($report['payment_processing_version'] ?? 0) >= RX_PAYMENT_PROCESSING_VERSION;
        }

    public static function paymentV2Mark($runtimePdo, $orderId, array $columns = [])
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $columns = array_merge(['payment_processing_version' => RX_PAYMENT_PROCESSING_VERSION], $columns);
        $sets = [];
        $params = [];
        foreach ($columns as $name => $value) {
            $sets[] = $name . ' = ?';
            $params[] = $value;
        }
        $params[] = (string) $orderId;
        try {
            $stmt = $pdo->prepare("UPDATE Payment_report SET " . implode(', ', $sets) . " WHERE id_order = ?");
            $stmt->execute($params);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('rxPaymentV2Mark failed: ' . $e->getMessage());
            return false;
        }
        }

    public static function paymentV2Escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
}
