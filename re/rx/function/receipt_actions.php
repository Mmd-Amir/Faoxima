<?php

if (!function_exists('rxReceiptScopeSql')) {
    function rxReceiptScopeSql(): string
    {
        return "Payment_Method = 'cart to cart'";
    }
}

if (!function_exists('rxReceiptGet')) {
    function rxReceiptGet(string $orderId, bool $useCache = true)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :o AND " . rxReceiptScopeSql() . " LIMIT 1");
        $stmt->bindValue(':o', $orderId, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? false : $row;
    }
}

if (!function_exists('rxReceiptSyncTelegram')) {
    function rxReceiptSyncTelegram(array $report, string $text, $keyboard = null): void
    {
        if (!function_exists('Editmessagetext')) return;

        $targets = [];
        $chatId = trim((string)($report['report_chat_id'] ?? ''));
        $msgId = (int)($report['report_message_id'] ?? 0);
        if ($chatId !== '' && $msgId > 0) {
            $targets[] = [
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'thread_id' => (int)($report['report_thread_id'] ?? 0) > 0 ? (int)($report['report_thread_id'] ?? 0) : null,
            ];
        }

        $privateRaw = (string)($report['private_receipt_targets'] ?? '');
        $privateTargets = $privateRaw !== '' ? json_decode($privateRaw, true) : [];
        if (is_array($privateTargets)) {
            foreach ($privateTargets as $t) {
                $tChat = (string)($t['chat_id'] ?? '');
                $tMsg = (int)($t['message_id'] ?? 0);
                if ($tChat === '' || $tMsg <= 0) continue;
                $targets[] = ['chat_id' => $tChat, 'message_id' => $tMsg, 'thread_id' => null];
            }
        }

        $seen = [];
        foreach ($targets as $target) {
            $key = $target['chat_id'] . ':' . $target['message_id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            try {
                $result = Editmessagetext($target['chat_id'], $target['message_id'], $text, $keyboard, 'HTML', $target['thread_id']);
                if ((!is_array($result) || empty($result['ok'])) && function_exists('rx_log_event')) {
                    rx_log_event('RECEIPT_SYNC_EDIT_FAILED', 'Editmessagetext returned failure for a receipt target', [
                        'id_order' => $report['id_order'] ?? null,
                        'chat_id' => $target['chat_id'],
                        'message_id' => $target['message_id'],
                        'desc' => is_array($result) ? (string)($result['description'] ?? '') : '',
                    ]);
                }
            } catch (Throwable $e) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('RECEIPT_SYNC_EDIT_FAILED', 'Editmessagetext threw for a receipt target', [
                        'id_order' => $report['id_order'] ?? null,
                        'chat_id' => $target['chat_id'],
                        'message_id' => $target['message_id'],
                        'err' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}

if (!function_exists('rxReceiptConfirm')) {
    function rxReceiptConfirm(string $orderId, array $ctx = []): array
    {
        global $pdo, $setting;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }

        $actorId = $ctx['actor_id'] ?? null;
        $actorLabel = (string)($ctx['actor_label'] ?? '');
        $skipTelegramSync = !empty($ctx['skip_telegram_sync']);

        $report = rxReceiptGet($orderId, false);
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

        $claim = rx_pf_claim((string) $report['id_order'], ['from' => ['waiting'], 'to' => 'processing']);
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

        if (!function_exists('DirectPayment')) {
            rx_pf_settle((string) $report['id_order'], (string) $claim['token'], rx_pf_result('retryable', 'direct_payment_unavailable'), 'waiting', 'waiting');
            return ['ok' => false, 'reason' => 'fulfillment_failed', 'report' => rxReceiptGet($orderId, false)];
        }

        $run = rx_pf_run_claimed((string) $report['id_order'], (string) $claim['token'], 'images.jpg', 'waiting');
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
                return ['ok' => false, 'reason' => 'already_paid', 'report' => rxReceiptGet($orderId, false)];
            }
            if ($settle === 'manual_review') {
                return ['ok' => false, 'reason' => 'manual_review', 'report' => rxReceiptGet($orderId, false)];
            }
            if ($settle === 'reconciling' || $settle === 'error') {
                return ['ok' => false, 'reason' => 'fulfillment_reconciling', 'report' => rxReceiptGet($orderId, false)];
            }
            return ['ok' => false, 'reason' => 'fulfillment_failed', 'report' => rxReceiptGet($orderId, false)];
        }

        $finalReport = rxReceiptGet($orderId, false);

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

        $balanceUser = function_exists('select') ? select('user', '*', 'id', $finalReport['id_user'], 'select') : false;
        if (!is_array($balanceUser)) $balanceUser = ['id' => $finalReport['id_user'], 'username' => '', 'Balance' => 0];

        $cashbackAmount = 0;
        $pricecashbackRow = function_exists('select') ? select('PaySetting', 'ValuePay', 'NamePay', 'chashbackcart', 'select') : false;
        $pricecashback = is_array($pricecashbackRow) ? (string)($pricecashbackRow['ValuePay'] ?? '0') : '0';
        $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
            || rx_cashbackEligibleForKey('chashbackcart', $balanceUser['register'] ?? null, $finalReport['id_invoice'] ?? null, $balanceUser['id'] ?? null, $finalReport['id_order'] ?? null);
        if ($cashbackEligible && $pricecashback !== '0' && $pricecashback !== '') {
            $cashbackAmount = (int) floor(((int)$finalReport['price'] * (int)$pricecashback) / 100);
            if (rx_cashback_credit_once($finalReport['id_order'], $balanceUser['id'], $cashbackAmount, 'chashbackcart', 'هدیه بازگشت وجه کارت به کارت') !== 'credited') {
                $cashbackAmount = 0;
            }
            if ($cashbackAmount > 0 && function_exists('sendmessage')) {
                sendmessage($balanceUser['id'], "🎁 کاربر عزیز مبلغ " . (function_exists('rxFormatToman') ? rxFormatToman($cashbackAmount) : number_format($cashbackAmount)) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.", null, 'HTML');
            }
            $balanceUser = function_exists('select') ? select('user', '*', 'id', $finalReport['id_user'], 'select', ['cache' => false]) : $balanceUser;
        }

        if (function_exists('update')) {
            update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $finalReport['id_order']);
            update('user', 'Processing_value_one', 'none', 'id', $balanceUser['id']);
            update('user', 'Processing_value_tow', 'none', 'id', $balanceUser['id']);
            update('user', 'Processing_value_four', 'none', 'id', $balanceUser['id']);
        }

        $formatPriceCart = number_format((int)$finalReport['price']);
        $paymentreports = null;
        if (function_exists('select')) {
            $topicRow = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
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
                    [['text' => '✅ تایید شده', 'callback_data' => 'confirmpaid']],
                    [['text' => '⚙️ مدیریت کاربر', 'callback_data' => 'manageuser_' . $finalReport['id_user']]],
                ],
            ]);
            $balanceFmt = function_exists('rxFormatToman') ? rxFormatToman($balanceUser['Balance'] ?? 0) : number_format((int)($balanceUser['Balance'] ?? 0));
            $textConfirm = "✅ پرداخت توسط ادمین تایید شده\n"
                . "👤 شناسه کاربر: <code>{$finalReport['id_user']}</code>\n"
                . "🛒 کد پیگیری پرداخت: {$orderId}\n"
                . "⚜️ نام کاربری: @{$balanceUser['username']}\n"
                . "💎 موجودی بعد از تایید : {$balanceFmt}\n"
                . "💸 مبلغ پرداختی: {$formatPriceCart} تومان\n";
            rxReceiptSyncTelegram($finalReport, $textConfirm, $confirmKb);
        }

        return [
            'ok' => true,
            'report' => $finalReport,
            'cashback_amount' => $cashbackAmount,
        ];
    }
}

