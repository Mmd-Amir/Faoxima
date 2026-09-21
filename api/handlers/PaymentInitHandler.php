<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/DiscountSupport.php';

final class PaymentInitHandler extends BaseHandler
{


    private const STALE_UNPAID_MINUTES = 15;

    private int $chargeBonus = 0;
    private string $chargeDiscountCode = '';
    private float $chargeDiscountAmount = 0.0;
    private float $chargePriceBeforeDiscount = 0.0;

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
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_amount_out_of_range', '❌ مبلغ باید بین {min} و {max} تومان باشد'), [
                'min' => number_format($min),
                'max' => number_format($max),
            ]));
        }


        $lockMethodKey = ($method === 'carttocart' || $method === 'carttocart_pv') ? 'carttocart' : $method;
        $lockName = 'faoxima_payment_init_' . $this->user['id'] . '_' . $lockMethodKey;
        $pdo = FaoximaDb::pdo();
        $lockStmt = $pdo->prepare('SELECT GET_LOCK(:n, 5)');
        $lockStmt->execute([':n' => $lockName]);
        $lockAcquired = (int) $lockStmt->fetchColumn() === 1;
        if (!$lockAcquired) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'));
        }

        try {
            if ($method === 'carttocart' || $method === 'carttocart_pv') {
                $this->purgeStaleCarttocart((int)$this->user['id']);
                $this->purgeAbandonedCarttocart((int)$this->user['id']);

                $pendingFresh = (int) FaoximaDb::fetchScalar(
                    "SELECT COUNT(*) FROM Payment_report
                      WHERE id_user = :u
                        AND payment_Status IN ('Unpaid','pending','waiting')
                        AND (Payment_Method = 'cart to cart' OR Payment_Method = 'carttocart_pv')
                        AND source = 'miniapp'",
                    [':u' => $this->user['id']]
                );
                if ($pendingFresh > 0) {
                    FaoximaResponse::fail(409, faoxima_textbot_get('dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'));
                }
            } else {
                $this->purgeStaleGatewayOrders((int)$this->user['id'], $method);

                $pendingFreshGateway = (int) FaoximaDb::fetchScalar(
                    "SELECT COUNT(*) FROM Payment_report
                      WHERE id_user = :u
                        AND payment_Status IN ('Unpaid','pending','waiting')
                        AND Payment_Method = :m
                        AND source = 'miniapp'",
                    [':u' => $this->user['id'], ':m' => $method]
                );
                if ($pendingFreshGateway > 0) {
                    FaoximaResponse::fail(409, faoxima_textbot_get('dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'));
                }
            }


            update('user', 'Processing_value', $amount, 'id', $this->user['id']);
            $this->user['Processing_value'] = $amount;


            $renewUsername = FaoximaInput::nullableString($this->data, 'renew_username');
            $purchaseUsername = FaoximaInput::nullableString($this->data, 'purchase_username');
            if ($renewUsername !== null && $renewUsername !== '') {

                $serviceExists = (int) FaoximaDb::fetchScalar(
                    "SELECT COUNT(*) FROM invoice WHERE username = :u AND id_user = :uid",
                    [':u' => $renewUsername, ':uid' => $this->user['id']]
                );
                if ($serviceExists === 0) {
                    FaoximaResponse::fail(404, faoxima_textbot_get('dyn_paymentinit_renew_service_not_found', '❌ سرویسی برای تمدید با این نام کاربری پیدا نشد.'));
                }

                $tow = (string)($this->user['Processing_value_tow'] ?? '');
                $one = (string)($this->user['Processing_value_one'] ?? '');
                $allowedTow = ['getextenduser', 'getextravolumeuser', 'getextratimeuser'];
                if (!in_array($tow, $allowedTow, true) || $one === '' || strpos($one, '%') === false) {
                    FaoximaResponse::fail(409, faoxima_textbot_get('dyn_paymentinit_renew_steps_incomplete', '❌ مراحل تمدید کامل نشده است. لطفاً تمدید را از ابتدا انجام دهید.'));
                }

            } elseif ($purchaseUsername !== null && $purchaseUsername !== '') {


                $unpaidInvoiceExists = (int) FaoximaDb::fetchScalar(
                    "SELECT COUNT(*) FROM invoice
                      WHERE username = :u AND id_user = :uid AND Status = 'unpaid'",
                    [':u' => $purchaseUsername, ':uid' => $this->user['id']]
                );
                if ($unpaidInvoiceExists === 0) {
                    FaoximaResponse::fail(404, faoxima_textbot_get('dyn_paymentinit_unpaid_invoice_not_found', '❌ فاکتور خرید ناتمامی برای این نام کاربری پیدا نشد.'));
                }
                update('user', 'Processing_value_one', $purchaseUsername, 'id', $this->user['id']);
                update('user', 'Processing_value_tow', 'getconfigafterpay', 'id', $this->user['id']);
                $this->user['Processing_value_one'] = $purchaseUsername;
                $this->user['Processing_value_tow'] = 'getconfigafterpay';
            } else {
                $chargeCode = FaoximaInput::string($this->data, 'discount_code');
                if ($chargeCode !== '') {
                    $dv = MiniDiscount::validateSell($chargeCode, 'charge', '', '', '', $this->user);
                    if (empty($dv['ok'])) {
                        FaoximaResponse::fail(422, (string)($dv['reason'] ?? faoxima_textbot_get('dyn_purchase_invalid_discount_code', '❌ کد تخفیف نامعتبر است.')));
                    }
                    $chargeAmountBefore = (float)$amount;
                    $gatewayAmount = (int) round(MiniDiscount::applyToPrice($dv['row'], $chargeAmountBefore));
                    if ($gatewayAmount <= 0) {
                        FaoximaResponse::fail(422, faoxima_textbot_get('dyn_paymentinit_discount_zeroes_charge', '❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).'));
                    }
                    $this->chargeBonus = $amount - $gatewayAmount;
                    if ($this->chargeBonus < 0) $this->chargeBonus = 0;
                    $amount = $gatewayAmount;
                    update('user', 'Processing_value', $amount, 'id', $this->user['id']);
                    $this->user['Processing_value'] = $amount;
                    $this->chargeDiscountCode = $chargeCode;
                    $this->chargeDiscountAmount = $chargeAmountBefore - (float)$gatewayAmount;
                    $this->chargePriceBeforeDiscount = $chargeAmountBefore;
                }
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

                case 'zarinpal':
                    $this->handleGateway('zarinpal', $amount, function ($amt, $orderId) {
                        return createPayZarinpal($amt, $orderId);
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

                case 'tetrapay':
                    $this->handleTetraPay($amount);
                    return;

                case 'plisio':
                    $this->handlePlisio($amount);
                    return;

                case 'nowpayment':
                    $this->handleNowPayment($amount);
                    return;

                case 'digitaltron':

                    FaoximaResponse::fail(409, faoxima_textbot_get('dyn_paymentinit_digitaltron_use_hashchecker', '⚠️ این روش ارزی در مینی‌اپ از فلوی هش‌چکر استفاده می‌کند. لطفاً صفحه را بازنشانی کنید و مجدداً انتخاب کنید.'));
                    return;

                case 'paymentnotverify':
                    $orderId = bin2hex(random_bytes(5));
                    $this->insertPaymentReport('paymentnotverify', $amount, $orderId);
                    FaoximaResponse::ok([
                        'kind'     => 'manual',
                        'order_id' => $orderId,
                        'message'  => faoxima_textbot_get('dyn_paymentinit_manual_request_registered', '✅ درخواست شما ثبت شد. ادمین پس از بررسی، حساب شما را شارژ می‌کند.'),
                    ]);
                    return;

                case 'startelegrams':
                    $orderId = bin2hex(random_bytes(5));
                    $this->insertPaymentReport('startelegrams', $amount, $orderId);
                    FaoximaResponse::ok([
                        'kind'     => 'manual',
                        'order_id' => $orderId,
                        'message'  => faoxima_textbot_get('dyn_paymentinit_stars_complete_in_bot', '⭐ پرداخت با Telegram Stars از داخل ربات قابل تکمیل است.'),
                    ]);
                    return;
            }

            FaoximaResponse::badRequest('Unknown payment method: ' . $method);
        } finally {
            $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(:n)');
            $releaseStmt->execute([':n' => $lockName]);
        }
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

    private function purgeAbandonedCarttocart(int $userId): void
    {
        try {
            $pdo = FaoximaDb::pdo();
            $stmt = $pdo->prepare(
                "DELETE FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status IN ('Unpaid','pending')
                    AND (Payment_Method = 'cart to cart' OR Payment_Method = 'carttocart_pv')
                    AND source = 'miniapp'
                    AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')"
            );
            $stmt->execute([':u' => $userId]);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('purgeAbandonedCarttocart delete failed', ['err' => $e->getMessage()]);
        }
    }

    private function purgeStaleGatewayOrders(int $userId, string $method): void
    {
        try {
            $rows = FaoximaDb::fetchAll(
                "SELECT id_order, time
                   FROM Payment_report
                  WHERE id_user = :u
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND Payment_Method = :m
                    AND source = 'miniapp'",
                [':u' => $userId, ':m' => $method]
            );
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('purgeStaleGatewayOrders fetch failed', ['err' => $e->getMessage()]);
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
        if (empty($stale)) return;

        try {
            $pdo = FaoximaDb::pdo();
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
            FaoximaLogger::debug('Marked stale gateway Payment_report rows as expired', [
                'user_id' => $userId,
                'method'  => $method,
                'count'   => count($stale),
            ]);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('purgeStaleGatewayOrders update failed', ['err' => $e->getMessage()]);
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
        if ($method === 'tetrapay') {
            return 10;
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
            'cubepay'       => ['minbalancecubepay',       'maxbalancecubepay'],
            'blupal'        => ['minbalanceblupal',        'maxbalanceblupal'],
            'variza'        => ['minbalancevariza',        'maxbalancevariza'],
            'abangateway'   => ['minbalanceabangateway',   'maxbalanceabangateway'],
            'atlaspay'      => ['minbalanceatlaspay',      'maxbalanceatlaspay'],
            'tetrapay'      => ['minbalancetetrapay',      'maxbalancetetrapay'],
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
        return [$min, $max];
    }


    private function handleCardToCard(int $amount, callable $get): void
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
            $adminRows = FaoximaDb::fetchAll('SELECT id_admin FROM admin');
            $adminIds  = array_map('intval', array_column($adminRows ?: [], 'id_admin'));
            if (in_array((int)$this->user['id'], $adminIds, true)) {
                $cardVerifyActive = false;
            }
        }

        $card  = null;
        $card2 = null;
        $directRows = [];
        try {
            $userId = (string)$this->user['id'];
            $pdo    = FaoximaDb::pdo();
            $mRow   = FaoximaDb::fetchOne("SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
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
                $seenRow  = FaoximaDb::fetchOne("SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1", [$cycleKey]);
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
            FaoximaLogger::userFacing('card_number fetch failed', ['err' => $e->getMessage()]);
        }

        if (!is_array($card) || empty($card['cardnumber']) || empty($card['namecard'])) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_no_active_card', '❌ کارت بانکی فعالی برای کارت‌به‌کارت تنظیم نشده است.'));
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
        $orderId    = bin2hex(random_bytes(5));
        $this->insertPaymentReport('cart to cart', $amount, $orderId);

        if ($cardVerifyActive) {
            $verifiedCards = [];
            try {
                $vcRows = FaoximaDb::fetchAll(
                    'SELECT last4 FROM verified_cards WHERE user_id = ? ORDER BY id DESC LIMIT 5',
                    [(string)$this->user['id']]
                );
                if (is_array($vcRows)) {
                    $verifiedCards = array_values(array_column($vcRows, 'last4'));
                }
            } catch (Throwable $e) {
                FaoximaLogger::userFacing('verified_cards fetch failed', ['err' => $e->getMessage()]);
            }

            FaoximaResponse::ok([
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
                'auto_confirm'          => false,
                'message'               => faoxima_textbot_get('dyn_paymentinit_card_verify_active', '🔒 احراز هویت کارت‌به‌کارت فعال است.'),
            ]);
        }

        FaoximaResponse::ok([
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
            'auto_confirm'          => false,
            'message'               => faoxima_textbot_get('dyn_paymentinit_deposit_and_upload_receipt', '💳 مبلغ را به کارت زیر واریز کنید و سپس رسید را آپلود نمایید.'),
        ]);
    }


    private function handleGateway(string $method, int $amount, callable $createFn, callable $extractUrl): void
    {
        $orderId = bin2hex(random_bytes(5));

        if (!is_callable($createFn)) {
            FaoximaResponse::fail(503, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_gateway_function_missing', '❌ تابع گیت‌وی در سرور موجود نیست: {method}'), ['method' => $method]));
        }

        try {
            $pay = $createFn($amount, $orderId);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('Gateway threw: ' . $method, ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_gateway_connection_error', '❌ خطا در ارتباط با درگاه {method}'), ['method' => $method]));
        }

        $url = $extractUrl($pay);
        if (!$url) {
            FaoximaLogger::userFacing('Gateway did not return a URL: ' . $method, ['raw' => $pay]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        $this->insertPaymentReport($method, $amount, $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $url,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_invoice_created_click_link', '✅ فاکتور ساخته شد. برای پرداخت روی لینک کلیک کنید.'),
        ]);
    }


    private function buildCryptoUrlFallback(string $method, int $amount): string
    {
        return $this->botDeepLink('miniapp_pay_' . $method);
    }

    private function handleTronado(int $amount): void
    {
        if (!function_exists('trnado')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_tronado_function_missing', '❌ تابع درگاه ترونادو روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceiranpay2', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceiranpay2', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('iranpay2', $amount, $orderId);

        try {
            $pay = trnado($orderId, $amount);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('trnado() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $token = function_exists('tronadoExtractPaymentToken') ? tronadoExtractPaymentToken($pay) : (string)($pay['Token'] ?? '');
        $paymentUrl = is_array($pay) ? trim((string)($pay['FullPaymentUrl'] ?? '')) : '';

        if ($token === '' || $paymentUrl === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('trnado() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        update('Payment_report', 'dec_not_confirmed', $token, 'id_order', $orderId);
        update('Payment_report', 'tronado_payment_url', $paymentUrl, 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $paymentUrl,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleTonPay(int $amount): void
    {
        if (!function_exists('tonpayCreateInvoice')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_tonpay_function_missing', '❌ تابع درگاه تون‌پی روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalancetonpay', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancetonpay', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('tonpay', $amount, $orderId);

        try {
            $pay = tonpayCreateInvoice($orderId, $amount);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('tonpayCreateInvoice() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $invoiceId = is_array($pay) ? trim((string)($pay['invoice_id'] ?? '')) : '';
        $invoiceUrl = is_array($pay) ? trim((string)($pay['invoice_url'] ?? '')) : '';

        if ($invoiceId === '' || $invoiceUrl === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('tonpayCreateInvoice() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        update('Payment_report', 'tonpay_invoice_id', $invoiceId, 'id_order', $orderId);
        update('Payment_report', 'tonpay_invoice_url', $invoiceUrl, 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $invoiceUrl,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleAtlasPay(int $amount): void
    {
        if (!function_exists('atlaspayCreateOrder')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_atlaspay_function_missing', '❌ تابع درگاه اطلس‌پی روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceatlaspay', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceatlaspay', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('atlaspay', $amount, $orderId);

        try {
            $pay = atlaspayCreateOrder($orderId, $amount);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('atlaspayCreateOrder() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $atlaspayOrderId = is_array($pay) ? trim((string)($pay['orderId'] ?? '')) : '';
        $paymentUrl = is_array($pay) ? trim((string)($pay['customerStartLink'] ?? '')) : '';
        $trackingCode = is_array($pay) ? trim((string)($pay['trackingCode'] ?? '')) : '';

        if ($atlaspayOrderId === '' || $paymentUrl === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('atlaspayCreateOrder() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        $totalAmountToman = is_array($pay) ? (int)($pay['totalAmountToman'] ?? $amount) : $amount;

        update('Payment_report', 'atlaspay_order_id', $atlaspayOrderId, 'id_order', $orderId);
        update('Payment_report', 'atlaspay_tracking_code', $trackingCode, 'id_order', $orderId);
        update('Payment_report', 'atlaspay_payment_url', $paymentUrl, 'id_order', $orderId);
        update('Payment_report', 'price', $totalAmountToman, 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $paymentUrl,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleTetraPay(int $amount): void
    {
        if (!function_exists('tetrapayCreatePaymentLink')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_tetrapay_function_missing', '❌ تابع درگاه تتراپی روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalancetetrapay', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancetetrapay', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('tetrapay', $amount, $orderId);

        try {
            $pay = tetrapayCreatePaymentLink($amount);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('tetrapayCreatePaymentLink() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $token = is_array($pay) ? trim((string)($pay['token'] ?? '')) : '';
        $paymentLink = is_array($pay) ? trim((string)($pay['link'] ?? '')) : '';
        $trackingCode = is_array($pay) ? trim((string)($pay['tracking_code'] ?? '')) : '';

        if ($token === '' || $paymentLink === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('tetrapayCreatePaymentLink() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        $totalAmountToman = is_array($pay) && isset($pay['total_amount']) ? (int) $pay['total_amount'] : $amount;

        update('Payment_report', 'tetrapay_token', $token, 'id_order', $orderId);
        update('Payment_report', 'tetrapay_tracking_code', $trackingCode, 'id_order', $orderId);
        update('Payment_report', 'tetrapay_payment_link', $paymentLink, 'id_order', $orderId);
        update('Payment_report', 'price', $totalAmountToman, 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $paymentLink,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleBluPal(int $amount): void
    {
        if (!function_exists('blupalCreateInvoice')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_blupal_function_missing', '❌ تابع درگاه بلوپال روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceblupal', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceblupal', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('blupal', $amount, $orderId);

        try {
            $pay = blupalCreateInvoice($orderId, $amount);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('blupalCreateInvoice() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $invoiceId = is_array($pay) ? trim((string)($pay['invoice_id'] ?? '')) : '';
        $paymentLink = is_array($pay) ? trim((string)($pay['payment_link'] ?? '')) : '';

        if ($invoiceId === '' || $paymentLink === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('blupalCreateInvoice() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        update('Payment_report', 'blupal_invoice_id', $invoiceId, 'id_order', $orderId);
        update('Payment_report', 'blupal_payment_link', $paymentLink, 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $paymentLink,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleVariza(int $amount): void
    {
        if (!function_exists('varizaCreatePayment')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_variza_function_missing', '❌ تابع درگاه واریزا روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalancevariza', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancevariza', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('variza', $amount, $orderId);

        try {
            $pay = varizaCreatePayment($orderId, $amount);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('varizaCreatePayment() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $slug = is_array($pay) ? trim((string)($pay['slug'] ?? '')) : '';
        $paymentLink = is_array($pay) ? trim((string)($pay['pay_url'] ?? '')) : '';

        if ($slug === '' || $paymentLink === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('varizaCreatePayment() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        update('Payment_report', 'dec_not_confirmed', $slug, 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $paymentLink,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleAbanGateway(int $amount): void
    {
        if (!function_exists('abangatewayCreatePayment')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_abangateway_function_missing', '❌ تابع درگاه آبان گیت وی روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceabangateway', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceabangateway', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('abangateway', $amount, $orderId);

        try {
            $pay = abangatewayCreatePayment($orderId, $amount, $this->user['id'] ?? null);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('abangatewayCreatePayment() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        // Success is only reported with an authority and an https link in hand.
        if (!is_array($pay) || empty($pay['success'])) {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('abangatewayCreatePayment() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        update('Payment_report', 'dec_not_confirmed', (string) $pay['authority'], 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => (string) $pay['pay_url'],
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handleCubepay(int $amount): void
    {
        if (!function_exists('cubepayCreatePayment')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_cubepay_function_missing', '❌ تابع درگاه کیوب‌پی روی این سرور موجود نیست.'));
        }

        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalancecubepay', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancecubepay', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'), ['max' => number_format($max)]));
        }

        $orderId = bin2hex(random_bytes(5));

        $this->insertPaymentReport('cubepay', $amount, $orderId);

        try {
            $pay = cubepayCreatePayment($orderId, $amount, (string)$this->user['id']);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('cubepayCreatePayment() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'));
        }

        $authority = is_array($pay) ? trim((string)($pay['authority'] ?? '')) : '';
        $paymentLink = is_array($pay) ? trim((string)($pay['payment_link'] ?? '')) : '';
        $method = is_array($pay) ? (string)($pay['method'] ?? 'choice') : 'choice';

        if ($paymentLink === '') {
            $errMsg = is_array($pay) ? json_encode($pay, JSON_UNESCAPED_UNICODE) : 'unknown';
            FaoximaLogger::userFacing('cubepayCreatePayment() returned bad response', ['raw' => $errMsg]);
            update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
            update('Payment_report', 'dec_not_confirmed', $errMsg, 'id_order', $orderId);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'));
        }

        update('Payment_report', 'cubepay_authority', $authority, 'id_order', $orderId);
        update('Payment_report', 'cubepay_payment_link', $paymentLink, 'id_order', $orderId);
        update('Payment_report', 'cubepay_method', $method, 'id_order', $orderId);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => $paymentLink,
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'),
        ]);
    }


    private function handlePlisio(int $amount): void
    {
        if (!function_exists('plisio')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_plisio_function_missing', '❌ تابع Plisio روی این سرور موجود نیست.'));
        }


        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalanceplisio', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalanceplisio', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_plisio_min_amount', '❌ حداقل مبلغ پرداخت Plisio {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_plisio_max_amount', '❌ حداکثر مبلغ پرداخت Plisio {max} تومان است.'), ['max' => number_format($max)]));
        }


        if (!function_exists('requireTronRates')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_rate_module_not_loaded', '❌ ماژول نرخ ارز روی سرور بارگذاری نشده.'));
        }
        $rates = requireTronRates(['TRX', 'USD']);
        if (!is_array($rates) || !isset($rates['TRX'], $rates['USD'])) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_rate_fetch_failed', '❌ دریافت نرخ ارز ناموفق بود. لطفاً چند دقیقه دیگر تلاش کنید.'));
        }
        $trx = (float)$rates['TRX'];
        $usd = (float)$rates['USD'];
        if ($trx <= 0 || $usd <= 0) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_rate_invalid', '❌ نرخ ارز نامعتبر است. لطفاً مدتی دیگر تلاش کنید.'));
        }
        $usdPrice = round($amount / $usd, 2);
        if ($usdPrice <= 1) {
            FaoximaResponse::fail(422, faoxima_textbot_get('dyn_paymentinit_amount_too_small_usd', '❌ مبلغ پرداخت بسیار کم است (کمتر از 1 دلار).'));
        }
        $trxPrice = round($amount / $trx, 2);

        $orderId = bin2hex(random_bytes(5));

        try {
            $pay = plisio($orderId, $trxPrice);
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('plisio() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_plisio_connection_error', '❌ خطا در ارتباط با Plisio. لطفاً دوباره تلاش کنید.'));
        }
        if (!is_array($pay) || empty($pay['txn_id']) || empty($pay['invoice_url'])) {
            $errMsg = is_array($pay) ? (string)($pay['message'] ?? json_encode($pay)) : 'unknown';
            FaoximaLogger::userFacing('plisio() returned bad response', ['raw' => $errMsg]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_plisio_link_creation_failed', '❌ ساخت لینک پرداخت Plisio ناموفق بود.'));
        }


        $this->insertPaymentReport('plisio', $amount, $orderId, (string)$pay['txn_id']);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => (string)$pay['invoice_url'],
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_plisio_invoice_created', 'فاکتور ارزی ساخته شد. روی لینک کلیک کنید تا به Plisio منتقل شوید.'),
        ]);
    }


    private function handleNowPayment(int $amount): void
    {
        if (!function_exists('nowPayments')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_nowpayment_function_missing', '❌ تابع NowPayments روی این سرور موجود نیست.'));
        }


        $minRow = select('PaySetting', 'ValuePay', 'NamePay', 'minbalancenowpayment', 'select');
        $maxRow = select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancenowpayment', 'select');
        $min = is_array($minRow) ? (int)($minRow['ValuePay'] ?? 0) : 0;
        $max = is_array($maxRow) ? (int)($maxRow['ValuePay'] ?? 0) : 0;
        if ($min > 0 && $amount < $min) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_nowpayment_min_amount', '❌ حداقل مبلغ پرداخت NowPayments {min} تومان است.'), ['min' => number_format($min)]));
        }
        if ($max > 0 && $amount > $max) {
            FaoximaResponse::fail(422, faoxima_render_text(faoxima_textbot_get('dyn_paymentinit_nowpayment_max_amount', '❌ حداکثر مبلغ پرداخت NowPayments {max} تومان است.'), ['max' => number_format($max)]));
        }


        if (!function_exists('requireTronRates')) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_rate_module_not_loaded', '❌ ماژول نرخ ارز روی سرور بارگذاری نشده.'));
        }
        $rates = requireTronRates(['USD']);
        if (!is_array($rates) || !isset($rates['USD'])) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_rate_fetch_failed', '❌ دریافت نرخ ارز ناموفق بود. لطفاً چند دقیقه دیگر تلاش کنید.'));
        }
        $usd = (float)$rates['USD'];
        if ($usd <= 0) {
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_nowpayment_rate_invalid', '❌ نرخ ارز نامعتبر است.'));
        }
        $usdPrice = round($amount / $usd, 2);
        if ($usdPrice <= 0) {
            FaoximaResponse::fail(422, faoxima_textbot_get('dyn_paymentinit_amount_too_small', '❌ مبلغ پرداخت بسیار کم است.'));
        }

        $orderId = bin2hex(random_bytes(5));

        try {
            $pay = nowPayments('invoice', $usdPrice, $orderId, 'order');
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('nowPayments() threw', ['err' => $e->getMessage()]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_nowpayment_connection_error', '❌ خطا در ارتباط با NowPayments.'));
        }
        if (!is_array($pay) || empty($pay['id']) || empty($pay['invoice_url'])) {
            $errMsg = is_array($pay) ? json_encode($pay) : 'unknown';
            FaoximaLogger::userFacing('nowPayments() returned bad response', ['raw' => $errMsg]);
            FaoximaResponse::fail(502, faoxima_textbot_get('dyn_paymentinit_nowpayment_link_creation_failed', '❌ ساخت لینک پرداخت NowPayments ناموفق بود.'));
        }

        $this->insertPaymentReport('nowpayment', $amount, $orderId, (string)$pay['id']);

        FaoximaResponse::ok([
            'kind'     => 'url',
            'url'      => (string)$pay['invoice_url'],
            'order_id' => $orderId,
            'message'  => faoxima_textbot_get('dyn_paymentinit_nowpayment_invoice_created', 'فاکتور ارزی NowPayments ساخته شد. روی لینک کلیک کنید.'),
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
            FaoximaResponse::fail(503, faoxima_textbot_get('dyn_paymentinit_bot_username_not_configured', '❌ نام کاربری ربات روی سرور ثبت نشده است.'));
        }
        return 'https://t.me/' . $bot . '?start=' . $startParam;
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

        try {
            $pdo = FaoximaDb::pdo();
            $sql = 'INSERT INTO Payment_report (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
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

