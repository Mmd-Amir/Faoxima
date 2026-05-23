<?php



function nm_appendInfoCardQrButton($existing, $invoice_id)
{
    $kb = ['inline_keyboard' => []];
    if (is_string($existing) && $existing !== '') {
        $decoded = json_decode($existing, true);
        if (is_array($decoded) && isset($decoded['inline_keyboard']) && is_array($decoded['inline_keyboard'])) {
            $kb = $decoded;
        }
    } elseif (is_array($existing) && isset($existing['inline_keyboard'])) {
        $kb = $existing;
    }
    $qrButton = ['text' => '📷 دریافت QR Code', 'callback_data' => 'infocard_qr_' . $invoice_id];
    array_unshift($kb['inline_keyboard'], [$qrButton]);
    return json_encode($kb, JSON_UNESCAPED_UNICODE);
}


function nm_sendInfoCardsForServiceList($from_id, array $services)
{
    if (!function_exists('nm_renderInfoCardForInvoice') || !function_exists('telegram')) {
        return;
    }
    if (empty($services)) {
        return;
    }


    $cap = defined('INFOCARD_LIST_MAX') ? (int) INFOCARD_LIST_MAX : 8;
    $sent = 0;
    foreach ($services as $row) {
        if ($sent >= $cap) {
            break;
        }
        if (!is_array($row) || empty($row['username']) || empty($row['id_invoice'])) {
            continue;
        }
        $panel = select("marzban_panel", "*", "name_panel", $row['Service_location'] ?? '', "select");
        if (!is_array($panel)) {
            continue;
        }
        $cardPath = nm_renderInfoCardForInvoice($panel, $row['username'], $row['id_invoice'], $from_id);
        if ($cardPath === null) {
            continue;
        }
        $note = isset($row['note']) && $row['note'] !== '' ? ' | ' . $row['note'] : '';
        $caption = '✨ <b>' . htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') . '</b>'
            . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
        $kb = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '🔧 مدیریت سرویس', 'callback_data' => 'product_' . $row['id_invoice']],
                    ['text' => '📷 دریافت QR Code', 'callback_data' => 'infocard_qr_' . $row['id_invoice']],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        try {
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($cardPath),
                'reply_markup' => $kb,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);
            $sent++;
        } catch (\Throwable $e) {
            error_log('nm_sendInfoCardsForServiceList send failed: ' . $e->getMessage());
        }
        @unlink($cardPath);
    }
}