if (!function_exists('rxReceiptReject')) {
    function rxReceiptReject(string $orderId, string $reason, array $ctx = []): array
    {
        global $pdo;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }

        $actorId = $ctx['actor_id'] ?? null;
        $actorLabel = (string)($ctx['actor_label'] ?? '');
        $skipTelegramSync = !empty($ctx['skip_telegram_sync']);
        $notifyUser = !array_key_exists('notify_user', $ctx) || !empty($ctx['notify_user']);

        $report = rxReceiptGet($orderId, false);
        if ($report === false) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $status = (string)($report['payment_Status'] ?? '');
        if ($status === 'reject' || $status === 'paid') {
            return ['ok' => false, 'reason' => 'already_final', 'report' => $report];
        }
        if ($status !== 'waiting') {
            return ['ok' => false, 'reason' => 'not_waiting', 'report' => $report];
        }

        $rejectStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject' WHERE id_order = :id_order AND payment_Status = 'waiting'");
        $rejectStmt->bindValue(':id_order', $orderId, PDO::PARAM_STR);
        $rejectStmt->execute();
        if ($rejectStmt->rowCount() !== 1) {
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_REJECT_RACE', 'Reject raced with another actor; dropping duplicate', [
                    'id_order' => $orderId,
                    'actor_id' => $actorId,
                ]);
            }
            return ['ok' => false, 'reason' => 'race_lost', 'report' => rxReceiptGet($orderId, false)];
        }

        if (function_exists('update')) {
            update('Payment_report', 'dec_not_confirmed', $reason, 'id_order', $orderId);
        }
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');

        $finalReport = rxReceiptGet($orderId, false);
        $reasonEsc = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

        if (!$skipTelegramSync) {
            $textConfirmed = "❌ رسید پرداخت رد شد.\n\n"
                . "اطلاعات :\n"
                . "<blockquote>💸 روش پرداخت : {$finalReport['Payment_Method']}</blockquote>\n"
                . "<blockquote>👤آیدی عددی  ادمین رد کننده : " . htmlspecialchars((string)$actorLabel, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
                . "<blockquote>💰 مبلغ پرداخت : " . (function_exists('rxFormatToman') ? rxFormatToman($finalReport['price']) : number_format((int)$finalReport['price'])) . "</blockquote>\n"
                . "<blockquote>دلیل رد کردن : {$reasonEsc}</blockquote>\n"
                . "<blockquote>👤 ایدی عددی کاربر: {$finalReport['id_user']}</blockquote>";
            rxReceiptSyncTelegram($finalReport, $textConfirmed, null);
        }

        if ($notifyUser && function_exists('sendmessage')) {
            $textReject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.\n"
                . "✍️ {$reason}\n"
                . "🛒 کد پیگیری پرداخت: {$orderId}\n                ";
            $targetUserId = $finalReport['id_user'] ?? null;
            if ($targetUserId) {
                sendmessage($targetUserId, $textReject, null, 'HTML');
            }
        }

        return ['ok' => true, 'report' => $finalReport];
    }
}

