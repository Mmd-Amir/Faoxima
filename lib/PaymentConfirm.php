<?php


if (!function_exists('rx_payment_miniapp_button_kb')) {
    function rx_payment_miniapp_button_kb(): ?string {
        global $usernamebot, $domainhosts;
        $bot = isset($usernamebot) ? ltrim(trim((string)$usernamebot), '@') : '';

        $host = isset($domainhosts) ? trim((string)$domainhosts) : '';
        $host = rtrim(preg_replace('#^https?://#', '', $host), '/');
        if ($host === '') return null;
        $webAppUrl = 'https://' . $host . '/app/';

        return json_encode([
            'inline_keyboard' => [
                [['text' => '🚀 بازکردن مینی‌اپ', 'web_app' => ['url' => $webAppUrl]]],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}


if (!function_exists('rx_payment_retry_kb')) {
    function rx_payment_retry_kb(): ?string {
        global $usernamebot, $domainhosts;
        $bot = isset($usernamebot) ? ltrim(trim((string)$usernamebot), '@') : '';

        $host = isset($domainhosts) ? trim((string)$domainhosts) : '';
        $host = rtrim(preg_replace('#^https?://#', '', $host), '/');
        $miniAppUrl = $host !== '' ? ('https://' . $host . '/app/') : '';

        $supportRow = function_exists('select')
            ? select('setting', 'support_username', null, null, 'select')
            : null;
        $supportHandle = '';
        if (is_array($supportRow) && !empty($supportRow['support_username'])) {
            $supportHandle = ltrim(trim((string)$supportRow['support_username']), '@');
        }
        $supportUrl = $supportHandle !== ''
            ? 'https://t.me/' . $supportHandle
            : ($bot !== '' ? 'https://t.me/' . $bot : '');

        $rows = [];
        if ($miniAppUrl !== '') {
            $rows[] = [['text' => '🔁 شارژ مجدد', 'web_app' => ['url' => $miniAppUrl . '#/recharge']]];
        }
        if ($supportUrl !== '') {
            $rows[] = [['text' => '📞 پشتیبانی', 'url' => $supportUrl]];
        }
        if (empty($rows)) return null;

        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }
}


if (!function_exists('payment_confirm_paid')) {


    function payment_confirm_paid(string $orderId, string $cashbackKey, array $reportData = []): array
    {
        global $pdo, $connect, $setting;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo unavailable'];
        }

        $atomic = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid' "
            . "WHERE id_order = :id AND payment_Status <> 'paid'"
        );
        $atomic->bindValue(':id', $orderId, PDO::PARAM_STR);
        $atomic->execute();
        if ($atomic->rowCount() < 1) {
            return ['ok' => false, 'reason' => 'already paid or missing'];
        }

        $report = select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!is_array($report)) {
            return ['ok' => false, 'reason' => 'report missing after update'];
        }

        if (function_exists('DirectPayment')) {
            $imagePath = __DIR__ . '/../images.jpeg';
            if (!is_file($imagePath)) {
                $imagePath = __DIR__ . '/../images.jpg';
            }
            DirectPayment($report['id_order'], $imagePath);
        }


        $userId = (string)($report['id_user'] ?? '');
        $balanceUser = select('user', '*', 'id', $userId, 'select');
        if (!is_array($balanceUser)) {
            $balanceUser = ['id' => $userId, 'username' => '—', 'Balance' => 0];
        }

        $cashbackPercent = '0';
        if ($cashbackKey !== '') {
            $row = select('PaySetting', 'ValuePay', 'NamePay', $cashbackKey, 'select');
            if (is_array($row)) {
                $cashbackPercent = (string)($row['ValuePay'] ?? '0');
            }
        }

        $cashbackAmount = 0;
        if ($cashbackPercent !== '' && $cashbackPercent !== '0') {
            $cashbackAmount = ((int)($report['price'] ?? 0) * (int)$cashbackPercent) / 100;
            $newBalance = (int)($balanceUser['Balance'] ?? 0) + $cashbackAmount;
            update('user', 'Balance', $newBalance, 'id', $balanceUser['id']);
            if (function_exists('sendmessage')) {
                sendmessage(
                    $balanceUser['id'],
                    "🎁 کاربر عزیز مبلغ {$cashbackAmount} تومان به عنوان هدیه واریز به حساب شما واریز گردید.",
                    null,
                    'HTML'
                );
            }
        }


        if (function_exists('telegram')) {
            $balanceNow = (int)select('user', 'Balance', 'id', $userId, 'select')['Balance'];
            $priceFmt   = number_format((int)($report['price'] ?? 0));
            $balanceFmt = number_format($balanceNow);
            $userText = "✅ <b>پرداخت شما با موفقیت تایید شد</b>\n\n"
                      . "🛒 کد فاکتور: <code>" . htmlspecialchars($report['id_order']) . "</code>\n"
                      . "💰 مبلغ: <b>{$priceFmt}</b> تومان\n"
                      . "💎 موجودی جدید: <b>{$balanceFmt}</b> تومان";
            $kb = rx_payment_miniapp_button_kb();
            telegram('sendmessage', array_filter([
                'chat_id'      => $userId,
                'text'         => $userText,
                'parse_mode'   => 'HTML',
                'reply_markup' => $kb,
            ]));
        }


        $method = (string)($reportData['method'] ?? ($report['Payment_Method'] ?? 'gateway'));
        $extraLines = $reportData['extra_lines'] ?? [];
        $linkLabel  = (string)($reportData['link_label'] ?? '');
        $linkUrl    = (string)($reportData['link_url']   ?? '');

        $textReport = "💵 پرداخت جدید\n" .
            "- 👤 نام کاربری کاربر : @{$balanceUser['username']}\n" .
            "- ‏🆔آیدی عددی کاربر : {$balanceUser['id']}\n" .
            "- 💸 مبلغ تراکنش {$report['price']}\n";
        if ($linkLabel !== '' && $linkUrl !== '') {
            $textReport .= "- 🔗 <a href=\"{$linkUrl}\">{$linkLabel}</a>\n";
        }
        foreach ((array)$extraLines as $line) {
            $textReport .= '- ' . $line . "\n";
        }
        $textReport .= "- 💳 روش پرداخت :  {$method}";

        $channelReport = is_array($setting ?? null) ? (string)($setting['Channel_Report'] ?? '') : '';
        if ($channelReport !== '' && function_exists('telegram')) {
            $payload = [
                'chat_id'    => $channelReport,
                'text'       => $textReport,
                'parse_mode' => 'HTML',
            ];
            if (!empty($reportData['thread_id'])) {
                $payload['message_thread_id'] = $reportData['thread_id'];
            }
            telegram('sendmessage', $payload);
        }

        return [
            'ok'              => true,
            'order_id'        => $report['id_order'],
            'user_id'         => $balanceUser['id'],
            'amount'          => (int)($report['price'] ?? 0),
            'cashback_amount' => $cashbackAmount,
        ];
    }
}


if (!function_exists('payment_notify_user_failed')) {


    function payment_notify_user_failed(string $orderId, string $reason = ''): bool
    {
        $report = select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!is_array($report)) return false;
        $status = strtolower((string)($report['payment_Status'] ?? ''));
        if ($status === 'paid') return false;

        update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);

        if (!function_exists('telegram')) return true;

        $priceFmt = number_format((int)($report['price'] ?? 0));
        $reasonFa = trim($reason) !== '' ? trim($reason) : 'تراکنش از سمت درگاه لغو شد';

        $text = "❌ <b>پرداخت تأیید نشد</b>\n\n"
              . "🛒 کد فاکتور: <code>" . htmlspecialchars($orderId) . "</code>\n"
              . "💸 مبلغ: <b>{$priceFmt}</b> تومان\n"
              . "📝 دلیل: " . htmlspecialchars($reasonFa) . "\n\n"
              . "اگر مطمئنید پرداخت انجام شده، با پشتیبانی تماس بگیرید.";
        $kb = rx_payment_retry_kb();
        telegram('sendmessage', array_filter([
            'chat_id'      => (string)$report['id_user'],
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $kb,
        ]));
        return true;
    }
}


