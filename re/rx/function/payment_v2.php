<?php

const RX_PAYMENT_PROCESSING_VERSION = 2;
const TRONADO_V2_RECENT_SECONDS = 7200;
const TRONADO_V2_WINDOW_SECONDS = 172800;
const TRONADO_V2_POLL_RECENT_SECONDS = 60;
const TRONADO_V2_POLL_OLD_SECONDS = 900;
const TRONADO_V2_TRX_EPSILON = 0.000001;

function rxPaymentV2Is($report)
{
    return is_array($report) && (int) ($report['payment_processing_version'] ?? 0) >= RX_PAYMENT_PROCESSING_VERSION;
}

function rxPaymentV2Mark($orderId, array $columns = [])
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return false;
    }
    $columns = array_merge(['payment_processing_version' => RX_PAYMENT_PROCESSING_VERSION], $columns);
    $sets = [];
    $params = [];
    foreach ($columns as $name => $value) {
        $sets[] = $name . ' = ?';
        $params[] = $value;
    }
    $params[] = (string) $orderId;
    try {
        $stmt = $pdo->prepare("UPDATE Payment_report SET " . implode(', ', $sets) . " WHERE id_order = ?");
        $stmt->execute($params);
        if (function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
        }
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('rxPaymentV2Mark failed: ' . $e->getMessage());
        return false;
    }
}

function rxPaymentV2PrepareContext()
{
    global $datatextbot, $textbotlang, $setting, $ManagePanel;
    if (!is_array($setting ?? null)) {
        $setting = select("setting", "*");
    }
    if (!is_array($datatextbot ?? null)) {
        $datatextbot = [
            'textafterpay' => '',
            'textaftertext' => '',
            'textmanual' => '',
            'textselectlocation' => '',
            'text_wgdashboard' => '',
        ];
        $rows = select("textbot", "*", null, null, "fetchAll");
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (array_key_exists($row['id_text'], $datatextbot) || (is_string($row['text']) && trim($row['text']) !== '')) {
                    $datatextbot[$row['id_text']] = $row['text'];
                }
            }
        }
    }
    if (!is_array($textbotlang ?? null) && function_exists('languagechange')) {
        $textbotlang = languagechange(dirname(__DIR__, 3) . '/text.json');
    }
    if (!is_object($ManagePanel ?? null) && class_exists('ManagePanel')) {
        $ManagePanel = new ManagePanel();
    }
    if (!function_exists('payment_confirm_paid')) {
        require_once dirname(__DIR__, 3) . '/lib/PaymentConfirm.php';
    }
}

function rxPaymentV2ReportToAdmin($text)
{
    global $setting;
    $channel = is_array($setting ?? null) ? (string) ($setting['Channel_Report'] ?? '') : '';
    if ($channel === '') {
        $row = select('setting', 'Channel_Report', null, null, 'select');
        $channel = is_array($row) ? (string) ($row['Channel_Report'] ?? '') : '';
    }
    if ($channel === '' || !function_exists('telegram')) {
        return;
    }
    $threadRow = select("topicid", "idreport", "report", "paymentreport", "select");
    $payload = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML'];
    if (is_array($threadRow) && !empty($threadRow['idreport'])) {
        $payload['message_thread_id'] = $threadRow['idreport'];
    }
    telegram('sendmessage', $payload);
}

function rxPaymentV2Escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tronadoV2ParseToman($value)
{
    if (is_numeric($value)) {
        return (int) ceil((float) $value);
    }
    if (is_string($value)) {
        $digits = preg_replace('/[^\d]/', '', $value);
        return $digits === '' ? 0 : (int) $digits;
    }
    return 0;
}

function tronadoV2NormalizeToken($token)
{
    return strtolower(str_replace('-', '', trim((string) $token)));
}