if (!function_exists('rxReceiptReopen')) {
    function rxReceiptReopen(string $orderId, array $ctx = []): array
    {
        global $pdo;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }

        $skipTelegramSync = !empty($ctx['skip_telegram_sync']);

        $report = rxReceiptGet($orderId, false);
        if ($report === false) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $reopenStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting' WHERE id_order = :id_order AND payment_Status = 'reject'");
        $reopenStmt->bindValue(':id_order', $orderId, PDO::PARAM_STR);
        $reopenStmt->execute();
        if ($reopenStmt->rowCount() !== 1) {
            return ['ok' => false, 'reason' => 'race_lost', 'report' => rxReceiptGet($orderId, false)];
        }
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');

        $finalReport = rxReceiptGet($orderId, false);

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
                . "💰 مبلغ پرداخت : " . (function_exists('rxFormatToman') ? rxFormatToman($finalReport['price']) : number_format((int)$finalReport['price'])) . "\n"
                . "👤 ایدی عددی کاربر: {$finalReport['id_user']}";
            rxReceiptSyncTelegram($finalReport, $textReopen, $confirmRejectKb);
        }

        return ['ok' => true, 'report' => $finalReport];
    }
}

if (!function_exists('rxReceiptSoftDeleteAll')) {
    function rxReceiptSoftDeleteAll(): int
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return 0;
        $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject', dec_not_confirmed = 'remove_all' WHERE " . rxReceiptScopeSql() . " AND payment_Status = 'waiting'");
        $stmt->execute();
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
        return $stmt->rowCount();
    }
}

