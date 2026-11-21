<?php

namespace Faoxima\Orders\Discounts;

use PDO;
use Throwable;
use RuntimeException;
final class SellDiscounts
{
    public static function releaseUnpaidDiscount($runtimePdo, $userId, $discountCode = null, $referenceTime = null) {
        $pdo = $runtimePdo;
        $userId = trim((string)$userId);
        if ($userId === '' || !isset($pdo)) return false;
        try {
            if ($referenceTime !== null && (int)$referenceTime > 0) {
                $low = (string)((int)$referenceTime - 900);
                $high = (string)((int)$referenceTime + 900);
            } else {
                $low = (string)(time() - 1800);
                $high = (string)(time() + 60);
            }
            $params = [':u' => $userId, ':lo' => $low, ':hi' => $high];
            $codeClause = '';
            if ($discountCode !== null && trim((string)$discountCode) !== '') {
                $codeClause = ' AND code = :c';
                $params[':c'] = trim((string)$discountCode);
            }
            $stmt = $pdo->prepare(
                "SELECT id, code FROM Giftcodeconsumed
                  WHERE id_user = :u
                    AND kind = 'sell'
                    AND (related_order IS NULL OR related_order = '')
                    AND (released IS NULL OR released = 0)
                    AND consumed_at <> ''
                    AND CAST(consumed_at AS UNSIGNED) BETWEEN :lo AND :hi" . $codeClause . "
                  ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || empty($row['code'])) return false;

            $code = (string)$row['code'];
            $rowId = (int)$row['id'];

            $marked = $pdo->prepare('UPDATE Giftcodeconsumed SET released = 1 WHERE id = :id AND (released IS NULL OR released = 0)');
            $marked->execute([':id' => $rowId]);
            if ($marked->rowCount() < 1) return false;

            $pdo->prepare("UPDATE DiscountSell SET usedDiscount = GREATEST(CAST(COALESCE(NULLIF(usedDiscount, ''), '0') AS SIGNED) - 1, 0) WHERE codeDiscount = :c")
                ->execute([':c' => $code]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function validateSellDiscount($runtimePdo, $code, $section, $codeProduct, $codePanel, $user, $from_id)
    {
        $pdo = $runtimePdo;
        $code = trim((string)$code);
        $sections = ['buy', 'extend', 'volume', 'time', 'charge', 'all'];
        $section = in_array($section, $sections, true) ? $section : 'all';
        $res = ['ok' => false, 'reason' => '', 'row' => null, 'value_type' => 'percent', 'value' => 0.0, 'label' => ''];

        if ($code === '') {
            $res['reason'] = '❌ کد تخفیف را وارد کنید.';
            return $res;
        }

        if ($section !== 'charge' && intval($user['pricediscount'] ?? 0) != 0) {
            $res['reason'] = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_exclusive_discount_conflict', '❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.');
            return $res;
        }

        $agent       = (string)($user['agent'] ?? 'f');
        $codeProduct = ($codeProduct === '' || $codeProduct === null) ? 'all' : $codeProduct;
        $codePanel   = ($codePanel === '' || $codePanel === null) ? '/all' : $codePanel;

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM DiscountSell
                  WHERE codeDiscount = :code
                    AND (code_product = :cp OR code_product = 'all')
                    AND (code_panel = :cpan OR code_panel = '/all')
                    AND (agent = :agent OR agent = 'allusers' OR agent = 'all')
                    AND (COALESCE(NULLIF(section, ''), type, 'all') = :section
                         OR COALESCE(NULLIF(section, ''), type, 'all') = 'all')
                    AND (status IS NULL OR status = '' OR status = 'active')
                    AND (target_user IS NULL OR target_user = '' OR target_user = :uid)
                  LIMIT 1"
            );
            $stmt->execute([
                ':code'   => $code,
                ':cp'     => $codeProduct,
                ':cpan'   => $codePanel,
                ':agent'  => $agent,
                ':section' => $section,
                ':uid'    => (string)$from_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('nm_validateSellDiscount query failed: ' . $e->getMessage());
            $res['reason'] = '❌ خطا در بررسی کد تخفیف.';
            return $res;
        }

        if (!$row) {
            $res['reason'] = '❌ کد تخفیف نامعتبر است یا برای این بخش فعال نیست.';
            return $res;
        }

        if (intval($row['time']) != 0 && time() >= intval($row['time'])) {
            $res['reason'] = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_discount_expired', '❌ زمان کد تخفیف به پایان رسیده است.');
            return $res;
        }

        if (intval($row['limitDiscount']) > 0 && intval($row['usedDiscount']) >= intval($row['limitDiscount'])) {
            $res['reason'] = '❌ ظرفیت استفاده از این کد تخفیف به پایان رسیده است.';
            return $res;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Giftcodeconsumed WHERE id_user = :u AND code = :c AND COALESCE(released, 0) = 0");
            $stmt->execute([':u' => (string)$from_id, ':c' => $code]);
            $usedByUser = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $usedByUser = 0;
        }
        $useUser = intval($row['useuser']);
        if ($useUser > 0 && $usedByUser >= $useUser) {
            $res['reason'] = '⭕️ سقف استفاده شما از این کد تخفیف پر شده است.';
            return $res;
        }

        if ((string)($row['usefirst'] ?? '') === '1') {
            $invoiceCount = \Faoxima\Orders\Discounts\DiscountRules::completedPurchaseCount($pdo, (string)$from_id);
            if (intval($invoiceCount) != 0) {
                $res['reason'] = '❌ این کد تخفیف فقط برای اولین خرید قابل استفاده است.';
                return $res;
            }
        }

        $vt = strtolower(trim((string)($row['value_type'] ?? '')));
        if (!in_array($vt, ['percent', 'amount', 'free'], true)) {
            $tcol = strtolower(trim((string)($row['type'] ?? '')));
            $vt = in_array($tcol, ['percent', 'amount', 'free'], true) ? $tcol : 'percent';
        }
        $val = (float)$row['price'];
        if ($vt === 'percent' && ($val <= 0 || $val > 100)) {
            $res['reason'] = '❌ درصد کد تخفیف نامعتبر است.';
            return $res;
        }
        if ($vt === 'amount' && $val <= 0) {
            $res['reason'] = '❌ مبلغ کد تخفیف نامعتبر است.';
            return $res;
        }

        $label = $vt === 'free'
            ? 'رایگان'
            : ($vt === 'amount' ? number_format($val) . ' تومان' : (string)$row['price'] . ' درصد');

        $res['ok']         = true;
        $res['row']        = $row;
        $res['value_type'] = $vt;
        $res['value']      = $val;
        $res['label']      = $label;
        return $res;
    }

    public static function applySellDiscountToPrice($row, $price)
    {
        $price = (float)$price;
        $vt = strtolower(trim((string)($row['value_type'] ?? '')));
        if (!in_array($vt, ['percent', 'amount', 'free'], true)) {
            $tcol = strtolower(trim((string)($row['type'] ?? '')));
            $vt = in_array($tcol, ['percent', 'amount', 'free'], true) ? $tcol : 'percent';
        }
        $val = (float)($row['price'] ?? 0);
        if ($vt === 'free')   return 0.0;
        if ($vt === 'amount') return max(0.0, $price - $val);
        return max(0.0, $price - ($price * $val / 100));
    }

    public static function markSellDiscountUsed($runtimeConnect, $runtimeOtherreport, $runtimePdo, $runtimeSetting, $code, $from_id, $username = '', $reportContext = '')
    {
        $connect = $runtimeConnect; $setting = $runtimeSetting; $otherreport = $runtimeOtherreport;
        $code = trim((string)$code);
        if ($code === '') return;

        $useLegacy = true;
        if (is_callable([\Faoxima\Orders\Discounts\SellDiscounts::class, 'discountMarkUsedAtomic'])) {
            $markResult = self::discountMarkUsedAtomic($runtimePdo, $code, $from_id, 'sell');
            $useLegacy = empty($markResult['ok']) && (string)($markResult['reason'] ?? '') === 'db_unavailable';
        }
        if ($useLegacy) {
            try {
                $row = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $code, "select");
                if ($row != false) {
                    $value = intval($row['usedDiscount']) + 1;
                    \Faoxima\Core\Database\Db::update("DiscountSell", "usedDiscount", $value, "codeDiscount", $code);
                }
            } catch (Throwable $e) {
                error_log('nm_markSellDiscountUsed update failed: ' . $e->getMessage());
            }

            try {
                $now  = (string)time();
                $kind = 'sell';
                $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user, code, kind, consumed_at) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $from_id, $code, $kind, $now);
                $stmt->execute();
                $stmt->close();
            } catch (Throwable $e) {
                try {
                    $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (?, ?)");
                    $stmt->bind_param("ss", $from_id, $code);
                    $stmt->execute();
                    $stmt->close();
                } catch (Throwable $e2) {
                }
            }
        }

        if (isset($setting['Channel_Report']) && strlen((string)$setting['Channel_Report']) > 0) {
            $uname = $username !== '' ? "@{$username} " : '';
            $ctx   = $reportContext !== '' ? " (بخش: {$reportContext})" : '';
            $text_report = "⭕️ کاربر {$uname}با آیدی عددی {$from_id} از کد تخفیف {$code}{$ctx} استفاده کرد.";
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport ?? null,
                'text' => $text_report,
                'parse_mode' => "HTML",
            ]);
        }
    }

    public static function discountMarkUsedAtomic($runtimePdo, $code, $userId, $kind = 'sell', $operationId = '')
    {
        $pdo = $runtimePdo;
        $code = trim((string)$code);
        $userId = trim((string)$userId);
        $kind = $kind === 'gift' ? 'gift' : 'sell';
        $operationId = trim((string)$operationId);
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'db_unavailable'];
        }
        if ($code === '' || $userId === '') {
            return ['ok' => false, 'reason' => 'invalid_code'];
        }
        $own = !$pdo->inTransaction();
        $savepoint = 'rx_disc_' . bin2hex(random_bytes(4));
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $stmt = $pdo->prepare("SELECT * FROM DiscountSell WHERE codeDiscount = :c ORDER BY id ASC LIMIT 1 FOR UPDATE");
            $stmt->execute([':c' => $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('not_found');
            }
            if ($operationId !== '') {
                $existing = $pdo->prepare("SELECT id FROM Giftcodeconsumed WHERE id_user = ? AND code = ? AND kind = ? AND related_order = ? AND released = 0 LIMIT 1 FOR UPDATE");
                $existing->execute([$userId, $code, $kind, $operationId]);
                if ($existing->fetchColumn() !== false) {
                    if ($own) {
                        $pdo->commit();
                    } else {
                        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    }
                    return ['ok' => true, 'replayed' => true];
                }
            }
            $limit = (int)($row['limitDiscount'] ?? 0);
            $used = (int)($row['usedDiscount'] ?? 0);
            if ($limit > 0 && $used >= $limit) {
                throw new RuntimeException('capacity');
            }
            $useUser = (int)($row['useuser'] ?? 0);
            if ($useUser > 0) {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM Giftcodeconsumed WHERE id_user = :u AND code = :c AND COALESCE(released, 0) = 0");
                $cnt->execute([':u' => $userId, ':c' => $code]);
                if ((int)$cnt->fetchColumn() >= $useUser) {
                    throw new RuntimeException('per_user_limit');
                }
            }
            $upd = $pdo->prepare("UPDATE DiscountSell SET usedDiscount = :v WHERE codeDiscount = :c");
            $upd->execute([':v' => (string)($used + 1), ':c' => $code]);
            if ($upd->rowCount() < 1) {
                throw new RuntimeException('not_found');
            }
            if ($operationId !== '') {
                $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code, kind, consumed_at, related_order) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$userId, $code, $kind, (string)time(), $operationId]);
            } else {
                try {
                    $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code, kind, consumed_at) VALUES (?, ?, ?, ?)")
                        ->execute([$userId, $code, $kind, (string)time()]);
                } catch (Throwable $e) {
                    $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (?, ?)")
                        ->execute([$userId, $code]);
                }
            }
            if ($own) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('DiscountSell', 'codeDiscount', $code);
            }
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($own) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $rollbackError) {
                    }
                }
            } else {
                try {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                } catch (Throwable $rollbackError) {
                }
            }
            $reason = $e->getMessage();
            if (!in_array($reason, ['not_found', 'capacity', 'per_user_limit'], true)) {
                error_log('rx_discount_mark_used_atomic failed: ' . $reason);
                $reason = 'db_error';
            }
            return ['ok' => false, 'reason' => $reason];
        }
    }

    public static function chargeServiceWallet(PDO $pdo, string $code, array $user, $amount, $agent, string $operationId, ?array $debit = null): array
    {
        $allowNegative = $agent === 'n2' ? (int)($user['maxbuyagent'] ?? 0) : 0;
        return self::chargeWallet($pdo, $code, $user, $amount, $allowNegative, $operationId, $debit);
    }

    private static function chargeWallet(PDO $pdo, string $code, array $user, $amount, int $allowNegative, string $operationId, ?array $debit): array
    {
        $own = !$pdo->inTransaction();
        $savepoint = 'rx_discount_charge_' . bin2hex(random_bytes(4));
        $commitAttempted = false;
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            if ($code !== '') {
                $reserved = self::discountMarkUsedAtomic($pdo, $code, (string)$user['id'], 'sell', $operationId);
                if (empty($reserved['ok']) || !empty($reserved['replayed'])) {
                    throw new RuntimeException('discount_' . ($reserved['reason'] ?? 'already_reserved'));
                }
            }
            $charged = (float)$amount > 0
                ? \Faoxima\Wallet\Balance\Balances::charge($pdo, $user['id'], $amount, $allowNegative)
                : ['ok' => true, 'new_balance' => (float)($user['Balance'] ?? 0)];
            if (empty($charged['ok'])) {
                throw new RuntimeException(($charged['reason'] ?? '') === 'insufficient-or-stale' ? 'insufficient_balance' : 'charge_failed');
            }
            if ($debit !== null && (float) $amount > 0 && !\Faoxima\Wallet\Ledger\Ledger::record(
                $pdo, $user['id'], 'debit', $amount, $debit['category'], $debit['note'], $operationId, 'invoice', $debit['invoice']
            )) {
                throw new RuntimeException('ledger_not_saved');
            }
            if ($own) {
                $commitAttempted = true;
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $charged;
        } catch (Throwable $e) {
            $outcomeUnknown = $commitAttempted && !$pdo->inTransaction();
            try {
                if ($own) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } else {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable $rollbackError) {
                $outcomeUnknown = true;
            }
            return ['ok' => false, 'reason' => $outcomeUnknown ? 'outcome_unknown' : $e->getMessage()];
        }
    }

    public static function releaseWalletDiscount(PDO $pdo, string $code, array $user, string $operationId): bool
    {
        if ($code === '') return true;
        $own = !$pdo->inTransaction();
        $savepoint = 'rx_discount_release_' . bin2hex(random_bytes(4));
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $lock = $pdo->prepare('SELECT id FROM DiscountSell WHERE codeDiscount = ? ORDER BY id ASC LIMIT 1 FOR UPDATE');
            $lock->execute([$code]);
            $marked = $pdo->prepare("UPDATE Giftcodeconsumed SET released = 1 WHERE id_user = ? AND code = ? AND kind = 'sell' AND related_order = ? AND released = 0");
            $marked->execute([(string)$user['id'], $code, $operationId]);
            $count = $marked->rowCount();
            if ($count > 0) {
                $pdo->prepare("UPDATE DiscountSell SET usedDiscount = GREATEST(CAST(COALESCE(NULLIF(usedDiscount, ''), '0') AS SIGNED) - ?, 0) WHERE codeDiscount = ?")
                    ->execute([$count, $code]);
            }
            if ($own) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('DiscountSell', 'codeDiscount', $code);
            }
            return true;
        } catch (Throwable $e) {
            try {
                if ($own) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                } else {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable $rollbackError) {
                \Faoxima\Api\Runtime\Logger::critical('Wallet discount release rollback failed', ['operation_id' => $operationId]);
            }
            \Faoxima\Api\Runtime\Logger::error('Wallet discount release failed', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
