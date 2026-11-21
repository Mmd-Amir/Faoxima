<?php

namespace Faoxima\Services\Fulfillment;

use Faoxima\Orders\Discounts\SellDiscounts;
use Faoxima\Wallet\Balance\Balances;
use Faoxima\Wallet\Ledger\Ledger;
use PDO;
use RuntimeException;
use Throwable;

final class WalletExtensions
{
    public static function reserve(
        PDO $pdo, array $user, string $username, array $order, string $type,
        float $price, string $agent, string $ledgerNote
    ): array {
        $own = !$pdo->inTransaction();
        $savepoint = 'rx_extra_' . bin2hex(random_bytes(4));
        $commitAttempted = false;
        $blockingOperation = null;
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $blockingOperation = self::existingOperation($pdo, (string) $user['id'], $username, $type, $order, false);
            if ($blockingOperation !== null) {
                throw new RuntimeException($blockingOperation === $order['operation_id'] ? 'operation_already_recorded' : 'operation_pending');
            }
            $charge = SellDiscounts::chargeServiceWallet(
                $pdo, $order['discount_code'], $user, $price, $agent, $order['operation_id']
            );
            if (empty($charge['ok'])) {
                throw new RuntimeException((string) ($charge['reason'] ?? 'charge_failed'));
            }
            self::lockWalletOwner($pdo, (string) $user['id']);
            $blockingOperation = self::existingOperation($pdo, (string) $user['id'], $username, $type, $order);
            if ($blockingOperation !== null) {
                throw new RuntimeException($blockingOperation === $order['operation_id'] ? 'operation_already_recorded' : 'operation_pending');
            }
            $statement = $pdo->prepare(
                'INSERT INTO service_other (id_user, username, value, type, time, price, output, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                (string) $user['id'], $username, json_encode($order, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $type, date('Y/m/d H:i:s'), (string) $price, '', 'pending',
            ]);
            $rowId = (int) $pdo->lastInsertId();
            if ($rowId <= 0) {
                throw new RuntimeException('operation_not_saved');
            }
            if ($price > 0 && !Ledger::record(
                $pdo, $user['id'], 'debit', $price, 'service_action', $ledgerNote,
                $order['operation_id'], isset($order['ledger_invoice']) ? 'invoice' : 'service_other',
                $order['ledger_invoice'] ?? (string) $rowId
            )) {
                throw new RuntimeException('ledger_not_saved');
            }
            if ($own) {
                $commitAttempted = true;
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return ['ok' => true, 'id' => $rowId, 'new_balance' => $charge['new_balance']];
        } catch (Throwable $error) {
            $unknown = $commitAttempted && !$pdo->inTransaction();
            try {
                self::rollback($pdo, $own, $savepoint);
            } catch (Throwable $rollbackError) {
                $unknown = true;
            }
            return ['ok' => false, 'reason' => $error->getMessage(), 'unknown' => $unknown, 'blocking_operation' => $blockingOperation];
        }
    }

    private static function existingOperation(PDO $pdo, string $userId, string $username, string $type, array $order, bool $lock = true): ?string
    {
        $statement = $pdo->prepare(
            "SELECT value, status FROM service_other
             WHERE id_user = ? AND username = ? AND type = ?
             AND (status = 'pending'
                 OR JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(value) THEN value ELSE '{}' END, '$.operation_id')) = ?)"
            . ($lock ? ' FOR UPDATE' : '')
        );
        if (!$statement->execute([$userId, $username, $type, $order['operation_id']])) {
            throw new RuntimeException('operation_lookup_failed');
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $saved = json_decode((string) $row['value'], true);
            if (!is_array($saved)) {
                continue;
            }
            $savedOperation = $saved['operation_id'] ?? null;
            if ($savedOperation === $order['operation_id']) {
                return $savedOperation;
            }
            if ($row['status'] === 'pending' && ($order['invoice_id'] ?? '') !== ''
                && ($saved['invoice_id'] ?? null) === $order['invoice_id'] && is_string($savedOperation) && $savedOperation !== '') {
                return $savedOperation;
            }
        }
        return null;
    }

    public static function blockingOperation(PDO $pdo, string $user, string $name, string $type, string $invoice, string $operation): ?string
    {
        return self::existingOperation($pdo, $user, $name, $type, ['invoice_id' => $invoice, 'operation_id' => $operation], false);
    }

    private static function lockWalletOwner(PDO $pdo, string $userId): void
    {
        $statement = $pdo->prepare('SELECT id FROM user WHERE id = ? FOR UPDATE');
        if (!$statement->execute([$userId]) || $statement->fetchColumn() === false) {
            throw new RuntimeException('operation_owner_unavailable');
        }
    }

    public static function refund(
        PDO $pdo, int $rowId, array $user, array $order, float $price, mixed $result, string $ledgerNote
    ): void {
        $own = !$pdo->inTransaction();
        $savepoint = 'rx_extra_refund_' . bin2hex(random_bytes(4));
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            if ($order['discount_code'] !== '') {
                $discount = $pdo->prepare('SELECT id FROM DiscountSell WHERE codeDiscount = ? ORDER BY id ASC LIMIT 1 FOR UPDATE');
                if (!$discount->execute([$order['discount_code']])) {
                    throw new RuntimeException('discount_lock_failed');
                }
                $discount->closeCursor();
            }
            self::lockWalletOwner($pdo, (string) $user['id']);
            $lock = $pdo->prepare('SELECT value, status, price FROM service_other WHERE id = ? AND id_user = ? FOR UPDATE');
            $lock->execute([$rowId, $user['id']]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            $saved = $row ? json_decode((string) $row['value'], true) : null;
            if (!is_array($saved) || ($saved['operation_id'] ?? null) !== $order['operation_id']
                || ($saved['discount_code'] ?? null) !== $order['discount_code']
                || (float) $row['price'] !== $price) {
                throw new RuntimeException('refund_operation_missing');
            }
            if ($row['status'] !== 'refunded') {
                $state = $saved['wallet_state'] ?? '';
                if ($row['status'] !== 'pending' || !in_array($state, ['charged', 'refundable', 'applying'], true)
                    || ($state === 'applying' && !empty($saved['dispatched_at']))) {
                    throw new RuntimeException('refund_operation_not_pending');
                }
                if (!SellDiscounts::releaseWalletDiscount($pdo, $order['discount_code'], $user, $order['operation_id'])) {
                    throw new RuntimeException('discount_release_failed');
                }
                if ($price > 0) {
                    if (!Balances::credit($pdo, $user['id'], $price)) {
                        throw new RuntimeException('refund_credit_failed');
                    }
                    if (!Ledger::record(
                        $pdo, $user['id'], 'credit', $price, 'refund', $ledgerNote,
                        $saved['operation_id'], isset($saved['ledger_invoice']) ? 'invoice' : 'service_other',
                        $saved['ledger_invoice'] ?? (string) $rowId
                    )) {
                        throw new RuntimeException('refund_ledger_failed');
                    }
                }
                $saved['wallet_state'] = 'refunded';
                $statement = $pdo->prepare("UPDATE service_other SET value = ?, status = 'refunded', output = ? WHERE id = ?");
                $statement->execute([
                    json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    json_encode($result, JSON_UNESCAPED_UNICODE), $rowId,
                ]);
            }
            if ($own) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
        } catch (Throwable $error) {
            self::rollback($pdo, $own, $savepoint);
            throw $error;
        }
    }

    public static function applyReserved(PDO $pdo, int $rowId, string $userId, array $order, $provider, array $panel, string $username, $amount): array
    {
        if ($pdo->inTransaction()) {
            throw new RuntimeException('extension_not_committed');
        }
        if (($order['panel'] ?? '') !== ($panel['name_panel'] ?? '')
            || !is_numeric($amount) || !is_finite((float) $amount) || (float) $amount < 0
            || (isset($order['amount']) && (float) $order['amount'] !== (float) $amount)) {
            throw new RuntimeException('extension_operation_mismatch');
        }
        $applying = ['wallet_state' => 'applying'] + $order;
        $guard = $pdo->prepare("UPDATE service_other SET value = ? WHERE id = ? AND id_user = ? AND username = ? AND status = 'pending' AND value = ?");
        $guard->execute([
            json_encode($applying, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $rowId, $userId, $username,
            json_encode($order, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        if ($guard->rowCount() !== 1) {
            return ['outcome' => 'uncertain', 'result' => ['status' => false, 'msg' => 'extension_claim_unavailable'], 'order' => $order];
        }
        if (Delivery::panelReconcilable($panel)) {
            $before = ProviderState::panelSnapshot($provider, (string) $panel['name_panel'], $username, (string) ($panel['type'] ?? ''));
            if (empty($before['ok']) || empty($before['exists']) || ($before['username'] ?? null) !== $username) {
                return ['outcome' => 'refundable', 'result' => ['status' => false, 'msg' => $before['error'] ?? 'provider_read_failed'], 'order' => $order];
            }
            $order['before'] = ProviderState::limits($before) + ['ok' => true, 'exists' => true];
            $order['target'] = $order['kind'] === 'time'
                ? Adjustments::extraTimeTarget($before, $amount, time())
                : Adjustments::extraVolumeTarget($before, $amount);
        }
        $order['dispatched_at'] = time();
        $save = $pdo->prepare("UPDATE service_other SET value = ? WHERE id = ? AND id_user = ? AND status = 'pending' AND value = ?");
        $current = json_encode(['wallet_state' => 'applying'] + $order, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $previous = json_encode($applying, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!$save->execute([$current, $rowId, $userId, $previous]) || $save->rowCount() !== 1) {
            return ['outcome' => 'uncertain', 'result' => ['status' => false, 'msg' => 'extension_target_not_saved'], 'order' => $order];
        }
        try {
            $result = Adjustments::apply($provider, $order['kind'], $username, $panel['code_panel'], $amount,
                Delivery::panelReconcilable($panel) ? $order['target'] : null);
        } catch (Throwable $error) {
            $result = ['status' => false, 'msg' => 'panel_request_interrupted'];
        }
        $outcome = 'applied';
        if (!is_array($result) || empty($result['status'])) {
            $outcome = 'uncertain';
            if (is_array($result) && ($result['no_mutation'] ?? false) === true) {
                $outcome = 'refundable';
            } elseif (Delivery::panelReconcilable($panel) && !empty($order['before']['ok'])
                && !empty($order['before']['exists']) && is_array($order['target'])) {
                $snapshot = ProviderState::panelSnapshot($provider, (string) $panel['name_panel'], $username, (string) ($panel['type'] ?? ''));
                $column = $order['kind'] === 'time' ? 'expire' : 'data_limit';
                $state = ProviderState::classify($snapshot, [$column => (int) ($order['before'][$column] ?? 0)], $order['target']);
                if ($state === 'target' && ($snapshot['username'] ?? null) === $username) {
                    $outcome = 'applied';
                    $result = ['status' => true, 'reconciled' => true];
                }
            }
        }
        $hold = $pdo->prepare("UPDATE service_other SET value = ?, output = ? WHERE id = ? AND id_user = ? AND status = 'pending' AND value = ?");
        if (!$hold->execute([
            json_encode(['wallet_state' => $outcome] + $order, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $rowId, $userId, $current,
        ]) || $hold->rowCount() !== 1) {
            return ['outcome' => 'uncertain', 'result' => ['status' => false, 'msg' => 'extension_outcome_not_saved'], 'order' => $order];
        }
        return ['outcome' => $outcome, 'result' => $result, 'order' => $order];
    }

    public static function complete(PDO $pdo, int $rowId, string $userId, array $order, array $history, array $result): bool
    {
        return self::finish($pdo, $rowId, $userId, $order + $history, $result,
            json_encode(['wallet_state' => 'applied'] + $order, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function finish(PDO $pdo, int $row, string $user, array $order, array $result, string $expected): bool
    {
        return \Faoxima\Payments\Fulfillment\Store::tx($pdo, static function (PDO $pdo) use ($row, $user, $order, $result, $expected) {
            $settle = $pdo->prepare("UPDATE service_other SET value = ?, output = ?, status = 'done'
                WHERE id = ? AND id_user = ? AND status = 'pending' AND value = ?");
            if (!$settle->execute([
                json_encode(['wallet_state' => 'settled'] + $order, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $row, $user, $expected,
            ]) || $settle->rowCount() !== 1) {
                return false;
            }
            if (($order['kind'] ?? '') === 'time' && !empty($order['invoice_id'])) {
                $reactivate = $pdo->prepare("UPDATE invoice i JOIN service_other o ON o.id = ? AND o.username = i.username
                    SET i.Status = 'active' WHERE i.id_invoice = ? AND i.id_user = ? AND i.Service_location = ?
                      AND i.Status IN ('end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')");
                $reactivate->execute([$row, $order['invoice_id'], $user, $order['panel']]);
            }
            return true;
        });
    }

    public static function reconcileUncertain(PDO $pdo, string $userId, string $operation, $provider, array $panel): bool
    {
        if ($pdo->inTransaction() || !Delivery::panelReconcilable($panel)) {
            return false;
        }
        $lookup = $pdo->prepare("SELECT id, username, value FROM service_other WHERE id_user = ?
            AND type IN ('extra_user', 'extra_time_user', 'extra_not_user') AND status = 'pending'
            AND JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(value) THEN value ELSE '{}' END, '$.operation_id')) = ? LIMIT 1");
        $lookup->execute([$userId, $operation]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);
        $order = $row ? json_decode((string) $row['value'], true) : null;
        if (!is_array($order) || ($order['panel'] ?? null) !== ($panel['name_panel'] ?? '') || !is_array($order['target'] ?? null)) {
            return false;
        }
        $interrupted = ($order['wallet_state'] ?? '') === 'applying' && !empty($order['dispatched_at'])
            && (int) $order['dispatched_at'] < time() - 300;
        if (($order['wallet_state'] ?? '') !== 'uncertain' && !$interrupted) {
            return false;
        }
        $snapshot = ProviderState::panelSnapshot($provider, (string) $panel['name_panel'], (string) $row['username'], (string) ($panel['type'] ?? ''));
        $column = $order['kind'] === 'time' ? 'expire' : 'data_limit';
        $before = [$column => (int) ($order['before'][$column] ?? 0)];
        if (($snapshot['username'] ?? null) !== $row['username']
            || ProviderState::classify($snapshot, $before, $order['target']) !== 'target') {
            return false;
        }
        return self::finish($pdo, (int) $row['id'], $userId, $order, ['status' => true, 'reconciled' => true], $row['value']);
    }

    public static function recoverPending(PDO $pdo, array $row, $provider, callable $findPanel): bool
    {
        $order = json_decode((string) $row['value'], true);
        if (!is_array($order) || empty($order['operation_id']) || !isset($order['discount_code'])) {
            return false;
        }
        $user = (string) $row['id_user'];
        $result = json_decode((string) $row['output'], true);
        $state = $order['wallet_state'] ?? '';
        if ($state === 'applied' && is_array($result) && !empty($result['status'])) {
            return self::complete($pdo, (int) $row['id'], $user, $order, [], $result);
        }
        if (($state === 'refundable' && ($result['no_mutation'] ?? false) === true)
            || $state === 'charged' || ($state === 'applying' && empty($order['dispatched_at']))) {
            self::refund($pdo, (int) $row['id'], ['id' => $user], $order, (float) $row['price'],
                ['status' => false, 'no_mutation' => true], 'Wallet extension recovery');
            return true;
        }
        $panel = $findPanel((string) ($order['panel'] ?? ''));
        return is_array($panel) && self::reconcileUncertain($pdo, $user, $order['operation_id'], $provider, $panel);
    }

    private static function rollback(PDO $pdo, bool $own, string $savepoint): void
    {
        if ($own) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } else {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
    }
}
