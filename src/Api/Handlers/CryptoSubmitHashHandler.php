<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use Throwable;

final class CryptoSubmitHashHandler extends \Faoxima\Api\Handlers\BaseHandler
{

    private const REASON_FA = [
        'invalid-hash'      => 'هش معتبری در متن ارسالی پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را ارسال کنید — نه آدرس کیف‌پول.',
        'hash-already-used' => 'این هش قبلاً برای فاکتور دیگری ثبت شده است',
        'order-not-pending' => 'فاکتور دیگر در حالت انتظار نیست، یا قبلاً پردازش شده است',
        'db-update-failed'  => 'خطا در ذخیره — لطفاً دوباره تلاش کنید',
        'no-db'             => 'دسترسی به دیتابیس برقرار نشد',
    ];

    public function handle(): void
    {
        $this->requireMethod('POST');

        if (!method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_attach_hash')) {
            \Faoxima\Api\Runtime\Response::fail(503, 'فلوی ارز آفلاین روی سرور تنظیم نشده است');
        }

        $orderId = \Faoxima\Api\Runtime\Input::string($this->data, 'order_id');
        $hashIn  = \Faoxima\Api\Runtime\Input::string($this->data, 'hash');

        if ($orderId === '') {
            \Faoxima\Api\Runtime\Response::badRequest('order_id is required');
        }
        if ($hashIn === '') {
            \Faoxima\Api\Runtime\Response::badRequest('hash is required');
        }


        $report = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT * FROM Payment_report WHERE id_order = :o AND id_user = :u AND source = \'miniapp\' LIMIT 1',
            [':o' => $orderId, ':u' => (string)$this->user['id']]
        );
        if ($report === null) {
            \Faoxima\Api\Runtime\Response::notFound('Payment not found');
        }
        if (trim((string)($report['Payment_Method'] ?? '')) !== 'arze digital offline') {
            \Faoxima\Api\Runtime\Response::fail(422, 'این فاکتور از نوع ارز آفلاین نیست');
        }
        $currentStatus = (string)($report['payment_Status'] ?? '');
        if (!in_array($currentStatus, ['Unpaid', 'AwaitingHash'], true)) {
            \Faoxima\Api\Runtime\Response::fail(409, 'این فاکتور قابل ثبت هش جدید نیست (وضعیت فعلی: ' . $currentStatus . ')');
        }

        try {
            $result = \Faoxima\Payments\Crypto\ManualCrypto::crypto_attach_hash($orderId, $hashIn);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'crypto_attach_hash threw', [
                'user' => $this->user['id'], 'order' => $orderId,
            ]);
            \Faoxima\Api\Runtime\Response::serverError('خطا در ثبت هش');
        }

        if (!is_array($result) || empty($result['ok'])) {
            $err = is_array($result) ? (string)($result['error'] ?? '') : 'unknown';
            $msg = self::REASON_FA[$err] ?? ('خطا در ثبت هش: ' . $err);
            \Faoxima\Api\Runtime\Logger::debug('Crypto hash submit failed', [
                'user' => $this->user['id'], 'order' => $orderId, 'err' => $err,
            ]);
            \Faoxima\Api\Runtime\Response::fail(422, $msg);
        }

        \Faoxima\Api\Runtime\Logger::debug('Crypto hash submitted', [
            'user'  => $this->user['id'],
            'order' => $orderId,
            'hash'  => substr((string)$result['hash'], 0, 10) . '...',
        ]);

        $currency = (string)($report['crypto_currency'] ?? '');
        $manualCurrencies = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
        $isManual = isset($manualCurrencies[$currency]);

        if ($isManual) {
            \Faoxima\Api\Runtime\Response::ok([
                'kind'            => 'hash_submitted_receipt_required',
                'message'         => '⚠️ این شبکه به‌صورت خودکار بررسی نمی‌شود. برای ثبت درخواست بررسی توسط ادمین، حالا عکس رسید تراکنش را نیز آپلود کنید (اجباری).',
                'hash'            => (string)$result['hash'],
                'receipt_required' => true,
            ]);
        }

        \Faoxima\Api\Runtime\Response::ok([
            'kind'    => 'hash_submitted',
            'message' => '✅ هش تراکنش ثبت شد. ربات شبکه را بررسی می‌کند…',
            'hash'    => (string)$result['hash'],
            'receipt_required' => false,
        ]);
    }
}
