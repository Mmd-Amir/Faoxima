<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;

final class ReviewQueries
{
    public static function confirmGuard($runtimePdo, string $orderId, string $reason): ?string
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if ($csp === null) {
            return null;
        }
        $report = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        if (\Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report)) {
            return trim($reason) !== '' ? null : 'verify_reason_required';
        }
        if (trim($reason) !== '') {
            return null;
        }
        if ($csp['deposit_id'] !== null && $csp['received_rial'] !== null && (int) $csp['received_rial'] === (int) $csp['payable_rial']) {
            return null;
        }
        return 'verify_reason_required';
    }

    public static function paymentRejectGuard($runtimePdo, string $orderId): ?string
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if ($csp === null) {
            return 'not_verify';
        }
        $report = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        if (!is_array($report)) {
            return 'not_found';
        }
        $status = (string) ($report['payment_Status'] ?? '');
        if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return 'already_paid';
        }
        if (in_array($status, ['processing', 'reconciling'], true)) {
            return 'processing';
        }
        if (in_array($status, ['reject', 'cancelled'], true)) {
            return 'already_final';
        }
        if (!in_array($status, ['Unpaid', 'waiting', 'expire'], true)) {
            return 'not_eligible';
        }
        $state = (string) ($csp['state'] ?? '');
        if (in_array($state, ['approved', 'credited'], true)) {
            return 'already_paid';
        }
        $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report);
        if (!in_array($state, ['open', 'matched', 'review'], true) && !($state === 'closed' && $expired)) {
            return 'not_eligible';
        }
        if (is_callable([\Faoxima\Payments\Fulfillment\Store::class, 'fetch'])) {
            try {
                $record = \Faoxima\Payments\Fulfillment\Store::fetch($runtimePdo, $orderId);
                if (is_array($record)) {
                    $recordState = (string) ($record['state'] ?? '');
                    if ($recordState === 'completed') {
                        return 'already_paid';
                    }
                    if (in_array($recordState, ['claimed', 'applying', 'reconciling'], true) && (int) ($record['lease_expires_at'] ?? 0) > time()) {
                        return 'processing';
                    }
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }

    public static function paymentLinkGuard($runtimePdo, string $orderId, int $depositId = 0): ?string
    {
        $csp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if ($csp === null) {
            return 'order_not_verify';
        }
        $report = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        if (!is_array($report)) {
            return 'not_found';
        }
        $status = (string) ($report['payment_Status'] ?? '');
        if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return 'already_paid';
        }
        if (in_array($status, ['processing', 'reconciling'], true)) {
            return 'processing';
        }
        $state = (string) ($csp['state'] ?? '');
        $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report);
        if ($state !== 'open' && !($expired && in_array($state, ['review', 'closed'], true))) {
            return 'order_state_' . $state;
        }
        if ($state === 'closed' && !$expired) {
            return 'order_state_closed';
        }
        if (!$expired && (int) ($csp['expires_at'] ?? 0) < time()) {
            return 'order_state_expired';
        }
        if ($csp['deposit_id'] !== null) {
            return 'order_has_deposit';
        }
        $allowed = $expired ? ['Unpaid', 'waiting', 'pending', 'expire'] : ['Unpaid', 'waiting', 'pending'];
        if (!in_array($status, $allowed, true)) {
            return 'not_eligible';
        }
        return null;
    }

    public static function reportCandidates($runtimePdo, int $depositId, int $limit = 8, int $page = 0, ?string $orderId = null): array
    {
        $deposit = \Faoxima\Payments\Receipts\ReceiptOrders::deposit($runtimePdo, $depositId);
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if (!is_array($deposit) || $pdo === null || $deposit['amount_rial'] === null) {
            return ['ok' => false, 'rows' => [], 'active' => [], 'expired' => [], 'has_more' => false];
        }
        $orderId = $orderId !== null ? trim($orderId) : '';
        $amountRial = (int) $deposit['amount_rial'];
        if ($amountRial <= 0) {
            return ['ok' => true, 'rows' => [], 'active' => [], 'expired' => [], 'has_more' => false];
        }
        $amountToman = intdiv($amountRial, 10);
        $storedAt = (int) $deposit['stored_at'];
        $eventAt = $storedAt;
        $receivedMs = $deposit['received_at_ms'] !== null ? (int) $deposit['received_at_ms'] : 0;
        if ($receivedMs > 0) {
            $eventSec = intdiv($receivedMs, 1000);
            if ($eventSec > 0 && $eventSec <= $storedAt + RX_SBV_DEVICE_SKEW && $eventSec >= $storedAt - 604800) {
                $eventAt = $eventSec;
            }
        }
        $rangeToman = \Faoxima\Payments\Receipts\Settings::suggestionRangeToman($runtimePdo);
        $rangeRial = $rangeToman * 10;
        $principalLow = max(0, $amountToman - $rangeToman);
        $principalHigh = $amountToman + $rangeToman;
        $payableLow = max(0, $amountRial - $rangeRial);
        $payableHigh = $amountRial + $rangeRial;
        $from = $eventAt - 86400;
        $to = $eventAt;
        $limit = max(1, min(30, $limit));
        $page = max(0, $page);
        $now = time();
        try {
            $base = " FROM card_sms_payment csp
                   JOIN Payment_report pr ON pr.id_order = csp.id_order
                   LEFT JOIN user u ON u.id = csp.id_user
                  WHERE csp.deposit_id IS NULL
                    AND csp.state IN ('open', 'review', 'closed')
                    AND (csp.state <> 'closed' OR csp.resolution IS NULL OR csp.resolution = '' OR csp.resolution = 'expired')
                    AND pr.payment_Status IN ('Unpaid', 'waiting', 'pending', 'expire')
                    AND COALESCE(pr.direct_payment_done, 0) = 0
                    AND csp.created_at BETWEEN ? AND ?
                    AND (csp.principal_toman BETWEEN ? AND ? OR csp.payable_rial BETWEEN ? AND ?)
                    AND NOT EXISTS (SELECT 1 FROM payment_fulfillment pf WHERE pf.id_order = csp.id_order AND (pf.state = 'completed' OR (pf.state IN ('claimed', 'applying', 'reconciling') AND COALESCE(pf.lease_expires_at, 0) > ?)))";
            if ($orderId !== '') {
                $base .= " AND csp.id_order = ?";
            }
            $params = [$from, $to, $principalLow, $principalHigh, $payableLow, $payableHigh, $now];
            if ($orderId !== '') {
                $params[] = $orderId;
            }
            $count = $pdo->prepare('SELECT COUNT(*)' . $base);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $pages = max(1, (int) ceil($total / $limit));
            $page = min($page, $pages - 1);
            $expiredRank = "CASE WHEN pr.payment_Status = 'expire' OR (csp.state = 'closed' AND csp.resolution = 'expired') OR (csp.state IN ('open', 'review', 'matched') AND csp.expires_at > 0 AND csp.expires_at < " . $now . ") THEN 1 ELSE 0 END";
            $stmt = $pdo->prepare(
                "SELECT csp.*, pr.payment_Status AS report_status, pr.id_invoice AS report_invoice, pr.time AS report_time, pr.source AS report_source, COALESCE(pr.direct_payment_done, 0) AS report_direct_done, u.username, u.namecustom"
                . $base . " ORDER BY " . $expiredRank . " ASC, CASE WHEN csp.principal_toman = ? THEN 0 WHEN csp.payable_rial = ? THEN 1 ELSE 2 END, LEAST(ABS(CAST(csp.principal_toman AS SIGNED) - CAST(? AS SIGNED)), ABS(CAST(csp.payable_rial AS SIGNED) - CAST(? AS SIGNED)) / 10) ASC, ABS(CAST(csp.created_at AS SIGNED) - CAST(? AS SIGNED)) ASC, csp.id DESC LIMIT " . $limit . " OFFSET " . ($page * $limit)
            );
            $stmt->execute(array_merge($params, [$amountToman, $amountRial, $amountToman, $amountRial, $eventAt]));
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasMore = $page + 1 < $pages;
            $active = [];
            $expired = [];
            foreach ($all as $row) {
                $rowExpired = \Faoxima\Payments\Receipts\ReceiptOrders::expiredFromValues(
                    (string) ($row['state'] ?? ''),
                    (string) ($row['report_status'] ?? ''),
                    (string) ($row['resolution'] ?? ''),
                    (int) ($row['expires_at'] ?? 0),
                    (int) ($row['report_direct_done'] ?? 0)
                );
                $row['candidate_expired'] = $rowExpired ? 1 : 0;
                if ($rowExpired) {
                    $expired[] = $row;
                } else {
                    $active[] = $row;
                }
            }
            return ['ok' => true, 'rows' => array_merge($active, $expired), 'active' => $active, 'expired' => $expired, 'has_more' => $hasMore, 'total' => $total, 'page' => $page, 'pages' => $pages];
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_CANDIDATE_SCAN_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            return ['ok' => false, 'rows' => [], 'active' => [], 'expired' => [], 'has_more' => false];
        }
    }

    public static function orderDepositCandidates($runtimePdo, string $orderId, int $limit = 5, int $page = 0, ?int $depositId = null): array
    {
        $orderId = trim($orderId);
        $per = max(1, min(5, $limit));
        $empty = ['ok' => false, 'rows' => [], 'has_more' => false, 'total' => 0, 'page' => 0, 'pages' => 1, 'per' => $per];
        if ($orderId === '') {
            return $empty;
        }
        $summary = \Faoxima\Payments\Receipts\Approval::orderSummary($runtimePdo, $orderId);
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($summary === null || $pdo === null) {
            return $empty;
        }
        $guard = self::paymentLinkGuard($runtimePdo, $orderId);
        if ($guard !== null) {
            $empty['ok'] = true;
            $empty['blocked'] = $guard;
            return $empty;
        }
        $csp = $summary['csp'];
        $rangeToman = \Faoxima\Payments\Receipts\Settings::suggestionRangeToman($runtimePdo);
        $rangeRial = $rangeToman * 10;
        $payable = (int) $csp['payable_rial'];
        $principal = (int) $csp['principal_toman'];
        $createdAt = (int) $csp['created_at'];
        $eventExpr = "CASE WHEN d.received_at_ms IS NOT NULL AND d.received_at_ms > 0 AND (d.received_at_ms / 1000) <= d.stored_at + " . RX_SBV_DEVICE_SKEW . " AND (d.received_at_ms / 1000) >= d.stored_at - 604800 THEN (d.received_at_ms / 1000) ELSE d.stored_at END";
        $base = " FROM card_sms_deposit d
                  WHERE d.state IN ('unmatched', 'ambiguous', 'late')
                    AND d.is_test = 0
                    AND d.is_deposit = 1
                    AND d.amount_rial IS NOT NULL
                    AND d.amount_rial > 0
                    AND NOT EXISTS (SELECT 1 FROM card_sms_payment csp2 WHERE csp2.deposit_id = d.id)
                    AND (d.amount_rial BETWEEN ? AND ? OR d.amount_rial BETWEEN ? AND ?)
                    AND " . $eventExpr . " BETWEEN ? AND ?";
        if ($depositId !== null) {
            $base .= " AND d.id = ?";
        }
        $params = [
            max(0, $payable - $rangeRial),
            $payable + $rangeRial,
            max(0, ($principal - $rangeToman) * 10),
            ($principal + $rangeToman) * 10,
            $createdAt,
            $createdAt + 86400,
        ];
        if ($depositId !== null) {
            $params[] = $depositId;
        }
        $page = max(0, $page);
        try {
            $count = $pdo->prepare('SELECT COUNT(*)' . $base);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $pages = max(1, (int) ceil($total / $per));
            $page = min($page, $pages - 1);
            $stmt = $pdo->prepare(
                "SELECT d.*" . $base . " ORDER BY CASE WHEN d.amount_rial = ? THEN 0 ELSE 1 END, ABS(CAST(d.amount_rial AS SIGNED) - CAST(? AS SIGNED)) ASC, " . $eventExpr . " ASC, d.id DESC LIMIT " . $per . " OFFSET " . ($page * $per)
            );
            $stmt->execute(array_merge($params, [$payable, $payable]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['ok' => true, 'rows' => $rows, 'has_more' => $page + 1 < $pages, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per];
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_ORDER_DEPOSIT_SCAN_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return $empty;
        }
    }
}
