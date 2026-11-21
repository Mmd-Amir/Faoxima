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
        $status = '⏳ در انتظار نتیجه';
        if ((int) ($report['rialit_abandoned_at'] ?? 0) > 0 && $localStatus !== 'paid') {
            $status = '🚫 از این پرداخت انصراف داده‌اید. اگر بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.';
        } elseif ($localStatus === 'paid') {
            $status = !empty($report['direct_payment_done']) ? '✅ پرداخت و سفارش تکمیل شد' : '⏳ پرداخت تأیید شد؛ در حال تکمیل سفارش';
        } elseif ($remoteStatus === 'expired' || $remoteStatus === 'cancelled') {
            $status = $remoteStatus === 'expired' ? '⌛ پرداخت منقضی شده است' : '❌ پرداخت در ریالیت لغو شده است';
        } elseif (empty($report['rialit_token'])) {
            $status = '⏳ در حال بازیابی لینک پرداخت';
        } elseif (!\Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkAvailable($report)) {
            $status = '⏳ مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه';
        }
        $text = '<b>' . $escape(\Faoxima\Payments\Gateways\Rialit\Client::displayName()) . "</b>\n\n" . $status
            . "\n🛒 کد پیگیری: <code>" . $escape($orderId) . '</code>'
            . "\n💰 مبلغ سفارش: <b>" . number_format((int) $report['price']) . '</b> تومان';
        if ((int) ($report['rialit_payer_fee'] ?? 0) > 0) {
            $text .= "\n💳 مبلغ پرداختی با کارمزد: <b>" . number_format((int) $report['rialit_payer_amount']) . '</b> تومان';
        }
        $rows = [];
        if (\Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkAvailable($report)) {
            $rows[] = [rx_payment_button('rialit', 'continue_link', ['text' => '💳 ادامه پرداخت', 'url' => \Faoxima\Payments\Gateways\Rialit\Client::paymentUrl($report['rialit_token'])])];
        }
        $rows[] = [rx_payment_button('rialit', 'check_status', ['text' => '🔄 بررسی وضعیت', 'callback_data' => 'rl_view_' . $orderId])];
        if ((int) ($report['rialit_abandoned_at'] ?? 0) === 0 && in_array($localStatus, ['unpaid','pending','waiting','expire','cancelled'], true) && $remoteStatus !== 'paid') {
            $rows[] = [rx_payment_button('rialit', 'cancel_transaction', ['text' => '🚫 انصراف و پرداخت جدید', 'callback_data' => 'rl_cancel_' . $orderId])];
        }
        $rows[] = [rx_payment_button('rialit', 'pending_list', ['text' => '🔙 تراکنش‌های در انتظار', 'callback_data' => 'tpc_list'])];
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
            $text = str_replace('در حال حاضر تراکنش پرداخت نیمه‌تمامی ندارید.', '', (string) ($view['text'] ?? ''));
            foreach ($reports as $report) {
                $paymentView = self::rialitBotPaymentView($report);
                $text .= "\n\n" . $paymentView['text'];
                $rows[] = [rx_payment_button('rialit', 'list_view', ['text' => '💚 پیگیری ریالیت · ' . (string) $report['id_order'], 'callback_data' => 'rl_view_' . (string) $report['id_order']])];
            }
            $rows[] = [rx_payment_button('rialit', 'back_account', ['text' => '🔙 بازگشت', 'callback_data' => 'account'])];
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
