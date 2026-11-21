<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;

final class WalletPurchaseEffects
{
    public static function plan(array $settings, array $affiliate, array $user, array $admins, float $price,
        int $invoiceCount, bool $telegram, string $topic, bool $stock): array
    {
        $effects = ['delivery' => []];
        if (!$telegram || !$stock) {
            $referrer = (string) ($user['affiliates'] ?? '');
            $effects['commission'] = [
                'referrer' => $referrer, 'price' => $price, 'affiliate' => array_intersect_key($affiliate, array_flip(['status_commission', 'porsant_one_buy'])),
                'paid_count' => $invoiceCount, 'telegram' => $telegram, 'rate' => $settings['affiliatespercentage'] ?? 0,
                'award_points' => (int) ($settings['scorestatus'] ?? 0) === 1 && !in_array($referrer, $admins),
            ];
            if (!empty($settings['Channel_Report'])) {
                $effects['report'] = ['chat' => (string) $settings['Channel_Report'], 'topic' => $topic];
            }
        }
        return $effects;
    }
    public static function seed(PDO $pdo, string $user, string $invoice, array $effects): void
    {
        $insert = $pdo->prepare("INSERT INTO service_other (id_user, username, value, type, time, price, status, output)
            VALUES (?, ?, ?, 'wallet_purchase_effect', ?, '0', 'pending', ?)");
        foreach ($effects as $effect => $details) {
            if (!$insert->execute([$user, $effect, $invoice, date('Y/m/d H:i:s'), json_encode($details, JSON_THROW_ON_ERROR)])) {
                throw new \RuntimeException('purchase_effect_not_saved');
            }
        }
    }

    public static function send(PDO $pdo, string $user, string $invoice, string $effect, callable $send): void
    {
        $claim = $pdo->prepare("UPDATE service_other effect JOIN invoice i
            ON i.id_invoice = effect.value AND i.id_user = effect.id_user AND i.Status = 'active'
            SET effect.status = 'sending', effect.time = ?
            WHERE effect.id_user = ? AND effect.value = ? AND effect.username = ?
              AND effect.type = 'wallet_purchase_effect' AND effect.status = 'pending'");
        $claim->execute([date('Y/m/d H:i:s'), $user, $invoice, $effect]);
        if ($claim->rowCount() !== 1) {
            return;
        }
        try {
            $result = $send();
            $state = $result === true || (is_array($result) && !empty($result['ok'])) ? 'sent' : 'manual_review';
        } catch (Throwable $error) {
            $state = 'manual_review';
            error_log('Wallet purchase ' . $effect . ' delivery: ' . $error->getMessage());
        }
        $pdo->prepare("UPDATE service_other SET status = ? WHERE id_user = ? AND value = ? AND username = ?
            AND type = 'wallet_purchase_effect' AND status = 'sending'")->execute([$state, $user, $invoice, $effect]);
    }

    public static function commission(PDO $pdo, string $user, string $invoice, string $note): ?array
    {
        $find = $pdo->prepare("SELECT effect.output FROM service_other effect JOIN invoice i
            ON i.id_invoice = effect.value AND i.id_user = effect.id_user AND i.Status = 'active'
            WHERE effect.id_user = ? AND effect.value = ? AND effect.username = 'commission'
              AND effect.type = 'wallet_purchase_effect' AND effect.status = 'pending'");
        $find->execute([$user, $invoice]);
        $terms = json_decode((string) $find->fetchColumn(), true);
        if (!is_array($terms)) {
            return null;
        }
        try {
            $commission = \Faoxima\Cashback\AffiliateCommissions::walletPurchaseCommission(
                $pdo, $user, (string) $terms['referrer'], $invoice, (float) $terms['price'], $terms, $note, true
            );
            $pdo->prepare("UPDATE service_other SET status = 'done' WHERE id_user = ? AND value = ? AND username = 'commission'
                AND type = 'wallet_purchase_effect' AND status = 'pending'")->execute([$user, $invoice]);
            return $commission;
        } catch (Throwable $error) {
            error_log('Wallet purchase commission recovery ' . $invoice . ': ' . $error->getMessage());
            return null;
        }
    }
}
