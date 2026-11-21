<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $datatextbot, $dpmm, $explorerHint, $from_id, $invoiceMsg, $keyboard, $user.
 * Inherited or exported by reference: $cur, $reason, $result.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DigitalTronPayment
{
    public static function handle(
        &$cur,
        $datatextbot,
        $dpmm,
        $explorerHint,
        $from_id,
        $invoiceMsg,
        $keyboard,
        &$reason,
        &$result,
        $user
    ) {
        $cur = $dpmm[1];
                $manualCurList = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
                $isManualCur = isset($manualCurList[$cur]);
                $amountIrt = (int) ($user['Processing_value'] ?? 0);
                if ($amountIrt <= 0) {
                    sendmessage($from_id, $datatextbot['dyn_wallet_charge_amount_undetectable'] ?? "❌ مبلغ شارژ قابل تشخیص نیست. لطفاً مجدداً از منوی شارژ کیف پول اقدام کنید.", $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $invoiceMeta = sprintf('%s|%s', $user['Processing_value_tow'] ?? '', $user['Processing_value_one'] ?? '');
                $result = \Faoxima\Payments\Crypto\ManualCrypto::crypto_create_invoice($from_id, $amountIrt, $cur, $invoiceMeta);
                if (empty($result['ok'])) {
                    $errMap = [
                        'currency-not-supported' => 'این ارز فعال نیست.',
                        'wallet-not-configured'  => 'آدرس کیف پول این ارز توسط ادمین تنظیم نشده است.',
                        'below-min'              => 'مبلغ کمتر از حداقل مجاز است.',
                        'above-max'              => 'مبلغ بیشتر از حداکثر مجاز است.',
                        'rate-unavailable'       => 'دریافت نرخ لحظه‌ای ممکن نبود؛ لطفاً دقایقی دیگر تلاش کنید.',
                        'db-write-failed'        => 'خطای داخلی در ثبت فاکتور.',
                    ];
                    $reason = $result['error'] ?? 'unknown';
                    $faMsg = $errMap[$reason] ?? $reason;
                    sendmessage($from_id, "❌ {$faMsg}", $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $displayDecimals = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_display_decimals') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_display_decimals($cur) : 2;
                $amountStr = number_format((float) $result['amount_coin'], $displayDecimals, '.', '');
                if (strpos($amountStr, '.') !== false) {
                    $amountStr = rtrim(rtrim($amountStr, '0'), '.');
                }
                $expireMin = max(1, (int) round(($result['expires_at'] - time()) / 60));
                if ($isManualCur) {
                    $explorerHint = '<i>(این شبکه به‌صورت خودکار بررسی نمی‌شود؛ هش تراکنش پس از ارسال توسط ادمین بررسی خواهد شد)</i>';
                } else {
                    $explorerHint = ($cur === 'TRX' || $cur === 'USDT_TRC20')
                        ? '<i>(لینک‌های Tronscan قابل قبول است)</i>'
                        : '<i>(لینک‌های Tonviewer / Tonscan قابل قبول است)</i>';
                }
                $walletEsc = htmlspecialchars((string) $result['wallet']);
                $walletMemo = trim((string) ($result['wallet_memo'] ?? ''));
                $isTonNetwork = in_array($cur, ['TON', 'USDT_TON'], true);

                $finalAmountIrt = (int) ($result['final_amount_irt'] ?? $amountIrt);
                $priceBlock = "💸 مبلغ قابل پرداخت: " . number_format($finalAmountIrt) . " تومان\n";

                $amountNotice = "❗️ <b>دقیقاً همین مقدار</b> را ارسال کنید. تفاوت در ارقام باعث می‌شود ربات تراکنش شما را شناسایی نکند.";

                $memoBlock = "";
                if ($isTonNetwork && $walletMemo !== '') {
                    $memoEsc = htmlspecialchars($walletMemo);
                    $memoBlock = "🏷 <b>ممو (Memo / Comment) — اجباری:</b>\n<code>{$memoEsc}</code>\n"
                               . "⚠️ <b>بدون ممو، تراکنش شما به فاکتور وصل نمی‌شود.</b>\n\n";
                }

                if ($isManualCur) {
                    $invoiceMsg = "🛠 <b>فاکتور پرداخت کریپتو (بررسی دستی)</b>\n\n"
                         . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
                         . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
                         . $priceBlock . "\n"
                         . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n"
                         . "(معادل " . number_format($finalAmountIrt) . " تومان)\n\n"
                         . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
                         . $memoBlock
                         . $amountNotice . "\n"
                         . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
                         . "⚠️ <b>این شبکه به‌صورت خودکار بررسی نمی‌شود.</b> بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید، "
                         . "هش/لینک تراکنش را ارسال کنید و سپس <b>عکس رسید تراکنش را نیز ارسال کنید</b> — بدون عکس رسید، درخواست شما برای ادمین ثبت نمی‌شود.\n{$explorerHint}";
                } else {
                    $invoiceMsg = "💎 <b>فاکتور پرداخت کریپتو</b>\n\n"
                         . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
                         . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
                         . $priceBlock . "\n"
                         . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n"
                         . "(معادل " . number_format($finalAmountIrt) . " تومان)\n\n"
                         . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
                         . $memoBlock
                         . $amountNotice . "\n"
                         . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
                         . "بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید و هش/لینک تراکنش را ارسال کنید.\n{$explorerHint}";
                }

                $styleWallet = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('copy_wallet')  : null;
                $styleAmount = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('copy_amount')  : null;
                $styleMemo   = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('copy_memo')    : null;
                $stylePaid   = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('paid_submit')  : null;
                $styleBack   = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('invoice_back') : null;

                $btnCopyWallet = ['text' => '🔗 کپی آدرس ولت', 'copy_text' => ['text' => (string) $result['wallet']], '_rxcolor' => 'crypto_order:' . $result['order_id'] . ':copy_wallet'];
                if ($styleWallet) $btnCopyWallet['style'] = $styleWallet;

                $btnCopyAmount = ['text' => '🪙 کپی مقدار', 'copy_text' => ['text' => $amountStr], '_rxcolor' => 'crypto_order:' . $result['order_id'] . ':copy_amount'];
                if ($styleAmount) $btnCopyAmount['style'] = $styleAmount;

                $btnPaid = ['text' => '✅ پرداخت کردم | ارسال هش (TXID) 🧾', 'callback_data' => 'digitaltron_submit_' . $result['order_id']];
                if ($stylePaid) $btnPaid['style'] = $stylePaid;

                $invoiceKbRows = [
                    [$btnCopyWallet, $btnCopyAmount],
                ];
                if ($isTonNetwork && $walletMemo !== '') {
                    $btnCopyMemo = ['text' => '🏷 کپی ممو', 'copy_text' => ['text' => $walletMemo], '_rxcolor' => 'crypto_order:' . $result['order_id'] . ':copy_memo'];
                    if ($styleMemo) $btnCopyMemo['style'] = $styleMemo;
                    $invoiceKbRows[] = [$btnCopyMemo];
                }
                $invoiceKbRows[] = [$btnPaid];
                $btnInvoiceBack = ['text' => '🔙 بازگشت (لغو فاکتور)', 'callback_data' => 'crypto_cancel_' . $result['order_id']];
                if ($styleBack) $btnInvoiceBack['style'] = $styleBack;
                $invoiceKbRows[] = [$btnInvoiceBack];
                $invoiceKb = json_encode(['inline_keyboard' => $invoiceKbRows], JSON_UNESCAPED_UNICODE);

                $resp = null;
                $fancyQrPath = null;
                $fancyOk = false;
                if (!(method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled())) {
                    try {
                        $rootDir = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 4);
                        $autoload = $rootDir . '/vendor/autoload.php';
                        if (is_file($autoload)) @require_once $autoload;
                        if (class_exists('\\Endroid\\QrCode\\Builder\\Builder') && method_exists(\Faoxima\Telegram\Support\QrAndKeys::class, 'addBackgroundImage') && strlen((string) $result['wallet']) <= 2048) {
                            $builder = new \Endroid\QrCode\Builder\Builder(
                                writer: new \Endroid\QrCode\Writer\PngWriter(),
                                writerOptions: [],
                                data: (string) $result['wallet'],
                                encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                                errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
                                size: 560,
                                margin: 2,
                            );
                            $built = $builder->build();
                            $fancyQrPath = $rootDir . DIRECTORY_SEPARATOR . 'cryptoqr_' . bin2hex(random_bytes(3)) . '.png';
                            @file_put_contents($fancyQrPath, $built->getString());
                            $made = @\Faoxima\Telegram\Support\QrAndKeys::addBackgroundImage($fancyQrPath, $built, 'images.jpeg');
                            if ($made && is_file($fancyQrPath) && function_exists('telegram')) {
                                $resp = @telegram('sendphoto', [
                                    'chat_id'      => (string) $from_id,
                                    'photo'        => new \CURLFile($fancyQrPath),
                                    'caption'      => $invoiceMsg,
                                    'parse_mode'   => 'HTML',
                                    'reply_markup' => $invoiceKb,
                                ]);
                                $fancyState = is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState']) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($resp) : (!empty($resp['ok']) ? 'confirmed' : 'unknown');
                                $fancyOk = $fancyState === 'confirmed' || $fancyState === 'unknown';
                            }
                        }
                    } catch (\Throwable $_) {  }
                    if ($fancyQrPath && is_file($fancyQrPath)) { @unlink($fancyQrPath); }
                }

                if (!$fancyOk) {
                    $resp = sendmessage($from_id, $invoiceMsg, $invoiceKb, 'HTML');
                }
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($resp, $result['order_id']);

        return true;
    }
}