if (!function_exists('payment_mark_expired')) {


    function payment_mark_expired(string $orderId, ?string $textExpire = null): bool
    {
        $report = select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!is_array($report)) return false;
        $status = strtolower((string)($report['payment_Status'] ?? ''));
        if ($status === 'paid') return false;

        update('Payment_report', 'payment_Status', 'expire', 'id_order', $orderId);

        if (!function_exists('telegram')) return true;

        $priceFmt = number_format((int)($report['price'] ?? 0));
        $defaultText = "⏰ <b>پرداخت بعد از ۱۰ دقیقه تأیید نشد</b>\n\n"
                     . "🛒 کد فاکتور: <code>" . htmlspecialchars($orderId) . "</code>\n"
                     . "💸 مبلغ: <b>{$priceFmt}</b> تومان\n\n"
                     . "💡 اگر پرداخت کرده‌اید، ممکنه شبکه‌ی کریپتو هنوز confirm نکرده باشه. "
                     . "چند دقیقه دیگر صبر کنید (تا ۲۰ دقیقه برای TRX/TON معمولاً).\n"
                     . "اگر هنوز تأیید نشد، با پشتیبانی تماس بگیرید.";

        $text = ($textExpire !== null && trim($textExpire) !== '') ? $textExpire : $defaultText;
        $kb   = rx_payment_retry_kb();
        telegram('sendmessage', array_filter([
            'chat_id'      => (string)$report['id_user'],
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $kb,
        ]));
        return true;
    }
}
