<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class PaymentInitHandler extends BaseHandler
{


    private const STALE_UNPAID_MINUTES = 15;

    public function handle(): void
    {
        $this->requireMethod('POST');

        $method = FaoximaInput::string($this->data, 'method');
        $amount = FaoximaInput::int($this->data, 'amount', 0);

        if ($method === '') {
            FaoximaResponse::badRequest('method is required');
        }
        if ($amount <= 0) {
            FaoximaResponse::badRequest('amount must be > 0');
        }

        $self = $this;
        $get = function (string $name) use ($self): string {
            return $self->paySetting($name);
        };
        $agent = $this->user['agent'] ?? 'f';


        [$min, $max] = $this->methodLimits($method, $agent, $get);
        if ($amount < $min || $amount > $max) {
            FaoximaResponse::fail(422,
                '❌ مبلغ باید بین ' . number_format($min) . ' و ' . number_format($max) . ' تومان باشد');
        }


        if ($method === 'carttocart' || $method === 'carttocart_pv') {
            $this->purgeStaleCarttocart((int)$this->user['id']);

            $pendingFresh = (int) FaoximaDb::fetchScalar(
                "SELECT COUNT(*) FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status IN ('Unpaid','pending')
                    AND (Payment_Method = 'cart to cart' OR Payment_Method = 'carttocart_pv')
                    AND source = 'miniapp'",
                [':u' => $this->user['id']]
            );
            if ($pendingFresh > 0) {
                FaoximaResponse::fail(409,
                    '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.');
            }
        }


        update('user', 'Processing_value', $amount, 'id', $this->user['id']);
        $this->user['Processing_value'] = $amount;


        $purchaseUsername = FaoximaInput::nullableString($this->data, 'purchase_username');
        if ($purchaseUsername !== null && $purchaseUsername !== '') {


            $unpaidInvoiceExists = (int) FaoximaDb::fetchScalar(
                "SELECT COUNT(*) FROM invoice
                  WHERE username = :u AND id_user = :uid AND Status = 'unpaid'",
                [':u' => $purchaseUsername, ':uid' => $this->user['id']]
            );
            if ($unpaidInvoiceExists === 0) {
                FaoximaResponse::fail(404, '❌ فاکتور خرید ناتمامی برای این نام کاربری پیدا نشد.');
            }
            update('user', 'Processing_value_one', $purchaseUsername, 'id', $this->user['id']);
            update('user', 'Processing_value_tow', 'getconfigafterpay', 'id', $this->user['id']);
            $this->user['Processing_value_one'] = $purchaseUsername;
            $this->user['Processing_value_tow'] = 'getconfigafterpay';
        } else {
            update('user', 'Processing_value_one', '', 'id', $this->user['id']);
            update('user', 'Processing_value_tow', '', 'id', $this->user['id']);
            $this->user['Processing_value_one'] = '';
            $this->user['Processing_value_tow'] = '';
        }

        switch ($method) {
            case 'carttocart':
            case 'carttocart_pv':
                $this->handleCardToCard($amount, $get);
                return;

            case 'aqayepardakht':
                $this->handleGateway('aqayepardakht', $amount, function ($amt, $orderId) {
                    return createPayaqayepardakht($amt, $orderId);
                }, function ($pay) {
                    return $pay && ($pay['status'] ?? '') === 'success'
                        ? 'https://panel.aqayepardakht.ir/startpay/' . $pay['transid']
                        : null;
                });
                return;

            case 'zarinpal':
                $this->handleGateway('zarinpal', $amount, function ($amt, $orderId) {
                    return createPayZarinpal($amt, $orderId);
                }, function ($pay) {
                    return $pay && ($pay['status'] ?? '') === 'success'
                        ? 'https://www.zarinpal.com/pg/StartPay/' . $pay['authority']
                        : null;
                });
                return;

            case 'zarinpey':
                $this->handleGateway('zarinpey', $amount, function ($amt, $orderId) {
                    return createPayZarinpey($amt, $orderId, (string)($this->user['id'] ?? ''));
                }, function ($pay) {
                    return $pay && !empty($pay['url']) ? $pay['url'] : null;
                });
                return;

            case 'iranpay1':
            case 'iranpay2':
            case 'iranpay3':
                $url = $this->buildIranpayUrlFallback($method, $amount);
                $orderId = bin2hex(random_bytes(5));
                $this->insertPaymentReport($method, $amount, $orderId);
                FaoximaResponse::ok([
                    'kind'     => 'url',
                    'url'      => $url,
                    'order_id' => $orderId,
                    'message'  => '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.',
                ]);
                return;

            case 'plisio':
                $this->handlePlisio($amount);
                return;

            case 'nowpayment':
                $this->handleNowPayment($amount);
                return;

            case 'digitaltron':

                FaoximaResponse::fail(409,
                    '⚠️ این روش ارزی در مینی‌اپ از فلوی هش‌چکر استفاده می‌کند. لطفاً صفحه را بازنشانی کنید و مجدداً انتخاب کنید.');
                return;

            case 'paymentnotverify':
                $orderId = bin2hex(random_bytes(5));
                $this->insertPaymentReport('paymentnotverify', $amount, $orderId);
                FaoximaResponse::ok([
                    'kind'     => 'manual',
                    'order_id' => $orderId,
                    'message'  => '✅ درخواست شما ثبت شد. ادمین پس از بررسی، حساب شما را شارژ می‌کند.',
                ]);
                return;

            case 'startelegrams':
                $orderId = bin2hex(random_bytes(5));
                $this->insertPaymentReport('startelegrams', $amount, $orderId);
                FaoximaResponse::ok([
                    'kind'     => 'manual',
                    'order_id' => $orderId,
                    'message'  => '⭐ پرداخت با Telegram Stars از داخل ربات قابل تکمیل است.',
                ]);
                return;
        }

        FaoximaResponse::badRequest('Unknown payment method: ' . $method);
    }


    private function purgeStaleCarttocart(int $userId): void
    {
        try {
            $rows = FaoximaDb::fetchAll(
                "SELECT id_order, time
                   FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status = 'Unpaid'
                    AND (Payment_Method = 'cart to cart' OR Payment_Method = 'carttocart_pv')
                    AND source = 'miniapp'",
                [':u' => $userId]
            );
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('purgeStaleCarttocart fetch failed', ['err' => $e->getMessage()]);
            return;
        }
        if (!is_array($rows) || empty($rows)) return;

        $cutoff = time() - (self::STALE_UNPAID_MINUTES * 60);
        $stale = [];
        foreach ($rows as $r) {
            $ts = $this->parseLegacyTime((string)($r['time'] ?? ''));


            if ($ts === null || $ts <= $cutoff) {
                $stale[] = (string)$r['id_order'];
            }
        }
        if (empty($stale)) return;

        try {
            $pdo = FaoximaDb::pdo();
            $placeholders = implode(',', array_fill(0, count($stale), '?'));
            $sql = "DELETE FROM Payment_report
                     WHERE id_user = ?
                       AND payment_Status = 'Unpaid'
                       AND (Payment_Method = 'cart to cart' OR Payment_Method = 'carttocart_pv')
                       AND source = 'miniapp'
                       AND id_order IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$userId], $stale);
            $stmt->execute($params);
            FaoximaLogger::debug('Purged stale carttocart Unpaid rows', [
                'user_id' => $userId,
                'count'   => count($stale),
            ]);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('purgeStaleCarttocart delete failed', ['err' => $e->getMessage()]);
        }
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
            'aqayepardakht' => ['minbalanceaqayepardakht', 'maxbalanceaqayepardakht'],
            'zarinpal'      => ['minbalancezarinpal',      'maxbalancezarinpal'],
            'zarinpey'      => ['minbalancezarinpey',      'maxbalancezarinpey'],
            'plisio'        => ['minbalanceplisio',        'maxbalanceplisio'],
            'nowpayment'    => ['minbalancenowpayment',    'maxbalancenowpayment'],
            'digitaltron'   => ['minbalancedigitaltron',   'maxbalancedigitaltron'],
            'iranpay1'      => ['minbalanceiranpay1',      'maxbalanceiranpay1'],
            'iranpay2'      => ['minbalanceiranpay2',      'maxbalanceiranpay2'],
            'iranpay3'      => ['minbalanceiranpay',       'maxbalanceiranpay'],
        ];

        if (isset($perMethod[$method])) {
            [$minK, $maxK] = $perMethod[$method];
            $min = (int) ($get($minK) ?: 0);
            $max = (int) ($get($maxK) ?: 0);
            if ($min > 0 && $max > 0) return [$min, $max];
        }


        $minJson = $get('minbalance');
        $maxJson = $get('maxbalance');
        $min = (int) $this->jsonAgentValue($minJson, $agent, 1000);
        $max = (int) $this->jsonAgentValue($maxJson, $agent, 100000000);
        return [$min, $max];
    }


    private function handleCardToCard(int $amount, callable $get): void
    {


        $card = null;
        try {
            $cards = FaoximaDb::fetchAll(
                'SELECT cardnumber, namecard FROM card_number'
            );
            if (is_array($cards) && !empty($cards)) {

                $idx = count($cards) === 1 ? 0 : array_rand($cards);
                $card = $cards[$idx];
            }
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('card_number fetch failed', ['err' => $e->getMessage()]);
        }
        if (!is_array($card) || empty($card['cardnumber']) || empty($card['namecard'])) {
            FaoximaResponse::fail(503, '❌ کارت بانکی فعالی برای کارت‌به‌کارت تنظیم نشده است.');
        }


        $autoConfirm = $get('statuscardautoconfirm') === 'onautoconfirm';
        $shownAmount = $amount;
        $rialAmount  = $amount * 10;
        if ($autoConfirm) {
            $shownAmount = $amount + random_int(0, 2000);
            $rialAmount  = $shownAmount * 10;
            update('user', 'Processing_value', $shownAmount, 'id', $this->user['id']);
        }

        $orderId = bin2hex(random_bytes(5));
        $this->insertPaymentReport('cart to cart', $shownAmount, $orderId);

        FaoximaResponse::ok([
            'kind'         => 'carttocart',
            'order_id'     => $orderId,
            'card_number'  => (string)$card['cardnumber'],
            'name_card'    => (string)$card['namecard'],
            'amount'       => $shownAmount,
            'amount_rial'  => $rialAmount,
            'auto_confirm' => $autoConfirm,
            'message'      => '💳 مبلغ را به کارت زیر واریز کنید و سپس رسید را آپلود نمایید.',
        ]);
    }


    private function handleGateway(string $method, int $amount, callable $createFn, callable $extractUrl): void
    {
        $orderId = bin2hex(random_bytes(5));

        if (!is_callable($createFn)) {
            FaoximaResponse::fail(503, '❌ تابع گیت‌وی در سرور موجود نیست: ' . $method);
        }

        try {
            $pay = $createFn($amount, $orderId);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('Gateway threw: ' . $method, ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, '❌ خطا در ارتباط با درگاه ' . $method);
        }

        $url = $extractUrl($pay);
        if (!$url) {
            FaoximaLogger::userFacing('Gateway did not return a URL: ' . $method, ['raw' => $pay]);
            FaoximaResponse::fail(502, '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.');
        }

        $this->insertPaymentReport($method, $amount, $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $url,
            'order_id' => $orderId,
            'message'  => '✅ فاکتور ساخته شد. برای پرداخت روی لینک کلیک کنید.',
        ]);
    }


    private function buildIranpayUrlFallback(string $method, int $amount): string
    {
        return $this->botDeepLink('miniapp_pay_' . $method);
    }

    private function buildCryptoUrlFallback(string $method, int $amount): string
    {
        return $this->botDeepLink('miniapp_pay_' . $method);
    }


    private function handlePlisio(int $amount): void
    {
        if (!function_exists('plisio')) {
            FaoximaResponse::fail(503, '❌ تابع Plisio روی این سرور موجود نیست.');
        }


        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceplisio', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceplisio', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422,
                '❌ حداقل مبلغ پرداخت Plisio ' . number_format($min) . ' تومان است.');
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422,
                '❌ حداکثر مبلغ پرداخت Plisio ' . number_format($max) . ' تومان است.');
        }


        if (!function_exists('requireTronRates')) {
            FaoximaResponse::fail(503, '❌ ماژول نرخ ارز روی سرور بارگذاری نشده.');
        }
        $rates = requireTronRates(['TRX', 'USD']);
        if (!is_array($rates) || !isset($rates['TRX'], $rates['USD'])) {
            FaoximaResponse::fail(503, '❌ دریافت نرخ ارز ناموفق بود. لطفاً چند دقیقه دیگر تلاش کنید.');
        }
        $trx = (float)$rates['TRX'];
        $usd = (float)$rates['USD'];
        if ($trx <= 0 || $usd <= 0) {
            FaoximaResponse::fail(503, '❌ نرخ ارز نامعتبر است. لطفاً مدتی دیگر تلاش کنید.');
        }
        $usdPrice = round($amount / $usd, 2);
        if ($usdPrice <= 1) {
            FaoximaResponse::fail(422, '❌ مبلغ پرداخت بسیار کم است (کمتر از 1 دلار).');
        }
        $trxPrice = round($amount / $trx, 2);

        $orderId = bin2hex(random_bytes(5));

        try {
            $pay = plisio($orderId, $trxPrice);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('plisio() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, '❌ خطا در ارتباط با Plisio. لطفاً دوباره تلاش کنید.');
        }
        if (!is_array($pay) || empty($pay['txn_id']) || empty($pay['invoice_url'])) {
            $errMsg = is_array($pay) ? (string)($pay['message'] ?? json_encode($pay)) : 'unknown';
            FaoximaLogger::userFacing('plisio() returned bad response', ['raw' => $errMsg]);
            FaoximaResponse::fail(502, '❌ ساخت لینک پرداخت Plisio ناموفق بود.');
        }


        $this->insertPaymentReport('plisio', $amount, $orderId, (string)$pay['txn_id']);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => (string)$pay['invoice_url'],
            'order_id' => $orderId,
            'message'  => 'فاکتور ارزی ساخته شد. روی لینک کلیک کنید تا به Plisio منتقل شوید.',
        ]);
    }


    private function handleNowPayment(int $amount): void
    {
        if (!function_exists('nowPayments')) {
            FaoximaResponse::fail(503, '❌ تابع NowPayments روی این سرور موجود نیست.');
        }


        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalancenowpayment', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancenowpayment', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422,
                '❌ حداقل مبلغ پرداخت NowPayments ' . number_format($min) . ' تومان است.');
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422,
                '❌ حداکثر مبلغ پرداخت NowPayments ' . number_format($max) . ' تومان است.');
        }


        if (!function_exists('requireTronRates')) {
            FaoximaResponse::fail(503, '❌ ماژول نرخ ارز روی سرور بارگذاری نشده.');
        }
        $rates = requireTronRates(['USD']);
        if (!is_array($rates) || !isset($rates['USD'])) {
            FaoximaResponse::fail(503, '❌ دریافت نرخ ارز ناموفق بود. لطفاً چند دقیقه دیگر تلاش کنید.');
        }
        $usd = (float)$rates['USD'];
        if ($usd <= 0) {
            FaoximaResponse::fail(503, '❌ نرخ ارز نامعتبر است.');
        }
        $usdPrice = round($amount / $usd, 2);
        if ($usdPrice <= 0) {
            FaoximaResponse::fail(422, '❌ مبلغ پرداخت بسیار کم است.');
        }

        $orderId = bin2hex(random_bytes(5));

        try {
            $pay = nowPayments('invoice', $usdPrice, $orderId, 'order');
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('nowPayments() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, '❌ خطا در ارتباط با NowPayments.');
        }
        if (!is_array($pay) || empty($pay['id']) || empty($pay['invoice_url'])) {
            $errMsg = is_array($pay) ? json_encode($pay) : 'unknown';
            FaoximaLogger::userFacing('nowPayments() returned bad response', ['raw' => $errMsg]);
            FaoximaResponse::fail(502, '❌ ساخت لینک پرداخت NowPayments ناموفق بود.');
        }

        $this->insertPaymentReport('nowpayment', $amount, $orderId, (string)$pay['id']);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => (string)$pay['invoice_url'],
            'order_id' => $orderId,
            'message'  => 'فاکتور ارزی NowPayments ساخته شد. روی لینک کلیک کنید.',
        ]);
    }

    private function botDeepLink(string $startParam): string
    {
        global $usernamebot, $username;
        $bot = '';
        if (isset($usernamebot) && is_string($usernamebot)) {
            $bot = ltrim(trim($usernamebot), '@');
        }
        if ($bot === '' && isset($username) && is_string($username)) {
            $bot = ltrim(trim($username), '@');
        }
        if ($bot === '') {
            FaoximaResponse::fail(503, '❌ نام کاربری ربات روی سرور ثبت نشده است.');
        }
        return 'https://t.me/' . $bot . '?start=' . $startParam;
    }

    private function insertPaymentReport(string $method, int $amount, string $orderId, ?string $extId = null): void
    {
        $invoice = ($this->user['Processing_value_tow'] ?? '') . '|' . ($this->user['Processing_value_one'] ?? '');
        $now = date('Y/m/d H:i:s');

        try {
            $pdo = FaoximaDb::pdo();
            if ($extId === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO Payment_report
                        (id_user, id_order, time, price, payment_Status, Payment_Method, id_invoice, source)
                     VALUES (:u, :o, :t, :p, :s, :m, :i, :src)'
                );
                $stmt->execute([
                    ':u'   => $this->user['id'],
                    ':o'   => $orderId,
                    ':t'   => $now,
                    ':p'   => $amount,
                    ':s'   => 'Unpaid',
                    ':m'   => $method,
                    ':i'   => $invoice,
                    ':src' => 'miniapp',
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO Payment_report
                        (id_user, id_order, time, price, payment_Status, Payment_Method, id_invoice, dec_not_confirmed, source)
                     VALUES (:u, :o, :t, :p, :s, :m, :i, :ext, :src)'
                );
                $stmt->execute([
                    ':u'   => $this->user['id'],
                    ':o'   => $orderId,
                    ':t'   => $now,
                    ':p'   => $amount,
                    ':s'   => 'Unpaid',
                    ':m'   => $method,
                    ':i'   => $invoice,
                    ':ext' => $extId,
                    ':src' => 'miniapp',
                ]);
            }
        } catch (Throwable $e) {
            FaoximaLogger::warn('Payment_report insert failed', ['err' => $e->getMessage(), 'has_ext' => $extId !== null]);
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

