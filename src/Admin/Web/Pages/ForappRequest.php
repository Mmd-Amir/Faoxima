<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $APIKEY, $pdo.
 * Inherited or exported by reference: $f, $fxApprovedToday, $fxDRows, $fxDepositBack, $fxDepositCand, $fxDepositDeleteKeepQs, $fxDepositKeepQs, $fxDepositMeta, $fxDepositSelf, $fxDepositView, $fxDf, $fxDpg, $fxDpgKeep, $fxExtraCriteria, $fxExtraDrop, $fxExtraFields, $fxFilterActive, $fxFlash, $fxKeep, $fxKeepExcept, $fxOrderBack, $fxOrderCand, $fxOrderKeepQs, $fxOrderSelf, $fxOrderUser, $fxOrderView, $fxPendingCount, $fxPg, $fxPgKeep, $fxQ, $fxRejectedToday, $fxRows, $fxSchemaReady, $fxSearchPlaceholder, $fxStatus, $fxStatusOptions, $fxTab, $fxTabDepositsQs, $fxTabReceiptsQs, $fxTotalRecords, $fxUnmatchedDeposits, $fxViewDeposit, $fxViewOrder, $query.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ForappRequest
{
    public static function handle(
        $APIKEY,
        &$f,
        &$fxApprovedToday,
        &$fxDRows,
        &$fxDepositBack,
        &$fxDepositCand,
        &$fxDepositDeleteKeepQs,
        &$fxDepositKeepQs,
        &$fxDepositMeta,
        &$fxDepositSelf,
        &$fxDepositView,
        &$fxDf,
        &$fxDpg,
        &$fxDpgKeep,
        &$fxExtraCriteria,
        &$fxExtraDrop,
        &$fxExtraFields,
        &$fxFilterActive,
        &$fxFlash,
        &$fxKeep,
        &$fxKeepExcept,
        &$fxOrderBack,
        &$fxOrderCand,
        &$fxOrderKeepQs,
        &$fxOrderSelf,
        &$fxOrderUser,
        &$fxOrderView,
        &$fxPendingCount,
        &$fxPg,
        &$fxPgKeep,
        &$fxQ,
        &$fxRejectedToday,
        &$fxRows,
        &$fxSchemaReady,
        &$fxSearchPlaceholder,
        &$fxStatus,
        &$fxStatusOptions,
        &$fxTab,
        &$fxTabDepositsQs,
        &$fxTabReceiptsQs,
        &$fxTotalRecords,
        &$fxUnmatchedDeposits,
        &$fxViewDeposit,
        &$fxViewOrder,
        $pdo,
        &$query
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);

        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();

        if (isset($_GET['media']) && $_GET['media'] !== '') {
            $mediaOrder = (string) $_GET['media'];
            if (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_is_verify_order') || !\Faoxima\Payments\Receipts\ReceiptOrders::isVerifyOrder($pdo, $mediaOrder)) {
                http_response_code(404);
                exit;
            }
            $mediaReport = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($pdo, $mediaOrder);
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










        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_fx_action'])) {
            [$fxFlash, $fxCancelledCount] = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappRunAction($_POST, (string) ($_SESSION['user'] ?? ''));
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

        $fxQ = \Faoxima\Admin\Web\Support\SearchFilter::fx_search_current();
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
        $fxStatus = \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_current();
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
        $fxMatch = \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('mstate', 20);
        if (!isset($fxMatchOptions[$fxMatch])) $fxMatch = '';

        $fxTypeOptions = [
            'getconfigafterpay' => 'خرید سرویس',
            'getextenduser' => 'تمدید سرویس',
            'getextravolumeuser' => 'افزایش حجم',
            'getextratimeuser' => 'افزایش زمان',
            'wallet' => 'شارژ کیف پول',
        ];
        $fxType = \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('otype', 40);
        if (!isset($fxTypeOptions[$fxType])) $fxType = '';

        $fxUserId = \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('uid', 64);
        $fxOrderFilter = \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('oid', 64);
        $fxDepositFilter = \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('did', 20);
        if ($fxDepositFilter !== '' && !ctype_digit($fxDepositFilter)) $fxDepositFilter = '';
        $fxLife = \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('life', 10);
        if (!in_array($fxLife, ['active', 'expired'], true)) $fxLife = '';
        if ($fxTab === 'deposits') {
            $fxMatch = '';
            $fxType = '';
            $fxLife = '';
        }
        $fxDf = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve();

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

        $fxSchemaReady = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_has_schema') ? \Faoxima\Payments\Receipts\Settings::hasSchema($pdo) : false;
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
                $fxPendingCount = (int) $pdo->query("SELECT COUNT(*) FROM Payment_report WHERE " . (is_callable([\Faoxima\Payments\Receipts\ManualReview::class, 'forappScopeSql']) ? \Faoxima\Payments\Receipts\ManualReview::forappScopeSql($pdo) : '1 = 0') . " AND payment_Status = 'waiting'")->fetchColumn();
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
        [$fxRFrom, $fxRWhere, $fxRParams] = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappReceiptQuery($fxSchemaReady, $fxReportFilters);

        if ($fxSchemaReady && $fxTab === 'receipts') {
            [$fxPg, $fxRows] = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappReceiptPage($pdo, $fxRFrom, $fxRWhere, $fxRParams);
        } else {
            $fxPg = ['page' => 1, 'perPage' => 20, 'offset' => 0, 'total' => 0, 'pages' => 1];
            $fxRows = [];
        }

        [$fxDFrom, $fxDWhere, $fxDParams] = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappDepositQuery($fxSchemaReady, $fxReportFilters);

        if ($fxSchemaReady && $fxTab === 'deposits') {
            [$fxDpg, $fxDRows] = \Faoxima\Admin\Web\Pages\ForappRequestHelpers::fxForappDepositPage($pdo, $fxDFrom, $fxDWhere, $fxDParams);
        } else {
            $fxDpg = ['page' => 1, 'perPage' => 20, 'offset' => 0, 'total' => 0, 'pages' => 1];
            $fxDRows = [];
        }

        $fxViewOrder = $fxTab === 'receipts' ? \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('order', 64) : '';
        $fxOrderCpage = max(0, (int) ($_GET['cpage'] ?? 0));
        $fxOrderView = null;
        $fxOrderCand = null;
        $fxOrderUser = null;
        if ($fxViewOrder !== '' && $fxSchemaReady) {
            $fxOrderView = \Faoxima\Payments\Receipts\Approval::orderSummary($pdo, $fxViewOrder);
            if ($fxOrderView !== null) {
                $fxOrderCand = \Faoxima\Payments\Receipts\ReviewQueries::orderDepositCandidates($pdo, $fxViewOrder, 5, $fxOrderCpage);
                $fxOrderUserStmt = $pdo->prepare("SELECT id, username, namecustom FROM user WHERE id = ? LIMIT 1");
                $fxOrderUserStmt->execute([(string) $fxOrderView['csp']['id_user']]);
                $fxOrderUser = $fxOrderUserStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        $fxViewDeposit = $fxTab === 'deposits' ? \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('deposit', 20) : '';
        $fxDepositCpage = max(0, (int) ($_GET['cpage'] ?? 0));
        $fxDepositView = null;
        $fxDepositCand = null;
        if ($fxViewDeposit !== '' && ctype_digit($fxViewDeposit) && $fxSchemaReady) {
            $fxDepositView = \Faoxima\Payments\Receipts\ReceiptOrders::deposit($pdo, (int) $fxViewDeposit);
            if ($fxDepositView !== null) {
                $fxDepositCand = \Faoxima\Payments\Receipts\ReviewQueries::reportCandidates($pdo, (int) $fxViewDeposit, 5, $fxDepositCpage);
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
        $fxOrderBack = 'forapp.php?' . \Faoxima\Admin\Web\Support\Pagination::fx_qs(array_merge($fxKeep, ['p' => (int) $fxPg['page']]), ['order' => null, 'cpage' => null]);
        $fxOrderSelf = 'forapp.php?' . \Faoxima\Admin\Web\Support\Pagination::fx_qs($fxOrderKeepArr, []);
        $fxDepositBack = 'forapp.php?' . \Faoxima\Admin\Web\Support\Pagination::fx_qs(array_merge($fxKeep, ['p' => (int) $fxDpg['page']]), ['deposit' => null, 'cpage' => null]);
        $fxDepositSelf = 'forapp.php?' . \Faoxima\Admin\Web\Support\Pagination::fx_qs($fxDepositKeepArr, []);
        $fxDepositDeleteKeepQs = http_build_query(array_merge($fxKeep, ['p' => (int) $fxDpg['page']]));

        return true;
    }
}
