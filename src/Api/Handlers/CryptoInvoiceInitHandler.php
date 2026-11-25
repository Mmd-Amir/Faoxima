<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use Throwable;

final class CryptoInvoiceInitHandler extends \Faoxima\Api\Handlers\BaseHandler
{

    private const REASON_FA = [
        'currency-not-supported' => 'این ارز پشتیبانی نمی‌شود',
        'wallet-not-configured'  => 'کیف‌پول این ارز روی سرور تنظیم نشده',
        'rate-unavailable'       => 'نرخ ارز در دسترس نیست، لطفاً چند دقیقه بعد تلاش کنید',
        'below-min'              => 'مبلغ کمتر از حداقل مجاز است',
        'above-max'              => 'مبلغ بیشتر از حداکثر مجاز است',
        'db-write-failed'        => 'خطا در ذخیره فاکتور — لطفاً دوباره تلاش کنید',
    ];

    public function handle(): void
    {
        $this->requireMethod('POST');

        if (!method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_create_invoice') || !method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_supported_currencies')) {
            \Faoxima\Api\Runtime\Response::fail(503, 'فلوی ارز آفلاین روی سرور تنظیم نشده است');
        }

        $this->enforceSingleActiveInvoice();

        $amount       = \Faoxima\Api\Runtime\Input::int($this->data, 'amount', 0);
        $currency     = strtoupper(\Faoxima\Api\Runtime\Input::string($this->data, 'currency_code'));
        $purchaseUser = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'purchase_username');
        $pendingKind  = \Faoxima\Api\Runtime\Input::string($this->data, 'pending_kind');
        $pendingOne   = \Faoxima\Api\Runtime\Input::string($this->data, 'pending_one');

        $serverPendingActionMap = [
            'renew'         => 'getextenduser',
            'extra_time'    => 'getextratimeuser',
            'extra_volume'  => 'getextravolumeuser',
        ];
        $checkoutId = \Faoxima\Api\Runtime\Input::string($this->data, 'checkout_id');
        $ck = null;
        $ckMethodKey = '';
        if ($checkoutId !== '' || ($purchaseUser !== null && $purchaseUser !== '') || ($pendingKind !== '' && isset($serverPendingActionMap[$pendingKind]))) {
            if ($checkoutId === '') {
                if ($purchaseUser !== null && $purchaseUser !== '') {
                    $ckMethodKey = 'getconfigafterpay|' . $purchaseUser;
                } else {
                    $ckMethodKey = $serverPendingActionMap[$pendingKind] . '|' . (string) ($this->user['Processing_value_one'] ?? '');
                }
            }
            $ck = $this->serverCheckoutForPayment($checkoutId, $ckMethodKey);
            $amount = (int) $ck['external_amount'];
            [$ckTow, $ckOne] = \Faoxima\Orders\Checkout\Checkout::keyParts((string) $ck['op_key']);
            if ($ckTow === 'getconfigafterpay') {
                $purchaseUser = (string) $ck['target'];
                $pendingKind = '';
            } else {
                $purchaseUser = null;
                $pendingKind = array_search($ckTow, $serverPendingActionMap, true) ?: '';
                $pendingOne = $ckOne;
            }
        }

        if ($amount <= 0) {
            \Faoxima\Api\Runtime\Response::badRequest('amount must be > 0');
        }
        if ($currency === '') {
            \Faoxima\Api\Runtime\Response::badRequest('currency_code is required');
        }
        $supported = \Faoxima\Payments\Crypto\ManualCrypto::crypto_supported_currencies();
        if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies')) {
            $supported += \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies();
        }
        if (!isset($supported[$currency])) {
            \Faoxima\Api\Runtime\Response::fail(422, '❌ ارز انتخابی پشتیبانی نمی‌شود');
        }


        if ($ck !== null) {
            $ck = $this->validateCheckoutForPayment($ck, 'crypto_' . strtolower($currency));
        }

        \Faoxima\Core\Database\Db::update('user', 'Processing_value', $amount, 'id', $this->user['id']);
        $this->user['Processing_value'] = $amount;

        $pendingActionMap = [
            'renew'         => 'getextenduser',
            'extra_time'    => 'getextratimeuser',
            'extra_volume'  => 'getextravolumeuser',
        ];

        $invoiceMeta = '';
        if ($purchaseUser !== null && $purchaseUser !== '') {
            $unpaidExists = (int) \Faoxima\Api\Runtime\Db::fetchScalar(
                "SELECT COUNT(*) FROM invoice
                  WHERE username = :u AND id_user = :uid AND Status = 'unpaid'",
                [':u' => $purchaseUser, ':uid' => $this->user['id']]
            );
            if ($unpaidExists === 0) {
                \Faoxima\Api\Runtime\Response::fail(404, '❌ فاکتور خرید ناتمامی برای این نام کاربری پیدا نشد.');
            }
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', $purchaseUser, 'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', 'getconfigafterpay', 'id', $this->user['id']);
            $this->user['Processing_value_one'] = $purchaseUser;
            $this->user['Processing_value_tow'] = 'getconfigafterpay';
            $invoiceMeta = 'getconfigafterpay|' . $purchaseUser;
        } elseif ($pendingKind !== '' && isset($pendingActionMap[$pendingKind]) && $pendingOne !== '') {
            $action = $pendingActionMap[$pendingKind];
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', $pendingOne, 'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', $action, 'id', $this->user['id']);
            $this->user['Processing_value_one'] = $pendingOne;
            $this->user['Processing_value_tow'] = $action;
            $invoiceMeta = $action . '|' . $pendingOne;
        } else {
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', '', 'id', $this->user['id']);
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', '', 'id', $this->user['id']);
            $this->user['Processing_value_one'] = '';
            $this->user['Processing_value_tow'] = '';
        }

        try {
            $result = \Faoxima\Payments\Crypto\ManualCrypto::crypto_create_invoice(
                (string)$this->user['id'],
                $amount,
                $currency,
                $invoiceMeta,
                false,
                'miniapp'
            );
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'crypto_create_invoice threw', [
                'user' => $this->user['id'], 'currency' => $currency, 'amount' => $amount,
            ]);
            \Faoxima\Api\Runtime\Response::serverError('خطا در ساخت فاکتور');
        }

        if (!is_array($result) || empty($result['ok'])) {
            $err = is_array($result) ? (string)($result['error'] ?? '') : 'unknown';
            $msg = self::REASON_FA[$err] ?? ('خطا در ساخت فاکتور: ' . $err);
            if ($err === 'below-min' && isset($result['min'])) {
                $msg = '❌ حداقل مبلغ برای این روش ' . number_format((int)$result['min']) . ' تومان است';
                if ($ck !== null && (int) $ck['wallet_amount'] > 0) {
                    $msg = '❌ مبلغ باقی‌مانده برای پرداخت خارجی (' . number_format($amount) . ' تومان) کمتر از حداقل این روش (' . number_format((int)$result['min']) . ' تومان) است. لطفاً روش دیگری انتخاب کنید یا بدون استفاده از کیف پول پرداخت کنید.';
                }
            } elseif ($err === 'above-max' && isset($result['max'])) {
                $msg = '❌ حداکثر مبلغ برای این روش ' . number_format((int)$result['max']) . ' تومان است';
            }
            \Faoxima\Api\Runtime\Response::fail(422, $msg);
        }

        if ($ck !== null) {
            \Faoxima\Orders\Checkout\Checkout::attachPayment(\Faoxima\Api\Runtime\Db::pdo(), (string) $ck['checkout_id'], (string) $result['order_id'], 'crypto_' . strtolower($currency));
        }

        $coinDecimals = (int)($supported[$currency]['decimals'] ?? 6);
        $displayDecimals = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_display_decimals') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_display_decimals($currency) : 2;
        $formattedAmount = number_format((float)$result['amount_coin'], $displayDecimals, '.', '');

        if (strpos($formattedAmount, '.') !== false) {
            $formattedAmount = rtrim(rtrim($formattedAmount, '0'), '.');
        }
        \Faoxima\Api\Runtime\Response::ok([
            'kind'               => 'crypto_invoice',
            'order_id'           => (string)$result['order_id'],
            'currency'           => $currency,
            'network'            => (string)($result['network'] ?? ''),
            'wallet_to'          => (string)$result['wallet'],
            'wallet_memo'        => (string)($result['wallet_memo'] ?? ''),
            'crypto_amount'      => $formattedAmount,
            'amount_toman'       => $amount,
            'amount_toman_final' => (int)($result['final_amount_irt'] ?? $amount),
            'rate'               => (float)($result['rate'] ?? 0),
            'expires_at'         => (int)($result['expires_at'] ?? 0),
        ]);
    }
}
