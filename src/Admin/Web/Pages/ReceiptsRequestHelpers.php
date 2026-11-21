<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ReceiptsRequestHelpers
{
    public static function rxReceiptJdate($raw): string
    {
        $s = trim((string) $raw);
        if ($s === '') return '';
        $ts = ctype_digit($s) ? (int) $s : strtotime($s);
        if ($ts === false || $ts <= 0) return $s;
        return jdate('Y/m/d H:i:s', $ts, '', 'Asia/Tehran', 'fa');
    }

    public static function fx_receipts_send_media(string $mediaOrder, $APIKEY): void
{
    if (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_is_verify_order') && \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_is_verify_order($mediaOrder)) {
        http_response_code(404);
        exit;
    }
    $mediaReport = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptGet($mediaOrder, false);
    if (!$mediaReport) {
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

    public static function fx_receipts_delete_orders(array $orderIds): int
{
    $deletedCount = 0;
    foreach ($orderIds as $orderId) {
        if (\Faoxima\Payments\Receipts\ReceiptActions::rxReceiptHardDelete($orderId)) {
            $deletedCount++;
        }
    }
    return $deletedCount;
}

    public static function fx_receipts_bulk_result(array $post): array
{
    $rxRequestedIds = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_string_ids($post['ids'] ?? []);
    $rxDeletedCount = self::fx_receipts_delete_orders($rxRequestedIds);
    $rxRequestedCount = count($rxRequestedIds);
    if ($rxRequestedCount === 0) {
        $rxFlash = ['type' => 'err', 'text' => 'هیچ رسیدی انتخاب نشده بود.'];
    } elseif ($rxDeletedCount === 0) {
        $rxFlash = ['type' => 'err', 'text' => 'هیچ‌کدام از رسیدهای انتخاب‌شده قابل حذف نبودند.'];
    } elseif ($rxDeletedCount === $rxRequestedCount) {
        $rxFlash = ['type' => 'ok', 'text' => 'تعداد ' . number_format($rxDeletedCount) . ' رسید با موفقیت حذف شد.'];
    } else {
        $rxFlash = ['type' => 'ok', 'text' => 'از ' . number_format($rxRequestedCount) . ' رسید انتخاب‌شده، ' . number_format($rxDeletedCount) . ' مورد حذف شد و ' . number_format($rxRequestedCount - $rxDeletedCount) . ' مورد به‌دلیل تغییر وضعیت یا نامعتبر بودن حذف نشد.'];
    }
    return [$rxFlash, $rxDeletedCount];
}

    public static function fx_receipts_action(string $rxAction, string $rxOrderId, string $rxActorLabel, array $post): ?array
{
    $rxFlash = null;
    if ($rxOrderId === '') {
        $rxFlash = ['type' => 'err', 'text' => 'درخواست نامعتبر است.'];
    } elseif (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_is_verify_order') && \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_is_verify_order($rxOrderId)) {
        $rxFlash = ['type' => 'err', 'text' => 'این پرداخت از نوع فوراپ است و باید از بخش فوراپ مدیریت شود.'];
    } elseif ($rxAction === 'confirm') {
        $res = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptConfirm($rxOrderId, ['actor_label' => $rxActorLabel, 'override_reason' => trim((string) ($post['reason'] ?? ''))]);
        if ($res['ok']) {
            $rxFlash = ['type' => 'ok', 'text' => 'رسید با موفقیت تایید شد.'];
        } elseif (($res['reason'] ?? '') === 'verify_reason_required') {
            $rxFlash = ['type' => 'err', 'text' => 'این پرداخت با روش «تأیید رسید با بررسی» ایجاد شده و هیچ واریز دقیقاً منطبقی برای آن ثبت نشده است؛ برای تأیید دستی، دلیل را وارد کنید.'];
        } elseif (($res['reason'] ?? '') === 'fulfillment_failed') {
            $rxFlash = ['type' => 'err', 'text' => 'پرداخت به‌صورت نهایی تایید نشد و رسید برای بررسی مجدد به حالت در انتظار بازگشت.'];
        } elseif (in_array($res['reason'] ?? '', ['race_lost', 'already_paid', 'already_final', 'not_waiting'], true)) {
            $rxFlash = ['type' => 'err', 'text' => 'این رسید قبلاً بررسی شده یا وضعیت آن تغییر کرده است.'];
        } elseif (($res['reason'] ?? '') === 'purchase_receipts_pending') {
            $rxFlash = ['type' => 'err', 'text' => 'ابتدا رسیدهای خرید یا تمدید این کاربر را بررسی و تایید کنید.'];
        } else {
            $rxFlash = ['type' => 'err', 'text' => 'تکمیل عملیات با خطا مواجه شد، دوباره تلاش کنید.'];
        }
    } elseif ($rxAction === 'reject') {
        $rxReason = trim((string) ($post['reason'] ?? ''));
        if ($rxReason === '') {
            $rxFlash = ['type' => 'err', 'text' => 'برای رد کردن رسید باید دلیل را وارد کنید.'];
        } else {
            $res = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptReject($rxOrderId, $rxReason, ['actor_label' => $rxActorLabel]);
            $rxFlash = $res['ok']
                ? ['type' => 'ok', 'text' => 'رسید با موفقیت رد شد.']
                : ['type' => 'err', 'text' => 'این رسید قبلاً بررسی شده یا وضعیت آن تغییر کرده است.'];
        }
    } elseif ($rxAction === 'reopen') {
        $res = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptReopen($rxOrderId);
        $rxFlash = $res['ok']
            ? ['type' => 'ok', 'text' => 'رسید دوباره در انتظار بررسی قرار گرفت.']
            : ['type' => 'err', 'text' => 'این رسید قابل بازگردانی نیست.'];
    } elseif ($rxAction === 'delete') {
        $ok = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptHardDelete($rxOrderId);
        $rxFlash = $ok
            ? ['type' => 'ok', 'text' => 'رسید با موفقیت حذف شد.']
            : ['type' => 'err', 'text' => 'حذف رسید ممکن نشد.'];
    }
    return $rxFlash;
}

    public static function fx_receipts_filter(string $rxQ, array $rxDf, $rxStatus, array $rxStatusOptions, array $rxAf, string $rxType): array
{
    $rxWhereSql = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptListScopeSql();
    $rxWhereParams = [];
    if ($rxQ !== '') {
        $rxLike = '%' . $rxQ . '%';
        $rxWhereSql .= ' AND (id_order LIKE :l1 OR id_user LIKE :l2 OR card_last4 LIKE :l3)';
        $rxWhereParams[':l1'] = $rxLike;
        $rxWhereParams[':l2'] = $rxLike;
        $rxWhereParams[':l3'] = $rxLike;
    }
    $rxWhereSql .= \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_sql_mixed_named('time', $rxDf['from'], $rxDf['to'], $rxWhereParams, 'd');
    $rxWhereSql .= \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_sql('payment_Status', $rxStatus, $rxStatusOptions, $rxWhereParams, ':statusVal');
    $rxWhereSql .= \Faoxima\Admin\Web\Support\ExtraFilter::fx_amount_filter_sql('price', $rxAf, $rxWhereParams, 'am');
    if ($rxType === 'wallet') {
        $rxWhereSql .= " AND (id_invoice IS NULL OR CAST(SUBSTRING_INDEX(id_invoice, '|', 1) AS BINARY) NOT IN ('getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'))";
    } elseif ($rxType !== '') {
        $rxWhereSql .= " AND CAST(SUBSTRING_INDEX(id_invoice, '|', 1) AS BINARY) = :otype";
        $rxWhereParams[':otype'] = $rxType;
    }
    return [$rxWhereSql, $rxWhereParams];
}

    public static function fx_receipts_page(PDO $pdo, string $rxWhereSql, array $rxWhereParams): array
{
    $rxPg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM Payment_report WHERE $rxWhereSql", $rxWhereParams, 20);

    $rxListStmt = $pdo->prepare("SELECT * FROM Payment_report WHERE $rxWhereSql ORDER BY id DESC LIMIT :perPage OFFSET :offset");
    foreach ($rxWhereParams as $k => $v) $rxListStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $rxListStmt->bindValue(':perPage', $rxPg['perPage'], PDO::PARAM_INT);
    $rxListStmt->bindValue(':offset', $rxPg['offset'], PDO::PARAM_INT);
    $rxListStmt->execute();
    $rxRows = $rxListStmt->fetchAll(PDO::FETCH_ASSOC);
    return [$rxPg, $rxRows];
}

    public static function fx_receipts_summary(PDO $pdo): array
{
    $rxPendingCount = (int) $pdo->query("SELECT COUNT(*) FROM Payment_report WHERE " . \Faoxima\Payments\Receipts\ManualReview::receiptListScopeSql($pdo) . " AND payment_Status = 'waiting'")->fetchColumn();

    $rxTodayStart = strtotime(date('Y-m-d 00:00:00'));
    $rxConfirmedTodayStmt = $pdo->prepare(
        "SELECT COUNT(*), COALESCE(SUM(price),0) FROM Payment_report WHERE " . \Faoxima\Payments\Receipts\ManualReview::receiptListScopeSql($pdo) . " AND payment_Status = 'paid' "
        . "AND ((`time` REGEXP '^[0-9]+$' AND CAST(`time` AS UNSIGNED) >= :t1) OR (`time` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(`time`, '/', '-'), '%Y-%m-%d %H:%i:%s') >= FROM_UNIXTIME(:t2)))"
    );
    $rxConfirmedTodayStmt->execute([':t1' => $rxTodayStart, ':t2' => $rxTodayStart]);
    $rxConfirmedTodayRow = $rxConfirmedTodayStmt->fetch(PDO::FETCH_NUM);
    $rxConfirmedTodayCount = (int) ($rxConfirmedTodayRow[0] ?? 0);
    $rxConfirmedTodayAmount = (int) ($rxConfirmedTodayRow[1] ?? 0);

    $rxRejectedTodayStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM Payment_report WHERE " . \Faoxima\Payments\Receipts\ManualReview::receiptListScopeSql($pdo) . " AND payment_Status = 'reject' "
        . "AND ((`time` REGEXP '^[0-9]+$' AND CAST(`time` AS UNSIGNED) >= :t1) OR (`time` NOT REGEXP '^[0-9]+$' AND STR_TO_DATE(REPLACE(`time`, '/', '-'), '%Y-%m-%d %H:%i:%s') >= FROM_UNIXTIME(:t2)))"
    );
    $rxRejectedTodayStmt->execute([':t1' => $rxTodayStart, ':t2' => $rxTodayStart]);
    $rxRejectedTodayCount = (int) $rxRejectedTodayStmt->fetchColumn();
    return [$rxPendingCount, $rxConfirmedTodayCount, $rxConfirmedTodayAmount, $rxRejectedTodayCount];
}
}