function rxRenderPremiumEmojiPanel($from_id, $page = 1) {
    global $pdo, $setting;
    $pageSize = 10;
    $page = max(1, (int)$page);

    $rows = [];
    try {
        $stmt = $pdo->query("SELECT id, emoji, custom_emoji_id FROM premium_emojis ORDER BY id ASC");
        if ($stmt) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $rows[] = $r; }
        }
    } catch (Throwable $e) { $rows = []; }

    $total = count($rows);
    $totalPages = max(1, (int)ceil($total / $pageSize));
    if ($page > $totalPages) { $page = $totalPages; }
    $sliceStart = ($page - 1) * $pageSize;
    $pageItems = array_slice($rows, $sliceStart, $pageSize);

    $statusVal = (string)($setting['premium_emoji_status'] ?? '0');
    $statusText = ($statusVal === '1') ? "☑️ فعال" : "🚫 غیرفعال";

    $msg  = "🌟 <b>تنظیمات ایموجی پرمیوم</b>\n\n";
    $msg .= "وضعیت کلی: <b>{$statusText}</b>\n";
    $msg .= "تعداد ایموجی‌های ثبت‌شده: <b>{$total}</b>";
    if ($totalPages > 1) {
        $msg .= "  ·  📄 صفحه <b>{$page}</b> از <b>{$totalPages}</b>";
    }
    $msg .= "\n\n";
    if (empty($rows)) {
        $msg .= "📭 هنوز هیچ ایموجی‌ای ثبت نشده است.\n\n";
        $msg .= "برای افزودن، روی دکمه «➕ افزودن ایموجی جدید» بزنید.";
    } else {
        $msg .= "📋 <b>لیست ایموجی‌ها</b>\n\n";
        foreach ($pageItems as $it) {
            $em = (string)$it['emoji'];
            $cid = (string)$it['custom_emoji_id'];
            $cidShort = mb_strlen($cid) > 14 ? mb_substr($cid, 0, 14) . '…' : $cid;


            $msg .= "{$em} ↦ <code>{$cidShort}</code>\n";
        }
        $msg .= "\nبرای ویرایش/حذف، از دکمه‌های زیر استفاده کنید.";
    }

    $kb = ['inline_keyboard' => []];
    foreach ($pageItems as $it) {
        $em = (string)$it['emoji'];
        $id = (int)$it['id'];


        $kb['inline_keyboard'][] = [
            ['text' => "{$em}", 'callback_data' => "premium_emoji_noop"],
            ['text' => "✏️ ویرایش", 'callback_data' => "premium_emoji_edit_{$id}"],
            ['text' => "🗑 حذف", 'callback_data' => "premium_emoji_del_{$id}"],
        ];
    }
    if ($totalPages > 1) {
        $prevCb = ($page > 1) ? "premium_emoji_settings_" . ($page - 1) : "premium_emoji_noop";
        $prevTxt = ($page > 1) ? "« قبل" : "▫️";
        $nextCb = ($page < $totalPages) ? "premium_emoji_settings_" . ($page + 1) : "premium_emoji_noop";
        $nextTxt = ($page < $totalPages) ? "بعد »" : "▫️";
        $kb['inline_keyboard'][] = [
            ['text' => $prevTxt, 'callback_data' => $prevCb],
            ['text' => "📄 {$page} / {$totalPages}", 'callback_data' => "premium_emoji_noop"],
            ['text' => $nextTxt, 'callback_data' => $nextCb],
        ];
    }
    $kb['inline_keyboard'][] = [
        ['text' => "➕ افزودن ایموجی جدید", 'callback_data' => "premium_emoji_add"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => ($statusVal === '1' ? "🚫 خاموش کردن قابلیت" : "☑️ روشن کردن قابلیت"),
         'callback_data' => "editstsuts-premiumemoji-{$statusVal}"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🔙 بازگشت", 'callback_data' => "featcat_main"],
        ['text' => "🔄 رفرش", 'callback_data' => "premium_emoji_settings_{$page}"],
        ['text' => "❌ بستن", 'callback_data' => "close_stat"],
    ];


    global $callback_query_id, $message_id;
    $kbJson = json_encode($kb, JSON_UNESCAPED_UNICODE);
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $delivered = false;

    if ($isCallback && function_exists('Editmessagetext')) {
        try {
            $editResult = Editmessagetext($from_id, $message_id, $msg, $kbJson, 'HTML');
            if (is_array($editResult) && !empty($editResult['ok'])) {
                $delivered = true;
                if (function_exists('telegram')) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 1,
                        ]);
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {
            error_log('rxRenderPremiumEmojiPanel Editmessagetext failed: ' . $e->getMessage());
        }
    }


    if (!$delivered && function_exists('telegram')) {
        if ($isCallback) {
            try {
                $rawEdit = telegram('editmessagetext', [
                    'chat_id'      => $from_id,
                    'message_id'   => $message_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
                if (is_array($rawEdit) && !empty($rawEdit['ok'])) {
                    $delivered = true;
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 1,
                        ]);
                    } catch (Throwable $e) {}
                }
            } catch (Throwable $e) {}
        }
        if (!$delivered) {
            if ($isCallback && function_exists('deletemessage')) {
                try { @deletemessage($from_id, $message_id); } catch (Throwable $e) {}
            }
            try {
                telegram('sendmessage', [
                    'chat_id'      => $from_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
            } catch (Throwable $e) {
                error_log('rxRenderPremiumEmojiPanel send failed: ' . $e->getMessage());
            }
        }
    }
}
if (!function_exists('crypto_supported_currencies')) {
    function crypto_supported_currencies(): array
    {
        return [
            'TRX'        => ['network' => 'TRON', 'decimals' => 6, 'label' => 'ترون (TRX)',           'fa_short' => 'ترون'],
            'TON'        => ['network' => 'TON',  'decimals' => 9, 'label' => 'تون (TON)',            'fa_short' => 'تون'],
            'USDT_TRC20' => ['network' => 'TRON', 'decimals' => 6, 'label' => 'تتر روی شبکه ترون',     'fa_short' => 'تتر-ترون'],
            'USDT_TON'   => ['network' => 'TON',  'decimals' => 6, 'label' => 'تتر روی شبکه تون',      'fa_short' => 'تتر-تون'],
        ];
    }
}

if (!function_exists('crypto_pay_setting')) {
    function crypto_pay_setting(string $name, string $default = ''): string
    {
        $row = function_exists('select') ? select('PaySetting', 'ValuePay', 'NamePay', $name, 'select') : null;
        if (is_array($row) && isset($row['ValuePay'])) {
            $v = trim((string) $row['ValuePay']);
            if ($v !== '') return $v;
        }
        return $default;
    }
}

if (!function_exists('crypto_extract_hash')) {


    function crypto_extract_hash($input): ?string
    {
        if (!is_string($input)) return null;
        $input = trim($input);
        if ($input === '') return null;

        $input = preg_replace('/\s+/u', ' ', $input);
        $input = str_replace(["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], '', (string) $input);
        $input = trim((string) $input);

        if (preg_match('~(?:tronscan\.org|tonscan\.org|tonviewer\.com|tonapi\.io)[^\s]*?/(?:tx|transaction|transactions|events)/([0-9a-fA-F]{64})~i', $input, $m)) {
            return strtolower($m[1]);
        }

        if (preg_match('/[0-9a-fA-F]{64}/', $input, $m)) {
            return strtolower($m[0]);
        }

        if (preg_match('~(?:tronscan\.org|tonscan\.org|tonviewer\.com|tonapi\.io)[^\s]*?/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{43,44}=?)~i', $input, $m)) {
            return $m[1];
        }
        if (preg_match('~/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{43,44}=?)(?:[/?#]|$)~', $input, $m)) {
            return $m[1];
        }

        if (preg_match('/^[A-Za-z0-9_\-+\/]{43}=?$/', $input)) {
            return $input;
        }

        return null;
    }
}

if (!function_exists('crypto_active_wallet')) {
    function crypto_active_wallet(string $currency): ?array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return null;
        try {
            $stmt = $pdo->prepare("SELECT * FROM crypto_wallets WHERE currency = :c AND enabled = 1 LIMIT 1");
            $stmt->execute([':c' => $currency]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) return null;
            $row['wallet_address'] = trim((string) ($row['wallet_address'] ?? ''));
            if ($row['wallet_address'] === '') return null;
            return $row;
        } catch (Throwable $e) {
            error_log('[crypto] active_wallet: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('crypto_active_wallets')) {
    function crypto_active_wallets(): array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return [];
        try {
            $stmt = $pdo->query("SELECT currency, network, wallet_address, label
                                   FROM crypto_wallets
                                  WHERE enabled = 1 AND wallet_address <> ''
                                  ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('crypto_validate_address')) {
    function crypto_validate_address(string $address, string $network): bool
    {
        $address = trim($address);
        if ($network === 'TRON') {
            return (bool) preg_match('/^T[A-Za-z0-9]{33}$/', $address);
        }
        if ($network === 'TON') {


            if (preg_match('/^(?:EQ|UQ|kQ|Ef|Uf|0Q)[A-Za-z0-9_\-]{46}$/', $address)) return true;
            if (preg_match('/^-?\d+:[0-9a-fA-F]{64}$/', $address)) return true;
            return false;
        }
        return mb_strlen($address) >= 20 && mb_strlen($address) <= 200;
    }
}

if (!function_exists('crypto_save_wallet')) {
    function crypto_save_wallet(string $currency, string $address): bool
    {
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $network = $supported[$currency]['network'];
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("INSERT INTO crypto_wallets (currency, network, wallet_address, label, enabled)
                                  VALUES (:c, :n, :a, :l, 1)
                                  ON DUPLICATE KEY UPDATE wallet_address = VALUES(wallet_address),
                                                          network        = VALUES(network),
                                                          enabled        = 1");
            $up->execute([
                ':c' => $currency,
                ':n' => $network,
                ':a' => $address,
                ':l' => $supported[$currency]['label'],
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] save_wallet: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_delete_wallet')) {

    function crypto_delete_wallet(string $currency): bool
    {
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("UPDATE crypto_wallets
                                    SET wallet_address = '', wallet_memo = '', enabled = 0
                                  WHERE currency = :c");
            $up->execute([':c' => $currency]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] delete_wallet: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_invoice_button_style')) {
    function crypto_invoice_button_style(string $key): ?string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
            if ($pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode((string) $row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['invoice_copy_buttons']) && is_array($all['invoice_copy_buttons'])) {
                            $cache = $all['invoice_copy_buttons'];
                        }
                    }
                } catch (Throwable $e) {  }
            }
        }
        $style = $cache[$key] ?? null;
        if ($style === null || $style === '' || $style === 'default') return null;
        return (string) $style;
    }
}

if (!function_exists('cm_apply_payment')) {
    function cm_apply_payment(string $orderId, int $finalIrr, array $payment, $adminId, $adminUsername, array $setting, $paymentreports, $messageId, $callbackQueryId, string $textInline = ''): void
    {
        $userRow = function_exists('select') ? select('user', '*', 'id', $payment['id_user'], 'select') : null;
        $oldBalance = is_array($userRow) ? (int) ($userRow['Balance'] ?? 0) : 0;
        $newBalance = $oldBalance + $finalIrr;
        if (function_exists('update')) {
            update('user', 'Balance', $newBalance, 'id', $payment['id_user']);
            update('Payment_report', 'payment_Status', 'paid', 'id_order', $orderId);
            update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $orderId);
        }
        $coinAmt = rtrim(rtrim(number_format((float) ($payment['crypto_amount'] ?? 0), 9, '.', ''), '0'), '.');
        $explorerUrl = function_exists('crypto_explorer_url')
            ? crypto_explorer_url((string) ($payment['crypto_currency'] ?? ''), (string) ($payment['crypto_tx_hash'] ?? ''))
            : (string) ($payment['crypto_tx_hash'] ?? '');
        if (function_exists('sendmessage')) {
            sendmessage(
                (string) $payment['id_user'],
                "✅ <b>درخواست بررسی دستی شما تایید شد و کیف پول‌تان شارژ گردید.</b>\n\n"
                . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                . "💎 ارز: <b>" . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "</b>\n"
                . "🪙 مقدار: <code>{$coinAmt}</code>\n"
                . "💵 مبلغ شارژ شده: " . number_format($finalIrr) . " تومان\n"
                . "💰 موجودی جدید: " . number_format($newBalance) . " تومان\n"
                . "🔗 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده تراکنش</a>",
                null,
                'HTML'
            );
        }
        if ($callbackQueryId && function_exists('telegram')) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text' => '✅ تایید شد و کیف پول شارژ شد.',
                'cache_time' => 1,
            ]);
        }
        if ($messageId && function_exists('Editmessagetext')) {
            $doneNote = "\n\n━━━━━━━━━━━━\n✅ <b>تایید شده</b> (مبلغ: " . number_format($finalIrr) . " تومان)\n👨‍💼 توسط ادمین: <code>{$adminId}</code>\n⏰ " . date('Y/m/d H:i:s');
            @Editmessagetext($adminId, $messageId, $textInline . $doneNote, null);
        }
        if (!empty($setting['Channel_Report']) && function_exists('telegram')) {
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'text'    => "✅ بررسی دستی پرداخت کریپتو تایید شد\n\n"
                    . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                    . "👤 کاربر: <code>{$payment['id_user']}</code>\n"
                    . "💎 ارز: " . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "\n"
                    . "🪙 مقدار: <code>{$coinAmt}</code>\n"
                    . "💵 مبلغ شارژ: " . number_format($finalIrr) . " تومان\n"
                    . "👨‍💼 ادمین تاییدکننده: <code>{$adminId}</code> (@{$adminUsername})",
                'parse_mode' => 'HTML',
            ];
            if (!empty($paymentreports)) {
                $payload['message_thread_id'] = $paymentreports;
            }
            telegram('sendmessage', $payload);
        }
    }
}

if (!function_exists('crypto_save_wallet_memo')) {
    function crypto_save_wallet_memo(string $currency, string $memo): bool
    {
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("UPDATE crypto_wallets SET wallet_memo = :m WHERE currency = :c");
            $up->execute([':m' => $memo, ':c' => $currency]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] save_wallet_memo: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_get_irt_rates')) {


    function crypto_get_irt_rates(array $wantKeys = []): array
    {
        $providerSet = [];
        foreach ($wantKeys ?: ['TRX', 'TON', 'USDT'] as $k) {
            $u = strtoupper(trim((string) $k));
            if ($u === '') continue;
            if ($u === 'USDT')      $providerSet['USD'] = true;
            else                    $providerSet[$u] = true;
        }
        $providerKeys = array_keys($providerSet);

        $rates = [];
        if (!empty($providerKeys) && function_exists('requireTronRates')) {


            foreach ($providerKeys as $pk) {
                $r = requireTronRates([$pk]);
                if (!is_array($r)) continue;
                if ($pk === 'TRX' && isset($r['TRX']) && is_numeric($r['TRX']) && (float) $r['TRX'] > 0) {
                    $rates['TRX'] = (float) $r['TRX'];
                } elseif ($pk === 'TON' && isset($r['Ton']) && is_numeric($r['Ton']) && (float) $r['Ton'] > 0) {
                    $rates['TON'] = (float) $r['Ton'];
                } elseif ($pk === 'USD' && isset($r['USD']) && is_numeric($r['USD']) && (float) $r['USD'] > 0) {
                    $rates['USDT'] = (float) $r['USD'];
                }
            }
        }


        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT currency, rate_irt_override FROM crypto_wallets WHERE rate_irt_override IS NOT NULL AND rate_irt_override > 0");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cur = strtoupper((string) $row['currency']);
                    $key = ($cur === 'USDT_TRC20' || $cur === 'USDT_TON') ? 'USDT' : $cur;
                    if (!isset($rates[$key])) {
                        $rates[$key] = (float) $row['rate_irt_override'];
                    }
                }
            } catch (Throwable $e) {  }
        }
        return $rates;
    }
}

