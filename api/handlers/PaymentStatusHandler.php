<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/ServiceHandler.php';

final class PaymentStatusHandler extends BaseHandler
{

    private const REASON_FA = [
        'hash-already-used'          => 'این هش قبلاً برای فاکتور دیگری ثبت شده است',
        'invalid-hash'               => 'فرمت هش نامعتبر است',
        'bad-hash-format'            => 'فرمت هش نامعتبر است',
        'wrong-recipient'            => 'گیرنده تراکنش با کیف‌پول ربات همخوانی ندارد',
        'amount-mismatch'            => 'مبلغ تراکنش با مبلغ فاکتور برابر نیست',
        'tx-failed'                  => 'تراکنش روی شبکه ناموفق بوده است',
        'tx-not-found'               => 'تراکنش روی شبکه پیدا نشد',
        'not-trx-transfer'           => 'تراکنش از نوع انتقال TRX نیست',
        'no-matching-trc20-transfer' => 'انتقال TRC20 معتبری در این هش پیدا نشد',
        'no-matching-jetton-transfer'=> 'انتقال Jetton معتبری در این هش پیدا نشد',
        'sender-mismatch'            => 'فرستنده تراکنش با ثبت قبلی فرق دارد',
        'sender-already-bound'       => 'فرستنده قبلاً به فاکتور دیگری متصل شده است',
        'memo-mismatch'              => 'memo تراکنش با memo فاکتور برابر نیست',
        'order-not-pending'          => 'فاکتور دیگر در حالت انتظار نیست',
        'currency-not-supported'     => 'ارز انتخابی پشتیبانی نمی‌شود',
        'wallet-not-configured'      => 'کیف‌پول این ارز روی سرور تنظیم نشده',
        'rate-unavailable'           => 'نرخ ارز در دسترس نیست — کمی بعد دوباره تلاش کنید',
        'below-min'                  => 'مبلغ کمتر از حداقل مجاز این روش است',
        'above-max'                  => 'مبلغ بیشتر از حداکثر مجاز این روش است',
    ];

