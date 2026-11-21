<?php

namespace Faoxima\Payments\Settlement;

final class ConfirmationFunctions
{
    public static function rx_payment_retry_kb(): ?string {
        global $usernamebot, $domainhosts;
        $bot = isset($usernamebot) ? ltrim(trim((string)$usernamebot), '@') : '';

        $host = isset($domainhosts) ? trim((string)$domainhosts) : '';
        $host = rtrim(preg_replace('#^https?://#', '', $host), '/');
        $miniAppUrl = $host !== '' ? ('https://' . $host . '/app/') : '';

        $supportRow = \Faoxima\Core\Database\Db::runtimeReady()
            ? \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select')
            : null;
        $supportHandle = '';
        if (is_array($supportRow) && !empty($supportRow['support_username'])) {
            $supportHandle = ltrim(trim((string)$supportRow['support_username']), '@');
        }
        $supportUrl = $supportHandle !== ''
            ? 'https://t.me/' . $supportHandle
            : ($bot !== '' ? 'https://t.me/' . $bot : '');

        $rows = [];
        if ($miniAppUrl !== '') {
            $rows[] = [['text' => '🔁 شارژ مجدد', 'web_app' => ['url' => $miniAppUrl . '#/recharge']]];
        }
        if ($supportUrl !== '') {
            $rows[] = [['text' => '📞 پشتیبانی', 'url' => $supportUrl]];
        }
        if (empty($rows)) return null;

        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function payment_confirm_paid(string $orderId, string $cashbackKey, array $reportData = []): array
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Settlement\Confirmation::confirmPaid($pdo, $setting, $orderId, $cashbackKey, $reportData);
    }

    public static function rx_blupal_confirmation_lines($creditAmountToman, string $payerName, string $payerCard, string $payerBank): array
    {
        return array_filter([
            $creditAmountToman > 0 ? ('💸 مبلغ نهایی تأییدشده توسط بلوپال : ' . number_format($creditAmountToman) . ' تومان') : '',
            $payerName !== '' ? ('👤 نام واریزکننده : ' . $payerName) : '',
            $payerCard !== '' ? ('💳 شماره کارت/شبا واریزکننده : ' . $payerCard) : '',
            $payerBank !== '' ? ('🏦 بانک واریزکننده : ' . $payerBank) : '',
        ]);
    }

    public static function rx_plisio_paid_line(string $paidAmount, string $paidCurrency): string
    {
        return $paidAmount !== ''
            ? ("📥 مبلغ واریز شده : <b>{$paidAmount}</b>" . ($paidCurrency !== '' ? ' ' . strtoupper($paidCurrency) : ''))
            : "📥 مبلغ واریز شده : —";
    }

    public static function rx_pc_mark_unsettled_terminal($pdo, string $orderId, string $target): ?array
    {
        return \Faoxima\Payments\Settlement\Confirmation::markUnsettledTerminal($pdo, $orderId, $target);
    }

    public static function payment_notify_user_failed(string $orderId, string $reason = ''): bool
    {
        global $pdo;
        $report = self::rx_pc_mark_unsettled_terminal($pdo, $orderId, 'reject');
        if ($report === null) return false;

        if (!function_exists('telegram')) return true;

        $priceFmt = number_format((int)($report['price'] ?? 0));
        $reasonFa = trim($reason) !== '' ? trim($reason) : 'تراکنش از سمت درگاه لغو شد';

        $text = "❌ <b>پرداخت تأیید نشد</b>\n\n"
              . "🛒 کد فاکتور: <code>" . htmlspecialchars($orderId) . "</code>\n"
              . "💸 مبلغ: <b>{$priceFmt}</b> تومان\n"
              . "📝 دلیل: " . htmlspecialchars($reasonFa) . "\n\n"
              . "اگر مطمئنید پرداخت انجام شده، با پشتیبانی تماس بگیرید.";
        $kb = self::rx_payment_retry_kb();
        telegram('sendmessage', array_filter([
            'chat_id'      => (string)$report['id_user'],
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $kb,
        ]));
        return true;
    }

    public static function payment_mark_expired(string $orderId, ?string $textExpire = null): bool
    {
        global $pdo;
        return \Faoxima\Payments\Settlement\Confirmation::markExpired($pdo, $orderId, $textExpire);
    }
}
