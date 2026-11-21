<?php

declare(strict_types=1);

namespace Faoxima\Orders\Discounts;

use Faoxima\Core\Database\PdoQueries;
use PDO;
use Throwable;

final class DiscountRules
{
    const SECTIONS = ['buy', 'extend', 'volume', 'time', 'charge', 'all'];

    public static function completedPurchaseCount(PDO $pdo, string $userId): int
    {
        return (int) (new PdoQueries($pdo))->fetchScalar(
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

    public static function redeemGift(PDO $pdo, string $code, array $user): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'reason' => '❌ کد هدیه را وارد کنید.'];
        }

        if (!is_callable([\Faoxima\Wallet\Gifts\Gifts::class, 'giftRedeemAtomic'])) {
            return ['ok' => false, 'reason' => '❌ خطا در واریز هدیه. لطفاً دوباره تلاش کنید.'];
        }
        $result = \Faoxima\Wallet\Gifts\Gifts::giftRedeemAtomic($pdo, $code, (string)$user['id']);
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

    private static function rowPassesUsageChecks(PDO $pdo, array $row, string $code, array $user): array
    {
        $expiry = (int)($row['time'] ?? 0);
        if ($expiry !== 0 && time() >= $expiry) {
            return ['ok' => false, 'reason' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_discount_expired', '❌ زمان کد تخفیف به پایان رسیده است.')];
        }

        $limitTotal = (int)($row['limitDiscount'] ?? 0);
        if ($limitTotal > 0 && (int)($row['usedDiscount'] ?? 0) >= $limitTotal) {
            return ['ok' => false, 'reason' => '❌ ظرفیت استفاده از این کد تخفیف به پایان رسیده است.'];
        }

        $usedByUser = (int) (new PdoQueries($pdo))->fetchScalar(
            'SELECT COUNT(*) FROM Giftcodeconsumed WHERE id_user = :u AND code = :c AND COALESCE(released, 0) = 0',
            [':u' => (string)$user['id'], ':c' => $code]
        );
        $useUser = (int)($row['useuser'] ?? 0);
        if ($useUser > 0 && $usedByUser >= $useUser) {
            return ['ok' => false, 'reason' => '⭕️ سقف استفاده شما از این کد تخفیف پر شده است.'];
        }

        if ((string)($row['usefirst'] ?? '') === '1') {
            $invoiceCount = self::completedPurchaseCount($pdo, (string)$user['id']);
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

    public static function hasEligible(PDO $pdo, string $section, string $codeProduct, string $codePanel, string $codeCategory, array $user): bool
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

        $rows = (new PdoQueries($pdo))->fetchAll(
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
            $check = self::rowPassesUsageChecks($pdo, $row, (string)($row['codeDiscount'] ?? ''), $user);
            if (!empty($check['ok'])) {
                return true;
            }
        }
        return false;
    }

    public static function hasEligibleForCategories(PDO $pdo, string $section, string $codeProduct, string $codePanel, array $codeCategories, array $user): bool
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

        $rows = (new PdoQueries($pdo))->fetchAll(
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
            $check = self::rowPassesUsageChecks($pdo, $row, (string)($row['codeDiscount'] ?? ''), $user);
            if (!empty($check['ok'])) {
                return true;
            }
        }
        return false;
    }

    public static function validateSellForCategories(PDO $pdo, string $code, string $section, string $codeProduct, string $codePanel, array $codeCategories, array $user, bool $blockIfUserDiscount = true): array
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

        $row = (new PdoQueries($pdo))->fetchOne(
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

        $check = self::rowPassesUsageChecks($pdo, $row, $code, $user);
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

    public static function validateSell(PDO $pdo, string $code, string $section, string $codeProduct, string $codePanel, string $codeCategory, array $user, bool $blockIfUserDiscount = true): array
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

        $row = (new PdoQueries($pdo))->fetchOne(
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

        $check = self::rowPassesUsageChecks($pdo, $row, $code, $user);
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

    public static function logOrderDiscount(PDO $pdo, array $ctx): void
    {
        try {
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

    public static function logSale(PDO $pdo, array $ctx): void
    {
        try {
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

    public static function markSellUsed(PDO $pdo, string $code, array $user): bool
    {
        $code = trim($code);
        if ($code === '') return false;
        $result = \Faoxima\Orders\Discounts\SellDiscounts::discountMarkUsedAtomic($pdo, $code, (string)$user['id'], 'sell');
        return !empty($result['ok']);
    }
}
