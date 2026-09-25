<?php


require_once __DIR__ . '/_init.php';
rx_cron_boot('cryptocheck', 120);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('cryptocheck', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
    __DIR__ . '/../keyboard.php',
    __DIR__ . '/../jdf.php',
])) {
    return;
}
if (!rx_cron_db_ready('cryptocheck')) {
    return;
}
@require __DIR__ . '/../vendor/autoload.php';


@require_once __DIR__ . '/../infocard.php';

if (!function_exists('crypto_check_payment')) {
    error_log('[cryptocheck] crypto_helpers.php is not loaded — aborting.');
    return;
}

$ManagePanel = class_exists('ManagePanel') ? new ManagePanel() : null;
$setting = function_exists('select') ? select('setting', '*') : [];
$paymentreports = function_exists('select')
    ? (select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null)
    : null;


if (function_exists('languagechange') && is_file(__DIR__ . '/../text.json')) {
    $textbotlang = languagechange(__DIR__ . '/../text.json');
}
$datatextbotget = function_exists('select') ? select('textbot', '*', null, null, 'fetchAll') : [];
$datatextbot = [
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'text_wgdashboard' => '',
];
if (is_array($datatextbotget)) {
    foreach ($datatextbotget as $row) {
        if (isset($row['id_text']) && (array_key_exists($row['id_text'], $datatextbot) || trim((string) ($row['text'] ?? '')) !== '')) {
            $datatextbot[$row['id_text']] = (string)($row['text'] ?? '');
        }
    }
}

$keyboard = null;

if (!isset($from_id)) $from_id = null;
if (!isset($message_id)) $message_id = null;

$pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
if (!($pdo instanceof PDO)) {
    error_log('[cryptocheck] no PDO connection');
    return;
}


$ttlUnpaid = 14400;
$expireBefore = date('Y/m/d H:i:s', time() - $ttlUnpaid);

try {
    $expireStmt = $pdo->prepare(
        "SELECT id_user, id_order, message_id, crypto_currency, time
           FROM Payment_report
          WHERE payment_Status = 'Unpaid'
            AND crypto_currency IS NOT NULL
            AND time < :cutoff
          LIMIT 50"
    );
    $expireStmt->execute([':cutoff' => $expireBefore]);
    while ($row = $expireStmt->fetch(PDO::FETCH_ASSOC)) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
        $rowTs = strtotime(str_replace('/', '-', (string) ($row['time'] ?? '')));
        if ($rowTs === false || $rowTs === 0 || $rowTs > time() - $ttlUnpaid) {
            continue;
        }
        $upd = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'expire'
               WHERE id_order = :o AND payment_Status = 'Unpaid'"
        );
        $upd->execute([':o' => $row['id_order']]);
        if ($upd->rowCount() > 0) {
            if (function_exists('rx_redis_del') && isset($row['id_user'])) {
                rx_redis_del('faoxima:paystatus:' . $row['id_order'] . ':' . (string)$row['id_user']);
            }
            $textExpire = "⭕️ کاربر گرامی، فاکتور کریپتویی زیر به دلیل عدم پرداخت در مدت مجاز منقضی شد.\n\n"
                . "🛒 کد فاکتور: <code>{$row['id_order']}</code>\n"
                . "💎 ارز: " . htmlspecialchars((string) $row['crypto_currency']) . "\n"
                . "❗️ لطفاً به هیچ عنوان وجهی بابت این فاکتور پرداخت نکنید.";
            if (function_exists('sendmessage')) {
                @sendmessage($row['id_user'], $textExpire, null, 'HTML');
            }
            $mid = isset($row['message_id']) ? (int) $row['message_id'] : 0;
            if ($mid > 0 && function_exists('deletemessage')) {
                @deletemessage($row['id_user'], $mid);
            }
        }
    }
} catch (Throwable $e) {
    error_log('[cryptocheck] expire pass: ' . $e->getMessage());
}

