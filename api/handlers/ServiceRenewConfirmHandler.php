<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceRenewConfirmHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $username = FaoximaInput::string($this->data, 'username');
        if ($username === '') {
            FaoximaResponse::badRequest('username is required');
        }


        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        if (!in_array((string)$invoice['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
            FaoximaResponse::fail(409, '❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.');
        }

        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (!empty($panel) && function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel((array)$panel)) {
            $emergencyMap = nmEmergencyReplacementMap();
            $srcCode = (string)$panel['code_panel'];
            if (isset($emergencyMap['by_code'][$srcCode])) {
                $panel = $emergencyMap['by_code'][$srcCode];
            }
        }
        if (empty($panel)) {
            FaoximaResponse::notFound('Panel not found');
        }
        if (($panel['status_extend'] ?? '') === 'off_extend') {
            FaoximaResponse::fail(409, '❌ امکان تمدید در این پنل وجود ندارد');
        }

        $agent = (string)($this->user['agent'] ?? 'f');


        $custom = FaoximaInput::array($this->data, 'custom');
        if (!empty($custom)) {
            $product = $this->buildCustomProduct($panel, $custom, $agent);
        } else {
            $code = FaoximaInput::string($this->data, 'product_code');
            if ($code === '') {
                FaoximaResponse::badRequest('product_code or custom is required');
            }
            $row = FaoximaDb::fetchOne(
                "SELECT * FROM product
                  WHERE (Location = :loc OR Location = '/all') AND code_product = :code
                    AND (agent = :agent OR agent = 'all')
                  LIMIT 1",
                [':loc' => $invoice['Service_location'], ':code' => $code, ':agent' => $agent]
            );
            if (!is_array($row)) {
                FaoximaResponse::fail(404, '❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.');
            }
            if (!$this->productIsAllowedForAgent($row, $agent)) {
                FaoximaResponse::fail(403, 'این محصول برای نوع کاربری شما فعال نیست');
            }
            $product = $row;
        }


        $discount = (int)($this->user['pricediscount'] ?? 0);
        $finalPrice = (float)$product['price_product'];
        if ($discount !== 0) {
            $finalPrice = $finalPrice - (($finalPrice * $discount) / 100);
        }
        $finalPrice = (int) round($finalPrice);


        $maxBuyAgent = (int)($this->user['maxbuyagent'] ?? 0);
        $balance = (float)($this->user['Balance'] ?? 0);
        if ($maxBuyAgent !== 0 && $agent === 'n2') {
            if (($balance - $finalPrice) < (-1 * $maxBuyAgent)) {
                FaoximaResponse::fail(403, '❌ مبلغ مجاز خرید شما به اتمام رسیده است.');
            }
        }


        $shortfall = $finalPrice - $balance;
        if ($shortfall > 0 && $agent !== 'n2') {
            $directBuyRow = select('shopSetting', '*', 'Namevalue', 'statusdirectpabuy', 'select');
            $directBuy = is_array($directBuyRow) ? (string)($directBuyRow['value'] ?? '') : '';
            $directBuyEnabled = ($directBuy === 'ondirectbuy');
            if (!$directBuyEnabled) {
                FaoximaResponse::fail(402, '❌ موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.');
            }


            $orderId = bin2hex(random_bytes(4));
            $amountDue = (int) ceil($shortfall);
            if ($amountDue <= 1) $amountDue = 0;


            update('user', 'Processing_value_one',  $invoice['username'] . '%' . $product['code_product'], 'id', $this->user['id']);
            update('user', 'Processing_value_tow',  'getextenduser',                                       'id', $this->user['id']);
            update('user', 'Processing_value_four', 'ext_' . $invoice['id_invoice'] . '_' . $finalPrice,    'id', $this->user['id']);

            FaoximaResponse::ok([
                'kind'        => 'requires_payment',
                'amount_due'  => $amountDue,
                'balance'     => $balance,
                'price'       => $finalPrice,
                'username'    => (string)$invoice['username'],
                'order_id'    => $orderId,
                'product'     => [
                    'name'        => (string)$product['name_product'],
                    'code'        => (string)$product['code_product'],
                    'volume_gb'   => (int)$product['Volume_constraint'],
                    'time_days'   => (int)$product['Service_time'],
                    'price'       => $finalPrice,
                ],
                'message'     => 'موجودی کیف پول کافی نیست. لطفاً مبلغ کسری را پرداخت کنید.',
            ]);
            return;
        }


        $cashback = 0;
        if ($finalPrice > 0) {
            $cashbackKey = $agent === 'f' ? 'chashbackextend' : 'chashbackextend_agent';
            $row = select('shopSetting', '*', 'Namevalue', $cashbackKey, 'select');
            $rawCashback = is_array($row) ? (string)($row['value'] ?? '') : '';
            $rate = 0;
            if ($agent === 'f') {
                $rate = (int)$rawCashback;
            } else {
                $decoded = json_decode($rawCashback, true);
                if (is_array($decoded)) {
                    $rate = (int)($decoded[$agent] ?? 0);
                }
            }
            if ($rate > 0) {
                $cashback = (int) round(($product['price_product'] * $rate) / 100);
                $finalPrice = $finalPrice - $cashback;
                if ($finalPrice < 0) $finalPrice = 0;
            }
        }


        $balanceCharged = false;
        if ($finalPrice > 0) {
            $allowNeg = ($agent === 'n2') ? (int)($this->user['maxbuyagent'] ?? 0) : 0;
            $charge = balance_atomic_charge($this->user['id'], $finalPrice, $allowNeg);
            if (empty($charge['ok'])) {
                FaoximaLogger::warn('Atomic balance charge failed at renew', [
                    'user_id' => $this->user['id'],
                    'invoice' => $invoice['id_invoice'] ?? null,
                    'price'   => $finalPrice,
                    'reason'  => $charge['reason'] ?? 'unknown',
                ]);
                FaoximaResponse::fail(402, '❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.');
            }
            $newBalance = $charge['new_balance'];
            $balanceCharged = true;
        } else {
            $newBalance = $balance;
        }


        $managePanel = new ManagePanel();

        $extend = $managePanel->extend(
            $panel['Methodextend'] ?? '',
            (int)$product['Volume_constraint'],
            (int)$product['Service_time'],
            (string)$invoice['username'],
            (string)$product['code_product'],
            (string)$panel['code_panel']
        );

        if (!is_array($extend) || ($extend['status'] ?? null) === false) {
            $reason = is_array($extend) ? json_encode($extend['msg'] ?? $extend) : (string)$extend;
            FaoximaLogger::error('ManagePanel->extend failed', [
                'user_id'  => $this->user['id'],
                'panel'    => $panel['name_panel'],
                'username' => $invoice['username'],
                'reason'   => $reason,
            ]);

            if ($balanceCharged) {
                balance_atomic_credit($this->user['id'], $finalPrice);
            }
            $this->reportError(
                "خطای تمدید سرویس\n" .
                "نام پنل : {$panel['name_panel']}\n" .
                "نام کاربری سرویس : {$invoice['username']}\n" .
                "دلیل خطا : {$reason}"
            );
            FaoximaResponse::fail(502, '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید');
        }


        try {
            $orderRand = bin2hex(random_bytes(2));
            $oldDataLimit = '';
            $oldExpire = '';
            try {
                $remote = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
                if (is_array($remote)) {
                    $oldDataLimit = (string)($remote['data_limit'] ?? '');
                    $oldExpire    = (string)($remote['expire'] ?? '');
                }
            } catch (Throwable $e) {  }

            $value = json_encode([
                'volumebuy'    => (int)$product['Volume_constraint'],
                'Service_time' => (int)$product['Service_time'],
                'oldvolume'    => $oldDataLimit,
                'oldtime'      => $oldExpire,
                'code_product' => (string)$product['code_product'],
                'id_order'     => $orderRand,
            ], JSON_UNESCAPED_UNICODE);

            $pdo = FaoximaDb::pdo();
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO service_other
                    (id_user, username, value, type, time, price, output, status)
                 VALUES (:u, :n, :v, 'extend_user', :t, :p, :o, 'paid')"
            );
            $stmt->execute([
                ':u' => $this->user['id'],
                ':n' => $invoice['username'],
                ':v' => $value,
                ':t' => date('Y/m/d H:i:s'),
                ':p' => (int)$product['price_product'],
                ':o' => json_encode($extend, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            FaoximaLogger::warn('service_other insert failed (extend)', ['err' => $e->getMessage()]);
        }


        update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);


        if (($invoice['name_product'] ?? '') === 'سرویس تست') {
            update('invoice', 'name_product',  $product['name_product'],  'id_invoice', $invoice['id_invoice']);
            update('invoice', 'price_product', $product['price_product'], 'id_invoice', $invoice['id_invoice']);
        }


        if ((int)($this->setting['scorestatus'] ?? 0) === 1) {
            $newScore = (int)($this->user['score'] ?? 0) + 2;
            update('user', 'score', $newScore, 'id', $this->user['id']);
        }

        FaoximaLogger::debug('Inline renewal completed', [
            'user_id'   => $this->user['id'],
            'username'  => $invoice['username'],
            'product'   => $product['code_product'] ?? null,
            'amount'    => $finalPrice,
            'cashback'  => $cashback,
        ]);

        FaoximaResponse::ok([
            'kind'          => 'done',
            'message'       => '✅ سرویس شما با موفقیت تمدید شد.',
            'cashback'      => $cashback,
            'balance_after' => $newBalance,
            'product'       => [
                'name'      => (string)$product['name_product'],
                'code'      => (string)$product['code_product'],
                'volume_gb' => (int)$product['Volume_constraint'],
                'time_days' => (int)$product['Service_time'],
                'price'     => $finalPrice,
            ],
        ]);
    }


    private function buildCustomProduct(array $panel, array $custom, string $agent): array
    {
        $volume = (int)($custom['volume_gb'] ?? 0);
        $time   = (int)($custom['time_days']  ?? 0);

        $minVol  = (int) $this->jsonAgentValue($panel['mainvolume'] ?? '', $agent);
        $maxVol  = (int) $this->jsonAgentValue($panel['maxvolume']  ?? '', $agent);
        $minTime = (int) $this->jsonAgentValue($panel['maintime']   ?? '', $agent);
        $maxTime = (int) $this->jsonAgentValue($panel['maxtime']    ?? '', $agent);
        $priceV  = (float) $this->jsonAgentValue($panel['pricecustomvolume'] ?? '', $agent);
        $priceT  = (float) $this->jsonAgentValue($panel['pricecustomtime']   ?? '', $agent);

        if ($volume > $maxVol || $volume < $minVol) {
            FaoximaResponse::badRequest("❌ حجم نامعتبر است (بین {$minVol} و {$maxVol} گیگابایت)");
        }
        if ($time > $maxTime || $time < $minTime) {
            FaoximaResponse::badRequest("❌ زمان نامعتبر است (بین {$minTime} و {$maxTime} روز)");
        }

        return [
            'code_product'      => 'customvolume',
            'name_product'      => '⚙️ سرویس دلخواه',
            'Volume_constraint' => $volume,
            'Service_time'      => $time,
            'Location'          => $panel['name_panel'],
            'price_product'     => ($volume * $priceV) + ($time * $priceT),
        ];
    }

    private function jsonAgentValue($raw, string $agent, $default = '')
    {
        if (is_array($raw)) {
            return $raw[$agent] ?? $default;
        }
        if (!is_string($raw) || $raw === '') return $default;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return $default;
        return $decoded[$agent] ?? $default;
    }

    private function reportError(string $text): void
    {
        $channel = (string)($this->setting['Channel_Report'] ?? '');
        if ($channel === '') return;
        $errorRow = select('topicid', 'idreport', 'report', 'errorreport', 'select');
        $topic = is_array($errorRow) ? (string)($errorRow['idreport'] ?? '') : '';
        try {
            telegram('sendmessage', [
                'chat_id'           => $channel,
                'message_thread_id' => $topic,
                'text'              => $text,
                'parse_mode'        => 'HTML',
            ]);
        } catch (Throwable $e) {  }
    }
}