if (!function_exists('crypto_irt_rate_for')) {
    function crypto_irt_rate_for(string $currency): ?float
    {


        if ($currency === 'TRX') {
            $rates = crypto_get_irt_rates(['TRX']);
            return $rates['TRX'] ?? null;
        }
        if ($currency === 'TON') {
            $rates = crypto_get_irt_rates(['TON']);
            return $rates['TON'] ?? null;
        }
        if ($currency === 'USDT_TRC20' || $currency === 'USDT_TON') {
            $rates = crypto_get_irt_rates(['USDT']);
            return $rates['USDT'] ?? null;
        }
        return null;
    }
}

if (!function_exists('crypto_display_decimals')) {

    function crypto_display_decimals(string $currency): int
    {
        $defaults = [
            'TRX'        => 2,
            'USDT_TRC20' => 2,
            'USDT_TON'   => 2,
            'TON'        => 3,
        ];
        $cfg = crypto_pay_setting('cryptocheck_display_decimals_' . $currency, '');
        if ($cfg !== '' && ctype_digit($cfg)) {
            return max(2, min(6, (int) $cfg));
        }
        return $defaults[$currency] ?? 2;
    }
}

if (!function_exists('crypto_unique_amount')) {


    function crypto_unique_amount(float $baseCoinAmount, string $currency, ?int $extraDecimalsOverride = null, bool $iranianMode = false): float
    {
        $currencies = crypto_supported_currencies();
        $decimals = $currencies[$currency]['decimals'] ?? 6;


        $exchangeFriendlyRaw = strtolower((string) crypto_pay_setting('cryptocheck_exchange_friendly', 'on'));
        $exchangeFriendly = !in_array($exchangeFriendlyRaw, ['off', '0', 'false', 'no'], true);

        if ($exchangeFriendly && !$iranianMode) {

            $displayDecimals = crypto_display_decimals($currency);
            $scale = (int) pow(10, $displayDecimals);
            $maxNoiseCfg = crypto_pay_setting('cryptocheck_max_cent_noise', '');
            $maxNoise = ($maxNoiseCfg !== '' && ctype_digit($maxNoiseCfg)) ? (int) $maxNoiseCfg : 24;
            $maxNoise = max(1, min($maxNoise, $scale - 1));

            $baseUnits = (int) ceil($baseCoinAmount * $scale - 1e-9);
            if ($baseUnits < 1) $baseUnits = 1;


            $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
            $existing = [];
            if ($pdo instanceof PDO) {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT crypto_amount FROM Payment_report
                          WHERE crypto_currency = :c
                            AND payment_Status IN ('Unpaid','AwaitingHash')
                            AND crypto_amount IS NOT NULL
                            AND (crypto_iranian_mode = 0 OR crypto_iranian_mode IS NULL)"
                    );
                    $stmt->execute([':c' => $currency]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $units = (int) round((float) $row['crypto_amount'] * $scale);
                        $existing[$units] = true;
                    }
                } catch (Throwable $e) {  }
            }


            for ($attempt = 0; $attempt < 64; $attempt++) {
                try { $rand = random_int(1, $maxNoise); }
                catch (Throwable $e) { $rand = mt_rand(1, $maxNoise); }
                $candidate = $baseUnits + $rand;
                if (!isset($existing[$candidate])) {
                    return $candidate / $scale;
                }
            }

            $bump = $maxNoise + ((int) (microtime(true) * 1000) % max(1, $maxNoise)) + 1;
            return ($baseUnits + $bump) / $scale;
        }


        if ($iranianMode) {
            $iranianAdaptiveThreshold = (float) crypto_pay_setting('cryptocheck_iranian_threshold', '1');
            if ($iranianAdaptiveThreshold <= 0) $iranianAdaptiveThreshold = 1.0;
            if ($baseCoinAmount < $iranianAdaptiveThreshold) {
                $scale = 100;
                $preserve = 6;
            } else {
                $scale = 10;
                $preserve = 6;
            }
        } else {
            $perCurrencyDefault = [
                'TRX'        => 2,
                'USDT_TRC20' => 2,
                'USDT_TON'   => 2,
                'TON'        => 5,
            ];
            if ($extraDecimalsOverride !== null) {
                $extra = (int) $extraDecimalsOverride;
            } else {
                $cfgKey = 'cryptocheck_extra_decimals_' . $currency;
                $cfgVal = crypto_pay_setting($cfgKey, '');
                $extra = $cfgVal !== '' && ctype_digit($cfgVal)
                    ? (int) $cfgVal
                    : ($perCurrencyDefault[$currency] ?? 2);
            }
            $extra = max(2, min($extra, $decimals - 1));
            $scale = (int) pow(10, $decimals);
            $preserve = (int) pow(10, $extra);
        }


        $baseUnits = (int) ceil($baseCoinAmount * $scale - 1e-9);
        if ($baseUnits < 1) $baseUnits = 1;

        if ($iranianMode) {
            $baseHigh = $baseUnits;
        } else {
            $baseHigh = ((int) ceil($baseUnits / $preserve)) * $preserve;
            if ($baseHigh < $baseUnits + 1) {
                $baseHigh += $preserve;
            }
        }


        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        $existing = [];
        if ($pdo instanceof PDO) {
            try {
                $sql = "SELECT crypto_amount FROM Payment_report
                         WHERE crypto_currency = :c
                           AND payment_Status IN ('Unpaid','AwaitingHash')
                           AND crypto_amount IS NOT NULL";
                if ($iranianMode) {
                    $sql .= " AND crypto_iranian_mode = 1";
                } else {
                    $sql .= " AND (crypto_iranian_mode = 0 OR crypto_iranian_mode IS NULL)";
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':c' => $currency]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $units = (int) round((float) $row['crypto_amount'] * $scale);
                    $existing[$units] = true;
                }
            } catch (Throwable $e) {  }
        }

        $maxAttempts = $iranianMode ? 5 : 64;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $rand = random_int(1, $preserve - 1);
            } catch (Throwable $e) {
                $rand = mt_rand(1, $preserve - 1);
            }
            $candidateUnits = $baseHigh + $rand;
            if (!isset($existing[$candidateUnits])) {
                return $candidateUnits / $scale;
            }
        }

        if ($iranianMode) {
            for ($bump = 1; $bump <= 100; $bump++) {
                for ($offset = 1; $offset < $preserve; $offset++) {
                    $candidateUnits = $baseHigh + $bump * $preserve + $offset;
                    if (!isset($existing[$candidateUnits])) {
                        return $candidateUnits / $scale;
                    }
                }
            }
            $fallback = $baseHigh + 1000 + ((int) (microtime(true) * 1000) % 9) + 1;
            return $fallback / $scale;
        }

        $fallback = $baseHigh + $preserve + ((int) (microtime(true) * 1000) % ($preserve - 1)) + 1;
        return $fallback / $scale;
    }
}

