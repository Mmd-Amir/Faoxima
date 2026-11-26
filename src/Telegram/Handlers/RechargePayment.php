<?php

namespace Faoxima\Telegram\Handlers;

use Faoxima\Payments\Settlement\PendingInvoices;
use Throwable;

/**
 * Handler stage. Inherited by value: $_cc_insert_err, $_rx_payrcpt_styles, $admin_ids, $backuser, $callback_query_id, $connect, $datain, $datatextbot, $errorreport, $explorerHint, $from_id, $hashHint, $invoiceMsg, $keyboard, $setting, $telegramRoute, $textbotlang, $update, $username.
 * Inherited or exported by reference: $btn, $cur, $data, $invoice, $label, $message_id, $payment, $pdo, $randomString, $reason, $result, $rows, $sendresidcart, $stmt, $textcart, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RechargePayment
{
    public static function handle(
        $_cc_insert_err,
        $_rx_payrcpt_styles,
        $admin_ids,
        $backuser,
        &$btn,
        $callback_query_id,
        $connect,
        &$cur,
        &$data,
        $datain,
        $datatextbot,
        $errorreport,
        $explorerHint,
        $from_id,
        $hashHint,
        &$invoice,
        $invoiceMsg,
        $keyboard,
        &$label,
        &$message_id,
        &$payment,
        &$pdo,
        &$randomString,
        &$reason,
        &$result,
        &$rows,
        &$sendresidcart,
        $setting,
        &$stmt,
        $telegramRoute,
        $textbotlang,
        &$textcart,
        $update,
        &$user,
        $username
    ) {
        switch ($telegramRoute) {
        case 'recharge_payment':
            if (isset($update['message']) && empty($datain)) { return; }
            if (\Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::isNewInvoiceSelection($datain) && !\Faoxima\Payments\Gateways\Tetraminator\BotRuntime::tetraminatorBotGuard($from_id)) {
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
                }
                return;
            }
            if (!preg_match('/^sbv(resume|cancel)_\w+$/', (string) $datain) && !\Faoxima\Orders\Checkout\CheckoutRuntime::rx_ck_bot_payment_guard($user, $message_id)) { return; }
            $method = (string) $datain;
            $gatewayMethods = PendingInvoices::rx_gateway_payment_method_map();
            $lockName = null;
            if ($method === 'startelegrams' || (isset($gatewayMethods[$method]) && !in_array($method, ['rialit', 'tetraminator'], true))) {
                $lockName = 'faoxima_payment_init_' . $from_id . '_' . $method;
                try {
                    $locked = PendingInvoices::acquireCreationLock($pdo, $lockName);
                } catch (Throwable $e) {
                    error_log('[payment invoice lock] ' . $e->getMessage());
                    $locked = false;
                }
                if (!$locked) {
                    sendmessage($from_id, '⏳ فاکتور پرداخت در حال بررسی است. لطفاً کمی بعد دوباره تلاش کنید.', $keyboard, 'HTML');
                    return;
                }
            }

            try {
                if ($lockName !== null && self::stopForExistingInvoice($pdo, $method, (string) $from_id, $user, $keyboard)) {
                    return;
                }
            $__chargeBonus = is_callable([\Faoxima\Cashback\ChargeBonus::class, 'pendingChargeBonus']) ? \Faoxima\Cashback\ChargeBonus::pendingChargeBonus($user) : 0;
            if ($datain == "cart_to_offline") {
                if (\Faoxima\Telegram\Handlers\Gateways\Card::handle(_cc_insert_err: $_cc_insert_err ?? null, _rx_payrcpt_styles: $_rx_payrcpt_styles ?? null, admin_ids: $admin_ids ?? null, backuser: $backuser ?? null, callback_query_id: $callback_query_id ?? null, connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, pdo: $pdo ?? null, randomString: $randomString, sendresidcart: $sendresidcart, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, textcart: $textcart, user: $user ?? null) !== true) {
                    return;
                }
            } elseif (preg_match('/^rxpgw_cancel_(zarinpal|plisio|nowpayment|iranpay2|tonpay|blupal|atlaspay|cubepay|variza|abangateway)_(\w+)$/', (string) $datain, $rxPgwMatch)) {
                if (\Faoxima\Telegram\Handlers\Gateways\CancelGateway::handle(callback_query_id: $callback_query_id ?? null, from_id: $from_id ?? null, message_id: $message_id ?? null, rxPgwMatch: $rxPgwMatch ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "zarinpal") {
                if (\Faoxima\Telegram\Handlers\Gateways\Zarinpal::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "plisio") {
                if (\Faoxima\Telegram\Handlers\Gateways\Plisio::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "nowpayment") {
                if (\Faoxima\Telegram\Handlers\Gateways\NowPayments::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "iranpay2") {
                if (\Faoxima\Telegram\Handlers\Gateways\Tronado::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, pdo: $pdo ?? null, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "tonpay") {
                if (\Faoxima\Telegram\Handlers\Gateways\Tonpay::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, pdo: $pdo ?? null, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "rialit") {
                if (\Faoxima\Telegram\Handlers\Gateways\Rialit::handle(__chargeBonus: $__chargeBonus ?? null, connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, from_id: $from_id ?? null, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, pdo: $pdo ?? null, randomString: $randomString, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "tetraminator") {
                if (\Faoxima\Telegram\Handlers\Gateways\Tetraminator::handle(__chargeBonus: $__chargeBonus ?? null, connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, from_id: $from_id ?? null, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, pdo: $pdo ?? null, randomString: $randomString, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "blupal") {
                if (\Faoxima\Telegram\Handlers\Gateways\Blupal::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "atlaspay") {
                if (\Faoxima\Telegram\Handlers\Gateways\Atlaspay::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, pdo: $pdo ?? null, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "cubepay") {
                if (\Faoxima\Telegram\Handlers\Gateways\Cubepay::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "variza") {
                if (\Faoxima\Telegram\Handlers\Gateways\Variza::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "abangateway") {
                if (\Faoxima\Telegram\Handlers\Gateways\Abangateway::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, payment: $payment, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "digitaltron") {
                if (\Faoxima\Telegram\Handlers\Gateways\DigitalTronMenu::handle(btn: $btn, cur: $cur, datatextbot: $datatextbot ?? null, from_id: $from_id ?? null, hashHint: $hashHint ?? null, keyboard: $keyboard ?? null, label: $label, rows: $rows, user: $user ?? null) !== true) {
                    return;
                }
            } elseif (preg_match('/^digitaltron_(?:pay|paymode_(?:ext|ir))_([A-Za-z0-9_]{2,20})$/', (string) $datain, $dpmm)
                && in_array($dpmm[1], array_column(method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets() : [], 'currency'), true)
            ) {
                if (\Faoxima\Telegram\Handlers\Gateways\DigitalTronPayment::handle(cur: $cur, datatextbot: $datatextbot ?? null, dpmm: $dpmm ?? null, explorerHint: $explorerHint ?? null, from_id: $from_id ?? null, invoiceMsg: $invoiceMsg ?? null, keyboard: $keyboard ?? null, reason: $reason, result: $result, user: $user ?? null) !== true) {
                    return;
                }
            } elseif (preg_match('/^crypto_cancel_([A-Za-z0-9_\-]+)$/', (string) $datain, $dcm)) {
                if (\Faoxima\Telegram\Handlers\Gateways\CancelCrypto::handle(dcm: $dcm ?? null, from_id: $from_id ?? null, keyboard: $keyboard ?? null, message_id: $message_id ?? null, pdo: $pdo, stmt: $stmt) !== true) {
                    return;
                }
            } elseif (preg_match('/^digitaltron_submit_([A-Za-z0-9_\-]+)$/', (string) $datain, $dsm)) {
                if (\Faoxima\Telegram\Handlers\Gateways\SubmitCrypto::handle(connect: $connect ?? null, datatextbot: $datatextbot ?? null, dsm: $dsm ?? null, from_id: $from_id ?? null, keyboard: $keyboard ?? null) !== true) {
                    return;
                }
            } elseif ($datain == "startelegrams") {
                if (\Faoxima\Telegram\Handlers\Gateways\TelegramStars::handle(connect: $connect ?? null, data: $data, datatextbot: $datatextbot ?? null, errorreport: $errorreport ?? null, from_id: $from_id ?? null, invoice: $invoice, keyboard: $keyboard ?? null, message_id: $message_id, pdo: $pdo ?? null, randomString: $randomString, setting: $setting ?? null, stmt: $stmt, textbotlang: $textbotlang ?? null, user: $user ?? null, username: $username ?? null) !== true) {
                    return;
                }
            }

            if (isset($randomString) && $randomString !== '') {
                if ($__chargeBonus > 0) {
                    \Faoxima\Core\Database\Db::update("Payment_report", "charge_bonus", $__chargeBonus, "id_order", $randomString);
                }
                $__pv4 = (string)($user['Processing_value_four'] ?? '');
                if (strpos($__pv4, 'chg|') === 0) {
                    $__pv4Parts = explode('|', $__pv4);
                    $__pendingDiscountCode = isset($__pv4Parts[2]) ? trim((string)$__pv4Parts[2]) : '';
                    $__pendingPriceBefore  = isset($__pv4Parts[3]) ? (float)$__pv4Parts[3] : 0.0;
                    if ($__pendingDiscountCode !== '') {
                        \Faoxima\Core\Database\Db::update("Payment_report", "discount_code", $__pendingDiscountCode, "id_order", $randomString);
                        \Faoxima\Core\Database\Db::update("Payment_report", "discount_amount", (string)$__chargeBonus, "id_order", $randomString);
                        \Faoxima\Core\Database\Db::update("Payment_report", "price_before_discount", (string)$__pendingPriceBefore, "id_order", $randomString);
                    }
                }
            }
            } finally {
                if ($lockName !== null) {
                    PendingInvoices::releaseCreationLock($pdo, $lockName);
                }
            }
            break;
        }

        return true;
    }

    private static function stopForExistingInvoice(\PDO $pdo, string $method, string $userId, array $user, $keyboard): bool
    {
        try {
            if ($method === 'iranpay2') {
                // The Mini App and bot store different method labels for this gateway.
                $pendingOrder = PendingInvoices::findRecentInvoice($pdo, $userId, $method, true, PendingInvoices::rx_gateway_stale_minutes($method));
            } elseif ($method === 'startelegrams') {
                $invoiceKey = ($user['Processing_value_tow'] ?? '') . '|' . ($user['Processing_value_one'] ?? '');
                $pendingOrder = PendingInvoices::findRecentInvoice($pdo, $userId, 'Star Telegram', false, 30, $invoiceKey, (int) ($user['Processing_value'] ?? 0));
            } else {
                return false;
            }
        } catch (Throwable $e) {
            error_log('[payment invoice lookup] ' . $e->getMessage());
            sendmessage($userId, '⏳ بررسی فاکتور پرداخت موقتاً ممکن نیست. لطفاً کمی بعد دوباره تلاش کنید.', $keyboard, 'HTML');
            return true;
        }
        if ($pendingOrder === null) {
            return false;
        }

        $orderCode = htmlspecialchars($pendingOrder, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        sendmessage($userId, "⏳ برای ادامه، فاکتور قبلی را در ربات یا مینی‌اپ باز کنید.\nکد پیگیری <code>{$orderCode}</code>", $keyboard, 'HTML');
        return true;
    }
}
