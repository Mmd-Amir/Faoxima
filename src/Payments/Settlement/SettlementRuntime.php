<?php

namespace Faoxima\Payments\Settlement;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class SettlementRuntime
{
    public static function rx_release_unpaid_discount($userId, $discountCode = null, $referenceTime = null) {
        global $pdo;
        return \Faoxima\Orders\Discounts\SellDiscounts::releaseUnpaidDiscount($pdo, $userId, $discountCode, $referenceTime);
    }

    public static function rx_wallet_transfer_report(array $sender, array $recipient, int $amount, string $operationId, float $senderAfter, float $recipientAfter): void
    {
        try {
            $settings = \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select');
            $chatId = is_array($settings) ? trim((string)($settings['Channel_Report'] ?? '')) : '';
            if ($chatId === '' || !function_exists('telegram')) {
                error_log('Wallet transfer admin report unavailable: ' . $operationId);
                return;
            }
            $topic = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select');
            $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $senderName = ltrim(trim((string)($sender['username'] ?? '')), '@');
            $recipientName = ltrim(trim((string)($recipient['username'] ?? '')), '@');
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
            $date = function_exists('jdate') ? jdate('Y/m/d H:i:s', $now->getTimestamp(), '', 'Asia/Tehran', 'en') : $now->format('Y/m/d H:i:s');
            $text = "💰 <b>گزارش مالی — انتقال کیف پول</b>\n\n✅ وضعیت: انتقال موفق\n"
                . "👤 فرستنده: <code>" . $escape($sender['id']) . "</code>\n"
                . "نام کاربری فرستنده: " . ($senderName !== '' ? '@' . $escape($senderName) : 'ثبت نشده') . "\n\n"
                . "👤 گیرنده: <code>" . $escape($recipient['id']) . "</code>\n"
                . "نام کاربری گیرنده: " . ($recipientName !== '' ? '@' . $escape($recipientName) : 'ثبت نشده') . "\n\n"
                . "💵 مبلغ انتقال: <b>" . number_format($amount) . " تومان</b>\n"
                . "موجودی فرستنده پیش از انتقال: " . number_format((float)$sender['Balance']) . " تومان\n"
                . "موجودی فرستنده پس از انتقال: " . number_format($senderAfter) . " تومان\n"
                . "موجودی گیرنده پیش از انتقال: " . number_format((float)$recipient['Balance']) . " تومان\n"
                . "موجودی گیرنده پس از انتقال: " . number_format($recipientAfter) . " تومان\n\n"
                . "📅 تاریخ و ساعت تهران: " . $escape($date) . "\n"
                . "🧾 شناسه انتقال: <code>" . $escape($operationId) . "</code>";
            $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
            if (is_array($topic) && (int)($topic['idreport'] ?? 0) > 0) $payload['message_thread_id'] = (int)$topic['idreport'];
            $sent = telegram('sendmessage', $payload);
            if (!is_array($sent) || empty($sent['ok'])) error_log('Wallet transfer admin report failed: ' . $operationId);
        } catch (Throwable $e) {
            error_log('Wallet transfer admin report failed: ' . $operationId . ' ' . $e->getMessage());
        }
    }

    public static function rx_refund_invoice_once(string $invoiceId, string $claimSql, array $claimParams, $userId, $amount, string $description): string
    {
        global $pdo;
        return \Faoxima\Payments\Settlement\Refunds::invoiceOnce($pdo, $invoiceId, $claimSql, $claimParams, $userId, $amount, $description);
    }

    public static function rx_cashback_credit_once($orderId, $userId, $amount, string $cashbackKey, string $description): string
    {
        global $pdo;
        return \Faoxima\Cashback\Credits::creditOnce($pdo, $orderId, $userId, $amount, $cashbackKey, $description);
    }
}
