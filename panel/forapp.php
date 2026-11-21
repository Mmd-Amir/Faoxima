<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'forapp.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
date_default_timezone_set('Asia/Tehran');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/extra_filter.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/bulk_delete.php';

if (\Faoxima\Admin\Web\Pages\ForappRequest::handle(
        APIKEY: $APIKEY ?? null,
        f: $f,
        fxApprovedToday: $fxApprovedToday,
        fxDRows: $fxDRows,
        fxDepositBack: $fxDepositBack,
        fxDepositCand: $fxDepositCand,
        fxDepositDeleteKeepQs: $fxDepositDeleteKeepQs,
        fxDepositKeepQs: $fxDepositKeepQs,
        fxDepositMeta: $fxDepositMeta,
        fxDepositSelf: $fxDepositSelf,
        fxDepositView: $fxDepositView,
        fxDf: $fxDf,
        fxDpg: $fxDpg,
        fxDpgKeep: $fxDpgKeep,
        fxExtraCriteria: $fxExtraCriteria,
        fxExtraDrop: $fxExtraDrop,
        fxExtraFields: $fxExtraFields,
        fxFilterActive: $fxFilterActive,
        fxFlash: $fxFlash,
        fxKeep: $fxKeep,
        fxKeepExcept: $fxKeepExcept,
        fxOrderBack: $fxOrderBack,
        fxOrderCand: $fxOrderCand,
        fxOrderKeepQs: $fxOrderKeepQs,
        fxOrderSelf: $fxOrderSelf,
        fxOrderUser: $fxOrderUser,
        fxOrderView: $fxOrderView,
        fxPendingCount: $fxPendingCount,
        fxPg: $fxPg,
        fxPgKeep: $fxPgKeep,
        fxQ: $fxQ,
        fxRejectedToday: $fxRejectedToday,
        fxRows: $fxRows,
        fxSchemaReady: $fxSchemaReady,
        fxSearchPlaceholder: $fxSearchPlaceholder,
        fxStatus: $fxStatus,
        fxStatusOptions: $fxStatusOptions,
        fxTab: $fxTab,
        fxTabDepositsQs: $fxTabDepositsQs,
        fxTabReceiptsQs: $fxTabReceiptsQs,
        fxTotalRecords: $fxTotalRecords,
        fxUnmatchedDeposits: $fxUnmatchedDeposits,
        fxViewDeposit: $fxViewDeposit,
        fxViewOrder: $fxViewOrder,
        pdo: $pdo ?? null,
        query: $query
    ) !== true) {
    unset($adminWebPage);
    return;
}

