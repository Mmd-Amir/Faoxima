<?php

namespace Faoxima\Payments\Gateways\Tronado;

use PDO;
use Throwable;

final class Settlement
{
    public static function isCompletedLegacyEvent(\mysqli $connect, string $paymentId, string $dedupeKey): bool
    {
        $stmt = $connect->prepare('SELECT dec_not_confirmed, direct_payment_done FROM Payment_report WHERE id_order = ? LIMIT 1');
        $stmt->bind_param('s', $paymentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) && ($row['dec_not_confirmed'] ?? null) === $dedupeKey && (int) ($row['direct_payment_done'] ?? 0) === 1;
    }

    public static function claimLegacyPaid(\mysqli $connect, string $paymentId, string $dedupeKey): bool
    {
        if (\Faoxima\Payments\Settlement\CallbackClaims::claimTronado($connect, $paymentId, $dedupeKey) > 0) {
            return true;
        }
        $stmt = $connect->prepare('SELECT direct_payment_done FROM Payment_report WHERE id_order = ? LIMIT 1');
        $stmt->bind_param('s', $paymentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) && (int) ($row['direct_payment_done'] ?? 0) === 0;
    }

    public static function recordLegacyEvent($connect, string $paymentId, string $dedupeKey): void
    {
        $statement = $connect->prepare('UPDATE Payment_report SET dec_not_confirmed = ? WHERE id_order = ?');
        $statement->bind_param('ss', $dedupeKey, $paymentId);
        $statement->execute();
        $statement->close();
    }

    public static function parseToman($value)
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

    public static function normalizeToken($token)
    {
        return strtolower(str_replace('-', '', trim((string) $token)));
        }

