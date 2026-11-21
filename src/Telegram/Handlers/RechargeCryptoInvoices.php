<?php

namespace Faoxima\Telegram\Handlers;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $callback_query_id, $datain, $datatextbot, $from_id, $message_id, $pdo, $telegramRoute, $user.
 * Inherited or exported by reference: $r.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RechargeCryptoInvoices
{
    public static function handle(
        $callback_query_id,
        $datain,
        $datatextbot,
        $from_id,
        $message_id,
        $pdo,
        &$r,
        $telegramRoute,
        $user
    ) {
        switch ($telegramRoute) {
        case 'crypto_cancel':
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $datatextbot['dyn_wallet_recheck_canceled'] ?? "❌ درخواست بررسی مجدد لغو شد.", null);
            } else {
                sendmessage($from_id, $datatextbot['dyn_wallet_recheck_canceled'] ?? "❌ درخواست بررسی مجدد لغو شد.", null, 'HTML');
            }
            break;
        case 'crypto_recheck':
            if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') && empty(\Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets())) {
                $rccNoWalletMsg = "⚠️ در حال حاضر هیچ درگاه پرداخت کریپتویی فعال نیست. لطفاً با پشتیبانی تماس بگیرید.";
                if ($message_id) {
                    Editmessagetext($from_id, $message_id, $rccNoWalletMsg, null);
                } else {
                    sendmessage($from_id, $rccNoWalletMsg, null, 'HTML');
                }
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $rccListRows = [];
            try {
                $rccListStmt = $pdo->prepare(
                    "SELECT id_order, crypto_currency, crypto_amount, price, time
               FROM Payment_report
              WHERE id_user = :u
                AND payment_Status = 'reject'
                AND crypto_tx_hash IS NOT NULL
                AND crypto_tx_hash <> ''
              ORDER BY id DESC
              LIMIT 10"
                );
                $rccListStmt->execute([':u' => (string) $from_id]);
                $rccListRows = $rccListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {  }

            if (empty($rccListRows)) {
                $rccEmptyMsg = "📭 <b>هیچ تراکنش رد شده‌ای ندارید</b>\n\n"
                    . "تراکنش‌های کریپتویی شما که توسط ربات رد شده باشن، در این بخش نمایش داده میشن.";
                if ($message_id) {
                    Editmessagetext($from_id, $message_id, $rccEmptyMsg, null);
                } else {
                    sendmessage($from_id, $rccEmptyMsg, null, 'HTML');
                }
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            $rccKbRows = [];
            foreach ($rccListRows as $r) {
                $rccLabel = '🪙 ' . (string) ($r['crypto_currency'] ?? '?')
                    . ' | ' . number_format((int) ($r['price'] ?? 0)) . ' ت'
                    . ' | ' . (string) ($r['id_order'] ?? '');
                $rccViewBtn = ['text' => $rccLabel, 'callback_data' => 'rcc_view_' . (string) ($r['id_order'] ?? '')];
                $rccViewStyle = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_action_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_action_style('rcc_view') : null;
                if ($rccViewStyle) $rccViewBtn['style'] = $rccViewStyle;
                $rccKbRows[] = [$rccViewBtn];
            }
            $rccKb = json_encode(['inline_keyboard' => $rccKbRows], JSON_UNESCAPED_UNICODE);
            $rccListMsg = "🔁 <b>تراکنش‌های رد شده شما</b>\n\n"
                . "روی هر تراکنش کلیک کنید تا جزئیاتش رو ببینید و در صورت لزوم برای بررسی دستی ادمین ارسال کنید.";
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $rccListMsg, $rccKb);
            } else {
                sendmessage($from_id, $rccListMsg, $rccKb, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'crypto_view':
            $rccVOid = substr((string) $datain, strlen('rcc_view_'));
            $rccVRow = null;
            try {
                $rccVStm = $pdo->prepare(
                    "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
                );
                $rccVStm->execute([':o' => $rccVOid, ':u' => (string) $from_id]);
                $rccVRow = $rccVStm->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {  }

            if (!is_array($rccVRow)) {
                if ($callback_query_id && function_exists('telegram')) {
                    @telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                        'show_alert' => true,
                    ]);
                }
                return;
            }

            $rccVExpUrl = is_callable([\Faoxima\Payments\Gateways\CryptoQuotes::class, 'cryptoExplorerUrl'])
                ? \Faoxima\Payments\Gateways\CryptoQuotes::cryptoExplorerUrl((string) ($rccVRow['crypto_currency'] ?? ''), (string) ($rccVRow['crypto_tx_hash'] ?? ''))
                : '';
            $rccVReasonRaw = (string) ($rccVRow['crypto_last_error'] ?? ($rccVRow['dec_not_confirmed'] ?? ''));
            $rccVReason = $rccVReasonRaw !== '' ? mb_substr($rccVReasonRaw, 0, 200) : '—';

            $rccVText = "🧾 <b>جزئیات تراکنش رد شده</b>\n\n"
                . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccVOid) . "</code>\n"
                . "💎 ارز: <b>" . htmlspecialchars((string) ($rccVRow['crypto_currency'] ?? '-')) . "</b>\n"
                . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccVRow['crypto_amount'] ?? '-')) . "</code>\n"
                . "💸 معادل تومانی: " . number_format((int) ($rccVRow['price'] ?? 0)) . " تومان\n"
                . "🔗 هش: <code>" . htmlspecialchars((string) ($rccVRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
                . "📅 زمان: " . htmlspecialchars((string) ($rccVRow['time'] ?? '-')) . "\n"
                . "📝 دلیل عدم تایید: " . htmlspecialchars($rccVReason)
                . ($rccVExpUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($rccVExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>" : '');

            $rccResubmitBtn = ['text' => '📨 ارسال مجدد به ادمین', 'callback_data' => 'rcc_resubmit_' . $rccVOid];
            $rccResubmitStyle = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_action_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_action_style('rcc_resubmit') : null;
            if ($rccResubmitStyle) $rccResubmitBtn['style'] = $rccResubmitStyle;
            $rccVKb = json_encode([
                'inline_keyboard' => [
                    [$rccResubmitBtn],
                    [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'recheckcrypto', '_rxcolor' => 'navigation:crypto_back', '_rxkeep' => true]],
                ],
            ], JSON_UNESCAPED_UNICODE);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $rccVText, $rccVKb);
            } else {
                sendmessage($from_id, $rccVText, $rccVKb, 'HTML');
            }
            break;
        case 'crypto_resubmit':
            $rccROid = substr((string) $datain, strlen('rcc_resubmit_'));
            $rccRRow = null;
            try {
                $rccRStm = $pdo->prepare(
                    "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
                );
                $rccRStm->execute([':o' => $rccROid, ':u' => (string) $from_id]);
                $rccRRow = $rccRStm->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {  }

            if (!is_array($rccRRow)) {
                if ($callback_query_id && function_exists('telegram')) {
                    @telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                        'show_alert' => true,
                    ]);
                }
                return;
            }

            try {
                $rccRUpd = $pdo->prepare(
                    "UPDATE Payment_report SET payment_Status = 'ManualPending', at_updated = :now
              WHERE id_order = :o AND payment_Status = 'reject'"
                );
                $rccRUpd->execute([':now' => date('Y/m/d H:i:s'), ':o' => $rccROid]);
                if ($rccRUpd->rowCount() < 1) {
                    if ($callback_query_id && function_exists('telegram')) {
                        @telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'text' => '❌ قبلاً ارسال شده',
                            'show_alert' => true,
                        ]);
                    }
                    return;
                }
            } catch (Throwable $e) {
                if ($callback_query_id && function_exists('telegram')) {
                    @telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => '❌ خطای داخلی',
                        'show_alert' => true,
                    ]);
                }
                return;
            }

            $rccRExpUrl = is_callable([\Faoxima\Payments\Gateways\CryptoQuotes::class, 'cryptoExplorerUrl'])
                ? \Faoxima\Payments\Gateways\CryptoQuotes::cryptoExplorerUrl((string) ($rccRRow['crypto_currency'] ?? ''), (string) ($rccRRow['crypto_tx_hash'] ?? ''))
                : '';
            $rccRUserTag = '@' . ($user['username'] ?? 'none');
            $rccRReasonRaw = (string) ($rccRRow['crypto_last_error'] ?? ($rccRRow['dec_not_confirmed'] ?? ''));
            $rccRReason = $rccRReasonRaw !== '' ? mb_substr($rccRReasonRaw, 0, 200) : '—';
            $rccRAdminCaption = "🔁 <b>درخواست بررسی دستی توسط کاربر</b>\n\n"
                . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n"
                . "👤 کاربر: <code>{$from_id}</code> ({$rccRUserTag})\n"
                . "💎 ارز: <b>" . htmlspecialchars((string) ($rccRRow['crypto_currency'] ?? '-')) . "</b>\n"
                . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccRRow['crypto_amount'] ?? '-')) . "</code>\n"
                . "💸 معادل تومانی: " . number_format((int) ($rccRRow['price'] ?? 0)) . " تومان\n"
                . "🔗 هش: <code>" . htmlspecialchars((string) ($rccRRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
                . "📝 دلیل اولیه رد: " . htmlspecialchars($rccRReason)
                . ($rccRExpUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($rccRExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>" : '');

            if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_lookup_verified_hash')) {
                $rccRExisting = \Faoxima\Payments\Crypto\ManualCrypto::crypto_lookup_verified_hash((string) ($rccRRow['crypto_tx_hash'] ?? ''));
                if (is_array($rccRExisting)) {
                    $rccRAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                        . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string) $rccRExisting['order_id']) . "</code>\n"
                        . "👤 کاربر قبلی: <code>" . htmlspecialchars((string) ($rccRExisting['user_id'] ?? '-')) . "</code>";
                }
            }

            $rccRAdminKb = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تایید خودکار', 'callback_data' => 'cmauto_' . $rccROid],
                        ['text' => '✏️ تایید دستی',   'callback_data' => 'cmmanual_' . $rccROid],
                    ],
                    [
                        ['text' => '🗑️ لغو و حذف از دیتابیس', 'callback_data' => 'cmdelete_' . $rccROid],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

            $rccRAdminIds = \Faoxima\Core\Database\Db::runtimeReady() ? (\Faoxima\Core\Database\Db::select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
            if (!is_array($rccRAdminIds)) {
                $rccRAdminIds = [];
            }
            if (function_exists('telegram')) {
                foreach ($rccRAdminIds as $rccRAdminOne) {
                    if (!is_numeric($rccRAdminOne)) continue;
                    @telegram('sendmessage', [
                        'chat_id' => (string)$rccRAdminOne,
                        'text' => $rccRAdminCaption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => $rccRAdminKb,
                    ]);
                }
            }

            $rccRUserMsg = "✅ <b>درخواست شما برای بررسی دستی ارسال شد</b>\n\n"
                . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n\n"
                . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام می‌شود.";
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $rccRUserMsg, null);
            } else {
                sendmessage($from_id, $rccRUserMsg, null, 'HTML');
            }
            break;
        }

        return true;
    }
}
