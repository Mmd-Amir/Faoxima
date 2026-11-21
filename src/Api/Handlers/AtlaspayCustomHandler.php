<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class AtlaspayCustomHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public string $mode = 'view';

    public function handle(): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayCustomFetchReport')) {
            \Faoxima\Api\Runtime\Response::fail(503, 'درگاه اطلس‌پی روی این سرور در دسترس نیست.');
        }

        if ($this->mode === 'view') {
            $this->requireMethod('GET');
        } else {
            $this->requireMethod('POST');
        }

        $orderId = \Faoxima\Api\Runtime\Input::string($this->data, 'order_id');
        if ($orderId === '' || !preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderId)) {
            \Faoxima\Api\Runtime\Response::badRequest('order_id is required');
        }
        $userId = (string)$this->user['id'];

        switch ($this->mode) {
            case 'view':
                $report = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomPollThrottled($orderId, $userId);
                if ($report === null) {
                    \Faoxima\Api\Runtime\Response::notFound('Payment not found');
                }
                \Faoxima\Api\Runtime\Response::ok(['view' => \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::publicView($report)]);
                return;

            case 'cancel':
                $result = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCancel($orderId, $userId);
                if (empty($result['ok'])) {
                    \Faoxima\Api\Runtime\Response::fail(409, (string)($result['message'] ?? 'لغو فاکتور انجام نشد.'));
                }
                \Faoxima\Api\Runtime\Response::ok(['kind' => 'cancelled', 'order_id' => $orderId]);
                return;
        }

        \Faoxima\Api\Runtime\Response::badRequest('Unknown mode');
    }
}
