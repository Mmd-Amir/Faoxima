<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class CancelserviceRequestHelpers
{
    public static function fx_table_has_col(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
        $st->execute([':t' => $table, ':c' => $col]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) { return false; }
}

    public static function fx_req_status($raw): array {
    $r = strtolower(trim((string)$raw));
    $raws = (string)$raw;
    if (in_array($r, ['unpaid','waiting','awaitinghash','pending'], true) || strpos($r,'wait')!==false || mb_strpos($raws,'در انتظار')!==false) return ['در انتظار', 'badge-warning', 'wait'];
    if ($r === 'paid' || $r === 'success' || mb_strpos($raws,'تایید')!==false || mb_strpos($raws,'موفق')!==false) return ['تاییدشده', 'badge-active', 'ok'];
    if ($r === 'reject' || $r === 'rejected' || mb_strpos($raws,'رد')!==false || mb_strpos($raws,'لغو')!==false) return ['ردشده', 'badge-block', 'reject'];
    if ($r === 'expire' || $r === 'expired' || mb_strpos($raws,'منقضی')!==false) return ['منقضی', 'badge-gray', 'expire'];
    return [$raws !== '' ? $raws : '—', 'badge-gray', 'other'];
}

    public static function fx_req_type($idOrder, $method): string {
    $io = (string)$idOrder; $m = strtolower(trim((string)$method));
    if ($m === 'cart to cart' || $m === 'carttocart_pv') return 'رسید بانکی';
    if ($m === 'add balance by admin' || $m === 'low balance by admin') return 'تنظیم کیف پول (ادمین)';
    if (in_array($m, ['plisio','nowpayment','digitaltron','arze digital offline'], true)) return 'ارز دیجیتال';
    if ($m === 'star telegram') return 'استارز تلگرام';
    if (strpos($io,'Add_Balance')!==false) return 'افزایش موجودی';
    if (strpos($io,'getconfigafterpay')!==false) return 'خرید سرویس';
    if (strpos($io,'getextenduser')!==false) return 'تمدید سرویس';
    if (strpos($io,'getextravolumeuser')!==false) return 'حجم اضافه';
    if (strpos($io,'getextratimeuser')!==false) return 'زمان اضافه';
    if ($m !== '') return 'پرداخت (' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ')';
    return 'پرداخت';
}

    public static function fx_purpose($idOrder): string {
    $io = (string)$idOrder;
    if (strpos($io,'Add_Balance')!==false) return 'افزایش موجودی کیف پول';
    if (strpos($io,'getconfigafterpay')!==false) return 'خرید سرویس';
    if (strpos($io,'getextenduser')!==false) return 'تمدید سرویس';
    if (strpos($io,'getextravolumeuser')!==false) return 'حجم اضافه';
    if (strpos($io,'getextratimeuser')!==false) return 'زمان اضافه';
    return '';
}

    public static function fx_method_label($method): string {
    $m = strtolower(trim((string)$method));
    if ($m === '') return '';
    $map = [
        'cart to cart'         => 'کارت به کارت',
        'carttocart_pv'        => 'کارت به کارت (پیروزپی)',
        'piroozpay'            => 'پیروزپی',
        'iranpay1'             => 'ایران‌پی',
        'currency rial 3'      => 'ایران‌پی',
        'plisio'               => 'ارز دیجیتال (Plisio)',
        'nowpayment'           => 'ارز دیجیتال (NOWPayments)',
        'digitaltron'          => 'ارز دیجیتال (دیجیتال‌ترون)',
        'arze digital offline' => 'ارز دیجیتال (آفلاین)',
        'star telegram'        => 'استارز تلگرام',
        'add balance by admin' => 'افزایش موجودی توسط ادمین',
        'low balance by admin' => 'کاهش موجودی توسط ادمین',
    ];
    return $map[$m] ?? (string)$method;
}

    public static function fx_req_ts($value): int {
    $v = trim((string)$value);
    if ($v === '') return 0;
    if (ctype_digit($v)) return (int)$v;
    $ts = strtotime(str_replace('/', '-', $v));
    return $ts !== false ? $ts : 0;
}

    public static function fx_invoice_username($idInvoice): string {
    $s = (string)$idInvoice;
    if (strpos($s, '|') === false) return '';
    $s = explode('|', $s, 2)[1];
    $s = preg_replace('/%[0-9a-fA-F]+$/', '', $s);
    return trim((string)$s);
}

    public static function fx_req_delete_tokens(PDO $pdo, array $requestedTokens): int {
    $prIds = []; $csIds = [];
    foreach ($requestedTokens as $token) {
        if (preg_match('/^pr:(\d+)$/', (string)$token, $m)) $prIds[] = (int)$m[1];
        elseif (preg_match('/^cs:(\d+)$/', (string)$token, $m)) $csIds[] = (int)$m[1];
    }
    $deletedCount = 0;
    if ($prIds) {
        $ph = implode(',', array_fill(0, count($prIds), '?'));
        $st = $pdo->prepare("SELECT id, payment_Status FROM Payment_report WHERE id IN ($ph)");
        $st->execute($prIds);
        $allowedPr = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (in_array(strtolower((string)$row['payment_Status']), ['reject', 'rejected', 'expire', 'expired'], true)) {
                $allowedPr[] = (int)$row['id'];
            }
        }
        if ($allowedPr) $deletedCount += \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_ids($pdo, 'Payment_report', 'id', $allowedPr);
    }
    if ($csIds) $deletedCount += \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_ids($pdo, 'cancel_service', 'id', $csIds);
    return $deletedCount;
}
}
