<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CryptoCancelInvoiceHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId = FaoximaInput::string($this->data, 'order_id');
        if ($orderId === '') {
            FaoximaResponse::badRequest('order_id is required');
        }

        $report = FaoximaDb::fetchOne(
            'SELECT id_order, payment_Status, Payment_Method FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND source = \'miniapp\' LIMIT 1',
            [':o' => $orderId, ':u' => (string)$this->user['id']]
        );
        if ($report === null) {
            FaoximaResponse::notFound('Payment not found');
        }
        $method = trim((string)($report['Payment_Method'] ?? ''));
        $cancellable = ['arze digital offline', 'plisio', 'nowpayment', 'digitaltron', 'cart to cart', 'carttocart_pv'];
        if (!in_array($method, $cancellable, true)) {
            FaoximaResponse::fail(422, 'این فاکتور قابل لغو از این طریق نیست');
        }
        $status = (string)($report['payment_Status'] ?? '');


        if (in_array($status, ['expire', 'paid', 'reject'], true)) {
            FaoximaResponse::ok([
                'kind'    => 'already_finalized',
                'message' => 'این فاکتور قبلاً نهایی شده است',
                'status'  => $status,
            ]);
            return;
        }

        if (!in_array($status, ['Unpaid', 'AwaitingHash'], true)) {
            FaoximaResponse::fail(409, 'این فاکتور در وضعیتی نیست که قابل لغو باشد (' . $status . ')');
        }

        try {
            $pdo = FaoximaDb::pdo();
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'expire'
                  WHERE id_order = :o
                    AND id_user = :u
                    AND source = 'miniapp'
                    AND payment_Status IN ('Unpaid', 'AwaitingHash')"
            );
            $stmt->bindValue(':o', $orderId, PDO::PARAM_STR);
            $stmt->bindValue(':u', (string)$this->user['id'], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'Crypto cancel-invoice update failed', [
                'user'  => $this->user['id'],
                'order' => $orderId,
            ]);
            FaoximaResponse::serverError('خطا در لغو فاکتور');
        }

        FaoximaLogger::debug('Crypto invoice cancelled by user', [
            'user'  => $this->user['id'],
            'order' => $orderId,
        ]);

        FaoximaResponse::ok([
            'kind'    => 'cancelled',
            'message' => '✅ فاکتور لغو شد',
        ]);
    }
}