    public function handle(): void
    {
        $this->requireMethod('GET');

        $orderId = FaoximaInput::string($this->data, 'order_id');
        if ($orderId === '') {
            FaoximaResponse::badRequest('order_id is required');
        }

        $report = FaoximaDb::fetchOne(
            'SELECT * FROM Payment_report WHERE id_order = :o AND id_user = :u AND source = \'miniapp\' LIMIT 1',
            [
                ':o' => $orderId,
                ':u' => (string)$this->user['id'],
            ]
        );
        if ($report === null) {
            FaoximaResponse::notFound('Payment not found');
        }

        $paymentStatus = (string)($report['payment_Status'] ?? 'Unpaid');
        $reasonRaw = trim((string)($report['dec_not_confirmed'] ?? ''));
        $reasonFa  = $this->resolveReason($paymentStatus, $reasonRaw);


        $invoice = $this->resolveInvoice($report, $orderId);

        $service = null;
        $isServiceReady = false;
        $invoiceStatus = $invoice ? (string)($invoice['Status'] ?? '') : '';

        if ($invoice !== null && in_array($invoiceStatus, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {

            try {
                $svcHandler = new ServiceHandler($this->user, []);
                $service = $svcHandler->buildPayloadFromInvoice($invoice);
                if ($service !== null) {
                    $isServiceReady = true;
                }
            } catch (Throwable $e) {
                FaoximaLogger::userFacing('PaymentStatus buildPayloadFromInvoice failed', [
                    'order' => $orderId,
                    'err'   => $e->getMessage(),
                ]);
            }
        }


        $createdAt = $this->parseTimestamp((string)($report['time'] ?? ''));
        $ttlSec = $this->resolveTtl($report);
        $expiresAt = $createdAt > 0 ? ($createdAt + $ttlSec) : 0;

        $hashAt = (int)($report['crypto_hash_at'] ?? 0);

        $rawInvoiceField = (string)($report['id_invoice'] ?? '');
        $hasPurchaseTarget = false;
        if ($rawInvoiceField !== '' && strpos($rawInvoiceField, '|') !== false) {
            $parts = explode('|', $rawInvoiceField, 2);
            $tag    = isset($parts[0]) ? trim((string)$parts[0]) : '';
            $target = isset($parts[1]) ? trim((string)$parts[1]) : '';
            if ($tag === 'getconfigafterpay' && $target !== '') {
                $hasPurchaseTarget = true;
            }
        }
        $flow = $hasPurchaseTarget ? 'direct_buy' : 'recharge';

        $payload = [
            'order_id'         => $orderId,
            'payment_status'   => $paymentStatus,
            'invoice_status'   => $invoiceStatus !== '' ? $invoiceStatus : null,
            'is_service_ready' => $isServiceReady,
            'service'          => $service,
            'reason'           => $reasonFa,
            'method'           => (string)($report['Payment_Method'] ?? ''),
            'amount'           => (int)($report['price'] ?? 0),
            'created_at'       => $createdAt,
            'expires_at'       => $expiresAt,
            'hash_at'          => $hashAt > 0 ? $hashAt : null,
            'flow'             => $flow,
            'currency_code'    => trim((string)($report['crypto_currency'] ?? '')) ?: null,
            'crypto_amount'    => trim((string)($report['crypto_amount']   ?? '')) ?: null,
            'wallet_to'        => trim((string)($report['crypto_wallet_to'] ?? '')) ?: null,
            'iranian_mode'     => (int)($report['crypto_iranian_mode'] ?? 0) === 1,
        ];

        FaoximaResponse::ok($payload);
    }


    private function resolveInvoice(array $report, string $orderId): ?array
    {

        $rawInvoiceField = (string)($report['id_invoice'] ?? '');
        $candidateUsernames = [];


        if ($rawInvoiceField !== '') {
            if (strpos($rawInvoiceField, '|') !== false) {
                $parts = explode('|', $rawInvoiceField, 2);
                if (isset($parts[1]) && $parts[1] !== '') {
                    $candidateUsernames[] = $parts[1];
                }
            }

            $candidateUsernames[] = $rawInvoiceField;
        }


        foreach ($candidateUsernames as $username) {
            $invoice = FaoximaDb::fetchOne(
                'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
                [':u' => $this->user['id'], ':n' => $username]
            );
            if ($invoice !== null) return $invoice;
        }


        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND id_invoice = :i LIMIT 1',
            [':u' => $this->user['id'], ':i' => $orderId]
        );
        return $invoice;
    }


    private function resolveReason(string $paymentStatus, string $reasonRaw): ?string
    {
        if ($paymentStatus !== 'reject' && $paymentStatus !== 'expire') {
            return null;
        }
        if ($reasonRaw === '') {
            return $paymentStatus === 'expire'
                ? 'فاکتور منقضی شده است'
                : 'پرداخت تایید نشد';
        }

        if (preg_match('/^\s*([a-z0-9\-_]+)/i', $reasonRaw, $m)) {
            $code = strtolower($m[1]);
            if (isset(self::REASON_FA[$code])) {
                return self::REASON_FA[$code];
            }
        }

        if (mb_strlen($reasonRaw) > 200) {
            return mb_substr($reasonRaw, 0, 200) . '…';
        }
        return $reasonRaw;
    }


    private function parseTimestamp(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') return 0;

        $raw = strtr($raw, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);


        if (preg_match('#^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?#', $raw, $m)) {
            $jy = (int)$m[1]; $jm = (int)$m[2]; $jd = (int)$m[3];
            $h  = isset($m[4]) ? (int)$m[4] : 0;
            $mi = isset($m[5]) ? (int)$m[5] : 0;
            $s  = isset($m[6]) ? (int)$m[6] : 0;
            if ($jy < 1700 && function_exists('jalali_to_gregorian')) {
                [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
                $ts = mktime($h, $mi, $s, $gm, $gd, $gy);
                if ($ts !== false) return (int)$ts;
            }
        }

        $ts = strtotime(str_replace('/', '-', $raw));
        return $ts === false ? 0 : (int)$ts;
    }


    private function resolveTtl(array $report): int
    {
        $method = strtolower(trim((string)($report['Payment_Method'] ?? '')));


        if (in_array($method, ['plisio', 'nowpayment', 'digitaltron'], true)) {
            return 600;
        }


        $iranian = (int)($report['crypto_iranian_mode'] ?? 0) === 1;
        if (function_exists('crypto_pay_setting')) {
            $key = $iranian ? 'cryptocheck_invoice_ttl_iranian' : 'cryptocheck_invoice_ttl';
            $val = (int) crypto_pay_setting($key, $iranian ? '7200' : '86400');
            if ($val >= 60) return $val;
        }
        return $iranian ? 7200 : 86400;
    }
}