if (!function_exists('rxCardPaymentMethods')) {
    function rxCardPaymentMethods(): array
    {
        return ['cart to cart', 'carttocart_pv'];
    }
}

if (!function_exists('rxInvoiceHasInvalidatedAt')) {
    function rxInvoiceHasInvalidatedAt(PDO $pdo): bool
    {
        static $has = null;
        if ($has !== null) return $has;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'invalidated_at'");
            $has = $chk !== false && $chk->rowCount() === 1;
        } catch (Throwable $e) {
            $has = false;
        }
        return $has;
    }
}

if (!function_exists('rxCancelAbandonedCardPayment')) {
    function rxCancelAbandonedCardPayment(string $orderId, $userId, string $reason, array $opts = []): array
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }
        $orderId = trim($orderId);
        $userId = trim((string) $userId);
        if ($orderId === '' || $userId === '') {
            return ['ok' => false, 'reason' => 'invalid_args'];
        }
        $targetStatus = (string) ($opts['status'] ?? 'cancelled');
        if (!in_array($targetStatus, ['cancelled', 'expire'], true)) {
            $targetStatus = 'cancelled';
        }
        $requiredSource = isset($opts['source']) ? (string) $opts['source'] : null;
        $preserveKey = isset($opts['preserve_invoice_key']) ? trim((string) $opts['preserve_invoice_key']) : '';
        $marker = substr(($targetStatus === 'expire' ? 'expired:' : 'cancelled:') . $reason, 0, 190);
        $now = date('Y/m/d H:i:s');
        $invoiceInvalidated = false;
        $ownTx = !$pdo->inTransaction();

        try {
            if ($ownTx) $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :o LIMIT 1 FOR UPDATE");
            $stmt->execute([':o' => $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $refuse = null;
            if (!is_array($row)) {
                $refuse = 'not_found';
            } elseif ((string) ($row['id_user'] ?? '') !== $userId) {
                $refuse = 'not_owner';
            } elseif (!in_array((string) ($row['Payment_Method'] ?? ''), rxCardPaymentMethods(), true)) {
                $refuse = 'not_card';
            } elseif ($requiredSource !== null && (string) ($row['source'] ?? '') !== $requiredSource) {
                $refuse = 'source_mismatch';
            } else {
                $status = strtolower((string) ($row['payment_Status'] ?? ''));
                $rowMarker = trim((string) ($row['dec_not_confirmed'] ?? ''));
                $delivered = (int) ($row['report_message_id'] ?? 0) > 0
                    || trim((string) ($row['private_receipt_targets'] ?? '')) !== '';
                if (!in_array($status, ['unpaid', 'pending'], true)) {
                    $refuse = 'status_' . ($status === '' ? 'empty' : $status);
                } elseif ($rowMarker !== '') {
                    $refuse = 'receipt_in_progress';
                } elseif ($delivered || (int) ($row['direct_payment_done'] ?? 0) !== 0) {
                    $refuse = 'receipt_delivered';
                }
            }
            if ($refuse !== null) {
                if ($ownTx) $pdo->rollBack();
                return ['ok' => false, 'reason' => $refuse];
            }

            $upd = $pdo->prepare(
                "UPDATE Payment_report SET payment_Status = :s, dec_not_confirmed = :d, at_updated = :au
                  WHERE id_order = :o AND id_user = :u AND payment_Status IN ('Unpaid','pending')
                    AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')
                    AND COALESCE(direct_payment_done, 0) = 0"
            );
            $upd->execute([':s' => $targetStatus, ':d' => $marker, ':au' => $now, ':o' => $orderId, ':u' => $userId]);
            if ($upd->rowCount() !== 1) {
                if ($ownTx) $pdo->rollBack();
                return ['ok' => false, 'reason' => 'race_lost'];
            }

            $idInvoice = (string) ($row['id_invoice'] ?? '');
            $parts = explode('|', $idInvoice, 2);
            $invUsername = trim((string) ($parts[1] ?? ''));
            if (($parts[0] ?? '') === 'getconfigafterpay' && $invUsername !== '' && $idInvoice !== $preserveKey) {
                $live = $pdo->prepare(
                    "SELECT COUNT(*) FROM Payment_report
                      WHERE id_user = :u AND id_invoice = :i AND id_order <> :o
                        AND COALESCE(payment_Status, '') NOT IN ('expire','cancelled')"
                );
                $live->execute([':u' => $userId, ':i' => $idInvoice, ':o' => $orderId]);
                if ((int) $live->fetchColumn() === 0) {
                    if (rxInvoiceHasInvalidatedAt($pdo)) {
                        $inv = $pdo->prepare("UPDATE invoice SET Status = 'Unsuccessful', invalidated_at = :t WHERE id_user = :u AND username = :n AND Status = 'unpaid'");
                        $inv->execute([':t' => time(), ':u' => $userId, ':n' => $invUsername]);
                    } else {
                        $inv = $pdo->prepare("UPDATE invoice SET Status = 'Unsuccessful' WHERE id_user = :u AND username = :n AND Status = 'unpaid'");
                        $inv->execute([':u' => $userId, ':n' => $invUsername]);
                    }
                    $invoiceInvalidated = $inv->rowCount() > 0;
                }
            }

            if ($ownTx) $pdo->commit();
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $re) {}
            }
            if (function_exists('rx_log_event')) {
                rx_log_event('CARD_PAYMENT_CANCEL_FAILED', $e->getMessage(), [
                    'id_order' => $orderId,
                    'id_user' => $userId,
                    'reason' => $reason,
                ]);
            }
            return ['ok' => false, 'reason' => 'db_error'];
        }

        if (function_exists('clearSelectCache')) {
            clearSelectCache('Payment_report');
            if ($invoiceInvalidated) clearSelectCache('invoice');
        }
        if (function_exists('rx_redis_del')) {
            rx_redis_del('faoxima:paystatus:' . $orderId . ':' . $userId);
        }

        return ['ok' => true, 'status' => $targetStatus, 'invoice_invalidated' => $invoiceInvalidated, 'report' => $row];
    }
}

