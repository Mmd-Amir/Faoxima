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
                    if (($result['error'] ?? '') === 'invoice-pending') {
                        $orderCode = htmlspecialchars((string) $result['order_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        sendmessage($from_id, "⏳ فاکتور فعال دارید. از پیام پرداخت قبلی ادامه دهید.\nکد پیگیری <code>{$orderCode}</code>", $keyboard, 'HTML');
                        return;
                    }
                    $errMap = [
                        'invoice-busy' => '⏳ ثبت فاکتور در حال انجام است. لطفاً کمی بعد دوباره تلاش کنید.',
                        'currency-not-supported' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_crypto_create_error_currency'),
                        'wallet-not-configured'  => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_crypto_create_error_wallet'),
                        'below-min'              => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_crypto_create_error_below_min'),
                        'above-max'              => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_crypto_create_error_above_max'),
                        'rate-unavailable'       => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_crypto_create_error_rate'),
                        'db-write-failed'        => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_crypto_create_error_db'),
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
                    $explorerHint = \Faoxima\Telegram\Support\MessageTemplates::get('payment_crypto_hint_manual_network');
                } else {
                    $explorerHint = ($cur === 'TRX' || $cur === 'USDT_TRC20')
                        ? \Faoxima\Telegram\Support\MessageTemplates::get('payment_crypto_hint_tronscan')
                        : \Faoxima\Telegram\Support\MessageTemplates::get('payment_crypto_hint_tonviewer');
                }
                $walletMemo = trim((string) ($result['wallet_memo'] ?? ''));
                $isTonNetwork = in_array($cur, ['TON', 'USDT_TON'], true);

                $finalAmountIrt = (int) ($result['final_amount_irt'] ?? $amountIrt);

                $memoBlock = "";
                if ($isTonNetwork && $walletMemo !== '') {
                    $memoBlock = \Faoxima\Telegram\Support\MessageTemplates::get('payment_crypto_memo_block', ['memo' => $walletMemo]);
                }

                $invoiceMsg = \Faoxima\Telegram\Support\MessageTemplates::get($isManualCur ? 'payment_crypto_invoice_manual' : 'payment_crypto_invoice_auto', [
                    'order_id' => $result['order_id'],
                    'currency' => $cur,
                    'network' => $result['network'],
                    'amount_irt' => number_format($finalAmountIrt),
                    'amount' => $amountStr,
                    'wallet' => (string) $result['wallet'],
                    'expire_min' => $expireMin,
                ], [
                    'memo_block' => $memoBlock,
                    'explorer_hint' => $explorerHint,
                ]);

                $styleWallet = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('copy_wallet')  : null;
                $styleAmount = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('copy_amount')  : null;
                $styleMemo   = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('copy_memo')    : null;
                $stylePaid   = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('paid_submit')  : null;
                $styleBack   = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('invoice_back') : null;

                $btnCopyWallet = ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_crypto_copy_wallet'), 'copy_text' => ['text' => (string) $result['wallet']], '_rxcolor' => 'crypto_order:' . $result['order_id'] . ':copy_wallet'];
                if ($styleWallet) $btnCopyWallet['style'] = $styleWallet;

                $btnCopyAmount = ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_crypto_copy_amount'), 'copy_text' => ['text' => $amountStr], '_rxcolor' => 'crypto_order:' . $result['order_id'] . ':copy_amount'];
                if ($styleAmount) $btnCopyAmount['style'] = $styleAmount;

                $btnPaid = ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_crypto_paid_submit'), 'callback_data' => 'digitaltron_submit_' . $result['order_id']];
                if ($stylePaid) $btnPaid['style'] = $stylePaid;

                $invoiceKbRows = [
                    [$btnCopyWallet, $btnCopyAmount],
                ];
                if ($isTonNetwork && $walletMemo !== '') {
                    $btnCopyMemo = ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_crypto_copy_memo'), 'copy_text' => ['text' => $walletMemo], '_rxcolor' => 'crypto_order:' . $result['order_id'] . ':copy_memo'];
                    if ($styleMemo) $btnCopyMemo['style'] = $styleMemo;
                    $invoiceKbRows[] = [$btnCopyMemo];
                }
                $invoiceKbRows[] = [$btnPaid];
                $btnInvoiceBack = ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_crypto_invoice_back'), 'callback_data' => 'crypto_cancel_' . $result['order_id']];
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
                                $cryptoQrDelivery = null;
                                $resp = rxSendMediaWithCaption('sendphoto', [
                                    'chat_id'      => (string) $from_id,
                                    'photo'        => new \CURLFile($fancyQrPath),
                                    'caption'      => $invoiceMsg,
                                    'parse_mode'   => 'HTML',
                                    'reply_markup' => $invoiceKb,
                                ], null, $cryptoQrDelivery);
                                if (is_array($cryptoQrDelivery) && !empty($cryptoQrDelivery['caption_rich_text']) && is_array($cryptoQrDelivery['text_message_result'] ?? null) && !empty($cryptoQrDelivery['text_message_result']['ok'])) {
                                    $resp = $cryptoQrDelivery['text_message_result'];
                                }
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
