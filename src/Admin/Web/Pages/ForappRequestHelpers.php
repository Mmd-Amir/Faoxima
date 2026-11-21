<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ForappRequestHelpers
{
    public static function fxForappJdate($raw): string
    {
        $s = trim((string) $raw);
        if ($s === '') return '';
        $ts = ctype_digit($s) ? (int) $s : strtotime($s);
        if ($ts === false || $ts <= 0) return $s;
        return jdate('Y/m/d H:i:s', $ts, '', 'Asia/Tehran', 'fa');
    }

    public static function fxForappAmount($raw): ?int
    {
        $s = strtr(trim((string) $raw), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ',' => '', '٬' => '', '،' => '', ' ' => '',
        ]);
        if ($s === '' || !preg_match('/^[0-9]{1,15}$/', $s)) return null;
        return (int) $s;
    }

    public static function fxForappH($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function fxForappErrorText(string $reason): string
    {
        if (method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_error_text')) {
            return \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_error_text($reason);
        }
        return 'عملیات انجام نشد.';
    }

    public static function fxForappField(string $label, string $valueHtml): string
    {
        return '<div class="detail-sheet__field"><span class="detail-sheet__field-label">' . self::fxForappH($label) . '</span><span class="detail-sheet__field-value">' . $valueHtml . '</span></div>';
    }

    public static function fxForappBadge(array $meta): string
    {
        $class = isset($meta['class']) ? (string) $meta['class'] : 'badge-gray';
        $label = isset($meta['label']) ? (string) $meta['label'] : '';
        return '<span class="badge ' . self::fxForappH($class) . '">' . self::fxForappH($label) . '</span>';
    }

    public static function fxForappDiffLabel(?int $diffRial): string
    {
        if ($diffRial === null) return '—';
        if ($diffRial === 0) return 'بدون اختلاف';
        return number_format(intdiv(abs($diffRial), 10)) . ' تومان ' . ($diffRial < 0 ? 'کمتر' : 'بیشتر');
    }

    public static function fxForappPaymentBadgeMeta(string $status, string $state, bool $expired, string $resolution): array
    {
        if ($status === 'paid') return ['label' => 'تایید شده', 'class' => 'badge-success'];
        if ($status === 'reject') return ['label' => 'رد شده', 'class' => 'badge-danger'];
        if ($status === 'cancelled') return ['label' => 'لغو شده', 'class' => 'badge-gray'];
        if ($state === 'closed' && in_array($resolution, ['cancelled', 'void'], true)) return ['label' => 'لغو شده', 'class' => 'badge-gray'];
        if ($state === 'closed' && in_array($resolution, ['rejected', 'rejected_blocked'], true)) return ['label' => 'رد شده', 'class' => 'badge-danger'];
        if ($expired) return ['label' => 'منقضی شده', 'class' => 'badge-warning'];
        if ($state === 'approved') return ['label' => 'تایید شده', 'class' => 'badge-success'];
        if ($state === 'credited') return ['label' => 'شارژ کیف پول شده', 'class' => 'badge-success'];
        if ($status === 'processing') return ['label' => 'در حال پردازش', 'class' => 'badge-info'];
        if ($status === 'reconciling') return ['label' => 'در حال تطبیق', 'class' => 'badge-info'];
        if ($status === 'waiting') return ['label' => 'در انتظار بررسی', 'class' => 'badge-warning'];
        if ($status === 'pending') return ['label' => 'در حال ارسال رسید', 'class' => 'badge-warning'];
        if ($status === 'manual_review') return ['label' => 'نیازمند بررسی دستی', 'class' => 'badge-warning'];
        return ['label' => 'در انتظار پرداخت', 'class' => 'badge-gray'];
    }

    public static function fxForappMatchBadgeMeta(string $status, string $state, bool $expired, string $resolution): ?array
    {
        if (in_array($status, ['paid', 'reject', 'cancelled'], true)) return null;
        if ($expired) return null;
        if (in_array($state, ['approved', 'credited'], true)) return null;
        if ($state === 'closed' && in_array($resolution, ['cancelled', 'void', 'rejected', 'rejected_blocked', 'expired', 'admin_credit', 'refunded'], true)) return null;
        if ($state === 'matched') return ['label' => 'تطبیق یافته', 'class' => 'badge-info'];
        if ($state === 'review') return ['label' => 'نیازمند بررسی', 'class' => 'badge-warning'];
        if ($state === 'open') return ['label' => 'بدون تطبیق', 'class' => 'badge-gray'];
        if ($state === 'closed') return ['label' => 'بسته شده', 'class' => 'badge-gray'];
        if ($state === 'void') return ['label' => 'باطل', 'class' => 'badge-gray'];
        return null;
    }

    public static function fxForappDepositTime(array $dep): int
    {
        $stored = (int) ($dep['stored_at'] ?? 0);
        $ms = isset($dep['received_at_ms']) ? (int) $dep['received_at_ms'] : 0;
        if ($ms > 0) {
            $skew = defined('RX_SBV_DEVICE_SKEW') ? (int) RX_SBV_DEVICE_SKEW : 600;
            $sec = intdiv($ms, 1000);
            if ($sec > 0 && $sec <= $stored + $skew && $sec >= $stored - 604800) return $sec;
        }
        return $stored;
    }

    public static function fxForappRunAction(array $post, string $fxActor): array
{
    $fxAction = (string) $post['_fx_action'];
    $fxPostOrder = trim((string) ($post['id_order'] ?? ''));
    $fxPostDeposit = (int) ($post['deposit_id'] ?? 0);
    $fxPostReason = trim((string) ($post['reason'] ?? ''));
    $fxPostCpage = max(0, (int) ($post['cpage'] ?? 0));
    $fxFlash = ['type' => 'err', 'text' => 'درخواست نامعتبر است.'];

    if ($fxAction === 'refresh') {
        $fxFlash = ['type' => 'ok', 'text' => 'فهرست پیشنهادهای پرداخت و واریز بازخوانی شد.'];
    } elseif ($fxAction === 'approve' && $fxPostOrder !== '') {
        if (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_admin_approve')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_approve($fxPostOrder, $fxActor, $fxPostReason);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'پرداخت فوراپ با موفقیت تأیید و تسویه شد.']
                : ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'reject' && $fxPostOrder !== '') {
        if ($fxPostReason === '') {
            $fxFlash = ['type' => 'err', 'text' => 'برای رد کردن پرداخت، ثبت دلیل الزامی است.'];
        } elseif (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_admin_reject')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_reject($fxPostOrder, $fxActor, $fxPostReason, false, $fxPostDeposit > 0 ? $fxPostDeposit : null);
            if (!empty($res['ok'])) {
                $fxFlash = ['type' => 'ok', 'text' => !empty($res['already_rejected']) ? 'این پرداخت قبلاً رد شده بود و گزارش تصمیم بازگردانی شد.' : 'پرداخت فوراپ رد شد.'];
            } else {
                $fxFlash = ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
            }
        }
    } elseif ($fxAction === 'set_received' && $fxPostOrder !== '') {
        $fxReceived = self::fxForappAmount($post['received'] ?? '');
        if ($fxReceived === null) {
            $fxFlash = ['type' => 'err', 'text' => 'مبلغ دریافتی واردشده معتبر نیست.'];
        } elseif ($fxPostReason === '') {
            $fxFlash = ['type' => 'err', 'text' => 'برای اصلاح مبلغ دریافتی، ثبت دلیل الزامی است.'];
        } elseif (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_admin_set_received')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_set_received($fxPostOrder, $fxActor, $fxReceived, $fxPostReason);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'مبلغ دریافتی پرداخت اصلاح شد.']
                : ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'credit' && $fxPostOrder !== '') {
        if ($fxPostReason === '') {
            $fxFlash = ['type' => 'err', 'text' => 'برای واریز مبلغ دریافتی به کیف پول، ثبت دلیل الزامی است.'];
        } elseif (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_admin_credit')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_credit($fxPostOrder, $fxActor, $fxPostReason);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'مبلغ دریافتی به کیف پول کاربر واریز شد.']
                : ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'cancel_expired' && $fxPostOrder !== '') {
        if (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_admin_delete_expired_payment')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_delete_expired_payment($fxPostOrder, $fxActor, $fxPostDeposit > 0 ? $fxPostDeposit : null);
            if (!empty($res['ok'])) {
                $fxFlash = ['type' => 'ok', 'text' => !empty($res['already_removed']) ? 'این پرداخت منقضی قبلاً از فهرست حذف شده بود.' : 'پرداخت منقضی لغو و از فهرست پیشنهادها حذف شد.'];
            } else {
                $fxFlash = ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
            }
        }
    } elseif ($fxAction === 'link_deposit' && $fxPostOrder !== '' && $fxPostDeposit > 0) {
        $cand = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_order_deposit_candidates') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_deposit_candidates($fxPostOrder, 5, $fxPostCpage, $fxPostDeposit) : ['ok' => false];
        $found = false;
        foreach ((array) ($cand['rows'] ?? []) as $candRow) {
            if ((int) ($candRow['id'] ?? 0) === $fxPostDeposit) {
                $found = true;
                break;
            }
        }
        if (!empty($cand['blocked'])) {
            $fxFlash = ['type' => 'err', 'text' => self::fxForappErrorText((string) $cand['blocked'])];
        } elseif (empty($cand['ok']) || !$found) {
            $fxFlash = ['type' => 'err', 'text' => 'شرایط تطبیق این واریز تغییر کرده است؛ فهرست پیشنهادها را دوباره بررسی کنید.'];
        } elseif (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_link')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه اتصال واریز در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_link($fxPostDeposit, $fxPostOrder, $fxActor);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'واریز بانکی به پرداخت متصل شد؛ نتیجه را تأیید یا رد کنید.']
                : ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'link_payment' && $fxPostOrder !== '' && $fxPostDeposit > 0) {
        $cand = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_report_candidates') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_report_candidates($fxPostDeposit, 5, $fxPostCpage, $fxPostOrder) : ['ok' => false];
        $found = false;
        foreach ((array) ($cand['rows'] ?? []) as $candRow) {
            if ((string) ($candRow['id_order'] ?? '') === $fxPostOrder) {
                $found = true;
                break;
            }
        }
        if (empty($cand['ok']) || !$found) {
            $fxFlash = ['type' => 'err', 'text' => 'شرایط تطبیق این پرداخت تغییر کرده است؛ فهرست پیشنهادها را دوباره بررسی کنید.'];
        } elseif (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_link')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه اتصال واریز در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_link($fxPostDeposit, $fxPostOrder, $fxActor);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'واریز بانکی به پرداخت متصل شد؛ نتیجه را تأیید یا رد کنید.']
                : ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'delete_deposit' && $fxPostDeposit > 0) {
        if (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_delete_deposit')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه حذف واریز در دسترس نیست.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_delete_deposit($fxPostDeposit, $fxActor);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'واریز بانکی با موفقیت حذف شد.']
                : ['type' => 'err', 'text' => self::fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'bulk_cancel') {
        $fxRequestedIds = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_string_ids($post['ids'] ?? []);
        $fxCancelledCount = 0;
        foreach ($fxRequestedIds as $fxBulkOrderId) {
            if (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_admin_delete_expired_payment')) {
                break;
            }
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_delete_expired_payment($fxBulkOrderId, $fxActor);
            if (!empty($res['ok'])) {
                $fxCancelledCount++;
            }
        }
        $fxRequestedCount = count($fxRequestedIds);
        if ($fxRequestedCount === 0) {
            $fxFlash = ['type' => 'err', 'text' => 'هیچ پرداختی انتخاب نشده بود.'];
        } elseif ($fxCancelledCount === 0) {
            $fxFlash = ['type' => 'err', 'text' => 'هیچ‌کدام از پرداخت‌های انتخاب‌شده برای لغو ایمن واجد شرایط نبودند.'];
        } elseif ($fxCancelledCount === $fxRequestedCount) {
            $fxFlash = ['type' => 'ok', 'text' => 'تعداد ' . number_format($fxCancelledCount) . ' پرداخت با موفقیت لغو شد و سابقه مالی آن حفظ شد.'];
        } else {
            $fxFlash = ['type' => 'ok', 'text' => 'از ' . number_format($fxRequestedCount) . ' پرداخت انتخاب‌شده، ' . number_format($fxCancelledCount) . ' مورد لغو شد و ' . number_format($fxRequestedCount - $fxCancelledCount) . ' مورد به‌دلیل تسویه‌شده بودن، اتصال به واریز یا عدم انقضا قابل لغو نبود.'];
        }
        return [$fxFlash, $fxCancelledCount];
    }
    return [$fxFlash, null];
}

    public static function fxForappReceiptQuery(bool $fxSchemaReady, array $filters): array
{
    $fxQ = $filters['fxQ'];
    $fxStatus = $filters['fxStatus'];
    $fxMatch = $filters['fxMatch'];
    $fxType = $filters['fxType'];
    $fxUserId = $filters['fxUserId'];
    $fxOrderFilter = $filters['fxOrderFilter'];
    $fxDepositFilter = $filters['fxDepositFilter'];
    $fxDf = $filters['fxDf'];
    $fxLife = $filters['fxLife'];
    $fxRFrom = " FROM card_sms_payment csp JOIN Payment_report pr ON pr.id_order = csp.id_order LEFT JOIN user u ON u.id = csp.id_user LEFT JOIN card_sms_deposit d ON d.id = csp.deposit_id";
    $fxRWhere = " WHERE 1 = 1";
    $fxRParams = [];
    if ($fxSchemaReady) {
        if ($fxQ !== '') {
            $like = '%' . $fxQ . '%';
            $fxRWhere .= ' AND (pr.id_order LIKE :rq1 OR pr.id_user LIKE :rq2 OR csp.id_user LIKE :rq3 OR u.username LIKE :rq4 OR u.namecustom LIKE :rq5 OR pr.card_last4 LIKE :rq6)';
            $fxRParams[':rq1'] = $like;
            $fxRParams[':rq2'] = $like;
            $fxRParams[':rq3'] = $like;
            $fxRParams[':rq4'] = $like;
            $fxRParams[':rq5'] = $like;
            $fxRParams[':rq6'] = $like;
        }
        if ($fxStatus !== '') {
            $fxRWhere .= ' AND pr.payment_Status = :rstatus';
            $fxRParams[':rstatus'] = $fxStatus;
        }
        if ($fxMatch !== '') {
            $fxRWhere .= ' AND csp.state = :rmstate';
            $fxRParams[':rmstate'] = $fxMatch;
        }
        if ($fxType === 'wallet') {
            $fxRWhere .= " AND (pr.id_invoice IS NULL OR CAST(SUBSTRING_INDEX(pr.id_invoice, '|', 1) AS BINARY) NOT IN ('getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'))";
        } elseif ($fxType !== '') {
            $fxRWhere .= " AND CAST(SUBSTRING_INDEX(pr.id_invoice, '|', 1) AS BINARY) = :rtype";
            $fxRParams[':rtype'] = $fxType;
        }
        if ($fxUserId !== '') {
            $fxRWhere .= ' AND csp.id_user = :ruid';
            $fxRParams[':ruid'] = $fxUserId;
        }
        if ($fxOrderFilter !== '') {
            $fxRWhere .= ' AND pr.id_order = :roid';
            $fxRParams[':roid'] = $fxOrderFilter;
        }
        if ($fxDepositFilter !== '') {
            $fxRWhere .= ' AND csp.deposit_id = :rdid';
            $fxRParams[':rdid'] = (int) $fxDepositFilter;
        }
        if ($fxDf['active']) {
            $fxRWhere .= " AND ((pr.`time` REGEXP '^[0-9]+$' AND CAST(pr.`time` AS UNSIGNED) BETWEEN :rd1 AND :rd2) OR (pr.`time` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(pr.`time`, '/', '-'), '%Y-%m-%d %H:%i:%s') BETWEEN FROM_UNIXTIME(:rd3) AND FROM_UNIXTIME(:rd4)))";
            $fxRParams[':rd1'] = $fxDf['from'];
            $fxRParams[':rd2'] = $fxDf['to'];
            $fxRParams[':rd3'] = $fxDf['from'];
            $fxRParams[':rd4'] = $fxDf['to'];
        }
        if ($fxLife !== '') {
            $fxExpiredExpr = "(pr.payment_Status = 'expire' OR (csp.state = 'closed' AND csp.resolution = 'expired') OR (csp.state IN ('open', 'review', 'matched') AND csp.expires_at > 0 AND csp.expires_at < :rlnow))";
            $fxRWhere .= $fxLife === 'expired' ? (' AND ' . $fxExpiredExpr) : (' AND NOT ' . $fxExpiredExpr);
            $fxRParams[':rlnow'] = time();
        }
    }
    return [$fxRFrom, $fxRWhere, $fxRParams];
}

    public static function fxForappDepositQuery(bool $fxSchemaReady, array $filters): array
{
    $fxQ = $filters['fxQ'];
    $fxStatus = $filters['fxStatus'];
    $fxUserId = $filters['fxUserId'];
    $fxOrderFilter = $filters['fxOrderFilter'];
    $fxDepositFilter = $filters['fxDepositFilter'];
    $fxDf = $filters['fxDf'];
    $fxDFrom = " FROM card_sms_deposit d LEFT JOIN card_sms_payment csp ON csp.deposit_id = d.id";
    $fxDWhere = " WHERE d.is_test = 0 AND d.is_deposit = 1";
    $fxDParams = [];
    if ($fxSchemaReady) {
        if ($fxQ !== '') {
            $like = '%' . $fxQ . '%';
            $fxDWhere .= ' AND (d.event_id LIKE :dq1 OR d.bank LIKE :dq2 OR d.sender LIKE :dq3 OR d.code LIKE :dq4 OR COALESCE(d.id_order, csp.id_order) LIKE :dq5 OR CAST(d.id AS CHAR) LIKE :dq6)';
            $fxDParams[':dq1'] = $like;
            $fxDParams[':dq2'] = $like;
            $fxDParams[':dq3'] = $like;
            $fxDParams[':dq4'] = $like;
            $fxDParams[':dq5'] = $like;
            $fxDParams[':dq6'] = $like;
        }
        if ($fxStatus !== '') {
            $fxDWhere .= ' AND d.state = :dstatus';
            $fxDParams[':dstatus'] = $fxStatus;
        }
        if ($fxUserId !== '') {
            $fxDWhere .= ' AND csp.id_user = :duid';
            $fxDParams[':duid'] = $fxUserId;
        }
        if ($fxOrderFilter !== '') {
            $fxDWhere .= ' AND COALESCE(d.id_order, csp.id_order) = :doid';
            $fxDParams[':doid'] = $fxOrderFilter;
        }
        if ($fxDepositFilter !== '') {
            $fxDWhere .= ' AND d.id = :ddid';
            $fxDParams[':ddid'] = (int) $fxDepositFilter;
        }
        if ($fxDf['active']) {
            $fxDWhere .= ' AND d.stored_at BETWEEN :ds1 AND :ds2';
            $fxDParams[':ds1'] = $fxDf['from'];
            $fxDParams[':ds2'] = $fxDf['to'];
        }
    }
    return [$fxDFrom, $fxDWhere, $fxDParams];
}

    public static function fxForappReceiptPage(PDO $pdo, string $fxRFrom, string $fxRWhere, array $fxRParams): array
{
    $fxPg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*)" . $fxRFrom . $fxRWhere, $fxRParams, 20);
    $fxRSelect = "SELECT pr.*, csp.id AS csp_id, csp.principal_toman, csp.fee_toman, csp.payable_toman, csp.payable_rial, csp.created_at AS csp_created_at, csp.receipt_after, csp.expires_at, csp.state AS csp_state, csp.deposit_id, csp.received_rial, csp.resolution, csp.resolved_by, csp.resolved_at, csp.resolution_note, csp.updated_at AS csp_updated_at, u.username, u.namecustom, d.amount_rial AS deposit_amount_rial, d.bank AS deposit_bank, d.sender AS deposit_sender, d.stored_at AS deposit_stored_at, d.state AS deposit_state";
    $fxListStmt = $pdo->prepare($fxRSelect . $fxRFrom . $fxRWhere . " ORDER BY CASE WHEN pr.payment_Status = 'waiting' THEN 0 ELSE 1 END, pr.id DESC LIMIT :perPage OFFSET :offset");
    foreach ($fxRParams as $k => $v) $fxListStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $fxListStmt->bindValue(':perPage', $fxPg['perPage'], PDO::PARAM_INT);
    $fxListStmt->bindValue(':offset', $fxPg['offset'], PDO::PARAM_INT);
    $fxListStmt->execute();
    $fxRows = $fxListStmt->fetchAll(PDO::FETCH_ASSOC);
    return [$fxPg, $fxRows];
}

    public static function fxForappDepositPage(PDO $pdo, string $fxDFrom, string $fxDWhere, array $fxDParams): array
{
    $fxDpg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*)" . $fxDFrom . $fxDWhere, $fxDParams, 20);
    $fxDSelect = "SELECT d.*, csp.id_order AS csp_order, csp.id_user AS csp_user, csp.state AS csp_state";
    $fxDStmt = $pdo->prepare($fxDSelect . $fxDFrom . $fxDWhere . " ORDER BY d.id DESC LIMIT :perPage OFFSET :offset");
    foreach ($fxDParams as $k => $v) $fxDStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $fxDStmt->bindValue(':perPage', $fxDpg['perPage'], PDO::PARAM_INT);
    $fxDStmt->bindValue(':offset', $fxDpg['offset'], PDO::PARAM_INT);
    $fxDStmt->execute();
    $fxDRows = $fxDStmt->fetchAll(PDO::FETCH_ASSOC);
    return [$fxDpg, $fxDRows];
}
}
