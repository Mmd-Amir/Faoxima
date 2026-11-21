<?php

namespace Faoxima\Payments\Settlement;

use PDO;
use Faoxima\Payments\Fulfillment\Claims;
use Faoxima\Payments\Fulfillment\Store;

final class CallbackClaims
{
    public static function claimCard(PDO $pdo, array $candidate, float $amount, string $evidence): ?string
    {
        $order = (string) ($candidate['id_order'] ?? '');
        if (!Claims::validOrderId($order) || !is_finite($amount) || $amount <= 0
            || !preg_match('/^[0-9a-f]{64}$/D', $evidence)) {
            return null;
        }
        $key = 'card:' . substr($evidence, 0, 59);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 3)');
        $lock->execute([$key]);
        if ((int) $lock->fetchColumn() !== 1) {
            return null;
        }
        try {
            return Store::tx($pdo, static function (PDO $pdo) use ($candidate, $order, $amount, $evidence) {
                $seen = $pdo->prepare("SELECT id FROM service_other WHERE username = ? AND type = 'card_sms_verified' LIMIT 1");
                $seen->execute([$evidence]);
                $payment = Store::lockPayment($pdo, $order);
                if ($seen->fetchColumn() !== false || !$payment
                    || (string) $payment['id_user'] !== (string) ($candidate['id_user'] ?? '')
                    || (isset($candidate['id']) && (string) $payment['id'] !== (string) $candidate['id'])
                    || (float) $payment['price'] !== $amount || ($payment['Payment_Method'] ?? '') !== 'cart to cart'
                    || !in_array(strtolower((string) $payment['payment_Status']), ['unpaid', 'waiting'], true)
                    || !empty($payment['direct_payment_done']) || Store::lockRecord($pdo, $order)) {
                    return null;
                }
                $token = Claims::newToken();
                Claims::writeClaim($pdo, null, $order, $payment, 'claimed', $token, time(), true);
                $claim = Store::lockRecord($pdo, $order);
                if (($claim['processing_token'] ?? null) !== $token) {
                    throw new \RuntimeException('card_fulfillment_claim_not_saved');
                }
                $save = $pdo->prepare("INSERT INTO service_other (id_user, username, value, time, price, type, status, output)
                    VALUES (?, ?, ?, ?, ?, 'card_sms_verified', 'done', '')");
                if (!$save->execute([$payment['id_user'], $evidence, $order, date('Y/m/d H:i:s'), $amount])) {
                    throw new \RuntimeException('card_evidence_not_saved');
                }
                if (Store::setPaymentStatus($pdo, $order, 'paid', [$payment['payment_Status']]) !== 1) {
                    throw new \RuntimeException('card_claim_not_saved');
                }
                return $token;
            });
        } finally {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$key]);
        }
    }

    public static function claimZarinpal($pdo, $invoice_id)
    {
        $atomic = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid' "
            . "WHERE id_order = :id AND payment_Status NOT IN ('paid', 'cancelled')"
        );
        $atomic->bindValue(':id', $invoice_id, PDO::PARAM_STR);
        $atomic->execute();
        return $atomic->rowCount();
    }

    public static function isVerifiedCard(PDO $pdo, string $order, string $user): bool
    {
        $find = $pdo->prepare("SELECT 1 FROM service_other WHERE value = ? AND id_user = ?
            AND type = 'card_sms_verified' AND status = 'done' LIMIT 1");
        $find->execute([$order, $user]);
        return $find->fetchColumn() !== false;
    }

    public static function claimOnline($connect, $orderId)
    {
        $atomic = $connect->prepare(
            "UPDATE Payment_report SET payment_Status = ? WHERE id_order = ? AND payment_Status NOT IN ('paid', 'cancelled')"
        );
        $statusPaid = 'paid';
        $atomic->bind_param('ss', $statusPaid, $orderId);
        $atomic->execute();
        $affected = $atomic->affected_rows;
        $atomic->close();
        return $affected;
    }

    public static function claimTronado($connect, $paymentId, $dedupeKey)
    {
        $atomic = $connect->prepare(
            "UPDATE Payment_report SET payment_Status = ?, dec_not_confirmed = ? "
            . "WHERE id_order = ? AND payment_Status <> 'paid'"
        );
        $statusPaid = 'paid';
        $atomic->bind_param('sss', $statusPaid, $dedupeKey, $paymentId);
        $atomic->execute();
        $affected = $atomic->affected_rows;
        $atomic->close();
        return $affected;
    }

    private static function creditCashback(PDO $pdo, array $report, array $user, string $key, callable $setting, string $description): ?int
    {
        $percentage = $setting($key);
        if (!is_numeric($percentage) || (float) $percentage <= 0
            || !\Faoxima\Cashback\Eligibility::cashbackTargetEligible(
                $pdo, (string) ($setting($key . '_target') ?? 'all'), $user['register'] ?? null, $user['id'], $report['id_order']
            )
            || !\Faoxima\Cashback\Eligibility::cashbackScopeEligible(
                (string) ($setting($key . '_scope') ?? 'all'), $report['id_invoice'] ?? ''
            )) {
            return null;
        }
        $amount = (int) floor(($report['price'] * $percentage) / 100);
        return \Faoxima\Cashback\Credits::creditOnce($pdo, $report['id_order'], $user['id'], $amount, $key, $description) === 'credited'
            ? $amount
            : null;
    }

    public static function canResumeOnline(array $report): bool
    {
        $alreadyDone = (int)($report['direct_payment_done'] ?? 0) === 1;
        $wasCancelled = strtolower((string)($report['payment_Status'] ?? '')) === 'cancelled';
        return !$alreadyDone && !$wasCancelled;
    }

    public static function claimResumableOnline($connect, $orderId, array $report): bool
    {
        return self::claimOnline($connect, $orderId) >= 1 || self::canResumeOnline($report);
    }

    public static function settleDelivery(PDO $pdo, string $orderId, array $verifiedReport, callable $deliver, string $image,
        ?string $cashbackKey = null, string $cashbackNote = '', ?callable $fetchSetting = null): array
    {
        $result = ['customer' => null, 'cashback' => null, 'delivery' => 'unverified'];
        $payment = $pdo->prepare('SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1');
        $payment->execute([$orderId]);
        $report = $payment->fetch(PDO::FETCH_ASSOC);
        if (!$report || ($report['payment_Status'] ?? '') !== 'paid'
            || (string) $report['id_user'] !== (string) ($verifiedReport['id_user'] ?? '')) {
            return $result;
        }
        if ((int) ($report['direct_payment_done'] ?? 0) === 1) {
            $result['delivery'] = 'already_completed';
        } else {
            try {
                $delivery = $deliver($orderId, $image);
                $result['delivery'] = is_array($delivery) ? (string) ($delivery['status'] ?? 'unknown') : 'unknown';
            } catch (\Throwable $error) {
                $result['delivery'] = 'requires_reconciliation';
                if (function_exists('rx_log_event')) {
                    rx_log_event('PAYMENT_DELIVERY_FAILED', $error->getMessage(), ['id_order' => $orderId]);
                }
            }
        }
        $customer = $pdo->prepare('SELECT * FROM user WHERE id = ? LIMIT 1');
        $customer->execute([$report['id_user']]);
        $result['customer'] = $customer->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($cashbackKey !== null && $fetchSetting !== null && $result['customer'] !== null
            && \Faoxima\Services\Fulfillment\Delivery::deliveryCompleted($result['delivery'])) {
            $result['cashback'] = self::creditCashback(
                $pdo, $report, $result['customer'], $cashbackKey, $fetchSetting, $cashbackNote
            );
        }
        return $result;
    }
}
