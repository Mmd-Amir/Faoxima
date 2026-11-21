<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use PDO;
use Throwable;

final class CryptoCancelInvoiceHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId = \Faoxima\Api\Runtime\Input::string($this->data, 'order_id');
        if ($orderId === '') {
            \Faoxima\Api\Runtime\Response::badRequest('order_id is required');
        }

        $report = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT id_order, payment_Status, Payment_Method, atlaspay_order_id FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND (source = \'miniapp\' OR (Payment_Method = \'tonpay\' AND tonpay_gateway_mode = \'custom\') OR (Payment_Method = \'atlaspay\' AND atlaspay_gateway_mode = \'custom\') OR Payment_Method = \'rialit\') LIMIT 1',
            [':o' => $orderId, ':u' => (string)$this->user['id']]
        );
        if ($report === null) {
            \Faoxima\Api\Runtime\Response::notFound('Payment not found');
        }
        $method = trim((string)($report['Payment_Method'] ?? ''));
        if ($method === 'rialit') {
            $result = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::cancelLocally(\Faoxima\Api\Runtime\Db::pdo(), $orderId, (string) $this->user['id']);
            if (empty($result['ok'])) {
                \Faoxima\Api\Runtime\Response::fail(409, (string) $result['message']);
            }
            \Faoxima\Api\Runtime\Response::ok(['kind' => 'cancelled', 'message' => (string) $result['message']]);
            return;
        }
        $cancellable = ['arze digital offline', 'plisio', 'nowpayment', 'digitaltron', 'cart to cart', 'carttocart_pv', 'iranpay2', 'tonpay', 'cubepay', 'blupal', 'variza', 'abangateway', 'atlaspay'];
        if (!in_array($method, $cancellable, true)) {
            \Faoxima\Api\Runtime\Response::fail(422, 'این فاکتور قابل لغو از این طریق نیست');
        }
        $status = (string)($report['payment_Status'] ?? '');

        if ($method === 'tonpay' && method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomFetchReport')) {
            $customReport = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport(\Faoxima\Api\Runtime\Db::pdo(), $orderId);
            if ($customReport !== null) {
                $customResult = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancel($orderId, (string)$this->user['id']);
                if (empty($customResult['ok'])) {
                    \Faoxima\Api\Runtime\Response::fail(409, (string)($customResult['message'] ?? 'لغو فاکتور انجام نشد.'));
                }
                \Faoxima\Api\Runtime\Response::ok([
                    'kind'    => 'cancelled',
                    'message' => '✅ فاکتور لغو شد',
                ]);
                return;
            }
        }


        if ($method === 'atlaspay' && method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayCustomFetchReport')) {
            $customReport = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport(\Faoxima\Api\Runtime\Db::pdo(), $orderId);
            if ($customReport !== null) {
                $customResult = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCancel($orderId, (string)$this->user['id']);
                if (empty($customResult['ok'])) {
                    \Faoxima\Api\Runtime\Response::fail(409, (string)($customResult['message'] ?? 'لغو فاکتور انجام نشد.'));
                }
                \Faoxima\Api\Runtime\Response::ok([
                    'kind'    => 'cancelled',
                    'message' => '✅ فاکتور لغو شد',
                ]);
                return;
            }
        }

        if (in_array($status, ['cancelled', 'paid', 'reject'], true)) {
            \Faoxima\Api\Runtime\Response::ok([
                'kind'    => 'already_finalized',
                'message' => 'این فاکتور قبلاً نهایی شده است',
                'status'  => $status,
            ]);
            return;
        }

        if (!in_array($status, ['Unpaid', 'AwaitingHash', 'expire'], true)) {
            \Faoxima\Api\Runtime\Response::fail(409, 'این فاکتور در وضعیتی نیست که قابل لغو باشد (' . $status . ')');
        }

        \Faoxima\Orders\Discounts\SellDiscounts::releaseUnpaidDiscount(\Faoxima\Api\Runtime\Db::pdo(), (string)$this->user['id']);

        if ($method === 'atlaspay' && is_callable([\Faoxima\Payments\Gateways\Atlaspay\Client::class, 'cancelOrder'])) {
            $atlaspayOrderId = trim((string)($report['atlaspay_order_id'] ?? ''));
            if ($atlaspayOrderId !== '') {
                try {
                    \Faoxima\Payments\Gateways\Atlaspay\Client::cancelOrder($atlaspayOrderId);
                } catch (Throwable $e) {
                    \Faoxima\Api\Runtime\Logger::exception($e, 'AtlasPay cancel-order call failed', [
                        'user'  => $this->user['id'],
                        'order' => $orderId,
                    ]);
                }
            }
        }

        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'cancelled'
                  WHERE id_order = :o
                    AND id_user = :u
                    AND source = 'miniapp'
                    AND payment_Status IN ('Unpaid', 'AwaitingHash', 'expire')"
            );
            $stmt->bindValue(':o', $orderId, PDO::PARAM_STR);
            $stmt->bindValue(':u', (string)$this->user['id'], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'Crypto cancel-invoice update failed', [
                'user'  => $this->user['id'],
                'order' => $orderId,
            ]);
            \Faoxima\Api\Runtime\Response::serverError('خطا در لغو فاکتور');
        }

        if (method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_release_for_order')) {
            \Faoxima\Orders\Checkout\Settlement::releaseForOrder(\Faoxima\Api\Runtime\Db::pdo(), $orderId, 'cancelled', 'crypto_cancelled');
        }

        \Faoxima\Api\Runtime\Logger::debug('Crypto invoice cancelled by user', [
            'user'  => $this->user['id'],
            'order' => $orderId,
        ]);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'    => 'cancelled',
            'message' => '✅ فاکتور لغو شد',
        ]);
    }
}
