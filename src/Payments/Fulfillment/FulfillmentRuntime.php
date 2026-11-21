<?php

namespace Faoxima\Payments\Fulfillment;

use PDO;

final class FulfillmentRuntime
{
    public static function rx_pf_wallet_schema_verify(PDO $pdo): array
    {
        return \Faoxima\Payments\Fulfillment\WalletSchema::walletSchemaVerify($pdo);
    }

    public static function rx_pf_wallet_ready(): bool
    {
        global $pdo;
        return \Faoxima\Payments\Fulfillment\WalletSchema::walletReady($pdo);
    }

    public static function rx_pf_claim(string $orderId, array $opts = []): array
    {
        global $pdo;
        return \Faoxima\Payments\Fulfillment\Claims::claim($pdo, $orderId, $opts);
    }

    public static function rx_pf_recover(string $orderId): array
    {
        global $pdo;
        return \Faoxima\Payments\Fulfillment\Recovery::recover($pdo, $orderId);
    }
}
