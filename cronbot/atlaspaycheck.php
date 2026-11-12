<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('atlaspaycheck', 60);

ini_set('error_log', 'error_log');

$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) {
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('payment_context_unavailable', 180);
    }
    return;
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $pdo, $ManagePanel, $setting;
$ManagePanel = $ctx['managePanel'];
$setting = $ctx['setting'];

if (!($pdo instanceof PDO)) {
    error_log('[atlaspaycheck] no PDO connection');
    return;
}

if (!function_exists('atlaspayCheckOrder')) {
    error_log('[atlaspaycheck] atlaspay functions not available');
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('atlaspay_functions_unavailable', 300);
    }
    return;
}

if (function_exists('atlaspayCustomRunCron')) {
    atlaspayCustomRunCron();
}

try {
    $rxPaymentWindow = rx_pf_cron_window_sql();
    $rxAtlasCursor = function_exists('rx_cron_invoice_cursor') ? rx_cron_invoice_cursor('atlaspay_poll') : '';
    $stmt = $pdo->prepare(
        "SELECT id, id_order, atlaspay_order_id, price, payment_Status, payment_processing_version, atlaspay_total_amount, atlaspay_local_state, id_user
           FROM Payment_report
          WHERE $rxPaymentWindow AND payment_Status = 'Unpaid'
            AND Payment_Method = 'atlaspay'
            AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom')
            AND atlaspay_order_id IS NOT NULL
            AND atlaspay_order_id <> ''
            AND id > ?
          ORDER BY id ASC
          LIMIT 30"
    );
    $stmt->execute([$rxAtlasCursor]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows && $rxAtlasCursor !== '') {
        $stmt->execute(['']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $lateStmt = $pdo->prepare(
        "SELECT id_order, atlaspay_order_id, price, payment_Status, payment_processing_version, atlaspay_total_amount, atlaspay_local_state, id_user
           FROM Payment_report
          WHERE $rxPaymentWindow AND payment_Status = 'expire'
            AND Payment_Method = 'atlaspay'
            AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom')
            AND payment_processing_version >= 2
            AND atlaspay_order_id IS NOT NULL
            AND atlaspay_order_id <> ''
            AND atlaspay_created_at >= ?
            AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
          ORDER BY atlaspay_last_checked_at ASC, id ASC
          LIMIT 20"
    );
    $lateStmt->execute([time() - 172800, time() - 900]);
    $rows = array_merge($rows, $lateStmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    error_log('[atlaspaycheck] select pending failed: ' . $e->getMessage());
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('select_failed', 120);
    }
    return;
}

if (empty($rows)) {
    if (function_exists('rx_cron_task_complete')) {
        rx_cron_task_complete('no_pending_payments');
    }
    return;
}

$successStatuses = ['confirmed', 'settled'];
$deadStatuses = ['rejected', 'expired', 'cancelled'];

$rxAtlasTimedOut = false;
foreach ($rows as $row) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
        $rxAtlasTimedOut = true;
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('time_budget', 60);
        }
        break;
    }

    try {
        $orderId = (string) $row['id_order'];
        $atlaspayOrderId = trim((string) $row['atlaspay_order_id']);
        if ($atlaspayOrderId === '') {
            continue;
        }

        $check = atlaspayCheckOrder($atlaspayOrderId);
        if ((int) ($row['payment_processing_version'] ?? 0) >= 2 && function_exists('atlaspayOfficialV2Process')) {
            if (function_exists('atlaspayCustomUpdateOfficialChecked')) {
                atlaspayCustomUpdateOfficialChecked($orderId);
            }
            atlaspayOfficialV2Process($row, $check);
            continue;
        }
        if (!is_array($check) || (int) ($check['id'] ?? 0) !== (int) $atlaspayOrderId) {
            continue;
        }

        $status = (string) ($check['status'] ?? '');

        if (in_array($status, $deadStatuses, true)) {
            if ($status === 'expired' || $status === 'cancelled') {
                payment_mark_expired($orderId);
            } else {
                payment_notify_user_failed($orderId, 'پرداخت توسط اطلس‌پی رد شد');
            }
            continue;
        }

        if (!in_array($status, $successStatuses, true)) {
            continue;
        }

        if (!empty($check['requiresManualDelivery'])) {
            $channelReport = is_array($setting) ? (string) ($setting['Channel_Report'] ?? '') : '';
            if ($channelReport !== '' && function_exists('telegram')) {
                $actualReceived = isset($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
                $text = "⚠️ سفارش اطلس‌پی با کسری واریز تایید شد — نیاز به بررسی دستی.\n"
                    . "🛒 کد فاکتور: <code>" . htmlspecialchars($orderId) . "</code>\n"
                    . "💰 مبلغ سفارش: " . number_format((int) $row['price']) . " تومان\n"
                    . ($actualReceived !== null ? ('💸 مبلغ واریزی واقعی: ' . number_format($actualReceived) . ' تومان') : '');
                telegram('sendmessage', [
                    'chat_id'    => $channelReport,
                    'message_thread_id' => $ctx['paymentreports'] ?? null,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ]);
            }
            continue;
        }

        $actualReceived = isset($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
        $extra = [
            'method'      => 'atlaspay',
            'thread_id'   => $ctx['paymentreports'] ?? null,
            'extra_lines' => array_filter([
                $actualReceived !== null ? ('💸 مبلغ نهایی تأییدشده توسط اطلس‌پی : ' . number_format($actualReceived) . ' تومان') : '',
                '🔁 تایید از طریق پولر',
            ]),
        ];

        payment_confirm_paid($orderId, 'chashbackatlaspay', $extra);
    } catch (Throwable $e) {
        error_log('[atlaspay_poll] order ' . (string) ($row['id_order'] ?? '') . ': ' . $e->getMessage());
    } finally {
        if ((int) ($row['id'] ?? 0) > 0 && function_exists('rx_cron_invoice_cursor')) {
            rx_cron_invoice_cursor('atlaspay_poll', (string) $row['id']);
        }
    }
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('atlaspay_poll_finished');
}
