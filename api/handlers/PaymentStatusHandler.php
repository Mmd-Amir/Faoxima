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
        'invoice_not_found'          => 'فاکتور مربوط به این پرداخت پیدا نشد — لطفاً با پشتیبانی تماس بگیرید',
    ];

    public function handle(): void
    {
        $this->requireMethod('GET');

        $orderId = FaoximaInput::string($this->data, 'order_id');
        if ($orderId === '') {
            FaoximaResponse::badRequest('order_id is required');
        }

        $rxPayCacheKey = rx_payment_status_cache_key($orderId, $this->user['id']);
        [$report, $sbvView] = $this->loadStatusReport($orderId, $rxPayCacheKey);
        if ($report === null) {
            FaoximaResponse::notFound('Payment not found');
        }

        [$report, $sbvView] = $this->refreshStatusReport($orderId, $rxPayCacheKey, $report, $sbvView);

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
        $hashAt = (int)($report['crypto_hash_at'] ?? 0);
        $isCrypto = trim((string)($report['crypto_currency'] ?? '')) !== '';

        if ($isCrypto && $hashAt > 0) {
            $expiresAt = $hashAt + 1800;
        } elseif ($isCrypto) {
            $expiresAt = 0;
        } else {
            $ttlSec = $this->resolveTtl($report);
            $expiresAt = $createdAt > 0 ? ($createdAt + $ttlSec) : 0;
        }
        if ($sbvView !== null && (string) ($report['id_user'] ?? '') === (string) $this->user['id']) {
            $expiresAt = (int) $sbvView['expires_at'];
        } else {
            $sbvView = null;
        }

        $rawInvoiceField = (string)($report['id_invoice'] ?? '');
        $hasPurchaseTarget = false;
        $pendingActionTag = '';
        if ($rawInvoiceField !== '' && strpos($rawInvoiceField, '|') !== false) {
            $parts = explode('|', $rawInvoiceField, 2);
            $tag    = isset($parts[0]) ? trim((string)$parts[0]) : '';
            $target = isset($parts[1]) ? trim((string)$parts[1]) : '';
            if ($tag === 'getconfigafterpay' && $target !== '') {
                $hasPurchaseTarget = true;
            } elseif (in_array($tag, ['getextenduser', 'getextratimeuser', 'getextravolumeuser'], true) && $target !== '') {
                $pendingActionTag = $tag;
            }
        }
        $flow = $hasPurchaseTarget ? 'direct_buy' : ($pendingActionTag !== '' ? 'pending_action' : 'recharge');

        // AbanGateway keeps no link column: the page address follows from the
        // authority stored at create time, so a buyer who comes back to a
        // pending order can reopen it.
        $abangatewayUrl = null;
        if ((string)($report['Payment_Method'] ?? '') === 'abangateway'
            && $paymentStatus !== 'paid'
            && function_exists('abangatewayPayUrlFor')) {
            $abangatewayUrl = abangatewayPayUrlFor((string)($report['dec_not_confirmed'] ?? ''));
        }

        $tonpayGatewayUrl = trim((string)($report['tonpay_invoice_url'] ?? ''));
        if ((string)($report['Payment_Method'] ?? '') === 'tonpay' && function_exists('tonpaySelectPaymentUrl')) {
            $tonpayGatewayUrl = tonpaySelectPaymentUrl($tonpayGatewayUrl, $report['tonpay_web_invoice_url'] ?? '');
        }

        $tonpayCustomView = null;
        if ((string)($report['Payment_Method'] ?? '') === 'tonpay'
            && (string)($report['tonpay_gateway_mode'] ?? '') === 'custom'
            && function_exists('tonpayCustomPublicView')) {
            $tonpayCustomView = tonpayCustomPublicView($report);
        }

        $rialitGatewayUrl = '';
        $rialitMeta = null;
        if ((string)($report['Payment_Method'] ?? '') === 'rialit') {
            $rialitToken = trim((string)($report['rialit_token'] ?? ''));
            $rialitCreatedAt = function_exists('rialitReportCreatedAt') ? rialitReportCreatedAt($report) : $createdAt;
            $rialitLinkExpiresAt = $rialitCreatedAt > 0 ? $rialitCreatedAt + 1800 : 0;
            $rialitStatus = strtolower(trim((string)($report['rialit_status'] ?? '')));
            $rialitLinkAvailable = function_exists('rialitLinkAvailable')
                ? rialitLinkAvailable($report)
                : ($rialitToken !== '' && $paymentStatus !== 'paid' && $rialitLinkExpiresAt > 0 && time() < $rialitLinkExpiresAt);
            if ($rialitLinkAvailable && function_exists('rialitPaymentUrl')) {
                $rialitGatewayUrl = rialitPaymentUrl($rialitToken);
            }
            $rialitMeta = [
                'token' => $rialitToken,
                'remote_status' => $rialitStatus,
                'link_available' => $rialitLinkAvailable,
                'link_expires_at' => $rialitLinkExpiresAt,
                'awaiting_result' => $paymentStatus !== 'paid' && $rialitLinkExpiresAt > 0 && time() >= $rialitLinkExpiresAt && !in_array($rialitStatus, ['expired','cancelled'], true),
                'payer_amount' => (int)($report['rialit_payer_amount'] ?? 0),
                'payer_fee' => (int)($report['rialit_payer_fee'] ?? 0),
                'paid_at' => trim((string)($report['rialit_paid_at'] ?? '')),
                'reference' => trim((string)($report['rialit_reference'] ?? '')),
                'fulfillment_done' => (int) ($report['direct_payment_done'] ?? 0) === 1,
                'locally_cancelled' => (int) ($report['rialit_abandoned_at'] ?? 0) > 0,
            ];
        }

        $atlaspayCustomView = null;
        if ((string)($report['Payment_Method'] ?? '') === 'atlaspay'
            && (string)($report['atlaspay_gateway_mode'] ?? '') === 'custom'
            && function_exists('atlaspayCustomPublicView')) {
            $atlaspayCustomView = atlaspayCustomPublicView($report);
        }

        $atlaspayReceipt = null;
        if ((string)($report['Payment_Method'] ?? '') === 'atlaspay'
            && function_exists('atlaspayCustomBuildState')) {
            $atlaspayState = atlaspayCustomBuildState($report);
            $atlaspayReceipt = [
                'url' => $atlaspayState['receipt_url'],
                'message' => $atlaspayState['receipt_url'] !== '' ? atlaspayReceiptInstructions() : '',
                'expires_at' => $atlaspayState['deadline_at'],
            ];
        }

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
            'pending_action'   => $pendingActionTag !== '' ? $pendingActionTag : null,
            'currency_code'    => trim((string)($report['crypto_currency'] ?? '')) ?: null,
            'crypto_amount'    => trim((string)($report['crypto_amount']   ?? '')) ?: null,
            'wallet_to'        => trim((string)($report['crypto_wallet_to'] ?? '')) ?: null,
            'verify'           => $sbvView,
            'tonpay_custom'    => $tonpayCustomView,
            'atlaspay_custom'  => $atlaspayCustomView,
            'atlaspay_receipt' => $atlaspayReceipt,
            'rialit'           => $rialitMeta,
            'fulfillment'      => ($sbvView !== null && function_exists('rx_sbv_fulfillment_outcome')) ? rx_sbv_fulfillment_outcome($orderId) : null,
            'gateway_url'      => $rialitGatewayUrl ?: (trim((string)($report['tronado_payment_url'] ?? '')) ?: ($tonpayGatewayUrl ?: (trim((string)($report['cubepay_payment_link'] ?? '')) ?: (trim((string)($report['blupal_payment_link'] ?? '')) ?: (trim((string)($report['atlaspay_payment_url'] ?? '')) ?: (trim((string)($report['tetrapay_payment_link'] ?? '')) ?: $abangatewayUrl)))))),
        ];

        FaoximaResponse::ok($payload);
    }


    private function loadStatusReport(string $orderId, string $rxPayCacheKey): array
    {
        $report = null;
        if (function_exists('rx_redis_is_active') && rx_redis_is_active()) {
            $rxPayCached = rx_redis_get($rxPayCacheKey);
            if ($rxPayCached !== null) {
                $rxPayDecoded = json_decode($rxPayCached, true);
                if (is_array($rxPayDecoded)
                    && (string) ($rxPayDecoded['id_order'] ?? '') === $orderId
                    && (string) ($rxPayDecoded['id_user'] ?? '') === (string) $this->user['id']) {
                    $report = $rxPayDecoded;
                }
            }
        }

        $sbvView = function_exists('rx_sbv_public_view') ? rx_sbv_public_view($orderId) : null;
        if ($report === null) {
            $report = FaoximaDb::fetchOne(
                'SELECT * FROM Payment_report WHERE id_order = :o AND id_user = :u AND ' . ($sbvView !== null ? '1 = 1' : '(source = \'miniapp\' OR (Payment_Method = \'tonpay\' AND tonpay_gateway_mode = \'custom\') OR (Payment_Method = \'atlaspay\' AND atlaspay_gateway_mode = \'custom\') OR Payment_Method = \'rialit\')') . ' LIMIT 1',
                [
                    ':o' => $orderId,
                    ':u' => (string)$this->user['id'],
                ]
            );
            if (is_array($report) && function_exists('rx_redis_is_active') && rx_redis_is_active()) {
                $rxPayEncoded = json_encode($report, JSON_UNESCAPED_UNICODE);
                if ($rxPayEncoded !== false) {
                    rx_redis_set($rxPayCacheKey, $rxPayEncoded, 3);
                }
            }
        }
        return [$report, $sbvView];
    }

    private function refreshStatusReport(string $orderId, string $rxPayCacheKey, $report, $sbvView): array
    {
        if ($sbvView !== null && (string) ($report['id_user'] ?? '') === (string) $this->user['id']) {
            $sbvPayment = rx_sbv_get($orderId);
            if (is_array($sbvPayment) && (string) $sbvPayment['state'] === 'matched' && !empty($sbvPayment['deposit_id'])) {
                try {
                    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                    if (is_file($autoload)) {
                        require_once $autoload;
                    }
                    rxEnsurePaymentRuntime();
                    rxPaymentV2PrepareContext();
                    rx_sbv_process_deposit((int) $sbvPayment['deposit_id'], dirname(__DIR__, 2) . '/images.jpg');
                } catch (Throwable $e) {
                    rx_sbv_log('SBV_STATUS_RECOVER_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                }
                $report = FaoximaDb::fetchOne(
                    'SELECT * FROM Payment_report WHERE id_order = :o AND id_user = :u LIMIT 1',
                    [':o' => $orderId, ':u' => (string) $this->user['id']]
                );
                if ($report === null) {
                    FaoximaResponse::notFound('Payment not found');
                }
                $sbvView = rx_sbv_public_view($orderId);
                if (function_exists('rx_redis_del')) {
                    rx_redis_del($rxPayCacheKey);
                }
            }
        }

        if ((string) ($report['Payment_Method'] ?? '') === 'rialit' && function_exists('rialitPollThrottled')) {
            $fresh = rialitPollThrottled($orderId, (string) $this->user['id']);
            if (is_array($fresh)) {
                $report = $fresh;
            }
            if (function_exists('rx_redis_del')) {
                rx_redis_del($rxPayCacheKey);
            }
        }
        return [$report, $sbvView];
    }

    private function resolveInvoice(array $report, string $orderId): ?array
    {

        $rawInvoiceField = (string)($report['id_invoice'] ?? '');
        $candidateUsernames = [];


        if ($rawInvoiceField !== '') {
            if (strpos($rawInvoiceField, '|') !== false) {
                $parts = explode('|', $rawInvoiceField, 2);
                if (isset($parts[1]) && $parts[1] !== '') {
                    $target = $parts[1];
                    if (strpos($target, '%') !== false) {
                        $target = explode('%', $target, 2)[0];
                    }
                    if ($target !== '') {
                        $candidateUsernames[] = $target;
                    }
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
        if ($paymentStatus !== 'reject' && $paymentStatus !== 'expire' && $paymentStatus !== 'cancelled') {
            return null;
        }
        if ($paymentStatus === 'expire') {
            return 'فاکتور منقضی شده است';
        }
        if ($paymentStatus === 'cancelled') {
            return 'فاکتور توسط شما لغو شده است';
        }
        if ($reasonRaw === '') {
            return 'پرداخت تایید نشد';
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
        if ((string)($report['Payment_Method'] ?? '') === 'cubepay') {
            return 3600;
        }
        if ((string)($report['Payment_Method'] ?? '') === 'variza') {
            return 3600;
        }
        if ((string)($report['Payment_Method'] ?? '') === 'atlaspay') {
            return 1200;
        }
        if ((string)($report['Payment_Method'] ?? '') === 'tonpay') {
            return 86400;
        }
        if ((string)($report['Payment_Method'] ?? '') === 'rialit') {
            return 1800;
        }
        return 1800;
    }
}