if (!function_exists('rxCancelAbandonedCardPaymentsForUser')) {
    function rxCancelAbandonedCardPaymentsForUser($userId, string $reason, array $opts = []): int
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return 0;
        $userId = trim((string) $userId);
        if ($userId === '') return 0;

        $methods = isset($opts['methods']) && is_array($opts['methods']) ? array_values($opts['methods']) : rxCardPaymentMethods();
        if (empty($methods)) return 0;
        $sql = "SELECT id_order FROM Payment_report
                 WHERE id_user = ? AND payment_Status IN ('Unpaid','pending')
                   AND Payment_Method IN (" . implode(',', array_fill(0, count($methods), '?')) . ")
                   AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')";
        $params = array_merge([$userId], $methods);
        if (isset($opts['source'])) {
            $sql .= " AND source = ?";
            $params[] = (string) $opts['source'];
        }
        if (!empty($opts['created_before'])) {
            $sql .= " AND time < ?";
            $params[] = (string) $opts['created_before'];
        }
        $sql .= " ORDER BY id ASC LIMIT 50";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('CARD_PAYMENT_CANCEL_FAILED', $e->getMessage(), ['id_user' => $userId, 'reason' => $reason]);
            }
            return 0;
        }

        $cancelOpts = [];
        if (isset($opts['source'])) $cancelOpts['source'] = (string) $opts['source'];
        if (isset($opts['preserve_invoice_key'])) $cancelOpts['preserve_invoice_key'] = (string) $opts['preserve_invoice_key'];
        $count = 0;
        foreach ($orders as $orderId) {
            $res = rxCancelAbandonedCardPayment((string) $orderId, $userId, $reason, $cancelOpts);
            if (!empty($res['ok'])) $count++;
        }
        return $count;
    }
}

