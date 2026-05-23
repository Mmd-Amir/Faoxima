<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CryptoSubmitHashHandler extends BaseHandler
{

    private const REASON_FA = [
        'invalid-hash'      => 'فرمت هش نامعتبر است. هش تراکنش باید 64 کاراکتر هگز باشد یا یک لینک معتبر Tronscan/Tonviewer',
        'hash-already-used' => 'این هش قبلاً برای فاکتور دیگری ثبت شده است',
        'order-not-pending' => 'فاکتور دیگر در حالت انتظار نیست، یا قبلاً پردازش شده است',
        'db-update-failed'  => 'خطا در ذخیره — لطفاً دوباره تلاش کنید',
        'no-db'             => 'دسترسی به دیتابیس برقرار نشد',
    ];

    public function handle(): void
    {
        $this->requireMethod('POST');

        if (!function_exists('crypto_attach_hash')) {
            FaoximaResponse::fail(503, 'فلوی ارز آفلاین روی سرور تنظیم نشده است');
        }

        $orderId = FaoximaInput::string($this->data, 'order_id');
        $hashIn  = FaoximaInput::string($this->data, 'hash');

        if ($orderId === '') {
            FaoximaResponse::badRequest('order_id is required');
        }
        if ($hashIn === '') {
            FaoximaResponse::badRequest('hash is required');
        }


        $report = FaoximaDb::fetchOne(
            'SELECT * FROM Payment_report WHERE id_order = :o AND id_user = :u AND source = \'miniapp\' LIMIT 1',
            [':o' => $orderId, ':u' => (string)$this->user['id']]
        );
        if ($report === null) {
            FaoximaResponse::notFound('Payment not found');
        }
        if (trim((string)($report['Payment_Method'] ?? '')) !== 'arze digital offline') {
            FaoximaResponse::fail(422, 'این فاکتور از نوع ارز آفلاین نیست');
        }
        $currentStatus = (string)($report['payment_Status'] ?? '');
        if (!in_array($currentStatus, ['Unpaid', 'AwaitingHash'], true)) {
            FaoximaResponse::fail(409, 'این فاکتور قابل ثبت هش جدید نیست (وضعیت فعلی: ' . $currentStatus . ')');
        }

        try {
            $result = crypto_attach_hash($orderId, $hashIn);
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'crypto_attach_hash threw', [
                'user' => $this->user['id'], 'order' => $orderId,
            ]);
            FaoximaResponse::serverError('خطا در ثبت هش');
        }

        if (!is_array($result) || empty($result['ok'])) {
            $err = is_array($result) ? (string)($result['error'] ?? '') : 'unknown';
            $msg = self::REASON_FA[$err] ?? ('خطا در ثبت هش: ' . $err);
            FaoximaLogger::debug('Crypto hash submit failed', [
                'user' => $this->user['id'], 'order' => $orderId, 'err' => $err,
            ]);
            FaoximaResponse::fail(422, $msg);
        }

        FaoximaLogger::debug('Crypto hash submitted', [
            'user'  => $this->user['id'],
            'order' => $orderId,
            'hash'  => substr((string)$result['hash'], 0, 10) . '...',
        ]);

        FaoximaResponse::ok([
            'kind'    => 'hash_submitted',
            'message' => '✅ هش تراکنش ثبت شد. ربات شبکه را بررسی می‌کند…',
            'hash'    => (string)$result['hash'],
        ]);
    }
}
