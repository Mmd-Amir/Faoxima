<?php

namespace Faoxima\Payments\Gateways\Stars;

use PDO;
use Throwable;

final class Settlement
{
    public static function starsPreCheckoutV2(array $query)
    {
        $orderId = (string) ($query['invoice_payload'] ?? '');
        $report = $orderId === '' ? null : \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $orderId, "select", ['cache' => false]);
        $problem = '';
        if (!is_array($report) || (string) ($report['Payment_Method'] ?? '') !== 'Star Telegram') {
            $problem = 'فاکتور معتبر نیست. لطفاً فاکتور جدید بسازید.';
        } elseif ((string) ($report['id_user'] ?? '') !== (string) ($query['from']['id'] ?? '')) {
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

    public static function starsHandleSuccessfulPayment($runtimePdo, $runtimeSetting, array $update)
    {
        $pdo = $runtimePdo;
        $message = $update['message'] ?? [];
        $payment = $message['successful_payment'] ?? null;
        if (!is_array($payment) || !($pdo instanceof PDO)) {
            return false;
        }
        $orderId = (string) ($payment['invoice_payload'] ?? '');
        $report = $orderId === '' ? null : \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $orderId, "select", ['cache' => false]);
        if (!is_array($report) || (string) ($report['Payment_Method'] ?? '') !== 'Star Telegram') {
            return false;
        }
        \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2PrepareContext();
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
            \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2ReportToAdmin(
                "⚠️ پرداخت استارز دریافت شد اما تأیید خودکار انجام نشد.\n"
                . "🛒 کد فاکتور: <code>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($orderId) . "</code>\n"
                . "👤 آیدی کاربر: <code>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($message['from']['id'] ?? '') . "</code>\n"
                . "🔖 شناسه تراکنش تلگرام: <code>" . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($chargeId) . "</code>\n"
                . "📝 دلیل: " . \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Escape($problem)
            );
            return true;
        }
        try {
            $mark = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = ? WHERE id_order = ? AND Payment_Method = 'Star Telegram' AND payment_Status <> 'paid'");
            $mark->execute([json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $orderId]);
        } catch (Throwable $e) {
            error_log('Stars payment note failed: ' . $e->getMessage());
        }
        $threadRow = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select");
        \Faoxima\Payments\Settlement\Confirmation::confirmPaid($runtimePdo, $runtimeSetting, $orderId, 'chashbackstar', [
            'method' => 'Telegram Stars',
            'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
            'extra_lines' => [
                '⭐️ تعداد استارز: ' . (int) $payment['total_amount'],
                '🔖 شناسه تراکنش تلگرام: ' . $chargeId,
            ],
        ]);
        return true;
        }
}
