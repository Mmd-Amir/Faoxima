<?php

namespace Faoxima\Payments\Receipts;

use PDO;

final class ManualReview
{
    public static function receiptScopeSql(): string
    {
        return "Payment_Method = 'cart to cart'";
    }

    public static function forappScopeSql($runtimePdo, string $alias = 'Payment_report'): string
    {
        if (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_has_schema') && !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return '1 = 0';
        }
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
        if ($alias === '') {
            $alias = 'Payment_report';
        }
        return "EXISTS (SELECT 1 FROM card_sms_payment csp_scope WHERE csp_scope.id_order = {$alias}.id_order)";
    }

    public static function receiptListScopeSql($runtimePdo): string
    {
        $scope = self::receiptScopeSql();
        if (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_has_schema') && !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return $scope;
        }
        return $scope . ' AND NOT ' . self::forappScopeSql($runtimePdo, 'Payment_report');
    }

    public static function receiptGet($runtimePdo, string $orderId, bool $useCache = true)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) return false;
        $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :o AND " . self::receiptScopeSql() . " LIMIT 1");
        $stmt->bindValue(':o', $orderId, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? false : $row;
    }

    public static function receiptConfirm($runtimePdo, $runtimeSetting, string $orderId, array $ctx = []): array
    {
        $pdo = $runtimePdo; $setting = $runtimeSetting;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }

        $actorId = $ctx['actor_id'] ?? null;
        $actorLabel = (string)($ctx['actor_label'] ?? '');
        $skipTelegramSync = !empty($ctx['skip_telegram_sync']);

        $report = self::receiptGet($runtimePdo, $orderId, false);
        if ($report === false) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $status = (string)($report['payment_Status'] ?? '');
        if ($status === 'paid') {
            return ['ok' => false, 'reason' => 'already_paid', 'report' => $report];
        }
        if (in_array($status, ['reject', 'expire', 'cancelled'], true)) {
            return ['ok' => false, 'reason' => 'already_final', 'report' => $report];
        }
        if (in_array($status, ['processing', 'reconciling'], true)) {
            return ['ok' => false, 'reason' => 'processing', 'report' => $report];
        }
        if ($status === 'manual_review') {
            return ['ok' => false, 'reason' => 'manual_review', 'report' => $report];
        }
        if ($status !== 'waiting') {
            return ['ok' => false, 'reason' => 'not_waiting', 'report' => $report];
        }

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Payment_report WHERE id_user = :uid AND payment_Status != 'paid' AND payment_Status != 'Unpaid' "
            . "AND payment_Status != 'expire' AND payment_Status != 'reject' AND payment_Status != 'cancelled' AND "
            . "(id_invoice LIKE '%getconfigafterpay%' OR id_invoice LIKE '%getextenduser%' OR id_invoice LIKE '%getextravolumeuser%' OR id_invoice LIKE '%getextratimeuser%')"
        );
        $countStmt->bindValue(':uid', $report['id_user'], PDO::PARAM_STR);
        $countStmt->execute();
        $countPay = (int)$countStmt->fetchColumn();
        $typePay = explode('|', (string)$report['id_invoice']);
        if ($countPay > 0 && !in_array($typePay[0], ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'], true)) {
            return ['ok' => false, 'reason' => 'purchase_receipts_pending', 'report' => $report];
        }

        $sbvReason = trim((string) ($ctx['override_reason'] ?? ''));
        if (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_confirm_guard')) {
            $sbvGuard = \Faoxima\Payments\Receipts\ReviewQueries::confirmGuard($runtimePdo, (string) $report['id_order'], $sbvReason);
            if ($sbvGuard !== null) {
                return ['ok' => false, 'reason' => $sbvGuard, 'report' => $report];
            }
        }

        $claim = \Faoxima\Payments\Fulfillment\Claims::claim($runtimePdo, (string) $report['id_order'], ['from' => ['waiting'], 'to' => 'processing']);
        $claimStatus = (string) ($claim['status'] ?? 'error');
        if ($claimStatus === 'error') {
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_CONFIRM_DB_ERROR', 'Atomic claim failed', [
                    'id_order' => $report['id_order'],
                    'err' => (string) ($claim['error'] ?? $claim['reason'] ?? ''),
                ]);
            }
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if ($claimStatus === 'already_completed') {
            return ['ok' => false, 'reason' => 'already_paid', 'report' => $report];
        }
        if ($claimStatus === 'not_found') {
            return ['ok' => false, 'reason' => 'not_found', 'report' => $report];
        }
        if (in_array($claimStatus, ['busy', 'requires_reconciliation'], true)) {
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_CONFIRM_IN_PROGRESS', 'Confirm found the receipt still processing', [
                    'id_order' => $report['id_order'],
                    'actor_id' => $actorId,
                    'claim' => $claimStatus,
                ]);
            }
            return ['ok' => false, 'reason' => 'processing', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
        }
        if ($claimStatus === 'manual_review') {
            return ['ok' => false, 'reason' => 'manual_review', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
        }
        if ($claimStatus !== 'claimed') {
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_CONFIRM_RACE', 'Confirm raced with another actor; dropping duplicate', [
                    'id_order' => $report['id_order'],
                    'actor_id' => $actorId,
                    'claim' => $claimStatus,
                ]);
            }
            return ['ok' => false, 'reason' => 'race_lost', 'report' => $report];
        }

        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'DirectPayment')) {
            \Faoxima\Payments\Fulfillment\Settlement::settle($runtimePdo, (string) $report['id_order'], (string) $claim['token'], \Faoxima\Payments\Fulfillment\Claims::result('retryable', 'direct_payment_unavailable'), 'waiting', 'waiting');
            return ['ok' => false, 'reason' => 'fulfillment_failed', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
        }

        $run = \Faoxima\Services\Fulfillment\Delivery::runClaimed($runtimePdo, (string) $report['id_order'], (string) $claim['token'], 'images.jpg', 'waiting');
        if (empty($run['finalized'])) {
            $runResult = (array) ($run['result'] ?? []);
            $settle = (string) ($run['settle'] ?? '');
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_CONFIRM_FULFILL_FAILED', 'Confirm claimed order but DirectPayment did not complete', [
                    'id_order' => $orderId,
                    'actor_id' => $actorId,
                    'payment_type' => (string) ($report['Payment_Method'] ?? ''),
                    'operation' => (string) ($typePay[0] ?? ''),
                    'result' => (string) ($runResult['status'] ?? ''),
                    'reason' => (string) ($runResult['reason'] ?? ''),
                    'settle' => $settle,
                ]);
            }
            if (!empty($runResult['already_completed']) || $settle === 'paid') {
                return ['ok' => false, 'reason' => 'already_paid', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
            }
            if ($settle === 'manual_review') {
                return ['ok' => false, 'reason' => 'manual_review', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
            }
            if ($settle === 'reconciling' || $settle === 'error') {
                return ['ok' => false, 'reason' => 'fulfillment_reconciling', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
            }
            return ['ok' => false, 'reason' => 'fulfillment_failed', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
        }

        $finalReport = self::receiptGet($runtimePdo, $orderId, false);

        if (is_callable([\Faoxima\Payments\Receipts\Approval::class, 'afterExternalConfirm'])) {
            \Faoxima\Payments\Receipts\Approval::afterExternalConfirm($runtimePdo, $orderId, $actorLabel, $sbvReason);
        }

        if (!empty($finalReport['card_photo_file_id']) && !empty($finalReport['card_last4'])) {
            $vcUid = (string)$finalReport['id_user'];
            $vcL4 = (string)$finalReport['card_last4'];
            $vcChk = $pdo->prepare("SELECT id FROM verified_cards WHERE user_id = ? AND last4 = ? LIMIT 1");
            $vcChk->execute([$vcUid, $vcL4]);
            if ($vcChk->rowCount() === 0) {
                $pdo->prepare("INSERT INTO verified_cards (user_id, last4, created_at) VALUES (?, ?, ?)")
                    ->execute([$vcUid, $vcL4, time()]);
            }
        }

        $balanceUser = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', '*', 'id', $finalReport['id_user'], 'select') : false;
        if (!is_array($balanceUser)) $balanceUser = ['id' => $finalReport['id_user'], 'username' => '', 'Balance' => 0];

        $cashbackAmount = 0;
        $pricecashbackRow = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'chashbackcart', 'select') : false;
        $pricecashback = is_array($pricecashbackRow) ? (string)($pricecashbackRow['ValuePay'] ?? '0') : '0';
        $cashbackEligible = !method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_cashbackEligibleForKey')
            || \Faoxima\Cashback\Eligibility::cashbackEligibleForKey($runtimePdo, 'chashbackcart', $balanceUser['register'] ?? null, $finalReport['id_invoice'] ?? null, $balanceUser['id'] ?? null, $finalReport['id_order'] ?? null);
        if ($cashbackEligible && $pricecashback !== '0' && $pricecashback !== '') {
            $cashbackAmount = (int) floor(((int)$finalReport['price'] * (int)$pricecashback) / 100);
            if (\Faoxima\Cashback\Credits::creditOnce($runtimePdo, $finalReport['id_order'], $balanceUser['id'], $cashbackAmount, 'chashbackcart', 'هدیه بازگشت وجه کارت به کارت') !== 'credited') {
                $cashbackAmount = 0;
            }
            if ($cashbackAmount > 0 && function_exists('sendmessage')) {
                sendmessage($balanceUser['id'], "🎁 کاربر عزیز مبلغ " . (method_exists(\Faoxima\Core\Support\Formatting::class, 'rxFormatToman') ? \Faoxima\Core\Support\Formatting::rxFormatToman($cashbackAmount) : number_format($cashbackAmount)) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.", null, 'HTML');
            }
            $balanceUser = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', '*', 'id', $finalReport['id_user'], 'select', ['cache' => false]) : $balanceUser;
        }

        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            \Faoxima\Core\Database\Db::update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $finalReport['id_order']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', 'none', 'id', $balanceUser['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', 'none', 'id', $balanceUser['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_four', 'none', 'id', $balanceUser['id']);
        }

        $formatPriceCart = number_format((int)$finalReport['price']);
        $paymentreports = null;
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $topicRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select');
            $paymentreports = is_array($topicRow) ? ($topicRow['idreport'] ?? null) : null;
        }
        if (is_array($setting ?? null) && !empty($setting['Channel_Report']) && function_exists('telegram')) {
            $textReport = "📣 یک ادمین رسید پرداخت  را تایید کرد.\n\n"
                . "اطلاعات :\n"
                . "<blockquote>💸 روش پرداخت : {$finalReport['Payment_Method']}</blockquote>\n"
                . "<blockquote>👤آیدی عددی  ادمین تایید کننده : " . htmlspecialchars((string)$actorLabel, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
                . "<blockquote>💰 مبلغ پرداخت : {$formatPriceCart}</blockquote>\n"
                . "<blockquote>👤 ایدی عددی کاربر : <code>{$finalReport['id_user']}</code></blockquote>\n"
                . "<blockquote>👤 نام کاربری کاربر : @{$balanceUser['username']}</blockquote>\n"
                . "<blockquote>کد پیگیری پرداحت : {$orderId}</blockquote>";
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => $textReport,
                'parse_mode' => 'HTML',
            ]);
        }

        if (!$skipTelegramSync) {
            $confirmKb = json_encode([
                'inline_keyboard' => [
                    [['text' => '✅ تایید شده', 'callback_data' => 'confirmpaid', 'style' => 'success', '_rxkeep' => 1]],
                    [['text' => '⚙️ مدیریت کاربر', 'callback_data' => 'manageuser_' . $finalReport['id_user'], 'style' => 'primary', '_rxkeep' => 1]],
                ],
            ]);
            $balanceFmt = method_exists(\Faoxima\Core\Support\Formatting::class, 'rxFormatToman') ? \Faoxima\Core\Support\Formatting::rxFormatToman($balanceUser['Balance'] ?? 0) : number_format((int)($balanceUser['Balance'] ?? 0));
            $textConfirm = "✅ پرداخت توسط ادمین تایید شده\n"
                . "👤 شناسه کاربر: <code>{$finalReport['id_user']}</code>\n"
                . "🛒 کد پیگیری پرداخت: {$orderId}\n"
                . "⚜️ نام کاربری: @{$balanceUser['username']}\n"
                . "💎 موجودی بعد از تایید : {$balanceFmt}\n"
                . "💸 مبلغ پرداختی: {$formatPriceCart} تومان\n";
            \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptSyncTelegram($finalReport, $textConfirm, $confirmKb);
        }

        return [
            'ok' => true,
            'report' => $finalReport,
            'cashback_amount' => $cashbackAmount,
        ];
    }

    public static function receiptReject($runtimePdo, string $orderId, string $reason, array $ctx = []): array
    {
        $pdo = $runtimePdo;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }

        $actorId = $ctx['actor_id'] ?? null;
        $actorLabel = (string)($ctx['actor_label'] ?? '');
        $skipTelegramSync = !empty($ctx['skip_telegram_sync']);
        $notifyUser = !array_key_exists('notify_user', $ctx) || !empty($ctx['notify_user']);

        $report = self::receiptGet($runtimePdo, $orderId, false);
        if ($report === false) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $status = (string)($report['payment_Status'] ?? '');
        if ($status === 'reject' || $status === 'paid') {
            return ['ok' => false, 'reason' => 'already_final', 'report' => $report];
        }
        $allowedStatuses = !empty($ctx['allow_unpaid']) ? ['waiting', 'Unpaid'] : ['waiting'];
        if (!in_array($status, $allowedStatuses, true)) {
            return ['ok' => false, 'reason' => $status === 'Unpaid' ? 'not_eligible' : 'not_waiting', 'report' => $report];
        }

        $statusPlaceholders = implode(', ', array_fill(0, count($allowedStatuses), '?'));
        $rejectStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject', dec_not_confirmed = ? WHERE id_order = ? AND payment_Status IN ($statusPlaceholders) AND COALESCE(direct_payment_done, 0) = 0");
        $params = array_merge([$reason, $orderId], $allowedStatuses);
        $rejectStmt->execute($params);
        if ($rejectStmt->rowCount() !== 1) {
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_REJECT_RACE', 'Reject raced with another actor; dropping duplicate', [
                    'id_order' => $orderId,
                    'actor_id' => $actorId,
                ]);
            }
            return ['ok' => false, 'reason' => 'race_lost', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
        }

        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');

        $finalReport = self::receiptGet($runtimePdo, $orderId, false);
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, ($finalReport['id_user'] ?? '')));
        }
        $side = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptRejectSideEffects($finalReport, $orderId, $reason, $actorLabel, $skipTelegramSync, $notifyUser);

        return ['ok' => true, 'report' => $finalReport, 'notified' => !empty($side['notified'])];
    }

    public static function receiptReopen($runtimePdo, string $orderId, array $ctx = []): array
    {
        $pdo = $runtimePdo;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }

        $skipTelegramSync = !empty($ctx['skip_telegram_sync']);

        $report = self::receiptGet($runtimePdo, $orderId, false);
        if ($report === false) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $reopenStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting' WHERE id_order = :id_order AND payment_Status = 'reject'");
        $reopenStmt->bindValue(':id_order', $orderId, PDO::PARAM_STR);
        $reopenStmt->execute();
        if ($reopenStmt->rowCount() !== 1) {
            return ['ok' => false, 'reason' => 'race_lost', 'report' => self::receiptGet($runtimePdo, $orderId, false)];
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');

        $finalReport = self::receiptGet($runtimePdo, $orderId, false);

        if (!$skipTelegramSync) {
            $confirmRejectKb = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => 'تایید پرداخت', 'callback_data' => "Confirm_pay_{$orderId}"],
                        ['text' => 'رد پرداخت', 'callback_data' => "reject_pay_{$orderId}"],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
            $textReopen = "🔁 رد رسید لغو شد. رسید دوباره در انتظار بررسی است.\n\n"
                . "🛒 کد پیگیری پرداخت: {$orderId}\n"
                . "💰 مبلغ پرداخت : " . (method_exists(\Faoxima\Core\Support\Formatting::class, 'rxFormatToman') ? \Faoxima\Core\Support\Formatting::rxFormatToman($finalReport['price']) : number_format((int)$finalReport['price'])) . "\n"
                . "👤 ایدی عددی کاربر: {$finalReport['id_user']}";
            \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptSyncTelegram($finalReport, $textReopen, $confirmRejectKb);
        }

        return ['ok' => true, 'report' => $finalReport];
    }

    public static function receiptSoftDeleteAll($runtimePdo): int
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) return 0;
        $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject', dec_not_confirmed = 'remove_all' WHERE " . self::receiptScopeSql() . " AND payment_Status = 'waiting'");
        $stmt->execute();
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
        return $stmt->rowCount();
    }
}