if (!function_exists('crypto_create_invoice')) {
    function crypto_create_invoice($userId, int $amountIrt, string $currency, string $invoiceMeta = '', bool $iranianMode = false, ?string $source = null): array
    {
        $currencies = crypto_supported_currencies();
        if (!isset($currencies[$currency])) {
            return ['ok' => false, 'error' => 'currency-not-supported'];
        }
        $wallet = crypto_active_wallet($currency);
        if (!$wallet) {
            return ['ok' => false, 'error' => 'wallet-not-configured'];
        }
        $minIrt = (int) ($wallet['min_irt'] ?? 0);
        $maxIrt = (int) ($wallet['max_irt'] ?? 0);
        if ($minIrt > 0 && $amountIrt < $minIrt) {
            return ['ok' => false, 'error' => 'below-min', 'min' => $minIrt];
        }
        if ($maxIrt > 0 && $amountIrt > $maxIrt) {
            return ['ok' => false, 'error' => 'above-max', 'max' => $maxIrt];
        }

        $rate = crypto_irt_rate_for($currency);
        if ($rate === null || $rate <= 0) {
            return ['ok' => false, 'error' => 'rate-unavailable'];
        }
        $rawCoin = $amountIrt / $rate;
        $finalCoin = crypto_unique_amount($rawCoin, $currency, null, $iranianMode);

        global $connect;
        $orderId = bin2hex(random_bytes(6));
        $now = date('Y/m/d H:i:s');
        $statusUnpaid = 'Unpaid';


        $methodLabel = 'arze digital offline';
        $network = $currencies[$currency]['network'];

        try {
            $stmt = $connect->prepare(
                "INSERT INTO Payment_report
                    (id_user, id_order, time, price, payment_Status, Payment_Method,
                     id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_wallet_to, crypto_iranian_mode, source)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $userIdStr = (string) $userId;
            $amountIrtStr = (string) $amountIrt;
            $coinStr = number_format($finalCoin, $currencies[$currency]['decimals'], '.', '');
            $iranianModeStr = $iranianMode ? '1' : '0';
            $sourceStr = ($source !== null && $source !== '') ? $source : null;
            $stmt->bind_param(
                'sssssssssssss',
                $userIdStr, $orderId, $now, $amountIrtStr, $statusUnpaid, $methodLabel,
                $invoiceMeta, $currency, $network, $coinStr, $wallet['wallet_address'], $iranianModeStr, $sourceStr
            );
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[crypto] create_invoice: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'db-write-failed'];
        }

        $ttlKey = $iranianMode ? 'cryptocheck_invoice_ttl_iranian' : 'cryptocheck_invoice_ttl';
        $ttlDefault = $iranianMode ? '7200' : '86400';
        $ttl = (int) crypto_pay_setting($ttlKey, $ttlDefault);
        return [
            'ok' => true,
            'order_id'    => $orderId,
            'amount_coin' => $finalCoin,
            'wallet'      => $wallet['wallet_address'],
            'wallet_memo' => trim((string) ($wallet['wallet_memo'] ?? '')),
            'currency'    => $currency,
            'network'     => $network,
            'rate'        => $rate,
            'expires_at'  => time() + max(60, $ttl),
            'iranian_mode' => $iranianMode,
        ];
    }
}

