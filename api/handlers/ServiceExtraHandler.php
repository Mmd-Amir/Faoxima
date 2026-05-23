<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceExtraHandler extends BaseHandler
{


    public ?string $mode = null;

    public function handle(): void
    {
        $mode = $this->mode ?? FaoximaInput::string($this->data, '_mode');
        if ($mode === 'quote') {
            $this->requireMethod('GET');
            $this->handleQuote();
            return;
        }
        if ($mode === 'confirm') {
            $this->requireMethod('POST');
            $this->handleConfirm();
            return;
        }
        FaoximaResponse::badRequest('Unknown mode for service_extra');
    }

    private function handleQuote(): void
    {
        [$invoice, $panel, $kind, $pricePerUnit, $bounds] = $this->resolveContext('GET');

        $amount = FaoximaInput::int($this->data, 'amount', 0);
        $total = null;
        if ($amount > 0) {
            $total = $amount * $pricePerUnit;
        }

        FaoximaResponse::ok([
            'username'        => (string)$invoice['username'],
            'kind'            => $kind,
            'price_per_unit'  => (int)$pricePerUnit,
            'unit_label'      => $kind === 'time' ? 'روز' : 'گیگابایت',
            'min'             => $bounds[0],
            'max'             => $bounds[1],
            'amount'          => $amount,
            'total_price'     => $total === null ? null : (int)$total,
            'balance'         => (float)($this->user['Balance'] ?? 0),
        ]);
    }

    private function handleConfirm(): void
    {
        [$invoice, $panel, $kind, $pricePerUnit, $bounds] = $this->resolveContext('POST');

        $amount = FaoximaInput::int($this->data, 'amount', 0);
        if ($amount <= 0) {
            FaoximaResponse::badRequest('amount must be > 0');
        }
        if (!ctype_digit((string)$amount)) {
            FaoximaResponse::badRequest('amount must be a positive integer');
        }
        if (count($bounds) === 2 && $bounds[0] > 0 && $amount < $bounds[0]) {
            FaoximaResponse::fail(422, "❌ مقدار باید حداقل {$bounds[0]} باشد");
        }
        if (count($bounds) === 2 && $bounds[1] > 0 && $amount > $bounds[1]) {
            FaoximaResponse::fail(422, "❌ مقدار باید حداکثر {$bounds[1]} باشد");
        }

        $price = (float) ($amount * $pricePerUnit);


        $discount = (int)($this->user['pricediscount'] ?? 0);
        $finalPrice = $price;
        if ($discount !== 0) {
            $finalPrice = $finalPrice - (($finalPrice * $discount) / 100);
        }
        $finalPrice = (int) round($finalPrice);

        $balance = (float)($this->user['Balance'] ?? 0);
        $agent = (string)($this->user['agent'] ?? 'f');


        $maxBuyAgent = (int)($this->user['maxbuyagent'] ?? 0);
        if ($maxBuyAgent !== 0 && $agent === 'n2') {
            if (($balance - $finalPrice) < (-1 * $maxBuyAgent)) {
                FaoximaResponse::fail(403, '❌ مبلغ مجاز خرید شما به اتمام رسیده است.');
            }
        }


        $shortfall = $finalPrice - $balance;
        if ($shortfall > 0 && $agent !== 'n2') {
            $directBuyRow = select('shopSetting', '*', 'Namevalue', 'statusdirectpabuy', 'select');
            $directBuy = is_array($directBuyRow) ? (string)($directBuyRow['value'] ?? '') : '';
            if ($directBuy !== 'ondirectbuy') {
                FaoximaResponse::fail(402, '❌ موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.');
            }

            $orderId = bin2hex(random_bytes(4));
            $amountDue = (int) ceil($shortfall);
            if ($amountDue <= 1) $amountDue = 0;


            $extendStateTow = $kind === 'time' ? 'getextratimeuser' : 'getextravolumeuser';
            update('user', 'Processing_value_one', $invoice['username'] . '%' . $amount, 'id', $this->user['id']);
            update('user', 'Processing_value_tow', $extendStateTow,                       'id', $this->user['id']);

            FaoximaResponse::ok([
                'kind'        => 'requires_payment',
                'amount_due'  => $amountDue,
                'balance'     => $balance,
                'price'       => $finalPrice,
                'username'    => (string)$invoice['username'],
                'order_id'    => $orderId,
                'extra_kind'  => $kind,
                'extra_amount'=> $amount,
                'message'     => 'موجودی کیف پول کافی نیست. لطفاً مبلغ کسری را پرداخت کنید.',
            ]);
            return;
        }


        $balanceCharged = false;
        if ($finalPrice > 0) {
            $allowNeg = ($agent === 'n2') ? (int)($this->user['maxbuyagent'] ?? 0) : 0;
            $charge = balance_atomic_charge($this->user['id'], $finalPrice, $allowNeg);
            if (empty($charge['ok'])) {
                FaoximaLogger::warn('Atomic balance charge failed at extra', [
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
        if ($kind === 'time') {
            $result = $managePanel->extra_time(
                (string)$invoice['username'],
                (string)$panel['code_panel'],
                (int)$amount
            );
        } else {
            $result = $managePanel->extra_volume(
                (string)$invoice['username'],
                (string)$panel['code_panel'],
                (int)$amount
            );
        }

        if (!is_array($result) || ($result['status'] ?? null) === false) {
            $reason = is_array($result) ? json_encode($result['msg'] ?? $result) : (string)$result;
            FaoximaLogger::error('ManagePanel extra_* failed', [
                'kind'     => $kind,
                'user_id'  => $this->user['id'],
                'panel'    => $panel['name_panel'],
                'username' => $invoice['username'],
                'reason'   => $reason,
            ]);

            if ($balanceCharged) {
                balance_atomic_credit($this->user['id'], $finalPrice);
            }
            $this->reportError(
                ($kind === 'time' ? 'خطای خرید زمان اضافه' : 'خطای خرید حجم اضافه') .
                "\nنام پنل : {$panel['name_panel']}\n" .
                "نام کاربری سرویس : {$invoice['username']}\n" .
                "دلیل خطا : {$reason}"
            );
            $human = $kind === 'time'
                ? '❌ خطایی در خرید زمان اضافه رخ داده با پشتیبانی در ارتباط باشید'
                : '❌ خطایی در خرید حجم اضافه رخ داده با پشتیبانی در ارتباط باشید';
            FaoximaResponse::fail(502, $human);
        }


        try {
            $oldDataLimit = '';
            $oldExpire = '';
            try {
                $remote = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
                if (is_array($remote)) {
                    $oldDataLimit = (string)($remote['data_limit'] ?? '');
                    $oldExpire    = (string)($remote['expire'] ?? '');
                }
            } catch (Throwable $e) {  }

            if ($kind === 'time') {
                $value = json_encode([
                    'day'             => $amount,
                    'priceـper_day'   => (int)$pricePerUnit,
                    'old_volume'      => $oldDataLimit,
                    'expire_old'      => $oldExpire,
                ], JSON_UNESCAPED_UNICODE);
                $type = 'extra_time_user';
            } else {
                $value = json_encode([
                    'volume_value'    => $amount,
                    'priceـper_gig'   => (int)$pricePerUnit,
                    'old_volume'      => $oldDataLimit,
                    'expire_old'      => $oldExpire,
                ], JSON_UNESCAPED_UNICODE);
                $type = 'extra_user';
            }

            $pdo = FaoximaDb::pdo();
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO service_other
                    (id_user, username, value, type, time, price, output)
                 VALUES (:u, :n, :v, :ty, :t, :p, :o)'
            );
            $stmt->execute([
                ':u'  => $this->user['id'],
                ':n'  => $invoice['username'],
                ':v'  => $value,
                ':ty' => $type,
                ':t'  => date('Y/m/d H:i:s'),
                ':p'  => $finalPrice,
                ':o'  => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            FaoximaLogger::warn('service_other insert failed (extra)', ['err' => $e->getMessage()]);
        }

        if ($kind === 'time') {
            update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
        }

        FaoximaLogger::debug('Inline extra purchase completed', [
            'kind'     => $kind,
            'user_id'  => $this->user['id'],
            'username' => $invoice['username'],
            'amount'   => $amount,
            'price'    => $finalPrice,
        ]);

        $msg = $kind === 'time'
            ? "✅ {$amount} روز به سرویس شما اضافه شد."
            : "✅ {$amount} گیگابایت به سرویس شما اضافه شد.";

        FaoximaResponse::ok([
            'kind'          => 'done',
            'message'       => $msg,
            'extra_kind'    => $kind,
            'extra_amount'  => $amount,
            'price'         => $finalPrice,
            'balance_after' => $newBalance,
        ]);
    }


    private function resolveContext(string $method): array
    {
        $username = FaoximaInput::string($this->data, 'username');
        if ($username === '') {
            FaoximaResponse::badRequest('username is required');
        }
        $kind = strtolower(FaoximaInput::string($this->data, 'kind'));
        if (!in_array($kind, ['time', 'volume'], true)) {
            FaoximaResponse::badRequest("kind must be 'time' or 'volume'");
        }

        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        if ($method === 'POST'
            && !in_array((string)$invoice['Status'], ['active','end_of_time','end_of_volume','sendedwarn','send_on_hold'], true)) {
            FaoximaResponse::fail(409, '❌ خرید با خطا مواجه گردید مراحل را مجدد انجام دهید.');
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
            FaoximaResponse::fail(409, $kind === 'time'
                ? '❌ امکان خرید زمان اضافه در این پنل وجود ندارد'
                : '❌ امکان خرید حجم اضافه در این پنل وجود ندارد');
        }


        $key = $kind === 'time' ? 'statustimeextra' : 'statusextra';
        $row = select('shopSetting', '*', 'Namevalue', $key, 'select');
        $val = is_array($row) ? (string)($row['value'] ?? '') : '';
        $off = $kind === 'time' ? 'offtimeextraa' : 'offextra';
        if ($val === $off) {
            FaoximaResponse::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }


        if ($kind === 'time' && ($invoice['name_product'] ?? '') === 'سرویس تست') {
            FaoximaResponse::fail(409, '❌ این قابلیت برای سرویس تست در دسترس نیست');
        }

        $agent = (string)($this->user['agent'] ?? 'f');
        $priceField = $kind === 'time' ? 'priceextratime' : 'priceextravolume';
        $pricePerUnit = (int) $this->jsonAgentValue($panel[$priceField] ?? '', $agent, 0);
        if ($pricePerUnit <= 0) {
            FaoximaResponse::fail(503, '❌ تعرفه خرید اضافه روی این پنل تنظیم نشده است.');
        }


        if ($kind === 'time') {
            $min = max(1, (int) $this->jsonAgentValue($panel['maintime'] ?? '', $agent, 1));
            $max = (int) $this->jsonAgentValue($panel['maxtime'] ?? '', $agent, 0);
        } else {
            $min = max(1, (int) $this->jsonAgentValue($panel['mainvolume'] ?? '', $agent, 1));
            $max = (int) $this->jsonAgentValue($panel['maxvolume'] ?? '', $agent, 0);
        }
        if ($max <= 0) $max = 9999;

        return [$invoice, $panel, $kind, $pricePerUnit, [$min, $max]];
    }

    private function jsonAgentValue($raw, string $agent, $default = '')
    {
        if (is_array($raw)) return $raw[$agent] ?? $default;
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