$hashExpireCutoff = time() - 1800;
try {
    $expireHashStmt = $pdo->prepare(
        "SELECT id_user, id_order, message_id, crypto_currency
           FROM Payment_report
          WHERE payment_Status = 'AwaitingHash'
            AND crypto_currency IS NOT NULL
            AND crypto_currency IN ('TRX','TON','USDT_TRC20','USDT_TON')
            AND crypto_hash_at IS NOT NULL
            AND crypto_hash_at > 0
            AND crypto_hash_at < :cutoff
          LIMIT 50"
    );
    $expireHashStmt->execute([':cutoff' => $hashExpireCutoff]);
    while ($hashRow = $expireHashStmt->fetch(PDO::FETCH_ASSOC)) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
        $updHash = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'expire'
               WHERE id_order = :o AND payment_Status = 'AwaitingHash'"
        );
        $updHash->execute([':o' => $hashRow['id_order']]);
        if ($updHash->rowCount() > 0) {
            if (function_exists('rx_redis_del') && isset($hashRow['id_user'])) {
                rx_redis_del('faoxima:paystatus:' . $hashRow['id_order'] . ':' . (string)$hashRow['id_user']);
            }
            $textHashExpire = "⭕️ کاربر گرامی، فاکتور کریپتویی زیر به دلیل عدم تایید هش در مدت ۳۰ دقیقه منقضی شد.\n\n"
                . "🛒 کد فاکتور: <code>{$hashRow['id_order']}</code>\n"
                . "💎 ارز: " . htmlspecialchars((string) $hashRow['crypto_currency']) . "\n"
                . "❗️ اگر وجهی پرداخت کرده‌اید، با پشتیبانی تماس بگیرید.";
            if (function_exists('sendmessage')) {
                @sendmessage($hashRow['id_user'], $textHashExpire, null, 'HTML');
            }
            $mid = isset($hashRow['message_id']) ? (int) $hashRow['message_id'] : 0;
            if ($mid > 0 && function_exists('deletemessage')) {
                @deletemessage($hashRow['id_user'], $mid);
            }
        }
    }
} catch (Throwable $e) {
    error_log('[cryptocheck] hash-expire pass: ' . $e->getMessage());
}


$batchSize = 25;


$maxChecks = (int) crypto_pay_setting('cryptocheck_max_retries', '10');
$maxChecks = max(2, min($maxChecks, 60));