    public static function store($runtimePdo, $orderId, array $payment)
    {
        $token = is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'extractPaymentToken']) ? \Faoxima\Payments\Gateways\Tronado\Client::extractPaymentToken($payment) : trim((string) ($payment['Token'] ?? ''));
        $estimated = $payment['EstimatedTomanAmount'] ?? ($payment['Data']['EstimatedTomanAmount'] ?? null);
        $tron = isset($payment['rx_tron_amount']) && is_numeric($payment['rx_tron_amount']) ? (float) $payment['rx_tron_amount'] : 0.0;
        $wage = isset($payment['rx_wage']) && is_numeric($payment['rx_wage']) ? (int) $payment['rx_wage'] : null;
        return \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Mark($runtimePdo, $orderId, [
            'tronado_tron_amount' => $tron,
            'tronado_wage_pct' => $wage,
            'tronado_estimated_toman' => self::parseToman($estimated),
            'tronado_token' => substr($token, 0, 80),
            'tronado_created_at' => time(),
            'tronado_local_state' => 'awaiting_payment',
        ]);
        }

    public static function remoteStatus($paymentId)
    {
        $config = defined('TRONADO_API_CONFIGURATION') ? TRONADO_API_CONFIGURATION : [];
        $base = rtrim((string) ($config['base_url'] ?? 'https://bot.tronado.cloud'), '/');
        $path = '/' . ltrim((string) ($config['get_status_by_payment_id_path'] ?? '/Order/GetStatusByPaymentID'), '/');
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
        $apiKey = is_array($row) ? trim((string) ($row['ValuePay'] ?? '')) : '';
        if ($apiKey === '' || !is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'curlJson'])) {
            return null;
        }
        return \Faoxima\Payments\Gateways\Tronado\Client::curlJson($base . $path, ['Id' => (string) $paymentId], $apiKey);
        }

    public static function evaluate(array $report, array $data, $source)
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
        $storedToken = self::normalizeToken($report['tronado_token'] ?? '');
        $callbackToken = self::normalizeToken($data['UniqueCode'] ?? '');
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

    public static function updateColumns($runtimePdo, $orderId, array $columns)
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
        try {
            $stmt = $pdo->prepare("UPDATE Payment_report SET " . implode(', ', $sets) . " WHERE id_order = ? AND payment_processing_version >= " . RX_PAYMENT_PROCESSING_VERSION);
            $stmt->execute($params);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('tronadoV2UpdateColumns failed: ' . $e->getMessage());
            return 0;
        }
        }

    public static function hold($runtimePdo, array $report, $reason, array $evaluation = [])
    {
        $pdo = $runtimePdo;
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
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', $orderId);
        }
        if ($claimed) {
            \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2ReportToAdmin(
                "⚠️ پرداخت ترونادو نیازمند بررسی دستی است و سرویس/شارژ خودکار انجام نشد.\n"
                . "🛒 کد فاکتور: <code>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($orderId) . "</code>\n"
                . "👤 آیدی کاربر: <code>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($report['id_user'] ?? '') . "</code>\n"
                . "💰 مبلغ فاکتور: " . number_format((int) ($report['price'] ?? 0)) . " تومان\n"
                . ($paidToman !== null ? "💸 مبلغ پرداختی کاربر: " . number_format($paidToman) . " تومان\n" : '')
                . "📝 دلیل: " . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($reason)
            );
            if (function_exists('sendmessage')) {
                sendmessage($report['id_user'], \Faoxima\Telegram\Support\MessageTemplates::get('payment_tronado_manual_review', [], ['order_id' => \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($orderId)]), null, 'HTML');
            }
        }
        return ['state' => 'held', 'claimed' => $claimed];
        }

    public static function fulfill($runtimePdo, $runtimeSetting, array $report, array $data, $source)
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
        $evaluation = self::evaluate($report, $data, $source);
        if (empty($evaluation['ok'])) {
            if (!empty($evaluation['not_paid'])) {
                return ['state' => 'not_paid'];
            }
            return self::hold($runtimePdo, $report, $evaluation['reason'] ?? 'عدم تطابق مبلغ', $evaluation);
        }
        if ($localStatus === 'cancelled') {
            return self::hold($runtimePdo, $report, 'فاکتور به‌صورت محلی لغو شده اما پرداخت در ترونادو تأیید شده است', $evaluation);
        }
        $preparedSetting = \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2PrepareContext();
        if (!is_array($runtimeSetting)) {
            $runtimeSetting = $preparedSetting;
        }
        $threadRow = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select");
        $lines = ['🧾 روش: ترونادو'];
        if (!empty($evaluation['paid_toman'])) {
            $lines[] = '💸 مبلغ پرداختی کاربر: ' . number_format((int) $evaluation['paid_toman']) . ' تومان';
        }
        if (in_array($localStatus, ['expire', 'reject'], true)) {
            $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($localStatus === 'reject' ? 'رد اولیه' : 'پایان مهلت محلی');
        }
        $result = \Faoxima\Payments\Settlement\Confirmation::confirmPaid($runtimePdo, $runtimeSetting, $orderId, 'chashbackiranpay2', [
            'method' => 'ترونادو',
            'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
            'extra_lines' => $lines,
        ]);
        if (!empty($result['ok'])) {
            self::updateColumns($runtimePdo, $orderId, [
                'tronado_local_state' => 'completed',
                'tronado_paid_toman' => isset($evaluation['paid_toman']) ? (int) $evaluation['paid_toman'] : null,
            ]);
            return ['state' => 'fulfilled'];
        }
        return ['state' => !empty($result['verified']) ? 'pending_fulfillment' : 'error'];
        }

    public static function handleCallback($runtimePdo, $runtimeSetting, array $data, array $report)
    {
        $orderId = (string) $report['id_order'];
        $status = isset($data['OrderStatusID']) && is_numeric($data['OrderStatusID']) ? (int) $data['OrderStatusID'] : null;
        $isPaid = $status === 30 || ($data['IsPaid'] ?? false) === true;
        \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2PrepareContext();

        if (!$isPaid) {
            if ($status === 40 && method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_notify_user_failed')) {
                \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_notify_user_failed($orderId, 'رسید پرداخت شما توسط ترونادو رد شد');
            } elseif ($status === 200 && method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_mark_expired')) {
                \Faoxima\Payments\Settlement\Confirmation::markExpired($runtimePdo, $orderId);
            }
            return [200, 'OK'];
        }

        if ((string) ($report['payment_Status'] ?? '') === 'paid' && (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return [200, 'Already processed'];
        }

        $remote = self::remoteStatus($orderId);
        if (is_array($remote) && isset($remote['OrderStatusID'])) {
            $remoteStatus = (int) $remote['OrderStatusID'];
            if ($remoteStatus !== 30 && ($remote['IsPaid'] ?? false) !== true) {
                error_log('Tronado v2 callback contradicted by status check: ' . json_encode(['order_id' => $orderId, 'remote_status' => $remoteStatus], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return [503, 'Status not confirmed'];
            }
        }

        $result = self::fulfill($runtimePdo, $runtimeSetting, $report, $data, 'callback');
        if (($result['state'] ?? '') === 'error') {
            return [500, 'Temporary error'];
        }
        return [200, 'OK'];
        }

    public static function runCron($runtimePdo, $runtimeSetting)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        $rxPaymentWindow = \Faoxima\Payments\Fulfillment\PaymentPolicy::cronWindowSql();
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
        \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2PrepareContext();
        foreach ($rows as $row) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                return;
            }
            if (!\Faoxima\Payments\Fulfillment\PaymentPolicy::cronPaymentAllowed($row)) {
                continue;
            }
            self::updateColumns($runtimePdo, $row['id_order'], ['tronado_last_checked_at' => time()]);
            try {
                $remote = self::remoteStatus($row['id_order']);
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
                    self::fulfill($runtimePdo, $runtimeSetting, $row, $remote, 'status');
                } catch (Throwable $e) {
                    error_log('[tronadocheck] fulfil threw for ' . $row['id_order'] . ': ' . $e->getMessage());
                }
            } elseif ($status === 40 && (string) $row['payment_Status'] !== 'reject' && method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_notify_user_failed')) {
                \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_notify_user_failed((string) $row['id_order'], 'رسید پرداخت شما توسط ترونادو رد شد');
            } elseif ($status === 200 && (string) $row['payment_Status'] !== 'expire' && method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_mark_expired')) {
                \Faoxima\Payments\Settlement\Confirmation::markExpired($runtimePdo, (string) $row['id_order']);
            }
        }
        }

    public static function handleDispute($runtimePdo, array $data)
    {
        $pdo = $runtimePdo;
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
        $report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $paymentId, "select", ['cache' => false]);
        if (!is_array($report) || ($report['Payment_Method'] ?? '') !== 'iranpay2'
            || !\Faoxima\Payments\Settlement\GatewayFlows::paymentV2Is($report)) {
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
        self::updateColumns($runtimePdo, $paymentId, [
            'tronado_local_state' => 'dispute_review',
            'tronado_review_reason' => mb_substr('dispute:' . $outcome . ':' . (string) ($data['DisputeType'] ?? ''), 0, 250),
        ]);
        $alreadyPaid = (string) ($report['payment_Status'] ?? '') === 'paid';
        \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2PrepareContext();
        \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2ReportToAdmin(
            "⚠️ اعتراض پذیرفته‌شده ترونادو — نیاز به بررسی دستی (هیچ برگشت مالی خودکار انجام نشد)\n"
            . "🛒 کد فاکتور: <code>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($paymentId) . "</code>\n"
            . "👤 آیدی کاربر: <code>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($report['id_user'] ?? '') . "</code>\n"
            . "🆔 شناسه اعتراض: <code>" . $disputeId . "</code>\n"
            . "📌 نوع: " . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($data['DisputeTypeTitle'] ?? ($data['DisputeType'] ?? '')) . "\n"
            . "🔁 نتیجه: <b>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($outcome) . "</b>\n"
            . "💰 مبلغ فاکتور ما: " . number_format((int) ($report['price'] ?? 0)) . " تومان\n"
            . "💳 مبلغ تومانی قبل: " . number_format((int) ($data['OriginalUserMustPayToman'] ?? 0)) . " ← بعد: " . number_format((int) ($data['UserMustPayToman'] ?? 0)) . " (تفاوت: " . number_format((int) ($data['UserMustPayTomanDelta'] ?? 0)) . ")\n"
            . "🪙 ترون قبل: " . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($data['OriginalTronAmount'] ?? '') . " ← بعد: " . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($data['TronAmount'] ?? '') . "\n"
            . "📦 وضعیت فاکتور در ربات: " . ($alreadyPaid ? 'پرداخت‌شده و تحویل‌شده' : 'هنوز تحویل نشده')
        );
        return [200, 'OK'];
        }
}