if (!function_exists('crypto_attach_hash')) {
    function crypto_attach_hash(string $orderId, string $hashOrUrl): array
    {
        $hash = crypto_extract_hash($hashOrUrl);
        if ($hash === null) {
            return ['ok' => false, 'error' => 'invalid-hash'];
        }
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'error' => 'no-db'];
        }
        try {
            $dup = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h AND id_order <> :o LIMIT 1");
            $dup->execute([':h' => $hash, ':o' => $orderId]);
            if ($dup->fetch()) {
                return ['ok' => false, 'error' => 'hash-already-used'];
            }
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                 SET crypto_tx_hash = :h, crypto_hash_at = :t, payment_Status = 'AwaitingHash'
                 WHERE id_order = :o AND payment_Status IN ('Unpaid','AwaitingHash')"
            );
            $stmt->execute([':h' => $hash, ':t' => time(), ':o' => $orderId]);
            if ($stmt->rowCount() < 1) {
                return ['ok' => false, 'error' => 'order-not-pending'];
            }
            return ['ok' => true, 'hash' => $hash];
        } catch (Throwable $e) {
            error_log('[crypto] attach_hash: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'db-update-failed'];
        }
    }
}

if (!function_exists('crypto_http_get_json')) {
    function crypto_http_get_json(string $url, array $headers = [], int $timeoutMs = 7000): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => 4000,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'User-Agent: CryptoHashChecker/1.0',
            ], $headers),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            error_log("[crypto] HTTP {$code} for {$url}: {$err}");
            return null;
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json)) return null;
        return $json;
    }
}

