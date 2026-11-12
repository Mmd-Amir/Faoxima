<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class TonpayCustomHandler extends BaseHandler
{
    public string $mode = 'view';

    public function handle(): void
    {
        if (!function_exists('tonpayCustomFetchReport')) {
            FaoximaResponse::fail(503, 'درگاه تون‌پی روی این سرور در دسترس نیست.');
        }

        if ($this->mode === 'view') {
            $this->requireMethod('GET');
        } else {
            $this->requireMethod('POST');
        }

        $source = $this->mode === 'receipt' ? $_POST : $this->data;
        $orderId = FaoximaInput::string($source, 'order_id');
        if ($orderId === '' || !preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderId)) {
            FaoximaResponse::badRequest('order_id is required');
        }
        $userId = (string)$this->user['id'];

        switch ($this->mode) {
            case 'view':
                $report = tonpayCustomPollThrottled($orderId, $userId);
                if ($report === null) {
                    FaoximaResponse::notFound('Payment not found');
                }
                FaoximaResponse::ok(['view' => tonpayCustomPublicView($report)]);
                return;

            case 'change_card':
                $result = tonpayCustomChangeCard($orderId, $userId);
                if (empty($result['ok'])) {
                    FaoximaResponse::fail(409, (string)($result['message'] ?? 'تعویض کارت انجام نشد.'), ['wait_sec' => $result['wait_sec'] ?? null]);
                }
                FaoximaResponse::ok(['view' => tonpayCustomPublicView($result['report'])]);
                return;

            case 'cancel':
                $result = tonpayCustomCancel($orderId, $userId);
                if (empty($result['ok'])) {
                    FaoximaResponse::fail(409, (string)($result['message'] ?? 'لغو فاکتور انجام نشد.'));
                }
                FaoximaResponse::ok(['kind' => 'cancelled', 'order_id' => $orderId]);
                return;

            case 'refund':
                $cardNumber = FaoximaInput::string($this->data, 'card_number');
                $cardHolder = FaoximaInput::string($this->data, 'card_holder');
                $result = tonpayCustomSubmitRefund($orderId, $userId, $cardNumber, $cardHolder);
                if (empty($result['ok'])) {
                    FaoximaResponse::fail(409, (string)($result['message'] ?? 'ثبت کارت عودت انجام نشد.'), [
                        'code' => $result['code'] ?? 'error',
                    ]);
                }
                FaoximaResponse::ok([
                    'view'    => isset($result['report']) ? tonpayCustomPublicView($result['report']) : null,
                    'message' => (string)($result['message'] ?? 'کارت عودت ثبت شد.'),
                    'already_open' => !empty($result['already_open']),
                ]);
                return;

            case 'receipt':
                $this->handleReceipt($orderId, $userId);
                return;
        }

        FaoximaResponse::badRequest('Unknown mode');
    }

    private function handleReceipt(string $orderId, string $userId): void
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            FaoximaResponse::badRequest('file is required');
        }
        $file = $_FILES['file'];
        if ((int)($file['error'] ?? 99) !== UPLOAD_ERR_OK) {
            FaoximaResponse::badRequest('upload error');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            FaoximaResponse::badRequest('file not accessible on server');
        }

        $result = tonpayCustomSubmitReceipt($orderId, $userId, $tmp);
        if (empty($result['ok'])) {
            $code = (string)($result['code'] ?? 'error');
            $status = in_array($code, ['invalid_file', 'invalid_receipt_type', 'receipt_too_large'], true) ? 422 : 409;
            FaoximaResponse::fail($status, (string)($result['message'] ?? 'ارسال رسید ناموفق بود.'), [
                'code' => $code,
                'wait_sec' => $result['wait_sec'] ?? null,
            ]);
        }
        FaoximaResponse::ok([
            'view'    => tonpayCustomPublicView($result['report']),
            'message' => 'رسید شما با موفقیت ارسال شد. بررسی پرداخت ممکن است تا ۲۴ ساعت طول بکشد.',
        ]);
    }
}
