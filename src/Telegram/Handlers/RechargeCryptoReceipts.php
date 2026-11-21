<?php

namespace Faoxima\Telegram\Handlers;

use Throwable;

/**
 * Handler stage. Inherited by value: $connect, $datain, $datatextbot, $from_id, $message_id, $paymentreports, $pdo, $photoid, $rccNetwork, $rccPick, $setting, $telegramRoute, $text, $user.
 * Inherited or exported by reference: $payload.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RechargeCryptoReceipts
{
    public static function handle(
        $connect,
        $datain,
        $datatextbot,
        $from_id,
        $message_id,
        &$payload,
        $paymentreports,
        $pdo,
        $photoid,
        $rccNetwork,
        $rccPick,
        $setting,
        $telegramRoute,
        $text,
        $user
    ) {
        switch ($telegramRoute) {
        case 'crypto_pick':
            $rccCur = $rccPick[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $rccCur, "id", $from_id);
            $rccWalletsFa = array_column(method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets() : [], 'label', 'currency');
            $rccCurFa = [
                'TRX' => 'ترون (TRX)',
                'TON' => 'تون (TON)',
                'USDT_TRC20' => 'تتر روی ترون (USDT-TRC20)',
                'USDT_TON' => 'تتر روی تون (USDT-TON)',
            ][$rccCur] ?? ($rccWalletsFa[$rccCur] ?? $rccCur);
            $askAmount = "💎 ارز انتخابی: <b>{$rccCurFa}</b>\n\n"
                       . "🪙 لطفاً <b>مقدار ارز پرداخت‌شده</b> را وارد کنید (مثلاً <code>0.2</code> یا <code>1.25</code>):";
            $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $askAmount, $cancelKb);
            } else {
                sendmessage($from_id, $askAmount, $cancelKb, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('rcc_amount', $from_id);
            break;
        case 'crypto_amount':
            $coinAmount = trim(str_replace([',', '،'], ['.', '.'], (string) $text));
            if (!is_numeric($coinAmount) || (float) $coinAmount <= 0) {
                sendmessage($from_id, $datatextbot['dyn_wallet_amount_not_numeric'] ?? "❌ مقدار وارد شده عددی نیست. لطفاً عددی مثل <code>0.2</code> ارسال کنید.", null, 'HTML');
                return;
            }
            $rccCurForRate = (string) ($user['Processing_value_one'] ?? '');
            $rateForCalc = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_irt_rate_for') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_irt_rate_for($rccCurForRate) : null;
            if ($rateForCalc === null || $rateForCalc <= 0) {
                sendmessage($from_id, $datatextbot['dyn_wallet_rate_unavailable'] ?? "❌ نرخ لحظه‌ای ارز در دسترس نیست. لطفاً دقایقی دیگر تلاش کنید.", null, 'HTML');
                return;
            }
            $calcIrr = (int) round(((float) $coinAmount) * $rateForCalc);
            if ($calcIrr <= 0) {
                sendmessage($from_id, $datatextbot['dyn_wallet_calculated_amount_invalid'] ?? "❌ مبلغ محاسبه‌شده معتبر نیست. مقدار ارز را بررسی کنید.", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $coinAmount, "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", (string) $calcIrr, "id", $from_id);
            $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
            sendmessage(
                $from_id,
                "💰 <b>محاسبه‌ی خودکار قیمت</b>\n\n"
                . "💎 ارز: <b>{$rccCurForRate}</b>\n"
                . "🪙 مقدار: <code>{$coinAmount}</code>\n"
                . "📈 نرخ لحظه‌ای: <code>" . number_format((float) $rateForCalc) . "</code> تومان\n"
                . "💵 <b>معادل تومانی محاسبه‌شده:</b> " . number_format($calcIrr) . " تومان\n\n"
                . "🔗 حالا <b>هش (TxID) تراکنش</b> را ارسال کنید:\n"
                . "<i>می‌توانید لینک کامل Tonviewer / Tonscan / Tronscan یا هش خام را بفرستید.</i>",
                $cancelKb,
                'HTML'
            );
            \Faoxima\Telegram\Navigation\Steps::step('rcc_hash', $from_id);
            break;
        case 'crypto_hash':
            $hashTry = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_extract_hash') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_extract_hash((string) $text) : null;
            if ($hashTry === null) {
                sendmessage($from_id, $datatextbot['dyn_wallet_hash_not_found'] ?? "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را بفرستید — نه آدرس کیف‌پول.", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $hashTry, "id", $from_id);
            $photoKb = json_encode([
                'inline_keyboard' => [
                    [['text' => '⏭ ارسال بدون عکس (رد شدن)', 'callback_data' => 'rcc_skip_photo']],
                    [['text' => '❌ انصراف',                    'callback_data' => 'rcc_cancel']],
                ],
            ], JSON_UNESCAPED_UNICODE);
            sendmessage($from_id, $datatextbot['dyn_wallet_ask_receipt_photo'] ?? "📸 <b>عکس رسید/اسکرین‌شات تراکنش</b> را ارسال کنید (اختیاری).\n\nاگر عکسی ندارید، روی دکمه «ارسال بدون عکس» بزنید.", $photoKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('rcc_photo', $from_id);
            break;
        case 'crypto_photo':
            $rccCur     = (string) $user['Processing_value_one'];
            $rccCoin    = (string) $user['Processing_value_tow'];
            $rccIrr     = (int)    $user['Processing_value'];
            $rccHash    = (string) $user['Processing_value_four'];
            $rccPhotoId = $datain === "rcc_skip_photo" ? '' : (string) $photoid;
            $rccKnownCurs = array_column(method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets() : [], 'currency');
            if (!in_array($rccCur, $rccKnownCurs, true) || $rccCoin === '' || $rccIrr <= 0 || $rccHash === '') {
                sendmessage($from_id, $datatextbot['dyn_wallet_incomplete_info'] ?? "❌ اطلاعات ناقص است. لطفاً از ابتدا تلاش کنید.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            try {
                $dupChk = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h LIMIT 1");
                $dupChk->execute([':h' => $rccHash]);
                if ($dupChk->fetch()) {
                    sendmessage($from_id, $datatextbot['dyn_wallet_hash_already_used'] ?? "❌ این هش قبلاً برای فاکتور دیگری ثبت شده است. اگر این پرداخت متعلق به شماست، با پشتیبانی تماس بگیرید.", null, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
            } catch (Throwable $e) {  }
            $rccOrderId = bin2hex(random_bytes(6));
            $rccNow = date('Y/m/d H:i:s');
            $rccCoinFmt = number_format((float) $rccCoin, 9, '.', '');
            $rccCoinFmt = rtrim(rtrim($rccCoinFmt, '0'), '.');
            $rccManualCurs = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
            if (isset($rccManualCurs[$rccCur])) {
                $rccNetwork = (string) $rccManualCurs[$rccCur]['network'];
            } else {
                $rccNetwork = in_array($rccCur, ['TRX', 'USDT_TRC20'], true) ? 'TRON' : 'TON';
            }
            try {
                $rccIns = $connect->prepare(
                    "INSERT INTO Payment_report
                (id_user, id_order, time, price, payment_Status, Payment_Method,
                 id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_tx_hash, crypto_hash_at, dec_not_confirmed)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $userIdStr = (string) $from_id;
                $statusManual = 'ManualPending';
                $methodLabel = 'manual crypto recheck';
                $invoiceMeta = '0|0';
                $irrStr = (string) $rccIrr;
                $nowUnix = time();
                $rccIns->bind_param(
                    'sssssssssssis',
                    $userIdStr, $rccOrderId, $rccNow, $irrStr, $statusManual, $methodLabel,
                    $invoiceMeta, $rccCur, $rccNetwork, $rccCoinFmt, $rccHash, $nowUnix, $rccPhotoId
                );
                $rccIns->execute();
                $rccIns->close();
            } catch (Throwable $e) {
                error_log('[recheckcrypto] insert failed: ' . $e->getMessage());
                sendmessage($from_id, $datatextbot['dyn_wallet_internal_error_retry'] ?? "❌ خطای داخلی در ثبت درخواست. لطفاً دوباره تلاش کنید.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            sendmessage(
                $from_id,
                "✅ <b>درخواست بررسی دستی شما ثبت شد.</b>\n\n"
                . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
                . "💎 ارز: <b>{$rccCur}</b>\n"
                . "🪙 مقدار: <code>{$rccCoinFmt}</code>\n"
                . "💸 معادل تومانی: " . number_format($rccIrr) . " تومان\n"
                . "🔗 هش: <code>" . substr($rccHash, 0, 16) . "…</code>\n\n"
                . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام خواهد شد.",
                null,
                'HTML'
            );

            $explorerUrl = is_callable([\Faoxima\Payments\Gateways\CryptoQuotes::class, 'cryptoExplorerUrl'])
                ? \Faoxima\Payments\Gateways\CryptoQuotes::cryptoExplorerUrl($rccCur, $rccHash)
                : '';
            $userTagLine = '@' . ($user['username'] ?? 'none');
            $rccAdminCaption = "🔁 <b>درخواست بررسی دستی پرداخت کریپتو</b>\n\n"
                . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
                . "👤 کاربر: <code>{$from_id}</code> ({$userTagLine})\n"
                . "💎 ارز: <b>{$rccCur}</b> ({$rccNetwork})\n"
                . "🪙 مقدار ادعاشده: <code>{$rccCoinFmt}</code>\n"
                . "💸 معادل تومانی ادعاشده: " . number_format($rccIrr) . " تومان\n"
                . "🔗 هش: <code>{$rccHash}</code>"
                . ($explorerUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده در مرورگر بلاکچین</a>" : '');
            if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_lookup_verified_hash')) {
                $rccExisting = \Faoxima\Payments\Crypto\ManualCrypto::crypto_lookup_verified_hash($rccHash);
                if (is_array($rccExisting)) {
                    $rccAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                        . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string)$rccExisting['order_id']) . "</code>\n"
                        . "👤 کاربر قبلی: <code>" . htmlspecialchars((string)($rccExisting['user_id'] ?? '-')) . "</code>\n"
                        . "📅 تایید در: " . htmlspecialchars((string)($rccExisting['verified_at'] ?? '-'));
                }
            }
            $rccAdminKb = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $rccOrderId],
                        ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $rccOrderId],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

            $admin_ids_local = \Faoxima\Core\Database\Db::runtimeReady() ? (\Faoxima\Core\Database\Db::select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
            if (!is_array($admin_ids_local)) $admin_ids_local = [];
            foreach ($admin_ids_local as $idAdminLocal) {
                if (!is_numeric($idAdminLocal)) continue;
                if ($rccPhotoId !== '') {
                    $rccAdminPhotoResult = telegram('sendphoto', [
                        'chat_id' => $idAdminLocal,
                        'photo'   => $rccPhotoId,
                        'caption' => $rccAdminCaption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => $rccAdminKb,
                    ]);
                    $rccAdminPhotoState = is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState']) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rccAdminPhotoResult) : (!empty($rccAdminPhotoResult['ok']) ? 'confirmed' : 'unknown');
                    if ($rccAdminPhotoState === 'failed') {
                        sendmessage((string) $idAdminLocal, $rccAdminCaption, $rccAdminKb, 'HTML');
                    }
                } else {
                    sendmessage((string) $idAdminLocal, $rccAdminCaption, $rccAdminKb, 'HTML');
                }
            }
            if (!empty($setting['Channel_Report'])) {
                $payload = [
                    'chat_id' => $setting['Channel_Report'],
                    'caption' => $rccAdminCaption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $rccAdminKb,
                ];
                if (!empty($paymentreports)) {
                    $payload['message_thread_id'] = $paymentreports;
                }
                if ($rccPhotoId !== '') {
                    $payload['photo'] = $rccPhotoId;
                    $rccChannelResult = telegram('sendphoto', $payload);
                    $rccChannelState = is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState']) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rccChannelResult) : (!empty($rccChannelResult['ok']) ? 'confirmed' : 'unknown');
                    if ($rccChannelState === 'failed') {
                        $rccChannelFallback = [
                            'chat_id' => $setting['Channel_Report'],
                            'text' => $rccAdminCaption,
                            'parse_mode' => 'HTML',
                            'reply_markup' => $rccAdminKb,
                        ];
                        if (!empty($paymentreports)) {
                            $rccChannelFallback['message_thread_id'] = $paymentreports;
                        }
                        telegram('sendmessage', $rccChannelFallback);
                    }
                } else {
                    unset($payload['caption']);
                    $payload['text'] = $rccAdminCaption;
                    telegram('sendmessage', $payload);
                }
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
