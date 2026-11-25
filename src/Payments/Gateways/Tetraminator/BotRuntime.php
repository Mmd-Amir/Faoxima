<?php

namespace Faoxima\Payments\Gateways\Tetraminator;

use PDO;
use Throwable;

final class BotRuntime
{
    public static function tetraminatorUserHasPending($userId): bool
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::userHasPending($pdo, $userId);
    }

    public static function tetraminatorBotPaymentView(array $report): array
    {
        $escape = static function ($value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $orderId = (string) $report['id_order'];
        $localStatus = strtolower((string) ($report['payment_Status'] ?? ''));
        $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_status_waiting');
        if ((int) ($report['tetraminator_abandoned_at'] ?? 0) > 0 && $localStatus !== 'paid') {
            $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_status_abandoned');
        } elseif ($localStatus === 'paid') {
            $status = !empty($report['direct_payment_done']) ? \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_status_completed') : \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_status_paid_finalizing');
        } elseif ($localStatus === 'expire') {
            $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_status_expired');
        } elseif (empty($report['tetraminator_pay_id'])) {
            $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_status_recovering_link');
        } elseif (!\Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::linkAvailable($report)) {
            $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_status_link_expired');
        }
        $text = '<b>' . $escape(\Faoxima\Payments\Gateways\Tetraminator\Client::displayName()) . "</b>\n\n" . $status
            . "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_line_tracking', ['order_id' => $orderId])
            . "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_line_amount', ['amount' => number_format((int) $report['price'])]);
        $rows = [];
        if (\Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::linkAvailable($report)) {
            $rows[] = [rx_payment_button('tetraminator', 'continue_link', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_tetraminator_continue'), 'url' => \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::paymentUrl($report)])];
        }
        $rows[] = [rx_payment_button('tetraminator', 'check_status', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_tetraminator_check_status'), 'callback_data' => 'tm_view_' . $orderId])];
        if ((int) ($report['tetraminator_abandoned_at'] ?? 0) === 0 && in_array($localStatus, ['unpaid', 'pending', 'waiting', 'expire', 'cancelled'], true) && strtolower((string) ($report['tetraminator_status'] ?? '')) !== 'paid') {
            $rows[] = [rx_payment_button('tetraminator', 'cancel_transaction', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_tetraminator_cancel_new'), 'callback_data' => 'tm_cancel_' . $orderId])];
        }
        $rows[] = [rx_payment_button('tetraminator', 'pending_list', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_tetraminator_pending_list'), 'callback_data' => 'tpc_list'])];
        return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }

    public static function tetraminatorBotListMerge($userId, array $view): array
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return $view;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = ? AND Payment_Method = 'tetraminator' AND payment_Status IN ('Unpaid','pending','waiting') AND COALESCE(tetraminator_abandoned_at, 0) = 0 AND tetraminator_pay_id IS NOT NULL AND tetraminator_pay_id <> '' ORDER BY id DESC LIMIT 8");
            $stmt->execute([(string) $userId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($reports)) {
                return $view;
            }
            $keyboard = json_decode((string) ($view['keyboard'] ?? ''), true);
            $rows = is_array($keyboard['inline_keyboard'] ?? null) ? $keyboard['inline_keyboard'] : [];
            if (!empty($rows) && ($rows[count($rows) - 1][0]['callback_data'] ?? '') === 'account') {
                array_pop($rows);
            }
            $text = str_replace(\Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_list_empty_note'), '', (string) ($view['text'] ?? ''));
            foreach ($reports as $report) {
                $paymentView = self::tetraminatorBotPaymentView($report);
                $text .= "\n\n" . $paymentView['text'];
                $rows[] = [rx_payment_button('tetraminator', 'list_view', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_tetraminator_follow', ['order_id' => (string) $report['id_order']]), 'callback_data' => 'tm_view_' . (string) $report['id_order']])];
            }
            $rows[] = [rx_payment_button('tetraminator', 'back_account', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => 'account'])];
            return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_BOT_LIST_FAILED', $e->getMessage());
            return $view;
        }
    }

    public static function tetraminatorBotGuard($userId): bool
    {
        global $pdo, $setting;
        if (!\Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::holdUserLock($pdo, $userId, 5)) {
            sendmessage($userId, \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_processing'), null, 'HTML');
            return false;
        }
        $blocking = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::resolveBlockingInvoice($pdo, $setting, $userId);
        if ($blocking === null) {
            return true;
        }
        $notice = \Faoxima\Telegram\Support\MessageTemplates::get(!empty($blocking['ambiguous']) ? 'payment_tetraminator_active_invoice_unverifiable' : 'payment_tetraminator_active_invoice_blocked');
        if (!empty($blocking['lookup_failed']) || empty($blocking['report']['id_order'])) {
            sendmessage($userId, $notice, null, 'HTML');
            return false;
        }
        $view = self::tetraminatorBotPaymentView($blocking['report']);
        $messageId = sendmessage($userId, $notice . "\n\n" . $view['text'], $view['keyboard'], 'HTML');
        \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($messageId, (string) $blocking['report']['id_order']);
        return false;
    }

    public static function tetraminatorVerifyPayment($orderId, array $options = []): array
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Tetraminator\Recovery::verifyPayment($pdo, $setting, $orderId, $options);
    }

    public static function tetraminatorPollThrottled($orderId, $userId = null)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Tetraminator\Recovery::pollThrottled($pdo, $setting, $orderId, $userId);
    }
}
