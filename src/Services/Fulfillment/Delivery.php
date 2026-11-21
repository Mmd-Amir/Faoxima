<?php

namespace Faoxima\Services\Fulfillment;

use Throwable;

final class Delivery
{
    public static function attemptClaimedDelivery(string $orderId, string $token, string $image, callable $onError): array
    {
        $result = null;
        try {
            $result = \Faoxima\Payments\Gateways\GatewayRuntime::DirectPayment($orderId, $image, $token);
        } catch (Throwable $e) {
            $onError($e);
        }
        return is_array($result) ? $result : \Faoxima\Payments\Fulfillment\Claims::result('requires_reconciliation', 'direct_payment_invalid_result');
    }

    public static function deliveryCompleted(string $status): bool
    {
        return $status === 'completed' || $status === 'already_completed';
    }

    public static function runClaimed($runtimePdo, string $orderId, string $token, string $image, string $failedTo): array
    {
        $result = self::attemptClaimedDelivery($orderId, $token, $image, static function (Throwable $e) use ($orderId) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_DIRECT_PAYMENT_THROWABLE', $e->getMessage(), [
                'id_order' => $orderId,
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });
        $settle = \Faoxima\Payments\Fulfillment\Settlement::settle($runtimePdo, $orderId, $token, $result, 'waiting', $failedTo);
        return [
            'result' => $result,
            'settle' => $settle,
            'finalized' => !empty($result['completed']) || $settle === 'finalized',
        ];
    }

    public static function panelReconcilable($panel): bool
    {
        if (!is_array($panel)) {
            return false;
        }
        if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel)) {
            return false;
        }
        return in_array((string) ($panel['type'] ?? ''), ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'remnawave', 'rebecca', 'jovadpanel'], true);
    }

    public static function userMissingMessage(string $message): bool
    {
        if ($message === '' || stripos($message, 'panel not found') !== false) {
            return false;
        }
        return (bool) preg_match('/user\s*(not\s*found|does\s*not\s*exist)|not\s*found|404/i', $message);
    }
}
