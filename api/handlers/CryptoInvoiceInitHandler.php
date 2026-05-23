<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CryptoInvoiceInitHandler extends BaseHandler
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

        if (!function_exists('crypto_create_invoice') || !function_exists('crypto_supported_currencies')) {
            FaoximaResponse::fail(503, 'فلوی ارز آفلاین روی سرور تنظیم نشده است');
        }

        $amount       = FaoximaInput::int($this->data, 'amount', 0);
        $currency     = strtoupper(FaoximaInput::string($this->data, 'currency_code'));
        $iranianMode  = (bool) FaoximaInput::int($this->data, 'iranian_mode', 0);
        $purchaseUser = FaoximaInput::nullableString($this->data, 'purchase_username');

        if ($amount <= 0) {
            FaoximaResponse::badRequest('amount must be > 0');
        }
        if ($currency === '') {
            FaoximaResponse::badRequest('currency_code is required');
        }
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) {
            FaoximaResponse::fail(422, '❌ ارز انتخابی پشتیبانی نمی‌شود');
        }


        update('user', 'Processing_value', $amount, 'id', $this->user['id']);
        $this->user['Processing_value'] = $amount;

        $invoiceMeta = '';
        if ($purchaseUser !== null && $purchaseUser !== '') {
            $unpaidExists = (int) FaoximaDb::fetchScalar(
                "SELECT COUNT(*) FROM invoice
                  WHERE username = :u AND id_user = :uid AND Status = 'unpaid'",
                [':u' => $purchaseUser, ':uid' => $this->user['id']]
            );
            if ($unpaidExists === 0) {
                FaoximaResponse::fail(404, '❌ فاکتور خرید ناتمامی برای این نام کاربری پیدا نشد.');
            }
            update('user', 'Processing_value_one', $purchaseUser, 'id', $this->user['id']);
            update('user', 'Processing_value_tow', 'getconfigafterpay', 'id', $this->user['id']);
            $this->user['Processing_value_one'] = $purchaseUser;
            $this->user['Processing_value_tow'] = 'getconfigafterpay';
            $invoiceMeta = 'getconfigafterpay|' . $purchaseUser;
        } else {
            update('user', 'Processing_value_one', '', 'id', $this->user['id']);
            update('user', 'Processing_value_tow', '', 'id', $this->user['id']);
            $this->user['Processing_value_one'] = '';
            $this->user['Processing_value_tow'] = '';
        }

        try {
            $result = crypto_create_invoice(
                (string)$this->user['id'],
                $amount,
                $currency,
                $invoiceMeta,
                $iranianMode,
                'miniapp'
            );
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'crypto_create_invoice threw', [
                'user' => $this->user['id'], 'currency' => $currency, 'amount' => $amount,
            ]);
            FaoximaResponse::serverError('خطا در ساخت فاکتور');
        }

        if (!is_array($result) || empty($result['ok'])) {
            $err = is_array($result) ? (string)($result['error'] ?? '') : 'unknown';
            $msg = self::REASON_FA[$err] ?? ('خطا در ساخت فاکتور: ' . $err);
            if ($err === 'below-min' && isset($result['min'])) {
                $msg = '❌ حداقل مبلغ برای این روش ' . number_format((int)$result['min']) . ' تومان است';
            } elseif ($err === 'above-max' && isset($result['max'])) {
                $msg = '❌ حداکثر مبلغ برای این روش ' . number_format((int)$result['max']) . ' تومان است';
            }
            FaoximaResponse::fail(422, $msg);
        }

        $coinDecimals = (int)($supported[$currency]['decimals'] ?? 6);
        $displayDecimals = function_exists('crypto_display_decimals') ? crypto_display_decimals($currency) : 2;
        $formattedAmount = number_format((float)$result['amount_coin'], $displayDecimals, '.', '');

        if (strpos($formattedAmount, '.') !== false) {
            $formattedAmount = rtrim(rtrim($formattedAmount, '0'), '.');
        }
        FaoximaResponse::ok([
            'kind'         => 'crypto_invoice',
            'order_id'     => (string)$result['order_id'],
            'currency'     => $currency,
            'network'      => (string)($result['network'] ?? ''),
            'wallet_to'    => (string)$result['wallet'],
            'wallet_memo'  => (string)($result['wallet_memo'] ?? ''),
            'crypto_amount'=> $formattedAmount,
            'amount_toman' => $amount,
            'rate'         => (float)($result['rate'] ?? 0),
            'expires_at'   => (int)($result['expires_at'] ?? 0),
            'iranian_mode' => (bool)($result['iranian_mode'] ?? $iranianMode),
        ]);
    }
}
