<?php

namespace Faoxima\Payments\Gateways\Tonpay;

use PDO;
use Throwable;

final class PaymentOrders
{
    public static function fetchReport($runtimePdo, $orderId)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
              WHERE id_order = ? AND Payment_Method = 'tonpay' AND tonpay_gateway_mode = 'custom'
              LIMIT 1"
            );
            $stmt->execute([(string) $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            error_log('TonPay custom fetch report failed: ' . $e->getMessage());
            return null;
        }
        }

    public static function storeInvoice($runtimePdo, $orderId, array $invoice)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $now = time();
        $invoiceId = (string) ($invoice['invoice_id'] ?? '');
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET tonpay_gateway_mode = 'custom',
                    tonpay_invoice_id = ?,
                    tonpay_final_amount = ?,
                    tonpay_card_number = ?,
                    tonpay_card_name = ?,
                    tonpay_remote_status = ?,
                    tonpay_created_at = ?,
                    tonpay_receipt_available_at = ?,
                    tonpay_local_state = 'awaiting_payment',
                    tonpay_last_checked_at = ?
              WHERE id_order = ?"
            );
            $stmt->execute([
                $invoiceId,
                $invoice['final_amount'] ?? 0,
                $invoice['card_number'] ?? '',
                $invoice['card_name'] ?? '',
                ($invoice['status'] ?? '') !== '' ? $invoice['status'] : 'pending',
                $now,
                $now + TONPAY_CUSTOM_RECEIPT_WAIT_SECONDS,
                $now,
                (string) $orderId,
            ]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
            $existing = self::fetchReport($runtimePdo, $orderId);
            if ($existing !== null && (string) ($existing['tonpay_invoice_id'] ?? '') === $invoiceId) {
                return true;
            }
        } catch (Throwable $e) {
            error_log('TonPay custom store invoice failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET tonpay_gateway_mode = 'custom',
                    tonpay_invoice_id = ?,
                    tonpay_remote_status = ?,
                    tonpay_created_at = ?,
                    tonpay_local_state = 'awaiting_payment',
                    tonpay_last_checked_at = ?
              WHERE id_order = ?"
            );
            $stmt->execute([
                $invoiceId,
                ($invoice['status'] ?? '') !== '' ? $invoice['status'] : 'pending',
                $now,
                $now,
                (string) $orderId,
            ]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
            $existing = self::fetchReport($runtimePdo, $orderId);
            return $existing !== null && (string) ($existing['tonpay_invoice_id'] ?? '') === $invoiceId;
        } catch (Throwable $e) {
            error_log('TonPay custom store invoice recovery failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function cancelConfirmedPending($invoiceId)
    {
        $invoiceId = trim((string) $invoiceId);
        if ($invoiceId === '') {
            return ['confirmed' => false, 'verified' => false, 'status' => ''];
        }
        $check = \Faoxima\Payments\Gateways\Tonpay\CustomClient::checkInvoice($invoiceId);
        if (!is_array($check) || \Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($check)) {
            return ['confirmed' => false, 'verified' => false, 'status' => ''];
        }
        $statusCode = (int) ($check['status_code'] ?? 0);
        if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
            return ['confirmed' => false, 'verified' => false, 'status' => ''];
        }
        if (trim((string) ($check['invoice_id'] ?? $invoiceId)) !== $invoiceId) {
            return ['confirmed' => false, 'verified' => false, 'status' => ''];
        }
        $status = strtolower(trim((string) ($check['status'] ?? '')));
        if (in_array($status, ['canceled', 'cancelled'], true)) {
            return ['confirmed' => true, 'verified' => true, 'status' => $status];
        }
        if ($status !== 'pending') {
            return ['confirmed' => false, 'verified' => true, 'status' => $status];
        }
        \Faoxima\Payments\Gateways\Tonpay\CustomClient::cancelRequest($invoiceId);
        $verify = \Faoxima\Payments\Gateways\Tonpay\CustomClient::checkInvoice($invoiceId);
        if (!is_array($verify) || \Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($verify)) {
            return ['confirmed' => false, 'verified' => false, 'status' => 'pending'];
        }
        $verifyCode = (int) ($verify['status_code'] ?? 0);
        if ($verifyCode !== 0 && ($verifyCode < 200 || $verifyCode >= 300)) {
            return ['confirmed' => false, 'verified' => false, 'status' => 'pending'];
        }
        $verifyStatus = strtolower(trim((string) ($verify['status'] ?? '')));
        return [
            'confirmed' => in_array($verifyStatus, ['canceled', 'cancelled'], true),
            'verified' => true,
            'status' => $verifyStatus,
        ];
        }

    public static function recoverUnstoredInvoice($runtimePdo, $orderId, array $invoice)
    {
        $invoiceId = trim((string) ($invoice['invoice_id'] ?? ''));
        $result = ['saved' => false, 'remote_state' => 'unknown', 'invoice_id' => $invoiceId];
        if ($invoiceId === '') {
            return $result;
        }
        if (self::storeInvoice($runtimePdo, $orderId, $invoice)) {
            return ['saved' => true, 'remote_state' => 'stored', 'invoice_id' => $invoiceId];
        }
        $report = self::fetchReport($runtimePdo, $orderId);
        if ($report !== null && (string) ($report['tonpay_invoice_id'] ?? '') === $invoiceId) {
            return ['saved' => true, 'remote_state' => 'stored', 'invoice_id' => $invoiceId];
        }
        $check = \Faoxima\Payments\Gateways\Tonpay\CustomClient::checkInvoice($invoiceId);
        if (!is_array($check) || \Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($check)) {
            error_log('TonPay custom unstored invoice check ambiguous: ' . json_encode([
                'order_id' => (string) $orderId,
                'invoice_id' => $invoiceId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $result;
        }
        $statusCode = (int) ($check['status_code'] ?? 0);
        if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
            return $result;
        }
        if (trim((string) ($check['invoice_id'] ?? $invoiceId)) !== $invoiceId) {
            return $result;
        }
        $remote = strtolower(trim((string) ($check['status'] ?? '')));
        if ($remote === 'pending') {
            $cancel = self::cancelConfirmedPending($invoiceId);
            $result['remote_state'] = $cancel['confirmed'] ? 'cancelled' : 'unknown';
            if (!$cancel['confirmed']) {
                error_log('TonPay custom unstored invoice cancel unresolved: ' . json_encode([
                    'order_id' => (string) $orderId,
                    'invoice_id' => $invoiceId,
                    'verified' => $cancel['verified'],
                    'status' => $cancel['status'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            return $result;
        }
        $result['remote_state'] = $remote !== '' ? $remote : 'unknown';
        return $result;
        }

    public static function cardIsComplete($cardNumber)
    {
        return preg_match('/^\d{16}$/', preg_replace('/[\s\-]/', '', (string) $cardNumber)) === 1;
        }

    public static function buildState(array $report, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $price = (int) ($report['price'] ?? 0);
        $final = (int) ($report['tonpay_final_amount'] ?? 0);
        if ($final < $price) {
            $final = $price;
        }
        $createdAt = (int) ($report['tonpay_created_at'] ?? 0);
        $availableAt = (int) ($report['tonpay_receipt_available_at'] ?? 0);
        if ($availableAt <= 0 && $createdAt > 0) {
            $availableAt = $createdAt + TONPAY_CUSTOM_RECEIPT_WAIT_SECONDS;
        }
        $submittedAt = (int) ($report['tonpay_receipt_submitted_at'] ?? 0);
        $deadlineAt = (int) ($report['tonpay_review_deadline_at'] ?? 0);
        if ($submittedAt > 0 && $deadlineAt <= 0) {
            $deadlineAt = $submittedAt + TONPAY_CUSTOM_REVIEW_SECONDS;
        }
        $remote = strtolower(trim((string) ($report['tonpay_remote_status'] ?? '')));
        $localStatus = (string) ($report['payment_Status'] ?? '');
        $localState = (string) ($report['tonpay_local_state'] ?? '');
        $card = (string) ($report['tonpay_card_number'] ?? '');
        $changedAt = (int) ($report['tonpay_card_changed_at'] ?? 0);
        $exhausted = !empty($report['tonpay_change_card_exhausted']);
        $remainingAmount = (int) ($report['tonpay_remaining_amount'] ?? 0);
        $remainingInvoiceId = trim((string) ($report['tonpay_remaining_invoice_id'] ?? ''));
        $remainingCard = trim((string) ($report['tonpay_remaining_card_number'] ?? ''));
        $remainingHolder = trim((string) ($report['tonpay_remaining_card_holder'] ?? ''));
        $remainingReceiptReceived = !empty($report['tonpay_remaining_receipt_received']);
        $refundAmount = (int) ($report['tonpay_refund_amount'] ?? 0);
        $refundPending = !empty($report['tonpay_refund_pending']);
        $refundCardSubmittedAt = (int) ($report['tonpay_refund_card_submitted_at'] ?? 0);
        $showChange = array_key_exists('tonpay_show_change_card', $report) && $report['tonpay_show_change_card'] !== null
            ? (int) (bool) $report['tonpay_show_change_card']
            : null;
        $cooldownSeconds = array_key_exists('tonpay_change_card_cooldown_seconds', $report) && $report['tonpay_change_card_cooldown_seconds'] !== null
            ? max(0, (int) $report['tonpay_change_card_cooldown_seconds'])
            : null;
        $lastCheckedAt = (int) ($report['tonpay_last_checked_at'] ?? 0);

        if ($localStatus === 'paid') {
            $phase = 'paid';
        } elseif ($remote === 'completed') {
            $phase = 'confirming';
        } elseif ($remote === 'canceled' || $remote === 'cancelled') {
            $phase = 'canceled';
        } elseif ($remote === 'expired') {
            $phase = 'expired';
        } elseif ($remote === 'rejected') {
            $phase = ($localStatus === 'reject' && !($deadlineAt > 0 && $deadlineAt <= $now)) ? 'rejected' : (($deadlineAt > 0 && $deadlineAt <= $now) ? 'review_timeout' : 'receipt_rejected');
        } elseif ($remote === 'action_required_less') {
            $phase = 'action_required_less';
        } elseif ($remote === 'action_required_more') {
            $phase = 'action_required_more';
        } elseif ($remote === 'processing') {
            $phase = ($deadlineAt > 0 && $deadlineAt <= $now) ? 'review_timeout' : 'receipt_submitted';
        } elseif ($localStatus === 'cancelled') {
            $phase = $remote === 'pending' ? 'cancel_pending' : 'canceled';
        } elseif ($localStatus === 'reject') {
            $phase = ($deadlineAt > 0 && $deadlineAt <= $now) ? 'review_timeout' : 'rejected';
        } elseif ($localStatus === 'expire') {
            if ($localState === 'review_timeout' || ($submittedAt > 0 && $deadlineAt > 0 && $deadlineAt <= $now)) {
                $phase = 'review_timeout';
            } else {
                $phase = 'local_timeout';
            }
        } elseif ($remote === 'pending') {
            if ($submittedAt > 0) {
                $phase = ($deadlineAt > 0 && $deadlineAt <= $now) ? 'review_timeout' : 'receipt_submitted';
            } elseif ($createdAt > 0 && $createdAt + TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS <= $now) {
                $phase = 'local_timeout';
            } elseif ($availableAt > $now) {
                $phase = 'awaiting_payment';
            } else {
                $phase = 'receipt_available';
            }
        } elseif ($remote === '') {
            if ($createdAt > 0 && $createdAt + TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS <= $now) {
                $phase = 'local_timeout';
            } elseif ($availableAt > $now) {
                $phase = 'awaiting_payment';
            } else {
                $phase = 'receipt_available';
            }
        } else {
            $phase = 'unknown';
        }

        $open = in_array($phase, ['awaiting_payment', 'receipt_available'], true);
        $less = $phase === 'action_required_less';
        $more = $phase === 'action_required_more';
        $changeAvailableAt = $changedAt > 0 ? $changedAt + TONPAY_CUSTOM_CHANGE_CARD_COOLDOWN_SECONDS : 0;
        $changeWait = $cooldownSeconds !== null
            ? max(0, $cooldownSeconds - max(0, $now - $lastCheckedAt))
            : max(0, $changeAvailableAt - $now);
        $changeAllowed = $showChange !== null ? ($showChange === 1) : ($changeAvailableAt <= $now);
        $canSubmitReceipt = $phase === 'receipt_available'
            || ($less && $remainingInvoiceId !== '' && !$remainingReceiptReceived);
        $canCancel = $remote === 'pending'
            && $submittedAt <= 0
            && !in_array($phase, ['paid', 'confirming', 'canceled', 'rejected'], true);

        return [
            'order_id' => (string) ($report['id_order'] ?? ''),
            'invoice_id' => (string) ($report['tonpay_invoice_id'] ?? ''),
            'tracking_code' => (string) ($report['id_order'] ?? ''),
            'purchase_kind' => self::purchaseKind((string) ($report['id_invoice'] ?? '')),
            'phase' => $phase,
            'phase_label' => \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomPhaseLabel($phase),
            'follow_up' => in_array($phase, ['receipt_rejected', 'review_timeout', 'rejected', 'expired', 'local_timeout', 'action_required_less', 'action_required_more', 'unknown'], true),
            'remote_status' => $remote,
            'now' => $now,
            'request_amount' => $price,
            'final_amount' => $final,
            'fee' => $final - $price,
            'payable_rial' => $final * 10,
            'card_number' => $card,
            'card_name' => (string) ($report['tonpay_card_name'] ?? ''),
            'card_complete' => self::cardIsComplete($card),
            'remaining_amount' => $remainingAmount,
            'remaining_payable_rial' => $remainingAmount * 10,
            'remaining_invoice_id' => $remainingInvoiceId,
            'remaining_card_number' => $remainingCard,
            'remaining_card_holder' => $remainingHolder,
            'remaining_card_complete' => self::cardIsComplete($remainingCard),
            'remaining_receipt_received' => $remainingReceiptReceived,
            'refund_amount' => $refundAmount,
            'refund_pending' => $refundPending,
            'refund_card_submitted' => $refundCardSubmittedAt > 0,
            'can_refund' => $more && $refundAmount > 0 && $refundCardSubmittedAt <= 0,
            'show_change_card' => $showChange,
            'created_at' => $createdAt,
            'expires_at' => $submittedAt > 0 ? $deadlineAt : ($createdAt > 0 ? $createdAt + TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS : 0),
            'receipt_available_at' => $availableAt,
            'receipt_wait_remaining' => max(0, $availableAt - $now),
            'receipt_submitted_at' => $submittedAt,
            'review_deadline_at' => $deadlineAt,
            'review_remaining' => $deadlineAt > 0 ? max(0, $deadlineAt - $now) : 0,
            'can_submit_receipt' => $canSubmitReceipt,
            'can_change_card' => $open && !$exhausted && $changeAllowed && $changeWait <= 0,
            'change_card_wait' => $changeWait,
            'change_card_cooldown_seconds' => $changeWait,
            'change_card_exhausted' => $exhausted,
            'can_cancel' => $canCancel,
            'terminal' => in_array($phase, ['paid', 'rejected', 'canceled', 'expired', 'review_timeout'], true),
        ];
        }

    public static function purchaseKind($invoiceField)
    {
        $tag = strpos($invoiceField, '|') !== false ? trim((string) explode('|', $invoiceField, 2)[0]) : '';
        $kinds = [
            'getconfigafterpay' => 'خرید سرویس',
            'getextenduser' => 'تمدید سرویس',
            'getextratimeuser' => 'خرید زمان اضافه',
            'getextravolumeuser' => 'خرید حجم اضافه',
        ];
        return $kinds[$tag] ?? 'شارژ کیف پول';
        }

    public static function receiptGate(array $state)
    {
        if ($state['can_submit_receipt']) {
            return ['ok' => true, 'code' => 'ok', 'message' => ''];
        }
        if ($state['phase'] === 'awaiting_payment') {
            return [
                'ok' => false,
                'code' => 'too_early',
                'message' => self::waitMessage($state['receipt_wait_remaining']),
                'wait_sec' => $state['receipt_wait_remaining'],
            ];
        }
        if ($state['phase'] === 'action_required_less') {
            return ['ok' => false, 'code' => 'already_submitted', 'message' => 'رسید مبلغ باقی‌مانده قبلاً ارسال شده و در انتظار بررسی است.'];
        }
        if ($state['phase'] === 'action_required_more') {
            return ['ok' => false, 'code' => 'not_eligible', 'message' => 'برای این پرداخت باید کارت مقصد عودت مبلغ مازاد ثبت شود.'];
        }
        if ($state['phase'] === 'receipt_submitted') {
            return ['ok' => false, 'code' => 'already_submitted', 'message' => 'رسید شما قبلاً ارسال شده و در انتظار بررسی است.'];
        }
        if ($state['phase'] === 'paid' || $state['phase'] === 'confirming') {
            return ['ok' => false, 'code' => 'already_paid', 'message' => 'پرداخت شما تأیید شده است.'];
        }
        if ($state['phase'] === 'receipt_rejected') {
            return ['ok' => false, 'code' => 'receipt_rejected', 'message' => 'رسید شما در بررسی اولیه رد شده است و در حال پیگیری است. برای راهنمایی با پشتیبانی تماس بگیرید.'];
        }
        if ($state['phase'] === 'unknown') {
            return ['ok' => false, 'code' => 'not_eligible', 'message' => 'وضعیت این فاکتور هنوز از تون‌پی تأیید نشده است؛ ابتدا وضعیت را دوباره بررسی کنید.'];
        }
        return ['ok' => false, 'code' => 'not_eligible', 'message' => 'این فاکتور دیگر قابل ارسال رسید نیست.'];
        }

    public static function persianDigits($value)
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
        }

    public static function waitMessage($seconds)
    {
        $seconds = max(1, (int) $seconds);
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;
        if ($minutes > 0 && $rest > 0) {
            $label = $minutes . ' دقیقه و ' . $rest . ' ثانیه';
        } elseif ($minutes > 0) {
            $label = $minutes . ' دقیقه';
        } else {
            $label = $rest . ' ثانیه';
        }
        return self::persianDigits($label) . ' دیگر می‌توانید رسید پرداخت را ارسال کنید.';
        }
}
