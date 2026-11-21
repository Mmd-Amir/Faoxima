<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $dcm, $from_id, $keyboard, $message_id.
 * Inherited or exported by reference: $pdo, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CancelCrypto
{
    public static function handle(
        $dcm,
        $from_id,
        $keyboard,
        $message_id,
        &$pdo,
        &$stmt
    ) {
        $orderId = $dcm[1];
                if (\Faoxima\Core\Database\Db::runtimeReady()) {
                    $pdo = \Faoxima\Core\Database\Db::getDatabaseConnection();
                    if ($pdo instanceof \PDO) {
                        try {
                            if (method_exists(\Faoxima\Payments\Settlement\SettlementRuntime::class, 'rx_release_unpaid_discount')) {
                                $rxRep = $pdo->prepare("SELECT time FROM Payment_report WHERE id_order = :o AND id_user = :u AND payment_Status IN ('Unpaid','AwaitingHash') LIMIT 1");
                                $rxRep->execute([':o' => $orderId, ':u' => (string) $from_id]);
                                $rxRepRow = $rxRep->fetch(\PDO::FETCH_ASSOC);
                                $rxRefTime = (is_array($rxRepRow) && !empty($rxRepRow['time'])) ? strtotime(str_replace('/', '-', (string) $rxRepRow['time'])) : null;
                                \Faoxima\Orders\Discounts\SellDiscounts::releaseUnpaidDiscount($pdo, (string) $from_id, null, $rxRefTime ?: null);
                            }
                            $stmt = $pdo->prepare(
                                "UPDATE Payment_report
                            SET payment_Status = 'expire'
                          WHERE id_order = :o
                            AND id_user = :u
                            AND payment_Status IN ('Unpaid', 'AwaitingHash')"
                            );
                            $stmt->execute([':o' => $orderId, ':u' => (string) $from_id]);
                        } catch (\Throwable $_) {  }
                    }
                }
                if (!empty($message_id) && function_exists('deletemessage')) {
                    @deletemessage((string) $from_id, (int) $message_id);
                }
                sendmessage((string) $from_id, "✅ فاکتور لغو شد.", $keyboard, 'HTML');

        return true;
    }
}