function tronadoV2Store($orderId, array $payment)
{
    $token = function_exists('tronadoExtractPaymentToken') ? tronadoExtractPaymentToken($payment) : trim((string) ($payment['Token'] ?? ''));
    $estimated = $payment['EstimatedTomanAmount'] ?? ($payment['Data']['EstimatedTomanAmount'] ?? null);
    $tron = isset($payment['rx_tron_amount']) && is_numeric($payment['rx_tron_amount']) ? (float) $payment['rx_tron_amount'] : 0.0;
    $wage = isset($payment['rx_wage']) && is_numeric($payment['rx_wage']) ? (int) $payment['rx_wage'] : null;
    return rxPaymentV2Mark($orderId, [
        'tronado_tron_amount' => $tron,
        'tronado_wage_pct' => $wage,
        'tronado_estimated_toman' => tronadoV2ParseToman($estimated),
        'tronado_token' => substr($token, 0, 80),
        'tronado_created_at' => time(),
        'tronado_local_state' => 'awaiting_payment',
    ]);
}

function tronadoV2RemoteStatus($paymentId)
{
    $config = defined('TRONADO_API_CONFIGURATION') ? TRONADO_API_CONFIGURATION : [];
    $base = rtrim((string) ($config['base_url'] ?? 'https://bot.tronado.cloud'), '/');
    $path = '/' . ltrim((string) ($config['get_status_by_payment_id_path'] ?? '/Order/GetStatusByPaymentID'), '/');
    $row = select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
    $apiKey = is_array($row) ? trim((string) ($row['ValuePay'] ?? '')) : '';
    if ($apiKey === '' || !function_exists('tronadoCurlJson')) {
        return null;
    }
    return tronadoCurlJson($base . $path, ['Id' => (string) $paymentId], $apiKey);
}

