<?php
date_default_timezone_set('Asia/Tehran');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/extra_filter.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/bulk_delete.php';

if (!function_exists('fxForappJdate')) {
    function fxForappJdate($raw): string
    {
        $s = trim((string) $raw);
        if ($s === '') return '';
        $ts = ctype_digit($s) ? (int) $s : strtotime($s);
        if ($ts === false || $ts <= 0) return $s;
        return jdate('Y/m/d H:i:s', $ts, '', 'Asia/Tehran', 'fa');
    }
}

if (!function_exists('fxForappAmount')) {
    function fxForappAmount($raw): ?int
    {
        $s = strtr(trim((string) $raw), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ',' => '', '٬' => '', '،' => '', ' ' => '',
        ]);
        if ($s === '' || !preg_match('/^[0-9]{1,15}$/', $s)) return null;
        return (int) $s;
    }
}

if (!function_exists('fxForappH')) {
    function fxForappH($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fxForappErrorText')) {
    function fxForappErrorText(string $reason): string
    {
        if (function_exists('rx_sbv_error_text')) {
            return rx_sbv_error_text($reason);
        }
        return 'عملیات انجام نشد.';
    }
}

if (!function_exists('fxForappField')) {
    function fxForappField(string $label, string $valueHtml): string
    {
        return '<div class="detail-sheet__field"><span class="detail-sheet__field-label">' . fxForappH($label) . '</span><span class="detail-sheet__field-value">' . $valueHtml . '</span></div>';
    }
}

if (!function_exists('fxForappBadge')) {
    function fxForappBadge(array $meta): string
    {
        $class = isset($meta['class']) ? (string) $meta['class'] : 'badge-gray';
        $label = isset($meta['label']) ? (string) $meta['label'] : '';
        return '<span class="badge ' . fxForappH($class) . '">' . fxForappH($label) . '</span>';
    }
}

if (!function_exists('fxForappDiffLabel')) {
    function fxForappDiffLabel(?int $diffRial): string
    {
        if ($diffRial === null) return '—';
        if ($diffRial === 0) return 'بدون اختلاف';
        return number_format(intdiv(abs($diffRial), 10)) . ' تومان ' . ($diffRial < 0 ? 'کمتر' : 'بیشتر');
    }
}

if (!function_exists('fxForappPaymentBadgeMeta')) {
    function fxForappPaymentBadgeMeta(string $status, string $state, bool $expired, string $resolution): array
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
}

if (!function_exists('fxForappMatchBadgeMeta')) {
    function fxForappMatchBadgeMeta(string $status, string $state, bool $expired, string $resolution): ?array
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
}

if (!function_exists('fxForappDepositTime')) {
    function fxForappDepositTime(array $dep): int
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
}

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

fx_csrf_guard();

if (isset($_GET['media']) && $_GET['media'] !== '') {
    $mediaOrder = (string) $_GET['media'];
    if (!function_exists('rx_sbv_is_verify_order') || !rx_sbv_is_verify_order($mediaOrder)) {
        http_response_code(404);
        exit;
    }
    $mediaReport = rx_sbv_payment_report($mediaOrder);
    if (!is_array($mediaReport)) {
        http_response_code(404);
        exit;
    }
    $mediaFileId = (string) ($mediaReport['card_photo_file_id'] ?? '');
    if ($mediaFileId === '') {
        http_response_code(404);
        exit;
    }
    $f = telegram('getFile', ['file_id' => $mediaFileId]);
    if (empty($f['ok'])) {
        http_response_code(404);
        exit;
    }
    $mediaFilePath = $f['result']['file_path'] ?? '';
    if ($mediaFilePath === '') {
        http_response_code(404);
        exit;
    }
    $bin = @file_get_contents("https://api.telegram.org/file/bot{$APIKEY}/{$mediaFilePath}");
    if ($bin === false || strlen($bin) === 0) {
        http_response_code(502);
        exit;
    }
    $mediaExt = strtolower(pathinfo($mediaFilePath, PATHINFO_EXTENSION));
    $mediaContentType = $mediaExt === 'png' ? 'image/png' : ($mediaExt === 'gif' ? 'image/gif' : 'image/jpeg');
    header('Content-Type: ' . $mediaContentType);
    header('Cache-Control: private, max-age=3600');
    echo $bin;
    exit;
}

$fxFlash = null;
function fxForappRunAction(array $post, string $fxActor): array
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
        if (!function_exists('rx_sbv_admin_approve')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = rx_sbv_admin_approve($fxPostOrder, $fxActor, $fxPostReason);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'پرداخت فوراپ با موفقیت تأیید و تسویه شد.']
                : ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'reject' && $fxPostOrder !== '') {
        if ($fxPostReason === '') {
            $fxFlash = ['type' => 'err', 'text' => 'برای رد کردن پرداخت، ثبت دلیل الزامی است.'];
        } elseif (!function_exists('rx_sbv_admin_reject')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = rx_sbv_admin_reject($fxPostOrder, $fxActor, $fxPostReason, false, $fxPostDeposit > 0 ? $fxPostDeposit : null);
            if (!empty($res['ok'])) {
                $fxFlash = ['type' => 'ok', 'text' => !empty($res['already_rejected']) ? 'این پرداخت قبلاً رد شده بود و گزارش تصمیم بازگردانی شد.' : 'پرداخت فوراپ رد شد.'];
            } else {
                $fxFlash = ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
            }
        }
    } elseif ($fxAction === 'set_received' && $fxPostOrder !== '') {
        $fxReceived = fxForappAmount($post['received'] ?? '');
        if ($fxReceived === null) {
            $fxFlash = ['type' => 'err', 'text' => 'مبلغ دریافتی واردشده معتبر نیست.'];
        } elseif ($fxPostReason === '') {
            $fxFlash = ['type' => 'err', 'text' => 'برای اصلاح مبلغ دریافتی، ثبت دلیل الزامی است.'];
        } elseif (!function_exists('rx_sbv_admin_set_received')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = rx_sbv_admin_set_received($fxPostOrder, $fxActor, $fxReceived, $fxPostReason);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'مبلغ دریافتی پرداخت اصلاح شد.']
                : ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'credit' && $fxPostOrder !== '') {
        if ($fxPostReason === '') {
            $fxFlash = ['type' => 'err', 'text' => 'برای واریز مبلغ دریافتی به کیف پول، ثبت دلیل الزامی است.'];
        } elseif (!function_exists('rx_sbv_admin_credit')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = rx_sbv_admin_credit($fxPostOrder, $fxActor, $fxPostReason);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'مبلغ دریافتی به کیف پول کاربر واریز شد.']
                : ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'cancel_expired' && $fxPostOrder !== '') {
        if (!function_exists('rx_sbv_admin_delete_expired_payment')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه پرداخت فوراپ در دسترس نیست.'];
        } else {
            $res = rx_sbv_admin_delete_expired_payment($fxPostOrder, $fxActor, $fxPostDeposit > 0 ? $fxPostDeposit : null);
            if (!empty($res['ok'])) {
                $fxFlash = ['type' => 'ok', 'text' => !empty($res['already_removed']) ? 'این پرداخت منقضی قبلاً از فهرست حذف شده بود.' : 'پرداخت منقضی لغو و از فهرست پیشنهادها حذف شد.'];
            } else {
                $fxFlash = ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
            }
        }
    } elseif ($fxAction === 'link_deposit' && $fxPostOrder !== '' && $fxPostDeposit > 0) {
        $cand = function_exists('rx_sbv_order_deposit_candidates') ? rx_sbv_order_deposit_candidates($fxPostOrder, 5, $fxPostCpage, $fxPostDeposit) : ['ok' => false];
        $found = false;
        foreach ((array) ($cand['rows'] ?? []) as $candRow) {
            if ((int) ($candRow['id'] ?? 0) === $fxPostDeposit) {
                $found = true;
                break;
            }
        }
        if (!empty($cand['blocked'])) {
            $fxFlash = ['type' => 'err', 'text' => fxForappErrorText((string) $cand['blocked'])];
        } elseif (empty($cand['ok']) || !$found) {
            $fxFlash = ['type' => 'err', 'text' => 'شرایط تطبیق این واریز تغییر کرده است؛ فهرست پیشنهادها را دوباره بررسی کنید.'];
        } elseif (!function_exists('rx_sbv_link')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه اتصال واریز در دسترس نیست.'];
        } else {
            $res = rx_sbv_link($fxPostDeposit, $fxPostOrder, $fxActor);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'واریز بانکی به پرداخت متصل شد؛ نتیجه را تأیید یا رد کنید.']
                : ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'link_payment' && $fxPostOrder !== '' && $fxPostDeposit > 0) {
        $cand = function_exists('rx_sbv_report_candidates') ? rx_sbv_report_candidates($fxPostDeposit, 5, $fxPostCpage, $fxPostOrder) : ['ok' => false];
        $found = false;
        foreach ((array) ($cand['rows'] ?? []) as $candRow) {
            if ((string) ($candRow['id_order'] ?? '') === $fxPostOrder) {
                $found = true;
                break;
            }
        }
        if (empty($cand['ok']) || !$found) {
            $fxFlash = ['type' => 'err', 'text' => 'شرایط تطبیق این پرداخت تغییر کرده است؛ فهرست پیشنهادها را دوباره بررسی کنید.'];
        } elseif (!function_exists('rx_sbv_link')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه اتصال واریز در دسترس نیست.'];
        } else {
            $res = rx_sbv_link($fxPostDeposit, $fxPostOrder, $fxActor);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'واریز بانکی به پرداخت متصل شد؛ نتیجه را تأیید یا رد کنید.']
                : ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'delete_deposit' && $fxPostDeposit > 0) {
        if (!function_exists('rx_sbv_delete_deposit')) {
            $fxFlash = ['type' => 'err', 'text' => 'سامانه حذف واریز در دسترس نیست.'];
        } else {
            $res = rx_sbv_delete_deposit($fxPostDeposit, $fxActor);
            $fxFlash = !empty($res['ok'])
                ? ['type' => 'ok', 'text' => 'واریز بانکی با موفقیت حذف شد.']
                : ['type' => 'err', 'text' => fxForappErrorText((string) ($res['reason'] ?? 'error'))];
        }
    } elseif ($fxAction === 'bulk_cancel') {
        $fxRequestedIds = fx_bulk_string_ids($post['ids'] ?? []);
        $fxCancelledCount = 0;
        foreach ($fxRequestedIds as $fxBulkOrderId) {
            if (!function_exists('rx_sbv_admin_delete_expired_payment')) {
                break;
            }
            $res = rx_sbv_admin_delete_expired_payment($fxBulkOrderId, $fxActor);
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

function fxForappReceiptQuery(bool $fxSchemaReady, array $filters): array
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

function fxForappDepositQuery(bool $fxSchemaReady, array $filters): array
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

function fxForappReceiptPage(PDO $pdo, string $fxRFrom, string $fxRWhere, array $fxRParams): array
{
    $fxPg = fx_paginate($pdo, "SELECT COUNT(*)" . $fxRFrom . $fxRWhere, $fxRParams, 20);
    $fxRSelect = "SELECT pr.*, csp.id AS csp_id, csp.principal_toman, csp.fee_toman, csp.payable_toman, csp.payable_rial, csp.created_at AS csp_created_at, csp.receipt_after, csp.expires_at, csp.state AS csp_state, csp.deposit_id, csp.received_rial, csp.resolution, csp.resolved_by, csp.resolved_at, csp.resolution_note, csp.updated_at AS csp_updated_at, u.username, u.namecustom, d.amount_rial AS deposit_amount_rial, d.bank AS deposit_bank, d.sender AS deposit_sender, d.stored_at AS deposit_stored_at, d.state AS deposit_state";
    $fxListStmt = $pdo->prepare($fxRSelect . $fxRFrom . $fxRWhere . " ORDER BY CASE WHEN pr.payment_Status = 'waiting' THEN 0 ELSE 1 END, pr.id DESC LIMIT :perPage OFFSET :offset");
    foreach ($fxRParams as $k => $v) $fxListStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $fxListStmt->bindValue(':perPage', $fxPg['perPage'], PDO::PARAM_INT);
    $fxListStmt->bindValue(':offset', $fxPg['offset'], PDO::PARAM_INT);
    $fxListStmt->execute();
    $fxRows = $fxListStmt->fetchAll(PDO::FETCH_ASSOC);
    return [$fxPg, $fxRows];
}

function fxForappDepositPage(PDO $pdo, string $fxDFrom, string $fxDWhere, array $fxDParams): array
{
    $fxDpg = fx_paginate($pdo, "SELECT COUNT(*)" . $fxDFrom . $fxDWhere, $fxDParams, 20);
    $fxDSelect = "SELECT d.*, csp.id_order AS csp_order, csp.id_user AS csp_user, csp.state AS csp_state";
    $fxDStmt = $pdo->prepare($fxDSelect . $fxDFrom . $fxDWhere . " ORDER BY d.id DESC LIMIT :perPage OFFSET :offset");
    foreach ($fxDParams as $k => $v) $fxDStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $fxDStmt->bindValue(':perPage', $fxDpg['perPage'], PDO::PARAM_INT);
    $fxDStmt->bindValue(':offset', $fxDpg['offset'], PDO::PARAM_INT);
    $fxDStmt->execute();
    $fxDRows = $fxDStmt->fetchAll(PDO::FETCH_ASSOC);
    return [$fxDpg, $fxDRows];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_fx_action'])) {
    [$fxFlash, $fxCancelledCount] = fxForappRunAction($_POST, (string) ($_SESSION['user'] ?? ''));
    if ($fxCancelledCount !== null) {
        $_SESSION['_forapp_flash'] = $fxFlash;
        $fxKeepQs = str_replace(["\r", "\n"], '', (string) ($_POST['_keep_qs'] ?? ''));
        $fxBulkFlag = 'bulk=' . ($fxCancelledCount > 0 ? 'ok' : 'err');
        header('Location: forapp.php' . ($fxKeepQs !== '' ? '?' . $fxKeepQs . '&' . $fxBulkFlag : '?' . $fxBulkFlag));
        exit;
    }

    $_SESSION['_forapp_flash'] = $fxFlash;
    $fxKeepQs = str_replace(["\r", "\n"], '', (string) ($_POST['_keep_qs'] ?? ''));
    header('Location: forapp.php' . ($fxKeepQs !== '' ? '?' . $fxKeepQs : ''));
    exit;
}

if (isset($_SESSION['_forapp_flash'])) {
    $fxFlash = $_SESSION['_forapp_flash'];
    unset($_SESSION['_forapp_flash']);
}

$fxTab = (string) ($_GET['tab'] ?? 'receipts');
if (!in_array($fxTab, ['receipts', 'deposits'], true)) $fxTab = 'receipts';

$fxQ = fx_search_current();
$fxPaymentStatusOptions = [
    'Unpaid' => 'در انتظار پرداخت',
    'pending' => 'در حال ارسال رسید',
    'waiting' => 'در انتظار بررسی',
    'processing' => 'در حال پردازش',
    'reconciling' => 'در حال تطبیق',
    'manual_review' => 'نیازمند بررسی دستی',
    'paid' => 'تایید شده',
    'reject' => 'رد شده',
    'expire' => 'منقضی شده',
    'cancelled' => 'لغو شده',
];
$fxDepositStatusOptions = [
    'pending' => 'در صف تطبیق',
    'matched' => 'تطبیق یافته',
    'applied' => 'اعمال شده',
    'unmatched' => 'تطبیق‌نیافته',
    'ambiguous' => 'مبهم (چند پرداخت هم‌مبلغ)',
    'late' => 'دیرهنگام',
    'linked' => 'متصل‌شده توسط ادمین',
    'reviewed_rejected' => 'بررسی‌شده — رد شده',
    'dismissed' => 'بایگانی‌شده',
    'ignored' => 'غیرقابل استفاده',
];
$fxStatusOptions = $fxTab === 'deposits' ? $fxDepositStatusOptions : $fxPaymentStatusOptions;
$fxStatus = fx_status_filter_current();
$fxStatusActive = $fxStatus !== '' && isset($fxStatusOptions[$fxStatus]);
if (!$fxStatusActive) $fxStatus = '';

$fxMatchOptions = [
    'open' => 'باز / در انتظار واریز',
    'matched' => 'واریز تطبیق یافته',
    'review' => 'نیازمند بررسی',
    'approved' => 'تأیید و تسویه شده',
    'credited' => 'شارژ کیف پول شده',
    'closed' => 'بسته شده',
    'void' => 'باطل',
];
$fxMatch = fx_extra_text_param('mstate', 20);
if (!isset($fxMatchOptions[$fxMatch])) $fxMatch = '';

$fxTypeOptions = [
    'getconfigafterpay' => 'خرید سرویس',
    'getextenduser' => 'تمدید سرویس',
    'getextravolumeuser' => 'افزایش حجم',
    'getextratimeuser' => 'افزایش زمان',
    'wallet' => 'شارژ کیف پول',
];
$fxType = fx_extra_text_param('otype', 40);
if (!isset($fxTypeOptions[$fxType])) $fxType = '';

$fxUserId = fx_extra_text_param('uid', 64);
$fxOrderFilter = fx_extra_text_param('oid', 64);
$fxDepositFilter = fx_extra_text_param('did', 20);
if ($fxDepositFilter !== '' && !ctype_digit($fxDepositFilter)) $fxDepositFilter = '';
$fxLife = fx_extra_text_param('life', 10);
if (!in_array($fxLife, ['active', 'expired'], true)) $fxLife = '';
if ($fxTab === 'deposits') {
    $fxMatch = '';
    $fxType = '';
    $fxLife = '';
}
$fxDf = fx_date_filter_resolve();

$fxKeep = array_filter([
    'tab' => $fxTab,
    'q' => $fxQ !== '' ? $fxQ : null,
    'status' => $fxStatus !== '' ? $fxStatus : null,
    'mstate' => ($fxTab === 'receipts' && $fxMatch !== '') ? $fxMatch : null,
    'otype' => ($fxTab === 'receipts' && $fxType !== '') ? $fxType : null,
    'uid' => $fxUserId !== '' ? $fxUserId : null,
    'oid' => $fxOrderFilter !== '' ? $fxOrderFilter : null,
    'did' => $fxDepositFilter !== '' ? $fxDepositFilter : null,
    'life' => ($fxTab === 'receipts' && $fxLife !== '') ? $fxLife : null,
    'ddays' => isset($_GET['ddays']) && (string) $_GET['ddays'] !== '' ? (string) $_GET['ddays'] : null,
], function ($v) { return $v !== null && $v !== ''; });

$fxKeepExcept = function (array $drop) use ($fxKeep) {
    $out = $fxKeep;
    foreach ($drop as $k) unset($out[$k]);
    return $out;
};

$fxFilterActive = $fxQ !== '' || $fxStatus !== '' || $fxMatch !== '' || $fxType !== '' || $fxUserId !== '' || $fxOrderFilter !== '' || $fxDepositFilter !== '' || $fxLife !== '' || $fxDf['active'];

$fxCrossKeep = array_filter([
    'q' => $fxQ !== '' ? $fxQ : null,
    'uid' => $fxUserId !== '' ? $fxUserId : null,
    'oid' => $fxOrderFilter !== '' ? $fxOrderFilter : null,
    'did' => $fxDepositFilter !== '' ? $fxDepositFilter : null,
    'ddays' => isset($_GET['ddays']) && (string) $_GET['ddays'] !== '' ? (string) $_GET['ddays'] : null,
], function ($v) { return $v !== null && $v !== ''; });

$fxSchemaReady = function_exists('rx_sbv_has_schema') ? rx_sbv_has_schema() : false;
$fxTodayStart = strtotime(date('Y-m-d 00:00:00'));
$fxTotalRecords = 0;
$fxPendingCount = 0;
$fxApprovedToday = 0;
$fxRejectedToday = 0;
$fxUnmatchedDeposits = 0;

if ($fxSchemaReady) {
    try {
        $fxTotalRecords = (int) $pdo->query("SELECT COUNT(*) FROM card_sms_payment csp JOIN Payment_report pr ON pr.id_order = csp.id_order")->fetchColumn();
    } catch (\Throwable $e) {}
    try {
        $fxPendingCount = (int) $pdo->query("SELECT COUNT(*) FROM Payment_report WHERE " . (function_exists('rxForappScopeSql') ? rxForappScopeSql() : '1 = 0') . " AND payment_Status = 'waiting'")->fetchColumn();
    } catch (\Throwable $e) {}
    $fxTodaySql = "SELECT COUNT(*) FROM card_sms_payment csp JOIN Payment_report pr ON pr.id_order = csp.id_order WHERE pr.payment_Status = :st AND ((pr.`time` REGEXP '^[0-9]+$' AND CAST(pr.`time` AS UNSIGNED) >= :t1) OR (pr.`time` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(pr.`time`, '/', '-'), '%Y-%m-%d %H:%i:%s') >= FROM_UNIXTIME(:t2)))";
    try {
        $fxTodayStmt = $pdo->prepare($fxTodaySql);
        $fxTodayStmt->execute([':st' => 'paid', ':t1' => $fxTodayStart, ':t2' => $fxTodayStart]);
        $fxApprovedToday = (int) $fxTodayStmt->fetchColumn();
    } catch (\Throwable $e) {}
    try {
        $fxTodayStmt = $pdo->prepare($fxTodaySql);
        $fxTodayStmt->execute([':st' => 'reject', ':t1' => $fxTodayStart, ':t2' => $fxTodayStart]);
        $fxRejectedToday = (int) $fxTodayStmt->fetchColumn();
    } catch (\Throwable $e) {}
    try {
        $fxUnmatchedDeposits = (int) $pdo->query("SELECT COUNT(*) FROM card_sms_deposit WHERE is_test = 0 AND is_deposit = 1 AND state IN ('unmatched', 'ambiguous', 'late')")->fetchColumn();
    } catch (\Throwable $e) {}
}

$fxReportFilters = compact('fxQ', 'fxStatus', 'fxMatch', 'fxType', 'fxUserId', 'fxOrderFilter', 'fxDepositFilter', 'fxDf', 'fxLife');
[$fxRFrom, $fxRWhere, $fxRParams] = fxForappReceiptQuery($fxSchemaReady, $fxReportFilters);

if ($fxSchemaReady && $fxTab === 'receipts') {
    [$fxPg, $fxRows] = fxForappReceiptPage($pdo, $fxRFrom, $fxRWhere, $fxRParams);
} else {
    $fxPg = ['page' => 1, 'perPage' => 20, 'offset' => 0, 'total' => 0, 'pages' => 1];
    $fxRows = [];
}

[$fxDFrom, $fxDWhere, $fxDParams] = fxForappDepositQuery($fxSchemaReady, $fxReportFilters);

if ($fxSchemaReady && $fxTab === 'deposits') {
    [$fxDpg, $fxDRows] = fxForappDepositPage($pdo, $fxDFrom, $fxDWhere, $fxDParams);
} else {
    $fxDpg = ['page' => 1, 'perPage' => 20, 'offset' => 0, 'total' => 0, 'pages' => 1];
    $fxDRows = [];
}

$fxViewOrder = $fxTab === 'receipts' ? fx_extra_text_param('order', 64) : '';
$fxOrderCpage = max(0, (int) ($_GET['cpage'] ?? 0));
$fxOrderView = null;
$fxOrderCand = null;
$fxOrderUser = null;
if ($fxViewOrder !== '' && $fxSchemaReady) {
    $fxOrderView = rx_sbv_order_summary($fxViewOrder);
    if ($fxOrderView !== null) {
        $fxOrderCand = rx_sbv_order_deposit_candidates($fxViewOrder, 5, $fxOrderCpage);
        $fxOrderUserStmt = $pdo->prepare("SELECT id, username, namecustom FROM user WHERE id = ? LIMIT 1");
        $fxOrderUserStmt->execute([(string) $fxOrderView['csp']['id_user']]);
        $fxOrderUser = $fxOrderUserStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

$fxViewDeposit = $fxTab === 'deposits' ? fx_extra_text_param('deposit', 20) : '';
$fxDepositCpage = max(0, (int) ($_GET['cpage'] ?? 0));
$fxDepositView = null;
$fxDepositCand = null;
if ($fxViewDeposit !== '' && ctype_digit($fxViewDeposit) && $fxSchemaReady) {
    $fxDepositView = rx_sbv_deposit((int) $fxViewDeposit);
    if ($fxDepositView !== null) {
        $fxDepositCand = rx_sbv_report_candidates((int) $fxViewDeposit, 5, $fxDepositCpage);
    }
}

$fxPayMeta = [
    'Unpaid' => ['label' => 'در انتظار پرداخت', 'class' => 'badge-gray'],
    'pending' => ['label' => 'در حال ارسال رسید', 'class' => 'badge-warning'],
    'waiting' => ['label' => 'در انتظار بررسی', 'class' => 'badge-warning'],
    'processing' => ['label' => 'در حال پردازش', 'class' => 'badge-info'],
    'reconciling' => ['label' => 'در حال تطبیق', 'class' => 'badge-info'],
    'manual_review' => ['label' => 'نیازمند بررسی دستی', 'class' => 'badge-warning'],
    'paid' => ['label' => 'تایید شده', 'class' => 'badge-success'],
    'reject' => ['label' => 'رد شده', 'class' => 'badge-danger'],
    'expire' => ['label' => 'منقضی شده', 'class' => 'badge-gray'],
    'cancelled' => ['label' => 'لغو شده', 'class' => 'badge-gray'],
];
$fxMatchMeta = [
    'open' => ['label' => 'باز / در انتظار واریز', 'class' => 'badge-info'],
    'matched' => ['label' => 'واریز تطبیق یافته', 'class' => 'badge-warning'],
    'review' => ['label' => 'نیازمند بررسی', 'class' => 'badge-warning'],
    'approved' => ['label' => 'تأیید و تسویه شده', 'class' => 'badge-success'],
    'credited' => ['label' => 'شارژ کیف پول شده', 'class' => 'badge-success'],
    'closed' => ['label' => 'بسته شده', 'class' => 'badge-gray'],
    'void' => ['label' => 'باطل', 'class' => 'badge-gray'],
];
$fxDepositMeta = [
    'pending' => ['label' => 'در صف تطبیق', 'class' => 'badge-info'],
    'matched' => ['label' => 'تطبیق یافته', 'class' => 'badge-info'],
    'applied' => ['label' => 'اعمال شده', 'class' => 'badge-success'],
    'unmatched' => ['label' => 'تطبیق‌نیافته', 'class' => 'badge-warning'],
    'ambiguous' => ['label' => 'مبهم', 'class' => 'badge-warning'],
    'late' => ['label' => 'دیرهنگام', 'class' => 'badge-warning'],
    'linked' => ['label' => 'متصل‌شده توسط ادمین', 'class' => 'badge-info'],
    'reviewed_rejected' => ['label' => 'بررسی‌شده — رد شده', 'class' => 'badge-danger'],
    'dismissed' => ['label' => 'بایگانی‌شده', 'class' => 'badge-gray'],
    'ignored' => ['label' => 'غیرقابل استفاده', 'class' => 'badge-gray'],
];

$fxSearchPlaceholder = $fxTab === 'deposits'
    ? 'جستجو در شناسه واریز، بانک، فرستنده، کد پیامک یا کد سفارش…'
    : 'جستجو در کد سفارش، آیدی کاربر، نام کاربری یا ۴ رقم آخر کارت…';

$fxExtraCriteria = [];
if ($fxTab === 'receipts') {
    if ($fxType !== '') $fxExtraCriteria['نوع پرداخت'] = $fxTypeOptions[$fxType];
    if ($fxMatch !== '') $fxExtraCriteria['وضعیت تطبیق'] = $fxMatchOptions[$fxMatch];
    if ($fxLife !== '') $fxExtraCriteria['وضعیت زمانی'] = $fxLife === 'expired' ? 'منقضی شده' : 'فعال';
}
if ($fxUserId !== '') $fxExtraCriteria['آیدی کاربر'] = $fxUserId;
if ($fxOrderFilter !== '') $fxExtraCriteria['کد سفارش'] = $fxOrderFilter;
if ($fxDepositFilter !== '') $fxExtraCriteria['شناسه واریز'] = $fxDepositFilter;

$fxExtraFields = $fxTab === 'deposits' ? [
    ['name' => 'uid', 'label' => 'آیدی کاربر', 'value' => $fxUserId, 'placeholder' => 'مثلاً 123456789'],
    ['name' => 'oid', 'label' => 'کد سفارش', 'value' => $fxOrderFilter, 'placeholder' => 'کد پیگیری پرداخت'],
    ['name' => 'did', 'label' => 'شناسه واریز', 'value' => $fxDepositFilter, 'placeholder' => 'مثلاً 42'],
] : [
    ['name' => 'otype', 'label' => 'نوع پرداخت', 'type' => 'select', 'options' => $fxTypeOptions, 'value' => $fxType, 'all_label' => 'همه انواع'],
    ['name' => 'mstate', 'label' => 'وضعیت تطبیق', 'type' => 'select', 'options' => $fxMatchOptions, 'value' => $fxMatch, 'all_label' => 'همه وضعیت‌ها'],
    ['name' => 'uid', 'label' => 'آیدی کاربر', 'value' => $fxUserId, 'placeholder' => 'مثلاً 123456789'],
    ['name' => 'oid', 'label' => 'کد سفارش', 'value' => $fxOrderFilter, 'placeholder' => 'کد پیگیری پرداخت'],
    ['name' => 'did', 'label' => 'شناسه واریز', 'value' => $fxDepositFilter, 'placeholder' => 'مثلاً 42'],
    ['name' => 'life', 'label' => 'وضعیت زمانی', 'type' => 'select', 'options' => ['active' => 'فعال', 'expired' => 'منقضی شده'], 'value' => $fxLife, 'all_label' => 'همه'],
];

$fxExtraDrop = $fxTab === 'deposits' ? ['uid', 'oid', 'did'] : ['otype', 'mstate', 'uid', 'oid', 'did', 'life'];
$fxTabReceiptsQs = http_build_query(array_merge($fxCrossKeep, ['tab' => 'receipts']));
$fxTabDepositsQs = http_build_query(array_merge($fxCrossKeep, ['tab' => 'deposits']));
$fxPgKeep = $fxKeep;
$fxDpgKeep = $fxKeep;
$fxOrderKeepArr = array_merge($fxKeep, ['p' => (int) $fxPg['page']], $fxViewOrder !== '' ? ['order' => $fxViewOrder] : [], ['cpage' => $fxOrderCpage]);
$fxDepositKeepArr = array_merge($fxKeep, ['p' => (int) $fxDpg['page']], $fxViewDeposit !== '' ? ['deposit' => $fxViewDeposit] : [], ['cpage' => $fxDepositCpage]);
$fxOrderKeepQs = http_build_query($fxOrderKeepArr);
$fxDepositKeepQs = http_build_query($fxDepositKeepArr);
$fxOrderBack = 'forapp.php?' . fx_qs(array_merge($fxKeep, ['p' => (int) $fxPg['page']]), ['order' => null, 'cpage' => null]);
$fxOrderSelf = 'forapp.php?' . fx_qs($fxOrderKeepArr, []);
$fxDepositBack = 'forapp.php?' . fx_qs(array_merge($fxKeep, ['p' => (int) $fxDpg['page']]), ['deposit' => null, 'cpage' => null]);
$fxDepositSelf = 'forapp.php?' . fx_qs($fxDepositKeepArr, []);
$fxDepositDeleteKeepQs = http_build_query(array_merge($fxKeep, ['p' => (int) $fxDpg['page']]));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت فوراپ | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=filterdrawer111">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat40">
    <script src="js/theme.js?v=dark62" defer></script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list receipt-page forapp-page fx-page-receipts">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('credit-card', 'svg-icon svg-lg'); ?>
                        مدیریت پرداخت‌های فوراپ
                    </div>
                    <div class="page-head__sub">رسیدها، سفارش‌ها، واریزهای بانکی و تطبیق پرداخت‌های فوراپ</div>
                </div>
            </div>

            <?php if ($fxFlash): ?>
                <div class="alert <?php echo $fxFlash['type'] === 'ok' ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo fxForappH($fxFlash['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (!$fxSchemaReady): ?>
                <div class="alert alert-danger">جداول پرداخت فوراپ در دسترس نیستند. پس از آماده‌سازی سامانه دوباره تلاش کنید.</div>
            <?php endif; ?>

            <div class="receipt-summary-grid">
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('hourglass', 'svg-icon svg-sm'); ?> در انتظار بررسی</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxPendingCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('check', 'svg-icon svg-sm'); ?> تایید شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxApprovedToday); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('xmark', 'svg-icon svg-sm'); ?> رد شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxRejectedToday); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('atm-machine', 'svg-icon svg-sm'); ?> واریز بدون تطبیق</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxUnmatchedDeposits); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo icon('credit-card', 'svg-icon svg-sm'); ?> کل پرداخت‌های فوراپ</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxTotalRecords); ?></div>
                </div>
            </div>

            <div class="card fx-status-filter">
                <div class="fx-status-filter__title">بخش مدیریت فوراپ</div>
                <div class="fx-status-filter__pills">
                    <a class="btn fx-status-filter__pill <?php echo $fxTab === 'receipts' ? 'btn-primary' : 'btn-outline'; ?>" href="forapp.php?<?php echo fxForappH($fxTabReceiptsQs); ?>">
                        <?php echo icon('receipt', 'svg-icon svg-sm'); ?> رسیدها و سفارش‌ها
                    </a>
                    <a class="btn fx-status-filter__pill <?php echo $fxTab === 'deposits' ? 'btn-primary' : 'btn-outline'; ?>" href="forapp.php?<?php echo fxForappH($fxTabDepositsQs); ?>">
                        <?php echo icon('link', 'svg-icon svg-sm'); ?> واریزی‌ها و تطبیق
                    </a>
                </div>
            </div>

            <?php if ($fxTab === 'receipts' && $fxViewOrder !== ''): ?>
                <div class="card invoice-results">
                    <div class="invoice-results__head">
                        <div>
                            <h2><?php echo icon('credit-card', 'svg-icon svg-sm'); ?> جزئیات پرداخت فوراپ</h2>
                            <p>بررسی رسید و واریزهای احتمالی؛ تصمیم مالی فقط با اقدام صریح ادمین ثبت می‌شود.</p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxOrderBack); ?>"><?php echo icon('chevron-right', 'svg-icon svg-sm'); ?> بازگشت</a>
                            <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxOrderSelf); ?>"><?php echo icon('refresh', 'svg-icon svg-sm'); ?> بررسی مجدد</a>
                        </div>
                    </div>
                    <?php if ($fxOrderView === null): ?>
                        <div class="alert alert-danger">پرداخت فوراپی با این کد یافت نشد.</div>
                    <?php else:
                        $fxOcsp = is_array($fxOrderView['csp'] ?? null) ? $fxOrderView['csp'] : [];
                        $fxOrep = is_array($fxOrderView['report'] ?? null) ? $fxOrderView['report'] : [];
                        $fxOdep = is_array($fxOrderView['deposit'] ?? null) ? $fxOrderView['deposit'] : null;
                        $fxOpayStatus = (string) ($fxOrep['payment_Status'] ?? '');
                        $fxOstate = (string) ($fxOcsp['state'] ?? '');
                        $fxOexpired = rx_sbv_payment_is_expired($fxOcsp, $fxOrep);
                        $fxOresolution = (string) ($fxOcsp['resolution'] ?? '');
                        $fxOpayMeta = fxForappPaymentBadgeMeta($fxOpayStatus, $fxOstate, $fxOexpired, $fxOresolution);
                        $fxOmatchMeta = fxForappMatchBadgeMeta($fxOpayStatus, $fxOstate, $fxOexpired, $fxOresolution);
                        $fxOtypeParts = explode('|', (string) ($fxOrep['id_invoice'] ?? ''));
                        $fxOtypeHead = (string) ($fxOtypeParts[0] ?? '');
                        $fxOisWallet = !in_array($fxOtypeHead, ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'], true);
                        $fxOname = is_array($fxOrderUser) ? trim((string) ($fxOrderUser['namecustom'] ?? '')) : '';
                        $fxOusername = is_array($fxOrderUser) ? trim((string) ($fxOrderUser['username'] ?? '')) : '';
                        $fxOuserLabel = ($fxOname !== '' && $fxOname !== 'none') ? $fxOname : ($fxOusername !== '' ? '@' . $fxOusername : '');
                        $fxOcanDecide = in_array($fxOpayStatus, ['Unpaid', 'waiting', 'expire'], true)
                            && !in_array($fxOstate, ['approved', 'credited'], true)
                            && (int) ($fxOrep['direct_payment_done'] ?? 0) !== 1;
                        $fxOcanReceived = in_array($fxOpayStatus, ['Unpaid', 'waiting', 'expire'], true)
                            && !in_array($fxOstate, ['approved', 'credited', 'matched', 'void'], true);
                        $fxOcanCredit = $fxOcanDecide && $fxOisWallet && $fxOrderView['received_rial'] !== null;
                        $fxOcanCancel = $fxOexpired && (int) ($fxOcsp['deposit_id'] ?? 0) === 0 && in_array($fxOpayStatus, ['Unpaid', 'waiting', 'expire'], true);
                        $fxOphoto = (string) ($fxOrep['card_photo_file_id'] ?? '') !== '';
                        $fxOdepositLink = $fxOdep !== null ? '<a class="text-link" href="forapp.php?tab=deposits&deposit=' . (int) $fxOdep['id'] . '" style="direction:ltr;">#' . (int) $fxOdep['id'] . '</a>' : '—';
                        ?>
                        <div class="forapp-detail-grid">
                            <div>
                                <?php
                                echo fxForappField('کد سفارش', '<span class="track-id invoice-reference" onclick="fxForappCopy(\'' . fxForappH(addslashes((string) ($fxOrep['id_order'] ?? ''))) . '\')" title="کپی کردن" style="direction:ltr;">' . fxForappH((string) ($fxOrep['id_order'] ?? $fxViewOrder)) . '</span>');
                                echo fxForappField('آیدی کاربر', '<a class="text-link" href="user.php?id=' . urlencode((string) ($fxOcsp['id_user'] ?? '')) . '">' . fxForappH((string) ($fxOcsp['id_user'] ?? '')) . '</a>' . ($fxOuserLabel !== '' ? ' — ' . fxForappH($fxOuserLabel) : ''));
                                echo fxForappField('نوع پرداخت', fxForappH(function_exists('rx_sbv_invoice_label') ? rx_sbv_invoice_label((string) ($fxOrep['id_invoice'] ?? '')) : (string) ($fxOrep['id_invoice'] ?? '—')));
                                echo fxForappField('وضعیت پرداخت', fxForappBadge($fxOpayMeta));
                                echo fxForappField('وضعیت تطبیق', $fxOmatchMeta !== null ? fxForappBadge($fxOmatchMeta) : '—');
                                echo fxForappField('اصل مبلغ', number_format((int) ($fxOcsp['principal_toman'] ?? 0)) . ' تومان');
                                echo fxForappField('کارمزد شناسایی', number_format((int) ($fxOcsp['fee_toman'] ?? 0)) . ' تومان');
                                echo fxForappField('مبلغ قابل پرداخت', number_format((int) ($fxOcsp['payable_toman'] ?? 0)) . ' تومان');
                                echo fxForappField('مبلغ دریافتی', $fxOrderView['received_rial'] !== null ? number_format(intdiv((int) $fxOrderView['received_rial'], 10)) . ' تومان' : 'ثبت نشده');
                                $fxOdiff = $fxOrderView['diff_rial'];
                                $fxOdiffLabel = $fxOdiff === null ? '—' : (number_format(intdiv(abs((int) $fxOdiff), 10)) . ' تومان ' . ((int) $fxOdiff < 0 ? 'کمتر' : ((int) $fxOdiff > 0 ? 'بیشتر' : 'بدون اختلاف')));
                                echo fxForappField('اختلاف مبلغ', fxForappH($fxOdiffLabel));
                                ?>
                            </div>
                            <div>
                                <?php
                                echo fxForappField('زمان ایجاد پرداخت', fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock((int) ($fxOcsp['created_at'] ?? 0), true) : '—'));
                                echo fxForappField('مهلت پرداخت', fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock((int) ($fxOcsp['expires_at'] ?? 0), true) : '—'));
                                echo fxForappField('زمان ارسال رسید', fxForappH(fxForappJdate($fxOrep['time'] ?? '')));
                                echo fxForappField('شناسه فاکتور', fxForappH((string) ($fxOrep['id_invoice'] ?? '—')));
                                echo fxForappField('۴ رقم آخر کارت', fxForappH((string) ($fxOrep['card_last4'] ?? '')) !== '' ? fxForappH((string) $fxOrep['card_last4']) : '—');
                                echo fxForappField('منبع پرداخت', fxForappH((string) ($fxOrep['source'] ?? '') === 'miniapp' ? 'مینی‌اپ' : 'ربات'));
                                echo fxForappField('واریز متصل', $fxOdepositLink);
                                if ($fxOdep !== null) {
                                    echo fxForappField('مبلغ واریز', fxForappH(function_exists('rx_sbv_toman_from_rial') ? rx_sbv_toman_from_rial(isset($fxOdep['amount_rial']) && $fxOdep['amount_rial'] !== null ? (int) $fxOdep['amount_rial'] : null) : '—'));
                                    echo fxForappField('بانک / فرستنده', fxForappH((string) ($fxOdep['bank'] ?? '—')) . ' / ' . fxForappH((string) ($fxOdep['sender'] ?? '—')));
                                    echo fxForappField('زمان واریز', fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock(fxForappDepositTime($fxOdep), true) : '—'));
                                }
                                echo fxForappField('یادداشت / دلیل', fxForappH((string) ($fxOrep['dec_not_confirmed'] ?? '')) !== '' ? fxForappH((string) $fxOrep['dec_not_confirmed']) : '—');
                                ?>
                            </div>
                        </div>

                        <?php if ($fxOphoto): ?>
                            <div class="forapp-detail-section">
                                <h3 class="forapp-detail-section__title"><?php echo icon('receipt', 'svg-icon svg-sm'); ?> تصویر رسید ارسال‌شده</h3>
                                <a href="forapp.php?media=<?php echo urlencode((string) ($fxOrep['id_order'] ?? '')); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><?php echo icon('eye', 'svg-icon svg-sm'); ?> مشاهده تصویر رسید</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($fxOcanDecide || $fxOcanReceived || $fxOcanCredit || $fxOcanCancel): ?>
                            <div class="forapp-actions-grid">
                                <?php if ($fxOcanDecide): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('تأیید و تسویه نهایی این پرداخت انجام شود؟');">
                                        <?php echo fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="approve">
                                        <input type="hidden" name="id_order" value="<?php echo fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo icon('circle-check', 'svg-icon svg-sm'); ?> تأیید و تسویه پرداخت</div>
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل تأیید دستی؛ در صورت نبود واریز منطبق الزامی است"></textarea>
                                        <button type="submit" class="btn btn-soft-success btn-sm">تایید نهایی</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanDecide): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('رد این پرداخت انجام شود؟');">
                                        <?php echo fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="reject">
                                        <input type="hidden" name="id_order" value="<?php echo fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="deposit_id" value="<?php echo (int) ($fxOcsp['deposit_id'] ?? 0); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo icon('xmark', 'svg-icon svg-sm'); ?> رد پرداخت</div>
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل رد کردن الزامی است" required></textarea>
                                        <button type="submit" class="btn btn-soft-danger btn-sm">رد پرداخت</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanReceived): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('مبلغ دریافتی این پرداخت اصلاح شود؟');">
                                        <?php echo fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="set_received">
                                        <input type="hidden" name="id_order" value="<?php echo fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo icon('pen-to-square', 'svg-icon svg-sm'); ?> اصلاح مبلغ دریافتی</div>
                                        <input type="text" name="received" class="form-control" inputmode="numeric" placeholder="مبلغ دریافتی به تومان" value="<?php echo $fxOrderView['received_rial'] !== null ? (int) intdiv((int) $fxOrderView['received_rial'], 10) : ''; ?>">
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل اصلاح مبلغ الزامی است" required></textarea>
                                        <button type="submit" class="btn btn-outline btn-sm">ثبت مبلغ دریافتی</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanCredit): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('مبلغ دریافتی به کیف پول کاربر واریز شود؟');">
                                        <?php echo fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="credit">
                                        <input type="hidden" name="id_order" value="<?php echo fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo icon('wallet-plus', 'svg-icon svg-sm'); ?> واریز به کیف پول</div>
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل واریز کامل مبلغ دریافتی به کیف پول" required></textarea>
                                        <button type="submit" class="btn btn-primary btn-sm">واریز به کیف پول</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanCancel): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('این پرداخت منقضی لغو و از فهرست پیشنهادها حذف شود؟');">
                                        <?php echo fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="cancel_expired">
                                        <input type="hidden" name="id_order" value="<?php echo fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo icon('trash', 'svg-icon svg-sm'); ?> لغو پرداخت منقضی</div>
                                        <p class="forapp-candidate-note" style="margin:0;">فقط همین پرداخت منقضی بسته می‌شود؛ سابقه مالی حذف نمی‌شود.</p>
                                        <button type="submit" class="btn btn-soft-danger btn-sm">لغو پرداخت منقضی</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="forapp-detail-section">
                            <h3 class="forapp-detail-section__title"><?php echo icon('link', 'svg-icon svg-sm'); ?> واریزهای بانکی پیشنهادی</h3>
                            <?php if (is_array($fxOrderCand) && !empty($fxOrderCand['blocked'])): ?>
                                <div class="alert alert-danger"><?php echo fxForappH(fxForappErrorText((string) $fxOrderCand['blocked'])); ?></div>
                            <?php elseif (!is_array($fxOrderCand) || empty($fxOrderCand['ok'])): ?>
                                <div class="alert alert-danger">جست‌وجوی واریزهای بانکی با خطا مواجه شد؛ این نتیجه به معنی نبود واریز احتمالی نیست. دوباره تلاش کنید.</div>
                            <?php elseif (empty($fxOrderCand['rows'])): ?>
                                <p class="forapp-candidate-note" style="margin:0;">هنوز هیچ واریز بانکی متناظری برای این پرداخت ثبت نشده است. اگر پیامک بانکی پس از ارسال رسید رسیده باشد، با «بررسی مجدد» فهرست به‌روزرسانی می‌شود.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                    <table class="display app-table app-table--summary" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>شناسه واریز</th>
                                                <th>مبلغ (تومان)</th>
                                                <th>بانک / فرستنده</th>
                                                <th>زمان واریز</th>
                                                <th>اختلاف</th>
                                                <th>وضعیت</th>
                                                <th data-no-sort="1">عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php $fxOcBase = ((int) $fxOrderCand['page']) * 5; $fxOi = 0; foreach ($fxOrderCand['rows'] as $fxOdepRow): $fxOi++; ?>
                                            <?php
                                            $fxOdepAmountRial = isset($fxOdepRow['amount_rial']) && $fxOdepRow['amount_rial'] !== null ? (int) $fxOdepRow['amount_rial'] : 0;
                                            $fxOdepDiff = $fxOdepAmountRial - (int) ($fxOcsp['payable_rial'] ?? 0);
                                            $fxOdepState = (string) ($fxOdepRow['state'] ?? '');
                                            $fxOdepMeta = $fxDepositMeta[$fxOdepState] ?? ['label' => function_exists('rx_sbv_deposit_state_label') ? rx_sbv_deposit_state_label($fxOdepState) : $fxOdepState, 'class' => 'badge-gray'];
                                            $fxOlinkGuard = function_exists('rx_sbv_payment_link_guard') ? rx_sbv_payment_link_guard($fxViewOrder, (int) $fxOdepRow['id']) : 'unavailable';
                                            ?>
                                            <tr>
                                                <td data-label="#" data-summary="1"><?php echo fx_persian_num($fxOcBase + $fxOi); ?></td>
                                                <td data-label="شناسه واریز" data-summary="1"><span style="direction:ltr;display:inline-block;">#<?php echo (int) $fxOdepRow['id']; ?></span></td>
                                                <td data-label="مبلغ (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo number_format(intdiv($fxOdepAmountRial, 10)); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                                <td data-label="بانک / فرستنده"><?php echo fxForappH((string) ($fxOdepRow['bank'] ?? '—')); ?> / <?php echo fxForappH((string) ($fxOdepRow['sender'] ?? '—')); ?></td>
                                                <td data-label="زمان واریز" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock(fxForappDepositTime($fxOdepRow), true) : '—'); ?></span></td>
                                                <td data-label="اختلاف"><?php echo fxForappH(fxForappDiffLabel($fxOdepDiff)); ?></td>
                                                <td data-label="وضعیت" data-summary="1"><?php echo fxForappBadge($fxOdepMeta); ?></td>
                                                <td data-label="عملیات" class="cell-actions">
                                                    <?php if ($fxOlinkGuard === null): ?>
                                                        <form method="POST" action="forapp.php" style="display:inline-block;">
                                                            <?php echo fx_csrf_field(); ?>
                                                            <input type="hidden" name="_fx_action" value="link_deposit">
                                                            <input type="hidden" name="id_order" value="<?php echo fxForappH($fxViewOrder); ?>">
                                                            <input type="hidden" name="deposit_id" value="<?php echo (int) $fxOdepRow['id']; ?>">
                                                            <input type="hidden" name="cpage" value="<?php echo (int) $fxOrderCand['page']; ?>">
                                                            <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxOrderKeepQs); ?>">
                                                            <button type="submit" class="btn btn-soft-success btn-sm"><?php echo icon('link', 'svg-icon svg-sm'); ?> اتصال</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray" title="<?php echo fxForappH(fxForappErrorText((string) $fxOlinkGuard)); ?>">غیرقابل اتصال</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php
                                    $fxOrderCandKeep = array_merge($fxKeep, ['p' => (int) $fxPg['page'], 'order' => $fxViewOrder]);
                                    echo fx_pager_html((int) $fxOrderCand['page'] + 1, max(1, (int) $fxOrderCand['pages']), (int) $fxOrderCand['total'], 5, 'forapp.php', $fxOrderCandKeep, 'cpage');
                                    ?>
                                </div>
                                <p class="forapp-candidate-note">این واریزها فقط پیشنهاد هستند؛ اتصال به‌تنهایی پرداخت را تأیید نمی‌کند و تأیید خودکار انجام نمی‌شود.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($fxTab === 'deposits' && $fxViewDeposit !== ''): ?>
                <div class="card invoice-results">
                    <div class="invoice-results__head">
                        <div>
                            <h2><?php echo icon('atm-machine', 'svg-icon svg-sm'); ?> جزئیات واریز بانکی فوراپ</h2>
                            <p>بررسی واریز، پرداخت‌های احتمالی و اتصال امن به پرداخت.</p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxDepositBack); ?>"><?php echo icon('chevron-right', 'svg-icon svg-sm'); ?> بازگشت</a>
                            <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxDepositSelf); ?>"><?php echo icon('refresh', 'svg-icon svg-sm'); ?> بروزرسانی پیشنهادها</a>
                        </div>
                    </div>
                    <?php if ($fxDepositView === null): ?>
                        <div class="alert alert-danger">واریزی با این شناسه یافت نشد.</div>
                    <?php else:
                        $fxDstate = (string) ($fxDepositView['state'] ?? '');
                        $fxDmeta = $fxDepositMeta[$fxDstate] ?? ['label' => function_exists('rx_sbv_deposit_state_label') ? rx_sbv_deposit_state_label($fxDstate) : $fxDstate, 'class' => 'badge-gray'];
                        $fxDlinkedOrder = trim((string) ($fxDepositView['id_order'] ?? ''));
                        $fxDcanDelete = in_array($fxDstate, ['unmatched', 'ambiguous', 'late'], true) && $fxDlinkedOrder === '';
                        $fxDbody = (string) ($fxDepositView['body'] ?? '');
                        ?>
                        <div class="forapp-detail-grid">
                            <div>
                                <?php
                                echo fxForappField('شناسه واریز', '<span style="direction:ltr;">#' . (int) $fxDepositView['id'] . '</span>');
                                echo fxForappField('مبلغ واریز', fxForappH(function_exists('rx_sbv_toman_from_rial') ? rx_sbv_toman_from_rial(isset($fxDepositView['amount_rial']) && $fxDepositView['amount_rial'] !== null ? (int) $fxDepositView['amount_rial'] : null) : '—'));
                                echo fxForappField('بانک', fxForappH((string) ($fxDepositView['bank'] ?? '—')));
                                echo fxForappField('فرستنده', fxForappH((string) ($fxDepositView['sender'] ?? '—')));
                                echo fxForappField('کد پیامک', fxForappH((string) ($fxDepositView['code'] ?? '—')));
                                echo fxForappField('وضعیت تطبیق', fxForappBadge($fxDmeta));
                                ?>
                            </div>
                            <div>
                                <?php
                                echo fxForappField('زمان دریافت در دستگاه', fxForappH((string) ($fxDepositView['received_at_raw'] ?? '—')));
                                echo fxForappField('زمان ثبت در سرور', fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock((int) ($fxDepositView['stored_at'] ?? 0), true) : '—'));
                                echo fxForappField('دستگاه', fxForappH((string) ($fxDepositView['device'] ?? '—')));
                                echo fxForappField('سفارش مرتبط', $fxDlinkedOrder !== '' ? '<a class="text-link" style="direction:ltr;" href="forapp.php?tab=receipts&order=' . urlencode($fxDlinkedOrder) . '">' . fxForappH($fxDlinkedOrder) . '</a>' : '—');
                                echo fxForappField('یادداشت', fxForappH((string) ($fxDepositView['note'] ?? '')) !== '' ? fxForappH((string) $fxDepositView['note']) : '—');
                                ?>
                            </div>
                        </div>
                        <?php if ($fxDbody !== ''): ?>
                            <div class="forapp-detail-section">
                                <h3 class="forapp-detail-section__title"><?php echo icon('message', 'svg-icon svg-sm'); ?> متن پیامک بانکی</h3>
                                <div class="forapp-deposit-body"><?php echo fxForappH($fxDbody); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($fxDcanDelete): ?>
                            <div class="forapp-actions-grid">
                                <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('این واریز برای همیشه حذف شود؟');">
                                    <?php echo fx_csrf_field(); ?>
                                    <input type="hidden" name="_fx_action" value="delete_deposit">
                                    <input type="hidden" name="deposit_id" value="<?php echo (int) $fxDepositView['id']; ?>">
                                    <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxDepositDeleteKeepQs); ?>">
                                    <div class="forapp-action__title"><?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف واریز</div>
                                    <p class="forapp-candidate-note" style="margin:0;">فقط واریزهای تطبیق‌نیافته و بدون اتصال قابل حذف هستند.</p>
                                    <button type="submit" class="btn btn-soft-danger btn-sm">حذف واریز</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="forapp-detail-section">
                            <h3 class="forapp-detail-section__title"><?php echo icon('link', 'svg-icon svg-sm'); ?> پرداخت‌های احتمالی</h3>
                            <?php if (!is_array($fxDepositCand) || empty($fxDepositCand['ok'])): ?>
                                <div class="alert alert-danger">جست‌وجوی پرداخت‌های احتمالی با خطا مواجه شد؛ این نتیجه به معنی نبود پرداخت احتمالی نیست.</div>
                            <?php elseif (empty($fxDepositCand['rows'])): ?>
                                <p class="forapp-candidate-note" style="margin:0;">هیچ پرداخت فعال یا منقضی‌شده‌ای با مبلغ و زمان نزدیک پیدا نشد.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                    <table class="display app-table app-table--summary" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>کد سفارش</th>
                                                <th>کاربر</th>
                                                <th>نوع پرداخت</th>
                                                <th>قابل پرداخت (تومان)</th>
                                                <th>اختلاف</th>
                                                <th>ایجاد</th>
                                                <th>مهلت</th>
                                                <th>وضعیت</th>
                                                <th data-no-sort="1">عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php $fxDcBase = ((int) $fxDepositCand['page']) * 5; $fxDi = 0; foreach ($fxDepositCand['rows'] as $fxDcand): $fxDi++; ?>
                                            <?php
                                            $fxDcOrder = (string) ($fxDcand['id_order'] ?? '');
                                            $fxDcPayable = (int) ($fxDcand['payable_rial'] ?? 0);
                                            $fxDcDiff = (int) ($fxDepositView['amount_rial'] ?? 0) - $fxDcPayable;
                                            $fxDcExpired = !empty($fxDcand['candidate_expired']);
                                            $fxDcStatus = (string) ($fxDcand['report_status'] ?? '');
                                            $fxDcStatusMeta = fxForappPaymentBadgeMeta($fxDcStatus, (string) ($fxDcand['state'] ?? ''), !empty($fxDcand['candidate_expired']), (string) ($fxDcand['resolution'] ?? ''));
                                            $fxDcName = trim((string) ($fxDcand['namecustom'] ?? ''));
                                            $fxDcUsername = trim((string) ($fxDcand['username'] ?? ''));
                                            $fxDcUserLabel = ($fxDcName !== '' && $fxDcName !== 'none') ? $fxDcName : ($fxDcUsername !== '' ? '@' . $fxDcUsername : '');
                                            $fxDcLinkGuard = function_exists('rx_sbv_payment_link_guard') ? rx_sbv_payment_link_guard($fxDcOrder, (int) $fxDepositView['id']) : 'unavailable';
                                            $fxDcDeleteGuard = $fxDcExpired && function_exists('rx_sbv_expired_payment_delete_guard') ? rx_sbv_expired_payment_delete_guard($fxDcOrder, null, null, (int) $fxDepositView['id']) : 'not_expired';
                                            $fxDcOrderLink = 'forapp.php?tab=receipts&order=' . urlencode($fxDcOrder);
                                            ?>
                                            <tr>
                                                <td data-label="#" data-summary="1"><?php echo fx_persian_num($fxDcBase + $fxDi); ?></td>
                                                <td data-label="کد سفارش" data-summary="1"><span class="track-id" style="direction:ltr;" onclick="fxForappCopy('<?php echo fxForappH(addslashes($fxDcOrder)); ?>')"><?php echo fxForappH($fxDcOrder); ?></span></td>
                                                <td data-label="کاربر"><span style="direction:ltr;display:inline-block;"><?php echo fxForappH((string) ($fxDcand['id_user'] ?? '')); ?></span><?php echo $fxDcUserLabel !== '' ? '<br><small style="color:var(--text-muted);">' . fxForappH($fxDcUserLabel) . '</small>' : ''; ?></td>
                                                <td data-label="نوع پرداخت"><span class="receipt-order-type"><?php echo fxForappH(function_exists('rx_sbv_invoice_label') ? rx_sbv_invoice_label((string) ($fxDcand['report_invoice'] ?? '')) : '—'); ?></span></td>
                                                <td data-label="قابل پرداخت (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo number_format(intdiv($fxDcPayable, 10)); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                                <td data-label="اختلاف"><?php echo fxForappH(fxForappDiffLabel($fxDcDiff)); ?></td>
                                                <td data-label="ایجاد" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock((int) ($fxDcand['created_at'] ?? 0), true) : '—'); ?></span></td>
                                                <td data-label="مهلت" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock((int) ($fxDcand['expires_at'] ?? 0), true) : '—'); ?></span></td>
                                                <td data-label="وضعیت" data-summary="1"><?php echo fxForappBadge($fxDcStatusMeta); ?><?php echo $fxDcExpired ? ' <span class="badge badge-warning">منقضی</span>' : ''; ?></td>
                                                <td data-label="عملیات" class="cell-actions">
                                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                        <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxDcOrderLink); ?>" title="مشاهده پرداخت"><?php echo icon('eye', 'svg-icon svg-sm'); ?></a>
                                                        <?php if ($fxDcLinkGuard === null): ?>
                                                            <form method="POST" action="forapp.php" style="display:inline-block;" onsubmit="return confirm('این واریز به پرداخت انتخاب‌شده متصل شود؟');">
                                                                <?php echo fx_csrf_field(); ?>
                                                                <input type="hidden" name="_fx_action" value="link_payment">
                                                                <input type="hidden" name="id_order" value="<?php echo fxForappH($fxDcOrder); ?>">
                                                                <input type="hidden" name="deposit_id" value="<?php echo (int) $fxDepositView['id']; ?>">
                                                                <input type="hidden" name="cpage" value="<?php echo (int) $fxDepositCand['page']; ?>">
                                                                <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxDepositKeepQs); ?>">
                                                                <button type="submit" class="btn btn-soft-success btn-sm"><?php echo icon('link', 'svg-icon svg-sm'); ?> اتصال</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($fxDcExpired && $fxDcDeleteGuard === null): ?>
                                                            <form method="POST" action="forapp.php" style="display:inline-block;" onsubmit="return confirm('این پرداخت منقضی لغو و از فهرست پیشنهادها حذف شود؟');">
                                                                <?php echo fx_csrf_field(); ?>
                                                                <input type="hidden" name="_fx_action" value="cancel_expired">
                                                                <input type="hidden" name="id_order" value="<?php echo fxForappH($fxDcOrder); ?>">
                                                                <input type="hidden" name="deposit_id" value="<?php echo (int) $fxDepositView['id']; ?>">
                                                                <input type="hidden" name="cpage" value="<?php echo (int) $fxDepositCand['page']; ?>">
                                                                <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxDepositKeepQs); ?>">
                                                                <button type="submit" class="btn btn-soft-danger btn-sm"><?php echo icon('trash', 'svg-icon svg-sm'); ?> لغو منقضی</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php
                                    $fxDepositCandKeep = array_merge($fxKeep, ['p' => (int) $fxDpg['page'], 'deposit' => $fxViewDeposit]);
                                    echo fx_pager_html((int) $fxDepositCand['page'] + 1, max(1, (int) $fxDepositCand['pages']), (int) $fxDepositCand['total'], 5, 'forapp.php', $fxDepositCandKeep, 'cpage');
                                    ?>
                                </div>
                                <p class="forapp-candidate-note">پیشنهادها قطعی نیستند و هیچ پرداختی به‌صورت خودکار تأیید نمی‌شود.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <details class="wrapper fx-page-list fx-filter-drawer fx-page-receipts">
                <summary class="fx-filter-drawer__summary">
                    <span class="fx-filter-drawer__icon" aria-hidden="true"><?php echo icon('filter', 'svg-icon'); ?></span>
                    <span class="fx-filter-drawer__text"><strong>جستجو و فیلترها</strong><small><?php echo $fxTab === 'deposits' ? 'جستجو، وضعیت واریز و بازهٔ زمانی' : 'جستجو، وضعیت پرداخت، تطبیق و بازهٔ زمانی'; ?></small></span>
                    <span class="fx-filter-drawer__action"><span class="fx-filter-drawer__show">نمایش فیلترها</span><span class="fx-filter-drawer__hide">بستن فیلترها</span></span>
                    <span class="fx-filter-drawer__chevron" aria-hidden="true"><?php echo icon('chevron-down', 'svg-icon'); ?></span>
                </summary>

                <?php echo fx_search_ui('forapp.php', $fxQ, $fxKeepExcept(['q']), $fxSearchPlaceholder); ?>

                <?php echo fx_status_filter_ui('forapp.php', $fxStatusOptions, $fxStatus, $fxKeepExcept(['status']), 'status', $fxTab === 'deposits' ? 'فیلتر وضعیت واریز' : 'فیلتر وضعیت پرداخت'); ?>

                <?php echo fx_extra_filter_ui('forapp.php', $fxKeepExcept($fxExtraDrop), $fxExtraFields, $fxExtraCriteria, 'فیلترهای بیشتر'); ?>

                <?php $fxDateKeep = $fxKeepExcept(['ddays']); $fxDateClearQs = fx_qs($fxDateKeep, ['ddays' => null]); ?>
                <div class="card fx-date-filter fx-date-filter--days-only">
                    <div class="fx-date-filter__title">فیلتر بر اساس تاریخ <?php echo $fxTab === 'deposits' ? 'واریز' : 'ایجاد پرداخت'; ?></div>
                    <form method="GET" action="forapp.php" class="fx-date-filter__form">
                        <?php foreach ($fxDateKeep as $fxDateKey => $fxDateValue): ?>
                            <input type="hidden" name="<?php echo fxForappH((string) $fxDateKey); ?>" value="<?php echo fxForappH((string) $fxDateValue); ?>">
                        <?php endforeach; ?>
                        <div class="fx-date-filter__field">
                            <label for="fx-forapp-days">تعداد روز اخیر</label>
                            <input type="number" id="fx-forapp-days" name="ddays" min="1" inputmode="numeric" value="<?php echo fxForappH((string) ($_GET['ddays'] ?? '')); ?>" onchange="this.form.submit()">
                        </div>
                    </form>
                    <?php if ($fxDf['active']): ?>
                        <div class="fx-date-filter__active">فیلتر فعال: <?php echo fxForappH($fxDf['label']); ?> — <a class="text-link" href="forapp.php?<?php echo fxForappH($fxDateClearQs); ?>">حذف فیلتر</a></div>
                    <?php endif; ?>
                </div>
            </details>

            <?php if ($fxTab === 'receipts'): ?>
                <div class="card invoice-results">
                    <div class="invoice-results__head">
                        <div><h2>رسیدها و سفارش‌های فوراپ</h2><p>رسیدهای ارسال‌شده، وضعیت تطبیق و مبالغ پرداخت فوراپ</p></div>
                        <span class="invoice-page-count"><?php echo fx_persian_num(number_format((int) $fxPg['total'])); ?> ردیف<?php echo $fxFilterActive ? ' مطابق فیلتر' : ' ثبت‌شده'; ?></span>
                    </div>
                    <form method="POST" action="forapp.php" id="forapp-bulk-form">
                        <?php echo fx_csrf_field(); ?>
                        <input type="hidden" name="_fx_action" value="bulk_cancel">
                        <input type="hidden" name="_keep_qs" value="<?php echo fxForappH(http_build_query($fxKeep)); ?>">
                        <div class="table-wrap">
                            <table id="forappReceiptsTable" class="display app-table app-table--summary" style="width:100%">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="check-all-forapp" onclick="fxForappToggleAll(this)"></th>
                                        <th>وضعیت</th>
                                        <th>کاربر</th>
                                        <th>کد سفارش</th>
                                        <th>نوع پرداخت</th>
                                        <th>مبالغ (تومان)</th>
                                        <th>دریافتی (تومان)</th>
                                        <th>تطبیق</th>
                                        <th>ایجاد</th>
                                        <th>مهلت</th>
                                        <th>رسید</th>
                                        <th>واریز</th>
                                        <th data-no-sort="1">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($fxRows as $fxRow):
                                    $fxRowStatus = (string) ($fxRow['payment_Status'] ?? '');
                                    $fxRowState = (string) ($fxRow['csp_state'] ?? '');
                                    $fxRowResolution = (string) ($fxRow['resolution'] ?? '');
                                    $fxRowExpired = function_exists('rx_sbv_expired_from_values') ? rx_sbv_expired_from_values($fxRowState, $fxRowStatus, $fxRowResolution, (int) ($fxRow['expires_at'] ?? 0), (int) ($fxRow['direct_payment_done'] ?? 0)) : false;
                                    $fxRowPayMeta = fxForappPaymentBadgeMeta($fxRowStatus, $fxRowState, $fxRowExpired, $fxRowResolution);
                                    $fxRowMatchMeta = fxForappMatchBadgeMeta($fxRowStatus, $fxRowState, $fxRowExpired, $fxRowResolution);
                                    $fxRowName = trim((string) ($fxRow['namecustom'] ?? ''));
                                    $fxRowUsername = trim((string) ($fxRow['username'] ?? ''));
                                    $fxRowUserLabel = ($fxRowName !== '' && $fxRowName !== 'none') ? $fxRowName : ($fxRowUsername !== '' ? '@' . $fxRowUsername : '');
                                    $fxRowDeposit = (int) ($fxRow['deposit_id'] ?? 0);
                                    $fxRowDetail = 'forapp.php?' . fx_qs(array_merge($fxKeep, ['p' => (int) $fxPg['page']]), ['order' => (string) ($fxRow['id_order'] ?? ''), 'cpage' => null]);
                                    ?>
                                    <tr data-detail-row data-detail-title="پرداخت <?php echo fxForappH((string) ($fxRow['id_order'] ?? '')); ?>">
                                        <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo fxForappH((string) ($fxRow['id_order'] ?? '')); ?>"></label></td>
                                        <td data-label="وضعیت" data-summary="1"><?php echo fxForappBadge($fxRowPayMeta); ?></td>
                                        <td data-label="کاربر">
                                            <a href="user.php?id=<?php echo urlencode((string) ($fxRow['id_user'] ?? '')); ?>" class="text-link" style="direction:ltr;display:inline-block;"><?php echo fxForappH((string) ($fxRow['id_user'] ?? '')); ?></a>
                                            <?php if ($fxRowUserLabel !== ''): ?><br><small style="color:var(--text-muted);"><?php echo fxForappH($fxRowUserLabel); ?></small><?php endif; ?>
                                        </td>
                                        <td data-label="کد سفارش" data-summary="1">
                                            <span class="track-id invoice-reference" onclick="fxForappCopy('<?php echo fxForappH(addslashes((string) ($fxRow['id_order'] ?? ''))); ?>')" title="کپی کردن" style="direction:ltr;display:inline-block;"><?php echo fxForappH((string) ($fxRow['id_order'] ?? '')); ?></span>
                                        </td>
                                        <td data-label="نوع پرداخت"><span class="receipt-order-type"><?php echo fxForappH(function_exists('rx_sbv_invoice_label') ? rx_sbv_invoice_label((string) ($fxRow['id_invoice'] ?? '')) : '—'); ?></span></td>
                                        <td data-label="مبالغ (تومان)">
                                            <div style="display:flex;flex-direction:column;gap:2px;">
                                                <span class="invoice-amount"><bdi><?php echo number_format((int) ($fxRow['principal_toman'] ?? 0)); ?></bdi><small class="currency-unit">تومان</small></span>
                                                <small style="color:var(--text-muted);">کارمزد: <?php echo number_format((int) ($fxRow['fee_toman'] ?? 0)); ?></small>
                                                <small style="color:var(--text-muted);">قابل پرداخت: <?php echo number_format((int) ($fxRow['payable_toman'] ?? 0)); ?></small>
                                            </div>
                                        </td>
                                        <td data-label="دریافتی (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo $fxRow['received_rial'] !== null ? number_format(intdiv((int) $fxRow['received_rial'], 10)) : '—'; ?></bdi><?php if ($fxRow['received_rial'] !== null): ?><small class="currency-unit">تومان</small><?php endif; ?></span></td>
                                        <td data-label="تطبیق" data-summary="1"><?php echo $fxRowMatchMeta !== null ? fxForappBadge($fxRowMatchMeta) : '—'; ?></td>
                                        <td data-label="ایجاد" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock((int) ($fxRow['csp_created_at'] ?? 0), true) : '—'); ?></span></td>
                                        <td data-label="مهلت" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock((int) ($fxRow['expires_at'] ?? 0), true) : '—'); ?></span></td>
                                        <td data-label="رسید" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo fxForappH(fxForappJdate($fxRow['time'] ?? '')); ?></span></td>
                                        <td data-label="واریز">
                                            <?php if ($fxRowDeposit > 0): ?>
                                                <a class="text-link" style="direction:ltr;display:inline-block;" href="forapp.php?tab=deposits&deposit=<?php echo $fxRowDeposit; ?>">#<?php echo $fxRowDeposit; ?></a>
                                                <?php if (isset($fxRow['deposit_amount_rial']) && $fxRow['deposit_amount_rial'] !== null): ?>
                                                    <br><small style="color:var(--text-muted);"><?php echo number_format(intdiv((int) $fxRow['deposit_amount_rial'], 10)); ?> تومان</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="عملیات" class="cell-actions">
                                            <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxRowDetail); ?>" title="جزئیات و مدیریت"><?php echo icon('eye', 'svg-icon svg-sm'); ?></a>
                                            <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxRowDetail); ?>" title="واریزها و تطبیق"><?php echo icon('link', 'svg-icon svg-sm'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($fxRows)): ?>
                                    <tr><td colspan="13" style="text-align:center;padding:24px;">رسیدی برای نمایش یافت نشد.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                            <?php echo fx_pager_html((int) $fxPg['page'], (int) $fxPg['pages'], (int) $fxPg['total'], count($fxRows), 'forapp.php', $fxPgKeep); ?>
                        </div>
                        <div style="padding:12px 0;">
                            <button type="submit" class="btn btn-soft-danger btn-sm js-forapp-bulk-delete-btn" style="display:none;" onclick="return confirm('پرداخت‌های انتخاب‌شده پس از بررسی شرایط به‌صورت ایمن لغو می‌شوند و سابقه مالی حفظ می‌شود. ادامه می‌دهید؟')">
                                <?php echo icon('trash', 'svg-icon svg-sm'); ?> حذف انتخاب‌شده‌ها
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card invoice-results">
                    <div class="invoice-results__head">
                        <div><h2>واریزی‌ها و تطبیق فوراپ</h2><p>واریزهای بانکی، وضعیت تطبیق و پرداخت‌های احتمالی</p></div>
                        <span class="invoice-page-count"><?php echo fx_persian_num(number_format((int) $fxDpg['total'])); ?> واریز<?php echo $fxFilterActive ? ' مطابق فیلتر' : ' ثبت‌شده'; ?></span>
                    </div>
                    <div class="table-wrap">
                        <table id="forappDepositsTable" class="display app-table app-table--summary" style="width:100%">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>مبلغ (تومان)</th>
                                    <th>بانک / فرستنده</th>
                                    <th>زمان واریز</th>
                                    <th>وضعیت تطبیق</th>
                                    <th>سفارش مرتبط</th>
                                    <th>پرداخت احتمالی</th>
                                    <th data-no-sort="1">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($fxDRows as $fxDRow):
                                $fxDRowState = (string) ($fxDRow['state'] ?? '');
                                $fxDRowMeta = $fxDepositMeta[$fxDRowState] ?? ['label' => function_exists('rx_sbv_deposit_state_label') ? rx_sbv_deposit_state_label($fxDRowState) : $fxDRowState, 'class' => 'badge-gray'];
                                $fxDRowLinked = trim((string) ($fxDRow['id_order'] ?? ''));
                                if ($fxDRowLinked === '') $fxDRowLinked = trim((string) ($fxDRow['csp_order'] ?? ''));
                                $fxDRowDetail = 'forapp.php?' . fx_qs(array_merge($fxKeep, ['p' => (int) $fxDpg['page']]), ['deposit' => (int) ($fxDRow['id'] ?? 0), 'cpage' => null]);
                                $fxDRowCanDelete = in_array($fxDRowState, ['unmatched', 'ambiguous', 'late'], true) && $fxDRowLinked === '';
                                ?>
                                <tr data-detail-row data-detail-title="واریز <?php echo (int) ($fxDRow['id'] ?? 0); ?>">
                                    <td data-label="شناسه" data-summary="1"><span style="direction:ltr;display:inline-block;">#<?php echo (int) ($fxDRow['id'] ?? 0); ?></span></td>
                                    <td data-label="مبلغ (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo number_format(intdiv((int) ($fxDRow['amount_rial'] ?? 0), 10)); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                    <td data-label="بانک / فرستنده"><?php echo fxForappH((string) ($fxDRow['bank'] ?? '—')); ?> / <?php echo fxForappH((string) ($fxDRow['sender'] ?? '—')); ?></td>
                                    <td data-label="زمان واریز" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo fxForappH(function_exists('rx_sbv_clock') ? rx_sbv_clock(fxForappDepositTime($fxDRow), true) : '—'); ?></span></td>
                                    <td data-label="وضعیت تطبیق" data-summary="1"><?php echo fxForappBadge($fxDRowMeta); ?></td>
                                    <td data-label="سفارش مرتبط">
                                        <?php if ($fxDRowLinked !== ''): ?>
                                            <a class="text-link" style="direction:ltr;display:inline-block;" href="forapp.php?tab=receipts&order=<?php echo urlencode($fxDRowLinked); ?>"><?php echo fxForappH($fxDRowLinked); ?></a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="پرداخت احتمالی">
                                        <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxDRowDetail); ?>"><?php echo icon('search', 'svg-icon svg-sm'); ?> بررسی</a>
                                    </td>
                                    <td data-label="عملیات" class="cell-actions">
                                        <a class="btn btn-outline btn-sm" href="<?php echo fxForappH($fxDRowDetail); ?>" title="جزئیات واریز"><?php echo icon('eye', 'svg-icon svg-sm'); ?></a>
                                        <?php if ($fxDRowCanDelete): ?>
                                            <form method="POST" action="forapp.php" style="display:inline-block;" onsubmit="return confirm('این واریز برای همیشه حذف شود؟');">
                                                <?php echo fx_csrf_field(); ?>
                                                <input type="hidden" name="_fx_action" value="delete_deposit">
                                                <input type="hidden" name="deposit_id" value="<?php echo (int) ($fxDRow['id'] ?? 0); ?>">
                                                <input type="hidden" name="_keep_qs" value="<?php echo fxForappH($fxDepositDeleteKeepQs); ?>">
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="حذف واریز"><?php echo icon('trash', 'svg-icon svg-sm'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($fxDRows)): ?>
                                <tr><td colspan="8" style="text-align:center;padding:24px;">واریزی‌ای برای نمایش یافت نشد.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <?php echo fx_pager_html((int) $fxDpg['page'], (int) $fxDpg['pages'], (int) $fxDpg['total'], count($fxDRows), 'forapp.php', $fxDpgKeep); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
