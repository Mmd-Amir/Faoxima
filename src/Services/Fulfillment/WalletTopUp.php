<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class WalletTopUp
{
    public static function topUp(array $context)
    {
        $order_id = $context['payment']['order_id'];
        $Payment_report = $context['payment']['report'];
        $Balance_id = $context['payment']['customer'];
        $rxToken = $context['fulfillment']['token'];
        $pdo = $context['provider']['database'];
        $setting = $context['presentation']['settings'];
        $paymentNote = $context['presentation']['payment_note'];
        $receipt = $context['presentation']['receipt'];

        $__chargeBonus = isset($Payment_report['charge_bonus']) ? intval($Payment_report['charge_bonus']) : 0;
        $__paidAmount = intval($Payment_report['price']);
        $__creditAmount = $__paidAmount + $__chargeBonus;
        $Balance_confrim = intval($Balance_id['Balance']) + $__creditAmount;
        $__pm = (string) ($Payment_report['Payment_Method'] ?? '');
        if (stripos($__pm, 'crypto') !== false || stripos($__pm, 'arze digital') !== false || stripos($__pm, 'plisio') !== false || stripos($__pm, 'nowpayment') !== false || stripos($__pm, 'digitaltron') !== false) {
            $__wlCategory = 'topup_crypto';
        } elseif (stripos($__pm, 'cart to cart') !== false || stripos($__pm, 'carttocart') !== false) {
            $__wlCategory = 'topup_card';
        } else {
            $__wlCategory = 'topup_gateway';
        }
        $rxCredit = \Faoxima\Wallet\Ledger\FulfillmentCredit::walletCredit($pdo, $order_id, $rxToken, $Payment_report['id_user'], $__creditAmount, $__wlCategory, $__pm);
        if ($rxCredit === 'already_completed') {
            return \Faoxima\Payments\Fulfillment\Claims::result('already_completed', 'wallet_already_credited');
        }
        if ($rxCredit !== 'credited') {
            if (function_exists('rx_log_event')) {
                rx_log_event('WALLET_CREDIT_FAILED', 'DirectPayment wallet credit failed; nothing was credited', [
                    'id_order' => $Payment_report['id_order'],
                    'id_user' => $Payment_report['id_user'],
                    'amount' => $__creditAmount,
                    'outcome' => $rxCredit,
                ]);
            }
            if ($rxCredit === 'not_owner' || $rxCredit === 'invalid_state') {
                return \Faoxima\Payments\Fulfillment\Claims::result('already_processing', 'wallet_' . $rxCredit);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'wallet_credit_failed', true);
        }
        \Faoxima\Core\Database\Db::update("Payment_report", "at_updated", date('Y/m/d H:i:s'), "id_order", $Payment_report['id_order']);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $Payment_report['id_user']);

        $__chargeDiscountCode = trim((string)($Payment_report['discount_code'] ?? ''));
        $__chargeDiscountAlreadyConsumed = (string)($Payment_report['discount_consumed'] ?? '') === '1';
        if ($__chargeDiscountCode !== '' && !$__chargeDiscountAlreadyConsumed && class_exists('Faoxima\Orders\Discounts\DiscountRules')) {
            \Faoxima\Core\Database\Db::update("Payment_report", "discount_consumed", "1", "id_order", $Payment_report['id_order']);
            \Faoxima\Orders\Discounts\DiscountRules::markSellUsed($pdo, $__chargeDiscountCode, $Balance_id);
            \Faoxima\Orders\Discounts\DiscountRules::logOrderDiscount($pdo, [
                'id_user' => $Payment_report['id_user'],
                'code' => $__chargeDiscountCode,
                'kind' => 'sell',
                'value_type' => null,
                'value_raw' => null,
                'price_before' => (float)($Payment_report['price_before_discount'] ?? 0),
                'discount_amount' => (float)($Payment_report['discount_amount'] ?? 0),
                'price_after' => $__paidAmount,
                'section' => 'charge',
            ]);
        }
        $format_price_cart = number_format($__paidAmount, 0);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $rxFmtBalanceBeforeTopup = \Faoxima\Core\Support\Formatting::rxFormatToman($Balance_id['Balance']);
            $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
💎 موجودی قبل ازافزایش موجودی : {$rxFmtBalanceBeforeTopup}
✍️ توضیحات : {$paymentNote}";
            ReceiptMessage::updateReceipt($receipt, $textconfrom);
        }
        $__creditFmt = number_format($__creditAmount, 0);
        sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$__creditFmt} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.

🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
            \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('wallet_deposit', [
                'user_id' => $Payment_report['id_user'],
                'amount'  => $__creditFmt,
                'price'   => $__creditFmt,
            ], $setting);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result('completed');
    }
}