$rxCryptoLogFile = __DIR__ . '/../cryptocheck_debug.log';
$rxCryptoLog = function (string $level, string $msg, array $ctx = []) use ($rxCryptoLogFile) {
    
    if ($level !== 'ERROR') return;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $msg;
    if (!empty($ctx)) {
        $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents($rxCryptoLogFile, $line . PHP_EOL, FILE_APPEND);
    error_log('[cryptocheck] ' . $msg . (empty($ctx) ? '' : ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
};

register_shutdown_function(function () use ($rxCryptoLogFile) {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array($err['type'] ?? 0, $fatal, true)) return;
    $line = '[' . date('Y-m-d H:i:s') . '] [FATAL] PHP fatal at ' . ($err['file'] ?? '?') . ':' . ($err['line'] ?? '?')
          . ' — ' . ($err['message'] ?? '');
    @file_put_contents($rxCryptoLogFile, $line . PHP_EOL, FILE_APPEND);
    error_log('[cryptocheck-fatal] ' . $line);
});


try {
    $stuckStmt = $pdo->prepare("
        SELECT
            p.id_order,
            p.id_user,
            p.price,
            p.crypto_currency,
            p.id_invoice,
            p.at_updated,
            i.id_invoice AS inv_id,
            i.Status     AS inv_status,
            i.uuid       AS inv_uuid
        FROM Payment_report p
        LEFT JOIN invoice i
            ON i.username = SUBSTRING_INDEX(p.id_invoice, '|', -1)
        WHERE p.payment_Status = 'paid'
          AND p.Payment_Method = 'arze digital offline'
          AND p.id_invoice LIKE 'getconfigafterpay|%'
          AND (p.dec_not_confirmed IS NULL OR (p.dec_not_confirmed NOT LIKE '%auto-refund%' AND p.dec_not_confirmed NOT LIKE '%service-created%'))
          AND (i.id_invoice IS NULL OR LOWER(i.Status) = 'unpaid' OR i.uuid IS NULL OR i.uuid = '')
          AND p.at_updated < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 25
    ");
    $stuckStmt->execute();
    $stuckList = $stuckStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($stuckList)) {
        $rxCryptoLog('INFO', 'found stuck paid invoices', ['count' => count($stuckList)]);
        foreach ($stuckList as $stuckRow) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
            $stuckOrderId  = (string) $stuckRow['id_order'];
            $stuckUserId   = (string) $stuckRow['id_user'];
            $stuckPriceIrr = (int) ($stuckRow['price'] ?? 0);
            $stuckCurrency = (string) $stuckRow['crypto_currency'];
            if ($stuckPriceIrr <= 0 || $stuckUserId === '') {
                $rxCryptoLog('WARN', 'invalid stuck row, skipping', ['order' => $stuckOrderId]);
                continue;
            }
            try {
                $pdo->beginTransaction();

                $markNote = '[auto-refund: stuck service creation at ' . date('Y-m-d H:i:s') . ']';
                $mark = $pdo->prepare("
                    UPDATE Payment_report
                    SET dec_not_confirmed = CASE
                            WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :note
                            ELSE CONCAT(dec_not_confirmed, ' | ', :note2)
                        END
                    WHERE id_order = :o
                      AND payment_Status = 'paid'
                      AND (dec_not_confirmed IS NULL OR (dec_not_confirmed NOT LIKE '%auto-refund%' AND dec_not_confirmed NOT LIKE '%service-created%'))
                ");
                $mark->execute([':note' => $markNote, ':note2' => $markNote, ':o' => $stuckOrderId]);
                if ($mark->rowCount() < 1) {
                    $pdo->commit();
                    $rxCryptoLog('WARN', 'stuck invoice refund claim lost (already claimed)', ['order' => $stuckOrderId]);
                    continue;
                }

                $balStmt = $pdo->prepare("SELECT Balance FROM user WHERE id = :id LIMIT 1");
                $balStmt->execute([':id' => $stuckUserId]);
                $stuckUserRow = $balStmt->fetch(PDO::FETCH_ASSOC);
                $oldBalance = $stuckUserRow ? (int) $stuckUserRow['Balance'] : 0;

                $updBal = $pdo->prepare("UPDATE user SET Balance = Balance + :d WHERE id = :id");
                $updBal->execute([':d' => $stuckPriceIrr, ':id' => $stuckUserId]);
                if ($updBal->rowCount() < 1) {
                    throw new RuntimeException('stuck refund credit affected no row');
                }

                if (!function_exists('wallet_ledger_record')
                    || !wallet_ledger_record($stuckUserId, 'credit', $stuckPriceIrr, 'refund', 'بازگشت خودکار پرداخت گیرکرده', $stuckOrderId, 'Payment_report', $stuckOrderId)) {
                    throw new RuntimeException('stuck refund ledger failed');
                }

                $balStmt->execute([':id' => $stuckUserId]);
                $stuckUserRow = $balStmt->fetch(PDO::FETCH_ASSOC);
                $newBalance = $stuckUserRow ? (int) $stuckUserRow['Balance'] : ($oldBalance + $stuckPriceIrr);

                $pdo->commit();
                if (function_exists('clearSelectCacheRow')) {
                    clearSelectCacheRow('user', 'id', $stuckUserId);
                }
                $rxCryptoLog('OK', 'stuck invoice refunded', [
                    'order' => $stuckOrderId,
                    'user'  => $stuckUserId,
                    'amount'=> $stuckPriceIrr,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                ]);

                if (function_exists('sendmessage')) {
                    @sendmessage(
                        $stuckUserId,
                        "💰 <b>برگشت پول خودکار</b>\n\n"
                        . "🛒 کد فاکتور: <code>{$stuckOrderId}</code>\n"
                        . "💎 ارز: " . htmlspecialchars($stuckCurrency) . "\n"
                        . "💵 مبلغ برگشت‌خورده: " . number_format($stuckPriceIrr) . " تومان\n"
                        . "💼 موجودی جدید: " . number_format($newBalance) . " تومان\n\n"
                        . "⚠️ پرداخت شما تأیید شد ولی به دلیل خطا در ساخت سرویس، مبلغ به کیف پول شما برگشت داده شد.\n"
                        . "💡 می‌توانید مجدداً اقدام به خرید سرویس کنید.",
                        null,
                        'HTML'
                    );
                }
                if (!empty($setting['Channel_Report']) && function_exists('telegram')) {
                    @telegram('sendmessage', [
                        'chat_id'    => $setting['Channel_Report'],
                        'text'       => "💰 <b>برگشت پول خودکار (سرویس گیرکرده)</b>\n\n"
                            . "<blockquote>🛒 کد فاکتور: <code>{$stuckOrderId}</code></blockquote>\n"
                            . "<blockquote>👤 کاربر: <code>{$stuckUserId}</code></blockquote>\n"
                            . "<blockquote>💎 ارز: " . htmlspecialchars($stuckCurrency) . "</blockquote>\n"
                            . "<blockquote>💵 مبلغ: " . number_format($stuckPriceIrr) . " تومان</blockquote>\n"
                            . "<blockquote>⚠️ علت: فاکتور paid شد ولی سرویس ساخته نشد ({$stuckRow['inv_status']}, uuid=" . ($stuckRow['inv_uuid'] ?: 'NULL') . ")</blockquote>",
                        'parse_mode' => 'HTML',
                    ]);
                }
            } catch (Throwable $e) {
                try { $pdo->rollBack(); } catch (Throwable $r) {  }
                $rxCryptoLog('ERROR', 'stuck refund transaction failed', [
                    'order' => $stuckOrderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
} catch (Throwable $e) {
    $rxCryptoLog('ERROR', 'stuck-refund pass failed', ['error' => $e->getMessage()]);
}


try {
    $retryStmt = $pdo->prepare("
        SELECT p.id_order, p.id_user, p.id_invoice, p.crypto_currency, p.price,
               p.at_updated, p.crypto_check_count,
               i.username AS service_username,
               i.Status AS invoice_status, i.uuid AS invoice_uuid
        FROM Payment_report p
        LEFT JOIN invoice i ON i.username = SUBSTRING_INDEX(p.id_invoice, '|', -1)
        WHERE p.payment_Status = 'paid'
          AND p.Payment_Method = 'arze digital offline'
          AND p.id_invoice LIKE 'getconfigafterpay|%'
          AND (p.dec_not_confirmed IS NULL OR (p.dec_not_confirmed NOT LIKE '%auto-refund%' AND p.dec_not_confirmed NOT LIKE '%service-created%'))
          AND (i.uuid IS NULL OR i.uuid = '' OR LOWER(i.Status) = 'unpaid')
          AND p.at_updated >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
          AND COALESCE(p.crypto_check_count, 0) < 5
        ORDER BY p.at_updated ASC
        LIMIT 5
    ");
    $retryStmt->execute();
    $retryList = $retryStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($retryList)) {
        $rxCryptoLog('INFO', 'retry pass: found stuck service-creation invoices', ['count' => count($retryList)]);
        foreach ($retryList as $retryRow) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
            $rOrderId       = (string) $retryRow['id_order'];
            $rUserId        = (string) $retryRow['id_user'];
            $rServiceUser   = (string) ($retryRow['service_username'] ?? '');
            $rTryBefore     = (int) ($retryRow['crypto_check_count'] ?? 0);
            $rTryNow        = $rTryBefore + 1;
            $rxCryptoLog('INFO', 'retry stuck service creation', [
                'order'        => $rOrderId,
                'user'         => $rUserId,
                'try'          => $rTryNow,
                'invoice_uuid' => $retryRow['invoice_uuid'] ?: 'NULL',
                'invoice_status' => $retryRow['invoice_status'] ?: 'NULL',
                'service_username' => $rServiceUser,
            ]);

            try {
                $bump = $pdo->prepare("UPDATE Payment_report SET crypto_check_count = COALESCE(crypto_check_count,0)+1 WHERE id_order = :o");
                $bump->execute([':o' => $rOrderId]);
            } catch (Throwable $e) {  }

            @set_time_limit(90);
            $rStartedAt = microtime(true);
            try {
                if (function_exists('DirectPayment')) {
                    DirectPayment($rOrderId, __DIR__ . '/../images.jpeg');
                    $rDuration = round((microtime(true) - $rStartedAt) * 1000);
                    $rxCryptoLog('OK', 'retry DirectPayment finished', ['order' => $rOrderId, 'ms' => $rDuration]);
                } else {
                    $rxCryptoLog('WARN', 'retry DirectPayment not defined', ['order' => $rOrderId]);
                }
            } catch (Throwable $e) {
                $rxCryptoLog('ERROR', 'retry DirectPayment threw', [
                    'order' => $rOrderId,
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                    'duration_ms' => round((microtime(true) - $rStartedAt) * 1000),
                ]);
            }

            try {
                if ($rServiceUser !== '') {
                    $invCheck = $pdo->prepare("SELECT id_invoice, Status, uuid FROM invoice WHERE username = :u LIMIT 1");
                    $invCheck->execute([':u' => $rServiceUser]);
                    $invNow = $invCheck->fetch(PDO::FETCH_ASSOC);
                    $__activated = $invNow && (
                        (string)$invNow['Status'] === 'active'
                        || !empty($invNow['uuid'])
                    );
                    if ($__activated && (string)$invNow['Status'] === 'unpaid') {
                        $fix = $pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :i");
                        $fix->execute([':i' => $invNow['id_invoice']]);
                    }
                    
                    if (!$__activated) {
                        $rxCryptoLog('WARN', 'retry did not create service', [
                            'order' => $rOrderId,
                            'try'   => $rTryNow,
                        ]);
                    }
                }
            } catch (Throwable $e) {
                $rxCryptoLog('ERROR', 'retry post-check failed', [
                    'order' => $rOrderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
} catch (Throwable $e) {
    $rxCryptoLog('ERROR', 'retry pass failed', ['error' => $e->getMessage()]);
}


try {
    $stmt = $pdo->prepare(
        "SELECT *
           FROM Payment_report
          WHERE payment_Status = 'AwaitingHash'
            AND crypto_currency IS NOT NULL
            AND crypto_currency IN ('TRX','TON','USDT_TRC20','USDT_TON')
            AND crypto_tx_hash IS NOT NULL
            AND crypto_tx_hash <> ''
            AND COALESCE(crypto_check_count, 0) < :maxChecks
          ORDER BY COALESCE(crypto_hash_at, 0) ASC
          LIMIT :limit"
    );
    $stmt->bindValue(':maxChecks', $maxChecks, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        $rxCryptoLog('INFO', 'pending rows fetched', ['count' => count($rows)]);
    }
} catch (Throwable $e) {
    $rxCryptoLog('ERROR', 'select pending failed', ['error' => $e->getMessage()]);
    return;
}

foreach ($rows as $row) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
    $orderId = (string) $row['id_order'];
    $currency = (string) $row['crypto_currency'];
    $hashShort = substr((string) ($row['crypto_tx_hash'] ?? ''), 0, 16) . '…';
    $rxCryptoLog('INFO', 'processing invoice', [
        'order'    => $orderId,
        'currency' => $currency,
        'hash'     => $hashShort,
        'amount'   => $row['crypto_amount'] ?? null,
        'to'       => $row['crypto_wallet_to'] ?? null,
        'try'      => (int) ($row['crypto_check_count'] ?? 0) + 1,
    ]);

    try {
        $bump = $pdo->prepare(
            "UPDATE Payment_report
                SET crypto_check_count = COALESCE(crypto_check_count,0) + 1
              WHERE id_order = :o"
        );
        $bump->execute([':o' => $orderId]);
    } catch (Throwable $e) {  }

    $result = crypto_check_payment($row);
    $rxCryptoLog('INFO', 'verify result', ['order' => $orderId, 'result' => $result]);

    if (is_array($result) && !empty($result['ok']) && function_exists('crypto_check_tx_timestamp_after_invoice')) {
        $txTimestamp = isset($result['detail']['tx_timestamp']) ? (int) $result['detail']['tx_timestamp'] : 0;
        $invoiceCreatedAtRaw = (string) ($row['time'] ?? '');
        $invoiceCreatedAt = 0;
        if ($invoiceCreatedAtRaw !== '') {
            $tsParsed = strtotime(str_replace('/', '-', $invoiceCreatedAtRaw));
            if ($tsParsed !== false) {
                $invoiceCreatedAt = (int) $tsParsed;
            }
        }
        if ($txTimestamp > 0 && $invoiceCreatedAt > 0) {
            $toleranceSec = 120;
            $earliestAllowed = $invoiceCreatedAt - $toleranceSec;
            if ($txTimestamp < $earliestAllowed) {
                $result = [
                    'ok' => false,
                    'reason' => 'tx-before-invoice',
                    'detail' => [
                        'tx_timestamp' => $txTimestamp,
                        'invoice_created_at' => $invoiceCreatedAt,
                        'diff_sec' => $invoiceCreatedAt - $txTimestamp,
                    ],
                ];
                $rxCryptoLog('WARN', 'transaction predates invoice', [
                    'order' => $orderId,
                    'tx_timestamp' => $txTimestamp,
                    'invoice_created_at' => $invoiceCreatedAt,
                    'diff_sec' => $invoiceCreatedAt - $txTimestamp,
                ]);
            }
        }
    }

    if (!is_array($result) || empty($result['ok'])) {
        $reason = is_array($result) ? (string) ($result['reason'] ?? 'unknown') : 'no-result';
        try {
            $errStr = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $errUpd = $pdo->prepare(
                "UPDATE Payment_report SET crypto_last_error = :err WHERE id_order = :o"
            );
            $errUpd->execute([':err' => $errStr, ':o' => $orderId]);
        } catch (Throwable $e) {  }


        $terminal = [

            'no-currency', 'no-hash', 'no-recipient-on-row', 'no-expected-amount',
            'unsupported-currency', 'bad-hash-format',

            'hash-already-used',

            'wrong-recipient', 'amount-mismatch', 'tx-failed',
            'not-trx-transfer', 'no-matching-trc20-transfer',
            'no-matching-jetton-transfer', 'no-in-msg',

            'sender-mismatch', 'sender-already-bound',

            'memo-mismatch',

            'tx-before-invoice',
        ];


        $newCount = (int) ($row['crypto_check_count'] ?? 0) + 1;
        $isTimeout = !in_array($reason, $terminal, true) && $newCount >= $maxChecks;

        $rxCryptoLog('INFO', 'verify decision', [
            'order'    => $orderId,
            'reason'   => $reason,
            'terminal' => in_array($reason, $terminal, true),
            'timeout'  => $isTimeout,
            'try'      => $newCount,
        ]);

        if (in_array($reason, $terminal, true) || $isTimeout) {
            try {
                $rej = $pdo->prepare(
                    "UPDATE Payment_report SET payment_Status = 'reject'
                       WHERE id_order = :o AND payment_Status = 'AwaitingHash'"
                );
                $rej->execute([':o' => $orderId]);
                if ($rej->rowCount() > 0) {
                    $reasonsFa = [
                        'hash-already-used'    => 'این هش قبلاً برای فاکتور دیگری استفاده شده است.',
                        'wrong-recipient'      => 'آدرس مقصد تراکنش با کیف پول ربات یکسان نیست.',
                        'tx-failed'            => 'تراکنش روی شبکه با خطا انجام شده است.',
                        'unsupported-currency' => 'این ارز توسط ربات پشتیبانی نمی‌شود.',
                        'bad-hash-format'      => 'فرمت هش ارسالی نامعتبر است.',
                        'amount-mismatch'      => 'مبلغ تراکنش با مبلغ فاکتور یکسان نیست. لطفاً دقیقاً همان مقدار اعلام‌شده را ارسال کنید.',
                        'not-trx-transfer'     => 'این تراکنش یک انتقال ساده‌ی TRX نیست.',
                        'no-matching-trc20-transfer'  => 'این هش حاوی انتقال USDT-TRC20 منطبق با فاکتور نیست.',
                        'no-matching-jetton-transfer' => 'این هش حاوی انتقال USDT-TON منطبق با فاکتور نیست.',
                        'no-in-msg'            => 'تراکنش روی شبکه TON معتبر نیست.',
                        'sender-mismatch'      => 'آدرس فرستنده با آدرس ثبت‌شده‌ی فاکتور یکسان نیست. باید از همان کیف پول/صرافی قبلی ارسال کنید.',
                        'sender-already-bound' => 'این آدرس فرستنده قبلاً برای فاکتور دیگری استفاده شده است. لطفاً از کیف پول/حساب دیگری ارسال کنید.',
                        'tx-before-invoice'    => 'این تراکنش قبل از ساخت فاکتور شما روی شبکه ثبت شده است. لطفاً پس از ساخت فاکتور پرداخت کنید و هش همان تراکنش را ارسال کنید.',
                        'memo-mismatch'        => 'ممو (Memo / Comment) تراکنش با مموی فاکتور یکسان نیست. لطفاً ممو دقیقاً همان‌طور که در فاکتور نشان داده شد را در پرداخت وارد کنید.',
                    ];
                    $faReason = $isTimeout
                        ? "هش وارد شده در {$maxChecks} دقیقه گذشته روی شبکه پیدا نشد. لطفاً مطمئن شوید هش صحیح را ارسال کرده‌اید و تراکنش روی شبکه تایید شده باشد."
                        : ($reasonsFa[$reason] ?? $reason);
                    if (function_exists('sendmessage')) {
                        @sendmessage(
                            (string) $row['id_user'],
                            "❌ پرداخت کریپتویی شما تایید نشد.\n\n"
                            . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
                            . "📝 دلیل: " . htmlspecialchars($faReason) . "\n\n"
                            . "در صورت اعتراض با پشتیبانی در ارتباط باشید.",
                            null,
                            'HTML'
                        );
                    }

                    try {
                        $explorerUrl = function_exists('crypto_explorer_url')
                            ? crypto_explorer_url((string)($row['crypto_currency'] ?? ''), (string)($row['crypto_tx_hash'] ?? ''))
                            : (string)($row['crypto_tx_hash'] ?? '');
                        $dupWarn = '';
                        if (function_exists('crypto_lookup_verified_hash')) {
                            $existing = crypto_lookup_verified_hash((string)($row['crypto_tx_hash'] ?? ''));
                            if (is_array($existing) && (string)($existing['order_id'] ?? '') !== $orderId) {
                                $dupWarn = "\n\n⚠️ <b>این هش قبلاً تایید شده</b>\n"
                                    . "<blockquote>🛒 فاکتور قبلی: <code>" . htmlspecialchars((string)$existing['order_id']) . "</code></blockquote>\n"
                                    . "<blockquote>👤 کاربر قبلی: <code>" . htmlspecialchars((string)($existing['user_id'] ?? '-')) . "</code></blockquote>";
                            }
                        }
                        $adminCaption = "❌ <b>هش کریپتو توسط ربات تایید نشد</b>\n\n"
                            . "<blockquote>🛒 کد فاکتور: <code>{$orderId}</code></blockquote>\n"
                            . "<blockquote>👤 کاربر: <code>" . htmlspecialchars((string)($row['id_user'] ?? '-')) . "</code></blockquote>\n"
                            . "<blockquote>💎 ارز: " . htmlspecialchars((string)($row['crypto_currency'] ?? '-')) . "</blockquote>\n"
                            . "<blockquote>🪙 مقدار ادعاشده: <code>" . htmlspecialchars((string)($row['crypto_amount'] ?? '-')) . "</code></blockquote>\n"
                            . "<blockquote>💸 معادل تومانی: " . number_format((int)($row['price'] ?? 0)) . " تومان</blockquote>\n"
                            . "<blockquote>🔗 هش: <code>" . htmlspecialchars((string)($row['crypto_tx_hash'] ?? '-')) . "</code></blockquote>\n"
                            . "<blockquote>📝 دلیل عدم تایید: " . htmlspecialchars($faReason) . "</blockquote>\n"
                            . "<blockquote>🔍 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a></blockquote>"
                            . $dupWarn
                            . "\n\n💡 اگر کاربر درخواست بررسی دستی بده، اعلان جدید با دکمه‌های اقدام به پی‌وی شما ارسال می‌شود.";

                        $settingsRow = function_exists('select') ? select('setting', '*') : [];
                        $channelReport = is_array($settingsRow) ? (string)($settingsRow['Channel_Report'] ?? '') : '';
                        $threadRow = function_exists('select') ? select('topicid', 'idreport', 'report', 'paymentreport', 'select') : null;
                        $threadId = is_array($threadRow) ? (string)($threadRow['idreport'] ?? '') : '';
                        if ($channelReport !== '' && function_exists('telegram')) {
                            $payload = [
                                'chat_id' => $channelReport,
                                'text' => $adminCaption,
                                'parse_mode' => 'HTML',
                            ];
                            if ($threadId !== '') {
                                $payload['message_thread_id'] = $threadId;
                            }
                            @telegram('sendmessage', $payload);
                        }
                    } catch (Throwable $e) {
                        $rxCryptoLog('ERROR', 'admin notify on reject failed', ['order' => $orderId, 'error' => $e->getMessage()]);
                    }
                }
            } catch (Throwable $e) {  }
        }
        continue;
    }


    try {
        $verifiedSender = isset($result['detail']['sender']) ? trim((string) $result['detail']['sender']) : '';
        $atomic = $pdo->prepare(
            "UPDATE Payment_report
                SET payment_Status = 'paid',
                    at_updated = :now,
                    crypto_sender_address = COALESCE(NULLIF(crypto_sender_address, ''), :sender)
              WHERE id_order = :o AND payment_Status <> 'paid'"
        );
        $atomic->execute([
            ':now' => date('Y/m/d H:i:s'),
            ':sender' => $verifiedSender !== '' ? $verifiedSender : null,
            ':o' => $orderId,
        ]);
        if ($atomic->rowCount() < 1) {
            $rxCryptoLog('WARN', 'mark-paid skipped (already paid?)', ['order' => $orderId]);
            continue;
        }
        if (function_exists('crypto_record_verified_hash')) {
            crypto_record_verified_hash($orderId, 'auto_cron');
        }
        $rxCryptoLog('OK', 'invoice marked PAID', ['order' => $orderId, 'sender' => $verifiedSender]);
    } catch (Throwable $e) {
        $rxCryptoLog('ERROR', 'atomic mark-paid failed', ['order' => $orderId, 'error' => $e->getMessage()]);
        continue;
    }

    $rxCryptoLog('INFO', 'calling DirectPayment', ['order' => $orderId]);
    @set_time_limit(90);
    $dpStartedAt = microtime(true);
    try {
        if (function_exists('DirectPayment')) {
            DirectPayment($orderId, __DIR__ . '/../images.jpeg');
            $dpDuration = round((microtime(true) - $dpStartedAt) * 1000);
            $rxCryptoLog('OK', 'DirectPayment finished', ['order' => $orderId, 'ms' => $dpDuration]);
        } else {
            $rxCryptoLog('WARN', 'DirectPayment not defined', ['order' => $orderId]);
        }
    } catch (Throwable $e) {
        $rxCryptoLog('ERROR', 'DirectPayment threw', [
            'order' => $orderId,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'class' => get_class($e),
            'duration_ms' => round((microtime(true) - $dpStartedAt) * 1000),
        ]);
    }

    try {
        $invMetaParts = explode('|', (string) ($row['id_invoice'] ?? ''));
        $invUsername = $invMetaParts[1] ?? '';
        if ($invUsername !== '' && strpos((string) ($row['id_invoice'] ?? ''), 'getconfigafterpay|') === 0) {
            $invStmt = $pdo->prepare("SELECT id_invoice, Status, uuid FROM invoice WHERE username = :u LIMIT 1");
            $invStmt->execute([':u' => $invUsername]);
            $invRow = $invStmt->fetch(PDO::FETCH_ASSOC);
            if ($invRow && !empty($invRow['uuid']) && (string) $invRow['Status'] === 'unpaid') {
                $rxCryptoLog('WARN', 'invoice created but not activated, fixing', [
                    'order'     => $orderId,
                    'invoice'   => $invRow['id_invoice'],
                    'username'  => $invUsername,
                ]);
                $fix1 = $pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :i");
                $fix1->execute([':i' => $invRow['id_invoice']]);
                $rxCryptoLog('OK', 'invoice activated by fallback', [
                    'order'   => $orderId,
                    'invoice' => $invRow['id_invoice'],
                ]);

                if (function_exists('sendmessage')) {
                    @sendmessage(
                        (string) $row['id_user'],
                        "✅ <b>پرداخت تایید شد و سرویس شما فعال است</b>\n\n"
                        . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
                        . "👤 نام کاربری سرویس: <code>" . htmlspecialchars($invUsername) . "</code>\n\n"
                        . "ℹ️ اگر پیام تحویل کانفیگ را دریافت نکردید، از بخش «🛍 سرویس‌های من» وارد سرویس شوید و کانفیگ را دریافت کنید.\n"
                        . "در صورت بروز مشکل با پشتیبانی در تماس باشید.",
                        null,
                        'HTML'
                    );
                }
            } elseif ($invRow) {
                
            } else {
                $rxCryptoLog('WARN', 'invoice not found after DirectPayment', [
                    'order' => $orderId,
                    'username' => $invUsername,
                ]);
            }
        }
    } catch (Throwable $e) {
        $rxCryptoLog('ERROR', 'invoice-state fallback failed', [
            'order' => $orderId,
            'error' => $e->getMessage(),
        ]);
    }

    try {
        $cashbackKey = 'cryptocheck_cashback_' . $currency;
        $pricecashback = (float) crypto_pay_setting($cashbackKey, '0');
        $userStmt = $pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
        $userStmt->execute([':id' => (string) $row['id_user']]);
        $Balance_id = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $row['id_user'], 'username' => '—', 'Balance' => 0];

        $bonus = $pricecashback > 0 ? (int) floor(((float) $row['price'] * $pricecashback) / 100.0) : 0;
        if ($bonus > 0 && rx_cashback_credit_once($orderId, $Balance_id['id'], $bonus, $cashbackKey, 'هدیه بازگشت وجه کریپتو') === 'credited') {
            if (function_exists('sendmessage')) {
                @sendmessage(
                    (string) $Balance_id['id'],
                    "🎁 کاربر عزیز، مبلغ " . number_format($bonus) . " تومان به عنوان هدیه به حساب شما واریز شد.",
                    null,
                    'HTML'
                );
            }
        }

        $explorerUrl = function_exists('crypto_explorer_url')
            ? crypto_explorer_url($currency, (string) $row['crypto_tx_hash'])
            : (string) $row['crypto_tx_hash'];
        $verifiedAmount = isset($result['detail']['amount']) ? $result['detail']['amount'] : '—';
        $username = isset($Balance_id['username']) ? '@' . $Balance_id['username'] : '—';

        $textReport = "💵 پرداخت کریپتو جدید (هش‌چکر)\n"
            . "<blockquote>- 👤 نام کاربری: {$username}</blockquote>\n"
            . "<blockquote>- 👤 آیدی عددی: {$Balance_id['id']}</blockquote>\n"
            . "<blockquote>- 💸 مبلغ تراکنش (تومان): " . number_format((int) $row['price']) . "</blockquote>\n"
            . "<blockquote>- 💎 ارز: " . htmlspecialchars($currency) . "</blockquote>\n"
            . "<blockquote>- 📥 مبلغ روی شبکه: " . htmlspecialchars((string) $verifiedAmount) . "</blockquote>\n"
            . "<blockquote>- 🔗 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">لینک تراکنش</a></blockquote>\n"
            . "<blockquote>- 🛒 کد فاکتور: <code>{$orderId}</code></blockquote>\n"
            . "<blockquote>- 💳 روش پرداخت: ارز آفلاین (تایید خودکار)</blockquote>";

        if (!empty($setting['Channel_Report']) && function_exists('telegram')) {
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'text'    => $textReport,
                'parse_mode' => 'HTML',
            ];
            if (!empty($paymentreports)) {
                $payload['message_thread_id'] = $paymentreports;
            }
            telegram('sendmessage', $payload);
        }

        
        
        
        
        
        
    } catch (Throwable $e) {
        $rxCryptoLog('ERROR', 'post-paid handling threw', ['order' => $orderId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
}


if (!empty($rows)) {
    $rxCryptoLog('INFO', 'cron run complete', ['processed' => count($rows)]);
}

