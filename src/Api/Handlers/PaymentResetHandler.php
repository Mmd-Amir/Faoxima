<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class PaymentResetHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId = \Faoxima\Api\Runtime\Input::string($_POST, 'order_id');
        if ($orderId === '') {
            \Faoxima\Api\Runtime\Response::badRequest('order_id is required');
        }

        $isVerify = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_is_verify_order') && \Faoxima\Payments\Receipts\ReceiptOrders::isVerifyOrder(\Faoxima\Api\Runtime\Db::pdo(), $orderId);
        $payment = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND ' . ($isVerify ? '1 = 1' : 'source = \'miniapp\'') . '
              LIMIT 1',
            [':o' => $orderId, ':u' => $this->user['id']]
        );
        if ($payment === null) {
            \Faoxima\Api\Runtime\Response::notFound('Payment record not found');
        }

        $currentStatus = strtolower((string)($payment['payment_Status'] ?? ''));
        $method = strtolower(trim((string)($payment['Payment_Method'] ?? '')));
        $receiptMarker = trim((string)($payment['dec_not_confirmed'] ?? ''));

        if (!in_array($method, ['cart to cart', 'carttocart_pv'], true)) {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این روش پرداخت از بازنشانی خودکار پشتیبانی نمی‌کند.');
        }
        if ($currentStatus === 'paid') {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این پرداخت قبلاً تأیید شده و نمی‌توان آن را بازنشانی کرد.');
        }

        if ($currentStatus === 'reject') {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این پرداخت قبلاً رد شده و نمی‌توان آن را بازنشانی کرد.');
        }

        if ($currentStatus === 'expire') {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این پرداخت منقضی شده و نمی‌توان آن را بازنشانی کرد.');
        }
        if ($currentStatus === 'cancelled') {
            \Faoxima\Api\Runtime\Response::ok([
                'order_id' => $orderId,
                'message'  => '✅ درخواست پرداخت قبلی لغو شد. اکنون می‌توانید دوباره پرداخت جدید ثبت کنید.',
            ]);
        }
        $allowedStatuses = ['pending', 'unpaid'];
        if (!in_array($currentStatus, $allowedStatuses, true)) {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ وضعیت پرداخت فعلی قابل بازنشانی نیست: ' . $currentStatus);
        }
        if ($receiptMarker !== '') {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ رسید این پرداخت قبلاً برای ادمین ارسال شده و قابل بازنشانی نیست.');
        }

        $result = \Faoxima\Payments\Receipts\Cancellation::cancelAbandonedCardPayment(\Faoxima\Api\Runtime\Db::pdo(), $orderId, (string)$this->user['id'], 'user_reset', $isVerify ? [] : ['source' => 'miniapp']);
        if (empty($result['ok'])) {
            $reason = (string)($result['reason'] ?? '');
            \Faoxima\Api\Runtime\Logger::warn('Payment_report cancel failed', ['order' => $orderId, 'user_id' => $this->user['id'], 'reason' => $reason]);
            if ($reason === 'db_error' || $reason === 'pdo_unavailable') {
                \Faoxima\Api\Runtime\Response::serverError('❌ خطا در بازنشانی وضعیت پرداخت');
            }
            if ($reason === 'verify_locked' || $reason === 'verify_window_active') {
                \Faoxima\Api\Runtime\Response::fail(409, '🔎 واریز این پرداخت شناسایی شده یا پرداخت هنوز در مهلت بررسی است و قابل لغو نیست.');
            }
            \Faoxima\Api\Runtime\Response::fail(409, '❌ رسید این پرداخت قبلاً برای ادمین ارسال شده و قابل بازنشانی نیست.');
        }

        if (method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_release_for_order')) {
            \Faoxima\Orders\Checkout\Settlement::releaseForOrder(\Faoxima\Api\Runtime\Db::pdo(), $orderId, 'cancelled', 'payment_reset');
        }

        \Faoxima\Api\Runtime\Logger::debug('Payment status reset', [
            'order'      => $orderId,
            'user_id'    => $this->user['id'],
            'old_status' => $currentStatus,
            'new_status' => 'cancelled',
            'invoice_invalidated' => !empty($result['invoice_invalidated']),
        ]);

        \Faoxima\Api\Runtime\Response::ok([
            'order_id' => $orderId,
            'message'  => '✅ درخواست پرداخت قبلی لغو شد. اکنون می‌توانید دوباره پرداخت جدید ثبت کنید.',
        ]);
    }
}
