<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class PaymentResetHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId = FaoximaInput::string($_POST, 'order_id');
        if ($orderId === '') {
            FaoximaResponse::badRequest('order_id is required');
        }

        $payment = FaoximaDb::fetchOne(
            'SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND source = \'miniapp\'
              LIMIT 1',
            [':o' => $orderId, ':u' => $this->user['id']]
        );
        if ($payment === null) {
            FaoximaResponse::notFound('Payment record not found');
        }

        $currentStatus = strtolower((string)($payment['payment_Status'] ?? ''));
        $method = strtolower(trim((string)($payment['Payment_Method'] ?? '')));
        $receiptMarker = trim((string)($payment['dec_not_confirmed'] ?? ''));

        if (!in_array($method, ['cart to cart', 'carttocart_pv'], true)) {
            FaoximaResponse::fail(409, '❌ این روش پرداخت از بازنشانی خودکار پشتیبانی نمی‌کند.');
        }
        if ($currentStatus === 'paid') {
            FaoximaResponse::fail(409, '❌ این پرداخت قبلاً تأیید شده و نمی‌توان آن را بازنشانی کرد.');
        }

        if ($currentStatus === 'reject') {
            FaoximaResponse::fail(409, '❌ این پرداخت قبلاً رد شده و نمی‌توان آن را بازنشانی کرد.');
        }

        if ($currentStatus === 'expire') {
            FaoximaResponse::fail(409, '❌ این پرداخت منقضی شده و نمی‌توان آن را بازنشانی کرد.');
        }
        if ($currentStatus === 'cancelled') {
            FaoximaResponse::ok([
                'order_id' => $orderId,
                'message'  => '✅ درخواست پرداخت قبلی لغو شد. اکنون می‌توانید دوباره پرداخت جدید ثبت کنید.',
            ]);
        }
        $allowedStatuses = ['pending', 'unpaid'];
        if (!in_array($currentStatus, $allowedStatuses, true)) {
            FaoximaResponse::fail(409, '❌ وضعیت پرداخت فعلی قابل بازنشانی نیست: ' . $currentStatus);
        }
        if ($receiptMarker !== '') {
            FaoximaResponse::fail(409, '❌ رسید این پرداخت قبلاً برای ادمین ارسال شده و قابل بازنشانی نیست.');
        }

        $result = rxCancelAbandonedCardPayment($orderId, (string)$this->user['id'], 'user_reset', ['source' => 'miniapp']);
        if (empty($result['ok'])) {
            $reason = (string)($result['reason'] ?? '');
            FaoximaLogger::warn('Payment_report cancel failed', ['order' => $orderId, 'user_id' => $this->user['id'], 'reason' => $reason]);
            if ($reason === 'db_error' || $reason === 'pdo_unavailable') {
                FaoximaResponse::serverError('❌ خطا در بازنشانی وضعیت پرداخت');
            }
            FaoximaResponse::fail(409, '❌ رسید این پرداخت قبلاً برای ادمین ارسال شده و قابل بازنشانی نیست.');
        }

        FaoximaLogger::debug('Payment status reset', [
            'order'      => $orderId,
            'user_id'    => $this->user['id'],
            'old_status' => $currentStatus,
            'new_status' => 'cancelled',
            'invoice_invalidated' => !empty($result['invoice_invalidated']),
        ]);

        FaoximaResponse::ok([
            'order_id' => $orderId,
            'message'  => '✅ درخواست پرداخت قبلی لغو شد. اکنون می‌توانید دوباره پرداخت جدید ثبت کنید.',
        ]);
    }
}