</section>

<script src="js/bulk-select.js?v=fx2"></script>
<script>
function fxForappCopy(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
            alert('کد سفارش کپی شد: ' + text);
        });
    }
}

function fxForappSyncMaster() {
    var form = document.getElementById('forapp-bulk-form');
    var master = document.getElementById('check-all-forapp');
    if (!form || !master) return;
    var boxes = form.querySelectorAll('input[name="ids[]"]:not(:disabled)');
    var checked = 0;
    boxes.forEach(function (box) {
        if (box.checked) checked++;
    });
    master.checked = checked > 0 && checked === boxes.length;
    master.indeterminate = checked > 0 && checked < boxes.length;
}

function fxForappToggleAll(master) {
    var form = document.getElementById('forapp-bulk-form');
    if (!form) return;
    var target = master.checked;
    form.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(function (cb) {
        if (cb.checked === target) return;
        cb.checked = target;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
    fxForappSyncMaster();
}

(function () {
    var form = document.getElementById('forapp-bulk-form');
    if (!form) return;

    form.addEventListener('change', function (e) {
        var cb = e.target;
        if (!cb || cb.name !== 'ids[]') return;
        fxForappSyncMaster();
    });

    if (window.FxBulkSelect) {
        FxBulkSelect.init({
            scope: 'forapp',
            checkboxSelector: 'input[name="ids[]"]',
            scopeRoot: form,
            formEl: form,
            deleteButtonSelector: '.js-forapp-bulk-delete-btn',
            clearOnQueryFlags: ['bulk']
        });
    }

    fxForappSyncMaster();
})();
</script>

</body>
</html>
