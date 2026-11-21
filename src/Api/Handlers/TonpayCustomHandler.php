<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class TonpayCustomHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public string $mode = 'view';

    public function handle(): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomFetchReport')) {
            \Faoxima\Api\Runtime\Response::fail(503, 'درگاه تون‌پی روی این سرور در دسترس نیست.');
        }

        if ($this->mode === 'view') {
            $this->requireMethod('GET');
        } else {
            $this->requireMethod('POST');
        }

        $source = $this->mode === 'receipt' ? $_POST : $this->data;
        $orderId = \Faoxima\Api\Runtime\Input::string($source, 'order_id');
        if ($orderId === '' || !preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderId)) {
            \Faoxima\Api\Runtime\Response::badRequest('order_id is required');
        }
        $userId = (string)$this->user['id'];

        switch ($this->mode) {
            case 'view':
                $report = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomPollThrottled($orderId, $userId);
                if ($report === null) {
                    \Faoxima\Api\Runtime\Response::notFound('Payment not found');
                }
                \Faoxima\Api\Runtime\Response::ok(['view' => \Faoxima\Payments\Gateways\Tonpay\Settlement::publicView($report)]);
                return;

            case 'change_card':
                $result = \Faoxima\Payments\Gateways\Tonpay\Settlement::changeCard(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $userId);
                if (empty($result['ok'])) {
                    \Faoxima\Api\Runtime\Response::fail(409, (string)($result['message'] ?? 'تعویض کارت انجام نشد.'), ['wait_sec' => $result['wait_sec'] ?? null]);
                }
                \Faoxima\Api\Runtime\Response::ok(['view' => \Faoxima\Payments\Gateways\Tonpay\Settlement::publicView($result['report'])]);
                return;

            case 'cancel':
                $result = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancel($orderId, $userId);
                if (empty($result['ok'])) {
                    \Faoxima\Api\Runtime\Response::fail(409, (string)($result['message'] ?? 'لغو فاکتور انجام نشد.'));
                }
                \Faoxima\Api\Runtime\Response::ok(['kind' => 'cancelled', 'order_id' => $orderId]);
                return;

            case 'refund':
                $cardNumber = \Faoxima\Api\Runtime\Input::string($this->data, 'card_number');
                $cardHolder = \Faoxima\Api\Runtime\Input::string($this->data, 'card_holder');
                $result = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomSubmitRefund($orderId, $userId, $cardNumber, $cardHolder);
                if (empty($result['ok'])) {
                    \Faoxima\Api\Runtime\Response::fail(409, (string)($result['message'] ?? 'ثبت کارت عودت انجام نشد.'), [
                        'code' => $result['code'] ?? 'error',
                    ]);
                }
                \Faoxima\Api\Runtime\Response::ok([
                    'view'    => isset($result['report']) ? \Faoxima\Payments\Gateways\Tonpay\Settlement::publicView($result['report']) : null,
                    'message' => (string)($result['message'] ?? 'کارت عودت ثبت شد.'),
                    'already_open' => !empty($result['already_open']),
                ]);
                return;

            case 'receipt':
                $this->handleReceipt($orderId, $userId);
                return;
        }

        \Faoxima\Api\Runtime\Response::badRequest('Unknown mode');
    }

    private function handleReceipt(string $orderId, string $userId): void
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            \Faoxima\Api\Runtime\Response::badRequest('file is required');
        }
        $file = $_FILES['file'];
        if ((int)($file['error'] ?? 99) !== UPLOAD_ERR_OK) {
            \Faoxima\Api\Runtime\Response::badRequest('upload error');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            \Faoxima\Api\Runtime\Response::badRequest('file not accessible on server');
        }

        $result = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomSubmitReceipt($orderId, $userId, $tmp);
        if (empty($result['ok'])) {
            $code = (string)($result['code'] ?? 'error');
            $status = in_array($code, ['invalid_file', 'invalid_receipt_type', 'receipt_too_large'], true) ? 422 : 409;
            \Faoxima\Api\Runtime\Response::fail($status, (string)($result['message'] ?? 'ارسال رسید ناموفق بود.'), [
                'code' => $code,
                'wait_sec' => $result['wait_sec'] ?? null,
            ]);
        }
        \Faoxima\Api\Runtime\Response::ok([
            'view'    => \Faoxima\Payments\Gateways\Tonpay\Settlement::publicView($result['report']),
            'message' => 'رسید شما با موفقیت ارسال شد. بررسی پرداخت ممکن است تا ۲۴ ساعت طول بکشد.',
        ]);
    }
}