if (!function_exists('crypto_amount_within_tolerance')) {


    function crypto_amount_within_tolerance(float $expected, float $observed, bool $iranianMode = false): bool
    {
        if ($expected <= 0) return false;

        $shortPct = (float) crypto_pay_setting('cryptocheck_amount_tolerance', '0');
        $shortPct = max(0.0, min($shortPct, 5.0));

        $overPct = (float) crypto_pay_setting('cryptocheck_overpay_tolerance', '0');
        $overPct = max(0.0, min($overPct, 100.0));

        if ($iranianMode) {
            $iranianPct = (float) crypto_pay_setting('cryptocheck_iranian_tolerance', '2');
            $iranianPct = max(0.0, min($iranianPct, 10.0));
            $shortPct = max($shortPct, $iranianPct);
            $overPct  = max($overPct,  $iranianPct);
        }

        $shortTol = $expected * ($shortPct / 100.0);
        $overTol  = $expected * ($overPct / 100.0);


        $eps = max(abs($expected), abs($observed)) * 1e-9;

        $diff = $observed - $expected;
        if ($diff >= -($shortTol + $eps) && $diff <= ($overTol + $eps)) {
            return true;
        }
        return false;
    }
}


if (!function_exists('crypto_check_tron_tx')) {
    function crypto_check_tron_tx(string $hash, string $expectedTo, float $expectedAmount, ?string $tokenContract = null, bool $iranianMode = false): array
    {
        $hash = strtolower(trim($hash));
        if (!preg_match('/^[0-9a-f]{64}$/', $hash)) {
            return ['ok' => false, 'reason' => 'bad-hash-format'];
        }
        $expectedTo = trim($expectedTo);
        if ($expectedTo === '') {
            return ['ok' => false, 'reason' => 'no-expected-recipient'];
        }

        $url = 'https://apilist.tronscanapi.com/api/transaction-info?hash=' . urlencode($hash);
        $key = crypto_pay_setting('cryptocheck_trongrid_key', '');
        $headers = [];
        if ($key !== '') $headers[] = 'TRON-PRO-API-KEY: ' . $key;

        $tx = crypto_http_get_json($url, $headers);
        if ($tx === null || empty($tx)) {
            return ['ok' => false, 'reason' => 'api-unreachable'];
        }
        if (empty($tx['hash'])) {
            return ['ok' => false, 'reason' => 'tx-not-found'];
        }
        $confirmed = (int) ($tx['confirmed'] ?? 0) === 1;
        if (!$confirmed && empty($tx['confirmations'])) {
            return ['ok' => false, 'reason' => 'tx-not-confirmed'];
        }
        if (isset($tx['contractRet']) && $tx['contractRet'] !== 'SUCCESS') {
            return ['ok' => false, 'reason' => 'tx-failed', 'detail' => $tx];
        }

        if ($tokenContract === null) {
            $contractType = (int) ($tx['contractType'] ?? 1);
            if ($contractType !== 1) {
                return ['ok' => false, 'reason' => 'not-trx-transfer'];
            }
            $to = trim((string) ($tx['toAddress'] ?? ''));
            if (strcasecmp($to, $expectedTo) !== 0) {
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['to' => $to, 'want' => $expectedTo]];
            }
            $amountSun = (float) ($tx['contractData']['amount'] ?? 0);
            $amountTrx = $amountSun / 1000000.0;
            if (!crypto_amount_within_tolerance($expectedAmount, $amountTrx, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amountTrx, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($tx['ownerAddress'] ?? $tx['contractData']['owner_address'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTrx, 'to' => $to, 'sender' => $sender]];
        }


        $transfers = $tx['tokenTransferInfo'] ?? null;
        if (!is_array($transfers)) {
            $transfers = $tx['trc20TransferInfo'] ?? [];
            if (!is_array($transfers)) $transfers = [];
            if (isset($transfers['contract_address'])) {
                $transfers = [$transfers];
            }
        } else {
            $transfers = [$transfers];
        }
        foreach ($transfers as $t) {
            if (!is_array($t)) continue;
            $contract = trim((string) ($t['contract_address'] ?? $t['contractAddress'] ?? ''));
            if ($contract === '' || strcasecmp($contract, $tokenContract) !== 0) continue;
            $to = trim((string) ($t['to_address'] ?? $t['toAddress'] ?? ''));
            if (strcasecmp($to, $expectedTo) !== 0) {
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['to' => $to, 'want' => $expectedTo]];
            }
            $decimals = (int) ($t['decimals'] ?? 6);
            $raw = (string) ($t['amount_str'] ?? $t['amount'] ?? '0');
            $amount = (float) $raw / pow(10, $decimals);
            if (!crypto_amount_within_tolerance($expectedAmount, $amount, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amount, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($t['from_address'] ?? $t['fromAddress'] ?? $tx['ownerAddress'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amount, 'to' => $to, 'sender' => $sender]];
        }
        return ['ok' => false, 'reason' => 'no-matching-trc20-transfer'];
    }
}