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
    'textafterpayibsng' => '',
];
if (is_array($datatextbotget)) {
    foreach ($datatextbotget as $row) {
        if (isset($row['id_text'], $datatextbot[$row['id_text']])) {
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


$ttl = (int) crypto_pay_setting('cryptocheck_invoice_ttl', '86400');
$ttl = max(300, $ttl);
$ttlIranian = (int) crypto_pay_setting('cryptocheck_invoice_ttl_iranian', '7200');
$ttlIranian = max(300, $ttlIranian);
$expireBefore = date('Y/m/d H:i:s', time() - min($ttl, $ttlIranian));

try {
    $expireStmt = $pdo->prepare(
        "SELECT id_user, id_order, message_id, crypto_currency, time, crypto_iranian_mode
           FROM Payment_report
          WHERE payment_Status = 'Unpaid'
            AND crypto_currency IS NOT NULL
            AND time < :cutoff
          LIMIT 50"
    );
    $expireStmt->execute([':cutoff' => $expireBefore]);
    while ($row = $expireStmt->fetch(PDO::FETCH_ASSOC)) {
        $rowIranian = !empty($row['crypto_iranian_mode']) && (int) $row['crypto_iranian_mode'] === 1;
        $perRowTtl = $rowIranian ? $ttlIranian : $ttl;
        $rowTs = strtotime(str_replace('/', '-', (string) ($row['time'] ?? '')));
        if ($rowTs === false || $rowTs === 0 || $rowTs > time() - $perRowTtl) {
            continue;
        }
        $upd = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'expire'
               WHERE id_order = :o AND payment_Status = 'Unpaid'"
        );
        $upd->execute([':o' => $row['id_order']]);
        if ($upd->rowCount() > 0) {
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
                $balStmt = $pdo->prepare("SELECT Balance FROM user WHERE id = :id LIMIT 1");
                $balStmt->execute([':id' => $stuckUserId]);
                $stuckUserRow = $balStmt->fetch(PDO::FETCH_ASSOC);
                $oldBalance = $stuckUserRow ? (int) $stuckUserRow['Balance'] : 0;
                $newBalance = $oldBalance + $stuckPriceIrr;

                $updBal = $pdo->prepare("UPDATE user SET Balance = :b WHERE id = :id");
                $updBal->execute([':b' => $newBalance, ':id' => $stuckUserId]);

                $markNote = '[auto-refund: stuck service creation at ' . date('Y-m-d H:i:s') . ']';
                $mark = $pdo->prepare("
                    UPDATE Payment_report
                    SET dec_not_confirmed = CASE
                            WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :note
                            ELSE CONCAT(dec_not_confirmed, ' | ', :note2)
                        END
                    WHERE id_order = :o
                ");
                $mark->execute([':note' => $markNote, ':note2' => $markNote, ':o' => $stuckOrderId]);

                $pdo->commit();
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
                            . "🛒 کد فاکتور: <code>{$stuckOrderId}</code>\n"
                            . "👤 کاربر: <code>{$stuckUserId}</code>\n"
                            . "💎 ارز: " . htmlspecialchars($stuckCurrency) . "\n"
                            . "💵 مبلغ: " . number_format($stuckPriceIrr) . " تومان\n"
                            . "⚠️ علت: فاکتور paid شد ولی سرویس ساخته نشد ({$stuckRow['inv_status']}, uuid=" . ($stuckRow['inv_uuid'] ?: 'NULL') . ")",
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
    $orderId = (string) $row['id_order'];
    $currency = (string) $row['crypto_currency'];
    $hashShort = substr((string) ($row['crypto_tx_hash'] ?? ''), 0, 16) . '…';
    $rxCryptoLog('INFO', 'processing invoice', [
        'order'    => $orderId,
        'currency' => $currency,
        'hash'     => $hashShort,
        'iranian'  => !empty($row['crypto_iranian_mode']) ? 1 : 0,
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

        if ($pricecashback > 0) {
            $bonus = ((float) $row['price'] * $pricecashback) / 100.0;
            $newBalance = (int) ($Balance_id['Balance'] ?? 0) + (int) $bonus;
            if (function_exists('update')) {
                update('user', 'Balance', $newBalance, 'id', $Balance_id['id']);
            }
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
            . "- 👤 نام کاربری: {$username}\n"
            . "- 🆔 آیدی عددی: {$Balance_id['id']}\n"
            . "- 💸 مبلغ تراکنش (تومان): " . number_format((int) $row['price']) . "\n"
            . "- 💎 ارز: " . htmlspecialchars($currency) . "\n"
            . "- 📥 مبلغ روی شبکه: " . htmlspecialchars((string) $verifiedAmount) . "\n"
            . "- 🔗 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">لینک تراکنش</a>\n"
            . "- 🛒 کد فاکتور: <code>{$orderId}</code>\n"
            . "- 💳 روش پرداخت: ارز آفلاین (تایید خودکار)";

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

