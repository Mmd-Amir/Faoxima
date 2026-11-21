<?php

namespace Faoxima\Cashback;

use PDO;

final class Eligibility
{
    public static function cashbackIsFirstPaidPurchase($runtimePdo, $userId, $currentOrderId = null)
    {
        $pdo = $runtimePdo;
        $userId = (string) $userId;
        if ($userId === '' || !($pdo instanceof PDO)) {
            return true;
        }
        $sql = "SELECT COUNT(*) FROM Payment_report WHERE id_user = :uid AND payment_Status = 'paid'";
        $params = [':uid' => $userId];
        if ($currentOrderId !== null && $currentOrderId !== '') {
            $sql .= " AND id_order <> :oid";
            $params[':oid'] = (string) $currentOrderId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) === 0;
    }

    public static function cashbackTargetEligible($runtimePdo, $target, $userRegisterTs, $userId = null, $currentOrderId = null)
    {
        $target = (string) $target;
        if ($target === '' || $target === 'all') {
            return true;
        }

        if ($target === 'firstpurchase') {
            if ($userId === null) {
                return true;
            }
            return self::cashbackIsFirstPaidPurchase($runtimePdo, $userId, $currentOrderId);
        }

        $registerTs = (is_numeric($userRegisterTs)) ? (int) $userRegisterTs : null;
        if ($registerTs === null || $registerTs <= 0) {
            return true;
        }

        $days = null;
        if (strpos($target, 'custom:') === 0) {
            $days = (int) substr($target, 7);
        } elseif (ctype_digit($target)) {
            $days = (int) $target;
        }

        if ($days === null || $days <= 0) {
            return true;
        }

        $windowStart = time() - ($days * 86400);
        return $registerTs >= $windowStart;
    }

    public static function cashbackScopeOptionMap()
    {
        return [
            'purchase' => '🛒 خرید اشتراک',
            'extend'   => '🔄 تمدید سرویس',
            'volume'   => '📦 خرید حجم',
            'time'     => '⏱ خرید روز',
            'topup'    => '💰 شارژ کیف پول',
        ];
    }

    public static function cashbackScopeDefault()
    {
        return array_keys(self::cashbackScopeOptionMap());
    }

    public static function cashbackScopeFromInvoice($idInvoice)
    {
        $prefix = explode('|', (string) $idInvoice, 2)[0];
        $map = [
            'getconfigafterpay'  => 'purchase',
            'getextenduser'      => 'extend',
            'getextravolumeuser' => 'volume',
            'getextratimeuser'   => 'time',
        ];
        return $map[$prefix] ?? 'topup';
    }

    public static function cashbackScopeEligible($scopeCsv, $idInvoice)
    {
        $scopeCsv = trim((string) $scopeCsv);
        if ($scopeCsv === '' || $scopeCsv === 'all') {
            return true;
        }
        $selected = array_filter(array_map('trim', explode(',', $scopeCsv)));
        if (empty($selected)) {
            return true;
        }
        $actionScope = self::cashbackScopeFromInvoice($idInvoice);
        return in_array($actionScope, $selected, true);
    }

    public static function cashbackEligibleForKey($runtimePdo, $cashbackKey, $userRegisterTs, $idInvoice = null, $userId = null, $currentOrderId = null)
    {
        if ($cashbackKey === '') {
            return true;
        }
        $row = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $cashbackKey . '_target', 'select');
        $target = is_array($row) ? (string) ($row['ValuePay'] ?? 'all') : 'all';
        if (!self::cashbackTargetEligible($runtimePdo, $target, $userRegisterTs, $userId, $currentOrderId)) {
            return false;
        }
        if ($idInvoice === null) {
            return true;
        }
        $scopeRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $cashbackKey . '_scope', 'select');
        $scopeCsv = is_array($scopeRow) ? (string) ($scopeRow['ValuePay'] ?? 'all') : 'all';
        return self::cashbackScopeEligible($scopeCsv, $idInvoice);
    }

    public static function shopCashbackEligible($runtimePdo, $shopSettingKey, $userRegisterTs, $idInvoice = null, $userId = null, $currentOrderId = null)
    {
        if ($shopSettingKey === '') {
            return true;
        }
        $row = \Faoxima\Core\Database\Db::select('shopSetting', 'value', 'Namevalue', $shopSettingKey . '_target', 'select');
        $target = is_array($row) ? (string) ($row['value'] ?? 'all') : 'all';
        if (!self::cashbackTargetEligible($runtimePdo, $target, $userRegisterTs, $userId, $currentOrderId)) {
            return false;
        }
        if ($idInvoice === null) {
            return true;
        }
        $scopeRow = \Faoxima\Core\Database\Db::select('shopSetting', 'value', 'Namevalue', $shopSettingKey . '_scope', 'select');
        $scopeCsv = is_array($scopeRow) ? (string) ($scopeRow['value'] ?? 'all') : 'all';
        return self::cashbackScopeEligible($scopeCsv, $idInvoice);
    }
}