function tronadoV2Evaluate(array $report, array $data, $source)
{
    $orderId = (string) $report['id_order'];
    $reference = $data['PaymentId'] ?? ($data['PaymentID'] ?? null);
    if ($reference === null || (string) $reference !== $orderId) {
        return ['ok' => false, 'reason' => 'شناسه پرداخت با فاکتور مطابقت ندارد'];
    }
    $status = isset($data['OrderStatusID']) && is_numeric($data['OrderStatusID']) ? (int) $data['OrderStatusID'] : null;
    $paid = $status === 30 || ($data['IsPaid'] ?? false) === true;
    if (!$paid) {
        return ['ok' => false, 'not_paid' => true, 'reason' => 'پرداخت تأیید نشده است'];
    }
    $storedToken = tronadoV2NormalizeToken($report['tronado_token'] ?? '');
    $callbackToken = tronadoV2NormalizeToken($data['UniqueCode'] ?? '');
    if ($storedToken !== '' && $callbackToken !== '' && $storedToken !== $callbackToken) {
        error_log('Tronado v2 token differs from UniqueCode: ' . json_encode(['order_id' => $orderId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $wageRaw = $report['tronado_wage_pct'] ?? null;
    $sent = (float) ($report['tronado_tron_amount'] ?? 0);
    $estimated = (int) ($report['tronado_estimated_toman'] ?? 0);
    if ($wageRaw === null || $wageRaw === '' || $sent <= 0) {
        return ['ok' => false, 'reason' => 'اطلاعات اولیه فاکتور ترونادو (کارمزد/مقدار ترون) ذخیره نشده است'];
    }
    $wage = (int) $wageRaw;
    $tron = isset($data['TronAmount']) && is_numeric($data['TronAmount']) ? (float) $data['TronAmount'] : null;
    $userPaid = isset($data['UserPaidTomanAmount']) && is_numeric($data['UserPaidTomanAmount']) ? (int) $data['UserPaidTomanAmount'] : null;

    if ($wage <= 0) {
        if ($tron === null) {
            return ['ok' => false, 'reason' => 'مقدار ترون تحویل‌شده در پاسخ ترونادو وجود ندارد'];
        }
        if (round($tron, 6) + TRONADO_V2_TRX_EPSILON < round($sent, 6)) {
            return ['ok' => false, 'paid_toman' => $userPaid, 'reason' => 'ترون تحویل‌شده (' . rtrim(rtrim(number_format($tron, 6, '.', ''), '0'), '.') . ') کمتر از ترون فاکتور (' . rtrim(rtrim(number_format($sent, 6, '.', ''), '0'), '.') . ') است'];
        }
        return ['ok' => true, 'paid_toman' => $userPaid];
    }

    if ($source !== 'callback') {
        return ['ok' => false, 'reason' => 'پاسخ استعلام وضعیت ترونادو شامل مبلغ تومانی پرداخت‌شده نیست؛ نیاز به بررسی دستی'];
    }
    if ($estimated <= 0) {
        return ['ok' => false, 'reason' => 'مبلغ تخمینی فاکتور ذخیره نشده است'];
    }
    if ($userPaid === null) {
        return ['ok' => false, 'reason' => 'مبلغ پرداختی کاربر در پاسخ ترونادو وجود ندارد'];
    }
    if ($userPaid < $estimated) {
        return ['ok' => false, 'paid_toman' => $userPaid, 'reason' => 'مبلغ پرداختی کاربر (' . number_format($userPaid) . ' تومان) کمتر از مبلغ تخمینی فاکتور (' . number_format($estimated) . ' تومان) است'];
    }
    return ['ok' => true, 'paid_toman' => $userPaid];
}

function tronadoV2UpdateColumns($orderId, array $columns)
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
    try {
        $stmt = $pdo->prepare("UPDATE Payment_report SET " . implode(', ', $sets) . " WHERE id_order = ? AND payment_processing_version >= " . RX_PAYMENT_PROCESSING_VERSION);
        $stmt->execute($params);
        if (function_exists('clearSelectCacheRow')) {
            clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
        }
        return $stmt->rowCount();
    } catch (Throwable $e) {
        error_log('tronadoV2UpdateColumns failed: ' . $e->getMessage());
        return 0;
    }
}

function tronadoV2Hold(array $report, $reason, array $evaluation = [])
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return ['state' => 'error'];
    }
    $orderId = (string) $report['id_order'];
    $paidToman = isset($evaluation['paid_toman']) ? (int) $evaluation['paid_toman'] : null;
    try {
        $stmt = $pdo->prepare(
            "UPDATE Payment_report
                SET tronado_local_state = 'manual_review', tronado_review_reason = ?, tronado_paid_toman = ?
              WHERE id_order = ?
                AND payment_processing_version >= " . RX_PAYMENT_PROCESSING_VERSION . "
                AND payment_Status <> 'paid'
                AND (tronado_local_state IS NULL OR tronado_local_state NOT IN ('manual_review','dispute_review'))"
        );
        $stmt->execute([mb_substr((string) $reason, 0, 250), $paidToman, $orderId]);
        $claimed = $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('tronadoV2Hold failed: ' . $e->getMessage());
        return ['state' => 'error'];
    }
    if (function_exists('clearSelectCacheRow')) {
        clearSelectCacheRow('Payment_report', 'id_order', $orderId);
    }
    if ($claimed) {
        rxPaymentV2ReportToAdmin(
            "⚠️ پرداخت ترونادو نیازمند بررسی دستی است و سرویس/شارژ خودکار انجام نشد.\n"
            . "🛒 کد فاکتور: <code>" . rxPaymentV2Escape($orderId) . "</code>\n"
            . "👤 آیدی کاربر: <code>" . rxPaymentV2Escape($report['id_user'] ?? '') . "</code>\n"
            . "💰 مبلغ فاکتور: " . number_format((int) ($report['price'] ?? 0)) . " تومان\n"
            . ($paidToman !== null ? "💸 مبلغ پرداختی کاربر: " . number_format($paidToman) . " تومان\n" : '')
            . "📝 دلیل: " . rxPaymentV2Escape($reason)
        );
        if (function_exists('sendmessage')) {
            sendmessage($report['id_user'], "⏳ پرداخت شما در ترونادو ثبت شده اما برای تأیید نهایی به بررسی مدیریت ارجاع شد. لطفاً با پشتیبانی در ارتباط باشید.\n\n🛒 کد فاکتور: <code>" . rxPaymentV2Escape($orderId) . "</code>", null, 'HTML');
        }
    }
    return ['state' => 'held', 'claimed' => $claimed];
}

function tronadoV2Fulfill(array $report, array $data, $source)
{
    $orderId = (string) $report['id_order'];
    $localStatus = (string) ($report['payment_Status'] ?? '');
    $localDone = (int) ($report['direct_payment_done'] ?? 0) === 1;
    if ($localStatus === 'paid' && $localDone) {
        return ['state' => 'already_paid'];
    }
    if (in_array((string) ($report['tronado_local_state'] ?? ''), ['manual_review', 'dispute_review'], true)) {
        return ['state' => 'held'];
    }
    $evaluation = tronadoV2Evaluate($report, $data, $source);
    if (empty($evaluation['ok'])) {
        if (!empty($evaluation['not_paid'])) {
            return ['state' => 'not_paid'];
        }
        return tronadoV2Hold($report, $evaluation['reason'] ?? 'عدم تطابق مبلغ', $evaluation);
    }
    if ($localStatus === 'cancelled') {
        return tronadoV2Hold($report, 'فاکتور به‌صورت محلی لغو شده اما پرداخت در ترونادو تأیید شده است', $evaluation);
    }
    rxPaymentV2PrepareContext();
    $threadRow = select("topicid", "idreport", "report", "paymentreport", "select");
    $lines = ['🧾 روش: ترونادو'];
    if (!empty($evaluation['paid_toman'])) {
        $lines[] = '💸 مبلغ پرداختی کاربر: ' . number_format((int) $evaluation['paid_toman']) . ' تومان';
    }
    if (in_array($localStatus, ['expire', 'reject'], true)) {
        $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($localStatus === 'reject' ? 'رد اولیه' : 'پایان مهلت محلی');
    }
    $result = payment_confirm_paid($orderId, 'chashbackiranpay2', [
        'method' => 'ترونادو',
        'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
        'extra_lines' => $lines,
    ]);
    if (!empty($result['ok'])) {
        tronadoV2UpdateColumns($orderId, [
            'tronado_local_state' => 'completed',
            'tronado_paid_toman' => isset($evaluation['paid_toman']) ? (int) $evaluation['paid_toman'] : null,
        ]);
        return ['state' => 'fulfilled'];
    }
    return ['state' => 'already_paid'];
}

function tronadoV2HandleCallback(array $data, array $report)
{
    $orderId = (string) $report['id_order'];
    $status = isset($data['OrderStatusID']) && is_numeric($data['OrderStatusID']) ? (int) $data['OrderStatusID'] : null;
    $isPaid = $status === 30 || ($data['IsPaid'] ?? false) === true;
    rxPaymentV2PrepareContext();

    if (!$isPaid) {
        if ($status === 40 && function_exists('payment_notify_user_failed')) {
            payment_notify_user_failed($orderId, 'رسید پرداخت شما توسط ترونادو رد شد');
        } elseif ($status === 200 && function_exists('payment_mark_expired')) {
            payment_mark_expired($orderId);
        }
        return [200, 'OK'];
    }

    if ((string) ($report['payment_Status'] ?? '') === 'paid' && (int) ($report['direct_payment_done'] ?? 0) === 1) {
        return [200, 'Already processed'];
    }

    $remote = tronadoV2RemoteStatus($orderId);
    if (is_array($remote) && isset($remote['OrderStatusID'])) {
        $remoteStatus = (int) $remote['OrderStatusID'];
        if ($remoteStatus !== 30 && ($remote['IsPaid'] ?? false) !== true) {
            error_log('Tronado v2 callback contradicted by status check: ' . json_encode(['order_id' => $orderId, 'remote_status' => $remoteStatus], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [503, 'Status not confirmed'];
        }
    }

    $result = tronadoV2Fulfill($report, $data, 'callback');
    if (($result['state'] ?? '') === 'error') {
        return [500, 'Temporary error'];
    }
    return [200, 'OK'];
}

function tronadoV2RunCron()
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    $rxPaymentWindow = rx_pf_cron_window_sql();
    $now = time();
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE $rxPaymentWindow AND Payment_Method IN ('Currency Rial 2','iranpay2')
                AND payment_processing_version >= " . RX_PAYMENT_PROCESSING_VERSION . "
                AND (payment_Status IN ('Unpaid','pending','waiting','expire','reject')
                     OR (payment_Status = 'paid' AND COALESCE(direct_payment_done, 0) = 0))
                AND (tronado_local_state IS NULL OR tronado_local_state NOT IN ('manual_review','dispute_review','completed'))
                AND tronado_created_at >= ?
                AND (
                    (tronado_created_at >= ? AND (tronado_last_checked_at IS NULL OR tronado_last_checked_at <= ?))
                    OR (tronado_created_at < ? AND (tronado_last_checked_at IS NULL OR tronado_last_checked_at <= ?))
                )
              ORDER BY tronado_last_checked_at ASC, id ASC
              LIMIT 20"
        );
        $stmt->execute([
            $now - TRONADO_V2_WINDOW_SECONDS,
            $now - TRONADO_V2_RECENT_SECONDS,
            $now - TRONADO_V2_POLL_RECENT_SECONDS,
            $now - TRONADO_V2_RECENT_SECONDS,
            $now - TRONADO_V2_POLL_OLD_SECONDS,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[tronadocheck] select failed: ' . $e->getMessage());
        return;
    }
    if (empty($rows)) {
        return;
    }
    rxPaymentV2PrepareContext();
    foreach ($rows as $row) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
            return;
        }
        if (!rx_pf_cron_payment_allowed($row)) {
            continue;
        }
        tronadoV2UpdateColumns($row['id_order'], ['tronado_last_checked_at' => time()]);
        try {
            $remote = tronadoV2RemoteStatus($row['id_order']);
        } catch (Throwable $e) {
            error_log('[tronadocheck] status call threw for ' . $row['id_order'] . ': ' . $e->getMessage());
            continue;
        }
        if (!is_array($remote) || !isset($remote['OrderStatusID'])) {
            continue;
        }
        $status = (int) $remote['OrderStatusID'];
        if ($status === 30 || ($remote['IsPaid'] ?? false) === true) {
            if (!isset($remote['PaymentId']) && !isset($remote['PaymentID'])) {
                $remote['PaymentId'] = (string) $row['id_order'];
            }
            try {
                tronadoV2Fulfill($row, $remote, 'status');
            } catch (Throwable $e) {
                error_log('[tronadocheck] fulfil threw for ' . $row['id_order'] . ': ' . $e->getMessage());
            }
        } elseif ($status === 40 && (string) $row['payment_Status'] !== 'reject' && function_exists('payment_notify_user_failed')) {
            payment_notify_user_failed((string) $row['id_order'], 'رسید پرداخت شما توسط ترونادو رد شد');
        } elseif ($status === 200 && (string) $row['payment_Status'] !== 'expire' && function_exists('payment_mark_expired')) {
            payment_mark_expired((string) $row['id_order']);
        }
    }
}

