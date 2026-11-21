<?php

namespace Faoxima\Cashback;

use PDO;
use RuntimeException;
use Throwable;

final class AffiliateCommissions
{
    public static function affiliateShouldPayCommission(array $affiliatesSettings, int $paidInvoiceCount): bool {
        if (($affiliatesSettings['status_commission'] ?? '') !== 'oncommission') return false;
        if (($affiliatesSettings['porsant_one_buy'] ?? '') === 'on_buy_porsant') {
            return $paidInvoiceCount <= 1;
        }
        return true;
    }

    public static function affiliatePaidInvoiceCount($runtimePdo, $buyerId): int {
        $pdo = $runtimePdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE name_product != 'سرویس تست' AND id_user = :id_user AND Status != 'Unpaid'");
        $stmt->bindValue(':id_user', $buyerId);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function walletPurchaseCommission(PDO $pdo, string $buyer, string $referrer, string $invoice,
        float $price, array $terms, string $note, bool $throwOnFailure = false): ?array
    {
        if ($referrer === '' || (int) $referrer === 0
            || !self::affiliateShouldPayCommission($terms['affiliate'], $terms['paid_count'])
            || ($terms['telegram'] && ($terms['affiliate']['porsant_one_buy'] ?? '') === 'on_buy_porsant' && $terms['paid_count'] !== 1)) {
            return null;
        }
        $rate = $terms['telegram'] ? (float) $terms['rate'] : min(100, max(0, (float) $terms['rate']));
        $commission = $price * $rate / 100;
        $amount = (int) round($commission);
        if (!is_finite($commission) || $amount <= 0) {
            return null;
        }
        try {
            return \Faoxima\Payments\Fulfillment\Store::tx($pdo, static function (PDO $pdo) use ($buyer, $referrer, $invoice, $commission, $amount, $terms, $note) {
                $purchase = $pdo->prepare("SELECT refral FROM invoice WHERE id_invoice = ? AND id_user = ? AND Status = 'active'");
                $purchase->execute([$invoice, $buyer]);
                if ((string) $purchase->fetchColumn() !== $referrer) {
                    return null;
                }
                $wallet = $pdo->prepare('SELECT id FROM user WHERE id = ? FOR UPDATE');
                $wallet->execute([$referrer]);
                if ($wallet->fetchColumn() === false) {
                    return null;
                }
                $seen = $pdo->prepare("SELECT id FROM wallet_ledger WHERE id_order = ? AND id_user = ?
                    AND category = 'affiliate_commission' AND direction = 'credit' LIMIT 1 FOR UPDATE");
                $seen->execute([$invoice, $referrer]);
                if ($seen->fetchColumn() !== false) {
                    return null;
                }
                if (\Faoxima\Wallet\Ledger\Ledger::creditAndRecord(
                    $pdo, $referrer, $amount, 'affiliate_commission', static fn () => $note, $invoice, 'invoice', $invoice
                ) !== 'credited') {
                    throw new RuntimeException('affiliate_commission_not_saved');
                }
                if ($terms['award_points']) {
                    $points = $pdo->prepare('UPDATE user SET score = COALESCE(score, 0) + 2 WHERE id = ?');
                    if (!$points->execute([$referrer]) || $points->rowCount() !== 1) {
                        throw new RuntimeException('affiliate_points_not_saved');
                    }
                }
                return ['amount' => $commission, 'awarded_points' => $terms['award_points']];
            });
        } catch (Throwable $error) {
            if ($throwOnFailure) {
                throw $error;
            }
            error_log('Wallet purchase commission ' . $invoice . ': ' . $error->getMessage());
            return null;
        }
    }

    public static function payAffiliateCommissionForPurchase($runtimePdo, $runtimeSetting, $buyerId, $referrerId, float $purchasePrice, ?int $paidInvoiceCount = null): ?float {
        $pdo = $runtimePdo; $setting = $runtimeSetting;
        $affiliatesSettings = \Faoxima\Core\Database\Db::select('affiliates', '*', null, null, 'select');
        if (!is_array($affiliatesSettings)) return null;
        if (empty($referrerId) || (int) $referrerId === 0) return null;

        if ($paidInvoiceCount === null) {
            $paidInvoiceCount = self::affiliatePaidInvoiceCount($runtimePdo, $buyerId);
        }

        if (!self::affiliateShouldPayCommission($affiliatesSettings, $paidInvoiceCount)) {
            return null;
        }

        $rate = (float) ($setting['affiliatespercentage'] ?? 0);
        $rate = min(100, max(0, $rate));
        $commission = ($purchasePrice * $rate) / 100;
        if ($commission <= 0) return null;

        $referrer = \Faoxima\Core\Database\Db::select('user', '*', 'id', $referrerId, 'select');
        if (empty($referrer)) return null;

        if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'credit'])) {
            \Faoxima\Wallet\Balance\Balances::credit($runtimePdo, $referrerId, $commission);
        } else {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance + :d WHERE id = :u");
            $stmt->execute([':d' => $commission, ':u' => $referrerId]);
        }
        if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
            \Faoxima\Wallet\Ledger\Ledger::record($runtimePdo, $referrerId, 'credit', $commission, 'affiliate_commission', \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_affiliate_commission_ledger_note', 'پورسانت خرید زیرمجموعه'), null, 'user', (string) $buyerId);
        }

        if ((int) ($setting['scorestatus'] ?? 0) === 1 && function_exists('sendmessage')) {
            $admin_ids = $GLOBALS['admin_ids'] ?? [];
            if (!in_array($referrerId, $admin_ids)) {
                sendmessage($referrerId, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_score_earned_2', "📌شما 2 امتیاز جدید کسب کردید."), null, 'html');
                \Faoxima\Core\Database\Db::update('user', 'score', (int) $referrer['score'] + 2, 'id', $referrerId);
            }
        }

        return $commission;
    }
}
