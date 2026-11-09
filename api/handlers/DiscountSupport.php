<?php


declare(strict_types=1);

require_once __DIR__ . '/../lib/Db.php';

if (class_exists('MiniDiscount')) {
    return;
}

final class MiniDiscount
{
    const SECTIONS = ['buy', 'extend', 'volume', 'time', 'charge', 'all'];

    public static function completedPurchaseCount(string $userId): int
    {
        return (int) FaoximaDb::fetchScalar(
            "SELECT COUNT(*) FROM invoice
              WHERE id_user = :u
                AND COALESCE(name_product, '') <> :trial
                AND LOWER(TRIM(COALESCE(Status, ''))) NOT IN ('', 'unpaid', 'unsuccessful')",
            [':u' => $userId, ':trial' => 'سرویس تست']
        );
    }

    public static function valueType(array $row): string
    {
        $vt = strtolower(trim((string)($row['value_type'] ?? '')));
        if (in_array($vt, ['percent', 'amount', 'free'], true)) {
            return $vt;
        }
        return 'percent';
    }

    public static function describe(array $row): string
    {
        $vt = self::valueType($row);
        $val = (float)($row['price'] ?? 0);
        if ($vt === 'free')   return 'رایگان';
        if ($vt === 'amount') return number_format($val) . ' تومان';
        $percentStr = (string)$val;
        if (strpos($percentStr, '.') !== false) {
            $percentStr = rtrim(rtrim($percentStr, '0'), '.');
        }
        return $percentStr . '٪';
    }

    public static function applyToPrice(array $row, float $price): float
    {
        $vt = self::valueType($row);
        $val = (float)($row['price'] ?? 0);
        if ($vt === 'free') {
            return 0.0;
        }
        if ($vt === 'amount') {
            $p = $price - $val;
            return $p < 0 ? 0.0 : $p;
        }
        $p = $price - (($price * $val) / 100);
        return $p < 0 ? 0.0 : $p;
    }