function tronadoV2HandleDispute(array $data)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return [500, 'Temporary error'];
    }
    $event = isset($data['Event']) && is_scalar($data['Event']) ? (string) $data['Event'] : '';
    $disputeId = isset($data['DisputeId']) && is_numeric($data['DisputeId']) ? (int) $data['DisputeId'] : 0;
    $eventId = isset($data['EventId']) && is_scalar($data['EventId']) ? substr(trim((string) $data['EventId']), 0, 64) : '';
    $paymentId = isset($data['PaymentId']) && is_scalar($data['PaymentId']) ? trim((string) $data['PaymentId']) : '';
    if ($event !== 'DisputeAccepted') {
        return [200, 'Ignored'];
    }
    if ($disputeId <= 0 || $eventId === '' || !preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $paymentId)) {
        return [400, 'Invalid dispute payload'];
    }
    $report = select("Payment_report", "*", "id_order", $paymentId, "select", ['cache' => false]);
    if (!is_array($report) || !rxPaymentV2Is($report)) {
        return [200, 'Ignored'];
    }
    $outcome = isset($data['Outcome']) && is_scalar($data['Outcome']) ? substr((string) $data['Outcome'], 0, 32) : '';
    try {
        $insert = $pdo->prepare("INSERT IGNORE INTO tronado_dispute_event (dispute_id, event_id, payment_id, outcome, payload, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([$disputeId, $eventId, $paymentId, $outcome, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time()]);
        $inserted = $insert->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('Tronado dispute insert failed: ' . $e->getMessage());
        return [500, 'Temporary error'];
    }
    if (!$inserted) {
        return [200, 'Duplicate'];
    }
    tronadoV2UpdateColumns($paymentId, [
        'tronado_local_state' => 'dispute_review',
        'tronado_review_reason' => mb_substr('dispute:' . $outcome . ':' . (string) ($data['DisputeType'] ?? ''), 0, 250),
    ]);
    $alreadyPaid = (string) ($report['payment_Status'] ?? '') === 'paid';
    rxPaymentV2PrepareContext();
    rxPaymentV2ReportToAdmin(
        "⚠️ اعتراض پذیرفته‌شده ترونادو — نیاز به بررسی دستی (هیچ برگشت مالی خودکار انجام نشد)\n"
        . "🛒 کد فاکتور: <code>" . rxPaymentV2Escape($paymentId) . "</code>\n"
        . "👤 آیدی کاربر: <code>" . rxPaymentV2Escape($report['id_user'] ?? '') . "</code>\n"
        . "🆔 شناسه اعتراض: <code>" . $disputeId . "</code>\n"
        . "📌 نوع: " . rxPaymentV2Escape($data['DisputeTypeTitle'] ?? ($data['DisputeType'] ?? '')) . "\n"
        . "🔁 نتیجه: <b>" . rxPaymentV2Escape($outcome) . "</b>\n"
        . "💰 مبلغ فاکتور ما: " . number_format((int) ($report['price'] ?? 0)) . " تومان\n"
        . "💳 مبلغ تومانی قبل: " . number_format((int) ($data['OriginalUserMustPayToman'] ?? 0)) . " ← بعد: " . number_format((int) ($data['UserMustPayToman'] ?? 0)) . " (تفاوت: " . number_format((int) ($data['UserMustPayTomanDelta'] ?? 0)) . ")\n"
        . "🪙 ترون قبل: " . rxPaymentV2Escape($data['OriginalTronAmount'] ?? '') . " ← بعد: " . rxPaymentV2Escape($data['TronAmount'] ?? '') . "\n"
        . "📦 وضعیت فاکتور در ربات: " . ($alreadyPaid ? 'پرداخت‌شده و تحویل‌شده' : 'هنوز تحویل نشده')
    );
    return [200, 'OK'];
}

