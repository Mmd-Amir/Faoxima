<?php

namespace Faoxima\Payments\Gateways\Tonpay;

use PDO;
use Throwable;

final class Settlement
{
    public static function updateColumns($runtimePdo, $orderId, array $columns, $onlyOpen = true)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || empty($columns)) {
            return 0;
        }
        $sets = [];
        $params = [];
        foreach ($columns as $name => $value) {
            $sets[] = $name . ' = ?';
            $params[] = $value;
        }
        $params[] = (string) $orderId;
        $sql = "UPDATE Payment_report SET " . implode(', ', $sets) . " WHERE id_order = ? AND tonpay_gateway_mode = 'custom'";
        if ($onlyOpen) {
            $sql .= " AND payment_Status IN ('Unpaid','pending','waiting')";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('TonPay custom update failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return 0;
        }
        }

    public static function applyRemote($runtimePdo, array $report, array $check)
    {
        $remote = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
        $columns = ['tonpay_last_checked_at' => time()];
        if ($remote !== '') {
            $columns['tonpay_remote_status'] = $remote;
        }
        if (isset($check['final_amount']) && is_numeric($check['final_amount']) && (int) $check['final_amount'] >= (int) ($report['price'] ?? 0)) {
            $columns['tonpay_final_amount'] = (int) $check['final_amount'];
        }
        $hasRemainderColumns = array_key_exists('tonpay_remaining_amount', $report);
        $hasRefundColumns = array_key_exists('tonpay_refund_amount', $report);
        $hasChangeCardColumns = array_key_exists('tonpay_show_change_card', $report);
        if ($hasRemainderColumns && array_key_exists('remaining_amount', $check)) {
            $columns['tonpay_remaining_amount'] = is_numeric($check['remaining_amount']) ? (int) $check['remaining_amount'] : null;
        }
        if ($hasRemainderColumns && array_key_exists('remaining_invoice_id', $check)) {
            $columns['tonpay_remaining_invoice_id'] = is_scalar($check['remaining_invoice_id']) && trim((string) $check['remaining_invoice_id']) !== ''
                ? trim((string) $check['remaining_invoice_id'])
                : null;
        }
        if ($hasRemainderColumns && array_key_exists('remaining_card_number', $check)) {
            $columns['tonpay_remaining_card_number'] = is_scalar($check['remaining_card_number']) && trim((string) $check['remaining_card_number']) !== ''
                ? substr(trim((string) $check['remaining_card_number']), 0, 40)
                : null;
        }
        if ($hasRemainderColumns && array_key_exists('remaining_card_holder', $check)) {
            $columns['tonpay_remaining_card_holder'] = is_scalar($check['remaining_card_holder']) && trim((string) $check['remaining_card_holder']) !== ''
                ? mb_substr(trim((string) $check['remaining_card_holder']), 0, 120)
                : null;
        }
        if ($hasRemainderColumns && array_key_exists('remaining_receipt_received', $check)) {
            $remainingReceived = !empty($check['remaining_receipt_received']) ? 1 : 0;
            if (!$remainingReceived && !empty($report['tonpay_remaining_receipt_received']) && $remote === 'action_required_less') {
                $remainingReceived = 1;
            }
            $columns['tonpay_remaining_receipt_received'] = $remainingReceived;
        }
        if ($hasRefundColumns && array_key_exists('refund_amount', $check)) {
            $columns['tonpay_refund_amount'] = is_numeric($check['refund_amount']) ? (int) $check['refund_amount'] : null;
        }
        if ($hasRefundColumns && array_key_exists('refund_pending', $check)) {
            $columns['tonpay_refund_pending'] = !empty($check['refund_pending']) ? 1 : 0;
        }
        if ($hasChangeCardColumns && array_key_exists('show_change_card', $check)) {
            $columns['tonpay_show_change_card'] = $check['show_change_card'] === null ? null : (int) (bool) $check['show_change_card'];
        }
        if ($hasChangeCardColumns && array_key_exists('change_card_cooldown_seconds', $check)) {
            $columns['tonpay_change_card_cooldown_seconds'] = is_numeric($check['change_card_cooldown_seconds'])
                ? max(0, (int) $check['change_card_cooldown_seconds'])
                : null;
        }
        if (array_key_exists('change_card_exhausted', $check)) {
            $columns['tonpay_change_card_exhausted'] = !empty($check['change_card_exhausted']) ? 1 : 0;
        }
        $localOpen = self::localStatusIsOpen($report);
        if (in_array($remote, ['processing', 'rejected'], true) && (int) ($report['tonpay_receipt_submitted_at'] ?? 0) <= 0) {
            $now = time();
            $columns['tonpay_receipt_submitted_at'] = $now;
            $columns['tonpay_review_deadline_at'] = $now + TONPAY_CUSTOM_REVIEW_SECONDS;
        }
        if ($localOpen) {
            if ($remote === 'processing') {
                $columns['tonpay_local_state'] = 'receipt_submitted';
            } elseif ($remote === 'action_required_less') {
                $columns['tonpay_local_state'] = !empty($check['remaining_receipt_received']) ? 'remaining_receipt_sent' : 'action_required_less';
            } elseif ($remote === 'action_required_more') {
                $columns['tonpay_local_state'] = 'action_required_more';
            } elseif ($remote !== '' && !in_array($remote, ['pending', 'completed', 'expired', 'canceled', 'cancelled', 'rejected'], true)) {
                $columns['tonpay_local_state'] = 'unverified';
            }
        }
        self::updateColumns($runtimePdo, $report['id_order'], $columns, false);
        if ($remote === 'rejected' && $localOpen && self::claimLocalState($runtimePdo, $report['id_order'], 'receipt_rejected')) {
            \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomNotifyReceiptRejected($report['id_order']);
        }
        }

    public static function localStatusIsOpen(array $report)
    {
        return in_array((string) ($report['payment_Status'] ?? ''), ['Unpaid', 'pending', 'waiting'], true);
        }

    public static function claimLocalState($runtimePdo, $orderId, $state)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET tonpay_local_state = ?
              WHERE id_order = ?
                AND tonpay_gateway_mode = 'custom'
                AND payment_Status IN ('Unpaid','pending','waiting')
                AND (tonpay_local_state IS NULL OR tonpay_local_state <> ?)"
            );
            $stmt->execute([$state, (string) $orderId, $state]);
            if ($stmt->rowCount() > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('TonPay custom claim state failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function fulfill($runtimePdo, $runtimeSetting, array $report, array $check)
    {
        $orderId = (string) $report['id_order'];
        if (empty($check['paid']) || strtolower((string) ($check['status'] ?? '')) !== 'completed') {
            return ['ok' => false, 'reason' => 'not completed'];
        }
        if (!\Faoxima\Payments\Gateways\Tonpay\Client::amountsMatch($check['request_amount'] ?? null, $report['price'] ?? null)) {
            \Faoxima\Payments\Gateways\Tonpay\Client::logAmountMismatch('custom', $orderId, $report['tonpay_invoice_id'] ?? '', $report['price'] ?? null, $check['request_amount'] ?? null);
            return ['ok' => false, 'reason' => 'amount mismatch'];
        }
        $price = (int) $report['price'];
        $final = isset($check['final_amount']) && is_numeric($check['final_amount']) ? (int) $check['final_amount'] : $price;
        if ($final < $price) {
            return ['ok' => false, 'reason' => 'final amount below request'];
        }
        $invoiceId = trim((string) ($report['tonpay_invoice_id'] ?? ''));
        if ($invoiceId === '' || (string) ($check['invoice_id'] ?? $invoiceId) !== $invoiceId || (string) ($check['order_id'] ?? $orderId) !== $orderId) {
            return ['ok' => false, 'reason' => 'identity mismatch'];
        }
        if (!method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_confirm_paid')) {
            require_once dirname(__DIR__, 4) . '/src/bootstrap.php';
        }
        $previousStatus = (string) ($report['payment_Status'] ?? '');
        if ($previousStatus === 'cancelled') {
            self::reopenCancelledForConfirmation($runtimePdo, $orderId);
        }
        $lines = [
            '🧾 شناسه فاکتور تون‌پی: ' . $invoiceId,
            '💸 مبلغ نهایی پرداخت‌شده: ' . number_format($final) . ' تومان',
            '🧾 کارمزد تون‌پی: ' . number_format($final - $price) . ' تومان',
            '🔐 درگاه کاستوم داخلی',
        ];
        if (in_array($previousStatus, ['expire', 'reject', 'cancelled'], true)) {
            $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($previousStatus === 'reject' ? 'رد اولیه رسید' : ($previousStatus === 'cancelled' ? 'لغو محلی فاکتور' : 'پایان مهلت محلی'));
        }
        $threadRow = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select");
        $result = \Faoxima\Payments\Settlement\Confirmation::confirmPaid($runtimePdo, $runtimeSetting, $orderId, 'chashbacktonpay', [
            'method' => 'tonpay',
            'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
            'extra_lines' => $lines,
        ]);
        if (!empty($result['ok'])) {
            self::updateColumns($runtimePdo, $orderId, ['tonpay_remote_status' => 'completed', 'tonpay_local_state' => 'completed'], false);
        }
        return $result;
        }

    public static function reopenCancelledForConfirmation($runtimePdo, $orderId)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET payment_Status = 'expire', tonpay_local_state = 'late_confirmation'
              WHERE id_order = ?
                AND tonpay_gateway_mode = 'custom'
                AND payment_Status = 'cancelled'"
            );
            $stmt->execute([(string) $orderId]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('TonPay custom reopen cancelled failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function pollOrder($runtimePdo, $runtimeSetting, array $report, $fulfill = true)
    {
        $invoiceId = trim((string) ($report['tonpay_invoice_id'] ?? ''));
        if ($invoiceId === '') {
            return ['state' => 'error'];
        }
        $check = \Faoxima\Payments\Gateways\Tonpay\CustomClient::checkInvoice($invoiceId);
        if (is_array($check) && !empty($check['local_rate_limited'])) {
            return ['state' => 'local_rate_limited'];
        }
        if (\Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($check)) {
            return ['state' => 'rate_limited'];
        }
        if (!is_array($check)) {
            return ['state' => 'error'];
        }
        $statusCode = (int) ($check['status_code'] ?? 0);
        if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
            return ['state' => 'error'];
        }
        if ((string) ($check['order_id'] ?? '') !== (string) $report['id_order']
            || (string) ($check['invoice_id'] ?? $invoiceId) !== $invoiceId) {
            error_log('TonPay custom check identity mismatch: ' . json_encode([
                'order_id' => (string) $report['id_order'],
                'invoice_id' => $invoiceId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return ['state' => 'error'];
        }

        self::applyRemote($runtimePdo, $report, $check);
        $remote = strtolower((string) ($check['status'] ?? ''));

        if ($remote === 'completed' && !empty($check['paid'])) {
            if (!$fulfill) {
                return ['state' => 'completed', 'status' => $remote];
            }
            $result = self::fulfill($runtimePdo, $runtimeSetting, $report, $check);
            return ['state' => 'completed', 'status' => $remote, 'fulfilled' => !empty($result['ok'])];
        }

        if ($remote === 'rejected') {
            return ['state' => 'open', 'status' => $remote];
        }

        if (\Faoxima\Payments\Gateways\Tonpay\Client::terminalLocalStatus($remote) !== null) {
            if (in_array((string) ($report['payment_Status'] ?? ''), ['expire', 'reject', 'cancelled'], true)) {
                self::updateColumns($runtimePdo, $report['id_order'], ['tonpay_local_state' => 'closed'], false);
            }
            $changed = \Faoxima\Payments\Gateways\Tonpay\Client::syncTerminalStatus($runtimePdo, $report['id_order'], $remote);
            if ($changed && function_exists('sendmessage')) {
                $notice = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomTerminalNotice($report, $remote === 'expired' ? 'expired' : 'canceled');
                if ($notice !== '') {
                    sendmessage($report['id_user'], $notice, \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomFollowUpKeyboard(\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState(\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $report['id_order']) ?? $report)), 'HTML');
                }
            }
            return ['state' => 'terminal', 'status' => $remote];
        }

        return ['state' => 'open', 'status' => $remote];
        }

    public static function isLateReconcilable(array $report)
    {
        return in_array((string) ($report['payment_Status'] ?? ''), ['expire', 'reject', 'cancelled'], true)
            && (string) ($report['tonpay_local_state'] ?? '') !== 'closed'
            && (int) ($report['tonpay_created_at'] ?? 0) >= time() - TONPAY_CUSTOM_LATE_RECONCILE_SECONDS;
        }

    public static function pollThrottled($runtimePdo, $runtimeSetting, $orderId, $userId)
    {
        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $userId) {
            return null;
        }
        $pollable = self::localStatusIsOpen($report) || self::isLateReconcilable($report);
        if ($pollable && time() - (int) ($report['tonpay_last_checked_at'] ?? 0) >= TONPAY_CUSTOM_POLL_THROTTLE_SECONDS) {
            self::pollOrder($runtimePdo, $runtimeSetting, $report, true);
            $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
        }
        return $report;
        }

    public static function changeCard($runtimePdo, $orderId, $userId)
    {
        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $userId) {
            return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        if (!$state['can_change_card']) {
            if (!in_array($state['phase'], ['awaiting_payment', 'receipt_available'], true)) {
                return ['ok' => false, 'message' => 'در این وضعیت امکان تعویض کارت وجود ندارد.'];
            }
            if ($state['change_card_exhausted']) {
                return ['ok' => false, 'message' => 'کارت دیگری برای تعویض باقی نمانده است.'];
            }
            if ($state['change_card_wait'] > 0) {
                return ['ok' => false, 'message' => 'لطفاً ' . $state['change_card_wait'] . ' ثانیه دیگر دوباره تلاش کنید.', 'wait_sec' => $state['change_card_wait']];
            }
            return ['ok' => false, 'message' => 'امکان تعویض کارت برای این فاکتور فعال نیست.'];
        }
        $decoded = \Faoxima\Payments\Gateways\Tonpay\CustomClient::changeCardRequest($report['tonpay_invoice_id']);
        if (is_array($decoded) && !empty($decoded['local_rate_limited'])) {
            return ['ok' => false, 'message' => 'سقف درخواست‌ها موقتاً پر است؛ کمی بعد تلاش کنید.'];
        }
        $invoice = \Faoxima\Payments\Gateways\Tonpay\CustomClient::normalizeInvoice($decoded);
        if ($invoice === null || $invoice['card_number'] === '') {
            $error = \Faoxima\Payments\Gateways\Tonpay\Client::normalizeError($decoded, 'تعویض کارت انجام نشد.');
            if (is_array($decoded) && isset($decoded['change_card_exhausted']) && !empty($decoded['change_card_exhausted'])) {
                self::updateColumns($runtimePdo, $orderId, ['tonpay_change_card_exhausted' => 1]);
            }
            return ['ok' => false, 'message' => $error['error'] !== '' ? $error['error'] : 'تعویض کارت انجام نشد.'];
        }
        if ($invoice['invoice_id'] !== (string) ($report['tonpay_invoice_id'] ?? '')
            || (isset($invoice['order_id']) && trim((string) $invoice['order_id']) !== '' && (string) $invoice['order_id'] !== (string) $orderId)
            || $invoice['request_amount'] !== (int) $report['price']) {
            return ['ok' => false, 'message' => 'پاسخ تون‌پی با فاکتور شما مطابقت ندارد.'];
        }
        $columns = [
            'tonpay_card_number' => $invoice['card_number'],
            'tonpay_card_name' => $invoice['card_name'],
            'tonpay_final_amount' => max($invoice['final_amount'], (int) $report['price']),
            'tonpay_card_changed_at' => time(),
            'tonpay_change_card_exhausted' => !empty($decoded['change_card_exhausted']) ? 1 : 0,
            'tonpay_last_checked_at' => time(),
        ];
        if (array_key_exists('tonpay_show_change_card', $report)) {
            if (array_key_exists('show_change_card', $decoded)) {
                $columns['tonpay_show_change_card'] = $decoded['show_change_card'] === null ? null : (int) (bool) $decoded['show_change_card'];
            }
            if (array_key_exists('change_card_cooldown_seconds', $decoded)) {
                $columns['tonpay_change_card_cooldown_seconds'] = is_numeric($decoded['change_card_cooldown_seconds'])
                    ? max(0, (int) $decoded['change_card_cooldown_seconds'])
                    : null;
            }
        }
        if ($invoice['status'] !== '') {
            $columns['tonpay_remote_status'] = $invoice['status'];
        }
        self::updateColumns($runtimePdo, $orderId, $columns);
        return ['ok' => true, 'report' => \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId)];
        }

    public static function validateReceiptFile($path)
    {
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            return ['ok' => false, 'message' => 'فایل رسید قابل خواندن نیست.'];
        }
        $size = (int) @filesize($path);
        if ($size <= 0) {
            return ['ok' => false, 'message' => 'فایل رسید خالی است.'];
        }
        if ($size > TONPAY_CUSTOM_RECEIPT_MAX_BYTES) {
            return ['ok' => false, 'message' => 'حجم رسید نباید بیشتر از ۱۰ مگابایت باشد.'];
        }
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) @finfo_file($finfo, $path);
                @finfo_close($finfo);
            }
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'message' => 'فقط تصویر رسید (JPG، PNG، WEBP یا GIF) پذیرفته می‌شود.'];
        }
        if (@getimagesize($path) === false) {
            return ['ok' => false, 'message' => 'محتوای فایل یک تصویر معتبر نیست.'];
        }
        return ['ok' => true, 'mime' => $mime, 'name' => 'receipt.' . $allowed[$mime]];
        }

    public static function submitReceipt($runtimePdo, $runtimeSetting, $orderId, $userId, $filePath)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'message' => 'اتصال پایگاه داده برقرار نیست.'];
        }
        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $userId) {
            return ['ok' => false, 'code' => 'not_found', 'message' => 'فاکتور پیدا نشد.'];
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        $gate = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::receiptGate($state);
        if (!$gate['ok']) {
            return ['ok' => false, 'code' => $gate['code'], 'message' => $gate['message'], 'wait_sec' => $gate['wait_sec'] ?? null];
        }
        $file = self::validateReceiptFile($filePath);
        if (!$file['ok']) {
            return ['ok' => false, 'code' => 'invalid_file', 'message' => $file['message']];
        }

        $lockName = 'tonpay_custom_receipt_' . substr(md5((string) $orderId), 0, 24);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            return ['ok' => false, 'code' => 'busy', 'message' => 'ارسال رسید در حال انجام است؛ چند لحظه صبر کنید.'];
        }
        try {
            $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
            $state = $report === null ? null : \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
            if ($state === null || !\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::receiptGate($state)['ok']) {
                return ['ok' => false, 'code' => 'already_submitted', 'message' => 'رسید شما قبلاً ارسال شده یا فاکتور دیگر قابل ارسال رسید نیست.'];
            }
            if ($state['phase'] === 'action_required_less') {
                $remainingInvoiceId = trim((string) ($state['remaining_invoice_id'] ?? ''));
                $remainingAmount = (int) ($state['remaining_amount'] ?? 0);
                if ($remainingInvoiceId === '' || $remainingAmount <= 0) {
                    return ['ok' => false, 'code' => 'not_eligible', 'message' => 'اطلاعات مبلغ باقی‌مانده از تون‌پی دریافت نشده است؛ کمی بعد دوباره تلاش کنید.'];
                }
                $decoded = \Faoxima\Payments\Gateways\Tonpay\CustomClient::receiptRequest($remainingInvoiceId, $filePath, $file['mime'], $file['name']);
                if (is_array($decoded) && (!empty($decoded['local_rate_limited']) || \Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($decoded))) {
                    return ['ok' => false, 'code' => 'rate_limited', 'message' => 'سقف درخواست‌های تون‌پی موقتاً پر است؛ کمی بعد دوباره تلاش کنید.'];
                }
                $invoice = \Faoxima\Payments\Gateways\Tonpay\CustomClient::normalizeInvoice($decoded);
                $received = is_array($decoded) && !empty($decoded['receipt_received']);
                if ($invoice === null || !$received || $invoice['status'] !== 'processing') {
                    $error = \Faoxima\Payments\Gateways\Tonpay\Client::normalizeError($decoded, 'ارسال رسید مبلغ باقی‌مانده به تون‌پی ناموفق بود.');
                    $messages = [
                        'INVALID_RECEIPT_TYPE' => 'فایل ارسالی تصویر معتبر نیست.',
                        'RECEIPT_TOO_LARGE' => 'حجم رسید بیش از حد مجاز است.',
                        'INVOICE_NOT_FOUND' => 'فاکتور در تون‌پی پیدا نشد.',
                        'NOT_PENDING' => 'فاکتور مبلغ باقی‌مانده در وضعیت قابل ارسال رسید نیست.',
                        'REMAINDER_INVOICE_MISSING' => 'فاکتور مبلغ باقی‌مانده برای این پرداخت باز نیست.',
                    ];
                    return [
                        'ok' => false,
                        'code' => $error['error_code'] !== '' ? strtolower($error['error_code']) : 'upstream_error',
                        'message' => $messages[$error['error_code']] ?? ($error['error'] !== '' ? $error['error'] : 'ارسال رسید مبلغ باقی‌مانده ناموفق بود.'),
                    ];
                }
                if ($invoice['invoice_id'] !== $remainingInvoiceId
                    || !\Faoxima\Payments\Gateways\Tonpay\Client::amountsMatch($invoice['request_amount'], $remainingAmount)
                    || !\Faoxima\Payments\Gateways\Tonpay\Client::amountsMatch($invoice['final_amount'], $remainingAmount)) {
                    return ['ok' => false, 'code' => 'mismatch', 'message' => 'پاسخ تون‌پی با مبلغ باقی‌مانده این فاکتور مطابقت ندارد.'];
                }
                $now = time();
                $saved = self::updateColumns($runtimePdo, $orderId, [
                    'tonpay_remaining_receipt_received' => 1,
                    'tonpay_remaining_receipt_at' => $now,
                    'tonpay_local_state' => 'remaining_receipt_sent',
                    'tonpay_last_checked_at' => $now,
                ], false);
                $updated = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
                if ($saved <= 0 && empty($updated['tonpay_remaining_receipt_received'])) {
                    return ['ok' => false, 'code' => 'local_save_failed', 'message' => 'رسید به تون‌پی ارسال شد، اما ثبت وضعیت آن در سیستم انجام نشد. پیش از ارسال مجدد، وضعیت پرداخت را بررسی کنید.'];
                }
                return ['ok' => true, 'report' => $updated];
            }
            $decoded = \Faoxima\Payments\Gateways\Tonpay\CustomClient::receiptRequest($report['tonpay_invoice_id'], $filePath, $file['mime'], $file['name']);
            if (is_array($decoded) && (!empty($decoded['local_rate_limited']) || \Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($decoded))) {
                return ['ok' => false, 'code' => 'rate_limited', 'message' => 'سقف درخواست‌های تون‌پی موقتاً پر است؛ کمی بعد دوباره تلاش کنید.'];
            }
            $invoice = \Faoxima\Payments\Gateways\Tonpay\CustomClient::normalizeInvoice($decoded);
            $received = is_array($decoded) && !empty($decoded['receipt_received']);
            if ($invoice === null || !$received) {
                $error = \Faoxima\Payments\Gateways\Tonpay\Client::normalizeError($decoded, 'ارسال رسید به تون‌پی ناموفق بود.');
                $messages = [
                    'INVALID_RECEIPT_TYPE' => 'فایل ارسالی تصویر معتبر نیست.',
                    'RECEIPT_TOO_LARGE' => 'حجم رسید بیش از حد مجاز است.',
                    'INVOICE_NOT_FOUND' => 'فاکتور در تون‌پی پیدا نشد.',
                ];
                return [
                    'ok' => false,
                    'code' => $error['error_code'] !== '' ? strtolower($error['error_code']) : 'upstream_error',
                    'message' => $messages[$error['error_code']] ?? ($error['error'] !== '' ? $error['error'] : 'ارسال رسید به تون‌پی ناموفق بود.'),
                ];
            }
            if ($invoice['invoice_id'] !== (string) ($report['tonpay_invoice_id'] ?? '')
                || (isset($invoice['order_id']) && trim((string) $invoice['order_id']) !== '' && (string) $invoice['order_id'] !== (string) $orderId)
                || $invoice['request_amount'] !== (int) $report['price']) {
                return ['ok' => false, 'code' => 'mismatch', 'message' => 'پاسخ تون‌پی با فاکتور شما مطابقت ندارد.'];
            }
            $now = time();
            $submittedColumns = [
                'tonpay_remote_status' => $invoice['status'] !== '' ? $invoice['status'] : 'processing',
                'tonpay_final_amount' => max($invoice['final_amount'], (int) $report['price']),
                'tonpay_local_state' => 'receipt_submitted',
            ];
            if ((int) ($report['tonpay_receipt_submitted_at'] ?? 0) <= 0) {
                $submittedColumns['tonpay_receipt_submitted_at'] = $now;
                $submittedColumns['tonpay_review_deadline_at'] = $now + TONPAY_CUSTOM_REVIEW_SECONDS;
            }
            self::updateColumns($runtimePdo, $orderId, $submittedColumns, false);
            $updated = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
            if (!empty($decoded['paid']) && $invoice['status'] === 'completed') {
                self::fulfill($runtimePdo, $runtimeSetting, $updated, $decoded);
                $updated = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $updated;
            }
            return ['ok' => true, 'report' => $updated];
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
        }

    public static function cancel($runtimePdo, $runtimeSetting, $orderId, $userId, $telegram = false)
    {
        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $userId) {
            return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        if ($state['phase'] === 'canceled') {
            if (in_array($state['remote_status'], ['canceled', 'cancelled'], true)) {
                return ['ok' => true, 'already_canceled' => true];
            }
            if ($state['remote_status'] === 'completed') {
                self::pollOrder($runtimePdo, $runtimeSetting, $report, true);
                return ['ok' => false, 'message' => 'تون‌پی این پرداخت را تکمیل شده اعلام کرد و فاکتور لغو نشد.'];
            }
        }
        $localCanceledNeedsCheck = $state['phase'] === 'canceled'
            && in_array($state['remote_status'], ['pending', ''], true);
        if (!$state['can_cancel'] && !$localCanceledNeedsCheck) {
            return ['ok' => false, 'message' => \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancelBlockedMessage($state, $telegram)];
        }
        $poll = self::pollOrder($runtimePdo, $runtimeSetting, $report, true);
        if (in_array($poll['state'], ['error', 'rate_limited', 'local_rate_limited'], true)) {
            return ['ok' => false, 'message' => 'در حال حاضر امکان بررسی وضعیت پرداخت در تون‌پی وجود ندارد؛ کمی بعد دوباره تلاش کنید.'];
        }
        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null) {
            return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        if ($state['phase'] === 'canceled') {
            if (in_array($state['remote_status'], ['canceled', 'cancelled'], true)) {
                return ['ok' => true, 'already_canceled' => true];
            }
            if ($state['remote_status'] === '') {
                return ['ok' => false, 'message' => 'وضعیت فاکتور از تون‌پی تأیید نشد؛ لغو به‌صورت محلی ثبت شده اما وضعیت راه دور نامشخص است. کمی بعد وضعیت را دوباره بررسی کنید.'];
            }
        }
        $localCanceledNeedsCheck = $state['phase'] === 'canceled'
            && in_array($state['remote_status'], ['pending', ''], true);
        if (!$state['can_cancel'] && !$localCanceledNeedsCheck) {
            return ['ok' => false, 'message' => \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancelBlockedMessage($state, $telegram)];
        }
        $invoiceId = trim((string) ($report['tonpay_invoice_id'] ?? ''));
        if ($invoiceId === '') {
            return ['ok' => false, 'message' => 'شناسه فاکتور تون‌پی برای لغو در دسترس نیست.'];
        }
        $decoded = \Faoxima\Payments\Gateways\Tonpay\CustomClient::cancelRequest($invoiceId);
        if (is_array($decoded) && !empty($decoded['local_rate_limited'])) {
            return ['ok' => false, 'message' => 'سقف درخواست‌های تون‌پی موقتاً پر است؛ کمی بعد دوباره تلاش کنید.'];
        }
        $error = \Faoxima\Payments\Gateways\Tonpay\Client::normalizeError($decoded, 'لغو فاکتور در تون‌پی تأیید نشد.');
        $check = \Faoxima\Payments\Gateways\Tonpay\CustomClient::checkInvoice($invoiceId);
        if (!is_array($check) || \Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($check)) {
            return ['ok' => false, 'message' => 'نتیجه لغو فاکتور از تون‌پی تأیید نشد؛ وضعیت را دوباره بررسی کنید.'];
        }
        $statusCode = (int) ($check['status_code'] ?? 0);
        if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
            return ['ok' => false, 'message' => 'نتیجه لغو فاکتور از تون‌پی تأیید نشد؛ وضعیت را دوباره بررسی کنید.'];
        }
        if (trim((string) ($check['invoice_id'] ?? $invoiceId)) !== $invoiceId) {
            return ['ok' => false, 'message' => 'پاسخ تون‌پی با فاکتور شما مطابقت ندارد.'];
        }
        $remote = strtolower(trim((string) ($check['status'] ?? '')));
        if (in_array($remote, ['canceled', 'cancelled'], true)) {
            $changed = self::updateColumns($runtimePdo, $orderId, [
                'payment_Status' => 'cancelled',
                'tonpay_local_state' => 'canceled',
                'tonpay_remote_status' => 'canceled',
                'tonpay_last_checked_at' => time(),
            ], false);
            if ($changed > 0) {
                return ['ok' => true, 'canceled' => true];
            }
            $after = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
            if ($after !== null && (string) ($after['payment_Status'] ?? '') === 'cancelled') {
                return ['ok' => true, 'already_canceled' => true];
            }
            return ['ok' => false, 'message' => 'لغو در تون‌پی انجام شد اما ثبت محلی آن تأیید نشد؛ وضعیت را دوباره بررسی کنید.'];
        }
        if ($remote === 'completed') {
            self::pollOrder($runtimePdo, $runtimeSetting, $report, true);
            return ['ok' => false, 'message' => 'تون‌پی این پرداخت را تکمیل شده اعلام کرد و فاکتور لغو نشد.'];
        }
        self::applyRemote($runtimePdo, $report, $check);
        if (\Faoxima\Payments\Gateways\Tonpay\Client::terminalLocalStatus($remote) !== null) {
            \Faoxima\Payments\Gateways\Tonpay\Client::syncTerminalStatus($runtimePdo, $orderId, $remote);
        }
        $updated = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
        $updatedState = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($updated);
        if ($remote === 'pending') {
            $message = $error['error'] !== '' ? $error['error'] : 'لغو فاکتور توسط تون‌پی تأیید نشد؛ وضعیت را دوباره بررسی کنید.';
            return ['ok' => false, 'message' => $message];
        }
        return ['ok' => false, 'message' => \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancelBlockedMessage($updatedState, $telegram)];
        }

    public static function submitRefund($runtimePdo, $runtimeSetting, $orderId, $userId, $cardNumber, $cardHolder)
    {
        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $userId) {
            return ['ok' => false, 'code' => 'not_found', 'message' => 'فاکتور پیدا نشد.'];
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        if ($state['phase'] !== 'action_required_more') {
            return ['ok' => false, 'code' => 'not_eligible', 'message' => 'ثبت کارت عودت فقط برای پرداخت مبلغ بیشتر مجاز است.'];
        }
        if ((int) ($report['tonpay_refund_card_submitted_at'] ?? 0) > 0) {
            return [
                'ok' => true,
                'already_open' => true,
                'report' => $report,
                'message' => 'کارت مقصد عودت قبلاً ثبت شده است و مبلغ مازاد پس از بررسی تون‌پی واریز می‌شود.',
            ];
        }
        $cardNumber = preg_replace('/[\s\-]/', '', (string) $cardNumber);
        $cardHolder = trim((string) $cardHolder);
        if (!\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::cardIsComplete($cardNumber)) {
            return ['ok' => false, 'code' => 'invalid_refund_card', 'message' => 'شماره کارت باید ۱۶ رقم باشد.'];
        }
        if ($cardHolder === '' || mb_strlen($cardHolder) < 3 || mb_strlen($cardHolder) > 120) {
            return ['ok' => false, 'code' => 'invalid_refund_holder', 'message' => 'نام صاحب کارت را کامل وارد کنید.'];
        }
        $invoiceId = trim((string) ($report['tonpay_invoice_id'] ?? ''));
        if ($invoiceId === '') {
            return ['ok' => false, 'code' => 'not_found', 'message' => 'شناسه فاکتور تون‌پی در دسترس نیست.'];
        }
        $decoded = \Faoxima\Payments\Gateways\Tonpay\CustomClient::refundRequest($invoiceId, $cardNumber, $cardHolder);
        if (is_array($decoded) && !empty($decoded['local_rate_limited'])) {
            return ['ok' => false, 'code' => 'rate_limited', 'message' => 'سقف درخواست‌های تون‌پی موقتاً پر است؛ کمی بعد دوباره تلاش کنید.'];
        }
        $statusCode = is_array($decoded) ? (int) ($decoded['status_code'] ?? 0) : 0;
        $success = is_array($decoded) && ($statusCode === 0 || ($statusCode >= 200 && $statusCode < 300));
        $error = $success ? null : \Faoxima\Payments\Gateways\Tonpay\Client::normalizeError($decoded, 'ثبت کارت عودت در تون‌پی ناموفق بود.');
        if (!$success && $error['error_code'] === 'REFUND_ALREADY_OPEN') {
            self::updateColumns($runtimePdo, $orderId, [
                'tonpay_refund_card_submitted_at' => (int) ($report['tonpay_refund_card_submitted_at'] ?? 0) > 0 ? (int) $report['tonpay_refund_card_submitted_at'] : time(),
                'tonpay_last_checked_at' => time(),
            ], false);
            self::pollThrottled($runtimePdo, $runtimeSetting, $orderId, $userId);
            $updated = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
            return [
                'ok' => true,
                'already_open' => true,
                'report' => $updated,
                'message' => 'درخواست عودت مبلغ مازاد قبلاً ثبت شده است و در انتظار واریز است.',
            ];
        }
        if (!$success) {
            $messages = [
                'REFUND_NOT_ALLOWED' => 'عودت مبلغ فقط برای پرداخت مبلغ بیشتر مجاز است.',
                'REFUND_AMOUNT_UNKNOWN' => 'مبلغ مازاد در تون‌پی ثبت نشده است؛ کمی بعد دوباره تلاش کنید.',
                'INVALID_REFUND_CARD' => 'شماره کارت واردشده معتبر نیست.',
                'INVALID_REFUND_HOLDER' => 'نام صاحب کارت معتبر نیست.',
                'INVOICE_ID_MISMATCH' => 'شناسه فاکتور ارسالی با فاکتور تون‌پی مطابقت ندارد.',
                'INVOICE_NOT_FOUND' => 'فاکتور در تون‌پی پیدا نشد.',
            ];
            return [
                'ok' => false,
                'code' => $error['error_code'] !== '' ? strtolower($error['error_code']) : 'upstream_error',
                'message' => $messages[$error['error_code']] ?? ($error['error'] !== '' ? $error['error'] : 'ثبت کارت عودت ناموفق بود.'),
            ];
        }
        if (isset($decoded['invoice_id']) && trim((string) $decoded['invoice_id']) !== $invoiceId) {
            return ['ok' => false, 'code' => 'mismatch', 'message' => 'پاسخ تون‌پی با فاکتور شما مطابقت ندارد.'];
        }
        $columns = [
            'tonpay_refund_card_submitted_at' => time(),
            'tonpay_last_checked_at' => time(),
        ];
        if (array_key_exists('refund_amount', $decoded)) {
            $columns['tonpay_refund_amount'] = is_numeric($decoded['refund_amount']) ? (int) $decoded['refund_amount'] : (int) ($state['refund_amount'] ?? 0);
        }
        if (array_key_exists('refund_pending', $decoded)) {
            $columns['tonpay_refund_pending'] = !empty($decoded['refund_pending']) ? 1 : 0;
        }
        if (isset($decoded['status']) && is_scalar($decoded['status']) && trim((string) $decoded['status']) !== '') {
            $columns['tonpay_remote_status'] = strtolower(trim((string) $decoded['status']));
        }
        self::updateColumns($runtimePdo, $orderId, $columns, false);
        $updated = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
        self::pollOrder($runtimePdo, $runtimeSetting, $updated, true);
        $updated = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $updated;
        return [
            'ok' => true,
            'report' => $updated,
            'message' => 'کارت مقصد عودت ثبت شد. مبلغ مازاد پس از بررسی تون‌پی به این کارت واریز می‌شود.',
        ];
        }

    public static function purgeProtected($runtimePdo, $orderId)
    {
        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null) {
            return false;
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        if ($state['receipt_submitted_at'] > 0 && $state['review_deadline_at'] > time()) {
            return true;
        }
        return $state['created_at'] > 0 && $state['created_at'] + TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS > time();
        }

    public static function expireOverdue($runtimePdo, $limit = 50)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return 0;
        }
        $now = time();
        $expired = 0;
        try {
            $stmt = $pdo->prepare(
                "SELECT id_order, id_user, tonpay_receipt_submitted_at, tonpay_remote_status
               FROM Payment_report
              WHERE Payment_Method = 'tonpay'
                AND tonpay_gateway_mode = 'custom'
                AND payment_Status = 'Unpaid'
                AND (
                    (tonpay_receipt_submitted_at IS NOT NULL AND tonpay_receipt_submitted_at > 0 AND tonpay_review_deadline_at < ?)
                    OR ((tonpay_receipt_submitted_at IS NULL OR tonpay_receipt_submitted_at = 0) AND tonpay_created_at < ?)
                )
              ORDER BY id ASC
              LIMIT " . (int) $limit
            );
            $stmt->execute([$now, $now - TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('TonPay custom overdue select failed: ' . $e->getMessage());
            return 0;
        }
        foreach ($rows as $row) {
            $remoteStatus = strtolower(trim((string) ($row['tonpay_remote_status'] ?? '')));
            if ($remoteStatus !== '' && !in_array($remoteStatus, ['rejected'], true)) {
                continue;
            }
            $hadReceipt = (int) ($row['tonpay_receipt_submitted_at'] ?? 0) > 0;
            $changed = self::updateColumns($runtimePdo, $row['id_order'], [
                'payment_Status' => 'expire',
                'tonpay_local_state' => $hadReceipt ? 'review_timeout' : 'local_timeout',
            ]);
            if ($changed > 0) {
                $expired++;
                $expiredReport = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $row['id_order']);
                if (function_exists('sendmessage') && $expiredReport !== null) {
                    $expiredState = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($expiredReport);
                    $expiredState['phase'] = $hadReceipt ? 'review_timeout' : 'local_timeout';
                    sendmessage(
                        $row['id_user'],
                        \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomStatusText($expiredState, true),
                        \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomFollowUpKeyboard($expiredState),
                        'HTML'
                    );
                }
            }
        }
        return $expired;
        }

    public static function publicView(array $report)
    {
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        $state['card_number_display'] = $state['card_complete']
            ? trim(chunk_split(preg_replace('/[\s\-]/', '', $state['card_number']), 4, ' '))
            : $state['card_number'];
        $state['card_copy_value'] = $state['card_complete'] ? preg_replace('/[\s\-]/', '', $state['card_number']) : '';
        $state['amount_copy_value'] = (string) $state['payable_rial'];
        $state['remaining_card_number_display'] = $state['remaining_card_complete']
            ? trim(chunk_split(preg_replace('/[\s\-]/', '', $state['remaining_card_number']), 4, ' '))
            : $state['remaining_card_number'];
        $state['remaining_card_copy_value'] = $state['remaining_card_complete'] ? preg_replace('/[\s\-]/', '', $state['remaining_card_number']) : '';
        $state['remaining_amount_copy_value'] = (string) $state['remaining_payable_rial'];
        $state['wallet_credit'] = $state['request_amount'];
        $state['messages'] = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomPhaseMessages($state);
        $state['button_styles'] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayButtonStyleMap();
        $state['gateway_title'] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayGatewayHeading();
        unset($state['fee'], $state['final_amount']);
        return $state;
        }

    public static function userPending($runtimePdo, $userId, $limit = 10)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
              WHERE id_user = ?
                AND Payment_Method = 'tonpay'
                AND tonpay_gateway_mode = 'custom'
                AND tonpay_invoice_id IS NOT NULL AND tonpay_invoice_id <> ''
                AND (
                    payment_Status IN ('Unpaid','pending','waiting')
                    OR (
                        payment_Status IN ('expire','reject')
                        AND (tonpay_local_state IS NULL OR tonpay_local_state <> 'closed')
                        AND tonpay_created_at >= ?
                        AND ((tonpay_receipt_submitted_at IS NOT NULL AND tonpay_receipt_submitted_at > 0) OR tonpay_created_at >= ?)
                    )
                    OR (payment_Status = 'cancelled' AND tonpay_remote_status = 'pending')
                )
              ORDER BY id DESC
              LIMIT " . max(1, (int) $limit)
            );
            $stmt->execute([(string) $userId, $now - TONPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - 172800]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('TonPay custom user pending failed: ' . $e->getMessage());
            return [];
        }
        }
}
