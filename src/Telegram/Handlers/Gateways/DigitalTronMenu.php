<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $datatextbot, $from_id, $hashHint, $keyboard, $user.
 * Inherited or exported by reference: $btn, $cur, $label, $rows.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DigitalTronMenu
{
    public static function handle(
        &$btn,
        &$cur,
        $datatextbot,
        $from_id,
        $hashHint,
        $keyboard,
        &$label,
        &$rows,
        $user
    ) {
        $mainbalancedigitaltron = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalancedigitaltron", "select")['ValuePay'];
                $maxbalancedigitaltron = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalancedigitaltron", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalancedigitaltron || $user['Processing_value'] > $maxbalancedigitaltron) {
                    $minF = number_format($mainbalancedigitaltron);
                    $maxF = number_format($maxbalancedigitaltron);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $minF, $maxF), null, 'HTML');
                    return;
                }

                if (!method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets')) {
                    sendmessage($from_id, $datatextbot['dyn_wallet_hashchecker_not_loaded'] ?? "❌ ماژول هش‌چکر بارگذاری نشده است. لطفاً مدتی دیگر تلاش کنید.", $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $available = \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets();
                if (empty($available)) {
                    sendmessage($from_id, $datatextbot['dyn_wallet_no_wallet_address_configured'] ?? "❌ آدرس کیف پولی برای هیچ شبکه‌ای توسط ادمین ثبت نشده است. لطفاً با پشتیبانی در تماس باشید.", $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $faShort = [
                    'TRX' => '🟥 ترون (TRX)',
                    'TON' => '🟦 تون (TON)',
                    'USDT_TRC20' => '🟢 تتر روی ترون',
                    'USDT_TON'   => '🟢 تتر روی تون',
                ];
                $manualCurListPicker = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
                $rows = [];
                $hasAutoInList = false;
                $hasManualInList = false;
                foreach ($available as $w) {
                    $cur = (string) $w['currency'];
                    $isManualPick = isset($manualCurListPicker[$cur]);
                    if ($isManualPick) { $hasManualInList = true; } else { $hasAutoInList = true; }
                    $label = $faShort[$cur] ?? $cur;
                    if ($isManualPick) { $label .= ' 🛠'; }
                    $btn = ['text' => $label, 'callback_data' => 'digitaltron_pay_' . $cur];
                    $styleCurrency = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_invoice_button_style') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_invoice_button_style('currency_pick_' . $cur) : null;
                    if ($styleCurrency) $btn['style'] = $styleCurrency;
                    $rows[] = [$btn];
                }
                $rows[] = [['text' => '❌ بستن', 'callback_data' => 'colselist']];
                $picker = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
                if ($hasAutoInList && $hasManualInList) {
                    $hashHint = "ℹ️ برای شبکه‌های <b>خودکار</b> (بدون 🛠)، فقط هش (Hash / TxID) تراکنش کافی است — نیازی به ارسال عکس نیست و ربات ظرف چند دقیقه به‌صورت خودکار تایید می‌کند.\n"
                              . "ℹ️ برای شبکه‌های <b>دستی</b> (🛠)، بعد از هش باید <b>عکس رسید تراکنش</b> نیز ارسال شود و تایید نهایی توسط ادمین انجام می‌شود.";
                } elseif ($hasManualInList) {
                    $hashHint = "ℹ️ این شبکه‌ها به‌صورت خودکار بررسی نمی‌شوند. بعد از پرداخت، هش (Hash / TxID) و سپس <b>عکس رسید تراکنش</b> را ارسال می‌کنید و تایید نهایی توسط ادمین انجام می‌شود.";
                } else {
                    $hashHint = "ℹ️ پس از پرداخت، فقط هش (Hash / TxID) تراکنش را برای ربات می‌فرستید — نیازی به ارسال عکس نیست. "
                              . "هش هم به‌صورت خام و هم به‌صورت لینک (مثلاً <code>tonviewer.com/transaction/...</code> یا <code>tronscan.org/#/transaction/...</code>) قابل ارسال است.";
                }
                $msg = "💎 <b>پرداخت با ارز دیجیتال</b>\n\n"
                     . "شبکه‌ای که می‌خواهید با آن پرداخت کنید را انتخاب کنید. بعد از انتخاب، ربات یک آدرس کیف پول و یک <b>مبلغ دقیق</b> به شما اعلام می‌کند.\n\n"
                     . $hashHint;
                sendmessage($from_id, $msg, $picker, 'HTML');

        return true;
    }
}
