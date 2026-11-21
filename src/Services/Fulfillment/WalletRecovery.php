<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;

final class WalletRecovery
{
    public static function scan(PDO $pdo, $provider, int $cursor, callable $stop, callable $findPanel, callable $notify): int
    {
        $query = $pdo->prepare("SELECT o.*, i.Status AS invoice_status, i.Service_location AS panel, i.bottype
            FROM service_other o LEFT JOIN invoice i ON o.type IN ('wallet_purchase', 'wallet_purchase_effect')
                AND o.value = i.id_invoice AND o.id_user = i.id_user
            WHERE o.id > ? AND o.time <= ? AND (
                (o.type IN ('extra_user', 'extra_time_user', 'extra_not_user', 'wallet_renewal') AND o.status = 'pending')
                OR (o.type = 'wallet_purchase' AND o.status IN ('applying', 'pending', 'refundable')
                    AND i.Status IN ('processing', 'manual_review'))
                OR (o.type = 'wallet_purchase_effect' AND o.status IN ('pending', 'sending') AND i.Status = 'active'))
            AND NOT (JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(o.output) THEN o.output ELSE '{}' END,
                '$.recovery_state')) <=> 'manual_review')
            ORDER BY o.id LIMIT 12");
        $query->execute([$cursor, date('Y/m/d H:i:s', time() - 300)]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return 0;
        }
        foreach ($rows as $row) {
            if ($stop()) {
                break;
            }
            $cursor = (int) $row['id'];
            $output = json_decode((string) $row['output'], true);
            $output = is_array($output) ? $output : [];
            if ((int) ($output['retry_after'] ?? 0) > time()) {
                continue;
            }
            try {
                $done = self::recoverRow($pdo, $provider, $row, $output, $findPanel, $notify);
            } catch (Throwable $error) {
                error_log('Wallet recovery row ' . $cursor . ': ' . $error->getMessage());
                $done = false;
            }
            if (!$done) {
                $attempt = (int) ($output['recovery_attempts'] ?? 0) + 1;
                $output['recovery_attempts'] = $attempt;
                $output['retry_after'] = time() + min(3600, 300 * $attempt);
                if ($attempt >= 12) {
                    $output['recovery_state'] = 'manual_review';
                    error_log('Wallet recovery exhausted for operation row ' . $cursor);
                }
                $update = $pdo->prepare('UPDATE service_other SET output = ? WHERE id = ? AND status = ? AND output <=> ?');
                $update->execute([json_encode($output, JSON_THROW_ON_ERROR), $cursor, $row['status'], $row['output']]);
            }
        }
        return $cursor;
    }

    private static function recoverRow(PDO $pdo, $provider, array $row, array $output, callable $findPanel, callable $notify): bool
    {
        $user = (string) $row['id_user'];
        $invoice = (string) $row['value'];
        if ($row['type'] === 'wallet_purchase') {
            if ($row['status'] === 'pending' || $row['status'] === 'refundable') {
                return Purchase::refundInterruptedWalletInvoice($pdo, $user, $invoice, $row, $output);
            }
            $panel = $findPanel((string) $row['panel']);
            return is_array($panel) && Purchase::recoverWalletInvoice($pdo, $user, $invoice, $provider, $panel);
        }
        if ($row['type'] === 'wallet_renewal') {
            return \Faoxima\Services\Renewal\WalletRenewal::recover($pdo, $row);
        }
        if ($row['type'] !== 'wallet_purchase_effect') {
            return WalletExtensions::recoverPending($pdo, $row, $provider, $findPanel);
        }
        if ($row['status'] === 'sending') {
            $pdo->prepare("UPDATE service_other SET status = 'manual_review' WHERE id = ? AND status = 'sending'")
                ->execute([$row['id']]);
            return true;
        }
        if ($row['username'] === 'commission') {
            WalletPurchaseEffects::commission($pdo, $user, $invoice, 'پورسانت خرید زیرمجموعه');
            $check = $pdo->prepare("SELECT status FROM service_other WHERE id = ?");
            $check->execute([$row['id']]);
            return $check->fetchColumn() === 'done';
        }
        WalletPurchaseEffects::send($pdo, $user, $invoice, (string) $row['username'],
            static fn () => $notify($row, $output)
        );
        return true;
    }
}
