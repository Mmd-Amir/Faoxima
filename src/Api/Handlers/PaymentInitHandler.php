<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use Faoxima\Payments\Receipts\ReceiptOrders;
use Faoxima\Payments\Settlement\PendingInvoices;
use PDO;
use Throwable;

final class PaymentInitHandler extends \Faoxima\Api\Handlers\BaseHandler
{


    private const STALE_UNPAID_MINUTES = 15;

    private int $chargeBonus = 0;
    private string $chargeDiscountCode = '';
    private float $chargeDiscountAmount = 0.0;
    private float $chargePriceBeforeDiscount = 0.0;
    private ?string $checkoutId = null;
    private bool $sbvDeferPurge = false;
    private ?string $sbvPreparedOrder = null;

    public function handle(): void
    {
        $this->requireMethod('POST');

        $resumeOrderId = \Faoxima\Api\Runtime\Input::string($this->data, 'resume_order_id');
        if ($resumeOrderId !== '') {
            $this->resumeVerifyPayment($resumeOrderId);
            return;
        }

        $this->enforceSingleActiveInvoice();

        $method =\Faoxima\Api\Runtime\Input::string($this->data, 'method');
        $amount = \Faoxima\Api\Runtime\Input::int($this->data, 'amount', 0);

        $serverRenewUsername = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'renew_username');
        $serverPurchaseUsername = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'purchase_username');
        $checkoutId = \Faoxima\Api\Runtime\Input::string($this->data, 'checkout_id');
        $ck = null;
        if ($checkoutId !== '' || ($serverRenewUsername !== null && $serverRenewUsername !== '') || ($serverPurchaseUsername !== null && $serverPurchaseUsername !== '')) {
            $expectedKey = '';
            if ($checkoutId === '') {
                if ($serverPurchaseUsername !== null && $serverPurchaseUsername !== '') {
                    $expectedKey = 'getconfigafterpay|' . $serverPurchaseUsername;
                } else {
                    $expectedKey = (string) ($this->user['Processing_value_tow'] ?? '') . '|' . (string) ($this->user['Processing_value_one'] ?? '');
                }
            }
            $ck = $this->serverCheckoutForPayment($checkoutId, $expectedKey);
            $amount = (int) $ck['external_amount'];
            [$ckTow, $ckOne] = \Faoxima\Orders\Checkout\Checkout::keyParts((string) $ck['op_key']);
            if ($ckTow === 'getconfigafterpay') {
                $this->data['purchase_username'] = (string) $ck['target'];
                $this->data['renew_username'] = '';
                $serverPurchaseUsername = (string) $ck['target'];
                $serverRenewUsername = null;
            } else {
                $this->data['renew_username'] = (string) $ck['target'];
                $this->data['purchase_username'] = '';
                $serverRenewUsername = (string) $ck['target'];
                $serverPurchaseUsername = null;
            }
            $this->checkoutId = (string) $ck['checkout_id'];
            if ((string) ($this->user['Processing_value_tow'] ?? '') !== $ckTow || (string) ($this->user['Processing_value_one'] ?? '') !== $ckOne) {
                \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', $ckOne, 'id', $this->user['id']);
                \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', $ckTow, 'id', $this->user['id']);
                $this->user['Processing_value_one'] = $ckOne;
                $this->user['Processing_value_tow'] = $ckTow;
            }
        }

        if ($method === '') {
            \Faoxima\Api\Runtime\Response::badRequest('method is required');
        }
        if ($amount <= 0) {
            \Faoxima\Api\Runtime\Response::badRequest('amount must be > 0');
        }

        $self = $this;
        $get = function (string $name) use ($self): string {
            return $self->paySetting($name);
        };
        $agent = $this->user['agent'] ?? 'f';


        [$min, $max] = $this->methodLimits($method, $agent, $get);
        if ($ck !== null && (int) $ck['wallet_amount'] > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_below_method_min', '❌ مبلغ باقی‌مانده برای پرداخت خارجی ({amount} تومان) کمتر از حداقل این روش ({min} تومان) است. لطفاً روش پرداخت دیگری انتخاب کنید یا بدون استفاده از کیف پول پرداخت کنید. مبلغ و سهم کیف پول به‌صورت خودکار تغییر نمی‌کند.'), [
                'amount' => number_format($amount),
                'min' => number_format($min),
            ]), ['code' => 'below_method_min', 'external_amount' => $amount, 'min' => $min]);
        }
        if ($amount < $min || $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_amount_out_of_range', '❌ مبلغ باید بین {min} و {max} تومان باشد'), [
                'min' => number_format($min),
                'max' => number_format($max),
            ]));
        }


        $isCardPayment = $method === 'carttocart' || $method === 'carttocart_pv';
        $lockName = $isCardPayment
            ? ReceiptOrders::cardCreationLockName((string) $this->user['id'])
            : 'faoxima_payment_init_' . $this->user['id'] . '_' . $method;
        $pdo = \Faoxima\Api\Runtime\Db::pdo();
        $lockStmt = $pdo->prepare('SELECT GET_LOCK(:n, 5)');
        $lockStmt->execute([':n' => $lockName]);
        $lockAcquired = (int) $lockStmt->fetchColumn() === 1;
        if (!$lockAcquired) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'));
        }

        register_shutdown_function(static function () use ($pdo, $lockName): void {
            try {
                $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(:n)');
                $releaseStmt->execute([':n' => $lockName]);
            } catch (Throwable $e) {
            }
        });

        try {
            if ($isCardPayment) {
                $keepInvoiceKey = $ck !== null ? (string) $ck['op_key'] : null;
                $this->sbvDeferPurge = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_active_payment') && \Faoxima\Payments\Receipts\ReceiptOrders::activePayment(\Faoxima\Api\Runtime\Db::pdo(), (string) $this->user['id']) !== null;
                if (!$this->sbvDeferPurge) {
                    $this->purgeCardAndGuard($keepInvoiceKey);
                }
            } else {
                if ($method !== 'rialit' && $method !== 'tetraminator') {
                    $this->purgeStaleGatewayOrders((int)$this->user['id'], $method);

                    $botMethod = PendingInvoices::rx_gateway_payment_method_map()[$method] ?? null;
                    $botOrder = $botMethod === null ? null : PendingInvoices::findRecentInvoice(
                        $pdo, (string) $this->user['id'], $botMethod, false, PendingInvoices::rx_gateway_stale_minutes($botMethod)
                    );
                    if ($botOrder !== null) {
                        \Faoxima\Api\Runtime\Response::fail(409, '⏳ یک فاکتور فعال در ربات تلگرام دارید. پرداخت را از پیام قبلی ادامه دهید.', ['code' => 'gateway_payment_pending', 'order_id' => $botOrder]);
                    }

                    $pendingFreshGateway = (int) \Faoxima\Api\Runtime\Db::fetchScalar(
                        "SELECT COUNT(*) FROM Payment_report
                          WHERE id_user = :u
                            AND payment_Status IN ('Unpaid','pending','waiting')
                            AND Payment_Method = :m
                            AND source = 'miniapp'",
                        [':u' => $this->user['id'], ':m' => $method]
                    );
                    if ($pendingFreshGateway > 0) {
                        \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'));
                    }
                }
            }


            if ($ck !== null) {
                $ck = $this->validateCheckoutForPayment($ck, $method);
            }

            \Faoxima\Core\Database\Db::update('user', 'Processing_value', $amount, 'id', $this->user['id']);
            $this->user['Processing_value'] = $amount;


            $renewUsername = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'renew_username');
            $purchaseUsername = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'purchase_username');
            if ($renewUsername !== null && $renewUsername !== '') {

                $serviceExists = (int) \Faoxima\Api\Runtime\Db::fetchScalar(
                    "SELECT COUNT(*) FROM invoice WHERE username = :u AND id_user = :uid",
                    [':u' => $renewUsername, ':uid' => $this->user['id']]
                );
                if ($serviceExists === 0) {
                    \Faoxima\Api\Runtime\Response::fail(404, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_renew_service_not_found', '❌ سرویسی برای تمدید با این نام کاربری پیدا نشد.'));
                }

                $tow = (string)($this->user['Processing_value_tow'] ?? '');
                $one = (string)($this->user['Processing_value_one'] ?? '');
                $allowedTow = ['getextenduser', 'getextravolumeuser', 'getextratimeuser'];
                if (!in_array($tow, $allowedTow, true) || $one === '' || strpos($one, '%') === false) {
                    \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_renew_steps_incomplete', '❌ مراحل تمدید کامل نشده است. لطفاً تمدید را از ابتدا انجام دهید.'));
                }

            } elseif ($purchaseUsername !== null && $purchaseUsername !== '') {


                $unpaidInvoiceExists = (int) \Faoxima\Api\Runtime\Db::fetchScalar(
                    "SELECT COUNT(*) FROM invoice
                      WHERE username = :u AND id_user = :uid AND Status = 'unpaid'",
                    [':u' => $purchaseUsername, ':uid' => $this->user['id']]
                );
                if ($unpaidInvoiceExists === 0) {
                    \Faoxima\Api\Runtime\Response::fail(404, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_unpaid_invoice_not_found', '❌ فاکتور خرید ناتمامی برای این نام کاربری پیدا نشد.'));
                }
                \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', $purchaseUsername, 'id', $this->user['id']);
                \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', 'getconfigafterpay', 'id', $this->user['id']);
                $this->user['Processing_value_one'] = $purchaseUsername;
                $this->user['Processing_value_tow'] = 'getconfigafterpay';
            } else {
                $chargeCode = \Faoxima\Api\Runtime\Input::string($this->data, 'discount_code');
                if ($chargeCode !== '') {
                    $dv = \Faoxima\Orders\Discounts\DiscountRules::validateSell(\Faoxima\Api\Runtime\Db::pdo(), $chargeCode, 'charge', '', '', '', $this->user);
                    if (empty($dv['ok'])) {
                        \Faoxima\Api\Runtime\Response::fail(422, (string)($dv['reason'] ?? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_invalid_discount_code', '❌ کد تخفیف نامعتبر است.')));
                    }
                    $chargeAmountBefore = (float)$amount;
                    $gatewayAmount = (int) round(\Faoxima\Orders\Discounts\DiscountRules::applyToPrice($dv['row'], $chargeAmountBefore));
                    if ($gatewayAmount <= 0) {
                        \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_discount_zeroes_charge', '❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).'));
                    }
                    $this->chargeBonus = $amount - $gatewayAmount;
                    if ($this->chargeBonus < 0) $this->chargeBonus = 0;
                    $amount = $gatewayAmount;
                    \Faoxima\Core\Database\Db::update('user', 'Processing_value', $amount, 'id', $this->user['id']);
                    $this->user['Processing_value'] = $amount;
                    $this->chargeDiscountCode = $chargeCode;
                    $this->chargeDiscountAmount = $chargeAmountBefore - (float)$gatewayAmount;
                    $this->chargePriceBeforeDiscount = $chargeAmountBefore;
                }
                \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', '', 'id', $this->user['id']);
                \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', '', 'id', $this->user['id']);
                $this->user['Processing_value_one'] = '';
                $this->user['Processing_value_tow'] = '';
            }

            switch ($method) {
                case 'carttocart':
                case 'carttocart_pv':
                    $this->handleCardToCard($amount, $get);
                    return;

                case 'zarinpal':
                    $this->handleGateway('zarinpal', $amount, function ($amt, $orderId) {
                        return \Faoxima\Payments\Gateways\GatewayRuntime::createPayZarinpal($amt, $orderId);
                    }, function ($pay) {
                        return $pay && ($pay['status'] ?? '') === 'success'
                            ? 'https://www.zarinpal.com/pg/StartPay/' . $pay['authority']
                            : null;
                    });
                    return;

                case 'iranpay2':
                    $this->handleTronado($amount);
                    return;

                case 'tonpay':
                    $this->handleTonPay($amount);
                    return;

                case 'rialit':
                    $this->handleRialit($amount);
                    return;

                case 'tetraminator':
                    $this->handleTetraminator($amount);
                    return;

                case 'cubepay':
                    $this->handleCubepay($amount);
                    return;

                case 'blupal':
                    $this->handleBluPal($amount);
                    return;

                case 'variza':
                    $this->handleVariza($amount);
                    return;

                case 'abangateway':
                    $this->handleAbanGateway($amount);
                    return;

                case 'atlaspay':
                    $this->handleAtlasPay($amount);
                    return;

                case 'plisio':
                    $this->handlePlisio($amount);
                    return;

                case 'nowpayment':
                    $this->handleNowPayment($amount);
                    return;

                case 'digitaltron':

                    \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_digitaltron_use_hashchecker', '⚠️ این روش ارزی در مینی‌اپ از فلوی هش‌چکر استفاده می‌کند. لطفاً صفحه را بازنشانی کنید و مجدداً انتخاب کنید.'));
                    return;

                case 'paymentnotverify':
                    $orderId = bin2hex(random_bytes(5));
                    $this->insertPaymentReport('paymentnotverify', $amount, $orderId);
                    \Faoxima\Api\Runtime\Response::ok([
                        'kind'     => 'manual',
                        'order_id' => $orderId,
                        'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_manual_request_registered', '✅ درخواست شما ثبت شد. ادمین پس از بررسی، حساب شما را شارژ می‌کند.'),
                    ]);
                    return;

                case 'startelegrams':
                    $orderId = bin2hex(random_bytes(5));
                    $this->insertPaymentReport('startelegrams', $amount, $orderId);
                    \Faoxima\Api\Runtime\Response::ok([
                        'kind'     => 'manual',
                        'order_id' => $orderId,
                        'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_stars_complete_in_bot', '⭐ پرداخت با Telegram Stars از داخل ربات قابل تکمیل است.'),
                    ]);
                    return;
            }

            \Faoxima\Api\Runtime\Response::badRequest('Unknown payment method: ' . $method);
        } finally {
            $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(:n)');
            $releaseStmt->execute([':n' => $lockName]);
        }
    }


    private function failVerifyPending(array $active): void
    {
        \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_pending_message(), [
            'code' => 'verify_pending',
            'pending' => \Faoxima\Payments\Receipts\ReceiptOrders::pendingSummary($active),
        ]);
    }

    private function resumeVerifyPayment(string $orderId): void
    {
        $active = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_active_payment') ? \Faoxima\Payments\Receipts\ReceiptOrders::activePayment(\Faoxima\Api\Runtime\Db::pdo(), (string) $this->user['id']) : null;
        if ($active === null || (string) $active['id_order'] !== $orderId) {
            \Faoxima\Api\Runtime\Response::fail(409, 'این پرداخت دیگر فعال نیست. اکنون می‌توانید پرداخت جدید ایجاد کنید.', ['code' => 'verify_not_active']);
        }
        if (empty($active['resumable'])) {
            $this->failVerifyPending($active);
        }
        $self = $this;
        $get = function (string $name) use ($self): string {
            return $self->paySetting($name);
        };
        $this->handleCardToCard((int) $active['principal_toman'], $get, $active);
    }

    private function purgeCardAndGuard(?string $keepInvoiceKey): void
    {
        $pdo = \Faoxima\Api\Runtime\Db::pdo();
        $userId = (string) $this->user['id'];
        $activeReport = $keepInvoiceKey !== null ? ReceiptOrders::findActiveCardReport($pdo, $userId, $keepInvoiceKey) : null;
        if ($activeReport === null) {
            $this->purgeStaleCarttocart((int) $userId, $keepInvoiceKey);
            $this->purgeAbandonedCarttocart((int) $userId, $keepInvoiceKey);
            $activeReport = ReceiptOrders::findActiveCardReport($pdo, $userId);
        }
        if ($activeReport !== null) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'), [
                'code' => 'card_payment_pending',
                'order_id' => (string) $activeReport['id_order'],
            ]);
        }
    }

    private function purgeStaleCarttocart(int $userId, ?string $keepInvoiceKey = null): void
    {
        try {
            $rows = \Faoxima\Api\Runtime\Db::fetchAll(
                "SELECT id_order, time
                   FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status = 'Unpaid'
                    AND (Payment_Method = 'cart to cart' OR Payment_Method = 'carttocart_pv')
                    AND source = 'miniapp'",
                [':u' => $userId]
            );
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('purgeStaleCarttocart fetch failed', ['err' => $e->getMessage()]);
            return;
        }
        if (!is_array($rows) || empty($rows)) return;

        $cutoff = time() - (self::STALE_UNPAID_MINUTES * 60);
        $opts = ['source' => 'miniapp'];
        if ($keepInvoiceKey !== null) {
            $opts['preserve_invoice_key'] = $keepInvoiceKey;
        }
        $cancelled = 0;
        foreach ($rows as $r) {
            $ts = $this->parseLegacyTime((string)($r['time'] ?? ''));
            if ($ts === null || $ts <= $cutoff) {
                $res = \Faoxima\Payments\Receipts\Cancellation::cancelAbandonedCardPayment(\Faoxima\Api\Runtime\Db::pdo(), (string)$r['id_order'], (string)$userId, 'stale_unpaid', $opts);
                if (!empty($res['ok'])) $cancelled++;
            }
        }
        if ($cancelled > 0) {
            \Faoxima\Api\Runtime\Logger::debug('Cancelled stale carttocart Unpaid rows', [
                'user_id' => $userId,
                'count'   => $cancelled,
            ]);
        }
    }

    private function purgeAbandonedCarttocart(int $userId, ?string $keepInvoiceKey = null): void
    {
        $opts = ['source' => 'miniapp'];
        if ($keepInvoiceKey !== null) {
            $opts['preserve_invoice_key'] = $keepInvoiceKey;
        }
        \Faoxima\Payments\Receipts\Cancellation::cancelAbandonedCardPaymentsForUser(\Faoxima\Api\Runtime\Db::pdo(), (string)$userId, 'superseded', $opts);
    }

    private function purgeStaleGatewayOrders(int $userId, string $method): void
    {
        if ($method === 'rialit' || $method === 'tetraminator') {
            return;
        }
        try {
            $rows = \Faoxima\Api\Runtime\Db::fetchAll(
                "SELECT id_order, time
                   FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND Payment_Method = :m
                    AND source = 'miniapp'",
                [':u' => $userId, ':m' => $method]
            );
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('purgeStaleGatewayOrders fetch failed', ['err' => $e->getMessage()]);
            return;
        }
        if (!is_array($rows) || empty($rows)) return;

        $cutoff = time() - ($this->methodStaleMinutes($method) * 60);
        $stale = [];
        foreach ($rows as $r) {
            $ts = $this->parseLegacyTime((string)($r['time'] ?? ''));
            if ($ts === null || $ts <= $cutoff) {
                $stale[] = (string)$r['id_order'];
            }
        }
        if ($method === 'tonpay' && method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomPurgeProtected')) {
            $stale = array_values(array_filter($stale, static function ($staleOrderId) {
                return !\Faoxima\Payments\Gateways\Tonpay\Settlement::purgeProtected(\Faoxima\Api\Runtime\Db::pdo(), $staleOrderId);
            }));
        }
        if ($method === 'atlaspay' && method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayCustomPurgeProtected')) {
            $stale = array_values(array_filter($stale, static function ($staleOrderId) {
                return !\Faoxima\Payments\Gateways\Atlaspay\Settlement::purgeProtected(\Faoxima\Api\Runtime\Db::pdo(), $staleOrderId);
            }));
        }
        if (empty($stale)) return;

        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $placeholders = implode(',', array_fill(0, count($stale), '?'));
            $sql = "UPDATE Payment_report
                       SET payment_Status = 'expire'
                     WHERE id_user = ?
                       AND payment_Status IN ('Unpaid','pending','waiting')
                       AND Payment_Method = ?
                       AND source = 'miniapp'
                       AND id_order IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$userId, $method], $stale);
            $stmt->execute($params);
            \Faoxima\Api\Runtime\Logger::debug('Marked stale gateway Payment_report rows as expired', [
                'user_id' => $userId,
                'method'  => $method,
                'count'   => count($stale),
            ]);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('purgeStaleGatewayOrders update failed', ['err' => $e->getMessage()]);
        }
    }


    private function methodStaleMinutes(string $method): int
    {
        if ($method === 'cubepay') {
            return 60;
        }
        if ($method === 'variza') {
            return 60;
        }
        if ($method === 'abangateway') {
            // Its invoices stay open for 30 minutes by default.
            return 30;
        }
        if ($method === 'atlaspay') {
            return 20;
        }
        if ($method === 'tonpay') {
            return 1440;
        }
        if ($method === 'rialit') {
            return 1440;
        }
        if ($method === 'tetraminator') {
            return 1440;
        }
        return self::STALE_UNPAID_MINUTES;
    }


    private function parseLegacyTime(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (ctype_digit($raw)) return (int)$raw;


        $raw = strtr($raw, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);


        $ts = strtotime(str_replace('/', '-', $raw));
        return $ts === false ? null : $ts;
    }

    private function methodLimits(string $method, string $agent, callable $get): array
    {

        $perMethod = [
            'carttocart'    => ['minbalancecart',          'maxbalancecart'],
            'carttocart_pv' => ['minbalancecart',          'maxbalancecart'],
            'zarinpal'      => ['minbalancezarinpal',      'maxbalancezarinpal'],
            'plisio'        => ['minbalanceplisio',        'maxbalanceplisio'],
            'nowpayment'    => ['minbalancenowpayment',    'maxbalancenowpayment'],
            'digitaltron'   => ['minbalancedigitaltron',   'maxbalancedigitaltron'],
            'iranpay2'      => ['minbalanceiranpay2',      'maxbalanceiranpay2'],
            'iranpay3'      => ['minbalanceiranpay',       'maxbalanceiranpay'],
            'tonpay'        => ['minbalancetonpay',        'maxbalancetonpay'],
            'rialit'        => ['minbalancerialit',        'maxbalancerialit'],
            'tetraminator'  => ['minbalancetetraminator',  'maxbalancetetraminator'],
            'cubepay'       => ['minbalancecubepay',       'maxbalancecubepay'],
            'blupal'        => ['minbalanceblupal',        'maxbalanceblupal'],
            'variza'        => ['minbalancevariza',        'maxbalancevariza'],
            'abangateway'   => ['minbalanceabangateway',   'maxbalanceabangateway'],
            'atlaspay'      => ['minbalanceatlaspay',      'maxbalanceatlaspay'],
        ];

        $minMethod = 0;
        $maxMethod = 0;
        if (isset($perMethod[$method])) {
            [$minK, $maxK] = $perMethod[$method];
            $minMethod = (int) ($get($minK) ?: 0);
            $maxMethod = (int) ($get($maxK) ?: 0);
        }


        $minJson = $get('minbalance');
        $maxJson = $get('maxbalance');
        $min = $minMethod > 0 ? $minMethod : (int) $this->jsonAgentValue($minJson, $agent, 1000);
        $max = $maxMethod > 0 ? $maxMethod : (int) $this->jsonAgentValue($maxJson, $agent, 100000000);
        if ($method === 'rialit') {
            $min = max(50000, (int) $min);
            $max = (int) $max > 0 ? min(10000000, (int) $max) : 10000000;
        }
        if ($method === 'tetraminator') {
            [$min, $max] = \Faoxima\Payments\Gateways\Tetraminator\Client::amountRange(\Faoxima\Api\Runtime\Db::pdo());
        }
        return [$min, $max];
    }


    private function handleCardToCard(int $amount, callable $get, ?array $resume = null): void
    {
        $s = is_array($this->setting) ? $this->setting : [];
        $cardVerifyStatus = (string)($s['card_verify_status'] ?? 'offcardverify');
        $cardVerifyActive = $cardVerifyStatus === 'oncardverify';

        if ($cardVerifyActive) {
            $minAmount = (int)($s['card_verify_min_amount'] ?? 0);
            if ($minAmount > 0 && $amount < $minAmount) {
                $cardVerifyActive = false;
            }
        }

        if ($cardVerifyActive && (int)($this->user['card_verify_bypass'] ?? 0) === 1) {
            $cardVerifyActive = false;
        }

        if ($cardVerifyActive) {
            $adminRows = \Faoxima\Api\Runtime\Db::fetchAll('SELECT id_admin FROM admin');
            $adminIds  = array_map('intval', array_column($adminRows ?: [], 'id_admin'));
            if (in_array((int)$this->user['id'], $adminIds, true)) {
                $cardVerifyActive = false;
            }
        }

        $sbvInvoiceKey = ($this->user['Processing_value_tow'] ?? '') . '|' . ($this->user['Processing_value_one'] ?? '');
        if ($resume !== null) {
            $sbvReuse = $resume;
        } else {
            $sbvReuse = is_callable([\Faoxima\Payments\Receipts\ReceiptOrders::class, 'findReusable']) ? \Faoxima\Payments\Receipts\ReceiptOrders::findReusable(\Faoxima\Api\Runtime\Db::pdo(), (string) $this->user['id'], $sbvInvoiceKey, $amount) : null;
            if ($sbvReuse === null && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_active_payment')) {
                $sbvActive = \Faoxima\Payments\Receipts\ReceiptOrders::activePayment(\Faoxima\Api\Runtime\Db::pdo(), (string) $this->user['id']);
                if ($sbvActive !== null) {
                    $this->failVerifyPending($sbvActive);
                }
            }
            if ($sbvReuse === null && $this->sbvDeferPurge) {
                $operation = (string) ($this->user['Processing_value_tow'] ?? '');
                $this->purgeCardAndGuard(in_array($operation, \Faoxima\Orders\Checkout\Checkout::operations(), true) ? $sbvInvoiceKey : null);
            }
        }

        $card  = null;
        $card2 = null;
        $directRows = [];
        try {
            $userId = (string)$this->user['id'];
            $pdo    = \Faoxima\Api\Runtime\Db::pdo();
            $mRow   = \Faoxima\Api\Runtime\Db::fetchOne("SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
            $dMode  = is_array($mRow) ? ($mRow['ValuePay'] ?? 'random') : 'random';
            $accessSql = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = ?)";

            if ($dMode == 'direct') {
                $stmt = $pdo->prepare("SELECT cardnumber, namecard FROM card_number cn WHERE {$accessSql} ORDER BY cn.created_at ASC LIMIT 50");
                $stmt->execute([$userId, $userId, $userId]);
                $directRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $card  = $directRows[0] ?? null;
                $card2 = $directRows[1] ?? null;
            } else {
                $cycleKey = "card_cycle_{$userId}";
                $seenRow  = \Faoxima\Api\Runtime\Db::fetchOne("SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1", [$cycleKey]);
                $seenStr  = is_array($seenRow) ? ($seenRow['ValuePay'] ?? '') : '';
                $seen     = array_values(array_filter(array_map('intval', explode(',', $seenStr))));
                $excl     = !empty($seen) ? "AND cn.id NOT IN (" . implode(',', $seen) . ")" : "";

                $stmt = $pdo->prepare("SELECT cardnumber, namecard, id FROM card_number cn WHERE {$accessSql} {$excl} ORDER BY RAND() LIMIT 1");
                $stmt->execute([$userId, $userId, $userId]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if (!$card) {
                    $stmt = $pdo->prepare("SELECT cardnumber, namecard, id FROM card_number cn WHERE {$accessSql} ORDER BY RAND() LIMIT 1");
                    $stmt->execute([$userId, $userId, $userId]);
                    $card = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    $seen = [];
                }

                if ($card) {
                    $newSeen  = implode(',', array_unique(array_merge($seen, [intval($card['id'])])));
                    $updStmt  = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?");
                    $updStmt->execute([$newSeen, $cycleKey]);
                    if ($updStmt->rowCount() === 0) {
                        $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)")->execute([$cycleKey, $newSeen]);
                    }
                }
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('card_number fetch failed', ['err' => $e->getMessage()]);
        }

        if (!is_array($card) || empty($card['cardnumber']) || empty($card['namecard'])) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_no_active_card', '❌ کارت بانکی فعالی برای کارت‌به‌کارت تنظیم نشده است.'));
        }

        $cardsList = [];
        if (($dMode ?? '') === 'direct') {
            foreach ($directRows as $row) {
                if (!empty($row['cardnumber']) && !empty($row['namecard'])) {
                    $cardsList[] = [
                        'number' => (string)$row['cardnumber'],
                        'last4'  => substr((string)$row['cardnumber'], -4),
                        'name'   => (string)$row['namecard'],
                    ];
                }
            }
        }
        if (empty($cardsList)) {
            $cardsList[] = [
                'number' => (string)$card['cardnumber'],
                'last4'  => substr((string)$card['cardnumber'], -4),
                'name'   => (string)$card['namecard'],
            ];
        }

        $rialAmount = $amount * 10;
        if ($sbvReuse !== null) {
            $orderId = (string) $sbvReuse['id_order'];
            if ($resume === null && $this->checkoutId !== null) {
                \Faoxima\Orders\Checkout\Checkout::attachPayment(\Faoxima\Api\Runtime\Db::pdo(), $this->checkoutId, $orderId, 'cart to cart');
            }
        } else {
            $orderId = bin2hex(random_bytes(5));
            if (is_callable([\Faoxima\Payments\Receipts\Settings::class, 'enabled']) && \Faoxima\Payments\Receipts\Settings::enabled(\Faoxima\Api\Runtime\Db::pdo())) {
                $sbvPrep = \Faoxima\Payments\Receipts\ReceiptOrders::prepare(\Faoxima\Api\Runtime\Db::pdo(), $orderId, (string) $this->user['id'], $amount);
                if (empty($sbvPrep['ok'])) {
                    if (($sbvPrep['reason'] ?? '') === 'active_exists' && is_array($sbvPrep['active'] ?? null)) {
                        $this->failVerifyPending($sbvPrep['active']);
                    }
                    if (($sbvPrep['reason'] ?? '') === 'busy') {
                        \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'));
                    }
                    if (($sbvPrep['reason'] ?? '') === 'exhausted') {
                        \Faoxima\Api\Runtime\Response::fail(409, '❌ در حال حاضر امکان ساخت مبلغ یکتا برای این مبلغ وجود ندارد. لطفاً چند دقیقه دیگر دوباره تلاش کنید یا روش پرداخت دیگری انتخاب کنید.', ['code' => 'verify_amount_exhausted']);
                    }
                    \Faoxima\Api\Runtime\Response::serverError(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
                }
                $this->sbvPreparedOrder = $orderId;
            }
            $this->insertPaymentReport('cart to cart', $amount, $orderId);
        }
        $sbvView = method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_public_view') ? \Faoxima\Payments\Receipts\ReceiptOrders::publicView(\Faoxima\Api\Runtime\Db::pdo(), $orderId) : null;
        if ($sbvView !== null) {
            $rialAmount = (int) $sbvView['payable_rial'];
        }
        $sbvMessage = $sbvView !== null ? '🔐 دقیقاً مبلغ قابل پرداخت را واریز کنید؛ پرداخت به‌صورت خودکار بررسی و تأیید می‌شود.' : null;

        if ($cardVerifyActive) {
            $verifiedCards = [];
            try {
                $vcRows = \Faoxima\Api\Runtime\Db::fetchAll(
                    'SELECT last4 FROM verified_cards WHERE user_id = ? ORDER BY id DESC LIMIT 5',
                    [(string)$this->user['id']]
                );
                if (is_array($vcRows)) {
                    $verifiedCards = array_values(array_column($vcRows, 'last4'));
                }
            } catch (Throwable $e) {
                \Faoxima\Api\Runtime\Logger::userFacing('verified_cards fetch failed', ['err' => $e->getMessage()]);
            }

            \Faoxima\Api\Runtime\Response::ok([
                'kind'                  => 'carttocart',
                'order_id'              => $orderId,
                'card_verify_required'  => true,
                'verified_cards'        => $verifiedCards,
                'card_number'           => (string)$card['cardnumber'],
                'card_number_last4'     => substr((string)$card['cardnumber'], -4),
                'name_card'             => (string)$card['namecard'],
                'card_number_2'         => is_array($card2) ? (string)$card2['cardnumber'] : null,
                'card_number_2_last4'   => is_array($card2) ? substr((string)($card2['cardnumber'] ?? ''), -4) : null,
                'card_number_2_present' => is_array($card2) && !empty($card2['cardnumber']),
                'name_card_2'           => is_array($card2) ? (string)$card2['namecard'] : null,
                'cards'                 => $cardsList,
                'display_mode'          => $dMode ?? 'random',
                'amount'                => $amount,
                'amount_rial'           => $rialAmount,
                'auto_confirm'          => $sbvView !== null,
                'verify'                => $sbvView,
                'message'               => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_card_verify_active', '🔒 احراز هویت کارت‌به‌کارت فعال است.'),
            ]);
        }

        \Faoxima\Api\Runtime\Response::ok([
            'kind'                  => 'carttocart',
            'order_id'              => $orderId,
            'card_number'           => (string)$card['cardnumber'],
            'card_number_last4'     => substr((string)$card['cardnumber'], -4),
            'name_card'             => (string)$card['namecard'],
            'card_number_2'         => is_array($card2) ? (string)$card2['cardnumber'] : null,
            'card_number_2_last4'   => is_array($card2) ? substr((string)($card2['cardnumber'] ?? ''), -4) : null,
            'card_number_2_present' => is_array($card2) && !empty($card2['cardnumber']),
            'name_card_2'           => is_array($card2) ? (string)$card2['namecard'] : null,
            'cards'                 => $cardsList,
            'display_mode'          => $dMode ?? 'random',
            'amount'                => $amount,
            'amount_rial'           => $rialAmount,
            'card_verify_required'  => false,
            'auto_confirm'          => $sbvView !== null,
            'verify'                => $sbvView,
            'message'               => $sbvMessage ?? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_deposit_and_upload_receipt', '💳 مبلغ را به کارت زیر واریز کنید و سپس رسید را آپلود نمایید.'),
        ]);
    }


    private function handleGateway(string $method, int $amount, callable $createFn, callable $extractUrl): void
    {
        $orderId = bin2hex(random_bytes(5));

        if (!is_callable($createFn)) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_function_missing', '❌ تابع گیت‌وی در سرور موجود نیست: {method}'), ['method' => $method]));
        }

        try {
            $pay = $createFn($amount, $orderId);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('Gateway threw: ' . $method, ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_connection_error', '❌ خطا در ارتباط با درگاه {method}'), ['method' => $method]));
        }

        $url = $extractUrl($pay);
        if (!$url) {
            \Faoxima\Api\Runtime\Logger::userFacing('Gateway did not return a URL: ' . $method, ['raw' => $pay]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        $this->insertPaymentReport($method, $amount, $orderId);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => $url,
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_invoice_created_click_link', '✅ فاکتور ساخته شد. برای پرداخت روی لینک کلیک کنید.'),
        ]);
    }


    private function handleTronado(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\Tronado\Runtime::class, 'trnado')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tronado_function_missing', '❌ تابع درگاه ترونادو روی این سرور موجود نیست.'));
        }

        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceiranpay2', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceiranpay2', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('iranpay2', $amount, $orderId);

        try {
            $pay = \Faoxima\Payments\Gateways\Tronado\Runtime::trnado($orderId, $amount);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('trnado() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $token = is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'extractPaymentToken']) ? \Faoxima\Payments\Gateways\Tronado\Client::extractPaymentToken($pay) : (string)($pay['Token'] ?? '');
        $paymentUrl = is_array($pay) ? trim((string)($pay['FullPaymentUrl'] ?? '')) : '';

        if ($token === '' || $paymentUrl === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('trnado() returned bad response', ['raw' => $errMsg]);
            $this->rejectPaymentReport($orderId, $errMsg);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', $token, 'id_order', $orderId);
        if (is_callable([\Faoxima\Payments\Gateways\Tronado\Settlement::class, 'store'])) {
            \Faoxima\Payments\Gateways\Tronado\Settlement::store(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $pay);
        }
        \Faoxima\Core\Database\Db::update('Payment_report', 'tronado_payment_url', $paymentUrl, 'id_order', $orderId);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => $paymentUrl,
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleTonPay(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'tonpayCreateInvoice')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tonpay_function_missing', '❌ تابع درگاه تون‌پی روی این سرور موجود نیست.'));
        }

        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalancetonpay', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancetonpay', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        if (\Faoxima\Payments\Gateways\Tonpay\Client::paymentMode() === 'custom') {
            $this->handleTonPayCustom($amount);
            return;
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('tonpay', $amount, $orderId);

        try {
            $pay = \Faoxima\Payments\Gateways\GatewayRuntime::tonpayCreateInvoice($orderId, $amount, (int)$this->user['id']);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('tonpayCreateInvoice() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $invoiceId = is_array($pay) ? trim((string)($pay['invoice_id'] ?? '')) : '';
        $invoiceUrl = is_array($pay) ? trim((string)($pay['invoice_url'] ?? '')) : '';

        if ($invoiceId === '' || $invoiceUrl === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('tonpayCreateInvoice() returned bad response', ['raw' => $errMsg]);
            $this->rejectPaymentReport($orderId, $errMsg);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        \Faoxima\Core\Database\Db::update('Payment_report', 'tonpay_invoice_id', $invoiceId, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'tonpay_invoice_url', $invoiceUrl, 'id_order', $orderId);
        \Faoxima\Payments\Gateways\Tonpay\Client::storeWebInvoiceUrl(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $pay['web_invoice_url'] ?? '');

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => \Faoxima\Payments\Gateways\Tonpay\Client::selectPaymentUrl($invoiceUrl, $pay['web_invoice_url'] ?? '', $orderId),
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleTonPayCustom(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomCreateInvoice')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tonpay_function_missing', '❌ تابع درگاه تون‌پی روی این سرور موجود نیست.'));
        }

        $orderId = bin2hex(random_bytes(5));
        $this->insertPaymentReport('tonpay', $amount, $orderId);

        try {
            $invoice = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCreateInvoice($orderId, $amount, (int)$this->user['id']);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('tonpayCustomCreateInvoice() threw', ['err' => $e->getMessage()]);
            \Faoxima\Core\Database\Db::update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        if (!is_array($invoice) || (isset($invoice['success']) && $invoice['success'] === false)) {
            $errorCode = is_array($invoice) ? (string)($invoice['error_code'] ?? '') : '';
            \Faoxima\Api\Runtime\Logger::userFacing('tonpayCustomCreateInvoice() returned bad response', ['code' => $errorCode]);
            \Faoxima\Core\Database\Db::update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', 'tonpay_custom_create_failed:' . $errorCode, 'id_order', $orderId);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        if (!\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::storeInvoice(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $invoice)) {
            $recovery = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::recoverUnstoredInvoice(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $invoice);
            if (empty($recovery['saved'])) {
                $invoiceRef = (string) ($recovery['invoice_id'] ?? ($invoice['invoice_id'] ?? ''));
                $remoteState = (string) ($recovery['remote_state'] ?? 'unknown');
                \Faoxima\Api\Runtime\Logger::userFacing('tonpayCustomStoreInvoice() unresolved', [
                    'order_id' => $orderId,
                    'invoice_id' => $invoiceRef,
                    'remote_state' => $remoteState,
                ]);
                \Faoxima\Core\Database\Db::update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
                \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', 'tonpay_custom_store_unresolved:' . $invoiceRef . ':' . $remoteState, 'id_order', $orderId);
                \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
            }
        }

        $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport(\Faoxima\Api\Runtime\Db::pdo(), $orderId);
        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'tonpay_custom',
            'order_id' => $orderId,
            'view'     => $report === null ? null : \Faoxima\Payments\Gateways\Tonpay\Settlement::publicView($report),
        ]);
    }


    private function handleRialit(int $amount): void
    {
        if (!is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'createPayment']) || !is_callable([\Faoxima\Payments\Gateways\Rialit\PaymentOrders::class, 'findPendingForUser'])) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rialit_function_missing', '❌ تابع درگاه ریالیت روی این سرور موجود نیست.'));
        }

        [$min, $max] = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'amountRange']) ? \Faoxima\Payments\Gateways\Rialit\Client::amountRange(\Faoxima\Api\Runtime\Db::pdo()) : [50000, 10000000];
        if ($amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $invoice = (string) ($this->user['Processing_value_tow'] ?? '') . '|' . (string) ($this->user['Processing_value_one'] ?? '');
        $existing = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::findPendingForUser(\Faoxima\Api\Runtime\Db::pdo(), (string) $this->user['id'], $invoice, $amount, [
            'checkout_id' => $this->checkoutId ?? '',
            'charge_bonus' => $this->chargeBonus,
            'discount_code' => $this->chargeDiscountCode,
            'discount_amount' => $this->chargeDiscountAmount,
            'price_before_discount' => $this->chargePriceBeforeDiscount,
        ]);
        if ($existing !== null) {
            $orderId = (string) ($existing['id_order'] ?? '');
            $token = trim((string) ($existing['rialit_token'] ?? ''));
            if ($orderId !== '' && $token !== '') {
                $linkAvailable = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkAvailable($existing);
                \Faoxima\Api\Runtime\Response::ok([
                    'kind' => 'url',
                    'url' => $linkAvailable ? \Faoxima\Payments\Gateways\Rialit\Client::paymentUrl($token) : '',
                    'order_id' => $orderId,
                    'expires_at' => \Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkExpiresAt($existing),
                    'message' => $linkAvailable
                        ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rialit_resume', '✅ همان فاکتور ریالیت شما هنوز باز است؛ برای تکمیل پرداخت روی لینک زیر بزنید.')
                        : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rialit_awaiting_result', 'مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه'),
                ]);
                return;
            }
        } else {
            if (!\Faoxima\Payments\Gateways\Rialit\Client::enabled(\Faoxima\Api\Runtime\Db::pdo())) {
                \Faoxima\Api\Runtime\Response::fail(409, '❌ درگاه ریالیت در حال حاضر غیرفعال است.');
            }
            $orderId = bin2hex(random_bytes(5));
            $this->insertPaymentReport('rialit', $amount, $orderId);
        }

        if ($orderId === '') {
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }
        if (!\Faoxima\Payments\Gateways\Rialit\PaymentOrders::markCreated(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $amount, (int) $this->user['id'])) {
            \Faoxima\Api\Runtime\Response::serverError(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        try {
            $pay = \Faoxima\Payments\Gateways\Rialit\Client::createPayment(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $amount, (int) $this->user['id']);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('rialitCreatePayment() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rialit_connection_error', '❌ ساخت پرداخت ریالیت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        if (!is_array($pay) || (isset($pay['success']) && $pay['success'] === false)) {
            $errorCode = is_array($pay) ? (string) ($pay['error_code'] ?? '') : '';
            \Faoxima\Api\Runtime\Logger::userFacing('rialitCreatePayment() returned bad response', ['code' => $errorCode]);
            \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', 'rialit_create_failed:' . $errorCode, 'id_order', $orderId);
            $message = ($errorCode === 'AUTH_FAILED' || $errorCode === 'MISSING_API_KEY')
                ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rialit_config_error', '❌ تنظیمات درگاه ریالیت کامل نیست؛ با پشتیبانی تماس بگیرید.')
                : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rialit_link_creation_failed', '❌ ساخت لینک پرداخت ریالیت ناموفق بود. لطفاً دوباره تلاش کنید.');
            \Faoxima\Api\Runtime\Response::fail(502, $message);
        }

        if (!\Faoxima\Payments\Gateways\Rialit\PaymentOrders::storePayment(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $pay)) {
            \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', 'rialit_store_failed', 'id_order', $orderId);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        if (strtolower((string) ($pay['status'] ?? '')) === 'paid' && method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitVerifyPayment')) {
            \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitVerifyPayment($orderId, ['source' => 'init']);
        }

        $storedPayment = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport(\Faoxima\Api\Runtime\Db::pdo(), $orderId);
        if ($storedPayment === null) {
            \Faoxima\Api\Runtime\Response::serverError(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }
        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => \Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkAvailable($storedPayment) ? \Faoxima\Payments\Gateways\Rialit\Client::paymentUrl((string) $pay['token']) : '',
            'order_id' => $orderId,
            'expires_at' => \Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkExpiresAt($storedPayment),
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rialit_click_link', '✅ فاکتور ریالیت ساخته شد. برای پرداخت روی لینک زیر بزنید.'),
        ]);
    }


    private function handleTetraminator(int $amount): void
    {
        $pdo = \Faoxima\Api\Runtime\Db::pdo();
        [$min, $max] = \Faoxima\Payments\Gateways\Tetraminator\Client::amountRange($pdo);
        if ($amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $invoice = (string) ($this->user['Processing_value_tow'] ?? '') . '|' . (string) ($this->user['Processing_value_one'] ?? '');
        $existing = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::findPendingForUser($pdo, (string) $this->user['id'], $invoice, $amount, [
            'checkout_id' => $this->checkoutId ?? '',
            'charge_bonus' => $this->chargeBonus,
            'discount_code' => $this->chargeDiscountCode,
            'discount_amount' => $this->chargeDiscountAmount,
            'price_before_discount' => $this->chargePriceBeforeDiscount,
        ]);
        if ($existing !== null && \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::linkAvailable($existing)) {
            \Faoxima\Api\Runtime\Response::ok([
                'kind' => 'url',
                'url' => \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::paymentUrl($existing),
                'order_id' => (string) $existing['id_order'],
                'expires_at' => \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::linkExpiresAt($existing),
                'message' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tetraminator_resume', '✅ همان فاکتور تترامیناتور شما هنوز باز است؛ برای تکمیل پرداخت روی لینک زیر بزنید.'),
            ]);
            return;
        }
        if (!\Faoxima\Payments\Gateways\Tetraminator\Client::enabled($pdo)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tetraminator_disabled', '❌ درگاه تترامیناتور در حال حاضر غیرفعال است.'));
        }

        $orderId = bin2hex(random_bytes(5));
        $this->insertPaymentReport('tetraminator', $amount, $orderId);

        try {
            $created = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::openInvoice($pdo, $orderId, $amount, (int) $this->user['id']);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('tetraminatorOpenInvoice() threw', ['err' => $e->getMessage()]);
            \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', 'tetraminator_create_failed:exception', 'id_order', $orderId);
            \Faoxima\Payments\Settlement\Confirmation::markUnsettledTerminal($pdo, $orderId, 'expire');
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tetraminator_connection_error', '❌ ساخت پرداخت تترامیناتور ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        if (!is_array($created) || empty($created['success'])) {
            $errorCode = is_array($created) ? (string) ($created['error_code'] ?? '') : '';
            \Faoxima\Api\Runtime\Logger::userFacing('tetraminatorOpenInvoice() returned bad response', ['code' => $errorCode]);
            \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', 'tetraminator_create_failed:' . $errorCode, 'id_order', $orderId);
            \Faoxima\Payments\Settlement\Confirmation::markUnsettledTerminal($pdo, $orderId, 'expire');
            $message = in_array($errorCode, ['AUTH_FAILED', 'FORBIDDEN', 'MISSING_API_KEY', 'INVALID_CALLBACK'], true)
                ? \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tetraminator_config_error', '❌ تنظیمات درگاه تترامیناتور کامل نیست؛ با پشتیبانی تماس بگیرید.')
                : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tetraminator_link_creation_failed', '❌ ساخت لینک پرداخت تترامیناتور ناموفق بود. لطفاً دوباره تلاش کنید.');
            \Faoxima\Api\Runtime\Response::fail(502, $message);
        }

        $stored = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($pdo, $orderId);
        if ($stored === null) {
            \Faoxima\Api\Runtime\Response::serverError(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }
        \Faoxima\Api\Runtime\Response::ok([
            'kind'       => 'url',
            'url'        => \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::linkAvailable($stored) ? \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::paymentUrl($stored) : '',
            'order_id'   => $orderId,
            'expires_at' => \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::linkExpiresAt($stored),
            'message'    => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_tetraminator_click_link', '✅ فاکتور تترامیناتور ساخته شد. برای پرداخت روی لینک زیر بزنید.'),
        ]);
    }


    private function handleAtlasPayCustom(int $amount): void
    {
        if (!is_callable([\Faoxima\Payments\Gateways\Atlaspay\CustomClient::class, 'createOrder'])) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_atlaspay_function_missing', '❌ تابع درگاه اطلس‌پی روی این سرور موجود نیست.'));
        }

        $orderId = bin2hex(random_bytes(5));
        $this->insertPaymentReport('atlaspay', $amount, $orderId);

        try {
            $order = \Faoxima\Payments\Gateways\Atlaspay\CustomClient::createOrder(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $amount, (int)$this->user['id']);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('atlaspayCustomCreateOrder() threw', ['err' => $e->getMessage()]);
            \Faoxima\Core\Database\Db::update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        if (!is_array($order) || (isset($order['success']) && $order['success'] === false)) {
            $errorCode = is_array($order) ? (string)($order['error_code'] ?? '') : '';
            \Faoxima\Api\Runtime\Logger::userFacing('atlaspayCustomCreateOrder() returned bad response', ['code' => $errorCode]);
            \Faoxima\Core\Database\Db::update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', 'atlaspay_custom_create_failed:' . $errorCode, 'id_order', $orderId);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        if (!\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::storeOrder(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $order)) {
            \Faoxima\Payments\Gateways\Atlaspay\Client::cancelOrder((string)$order['orderId']);
            \Faoxima\Core\Database\Db::update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        $report = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport(\Faoxima\Api\Runtime\Db::pdo(), $orderId);
        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'atlaspay_custom',
            'order_id' => $orderId,
            'view'     => $report === null ? null : \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::publicView($report),
        ]);
    }


    private function handleAtlasPay(int $amount): void
    {
        if (!is_callable([\Faoxima\Payments\Gateways\Atlaspay\Client::class, 'createOrder'])) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_atlaspay_function_missing', '❌ تابع درگاه اطلس‌پی روی این سرور موجود نیست.'));
        }

        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceatlaspay', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceatlaspay', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        if (\Faoxima\Payments\Gateways\Atlaspay\CustomClient::paymentMode() === 'custom') {
            $this->handleAtlasPayCustom($amount);
            return;
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('atlaspay', $amount, $orderId);

        try {
            $pay = \Faoxima\Payments\Gateways\Atlaspay\Client::createOrder(\Faoxima\Api\Runtime\Db::pdo(), $orderId, $amount, (int)$this->user['id']);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('atlaspayCreateOrder() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $atlaspayOrderId = is_array($pay) ? trim((string)($pay['orderId'] ?? '')) : '';
        $paymentUrl = is_array($pay) ? trim((string)($pay['customerStartLink'] ?? '')) : '';
        $receiptUrl = is_array($pay) && method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayReceiptBotUrl')
            ? \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayReceiptBotUrl($pay['receiptBotLink'] ?? '')
            : '';
        $trackingCode = is_array($pay) ? trim((string)($pay['trackingCode'] ?? '')) : '';

        if ($atlaspayOrderId === '' || $paymentUrl === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('atlaspayCreateOrder() returned bad response', ['raw' => $errMsg]);
            $this->rejectPaymentReport($orderId, $errMsg);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        $totalAmountToman = is_array($pay) ? (int)($pay['totalAmountToman'] ?? $amount) : $amount;

        \Faoxima\Core\Database\Db::update('Payment_report', 'atlaspay_order_id', $atlaspayOrderId, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'atlaspay_tracking_code', $trackingCode, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'atlaspay_payment_url', $paymentUrl, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'atlaspay_receipt_url', $receiptUrl, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'atlaspay_total_amount', $totalAmountToman, 'id_order', $orderId);
        \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Mark(\Faoxima\Api\Runtime\Db::pdo(), $orderId, ['atlaspay_created_at' => time()]);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => $paymentUrl,
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleBluPal(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'blupalCreateInvoice')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_blupal_function_missing', '❌ تابع درگاه بلوپال روی این سرور موجود نیست.'));
        }

        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceblupal', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceblupal', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('blupal', $amount, $orderId);

        try {
            $pay = \Faoxima\Payments\Gateways\GatewayRuntime::blupalCreateInvoice($orderId, $amount);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('blupalCreateInvoice() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $invoiceId = is_array($pay) ? trim((string)($pay['invoice_id'] ?? '')) : '';
        $paymentLink = is_array($pay) ? trim((string)($pay['payment_link'] ?? '')) : '';

        if ($invoiceId === '' || $paymentLink === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('blupalCreateInvoice() returned bad response', ['raw' => $errMsg]);
            $this->rejectPaymentReport($orderId, $errMsg);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        \Faoxima\Core\Database\Db::update('Payment_report', 'blupal_invoice_id', $invoiceId, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'blupal_payment_link', $paymentLink, 'id_order', $orderId);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => $paymentLink,
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleVariza(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'varizaCreatePayment')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_variza_function_missing', '❌ تابع درگاه واریزا روی این سرور موجود نیست.'));
        }

        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalancevariza', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancevariza', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('variza', $amount, $orderId);

        try {
            $pay = \Faoxima\Payments\Gateways\GatewayRuntime::varizaCreatePayment($orderId, $amount);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('varizaCreatePayment() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $slug = is_array($pay) ? trim((string)($pay['slug'] ?? '')) : '';
        $paymentLink = is_array($pay) ? trim((string)($pay['pay_url'] ?? '')) : '';

        if ($slug === '' || $paymentLink === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('varizaCreatePayment() returned bad response', ['raw' => $errMsg]);
            $this->rejectPaymentReport($orderId, $errMsg);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', $slug, 'id_order', $orderId);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => $paymentLink,
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleAbanGateway(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'abangatewayCreatePayment')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_abangateway_function_missing', '❌ تابع درگاه آبان گیت وی روی این سرور موجود نیست.'));
        }

        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceabangateway', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceabangateway', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('abangateway', $amount, $orderId);

        try {
            $pay = \Faoxima\Payments\Gateways\GatewayRuntime::abangatewayCreatePayment($orderId, $amount, $this->user['id'] ?? null);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('abangatewayCreatePayment() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        // Success is only reported with an authority and an https link in hand.
        if (!is_array($pay) || empty($pay['success'])) {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('abangatewayCreatePayment() returned bad response', ['raw' => $errMsg]);
            $this->rejectPaymentReport($orderId, $errMsg);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', (string) $pay['authority'], 'id_order', $orderId);

        try {
            $cardInvoice = \Faoxima\Payments\Gateways\GatewayRuntime::abangatewayCardInvoice($pay, $orderId);
            if ($cardInvoice !== null) {
                $sent = sendmessage(
                    $this->user['id'],
                    $cardInvoice['text'],
                    $cardInvoice['keyboard'],
                    'HTML'
                );

                if (
                    is_array($sent) &&
                    !empty($sent['ok']) &&
                    !empty($sent['result']['message_id'])
                ) {
                    \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($sent, $orderId);
                }
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('abangateway card message delivery failed', ['err' => $e->getMessage()]);
        }

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => (string) $pay['pay_url'],
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleCubepay(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'cubepayCreatePayment')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_cubepay_function_missing', '❌ تابع درگاه کیوب‌پی روی این سرور موجود نیست.'));
        }

        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalancecubepay', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancecubepay', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('cubepay', $amount, $orderId);

        try {
            $pay = \Faoxima\Payments\Gateways\GatewayRuntime::cubepayCreatePayment($orderId, $amount, (string)$this->user['id']);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('cubepayCreatePayment() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $authority = is_array($pay) ? trim((string)($pay['authority'] ?? '')) : '';
        $paymentLink = is_array($pay) ? trim((string)($pay['payment_link'] ?? '')) : '';
        $method = is_array($pay) ? (string)($pay['method'] ?? 'choice') : 'choice';

        if ($paymentLink === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('cubepayCreatePayment() returned bad response', ['raw' => $errMsg]);
            $this->rejectPaymentReport($orderId, $errMsg);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        \Faoxima\Core\Database\Db::update('Payment_report', 'cubepay_authority', $authority, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'cubepay_payment_link', $paymentLink, 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'cubepay_method', $method, 'id_order', $orderId);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => $paymentLink,
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handlePlisio(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'plisio')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_plisio_function_missing', '❌ تابع Plisio روی این سرور موجود نیست.'));
        }


        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceplisio', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceplisio', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_plisio_min_amount', '❌ حداقل مبلغ پرداخت Plisio {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_plisio_max_amount', '❌ حداکثر مبلغ پرداخت Plisio {max} تومان است.'), ['max' => number_format($max)]));
        }


        if (!method_exists(\Faoxima\Payments\Gateways\Tronado\Runtime::class, 'requireTronRates')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rate_module_not_loaded', '❌ ماژول نرخ ارز روی سرور بارگذاری نشده.'));
        }
        $rates = \Faoxima\Payments\Gateways\Tronado\Runtime::requireTronRates(['TRX', 'USD']);
        if (!is_array($rates) || !isset($rates['TRX'], $rates['USD'])) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rate_fetch_failed', '❌ دریافت نرخ ارز ناموفق بود. لطفاً چند دقیقه دیگر تلاش کنید.'));
        }
        $trx = (float)$rates['TRX'];
        $usd = (float)$rates['USD'];
        if ($trx <= 0 || $usd <= 0) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rate_invalid', '❌ نرخ ارز نامعتبر است. لطفاً مدتی دیگر تلاش کنید.'));
        }
        $usdPrice = round($amount / $usd, 2);
        if ($usdPrice <= 1) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_amount_too_small_usd', '❌ مبلغ پرداخت بسیار کم است (کمتر از 1 دلار).'));
        }
        $trxPrice = round($amount / $trx, 2);

        $orderId = bin2hex(random_bytes(5));

        try {
            $pay = \Faoxima\Payments\Gateways\GatewayRuntime::plisio($orderId, $trxPrice);
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('plisio() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_plisio_connection_error', '❌ خطا در ارتباط با Plisio. لطفاً دوباره تلاش کنید.'));
        }
        if (!is_array($pay) || empty($pay['txn_id']) || empty($pay['invoice_url'])) {
            $errMsg = is_array($pay) ? (string)($pay['message'] ?? json_encode($pay)) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('plisio() returned bad response', ['raw' => $errMsg]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_plisio_link_creation_failed', '❌ ساخت لینک پرداخت Plisio ناموفق بود.'));
        }


        $this->insertPaymentReport('plisio', $amount, $orderId, (string)$pay['txn_id']);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => (string)$pay['invoice_url'],
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_plisio_invoice_created', 'فاکتور ارزی ساخته شد. روی لینک کلیک کنید تا به Plisio منتقل شوید.'),
        ]);
    }


    private function handleNowPayment(int $amount): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'nowPayments')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_nowpayment_function_missing', '❌ تابع NowPayments روی این سرور موجود نیست.'));
        }


        $minRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'minbalancenowpayment', 'select');
        $maxRow = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancenowpayment', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_nowpayment_min_amount', '❌ حداقل مبلغ پرداخت NowPayments {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_nowpayment_max_amount', '❌ حداکثر مبلغ پرداخت NowPayments {max} تومان است.'), ['max' => number_format($max)]));
        }


        if (!method_exists(\Faoxima\Payments\Gateways\Tronado\Runtime::class, 'requireTronRates')) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rate_module_not_loaded', '❌ ماژول نرخ ارز روی سرور بارگذاری نشده.'));
        }
        $rates = \Faoxima\Payments\Gateways\Tronado\Runtime::requireTronRates(['USD']);
        if (!is_array($rates) || !isset($rates['USD'])) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_rate_fetch_failed', '❌ دریافت نرخ ارز ناموفق بود. لطفاً چند دقیقه دیگر تلاش کنید.'));
        }
        $usd = (float)$rates['USD'];
        if ($usd <= 0) {
            \Faoxima\Api\Runtime\Response::fail(503, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_nowpayment_rate_invalid', '❌ نرخ ارز نامعتبر است.'));
        }
        $usdPrice = round($amount / $usd, 2);
        if ($usdPrice <= 0) {
            \Faoxima\Api\Runtime\Response::fail(422, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_amount_too_small', '❌ مبلغ پرداخت بسیار کم است.'));
        }

        $orderId = bin2hex(random_bytes(5));

        try {
            $pay = \Faoxima\Payments\Gateways\GatewayRuntime::nowPayments('invoice', $usdPrice, $orderId, 'order');
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::userFacing('nowPayments() threw', ['err' => $e->getMessage()]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_nowpayment_connection_error', '❌ خطا در ارتباط با NowPayments.'));
        }
        if (!is_array($pay) || empty($pay['id']) || empty($pay['invoice_url'])) {
            $errMsg = is_array($pay) ? json_encode($pay) : 'unknown';
            \Faoxima\Api\Runtime\Logger::userFacing('nowPayments() returned bad response', ['raw' => $errMsg]);
            \Faoxima\Api\Runtime\Response::fail(502, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_nowpayment_link_creation_failed', '❌ ساخت لینک پرداخت NowPayments ناموفق بود.'));
        }

        $this->insertPaymentReport('nowpayment', $amount, $orderId, (string)$pay['id']);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'     => 'url',
            'url'      => (string)$pay['invoice_url'],
            'order_id' => $orderId,
            'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_nowpayment_invoice_created', 'فاکتور ارزی NowPayments ساخته شد. روی لینک کلیک کنید.'),
        ]);
    }

    private function rejectPaymentReport(string $orderId, $reason): void
    {
        \Faoxima\Core\Database\Db::update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
        \Faoxima\Core\Database\Db::update('Payment_report', 'dec_not_confirmed', $reason, 'id_order', $orderId);
    }

    private function insertPaymentReport(string $method, int $amount, string $orderId, ?string $extId = null): void
    {
        $invoice = ($this->user['Processing_value_tow'] ?? '') . '|' . ($this->user['Processing_value_one'] ?? '');
        $now = date('Y/m/d H:i:s');

        $cols = ['id_user', 'id_order', 'time', 'price', 'payment_Status', 'Payment_Method', 'id_invoice', 'source'];
        $vals = [':u', ':o', ':t', ':p', ':s', ':m', ':i', ':src'];
        $params = [
            ':u'   => $this->user['id'],
            ':o'   => $orderId,
            ':t'   => $now,
            ':p'   => $amount,
            ':s'   => 'Unpaid',
            ':m'   => $method,
            ':i'   => $invoice,
            ':src' => 'miniapp',
        ];
        if ($extId !== null) {
            $cols[] = 'dec_not_confirmed';
            $vals[] = ':ext';
            $params[':ext'] = $extId;
        }
        if ($this->chargeBonus > 0) {
            $cols[] = 'charge_bonus';
            $vals[] = ':cb';
            $params[':cb'] = $this->chargeBonus;
        }
        if ($this->chargeDiscountCode !== '') {
            $cols[] = 'discount_code';
            $vals[] = ':dcode';
            $params[':dcode'] = $this->chargeDiscountCode;
            $cols[] = 'discount_amount';
            $vals[] = ':damt';
            $params[':damt'] = (string)$this->chargeDiscountAmount;
            $cols[] = 'price_before_discount';
            $vals[] = ':dpbd';
            $params[':dpbd'] = (string)$this->chargePriceBeforeDiscount;
        }

        $inserted = false;
        try {
            $pdo = \Faoxima\Api\Runtime\Db::pdo();
            $sql = 'INSERT INTO Payment_report (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $stmt = $pdo->prepare($sql);
            $inserted = $stmt->execute($params) && $stmt->rowCount() === 1;
            if (!$inserted) {
                \Faoxima\Api\Runtime\Logger::error('Payment_report insert affected no rows', ['order' => $orderId, 'method' => $method, 'user_id' => $this->user['id']]);
            }
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::error('Payment_report insert failed', ['err' => $e->getMessage(), 'order' => $orderId, 'method' => $method, 'user_id' => $this->user['id'], 'has_ext' => $extId !== null]);
        }
        if (!$inserted) {
            if ($this->sbvPreparedOrder === $orderId && is_callable([\Faoxima\Payments\Receipts\ReceiptOrders::class, 'abandon'])) {
                \Faoxima\Payments\Receipts\ReceiptOrders::abandon(\Faoxima\Api\Runtime\Db::pdo(), $orderId);
            }
            \Faoxima\Api\Runtime\Response::serverError(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_payment_record_failed', '❌ ثبت درخواست پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }
        if ($this->checkoutId !== null) {
            \Faoxima\Orders\Checkout\Checkout::attachPayment(\Faoxima\Api\Runtime\Db::pdo(), $this->checkoutId, $orderId, $method);
        }
    }

    private function jsonAgentValue(string $json, string $agent, $default)
    {
        if ($json === '') return $default;
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return $default;
        return $decoded[$agent] ?? $default;
    }
}