function rxStarsPreCheckoutV2(array $query)
{
    $orderId = (string) ($query['invoice_payload'] ?? '');
    if ($orderId === '') {
        return false;
    }
    $report = select("Payment_report", "*", "id_order", $orderId, "select", ['cache' => false]);
    if (!rxPaymentV2Is($report) || (string) ($report['Payment_Method'] ?? '') !== 'Star Telegram') {
        return false;
    }
    $problem = '';
    if ((string) ($report['id_user'] ?? '') !== (string) ($query['from']['id'] ?? '')) {
        $problem = 'این فاکتور متعلق به حساب شما نیست.';
    } elseif (!in_array((string) ($report['payment_Status'] ?? ''), ['Unpaid', 'pending', 'waiting'], true)) {
        $problem = 'این فاکتور دیگر قابل پرداخت نیست. لطفاً فاکتور جدید بسازید.';
    } elseif ((string) ($query['currency'] ?? '') !== 'XTR') {
        $problem = 'ارز پرداخت نامعتبر است.';
    } elseif ((int) ($report['stars_amount'] ?? 0) <= 0 || (int) ($query['total_amount'] ?? 0) !== (int) $report['stars_amount']) {
        $problem = 'مبلغ پرداخت با فاکتور مطابقت ندارد.';
    }
    $answer = ['pre_checkout_query_id' => $query['id'], 'ok' => $problem === ''];
    if ($problem !== '') {
        $answer['error_message'] = $problem;
    }
    telegram('answerPreCheckoutQuery', $answer);
    return true;
}

