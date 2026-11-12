<?php

require_once __DIR__ . '/tonpay_templates.php';

const TONPAY_CUSTOM_BASE_URL = 'https://tonpays.online/api/custom/v1';
const TONPAY_CUSTOM_RECEIPT_WAIT_SECONDS = 600;
const TONPAY_CUSTOM_REVIEW_SECONDS = 86400;
const TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS = 86400;
const TONPAY_CUSTOM_LATE_RECONCILE_SECONDS = 1209600;
const TONPAY_CUSTOM_RECEIPT_MAX_BYTES = 10485760;
const TONPAY_CUSTOM_POLL_THROTTLE_SECONDS = 8;
const TONPAY_CUSTOM_CHANGE_CARD_COOLDOWN_SECONDS = 60;

function tonpayCustomApiKey()
{
    $row = select("PaySetting", "ValuePay", "NamePay", "tonpay_custom_api_key", "select");
    return is_array($row) ? trim((string) ($row['ValuePay'] ?? '')) : '';
}

function tonpayCustomSaveApiKey($apiKey)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return false;
    }
    try {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?");
        $exists->execute(['tonpay_custom_api_key']);
        if ((int) $exists->fetchColumn() > 0) {
            $stmt = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?");
        } else {
            $stmt = $pdo->prepare("INSERT INTO PaySetting (ValuePay, NamePay) VALUES (?, ?)");
        }
        $stmt->execute([trim((string) $apiKey), 'tonpay_custom_api_key']);
        if (function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('PaySetting', 'NamePay', 'tonpay_custom_api_key');
        }
        return true;
    } catch (Throwable $e) {
        error_log('TonPay custom api key save failed: ' . $e->getMessage());
        return false;
    }
}

function tonpayCustomError($code, $message, $statusCode = 0)
{
    return [
        'success' => false,
        'error_code' => (string) $code,
        'error' => (string) $message,
        'status_code' => (int) $statusCode,
        'rate_limited' => (string) $code === 'RATE_LIMIT_EXCEEDED',
    ];
}

function tonpayCustomNormalizeInvoice($decoded)
{
    if (!is_array($decoded)) {
        return null;
    }
    $statusCode = (int) ($decoded['status_code'] ?? 0);
    if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
        return null;
    }
    $invoiceId = is_scalar($decoded['invoice_id'] ?? null) ? trim((string) $decoded['invoice_id']) : '';
    if ($invoiceId === '' || !is_numeric($decoded['request_amount'] ?? null) || !is_numeric($decoded['final_amount'] ?? null)) {
        return null;
    }
    $decoded['invoice_id'] = $invoiceId;
    $decoded['request_amount'] = (int) $decoded['request_amount'];
    $decoded['final_amount'] = (int) $decoded['final_amount'];
    $decoded['status'] = strtolower(trim(is_scalar($decoded['status'] ?? null) ? (string) $decoded['status'] : ''));
    $decoded['card_number'] = isset($decoded['card_number']) && is_scalar($decoded['card_number']) ? substr(trim((string) $decoded['card_number']), 0, 40) : '';
    $decoded['card_name'] = isset($decoded['card_name']) && is_scalar($decoded['card_name']) ? mb_substr(trim((string) $decoded['card_name']), 0, 120) : '';
    return $decoded;
}

