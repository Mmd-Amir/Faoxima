<?php

namespace Faoxima\Payments\Receipts;

use Throwable;

final class ReceiptActions
{
    public static function rxReceiptListScopeSql(): string
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ManualReview::receiptListScopeSql($pdo);
    }

    public static function rxReceiptGet(string $orderId, bool $useCache = true)
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ManualReview::receiptGet($pdo, $orderId, $useCache);
    }

    public static function rxReceiptSyncTelegram(array $report, string $text, $keyboard = null): void
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

    public static function rxReceiptRejectSideEffects(array $finalReport, string $orderId, string $reason, string $actorLabel = '', bool $skipTelegramSync = false, bool $notifyUser = true): array
    {
        $reasonEsc = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        if (!$skipTelegramSync) {
            $textConfirmed = "❌ رسید پرداخت رد شد.\n\n"
                . "اطلاعات :\n"
                . "<blockquote>💸 روش پرداخت : " . (string) ($finalReport['Payment_Method'] ?? '') . "</blockquote>\n"
                . "<blockquote>👤آیدی عددی  ادمین رد کننده : " . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
                . "<blockquote>💰 مبلغ پرداخت : " . (method_exists(\Faoxima\Core\Support\Formatting::class, 'rxFormatToman') ? \Faoxima\Core\Support\Formatting::rxFormatToman($finalReport['price'] ?? 0) : number_format((int) ($finalReport['price'] ?? 0))) . "</blockquote>\n"
                . "<blockquote>دلیل رد کردن : {$reasonEsc}</blockquote>\n"
                . "<blockquote>👤 ایدی عددی کاربر: " . (string) ($finalReport['id_user'] ?? '') . "</blockquote>";
            self::rxReceiptSyncTelegram($finalReport, $textConfirmed, null);
        }

        $notified = false;
        if ($notifyUser && function_exists('sendmessage')) {
            $textReject = "❌ کاربر گرامی، درخواست پرداخت شما رد شد.\n"
                . "✍️ دلیل: {$reasonEsc}\n"
                . "🛒 کد پیگیری پرداخت: {$orderId}\n"
                . "ℹ️ این تصمیم درباره درخواست پرداخت است؛ به‌تنهایی به معنی دریافت، تأیید یا بازگشت وجه نیست.";
            $targetUserId = $finalReport['id_user'] ?? null;
            if ($targetUserId) {
                try {
                    $notifyResult = sendmessage($targetUserId, $textReject, null, 'HTML');
                    $notified = is_array($notifyResult) ? !empty($notifyResult['ok']) : $notifyResult !== null;
                } catch (Throwable $e) {
                    $notified = false;
                    if (function_exists('rx_log_event')) {
                        rx_log_event('RECEIPT_REJECT_NOTIFY_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                    }
                }
            }
        }
        return ['notified' => $notified];
    }

    public static function rxReceiptConfirm(string $orderId, array $ctx = []): array
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Receipts\ManualReview::receiptConfirm($pdo, $setting, $orderId, $ctx);
    }

    public static function rxReceiptReject(string $orderId, string $reason, array $ctx = []): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ManualReview::receiptReject($pdo, $orderId, $reason, $ctx);
    }

    public static function rxReceiptReopen(string $orderId, array $ctx = []): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ManualReview::receiptReopen($pdo, $orderId, $ctx);
    }

    public static function rxCancelAbandonedCardPayment(string $orderId, $userId, string $reason, array $opts = []): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Cancellation::cancelAbandonedCardPayment($pdo, $orderId, $userId, $reason, $opts);
    }

    public static function rxReceiptHardDelete(string $orderId): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Cancellation::receiptHardDelete($pdo, $orderId);
    }
}