function rxStarsHandleSuccessfulPayment(array $update)
{
    global $pdo;
    $message = $update['message'] ?? [];
    $payment = $message['successful_payment'] ?? null;
    if (!is_array($payment) || !($pdo instanceof PDO)) {
        return false;
    }
    $orderId = (string) ($payment['invoice_payload'] ?? '');
    $report = $orderId === '' ? null : select("Payment_report", "*", "id_order", $orderId, "select", ['cache' => false]);
    if (!rxPaymentV2Is($report) || (string) ($report['Payment_Method'] ?? '') !== 'Star Telegram') {
        return false;
    }
    rxPaymentV2PrepareContext();
    $chargeId = (string) ($payment['telegram_payment_charge_id'] ?? '');
    $problem = '';
    if ((string) ($report['id_user'] ?? '') !== (string) ($message['from']['id'] ?? '')) {
        $problem = 'کاربر پرداخت‌کننده با صاحب فاکتور متفاوت است';
    } elseif ((string) ($payment['currency'] ?? '') !== 'XTR') {
        $problem = 'ارز پرداخت XTR نیست';
    } elseif ((int) ($report['stars_amount'] ?? 0) <= 0 || (int) ($payment['total_amount'] ?? 0) !== (int) $report['stars_amount']) {
        $problem = 'تعداد استارز پرداخت‌شده (' . (int) ($payment['total_amount'] ?? 0) . ') با فاکتور (' . (int) ($report['stars_amount'] ?? 0) . ') مطابقت ندارد';
    } elseif ((string) ($report['payment_Status'] ?? '') === 'cancelled') {
        $problem = 'فاکتور قبل از پرداخت لغو شده بود';
    }
    if ($problem !== '') {
        rxPaymentV2ReportToAdmin(
            "⚠️ پرداخت استارز دریافت شد اما تأیید خودکار انجام نشد.\n"
            . "🛒 کد فاکتور: <code>" . rxPaymentV2Escape($orderId) . "</code>\n"
            . "👤 آیدی کاربر: <code>" . rxPaymentV2Escape($message['from']['id'] ?? '') . "</code>\n"
            . "🔖 شناسه تراکنش تلگرام: <code>" . rxPaymentV2Escape($chargeId) . "</code>\n"
            . "📝 دلیل: " . rxPaymentV2Escape($problem)
        );
        return true;
    }
    try {
        $mark = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = ? WHERE id_order = ? AND payment_Status <> 'paid' AND payment_processing_version >= " . RX_PAYMENT_PROCESSING_VERSION);
        $mark->execute([json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $orderId]);
    } catch (Throwable $e) {
        error_log('Stars payment note failed: ' . $e->getMessage());
    }
    $threadRow = select("topicid", "idreport", "report", "paymentreport", "select");
    payment_confirm_paid($orderId, 'chashbackstar', [
        'method' => 'Telegram Stars',
        'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
        'extra_lines' => [
            '⭐️ تعداد استارز: ' . (int) $payment['total_amount'],
            '🔖 شناسه تراکنش تلگرام: ' . $chargeId,
        ],
    ]);
    return true;
}