function tonpayCustomCreateInvoice($orderId, $amount, $buyerChatId)
{
    global $domainhosts;

    $apiKey = tonpayCustomApiKey();
    if ($apiKey === '') {
        return tonpayCustomError('MISSING_API_KEY', 'کلید API درگاه کاستوم تون‌پی تنظیم نشده است');
    }
    $buyer = tonpayNormalizeBuyerChatId($buyerChatId);
    if ($buyer === null) {
        return tonpayCustomError('INVALID_BUYER', 'شناسه تلگرام خریدار نامعتبر است');
    }
    $amount = (int) $amount;
    $payload = [
        'amount' => $amount,
        'order_id' => (string) $orderId,
        'buyer_chat_id' => $buyer,
        'callback_url' => 'https://' . $domainhosts . '/payment/tonpay.php',
    ];
    $decoded = tonpayCurlJson('POST', TONPAY_CUSTOM_BASE_URL . '/invoices/telegram/create', $payload, $apiKey);
    $invoice = tonpayCustomNormalizeInvoice($decoded);
    if ($invoice === null) {
        $error = tonpayNormalizeError($decoded, 'پاسخ نامعتبر از سرویس تون‌پی');
        error_log('TonPay custom create invoice failed: ' . json_encode([
            'order_id' => (string) $orderId,
            'status_code' => $error['status_code'],
            'error_code' => $error['error_code'],
            'error' => $error['error'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $error;
    }
    if ($invoice['request_amount'] !== $amount || $invoice['final_amount'] < $amount) {
        error_log('TonPay custom create invoice amount mismatch: ' . json_encode([
            'order_id' => (string) $orderId,
            'requested' => $amount,
            'request_amount' => $invoice['request_amount'],
            'final_amount' => $invoice['final_amount'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return tonpayCustomError('AMOUNT_MISMATCH', 'مبلغ فاکتور تون‌پی با مبلغ درخواستی مطابقت ندارد', (int) ($decoded['status_code'] ?? 0));
    }
    return $invoice;
}

function tonpayCustomCheckInvoice($invoiceId)
{
    $apiKey = tonpayCustomApiKey();
    if ($apiKey === '') {
        return null;
    }
    return tonpayCurlJson('GET', TONPAY_CUSTOM_BASE_URL . '/invoices/check/' . rawurlencode((string) $invoiceId), [], $apiKey);
}

function tonpayCustomChangeCardRequest($invoiceId)
{
    $apiKey = tonpayCustomApiKey();
    if ($apiKey === '') {
        return null;
    }
    return tonpayCurlJson('POST', TONPAY_CUSTOM_BASE_URL . '/invoices/' . rawurlencode((string) $invoiceId) . '/change-card', [], $apiKey);
}

function tonpayCustomReceiptRequest($invoiceId, $filePath, $mime, $fileName)
{
    $apiKey = tonpayCustomApiKey();
    if ($apiKey === '') {
        return null;
    }
    return tonpayCurlJson(
        'POST',
        TONPAY_CUSTOM_BASE_URL . '/invoices/' . rawurlencode((string) $invoiceId) . '/receipt',
        ['file' => new CURLFile($filePath, $mime, $fileName)],
        $apiKey,
        true
    );
}

function tonpayCustomFetchReport($orderId)
{
    global $pdo;
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

function tonpayCustomStoreInvoice($orderId, array $invoice)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return false;
    }
    $now = time();
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
            $invoice['invoice_id'],
            $invoice['final_amount'],
            $invoice['card_number'],
            $invoice['card_name'],
            $invoice['status'] !== '' ? $invoice['status'] : 'pending',
            $now,
            $now + TONPAY_CUSTOM_RECEIPT_WAIT_SECONDS,
            $now,
            (string) $orderId,
        ]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('TonPay custom store invoice failed: ' . json_encode([
            'order_id' => (string) $orderId,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return false;
    }
}

function tonpayCustomCardIsComplete($cardNumber)
{
    return preg_match('/^\d{16}$/', preg_replace('/[\s\-]/', '', (string) $cardNumber)) === 1;
}

function tonpayCustomBuildState(array $report, $now = null)
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

    if ($localStatus === 'paid') {
        $phase = 'paid';
    } elseif ($remote === 'completed') {
        $phase = 'confirming';
    } elseif ($localStatus === 'reject') {
        $phase = 'rejected';
    } elseif ($localStatus === 'cancelled') {
        $phase = 'canceled';
    } elseif ($localStatus === 'expire') {
        $phase = ($localState === 'review_timeout' || ($submittedAt > 0 && $deadlineAt > 0 && $deadlineAt <= $now)) ? 'review_timeout' : 'expired';
    } elseif ($remote === 'rejected') {
        $phase = ($deadlineAt > 0 && $deadlineAt <= $now) ? 'review_timeout' : 'receipt_rejected';
    } elseif ($remote === 'canceled' || $remote === 'cancelled') {
        $phase = 'canceled';
    } elseif ($remote === 'expired') {
        $phase = 'expired';
    } elseif ($remote === 'need_action') {
        $phase = 'need_action';
    } elseif ($remote === 'processing' || ($submittedAt > 0 && $remote !== 'pending')) {
        $phase = ($deadlineAt > 0 && $deadlineAt <= $now) ? 'review_timeout' : 'receipt_submitted';
    } elseif ($createdAt > 0 && $createdAt + TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS <= $now) {
        $phase = 'expired';
    } elseif ($availableAt > $now) {
        $phase = 'awaiting_payment';
    } else {
        $phase = 'receipt_available';
    }

    $open = in_array($phase, ['awaiting_payment', 'receipt_available', 'need_action'], true);
    $changeAvailableAt = $changedAt > 0 ? $changedAt + TONPAY_CUSTOM_CHANGE_CARD_COOLDOWN_SECONDS : 0;

    $canCancel = ($open && $submittedAt <= 0) || $phase === 'receipt_rejected';

    return [
        'order_id' => (string) ($report['id_order'] ?? ''),
        'invoice_id' => (string) ($report['tonpay_invoice_id'] ?? ''),
        'tracking_code' => (string) ($report['id_order'] ?? ''),
        'purchase_kind' => tonpayCustomPurchaseKind((string) ($report['id_invoice'] ?? '')),
        'phase' => $phase,
        'phase_label' => tonpayCustomPhaseLabel($phase),
        'follow_up' => in_array($phase, ['receipt_rejected', 'review_timeout', 'rejected', 'expired'], true),
        'remote_status' => $remote,
        'now' => $now,
        'request_amount' => $price,
        'final_amount' => $final,
        'fee' => $final - $price,
        'payable_rial' => $final * 10,
        'card_number' => $card,
        'card_name' => (string) ($report['tonpay_card_name'] ?? ''),
        'card_complete' => tonpayCustomCardIsComplete($card),
        'created_at' => $createdAt,
        'receipt_available_at' => $availableAt,
        'receipt_wait_remaining' => max(0, $availableAt - $now),
        'receipt_submitted_at' => $submittedAt,
        'review_deadline_at' => $deadlineAt,
        'review_remaining' => $deadlineAt > 0 ? max(0, $deadlineAt - $now) : 0,
        'can_submit_receipt' => $phase === 'receipt_available' || $phase === 'need_action',
        'can_change_card' => $open && !$exhausted && $changeAvailableAt <= $now,
        'change_card_wait' => max(0, $changeAvailableAt - $now),
        'change_card_exhausted' => $exhausted,
        'can_cancel' => $canCancel,
        'terminal' => in_array($phase, ['paid', 'rejected', 'canceled', 'expired', 'review_timeout'], true),
    ];
}

function tonpayCustomPhaseLabel($phase)
{
    $labels = [
        'awaiting_payment' => 'در انتظار پرداخت و تأیید خودکار',
        'receipt_available' => 'امکان ارسال رسید',
        'need_action' => 'نیاز به اقدام',
        'receipt_submitted' => 'رسید در حال بررسی تون‌پی',
        'receipt_rejected' => 'رسید در بررسی اولیه رد شده (در حال پیگیری)',
        'review_timeout' => 'پایان مهلت بررسی',
        'confirming' => 'تأیید شده و در حال نهایی‌سازی',
        'paid' => 'پرداخت تأییدشده',
        'rejected' => 'رسید ردشده',
        'canceled' => 'لغو‌شده',
        'expired' => 'منقضی‌شده',
    ];
    return $labels[$phase] ?? '';
}

function tonpayCustomPurchaseKind($invoiceField)
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

function tonpayCustomReceiptGate(array $state)
{
    if ($state['can_submit_receipt']) {
        return ['ok' => true, 'code' => 'ok', 'message' => ''];
    }
    if ($state['phase'] === 'awaiting_payment') {
        return [
            'ok' => false,
            'code' => 'too_early',
            'message' => tonpayCustomWaitMessage($state['receipt_wait_remaining']),
            'wait_sec' => $state['receipt_wait_remaining'],
        ];
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
    return ['ok' => false, 'code' => 'not_eligible', 'message' => 'این فاکتور دیگر قابل ارسال رسید نیست.'];
}

function tonpayCustomPersianDigits($value)
{
    return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function tonpayCustomWaitMessage($seconds)
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
    return tonpayCustomPersianDigits($label) . ' دیگر می‌توانید رسید پرداخت را ارسال کنید.';
}

function tonpayCustomReceiptPromptText()
{
    return tonpayTemplateRender('dyn_tonpay_receipt_prompt', [], true);
}

function tonpayCustomReceiptSubmittedText()
{
    return tonpayTemplateRender('dyn_tonpay_receipt_submitted', [], true);
}

function tonpayCustomUpdateColumns($orderId, array $columns, $onlyOpen = true)
{
    global $pdo;
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
        if (function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
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

function tonpayCustomApplyRemote(array $report, array $check)
{
    $remote = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
    $columns = ['tonpay_last_checked_at' => time()];
    if ($remote !== '') {
        $columns['tonpay_remote_status'] = $remote;
    }
    if (isset($check['final_amount']) && is_numeric($check['final_amount']) && (int) $check['final_amount'] >= (int) ($report['price'] ?? 0)) {
        $columns['tonpay_final_amount'] = (int) $check['final_amount'];
    }
    $localOpen = tonpayCustomLocalStatusIsOpen($report);
    if (in_array($remote, ['processing', 'rejected'], true) && (int) ($report['tonpay_receipt_submitted_at'] ?? 0) <= 0) {
        $now = time();
        $columns['tonpay_receipt_submitted_at'] = $now;
        $columns['tonpay_review_deadline_at'] = $now + TONPAY_CUSTOM_REVIEW_SECONDS;
    }
    if ($localOpen) {
        if ($remote === 'processing') {
            $columns['tonpay_local_state'] = 'receipt_submitted';
        }
        if ($remote === 'need_action') {
            $columns['tonpay_local_state'] = 'need_action';
        }
    }
    tonpayCustomUpdateColumns($report['id_order'], $columns, false);
    if ($remote === 'rejected' && $localOpen && tonpayCustomClaimLocalState($report['id_order'], 'receipt_rejected')) {
        tonpayCustomNotifyReceiptRejected($report['id_order']);
    }
}

function tonpayCustomLocalStatusIsOpen(array $report)
{
    return in_array((string) ($report['payment_Status'] ?? ''), ['Unpaid', 'pending', 'waiting'], true);
}

function tonpayCustomClaimLocalState($orderId, $state)
{
    global $pdo;
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
        if ($stmt->rowCount() > 0 && function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
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

function tonpayCustomNotifyReceiptRejected($orderId)
{
    if (!function_exists('sendmessage')) {
        return;
    }
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null) {
        return;
    }
    $state = tonpayCustomBuildState($report);
    $text = tonpayCustomStatusText($state, true) . "\n\n🧾 شناسه فاکتور: <code>" . htmlspecialchars($state['invoice_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
    sendmessage($report['id_user'], $text, tonpayCustomFollowUpKeyboard($state), 'HTML');
}

function tonpayCustomFulfill(array $report, array $check)
{
    $orderId = (string) $report['id_order'];
    if (empty($check['paid']) || strtolower((string) ($check['status'] ?? '')) !== 'completed') {
        return ['ok' => false, 'reason' => 'not completed'];
    }
    if (!tonpayAmountsMatch($check['request_amount'] ?? null, $report['price'] ?? null)) {
        tonpayLogAmountMismatch('custom', $orderId, $report['tonpay_invoice_id'] ?? '', $report['price'] ?? null, $check['request_amount'] ?? null);
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
    if (!function_exists('payment_confirm_paid')) {
        require_once __DIR__ . '/../../../lib/PaymentConfirm.php';
    }
    $previousStatus = (string) ($report['payment_Status'] ?? '');
    if ($previousStatus === 'cancelled') {
        tonpayCustomReopenCancelledForConfirmation($orderId);
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
    $threadRow = select("topicid", "idreport", "report", "paymentreport", "select");
    $result = payment_confirm_paid($orderId, 'chashbacktonpay', [
        'method' => 'tonpay',
        'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
        'extra_lines' => $lines,
    ]);
    if (!empty($result['ok'])) {
        tonpayCustomUpdateColumns($orderId, ['tonpay_remote_status' => 'completed', 'tonpay_local_state' => 'completed'], false);
    }
    return $result;
}

function tonpayCustomReopenCancelledForConfirmation($orderId)
{
    global $pdo;
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
        if (function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
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

function tonpayCustomTerminalNotice(array $report, $phase)
{
    if (!in_array($phase, ['rejected', 'canceled', 'expired'], true)) {
        return '';
    }
    $state = tonpayCustomBuildState($report);
    $state['phase'] = $phase;
    return tonpayCustomStatusText($state, true);
}

function tonpayCustomPollOrder(array $report, $fulfill = true)
{
    $invoiceId = trim((string) ($report['tonpay_invoice_id'] ?? ''));
    if ($invoiceId === '') {
        return ['state' => 'error'];
    }
    $check = tonpayCustomCheckInvoice($invoiceId);
    if (is_array($check) && !empty($check['local_rate_limited'])) {
        return ['state' => 'local_rate_limited'];
    }
    if (tonpayIsRateLimited($check)) {
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

    tonpayCustomApplyRemote($report, $check);
    $remote = strtolower((string) ($check['status'] ?? ''));

    if ($remote === 'completed' && !empty($check['paid'])) {
        if (!$fulfill) {
            return ['state' => 'completed', 'status' => $remote];
        }
        $result = tonpayCustomFulfill($report, $check);
        return ['state' => 'completed', 'status' => $remote, 'fulfilled' => !empty($result['ok'])];
    }

    if ($remote === 'rejected') {
        return ['state' => 'open', 'status' => $remote];
    }

    if (tonpayTerminalLocalStatus($remote) !== null) {
        if (in_array((string) ($report['payment_Status'] ?? ''), ['expire', 'reject', 'cancelled'], true)) {
            tonpayCustomUpdateColumns($report['id_order'], ['tonpay_local_state' => 'closed'], false);
        }
        $changed = tonpaySyncTerminalStatus($report['id_order'], $remote);
        if ($changed && function_exists('sendmessage')) {
            $notice = tonpayCustomTerminalNotice($report, $remote === 'expired' ? 'expired' : 'canceled');
            if ($notice !== '') {
                sendmessage($report['id_user'], $notice, tonpayCustomFollowUpKeyboard(tonpayCustomBuildState(tonpayCustomFetchReport($report['id_order']) ?? $report)), 'HTML');
            }
        }
        return ['state' => 'terminal', 'status' => $remote];
    }

    return ['state' => 'open', 'status' => $remote];
}

function tonpayCustomIsLateReconcilable(array $report)
{
    return in_array((string) ($report['payment_Status'] ?? ''), ['expire', 'reject', 'cancelled'], true)
        && (string) ($report['tonpay_local_state'] ?? '') !== 'closed'
        && (int) ($report['tonpay_created_at'] ?? 0) >= time() - TONPAY_CUSTOM_LATE_RECONCILE_SECONDS;
}

function tonpayCustomPollThrottled($orderId, $userId)
{
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $userId) {
        return null;
    }
    $pollable = tonpayCustomLocalStatusIsOpen($report) || tonpayCustomIsLateReconcilable($report);
    if ($pollable && time() - (int) ($report['tonpay_last_checked_at'] ?? 0) >= TONPAY_CUSTOM_POLL_THROTTLE_SECONDS) {
        tonpayCustomPollOrder($report, true);
        $report = tonpayCustomFetchReport($orderId) ?? $report;
    }
    return $report;
}

function tonpayCustomChangeCard($orderId, $userId)
{
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $userId) {
        return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
    }
    $state = tonpayCustomBuildState($report);
    if (!in_array($state['phase'], ['awaiting_payment', 'receipt_available', 'need_action'], true)) {
        return ['ok' => false, 'message' => 'در این وضعیت امکان تعویض کارت وجود ندارد.'];
    }
    if ($state['change_card_exhausted']) {
        return ['ok' => false, 'message' => 'کارت دیگری برای تعویض باقی نمانده است.'];
    }
    if ($state['change_card_wait'] > 0) {
        return ['ok' => false, 'message' => 'لطفاً ' . $state['change_card_wait'] . ' ثانیه دیگر دوباره تلاش کنید.', 'wait_sec' => $state['change_card_wait']];
    }
    $decoded = tonpayCustomChangeCardRequest($report['tonpay_invoice_id']);
    if (is_array($decoded) && !empty($decoded['local_rate_limited'])) {
        return ['ok' => false, 'message' => 'سقف درخواست‌ها موقتاً پر است؛ کمی بعد تلاش کنید.'];
    }
    $invoice = tonpayCustomNormalizeInvoice($decoded);
    if ($invoice === null || $invoice['card_number'] === '') {
        $error = tonpayNormalizeError($decoded, 'تعویض کارت انجام نشد.');
        if (is_array($decoded) && isset($decoded['change_card_exhausted']) && !empty($decoded['change_card_exhausted'])) {
            tonpayCustomUpdateColumns($orderId, ['tonpay_change_card_exhausted' => 1]);
        }
        return ['ok' => false, 'message' => $error['error'] !== '' ? $error['error'] : 'تعویض کارت انجام نشد.'];
    }
    if ($invoice['request_amount'] !== (int) $report['price']) {
        return ['ok' => false, 'message' => 'پاسخ تون‌پی با فاکتور شما مطابقت ندارد.'];
    }
    $columns = [
        'tonpay_card_number' => $invoice['card_number'],
        'tonpay_card_name' => $invoice['card_name'],
        'tonpay_final_amount' => max($invoice['final_amount'], (int) $report['price']),
        'tonpay_card_changed_at' => time(),
        'tonpay_change_card_exhausted' => !empty($decoded['change_card_exhausted']) ? 1 : 0,
    ];
    if ($invoice['status'] !== '') {
        $columns['tonpay_remote_status'] = $invoice['status'];
    }
    tonpayCustomUpdateColumns($orderId, $columns);
    return ['ok' => true, 'report' => tonpayCustomFetchReport($orderId)];
}

function tonpayCustomValidateReceiptFile($path)
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
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'message' => 'فقط تصویر رسید (JPG، PNG یا WEBP) پذیرفته می‌شود.'];
    }
    if (@getimagesize($path) === false) {
        return ['ok' => false, 'message' => 'محتوای فایل یک تصویر معتبر نیست.'];
    }
    return ['ok' => true, 'mime' => $mime, 'name' => 'receipt.' . $allowed[$mime]];
}

function tonpayCustomSubmitReceipt($orderId, $userId, $filePath)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return ['ok' => false, 'message' => 'اتصال پایگاه داده برقرار نیست.'];
    }
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $userId) {
        return ['ok' => false, 'code' => 'not_found', 'message' => 'فاکتور پیدا نشد.'];
    }
    $state = tonpayCustomBuildState($report);
    $gate = tonpayCustomReceiptGate($state);
    if (!$gate['ok']) {
        return ['ok' => false, 'code' => $gate['code'], 'message' => $gate['message'], 'wait_sec' => $gate['wait_sec'] ?? null];
    }
    $file = tonpayCustomValidateReceiptFile($filePath);
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
        $report = tonpayCustomFetchReport($orderId);
        $state = $report === null ? null : tonpayCustomBuildState($report);
        if ($state === null || !tonpayCustomReceiptGate($state)['ok']) {
            return ['ok' => false, 'code' => 'already_submitted', 'message' => 'رسید شما قبلاً ارسال شده یا فاکتور دیگر قابل ارسال رسید نیست.'];
        }
        $decoded = tonpayCustomReceiptRequest($report['tonpay_invoice_id'], $filePath, $file['mime'], $file['name']);
        if (is_array($decoded) && (!empty($decoded['local_rate_limited']) || tonpayIsRateLimited($decoded))) {
            return ['ok' => false, 'code' => 'rate_limited', 'message' => 'سقف درخواست‌های تون‌پی موقتاً پر است؛ کمی بعد دوباره تلاش کنید.'];
        }
        $invoice = tonpayCustomNormalizeInvoice($decoded);
        $received = is_array($decoded) && !empty($decoded['receipt_received']);
        if ($invoice === null || !$received) {
            $error = tonpayNormalizeError($decoded, 'ارسال رسید به تون‌پی ناموفق بود.');
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
        if ($invoice['request_amount'] !== (int) $report['price']) {
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
        tonpayCustomUpdateColumns($orderId, $submittedColumns, false);
        $updated = tonpayCustomFetchReport($orderId) ?? $report;
        if (!empty($decoded['paid']) && $invoice['status'] === 'completed') {
            tonpayCustomFulfill($updated, $decoded);
            $updated = tonpayCustomFetchReport($orderId) ?? $updated;
        }
        return ['ok' => true, 'report' => $updated];
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

function tonpayCustomCancel($orderId, $userId)
{
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $userId) {
        return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
    }
    $state = tonpayCustomBuildState($report);
    if (!$state['can_cancel']) {
        return ['ok' => false, 'message' => tonpayCustomCancelBlockedMessage($state)];
    }
    $poll = tonpayCustomPollOrder($report, true);
    if (in_array($poll['state'], ['error', 'rate_limited', 'local_rate_limited'], true)) {
        return ['ok' => false, 'message' => 'در حال حاضر امکان بررسی وضعیت پرداخت در تون‌پی وجود ندارد؛ کمی بعد دوباره تلاش کنید.'];
    }
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null) {
        return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
    }
    $state = tonpayCustomBuildState($report);
    if (!$state['can_cancel']) {
        return ['ok' => false, 'message' => tonpayCustomCancelBlockedMessage($state)];
    }
    $changed = tonpayCustomUpdateColumns($orderId, ['payment_Status' => 'cancelled', 'tonpay_local_state' => 'canceled']);
    return $changed > 0 ? ['ok' => true] : ['ok' => false, 'message' => 'این فاکتور دیگر قابل لغو نیست.'];
}

function tonpayCustomCancelBlockedMessage(array $state)
{
    if (in_array($state['phase'], ['paid', 'confirming'], true)) {
        return 'پرداخت شما در تون‌پی تأیید شده است و فاکتور قابل لغو نیست.';
    }
    if (in_array($state['phase'], ['receipt_submitted', 'need_action'], true)) {
        return 'رسید شما در حال بررسی تون‌پی است و تا مشخص شدن نتیجه امکان لغو فاکتور وجود ندارد.';
    }
    return 'این فاکتور دیگر قابل لغو نیست.';
}

function tonpayCustomPurgeProtected($orderId)
{
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null) {
        return false;
    }
    $state = tonpayCustomBuildState($report);
    if ($state['receipt_submitted_at'] > 0 && $state['review_deadline_at'] > time()) {
        return true;
    }
    return $state['created_at'] > 0 && $state['created_at'] + TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS > time();
}

function tonpayCustomExpireOverdue($limit = 50)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return 0;
    }
    $now = time();
    $expired = 0;
    try {
        $stmt = $pdo->prepare(
            "SELECT id_order, id_user, tonpay_receipt_submitted_at
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
        $hadReceipt = (int) ($row['tonpay_receipt_submitted_at'] ?? 0) > 0;
        $changed = tonpayCustomUpdateColumns($row['id_order'], [
            'payment_Status' => 'expire',
            'tonpay_local_state' => $hadReceipt ? 'review_timeout' : 'unpaid_timeout',
        ]);
        if ($changed > 0) {
            $expired++;
            $expiredReport = tonpayCustomFetchReport($row['id_order']);
            if (function_exists('sendmessage') && $expiredReport !== null) {
                $expiredState = tonpayCustomBuildState($expiredReport);
                $expiredState['phase'] = $hadReceipt ? 'review_timeout' : 'expired';
                sendmessage(
                    $row['id_user'],
                    tonpayCustomStatusText($expiredState, true),
                    tonpayCustomFollowUpKeyboard($expiredState),
                    'HTML'
                );
            }
        }
    }
    return $expired;
}

function tonpayCustomPublicView(array $report)
{
    $state = tonpayCustomBuildState($report);
    $state['card_number_display'] = $state['card_complete']
        ? trim(chunk_split(preg_replace('/[\s\-]/', '', $state['card_number']), 4, ' '))
        : $state['card_number'];
    $state['card_copy_value'] = $state['card_complete'] ? preg_replace('/[\s\-]/', '', $state['card_number']) : '';
    $state['amount_copy_value'] = (string) $state['payable_rial'];
    $state['wallet_credit'] = $state['request_amount'];
    $state['messages'] = tonpayCustomPhaseMessages($state);
    $state['button_styles'] = tonpayButtonStyleMap();
    $state['gateway_title'] = tonpayGatewayHeading();
    unset($state['fee'], $state['final_amount']);
    return $state;
}

function tonpayCustomFormatDateTime($timestamp)
{
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return '—';
    }
    return function_exists('jdate') ? jdate('Y/m/d H:i', $timestamp) : date('Y/m/d H:i', $timestamp);
}

function tonpayCustomTemplateValues(array $state)
{
    $card = (string) $state['card_number'];
    return [
        'gateway_title' => tonpayGatewayHeading(),
        'purchase_amount' => number_format($state['request_amount']),
        'payable_amount' => number_format($state['payable_rial']),
        'card_number' => $state['card_complete'] ? preg_replace('/[\s\-]/', '', $card) : ($card !== '' ? $card : '—'),
        'cardholder_name' => $state['card_name'] !== '' ? $state['card_name'] : '—',
        'invoice_id' => $state['invoice_id'],
        'tracking_code' => $state['tracking_code'],
        'review_deadline' => tonpayCustomFormatDateTime($state['review_deadline_at']),
        'remaining_time' => tonpayCustomFormatClock($state['phase'] === 'awaiting_payment' ? $state['receipt_wait_remaining'] : $state['review_remaining']),
    ];
}

function tonpayCustomPaymentWarnings($html, $copyButtonName)
{
    $amountWarning = tonpayTemplateRender('dyn_tonpay_amount_warning', ['copy_button_name' => $copyButtonName], $html);
    $cardOnlyWarning = tonpayTemplateRender('dyn_tonpay_card_only_warning', [], $html);
    return $amountWarning . "\n\n" . $cardOnlyWarning;
}

function tonpayCustomStatusText(array $state, $html)
{
    return tonpayTemplateRender('dyn_tonpay_status_' . $state['phase'], tonpayCustomTemplateValues($state), $html);
}

function tonpayCustomPhaseMessages(array $state)
{
    return [
        'status' => tonpayCustomStatusText($state, false),
        'amount_warning' => tonpayTemplateRender('dyn_tonpay_amount_warning', ['copy_button_name' => 'کپی مبلغ'], false),
        'card_only_warning' => tonpayTemplateRender('dyn_tonpay_card_only_warning', [], false),
    ];
}

function tonpayCustomFormatClock($seconds)
{
    $seconds = max(0, (int) $seconds);
    if ($seconds >= 3600) {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
    return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
}

function tonpayCustomBotText(array $state)
{
    $values = tonpayCustomTemplateValues($state);
    $open = in_array($state['phase'], ['awaiting_payment', 'receipt_available', 'need_action'], true);
    $values['amount_warning'] = tonpayCustomPaymentWarnings(true, 'کپی مبلغ پرداخت');
    $lines = [tonpayTemplateRender($open ? 'dyn_tonpay_invoice_tpl' : 'dyn_tonpay_progress_tpl', $values, true, ['amount_warning'])];
    if ($open && !$state['card_complete']) {
        $lines[] = '⚠️ شماره کارت به‌صورت کامل دریافت نشد؛ لطفاً با پشتیبانی تماس بگیرید.';
    }
    $lines[] = '';
    $lines[] = tonpayCustomStatusText($state, true);
    if ($state['phase'] === 'awaiting_payment') {
        $lines[] = tonpayTemplateRender('dyn_tonpay_countdown_wait', $values, true);
    }
    if (in_array($state['phase'], ['receipt_submitted', 'receipt_rejected'], true)) {
        $lines[] = tonpayTemplateRender('dyn_tonpay_countdown_review', $values, true);
    }
    return implode("\n", $lines);
}

function tonpayCustomSupportUrl()
{
    global $usernamebot;
    $row = function_exists('select') ? select('setting', 'support_username', null, null, 'select') : null;
    $handle = is_array($row) ? ltrim(trim((string) ($row['support_username'] ?? '')), '@') : '';
    if ($handle !== '') {
        return 'https://t.me/' . $handle;
    }
    $bot = isset($usernamebot) ? ltrim(trim((string) $usernamebot), '@') : '';
    return $bot !== '' ? 'https://t.me/' . $bot : '';
}

function tonpayCustomFollowUpKeyboard(array $state)
{
    $rows = [[rx_payment_button('tonpay', 'view_details', ['text' => '🔍 مشاهده جزئیات و وضعیت', 'callback_data' => 'tpc_view_' . $state['order_id']])]];
    $supportUrl = tonpayCustomSupportUrl();
    if ($supportUrl !== '') {
        $rows[] = [rx_payment_button('tonpay', 'support', ['text' => '📞 پشتیبانی', 'url' => $supportUrl])];
    }
    $rows[] = [rx_payment_button('tonpay', 'pending_list', ['text' => '⏳ تراکنش‌های در انتظار', 'callback_data' => 'tpc_list'])];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function tonpayCustomBotKeyboard(array $state)
{
    $orderId = $state['order_id'];
    $open = in_array($state['phase'], ['awaiting_payment', 'receipt_available', 'need_action'], true);
    $rows = [];
    if ($open) {
        $copyRow = [];
        if ($state['card_complete']) {
            $copyRow[] = tonpayStyledButton(['text' => '📋 کپی شماره کارت', 'copy_text' => ['text' => preg_replace('/[\s\-]/', '', $state['card_number'])], '_rxck' => 'card_num'], 'copy_card');
        }
        $copyRow[] = tonpayStyledButton(['text' => '📋 کپی مبلغ پرداخت', 'copy_text' => ['text' => (string) $state['payable_rial']], '_rxck' => 'card_amount'], 'copy_amount');
        $rows[] = $copyRow;
        if (!$state['change_card_exhausted']) {
            $rows[] = [tonpayStyledButton(['text' => '🔁 تعویض کارت', 'callback_data' => 'tpc_chg_' . $orderId], 'change_card')];
        }
    }
    $actionRow = [];
    if (!$state['terminal'] && $state['phase'] !== 'confirming') {
        $actionRow[] = tonpayStyledButton(['text' => '🔄 بررسی وضعیت پرداخت', 'callback_data' => 'tpc_chk_' . $orderId], 'check_status');
    }
    if ($open) {
        $actionRow[] = tonpayStyledButton(['text' => '📤 ارسال رسید پرداخت', 'callback_data' => 'tpc_rcp_' . $orderId], 'send_receipt');
    }
    if (!empty($actionRow)) {
        $rows[] = $actionRow;
    }
    if ($state['can_cancel']) {
        $rows[] = [tonpayStyledButton(['text' => '❌ لغو تراکنش', 'callback_data' => 'tpc_can_' . $orderId], 'cancel_transaction')];
    }
    $rows[] = [tonpayStyledButton(['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser'], 'back_main')];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function tonpayCustomUserPending($userId, $limit = 10)
{
    global $pdo;
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

function tonpayCustomBotListView($userId)
{
    $e = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $reports = tonpayCustomUserPending($userId);
    $rows = [];
    if (empty($reports)) {
        $text = "⏳ <b>تراکنش‌های در انتظار</b>\n\nدر حال حاضر تراکنش پرداخت نیمه‌تمامی ندارید.";
    } else {
        $lines = ["⏳ <b>تراکنش‌های در انتظار</b>", ''];
        foreach ($reports as $index => $report) {
            $state = tonpayCustomBuildState($report);
            $createdAt = (int) $state['created_at'];
            $lines[] = '🧾 شناسه فاکتور: <code>' . $e($state['invoice_id']) . '</code>';
            $lines[] = '🛍 نوع تراکنش: ' . $e($state['purchase_kind']);
            $lines[] = '💰 مبلغ: <b>' . number_format($state['request_amount']) . '</b> تومان';
            $lines[] = '📌 وضعیت: ' . $e($state['phase_label']);
            $lines[] = '📅 تاریخ ایجاد: ' . $e(tonpayCustomFormatDateTime($createdAt));
            if (in_array($state['phase'], ['receipt_submitted', 'receipt_rejected'], true) && $state['review_deadline_at'] > 0) {
                $lines[] = '⏱ زمان باقی‌مانده بررسی: <b>' . tonpayCustomFormatClock($state['review_remaining']) . '</b>';
            } elseif ($state['phase'] === 'awaiting_payment') {
                $lines[] = '⏱ زمان باقی‌مانده تا امکان ارسال رسید: <b>' . tonpayCustomFormatClock($state['receipt_wait_remaining']) . '</b>';
            }
            $lines[] = '';
            $buttons = [rx_payment_button('tonpay', 'list_view', ['text' => '🔍 مشاهده جزئیات و وضعیت', 'callback_data' => 'tpc_view_' . $state['order_id']])];
            if ($state['can_cancel']) {
                $buttons[] = rx_payment_button('tonpay', 'list_cancel', ['text' => '❌ لغو تراکنش', 'callback_data' => 'tpc_can_' . $state['order_id']]);
            }
            $rows[] = $buttons;
        }
        $text = implode("\n", $lines);
    }
    $rows[] = [rx_payment_button('tonpay', 'back_account', ['text' => '🔙 بازگشت', 'callback_data' => 'account'])];
    return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function tonpayCustomBotCancelConfirmView($orderId, $backData)
{
    $text = "آیا از لغو این تراکنش اطمینان دارید؟\n\n⚠️ لغو فاکتور به‌معنی بازگشت وجه واریزشده نیست. اگر مبلغ را واریز کرده‌اید و تون‌پی آن را تأیید کند، پرداخت شما همچنان ثبت خواهد شد.";
    $keyboard = json_encode(['inline_keyboard' => [
        [tonpayStyledButton(['text' => '✅ بله، لغو تراکنش', 'callback_data' => 'tpc_cny_' . $orderId], 'cancel_confirm_approve')],
        [tonpayStyledButton(['text' => '🔙 بازگشت', 'callback_data' => $backData], 'cancel_confirm_back')],
    ]], JSON_UNESCAPED_UNICODE);
    return ['text' => $text, 'keyboard' => $keyboard];
}

function tonpayCustomBotRefreshOriginal($chatId, $orderId, $currentMessageId)
{
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
        return false;
    }
    $originalId = (int) ($report['message_id'] ?? 0);
    if ($originalId > 0 && $originalId !== (int) $currentMessageId) {
        tonpayCustomBotRender($chatId, $originalId, $orderId, $report);
        if ((int) $currentMessageId > 0) {
            deletemessage($chatId, $currentMessageId);
        }
        return true;
    }
    return tonpayCustomBotRender($chatId, $currentMessageId, $orderId, $report);
}

function tonpayCustomBotSend($chatId, $orderId)
{
    $report = tonpayCustomFetchReport($orderId);
    if ($report === null) {
        return false;
    }
    $state = tonpayCustomBuildState($report);
    $response = sendmessage($chatId, tonpayCustomBotText($state), tonpayCustomBotKeyboard($state), 'HTML');
    if (function_exists('updatePaymentMessageId')) {
        updatePaymentMessageId($response, $orderId);
    }
    return true;
}

function tonpayCustomBotRender($chatId, $messageId, $orderId, ?array $report = null)
{
    $report = $report ?? tonpayCustomFetchReport($orderId);
    if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
        return false;
    }
    $state = tonpayCustomBuildState($report);
    Editmessagetext($chatId, $messageId, tonpayCustomBotText($state), tonpayCustomBotKeyboard($state), 'HTML');
    return true;
}

function tonpayCustomBotAnswer($callbackQueryId, $text = '', $alert = false)
{
    if ($callbackQueryId === '' || $callbackQueryId === 0) {
        return;
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => $alert,
        'cache_time' => 0,
    ]);
}

function tonpayCustomBotDownloadFile($fileId, &$error)
{
    global $APIKEY;
    $token = is_string($APIKEY ?? null) ? $APIKEY : '';
    if ($token === '') {
        $row = select('setting', 'token_bot', null, null, 'select');
        $token = is_array($row) ? (string) ($row['token_bot'] ?? '') : '';
    }
    if ($token === '' || !is_string($fileId) || $fileId === '') {
        $error = 'دریافت فایل از تلگرام ممکن نیست.';
        return null;
    }
    $meta = telegram('getFile', ['file_id' => $fileId]);
    $filePath = is_array($meta) ? (string) ($meta['result']['file_path'] ?? '') : '';
    $fileSize = is_array($meta) ? (int) ($meta['result']['file_size'] ?? 0) : 0;
    if ($filePath === '') {
        $error = 'فایل در تلگرام پیدا نشد.';
        return null;
    }
    if ($fileSize > TONPAY_CUSTOM_RECEIPT_MAX_BYTES) {
        $error = 'حجم رسید نباید بیشتر از ۱۰ مگابایت باشد.';
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'tpcr_');
    if ($tmp === false) {
        $error = 'ذخیره موقت فایل ممکن نیست.';
        return null;
    }
    $handle = fopen($tmp, 'wb');
    if ($handle === false) {
        @unlink($tmp);
        $error = 'ذخیره موقت فایل ممکن نیست.';
        return null;
    }
    $curl = curl_init('https://api.telegram.org/file/bot' . $token . '/' . $filePath);
    curl_setopt_array($curl, [
        CURLOPT_FILE => $handle,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_FAILONERROR => true,
    ]);
    $ok = curl_exec($curl);
    curl_close($curl);
    fclose($handle);
    if ($ok === false || (int) @filesize($tmp) <= 0) {
        @unlink($tmp);
        $error = 'دریافت فایل از تلگرام ناموفق بود.';
        return null;
    }
    return $tmp;
}

function tonpayCustomBotHandleReceiptUpload($chatId, $orderId, array $message)
{
    $fileId = '';
    if (!empty($message['photo']) && is_array($message['photo'])) {
        $largest = end($message['photo']);
        $fileId = (string) ($largest['file_id'] ?? '');
    } elseif (!empty($message['document']['file_id'])) {
        $mime = strtolower((string) ($message['document']['mime_type'] ?? ''));
        if (strpos($mime, 'image/') !== 0) {
            return ['ok' => false, 'message' => 'فقط تصویر رسید پذیرفته می‌شود. لطفاً عکس رسید را ارسال کنید.'];
        }
        $fileId = (string) $message['document']['file_id'];
    }
    if ($fileId === '') {
        return ['ok' => false, 'message' => 'لطفاً رسید را به‌صورت عکس ارسال کنید.'];
    }
    $error = '';
    $tmp = tonpayCustomBotDownloadFile($fileId, $error);
    if ($tmp === null) {
        return ['ok' => false, 'message' => $error];
    }
    try {
        return tonpayCustomSubmitReceipt($orderId, $chatId, $tmp);
    } finally {
        @unlink($tmp);
    }
}

function tonpayCustomRunCron()
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    tonpayCustomExpireOverdue();
    $now = time();
    $batches = [
        [
            "SELECT * FROM Payment_report
              WHERE Payment_Method = 'tonpay' AND tonpay_gateway_mode = 'custom'
                AND tonpay_invoice_id IS NOT NULL AND tonpay_invoice_id <> ''
                AND payment_Status = 'Unpaid'
                AND (tonpay_last_checked_at IS NULL OR tonpay_last_checked_at <= ?)
              ORDER BY tonpay_last_checked_at ASC, id ASC
              LIMIT 30",
            [$now - 45],
        ],
        [
            "SELECT * FROM Payment_report
              WHERE Payment_Method = 'tonpay' AND tonpay_gateway_mode = 'custom'
                AND tonpay_invoice_id IS NOT NULL AND tonpay_invoice_id <> ''
                AND payment_Status IN ('expire','reject','cancelled')
                AND (tonpay_local_state IS NULL OR tonpay_local_state <> 'closed')
                AND tonpay_created_at >= ?
                AND (tonpay_last_checked_at IS NULL OR tonpay_last_checked_at <= ?)
              ORDER BY tonpay_last_checked_at ASC, id ASC
              LIMIT 10",
            [$now - TONPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - 900],
        ],
    ];
    foreach ($batches as [$sql, $params]) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[tonpaycheck] custom select failed: ' . $e->getMessage());
            continue;
        }
        foreach ($rows as $row) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                return;
            }
            tonpayCustomUpdateColumns($row['id_order'], ['tonpay_last_checked_at' => time()], false);
            try {
                $result = tonpayCustomPollOrder($row, true);
            } catch (Throwable $e) {
                error_log('[tonpaycheck] custom poll threw for order ' . $row['id_order'] . ': ' . $e->getMessage());
                continue;
            }
            if (in_array($result['state'], ['rate_limited', 'local_rate_limited'], true)) {
                return;
            }
        }
    }
}