    public static function redeemGift(string $code, array $user): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'reason' => '❌ کد هدیه را وارد کنید.'];
        }

        if (!function_exists('rx_gift_redeem_atomic')) {
            return ['ok' => false, 'reason' => '❌ خطا در واریز هدیه. لطفاً دوباره تلاش کنید.'];
        }
        $result = rx_gift_redeem_atomic($code, (string)$user['id']);
        if (empty($result['ok'])) {
            $reason = (string)($result['reason'] ?? '');
            if ($reason === 'expired') {
                return ['ok' => false, 'reason' => '❌ زمان این کد هدیه به پایان رسیده است.'];
            }
            if ($reason === 'capacity') {
                return ['ok' => false, 'reason' => '❌ ظرفیت استفاده از این کد هدیه به پایان رسیده است.'];
            }
            if ($reason === 'invalid_amount') {
                return ['ok' => false, 'reason' => '❌ این کد هدیه مبلغی ندارد.'];
            }
            if ($reason === 'invalid_status') {
                return ['ok' => false, 'reason' => '❌ این کد هدیه غیرفعال است.'];
            }
            if (in_array($reason, ['not_found', 'invalid_target', 'invalid_code'], true)) {
                return ['ok' => false, 'reason' => '❌ کد هدیه نامعتبر است.'];
            }
            return ['ok' => false, 'reason' => '❌ خطا در واریز هدیه. لطفاً دوباره تلاش کنید.'];
        }

        return [
            'ok' => true,
            'amount' => (int)($result['amount'] ?? 0),
            'new_balance' => (float)($result['new_balance'] ?? 0),
        ];
    }

    private static function normalizeTargeting(string $codeProduct, string $codePanel, string $codeCategory): array
    {
        if ($codeProduct === '')  $codeProduct = 'all';
        if ($codePanel === '')    $codePanel = '/all';
        if ($codeCategory === '') $codeCategory = 'all';
        return [$codeProduct, $codePanel, $codeCategory];
    }

    private static function targetingClause(): string
    {
        return "(
                    (COALESCE(NULLIF(targeting_mode,''),'none') = 'none'
                      AND (code_product = :cp OR code_product = 'all')
                      AND (code_panel = :cpan OR code_panel = '/all'))
                 OR (COALESCE(NULLIF(targeting_mode,''),'none') = 'panel'
                      AND (code_panel = '/all' OR FIND_IN_SET(:cpan2, code_panel) > 0))
                 OR (COALESCE(NULLIF(targeting_mode,''),'none') = 'category'
                      AND (code_category = 'all' OR FIND_IN_SET(:cat, code_category) > 0))
               )";
    }

    private static function targetingClauseMulti(array $codeCategories, string $paramPrefix = 'catm'): array
    {
        $categoryOr = "code_category = 'all'";
        $extraParams = [];
        $codeCategories = array_values(array_unique(array_filter(array_map('strval', $codeCategories), function ($v) {
            return trim($v) !== '';
        })));
        if ($codeCategories) {
            $parts = [];
            foreach ($codeCategories as $i => $value) {
                $key = ":{$paramPrefix}{$i}";
                $parts[] = "FIND_IN_SET({$key}, code_category) > 0";
                $extraParams[$key] = $value;
            }
            $categoryOr .= ' OR ' . implode(' OR ', $parts);
        }
        $clause = "(
                    (COALESCE(NULLIF(targeting_mode,''),'none') = 'none'
                      AND (code_product = :cp OR code_product = 'all')
                      AND (code_panel = :cpan OR code_panel = '/all'))
                 OR (COALESCE(NULLIF(targeting_mode,''),'none') = 'panel'
                      AND (code_panel = '/all' OR FIND_IN_SET(:cpan2, code_panel) > 0))
                 OR (COALESCE(NULLIF(targeting_mode,''),'none') = 'category'
                      AND ({$categoryOr}))
               )";
        return [$clause, $extraParams];
    }

    private static function agentClause(): string
    {
        return "(agent = 'allusers' OR agent = 'all' OR FIND_IN_SET(:agent, agent) > 0)";
    }

    private static function sectionClause(): string
    {
        return "(COALESCE(NULLIF(section, ''), type, 'all') = 'all'
                 OR FIND_IN_SET(:section, COALESCE(NULLIF(section, ''), type, 'all')) > 0)";
    }

    private static function rowPassesUsageChecks(array $row, string $code, array $user): array
    {
        $expiry = (int)($row['time'] ?? 0);
        if ($expiry !== 0 && time() >= $expiry) {
            return ['ok' => false, 'reason' => faoxima_textbot_get('dyn_errors_discount_expired', '❌ زمان کد تخفیف به پایان رسیده است.')];
        }

        $limitTotal = (int)($row['limitDiscount'] ?? 0);
        if ($limitTotal > 0 && (int)($row['usedDiscount'] ?? 0) >= $limitTotal) {
            return ['ok' => false, 'reason' => '❌ ظرفیت استفاده از این کد تخفیف به پایان رسیده است.'];
        }

        $usedByUser = (int) FaoximaDb::fetchScalar(
            'SELECT COUNT(*) FROM Giftcodeconsumed WHERE id_user = :u AND code = :c AND COALESCE(released, 0) = 0',
            [':u' => (string)$user['id'], ':c' => $code]
        );
        $useUser = (int)($row['useuser'] ?? 0);
        if ($useUser > 0 && $usedByUser >= $useUser) {
            return ['ok' => false, 'reason' => '⭕️ سقف استفاده شما از این کد تخفیف پر شده است.'];
        }

        if ((string)($row['usefirst'] ?? '') === '1') {
            $invoiceCount = self::completedPurchaseCount((string)$user['id']);
            if ($invoiceCount != 0) {
                return ['ok' => false, 'reason' => '❌ این کد تخفیف فقط برای اولین خرید قابل استفاده است.'];
            }
        }

        $vt = self::valueType($row);
        $val = (float)($row['price'] ?? 0);
        if ($vt === 'percent' && ($val <= 0 || $val > 100)) {
            return ['ok' => false, 'reason' => '❌ درصد کد تخفیف نامعتبر است.'];
        }
        if ($vt === 'amount' && $val <= 0) {
            return ['ok' => false, 'reason' => '❌ مبلغ کد تخفیف نامعتبر است.'];
        }

        return ['ok' => true];
    }

    public static function hasEligible(string $section, string $codeProduct, string $codePanel, string $codeCategory, array $user): bool
    {
        $section = in_array($section, self::SECTIONS, true) ? $section : 'all';

        if ($section !== 'charge') {
            $userDiscount = (int)($user['pricediscount'] ?? 0);
            if ($userDiscount !== 0) {
                return false;
            }
        }

        [$codeProduct, $codePanel, $codeCategory] = self::normalizeTargeting($codeProduct, $codePanel, $codeCategory);
        $agent = (string)($user['agent'] ?? 'f');

        $rows = FaoximaDb::fetchAll(
            "SELECT * FROM DiscountSell
              WHERE " . self::agentClause() . "
                AND " . self::sectionClause() . "
                AND (status IS NULL OR status = '' OR status = 'active')
                AND (target_user IS NULL OR target_user = '' OR target_user = :uid)
                AND " . self::targetingClause(),
            [
                ':agent' => $agent,
                ':section' => $section,
                ':uid' => (string)$user['id'],
                ':cp' => $codeProduct,
                ':cpan' => $codePanel,
                ':cpan2' => $codePanel,
                ':cat' => $codeCategory,
            ]
        );

        foreach ($rows as $row) {
            $check = self::rowPassesUsageChecks($row, (string)($row['codeDiscount'] ?? ''), $user);
            if (!empty($check['ok'])) {
                return true;
            }
        }
        return false;
    }

    public static function hasEligibleForCategories(string $section, string $codeProduct, string $codePanel, array $codeCategories, array $user): bool
    {
        $section = in_array($section, self::SECTIONS, true) ? $section : 'all';

        if ($section !== 'charge') {
            $userDiscount = (int)($user['pricediscount'] ?? 0);
            if ($userDiscount !== 0) {
                return false;
            }
        }

        [$codeProduct, $codePanel, ] = self::normalizeTargeting($codeProduct, $codePanel, 'all');
        $agent = (string)($user['agent'] ?? 'f');
        [$targeting, $targetingParams] = self::targetingClauseMulti($codeCategories);

        $rows = FaoximaDb::fetchAll(
            "SELECT * FROM DiscountSell
              WHERE " . self::agentClause() . "
                AND " . self::sectionClause() . "
                AND (status IS NULL OR status = '' OR status = 'active')
                AND (target_user IS NULL OR target_user = '' OR target_user = :uid)
                AND " . $targeting,
            [
                ':agent' => $agent,
                ':section' => $section,
                ':uid' => (string)$user['id'],
                ':cp' => $codeProduct,
                ':cpan' => $codePanel,
                ':cpan2' => $codePanel,
            ] + $targetingParams
        );

        foreach ($rows as $row) {
            $check = self::rowPassesUsageChecks($row, (string)($row['codeDiscount'] ?? ''), $user);
            if (!empty($check['ok'])) {
                return true;
            }
        }
        return false;
    }

    public static function validateSellForCategories(string $code, string $section, string $codeProduct, string $codePanel, array $codeCategories, array $user, bool $blockIfUserDiscount = true): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'reason' => '❌ کد تخفیف را وارد کنید.'];
        }

        $section = in_array($section, self::SECTIONS, true) ? $section : 'all';

        if ($blockIfUserDiscount && $section !== 'charge') {
            $userDiscount = (int)($user['pricediscount'] ?? 0);
            if ($userDiscount !== 0) {
                return ['ok' => false, 'reason' => '❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.'];
            }
        }

        $agent = (string)($user['agent'] ?? 'f');
        [$codeProduct, $codePanel, ] = self::normalizeTargeting($codeProduct, $codePanel, 'all');
        [$targeting, $targetingParams] = self::targetingClauseMulti($codeCategories);

        $row = FaoximaDb::fetchOne(
            "SELECT * FROM DiscountSell
              WHERE codeDiscount = :code
                AND " . self::agentClause() . "
                AND " . self::sectionClause() . "
                AND (status IS NULL OR status = '' OR status = 'active')
                AND (target_user IS NULL OR target_user = '' OR target_user = :uid)
                AND " . $targeting . "
              LIMIT 1",
            [
                ':code' => $code,
                ':agent' => $agent,
                ':section' => $section,
                ':uid' => (string)$user['id'],
                ':cp' => $codeProduct,
                ':cpan' => $codePanel,
                ':cpan2' => $codePanel,
            ] + $targetingParams
        );
        if ($row === null) {
            return ['ok' => false, 'reason' => '❌ کد تخفیف نامعتبر است یا برای این بخش فعال نیست.'];
        }

        $check = self::rowPassesUsageChecks($row, $code, $user);
        if (empty($check['ok'])) {
            return $check;
        }

        $vt = self::valueType($row);
        $val = (float)($row['price'] ?? 0);

        return [
            'ok'         => true,
            'code'       => $code,
            'value_type' => $vt,
            'value'      => $val,
            'label'      => self::describe($row),
            'row'        => $row,
        ];
    }

    public static function validateSell(string $code, string $section, string $codeProduct, string $codePanel, string $codeCategory, array $user, bool $blockIfUserDiscount = true): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'reason' => '❌ کد تخفیف را وارد کنید.'];
        }

        $section = in_array($section, self::SECTIONS, true) ? $section : 'all';

        if ($blockIfUserDiscount && $section !== 'charge') {
            $userDiscount = (int)($user['pricediscount'] ?? 0);
            if ($userDiscount !== 0) {
                return ['ok' => false, 'reason' => '❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.'];
            }
        }

        $agent = (string)($user['agent'] ?? 'f');
        [$codeProduct, $codePanel, $codeCategory] = self::normalizeTargeting($codeProduct, $codePanel, $codeCategory);

        $row = FaoximaDb::fetchOne(
            "SELECT * FROM DiscountSell
              WHERE codeDiscount = :code
                AND " . self::agentClause() . "
                AND " . self::sectionClause() . "
                AND (status IS NULL OR status = '' OR status = 'active')
                AND (target_user IS NULL OR target_user = '' OR target_user = :uid)
                AND " . self::targetingClause() . "
              LIMIT 1",
            [
                ':code' => $code,
                ':agent' => $agent,
                ':section' => $section,
                ':uid' => (string)$user['id'],
                ':cp' => $codeProduct,
                ':cpan' => $codePanel,
                ':cpan2' => $codePanel,
                ':cat' => $codeCategory,
            ]
        );
        if ($row === null) {
            return ['ok' => false, 'reason' => '❌ کد تخفیف نامعتبر است یا برای این بخش فعال نیست.'];
        }

        $check = self::rowPassesUsageChecks($row, $code, $user);
        if (empty($check['ok'])) {
            return $check;
        }

        $vt = self::valueType($row);
        $val = (float)($row['price'] ?? 0);

        return [
            'ok'         => true,
            'code'       => $code,
            'value_type' => $vt,
            'value'      => $val,
            'label'      => self::describe($row),
            'row'        => $row,
        ];
    }

    public static function logOrderDiscount(array $ctx): void
    {
        try {
            $pdo = FaoximaDb::pdo();
            $pdo->prepare(
                'INSERT INTO order_discount_log
                    (id_user, id_invoice, code, kind, value_type, value_raw, price_before, discount_amount, price_after, section, created_at)
                 VALUES (:id_user, :id_invoice, :code, :kind, :value_type, :value_raw, :price_before, :discount_amount, :price_after, :section, :created_at)'
            )->execute([
                ':id_user'         => (string)($ctx['id_user'] ?? ''),
                ':id_invoice'      => $ctx['id_invoice'] ?? null,
                ':code'            => (string)($ctx['code'] ?? ''),
                ':kind'            => (string)($ctx['kind'] ?? 'sell'),
                ':value_type'      => (string)($ctx['value_type'] ?? 'percent'),
                ':value_raw'       => $ctx['value_raw'] ?? null,
                ':price_before'    => (int)round((float)($ctx['price_before'] ?? 0)),
                ':discount_amount' => (int)round((float)($ctx['discount_amount'] ?? 0)),
                ':price_after'     => (int)round((float)($ctx['price_after'] ?? 0)),
                ':section'         => $ctx['section'] ?? null,
                ':created_at'      => (string)time(),
            ]);
        } catch (Throwable $e) {
        }
    }

    public static function logSale(array $ctx): void
    {
        try {
            $pdo = FaoximaDb::pdo();
            $pdo->prepare(
                'INSERT INTO sale_ledger
                    (id_user, id_invoice, Service_location, kind, name_product, amount, source, created_at)
                 VALUES (:id_user, :id_invoice, :panel, :kind, :name_product, :amount, :source, :created_at)'
            )->execute([
                ':id_user'      => (string)($ctx['id_user'] ?? ''),
                ':id_invoice'   => $ctx['id_invoice'] ?? null,
                ':panel'        => (string)($ctx['Service_location'] ?? ''),
                ':kind'         => (string)($ctx['kind'] ?? ''),
                ':name_product' => $ctx['name_product'] ?? null,
                ':amount'       => (int)round((float)($ctx['amount'] ?? 0)),
                ':source'       => (string)($ctx['source'] ?? 'bot'),
                ':created_at'   => (string)time(),
            ]);
        } catch (Throwable $e) {
        }
    }

    public static function chargeWallet(string $code, array $user, $amount, int $allowNegative, string $operationId): array
    {
        $pdo = FaoximaDb::pdo();
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
                if (!function_exists('rx_discount_mark_used_atomic')) {
                    throw new RuntimeException('discount_unavailable');
                }
                $reserved = rx_discount_mark_used_atomic($code, (string)$user['id'], 'sell', $operationId);
                if (empty($reserved['ok']) || !empty($reserved['replayed'])) {
                    throw new RuntimeException('discount_' . ($reserved['reason'] ?? 'already_reserved'));
                }
            }
            $charged = (float)$amount > 0
                ? balance_atomic_charge($user['id'], $amount, $allowNegative)
                : ['ok' => true, 'new_balance' => (float)($user['Balance'] ?? 0)];
            if (empty($charged['ok'])) {
                throw new RuntimeException(($charged['reason'] ?? '') === 'insufficient-or-stale' ? 'insufficient_balance' : 'charge_failed');
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

    public static function releaseWalletDiscount(string $code, array $user, string $operationId): bool
    {
        if ($code === '') return true;
        $pdo = FaoximaDb::pdo();
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
            if (function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('DiscountSell', 'codeDiscount', $code);
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
                FaoximaLogger::critical('Wallet discount release rollback failed', ['operation_id' => $operationId]);
            }
            FaoximaLogger::error('Wallet discount release failed', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function markSellUsed(string $code, array $user): bool
    {
        $code = trim($code);
        if ($code === '') return false;
        if (function_exists('rx_discount_mark_used_atomic')) {
            $result = rx_discount_mark_used_atomic($code, (string)$user['id'], 'sell');
            if (!empty($result['ok'])) {
                return true;
            }
            if ((string)($result['reason'] ?? '') !== 'db_unavailable') {
                return false;
            }
        }
        try {
            $pdo = FaoximaDb::pdo();
            $row = FaoximaDb::fetchOne('SELECT usedDiscount FROM DiscountSell WHERE codeDiscount = :c LIMIT 1', [':c' => $code]);
            $used = $row !== null ? ((int)($row['usedDiscount'] ?? 0) + 1) : 1;
            $pdo->prepare('UPDATE DiscountSell SET usedDiscount = :v WHERE codeDiscount = :c')
                ->execute([':v' => (string)$used, ':c' => $code]);
        } catch (Throwable $e) {
        }
        self::recordConsumed($code, (string)$user['id'], 'sell');
        return true;
    }

    private static function recordConsumed(string $code, string $userId, string $kind): void
    {
        try {
            $pdo = FaoximaDb::pdo();
            $pdo->prepare('INSERT INTO Giftcodeconsumed (id_user, code, kind, consumed_at) VALUES (:u, :c, :k, :t)')
                ->execute([':u' => $userId, ':c' => $code, ':k' => $kind, ':t' => (string)time()]);
        } catch (Throwable $e) {
            try {
                $pdo = FaoximaDb::pdo();
                $pdo->prepare('INSERT INTO Giftcodeconsumed (id_user, code) VALUES (:u, :c)')
                    ->execute([':u' => $userId, ':c' => $code]);
            } catch (Throwable $e2) {
            }
        }
    }

    public static function releaseLastUnpaidDiscount(string $userId, ?string $discountCode = null, ?int $referenceTime = null): bool
    {
        $userId = trim($userId);
        if ($userId === '') return false;
        try {
            $pdo = FaoximaDb::pdo();
            if ($referenceTime !== null && $referenceTime > 0) {
                $low = (string)($referenceTime - 900);
                $high = (string)($referenceTime + 900);
            } else {
                $low = (string)(time() - 1800);
                $high = (string)(time() + 60);
            }
            $params = [':u' => $userId, ':lo' => $low, ':hi' => $high];
            $codeClause = '';
            if ($discountCode !== null && trim($discountCode) !== '') {
                $codeClause = ' AND code = :c';
                $params[':c'] = trim($discountCode);
            }
            $row = FaoximaDb::fetchOne(
                "SELECT id, code FROM Giftcodeconsumed
                  WHERE id_user = :u
                    AND kind = 'sell'
                    AND (related_order IS NULL OR related_order = '')
                    AND (released IS NULL OR released = 0)
                    AND consumed_at <> ''
                    AND CAST(consumed_at AS UNSIGNED) BETWEEN :lo AND :hi" . $codeClause . "
                  ORDER BY id DESC LIMIT 1",
                $params
            );
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
}