if (!function_exists('rxRecoverStaleReceiptUploads')) {
    function rxRecoverStaleReceiptUploads(int $olderThanSeconds = 600, int $limit = 50): array
    {
        global $pdo;
        $out = ['waiting' => 0, 'unpaid' => 0, 'failed' => 0];
        if (!($pdo instanceof PDO)) return $out;
        $cutoff = date('Y/m/d H:i:s', time() - max(120, $olderThanSeconds));
        $methods = rxCardPaymentMethods();

        try {
            $stmt = $pdo->prepare(
                "SELECT id_order, id_user, report_message_id, private_receipt_targets FROM Payment_report
                  WHERE payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'
                    AND Payment_Method IN (" . implode(',', array_fill(0, count($methods), '?')) . ")
                    AND COALESCE(NULLIF(at_updated, ''), time) < ?
                  ORDER BY id ASC LIMIT " . max(1, $limit)
            );
            $stmt->execute(array_merge($methods, [$cutoff]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_UPLOAD_RECOVERY_FAILED', $e->getMessage(), ['stage' => 'scan']);
            }
            $out['failed']++;
            return $out;
        }

        $toWaiting = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting', dec_not_confirmed = 'receipt-submitted', at_updated = :au WHERE id_order = :o AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'");
        $toUnpaid = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :o AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading' AND (report_message_id IS NULL OR report_message_id = 0) AND (private_receipt_targets IS NULL OR private_receipt_targets = '')");

        foreach ($rows as $row) {
            $orderId = (string) $row['id_order'];
            $delivered = (int) ($row['report_message_id'] ?? 0) > 0
                || trim((string) ($row['private_receipt_targets'] ?? '')) !== '';
            try {
                if ($delivered) {
                    $toWaiting->execute([':au' => date('Y/m/d H:i:s'), ':o' => $orderId]);
                    if ($toWaiting->rowCount() === 1) $out['waiting']++;
                } else {
                    $toUnpaid->execute([':o' => $orderId]);
                    if ($toUnpaid->rowCount() === 1) $out['unpaid']++;
                }
                if (function_exists('rx_redis_del')) {
                    rx_redis_del('faoxima:paystatus:' . $orderId . ':' . (string) $row['id_user']);
                }
            } catch (Throwable $e) {
                $out['failed']++;
                if (function_exists('rx_log_event')) {
                    rx_log_event('RECEIPT_UPLOAD_RECOVERY_FAILED', $e->getMessage(), ['id_order' => $orderId, 'delivered' => $delivered]);
                }
            }
        }
        if (($out['waiting'] + $out['unpaid']) > 0 && function_exists('clearSelectCache')) {
            clearSelectCache('Payment_report');
        }
        return $out;
    }
}

if (!function_exists('rxReceiptHardDelete')) {
    function rxReceiptHardDelete(string $orderId): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :id_order AND " . rxReceiptScopeSql());
        $stmt->bindValue(':id_order', $orderId, PDO::PARAM_STR);
        $stmt->execute();
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
        return $stmt->rowCount() > 0;
    }
}