unset($adminWebPage);
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
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('credit-card', 'svg-icon svg-lg'); ?>
                        مدیریت پرداخت‌های فوراپ
                    </div>
                    <div class="page-head__sub">رسیدها، سفارش‌ها، واریزهای بانکی و تطبیق پرداخت‌های فوراپ</div>
                </div>
            </div>

            <?php if ($fxFlash): ?>
                <div class="alert <?php echo $fxFlash['type'] === 'ok' ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxFlash['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (!$fxSchemaReady): ?>
                <div class="alert alert-danger">جداول پرداخت فوراپ در دسترس نیستند. پس از آماده‌سازی سامانه دوباره تلاش کنید.</div>
            <?php endif; ?>

            <div class="receipt-summary-grid">
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('hourglass', 'svg-icon svg-sm'); ?> در انتظار بررسی</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxPendingCount); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon svg-sm'); ?> تایید شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxApprovedToday); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?> رد شده امروز</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxRejectedToday); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('atm-machine', 'svg-icon svg-sm'); ?> واریز بدون تطبیق</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxUnmatchedDeposits); ?></div>
                </div>
                <div class="card receipt-summary-card">
                    <div class="receipt-summary-card__label"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('credit-card', 'svg-icon svg-sm'); ?> کل پرداخت‌های فوراپ</div>
                    <div class="receipt-summary-card__value"><?php echo number_format($fxTotalRecords); ?></div>
                </div>
            </div>

            <div class="card fx-status-filter">
                <div class="fx-status-filter__title">بخش مدیریت فوراپ</div>
                <div class="fx-status-filter__pills">
                    <a class="btn fx-status-filter__pill <?php echo $fxTab === 'receipts' ? 'btn-primary' : 'btn-outline'; ?>" href="forapp.php?<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxTabReceiptsQs); ?>">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon svg-sm'); ?> رسیدها و سفارش‌ها
                    </a>
                    <a class="btn fx-status-filter__pill <?php echo $fxTab === 'deposits' ? 'btn-primary' : 'btn-outline'; ?>" href="forapp.php?<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxTabDepositsQs); ?>">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('link', 'svg-icon svg-sm'); ?> واریزی‌ها و تطبیق
                    </a>
                </div>
            </div>

            <?php if ($fxTab === 'receipts' && $fxViewOrder !== ''): ?>
                <div class="card invoice-results">
                    <div class="invoice-results__head">
                        <div>
                            <h2><?php echo \Faoxima\Admin\Web\Support\Icons::icon('credit-card', 'svg-icon svg-sm'); ?> جزئیات پرداخت فوراپ</h2>
                            <p>بررسی رسید و واریزهای احتمالی؛ تصمیم مالی فقط با اقدام صریح ادمین ثبت می‌شود.</p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderBack); ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-right', 'svg-icon svg-sm'); ?> بازگشت</a>
                            <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderSelf); ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('refresh', 'svg-icon svg-sm'); ?> بررسی مجدد</a>
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
                        $fxOexpired = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_is_expired($fxOcsp, $fxOrep);
                        $fxOresolution = (string) ($fxOcsp['resolution'] ?? '');
                        $fxOpayMeta = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappPaymentBadgeMeta($fxOpayStatus, $fxOstate, $fxOexpired, $fxOresolution);
                        $fxOmatchMeta = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappMatchBadgeMeta($fxOpayStatus, $fxOstate, $fxOexpired, $fxOresolution);
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
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('کد سفارش', '<span class="track-id invoice-reference" onclick="fxForappCopy(\'' . \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(addslashes((string) ($fxOrep['id_order'] ?? ''))) . '\')" title="کپی کردن" style="direction:ltr;">' . \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOrep['id_order'] ?? $fxViewOrder)) . '</span>');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('آیدی کاربر', '<a class="text-link" href="user.php?id=' . urlencode((string) ($fxOcsp['id_user'] ?? '')) . '">' . \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOcsp['id_user'] ?? '')) . '</a>' . ($fxOuserLabel !== '' ? ' — ' . \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOuserLabel) : ''));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('نوع پرداخت', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_invoice_label') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_invoice_label((string) ($fxOrep['id_invoice'] ?? '')) : (string) ($fxOrep['id_invoice'] ?? '—')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('وضعیت پرداخت', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxOpayMeta));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('وضعیت تطبیق', $fxOmatchMeta !== null ? \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxOmatchMeta) : '—');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('اصل مبلغ', number_format((int) ($fxOcsp['principal_toman'] ?? 0)) . ' تومان');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('کارمزد شناسایی', number_format((int) ($fxOcsp['fee_toman'] ?? 0)) . ' تومان');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('مبلغ قابل پرداخت', number_format((int) ($fxOcsp['payable_toman'] ?? 0)) . ' تومان');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('مبلغ دریافتی', $fxOrderView['received_rial'] !== null ? number_format(intdiv((int) $fxOrderView['received_rial'], 10)) . ' تومان' : 'ثبت نشده');
                                $fxOdiff = $fxOrderView['diff_rial'];
                                $fxOdiffLabel = $fxOdiff === null ? '—' : (number_format(intdiv(abs((int) $fxOdiff), 10)) . ' تومان ' . ((int) $fxOdiff < 0 ? 'کمتر' : ((int) $fxOdiff > 0 ? 'بیشتر' : 'بدون اختلاف')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('اختلاف مبلغ', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOdiffLabel));
                                ?>
                            </div>
                            <div>
                                <?php
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('زمان ایجاد پرداخت', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) ($fxOcsp['created_at'] ?? 0), true) : '—'));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('مهلت پرداخت', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) ($fxOcsp['expires_at'] ?? 0), true) : '—'));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('زمان ارسال رسید', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappJdate($fxOrep['time'] ?? '')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('شناسه فاکتور', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOrep['id_invoice'] ?? '—')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('۴ رقم آخر کارت', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOrep['card_last4'] ?? '')) !== '' ? \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) $fxOrep['card_last4']) : '—');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('منبع پرداخت', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOrep['source'] ?? '') === 'miniapp' ? 'مینی‌اپ' : 'ربات'));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('واریز متصل', $fxOdepositLink);
                                if ($fxOdep !== null) {
                                    echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('مبلغ واریز', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_toman_from_rial') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_toman_from_rial(isset($fxOdep['amount_rial']) && $fxOdep['amount_rial'] !== null ? (int) $fxOdep['amount_rial'] : null) : '—'));
                                    echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('بانک / فرستنده', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOdep['bank'] ?? '—')) . ' / ' . \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOdep['sender'] ?? '—')));
                                    echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('زمان واریز', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappDepositTime($fxOdep), true) : '—'));
                                }
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('یادداشت / دلیل', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOrep['dec_not_confirmed'] ?? '')) !== '' ? \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) $fxOrep['dec_not_confirmed']) : '—');
                                ?>
                            </div>
                        </div>

                        <?php if ($fxOphoto): ?>
                            <div class="forapp-detail-section">
                                <h3 class="forapp-detail-section__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('receipt', 'svg-icon svg-sm'); ?> تصویر رسید ارسال‌شده</h3>
                                <a href="forapp.php?media=<?php echo urlencode((string) ($fxOrep['id_order'] ?? '')); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('eye', 'svg-icon svg-sm'); ?> مشاهده تصویر رسید</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($fxOcanDecide || $fxOcanReceived || $fxOcanCredit || $fxOcanCancel): ?>
                            <div class="forapp-actions-grid">
                                <?php if ($fxOcanDecide): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('تأیید و تسویه نهایی این پرداخت انجام شود؟');">
                                        <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="approve">
                                        <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-check', 'svg-icon svg-sm'); ?> تأیید و تسویه پرداخت</div>
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل تأیید دستی؛ در صورت نبود واریز منطبق الزامی است"></textarea>
                                        <button type="submit" class="btn btn-soft-success btn-sm">تایید نهایی</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanDecide): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('رد این پرداخت انجام شود؟');">
                                        <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="reject">
                                        <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="deposit_id" value="<?php echo (int) ($fxOcsp['deposit_id'] ?? 0); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?> رد پرداخت</div>
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل رد کردن الزامی است" required></textarea>
                                        <button type="submit" class="btn btn-soft-danger btn-sm">رد پرداخت</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanReceived): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('مبلغ دریافتی این پرداخت اصلاح شود؟');">
                                        <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="set_received">
                                        <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon svg-sm'); ?> اصلاح مبلغ دریافتی</div>
                                        <input type="text" name="received" class="form-control" inputmode="numeric" placeholder="مبلغ دریافتی به تومان" value="<?php echo $fxOrderView['received_rial'] !== null ? (int) intdiv((int) $fxOrderView['received_rial'], 10) : ''; ?>">
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل اصلاح مبلغ الزامی است" required></textarea>
                                        <button type="submit" class="btn btn-outline btn-sm">ثبت مبلغ دریافتی</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanCredit): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('مبلغ دریافتی به کیف پول کاربر واریز شود؟');">
                                        <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="credit">
                                        <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('wallet-plus', 'svg-icon svg-sm'); ?> واریز به کیف پول</div>
                                        <textarea name="reason" class="form-control" rows="2" placeholder="دلیل واریز کامل مبلغ دریافتی به کیف پول" required></textarea>
                                        <button type="submit" class="btn btn-primary btn-sm">واریز به کیف پول</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($fxOcanCancel): ?>
                                    <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('این پرداخت منقضی لغو و از فهرست پیشنهادها حذف شود؟');">
                                        <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                        <input type="hidden" name="_fx_action" value="cancel_expired">
                                        <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxViewOrder); ?>">
                                        <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderKeepQs); ?>">
                                        <div class="forapp-action__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm'); ?> لغو پرداخت منقضی</div>
                                        <p class="forapp-candidate-note" style="margin:0;">فقط همین پرداخت منقضی بسته می‌شود؛ سابقه مالی حذف نمی‌شود.</p>
                                        <button type="submit" class="btn btn-soft-danger btn-sm">لغو پرداخت منقضی</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="forapp-detail-section">
                            <h3 class="forapp-detail-section__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('link', 'svg-icon svg-sm'); ?> واریزهای بانکی پیشنهادی</h3>
                            <?php if (is_array($fxOrderCand) && !empty($fxOrderCand['blocked'])): ?>
                                <div class="alert alert-danger"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappErrorText((string) $fxOrderCand['blocked'])); ?></div>
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
                                            $fxOdepMeta = $fxDepositMeta[$fxOdepState] ?? ['label' => method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_deposit_state_label') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_deposit_state_label($fxOdepState) : $fxOdepState, 'class' => 'badge-gray'];
                                            $fxOlinkGuard = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_payment_link_guard') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_link_guard($fxViewOrder, (int) $fxOdepRow['id']) : 'unavailable';
                                            ?>
                                            <tr>
                                                <td data-label="#" data-summary="1"><?php echo \Faoxima\Admin\Web\Support\Pagination::fx_persian_num($fxOcBase + $fxOi); ?></td>
                                                <td data-label="شناسه واریز" data-summary="1"><span style="direction:ltr;display:inline-block;">#<?php echo (int) $fxOdepRow['id']; ?></span></td>
                                                <td data-label="مبلغ (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo number_format(intdiv($fxOdepAmountRial, 10)); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                                <td data-label="بانک / فرستنده"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOdepRow['bank'] ?? '—')); ?> / <?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxOdepRow['sender'] ?? '—')); ?></td>
                                                <td data-label="زمان واریز" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappDepositTime($fxOdepRow), true) : '—'); ?></span></td>
                                                <td data-label="اختلاف"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappDiffLabel($fxOdepDiff)); ?></td>
                                                <td data-label="وضعیت" data-summary="1"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxOdepMeta); ?></td>
                                                <td data-label="عملیات" class="cell-actions">
                                                    <?php if ($fxOlinkGuard === null): ?>
                                                        <form method="POST" action="forapp.php" style="display:inline-block;">
                                                            <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                                            <input type="hidden" name="_fx_action" value="link_deposit">
                                                            <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxViewOrder); ?>">
                                                            <input type="hidden" name="deposit_id" value="<?php echo (int) $fxOdepRow['id']; ?>">
                                                            <input type="hidden" name="cpage" value="<?php echo (int) $fxOrderCand['page']; ?>">
                                                            <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxOrderKeepQs); ?>">
                                                            <button type="submit" class="btn btn-soft-success btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('link', 'svg-icon svg-sm'); ?> اتصال</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray" title="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappErrorText((string) $fxOlinkGuard)); ?>">غیرقابل اتصال</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php
                                    $fxOrderCandKeep = array_merge($fxKeep, ['p' => (int) $fxPg['page'], 'order' => $fxViewOrder]);
                                    echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html((int) $fxOrderCand['page'] + 1, max(1, (int) $fxOrderCand['pages']), (int) $fxOrderCand['total'], 5, 'forapp.php', $fxOrderCandKeep, 'cpage');
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
                            <h2><?php echo \Faoxima\Admin\Web\Support\Icons::icon('atm-machine', 'svg-icon svg-sm'); ?> جزئیات واریز بانکی فوراپ</h2>
                            <p>بررسی واریز، پرداخت‌های احتمالی و اتصال امن به پرداخت.</p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDepositBack); ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-right', 'svg-icon svg-sm'); ?> بازگشت</a>
                            <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDepositSelf); ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('refresh', 'svg-icon svg-sm'); ?> بروزرسانی پیشنهادها</a>
                        </div>
                    </div>
                    <?php if ($fxDepositView === null): ?>
                        <div class="alert alert-danger">واریزی با این شناسه یافت نشد.</div>
                    <?php else:
                        $fxDstate = (string) ($fxDepositView['state'] ?? '');
                        $fxDmeta = $fxDepositMeta[$fxDstate] ?? ['label' => method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_deposit_state_label') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_deposit_state_label($fxDstate) : $fxDstate, 'class' => 'badge-gray'];
                        $fxDlinkedOrder = trim((string) ($fxDepositView['id_order'] ?? ''));
                        $fxDcanDelete = in_array($fxDstate, ['unmatched', 'ambiguous', 'late'], true) && $fxDlinkedOrder === '';
                        $fxDbody = (string) ($fxDepositView['body'] ?? '');
                        ?>
                        <div class="forapp-detail-grid">
                            <div>
                                <?php
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('شناسه واریز', '<span style="direction:ltr;">#' . (int) $fxDepositView['id'] . '</span>');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('مبلغ واریز', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_toman_from_rial') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_toman_from_rial(isset($fxDepositView['amount_rial']) && $fxDepositView['amount_rial'] !== null ? (int) $fxDepositView['amount_rial'] : null) : '—'));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('بانک', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDepositView['bank'] ?? '—')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('فرستنده', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDepositView['sender'] ?? '—')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('کد پیامک', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDepositView['code'] ?? '—')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('وضعیت تطبیق', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxDmeta));
                                ?>
                            </div>
                            <div>
                                <?php
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('زمان دریافت در دستگاه', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDepositView['received_at_raw'] ?? '—')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('زمان ثبت در سرور', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) ($fxDepositView['stored_at'] ?? 0), true) : '—'));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('دستگاه', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDepositView['device'] ?? '—')));
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('سفارش مرتبط', $fxDlinkedOrder !== '' ? '<a class="text-link" style="direction:ltr;" href="forapp.php?tab=receipts&order=' . urlencode($fxDlinkedOrder) . '">' . \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDlinkedOrder) . '</a>' : '—');
                                echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappField('یادداشت', \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDepositView['note'] ?? '')) !== '' ? \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) $fxDepositView['note']) : '—');
                                ?>
                            </div>
                        </div>
                        <?php if ($fxDbody !== ''): ?>
                            <div class="forapp-detail-section">
                                <h3 class="forapp-detail-section__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('message', 'svg-icon svg-sm'); ?> متن پیامک بانکی</h3>
                                <div class="forapp-deposit-body"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDbody); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($fxDcanDelete): ?>
                            <div class="forapp-actions-grid">
                                <form class="forapp-action" method="POST" action="forapp.php" onsubmit="return confirm('این واریز برای همیشه حذف شود؟');">
                                    <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                    <input type="hidden" name="_fx_action" value="delete_deposit">
                                    <input type="hidden" name="deposit_id" value="<?php echo (int) $fxDepositView['id']; ?>">
                                    <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDepositDeleteKeepQs); ?>">
                                    <div class="forapp-action__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm'); ?> حذف واریز</div>
                                    <p class="forapp-candidate-note" style="margin:0;">فقط واریزهای تطبیق‌نیافته و بدون اتصال قابل حذف هستند.</p>
                                    <button type="submit" class="btn btn-soft-danger btn-sm">حذف واریز</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="forapp-detail-section">
                            <h3 class="forapp-detail-section__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('link', 'svg-icon svg-sm'); ?> پرداخت‌های احتمالی</h3>
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
                                            $fxDcStatusMeta = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappPaymentBadgeMeta($fxDcStatus, (string) ($fxDcand['state'] ?? ''), !empty($fxDcand['candidate_expired']), (string) ($fxDcand['resolution'] ?? ''));
                                            $fxDcName = trim((string) ($fxDcand['namecustom'] ?? ''));
                                            $fxDcUsername = trim((string) ($fxDcand['username'] ?? ''));
                                            $fxDcUserLabel = ($fxDcName !== '' && $fxDcName !== 'none') ? $fxDcName : ($fxDcUsername !== '' ? '@' . $fxDcUsername : '');
                                            $fxDcLinkGuard = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_payment_link_guard') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_link_guard($fxDcOrder, (int) $fxDepositView['id']) : 'unavailable';
                                            $fxDcDeleteGuard = $fxDcExpired && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_expired_payment_delete_guard') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_expired_payment_delete_guard($fxDcOrder, null, null, (int) $fxDepositView['id']) : 'not_expired';
                                            $fxDcOrderLink = 'forapp.php?tab=receipts&order=' . urlencode($fxDcOrder);
                                            ?>
                                            <tr>
                                                <td data-label="#" data-summary="1"><?php echo \Faoxima\Admin\Web\Support\Pagination::fx_persian_num($fxDcBase + $fxDi); ?></td>
                                                <td data-label="کد سفارش" data-summary="1"><span class="track-id" style="direction:ltr;" onclick="fxForappCopy('<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(addslashes($fxDcOrder)); ?>')"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDcOrder); ?></span></td>
                                                <td data-label="کاربر"><span style="direction:ltr;display:inline-block;"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDcand['id_user'] ?? '')); ?></span><?php echo $fxDcUserLabel !== '' ? '<br><small style="color:var(--text-muted);">' . \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDcUserLabel) . '</small>' : ''; ?></td>
                                                <td data-label="نوع پرداخت"><span class="receipt-order-type"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_invoice_label') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_invoice_label((string) ($fxDcand['report_invoice'] ?? '')) : '—'); ?></span></td>
                                                <td data-label="قابل پرداخت (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo number_format(intdiv($fxDcPayable, 10)); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                                <td data-label="اختلاف"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappDiffLabel($fxDcDiff)); ?></td>
                                                <td data-label="ایجاد" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) ($fxDcand['created_at'] ?? 0), true) : '—'); ?></span></td>
                                                <td data-label="مهلت" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) ($fxDcand['expires_at'] ?? 0), true) : '—'); ?></span></td>
                                                <td data-label="وضعیت" data-summary="1"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxDcStatusMeta); ?><?php echo $fxDcExpired ? ' <span class="badge badge-warning">منقضی</span>' : ''; ?></td>
                                                <td data-label="عملیات" class="cell-actions">
                                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                        <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDcOrderLink); ?>" title="مشاهده پرداخت"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('eye', 'svg-icon svg-sm'); ?></a>
                                                        <?php if ($fxDcLinkGuard === null): ?>
                                                            <form method="POST" action="forapp.php" style="display:inline-block;" onsubmit="return confirm('این واریز به پرداخت انتخاب‌شده متصل شود؟');">
                                                                <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                                                <input type="hidden" name="_fx_action" value="link_payment">
                                                                <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDcOrder); ?>">
                                                                <input type="hidden" name="deposit_id" value="<?php echo (int) $fxDepositView['id']; ?>">
                                                                <input type="hidden" name="cpage" value="<?php echo (int) $fxDepositCand['page']; ?>">
                                                                <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDepositKeepQs); ?>">
                                                                <button type="submit" class="btn btn-soft-success btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('link', 'svg-icon svg-sm'); ?> اتصال</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($fxDcExpired && $fxDcDeleteGuard === null): ?>
                                                            <form method="POST" action="forapp.php" style="display:inline-block;" onsubmit="return confirm('این پرداخت منقضی لغو و از فهرست پیشنهادها حذف شود؟');">
                                                                <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                                                <input type="hidden" name="_fx_action" value="cancel_expired">
                                                                <input type="hidden" name="id_order" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDcOrder); ?>">
                                                                <input type="hidden" name="deposit_id" value="<?php echo (int) $fxDepositView['id']; ?>">
                                                                <input type="hidden" name="cpage" value="<?php echo (int) $fxDepositCand['page']; ?>">
                                                                <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDepositKeepQs); ?>">
                                                                <button type="submit" class="btn btn-soft-danger btn-sm"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm'); ?> لغو منقضی</button>
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
                                    echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html((int) $fxDepositCand['page'] + 1, max(1, (int) $fxDepositCand['pages']), (int) $fxDepositCand['total'], 5, 'forapp.php', $fxDepositCandKeep, 'cpage');
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
                    <span class="fx-filter-drawer__icon" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('filter', 'svg-icon'); ?></span>
                    <span class="fx-filter-drawer__text"><strong>جستجو و فیلترها</strong><small><?php echo $fxTab === 'deposits' ? 'جستجو، وضعیت واریز و بازهٔ زمانی' : 'جستجو، وضعیت پرداخت، تطبیق و بازهٔ زمانی'; ?></small></span>
                    <span class="fx-filter-drawer__action"><span class="fx-filter-drawer__show">نمایش فیلترها</span><span class="fx-filter-drawer__hide">بستن فیلترها</span></span>
                    <span class="fx-filter-drawer__chevron" aria-hidden="true"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chevron-down', 'svg-icon'); ?></span>
                </summary>

                <?php echo \Faoxima\Admin\Web\Support\SearchFilter::fx_search_ui('forapp.php', $fxQ, $fxKeepExcept(['q']), $fxSearchPlaceholder); ?>

                <?php echo \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_ui('forapp.php', $fxStatusOptions, $fxStatus, $fxKeepExcept(['status']), 'status', $fxTab === 'deposits' ? 'فیلتر وضعیت واریز' : 'فیلتر وضعیت پرداخت'); ?>

                <?php echo \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_filter_ui('forapp.php', $fxKeepExcept($fxExtraDrop), $fxExtraFields, $fxExtraCriteria, 'فیلترهای بیشتر'); ?>

                <?php $fxDateKeep = $fxKeepExcept(['ddays']); $fxDateClearQs = \Faoxima\Admin\Web\Support\Pagination::fx_qs($fxDateKeep, ['ddays' => null]); ?>
                <div class="card fx-date-filter fx-date-filter--days-only">
                    <div class="fx-date-filter__title">فیلتر بر اساس تاریخ <?php echo $fxTab === 'deposits' ? 'واریز' : 'ایجاد پرداخت'; ?></div>
                    <form method="GET" action="forapp.php" class="fx-date-filter__form">
                        <?php foreach ($fxDateKeep as $fxDateKey => $fxDateValue): ?>
                            <input type="hidden" name="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) $fxDateKey); ?>" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) $fxDateValue); ?>">
                        <?php endforeach; ?>
                        <div class="fx-date-filter__field">
                            <label for="fx-forapp-days">تعداد روز اخیر</label>
                            <input type="number" id="fx-forapp-days" name="ddays" min="1" inputmode="numeric" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($_GET['ddays'] ?? '')); ?>" onchange="this.form.submit()">
                        </div>
                    </form>
                    <?php if ($fxDf['active']): ?>
                        <div class="fx-date-filter__active">فیلتر فعال: <?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDf['label']); ?> — <a class="text-link" href="forapp.php?<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDateClearQs); ?>">حذف فیلتر</a></div>
                    <?php endif; ?>
                </div>
            </details>

            <?php if ($fxTab === 'receipts'): ?>
                <div class="card invoice-results">
                    <div class="invoice-results__head">
                        <div><h2>رسیدها و سفارش‌های فوراپ</h2><p>رسیدهای ارسال‌شده، وضعیت تطبیق و مبالغ پرداخت فوراپ</p></div>
                        <span class="invoice-page-count"><?php echo \Faoxima\Admin\Web\Support\Pagination::fx_persian_num(number_format((int) $fxPg['total'])); ?> ردیف<?php echo $fxFilterActive ? ' مطابق فیلتر' : ' ثبت‌شده'; ?></span>
                    </div>
                    <form method="POST" action="forapp.php" id="forapp-bulk-form">
                        <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                        <input type="hidden" name="_fx_action" value="bulk_cancel">
                        <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(http_build_query($fxKeep)); ?>">
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
                                    $fxRowExpired = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_expired_from_values') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_expired_from_values($fxRowState, $fxRowStatus, $fxRowResolution, (int) ($fxRow['expires_at'] ?? 0), (int) ($fxRow['direct_payment_done'] ?? 0)) : false;
                                    $fxRowPayMeta = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappPaymentBadgeMeta($fxRowStatus, $fxRowState, $fxRowExpired, $fxRowResolution);
                                    $fxRowMatchMeta = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappMatchBadgeMeta($fxRowStatus, $fxRowState, $fxRowExpired, $fxRowResolution);
                                    $fxRowName = trim((string) ($fxRow['namecustom'] ?? ''));
                                    $fxRowUsername = trim((string) ($fxRow['username'] ?? ''));
                                    $fxRowUserLabel = ($fxRowName !== '' && $fxRowName !== 'none') ? $fxRowName : ($fxRowUsername !== '' ? '@' . $fxRowUsername : '');
                                    $fxRowDeposit = (int) ($fxRow['deposit_id'] ?? 0);
                                    $fxRowDetail = 'forapp.php?' . \Faoxima\Admin\Web\Support\Pagination::fx_qs(array_merge($fxKeep, ['p' => (int) $fxPg['page']]), ['order' => (string) ($fxRow['id_order'] ?? ''), 'cpage' => null]);
                                    ?>
                                    <tr data-detail-row data-detail-title="پرداخت <?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxRow['id_order'] ?? '')); ?>">
                                        <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxRow['id_order'] ?? '')); ?>"></label></td>
                                        <td data-label="وضعیت" data-summary="1"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxRowPayMeta); ?></td>
                                        <td data-label="کاربر">
                                            <a href="user.php?id=<?php echo urlencode((string) ($fxRow['id_user'] ?? '')); ?>" class="text-link" style="direction:ltr;display:inline-block;"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxRow['id_user'] ?? '')); ?></a>
                                            <?php if ($fxRowUserLabel !== ''): ?><br><small style="color:var(--text-muted);"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxRowUserLabel); ?></small><?php endif; ?>
                                        </td>
                                        <td data-label="کد سفارش" data-summary="1">
                                            <span class="track-id invoice-reference" onclick="fxForappCopy('<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(addslashes((string) ($fxRow['id_order'] ?? ''))); ?>')" title="کپی کردن" style="direction:ltr;display:inline-block;"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxRow['id_order'] ?? '')); ?></span>
                                        </td>
                                        <td data-label="نوع پرداخت"><span class="receipt-order-type"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_invoice_label') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_invoice_label((string) ($fxRow['id_invoice'] ?? '')) : '—'); ?></span></td>
                                        <td data-label="مبالغ (تومان)">
                                            <div style="display:flex;flex-direction:column;gap:2px;">
                                                <span class="invoice-amount"><bdi><?php echo number_format((int) ($fxRow['principal_toman'] ?? 0)); ?></bdi><small class="currency-unit">تومان</small></span>
                                                <small style="color:var(--text-muted);">کارمزد: <?php echo number_format((int) ($fxRow['fee_toman'] ?? 0)); ?></small>
                                                <small style="color:var(--text-muted);">قابل پرداخت: <?php echo number_format((int) ($fxRow['payable_toman'] ?? 0)); ?></small>
                                            </div>
                                        </td>
                                        <td data-label="دریافتی (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo $fxRow['received_rial'] !== null ? number_format(intdiv((int) $fxRow['received_rial'], 10)) : '—'; ?></bdi><?php if ($fxRow['received_rial'] !== null): ?><small class="currency-unit">تومان</small><?php endif; ?></span></td>
                                        <td data-label="تطبیق" data-summary="1"><?php echo $fxRowMatchMeta !== null ? \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxRowMatchMeta) : '—'; ?></td>
                                        <td data-label="ایجاد" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) ($fxRow['csp_created_at'] ?? 0), true) : '—'); ?></span></td>
                                        <td data-label="مهلت" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) ($fxRow['expires_at'] ?? 0), true) : '—'); ?></span></td>
                                        <td data-label="رسید" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappJdate($fxRow['time'] ?? '')); ?></span></td>
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
                                            <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxRowDetail); ?>" title="جزئیات و مدیریت"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('eye', 'svg-icon svg-sm'); ?></a>
                                            <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxRowDetail); ?>" title="واریزها و تطبیق"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('link', 'svg-icon svg-sm'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($fxRows)): ?>
                                    <tr><td colspan="13" style="text-align:center;padding:24px;">رسیدی برای نمایش یافت نشد.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                            <?php echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html((int) $fxPg['page'], (int) $fxPg['pages'], (int) $fxPg['total'], count($fxRows), 'forapp.php', $fxPgKeep); ?>
                        </div>
                        <div style="padding:12px 0;">
                            <button type="submit" class="btn btn-soft-danger btn-sm js-forapp-bulk-delete-btn" style="display:none;" onclick="return confirm('پرداخت‌های انتخاب‌شده پس از بررسی شرایط به‌صورت ایمن لغو می‌شوند و سابقه مالی حفظ می‌شود. ادامه می‌دهید؟')">
                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm'); ?> حذف انتخاب‌شده‌ها
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card invoice-results">
                    <div class="invoice-results__head">
                        <div><h2>واریزی‌ها و تطبیق فوراپ</h2><p>واریزهای بانکی، وضعیت تطبیق و پرداخت‌های احتمالی</p></div>
                        <span class="invoice-page-count"><?php echo \Faoxima\Admin\Web\Support\Pagination::fx_persian_num(number_format((int) $fxDpg['total'])); ?> واریز<?php echo $fxFilterActive ? ' مطابق فیلتر' : ' ثبت‌شده'; ?></span>
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
                                $fxDRowMeta = $fxDepositMeta[$fxDRowState] ?? ['label' => method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_deposit_state_label') ? \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_deposit_state_label($fxDRowState) : $fxDRowState, 'class' => 'badge-gray'];
                                $fxDRowLinked = trim((string) ($fxDRow['id_order'] ?? ''));
                                if ($fxDRowLinked === '') $fxDRowLinked = trim((string) ($fxDRow['csp_order'] ?? ''));
                                $fxDRowDetail = 'forapp.php?' . \Faoxima\Admin\Web\Support\Pagination::fx_qs(array_merge($fxKeep, ['p' => (int) $fxDpg['page']]), ['deposit' => (int) ($fxDRow['id'] ?? 0), 'cpage' => null]);
                                $fxDRowCanDelete = in_array($fxDRowState, ['unmatched', 'ambiguous', 'late'], true) && $fxDRowLinked === '';
                                ?>
                                <tr data-detail-row data-detail-title="واریز <?php echo (int) ($fxDRow['id'] ?? 0); ?>">
                                    <td data-label="شناسه" data-summary="1"><span style="direction:ltr;display:inline-block;">#<?php echo (int) ($fxDRow['id'] ?? 0); ?></span></td>
                                    <td data-label="مبلغ (تومان)" data-summary="1"><span class="invoice-amount"><bdi><?php echo number_format(intdiv((int) ($fxDRow['amount_rial'] ?? 0), 10)); ?></bdi><small class="currency-unit">تومان</small></span></td>
                                    <td data-label="بانک / فرستنده"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDRow['bank'] ?? '—')); ?> / <?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH((string) ($fxDRow['sender'] ?? '—')); ?></td>
                                    <td data-label="زمان واریز" style="direction:ltr;text-align:right;"><span class="invoice-date"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH(method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_clock') ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock(\Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappDepositTime($fxDRow), true) : '—'); ?></span></td>
                                    <td data-label="وضعیت تطبیق" data-summary="1"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappBadge($fxDRowMeta); ?></td>
                                    <td data-label="سفارش مرتبط">
                                        <?php if ($fxDRowLinked !== ''): ?>
                                            <a class="text-link" style="direction:ltr;display:inline-block;" href="forapp.php?tab=receipts&order=<?php echo urlencode($fxDRowLinked); ?>"><?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDRowLinked); ?></a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="پرداخت احتمالی">
                                        <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDRowDetail); ?>"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('search', 'svg-icon svg-sm'); ?> بررسی</a>
                                    </td>
                                    <td data-label="عملیات" class="cell-actions">
                                        <a class="btn btn-outline btn-sm" href="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDRowDetail); ?>" title="جزئیات واریز"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('eye', 'svg-icon svg-sm'); ?></a>
                                        <?php if ($fxDRowCanDelete): ?>
                                            <form method="POST" action="forapp.php" style="display:inline-block;" onsubmit="return confirm('این واریز برای همیشه حذف شود؟');">
                                                <?php echo \Faoxima\Admin\Web\Support\Csrf::fx_csrf_field(); ?>
                                                <input type="hidden" name="_fx_action" value="delete_deposit">
                                                <input type="hidden" name="deposit_id" value="<?php echo (int) ($fxDRow['id'] ?? 0); ?>">
                                                <input type="hidden" name="_keep_qs" value="<?php echo \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappH($fxDepositDeleteKeepQs); ?>">
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="حذف واریز"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon svg-sm'); ?></button>
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
                        <?php echo \Faoxima\Admin\Web\Support\Pagination::fx_pager_html((int) $fxDpg['page'], (int) $fxDpg['pages'], (int) $fxDpg['total'], count($fxDRows), 'forapp.php', $fxDpgKeep); ?>
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