function tronadoAdminMaskSecret($secret)
{
    $secret = trim((string) $secret);
    if ($secret === '') {
        return '';
    }
    return strlen($secret) < 8 ? '••••' : '••••' . substr($secret, -4);
}

function tronadoCallbackSetup()
{
    global $domainhosts;
    $raw = trim(preg_replace('#^https?://#i', '', trim((string) $domainhosts)), '/');
    $host = $raw === '' ? '' : strtolower((string) parse_url('https://' . $raw, PHP_URL_HOST));
    $valid = $host !== ''
        && $host !== 'localhost'
        && filter_var($host, FILTER_VALIDATE_IP) === false
        && preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1;
    if (!$valid) {
        return ['ok' => false, 'domain' => '', 'ipn' => '', 'dispute' => '', 'configured' => $raw];
    }
    $base = 'https://' . $raw;
    return [
        'ok' => true,
        'domain' => $host,
        'ipn' => $base . '/payment/tronado.php',
        'dispute' => $base . '/payment/tronado_dispute.php',
        'configured' => $raw,
    ];
}

function tronadoAdminSetupText()
{
    $apiRow = select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
    $apiKey = is_array($apiRow) ? trim((string) ($apiRow['ValuePay'] ?? '')) : '';
    $signRow = select("PaySetting", "ValuePay", "NamePay", "ipnsigningkeytronado", "select");
    $signKey = is_array($signRow) ? trim((string) ($signRow['ValuePay'] ?? '')) : '';
    $setup = tronadoCallbackSetup();
    $e = 'rxPaymentV2Escape';

    $lines = ["📘 <b>راهنمای اتصال ترونادو</b>", ''];
    $lines[] = $apiKey === ''
        ? '🔑 وضعیت API Key: ❌ ثبت نشده'
        : '🔑 وضعیت API Key: ✅ ثبت شده (' . $e(tronadoAdminMaskSecret($apiKey)) . ')؛ صحت آن از طرف ترونادو تأیید نشده است.';
    $lines[] = $signKey === ''
        ? '🔏 کلید امضای IPN: ⚠️ ثبت نشده؛ تا زمان ثبت، تمام Callbackهای ترونادو رد می‌شوند.'
        : '🔏 کلید امضای IPN: ✅ ثبت شده (' . $e(tronadoAdminMaskSecret($signKey)) . ')';
    $lines[] = 'کلید امضای IPN با API Key جداست و از دکمه «🔏 ثبت کلید امضای IPN ترونادو» تنظیم می‌شود؛ ثبت یا تغییر API Key آن را تغییر نمی‌دهد.';
    $lines[] = '';
    if ($setup['ok']) {
        $lines[] = '🌐 دامنه‌ی Callback که باید ثبت شود:';
        $lines[] = '<code>' . $e($setup['domain']) . '</code>';
        $lines[] = '';
        $lines[] = '📥 آدرس IPN پرداخت:';
        $lines[] = '<code>' . $e($setup['ipn']) . '</code>';
        $lines[] = '';
        $lines[] = '⚖️ آدرس Callback اعتراض (Dispute، اختیاری):';
        $lines[] = '<code>' . $e($setup['dispute']) . '</code>';
    } else {
        $lines[] = '❌ دامنه‌ی ربات تنظیم نشده یا معتبر نیست' . ($setup['configured'] !== '' ? ' (' . $e($setup['configured']) . ')' : '') . '؛ آدرس Callback ساخته نشد. ابتدا دامنه‌ی معتبر (نه IP و نه localhost) را در تنظیمات ربات ثبت کنید.';
    }
    $lines[] = '';
    $lines[] = '⚠️ دامنه‌ی Callback باید پیش از استفاده توسط پشتیبانی ترونادو (@TronadoSupp) ثبت و تأیید شود؛ ربات نمی‌تواند این کار را خودکار انجام دهد و بدون آن، Callback پرداخت‌ها ارسال نمی‌شود.';
    $lines[] = '🔒 هر دو آدرس باید با HTTPS در دسترس باشند.';
    $lines[] = '📌 برای فعال‌سازی Callback اعتراض، آدرس بالا را در مینی‌اپ ترونادو (کسب‌وکار ← تنظیمات) ثبت کنید؛ دامنه‌ی آن هم باید در فهرست دامنه‌های مجاز باشد.';
    return implode("\n", $lines);
}

function tronadoAdminSetupKeyboard()
{
    $setup = tronadoCallbackSetup();
    if (!$setup['ok']) {
        return null;
    }
    return json_encode(['inline_keyboard' => [
        [['text' => '📋 کپی دامنه', 'copy_text' => ['text' => $setup['domain']]]],
        [['text' => '📋 کپی آدرس IPN پرداخت', 'copy_text' => ['text' => $setup['ipn']]]],
        [['text' => '📋 کپی آدرس Callback اعتراض', 'copy_text' => ['text' => $setup['dispute']]]],
    ]], JSON_UNESCAPED_UNICODE);
}
