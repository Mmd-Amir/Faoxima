<?php

namespace Faoxima\Payments\Gateways\Rialit;

use PDO;
use Throwable;

final class BotRuntime
{
    public static function rialitWebhookUrl(): string
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Rialit\Access::webhookUrl($domainhosts);
    }

    public static function rialitRegisterWebhook($url = null): array
    {
        global $domainhosts, $pdo;
        return \Faoxima\Payments\Gateways\Rialit\Client::registerWebhook($domainhosts, $pdo, $url);
    }

    public static function rialitWebhookStatusSummary(): array
    {
        global $domainhosts, $pdo;
        return \Faoxima\Payments\Gateways\Rialit\Client::webhookStatusSummary($domainhosts, $pdo);
    }

    public static function rialitUserHasPending($userId): bool
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Rialit\PaymentOrders::userHasPending($pdo, $userId);
    }

    public static function rialitBotPaymentView(array $report): array
    {
        $escape = static function ($value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $orderId = (string) $report['id_order'];
        $localStatus = strtolower((string) ($report['payment_Status'] ?? ''));
        $remoteStatus = (string) ($report['rialit_status'] ?? '');
        $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_waiting');
        if ((int) ($report['rialit_abandoned_at'] ?? 0) > 0 && $localStatus !== 'paid') {
            $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_abandoned');
        } elseif ($localStatus === 'paid') {
            $status = !empty($report['direct_payment_done']) ? \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_completed') : \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_paid_finalizing');
        } elseif ($remoteStatus === 'expired' || $remoteStatus === 'cancelled') {
            $status = $remoteStatus === 'expired' ? \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_expired') : \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_canceled');
        } elseif (empty($report['rialit_token'])) {
            $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_recovering_link');
        } elseif (!\Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkAvailable($report)) {
            $status = \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_status_link_expired');
        }
        $text = '<b>' . $escape(\Faoxima\Payments\Gateways\Rialit\Client::displayName()) . "</b>\n\n" . $status
            . "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_line_tracking', ['order_id' => $orderId])
            . "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_line_amount', ['amount' => number_format((int) $report['price'])]);
        if ((int) ($report['rialit_payer_fee'] ?? 0) > 0) {
            $text .= "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_line_payer_amount', ['amount' => number_format((int) $report['rialit_payer_amount'])]);
        }
        $rows = [];
        if (\Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkAvailable($report)) {
            $rows[] = [rx_payment_button('rialit', 'continue_link', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_rialit_continue'), 'url' => \Faoxima\Payments\Gateways\Rialit\Client::paymentUrl($report['rialit_token'])])];
        }
        $rows[] = [rx_payment_button('rialit', 'check_status', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_rialit_check_status'), 'callback_data' => 'rl_view_' . $orderId])];
        if ((int) ($report['rialit_abandoned_at'] ?? 0) === 0 && in_array($localStatus, ['unpaid','pending','waiting','expire','cancelled'], true) && $remoteStatus !== 'paid') {
            $rows[] = [rx_payment_button('rialit', 'cancel_transaction', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_rialit_cancel_new'), 'callback_data' => 'rl_cancel_' . $orderId])];
        }
        $rows[] = [rx_payment_button('rialit', 'pending_list', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_rialit_pending_list'), 'callback_data' => 'tpc_list'])];
        return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }

    public static function rialitBotListMerge($userId, array $view): array
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return $view;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = ? AND Payment_Method = 'rialit' AND payment_Status IN ('Unpaid','pending','waiting') AND COALESCE(rialit_abandoned_at, 0) = 0 AND (rialit_status IS NULL OR rialit_status IN ('pending','paid')) ORDER BY id DESC LIMIT 8");
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
                $paymentView = self::rialitBotPaymentView($report);
                $text .= "\n\n" . $paymentView['text'];
                $rows[] = [rx_payment_button('rialit', 'list_view', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_rialit_follow', ['order_id' => (string) $report['id_order']]), 'callback_data' => 'rl_view_' . (string) $report['id_order']])];
            }
            $rows[] = [rx_payment_button('rialit', 'back_account', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => 'account'])];
            return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_BOT_LIST_FAILED', $e->getMessage());
            return $view;
        }
    }

    public static function rialitVerifyPayment($orderId, array $options = []): array
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Rialit\Recovery::verifyPayment($pdo, $setting, $orderId, $options);
    }

    public static function rialitPollThrottled($orderId, $userId = null)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Rialit\Recovery::pollThrottled($pdo, $setting, $orderId, $userId);
    }

    public static function rialitEnsureWebhookSchema(?PDO $pdo = null): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        $pdo = $pdo instanceof PDO ? $pdo : (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : null);
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->query('SELECT 1 FROM rialit_webhook_event LIMIT 1');
            $ready = true;
            return true;
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec(\Faoxima\Payments\Gateways\Rialit\WebhookQueue::webhookTableDdl());
            $ready = true;
            return true;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_WEBHOOK_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }

    public static function rialitProcessWebhookEvents($limit = 20): array
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Rialit\WebhookQueue::processWebhookEvents($pdo, $setting, $limit);
    }

    public static function rialitProcessPendingOrders($limit = 20): array
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Rialit\WebhookQueue::processPendingOrders($pdo, $setting, $limit);
    }
}
