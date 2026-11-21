<?php

namespace Faoxima\Payments\Gateways\Tronado;

use ManagePanel;

final class Runtime
{
    public static function tronratee(array $requiredKeys = [])
    {
        $normalizedKeys = [];
        foreach ($requiredKeys as $key) {
            $normalized = strtoupper(trim((string) $key));
            if ($normalized === '') {
                continue;
            }
            $normalizedKeys[$normalized] = true;
        }

        if (empty($normalizedKeys)) {
            $normalizedKeys = ['TRX' => true, 'TON' => true, 'USD' => true];
        }

        $needsTrx = isset($normalizedKeys['TRX']);
        $needsTon = isset($normalizedKeys['TON']);
        $needsUsd = isset($normalizedKeys['USD']);

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
            ],
        ]);

        $result = [];
        $missingKeys = [];

        if (!$needsTrx && !$needsTon && !$needsUsd) {
            return ['ok' => true, 'result' => $result];
        }

        $endpoint = 'https://swapwallet.app/api/v1/market/prices';
        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            error_log('Failed to fetch market prices from SwapWallet API');
            if ($needsTrx) $missingKeys[] = 'TRX';
            if ($needsTon) $missingKeys[] = 'Ton';
            if ($needsUsd) $missingKeys[] = 'USD';
            return ['ok' => empty($missingKeys), 'result' => $result];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || ($data['status'] ?? null) !== 'OK' || !isset($data['result']) || !is_array($data['result'])) {
            error_log('Invalid response received from SwapWallet API');
            if ($needsTrx) $missingKeys[] = 'TRX';
            if ($needsTon) $missingKeys[] = 'Ton';
            if ($needsUsd) $missingKeys[] = 'USD';
            return ['ok' => empty($missingKeys), 'result' => $result];
        }

        $prices = $data['result'];

        $getPair = static function (array $prices, string $base, string $quote) {
            $target = strtoupper($base . '/' . $quote);
            foreach ($prices as $k => $v) {
                if (strtoupper((string) $k) !== $target) {
                    continue;
                }

                if (is_string($v)) {
                    $v = preg_replace('/[^\d\.\-]/u', '', $v);
                }

                if (!is_numeric($v)) {
                    return null;
                }

                $num = (float) $v;
                if ($num <= 0.0 || !is_finite($num)) {
                    return null;
                }

                return $num;
            }
            return null;
        };

        if ($needsTrx) {
            $trxIrt = $getPair($prices, 'TRX', 'IRT');
            if ($trxIrt === null) {
                error_log('Missing or invalid TRX/IRT price from SwapWallet');
                $missingKeys[] = 'TRX';
            } else {
                $result['TRX'] = round($trxIrt, 2);
            }
        }

        if ($needsTon) {
            $tonIrt = $getPair($prices, 'TON', 'IRT');
            if ($tonIrt === null) {
                $tonIrt = $getPair($prices, 'Ton', 'IRT');
            }

            if ($tonIrt === null) {
                error_log('Missing or invalid TON/IRT price from SwapWallet');
                $missingKeys[] = 'Ton';
            } else {
                $result['Ton'] = round($tonIrt, 2);
            }
        }

        if ($needsUsd) {
            $usdtIrt = $getPair($prices, 'USDT', 'IRT');
            if ($usdtIrt === null) {
                error_log('Missing or invalid USDT/IRT price from SwapWallet');
                $missingKeys[] = 'USD';
            } else {
                $result['USD'] = round($usdtIrt, 2);
            }
        }

        return ['ok' => empty($missingKeys), 'result' => $result];
    }

    public static function requireTronRates(array $keys = [])
    {
        $normalizedKeys = [];
        foreach ($keys as $key) {
            $upper = strtoupper(trim((string) $key));
            if ($upper === '') {
                continue;
            }
            $normalizedKeys[$upper] = true;
        }

        $requestedKeys = array_keys($normalizedKeys);
        $rates = self::tronratee($requestedKeys);

        if (!is_array($rates) || !isset($rates['result']) || !is_array($rates['result'])) {
            return null;
        }

        $result = $rates['result'];

        if (isset($result['USD']) && is_numeric($result['USD'])) {
            $result['USD'] = round(abs((float) $result['USD']), 2);
        }

        $validationKeys = [];
        if (empty($requestedKeys)) {
            $validationKeys = ['TRX', 'Ton', 'USD'];
        } else {
            foreach ($requestedKeys as $requestedKey) {
                if ($requestedKey === 'TON') {
                    $validationKeys[] = 'Ton';
                } elseif ($requestedKey === 'TRX' || $requestedKey === 'USD') {
                    $validationKeys[] = $requestedKey;
                } else {
                    $validationKeys[] = $requestedKey;
                }
            }
        }

        foreach ($validationKeys as $key) {
            if (!isset($result[$key]) || (is_numeric($result[$key]) && (float) $result[$key] == 0.0)) {
                return null;
            }
        }

        return $result;
    }

    public static function trnado($order_id, $price)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Tronado\Client::createPayment($domainhosts, $order_id, $price);
    }

    public static function rxPaymentV2PrepareContext()
    {
        global $datatextbot, $textbotlang, $setting, $ManagePanel;
        if (!is_array($setting ?? null)) {
            $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        }
        if (!is_array($datatextbot ?? null)) {
            $datatextbot = [
                'textafterpay' => '',
                'textaftertext' => '',
                'textmanual' => '',
                'textselectlocation' => '',
                'text_wgdashboard' => '',
            ];
            $rows = \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll");
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (array_key_exists($row['id_text'], $datatextbot) || (is_string($row['text']) && trim($row['text']) !== '')) {
                        $datatextbot[$row['id_text']] = $row['text'];
                    }
                }
            }
        }
        if (!is_array($textbotlang ?? null) && method_exists(\Faoxima\Telegram\Conversation\ConversationData::class, 'languagechange')) {
            $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange(dirname(__DIR__, 4) . '/text.json');
        }
        if (!is_object($ManagePanel ?? null) && class_exists('ManagePanel')) {
            $ManagePanel = new ManagePanel();
        }
        if (!method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_confirm_paid')) {
            require_once dirname(__DIR__, 4) . '/src/bootstrap.php';
        }
        return $setting;
    }

    public static function rxPaymentV2ReportToAdmin($text)
    {
        global $setting;
        $channel = is_array($setting ?? null) ? (string) ($setting['Channel_Report'] ?? '') : '';
        if ($channel === '') {
            $row = \Faoxima\Core\Database\Db::select('setting', 'Channel_Report', null, null, 'select');
            $channel = is_array($row) ? (string) ($row['Channel_Report'] ?? '') : '';
        }
        if ($channel === '' || !function_exists('telegram')) {
            return;
        }
        $threadRow = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select");
        $payload = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML'];
        if (is_array($threadRow) && !empty($threadRow['idreport'])) {
            $payload['message_thread_id'] = $threadRow['idreport'];
        }
        telegram('sendmessage', $payload);
    }

    public static function rxStarsHandleSuccessfulPayment(array $update)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Stars\Settlement::starsHandleSuccessfulPayment($pdo, $setting, $update);
    }

    public static function tronadoAdminMaskSecret($secret)
    {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return '';
        }
        return strlen($secret) < 8 ? '••••' : '••••' . substr($secret, -4);
    }

    public static function tronadoCallbackSetup()
    {
        global $domainhosts;
        $raw = trim(preg_replace('#^https?://#i', '', trim((string) $domainhosts)), '/');
        $host = $raw === '' ? '' : strtolower((string) parse_url('https://' . $raw, PHP_URL_HOST));
        $valid = $host !== ''
            && $host !== 'localhost'
            && filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1;
        if (!$valid) {
            return ['ok' => false, 'domain' => '', 'ipn' => '', 'dispute' => '', 'configured' => $raw];
        }
        $base = 'https://' . $raw;
        return [
            'ok' => true,
            'domain' => $host,
            'ipn' => $base . '/payment/tronado.php',
            'dispute' => $base . '/payment/tronado_dispute.php',
            'configured' => $raw,
        ];
    }

    public static function tronadoAdminSetupText()
    {
        $apiRow = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
        $apiKey = is_array($apiRow) ? trim((string) ($apiRow['ValuePay'] ?? '')) : '';
        $signRow = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "ipnsigningkeytronado", "select");
        $signKey = is_array($signRow) ? trim((string) ($signRow['ValuePay'] ?? '')) : '';
        $setup = self::tronadoCallbackSetup();
        $e = [\Faoxima\Payments\Settlement\GatewayFlows::class, 'paymentV2Escape'];

        $lines = ["📘 <b>راهنمای اتصال ترونادو</b>", ''];
        $lines[] = $apiKey === ''
            ? '🔑 وضعیت API Key: ❌ ثبت نشده'
            : '🔑 وضعیت API Key: ✅ ثبت شده (' . $e(self::tronadoAdminMaskSecret($apiKey)) . ')؛ صحت آن از طرف ترونادو تأیید نشده است.';
        $lines[] = $signKey === ''
            ? '🔏 کلید امضای IPN: ⚠️ ثبت نشده؛ تا زمان ثبت، تمام Callbackهای ترونادو رد می‌شوند.'
            : '🔏 کلید امضای IPN: ✅ ثبت شده (' . $e(self::tronadoAdminMaskSecret($signKey)) . ')';
        $lines[] = 'کلید امضای IPN با API Key جداست و از دکمه «🔏 ثبت کلید امضای IPN ترونادو» تنظیم می‌شود؛ ثبت یا تغییر API Key آن را تغییر نمی‌دهد.';
        $lines[] = '';
        if ($setup['ok']) {
            $lines[] = '🌐 دامنه‌ی Callback که باید ثبت شود:';
            $lines[] = '<code>' . $e($setup['domain']) . '</code>';
            $lines[] = '';
            $lines[] = '📥 آدرس IPN پرداخت:';
            $lines[] = '<code>' . $e($setup['ipn']) . '</code>';
            $lines[] = '';
            $lines[] = '⚖️ آدرس Callback اعتراض (Dispute، اختیاری):';
            $lines[] = '<code>' . $e($setup['dispute']) . '</code>';
        } else {
            $lines[] = '❌ دامنه‌ی ربات تنظیم نشده یا معتبر نیست' . ($setup['configured'] !== '' ? ' (' . $e($setup['configured']) . ')' : '') . '؛ آدرس Callback ساخته نشد. ابتدا دامنه‌ی معتبر (نه IP و نه localhost) را در تنظیمات ربات ثبت کنید.';
        }
        $lines[] = '';
        $lines[] = '⚠️ دامنه‌ی Callback باید پیش از استفاده توسط پشتیبانی ترونادو (@TronadoSupp) ثبت و تأیید شود؛ ربات نمی‌تواند این کار را خودکار انجام دهد و بدون آن، Callback پرداخت‌ها ارسال نمی‌شود.';
        $lines[] = '🔒 هر دو آدرس باید با HTTPS در دسترس باشند.';
        $lines[] = '📌 برای فعال‌سازی Callback اعتراض، آدرس بالا را در مینی‌اپ ترونادو (کسب‌وکار ← تنظیمات) ثبت کنید؛ دامنه‌ی آن هم باید در فهرست دامنه‌های مجاز باشد.';
        return implode("\n", $lines);
    }

    public static function tronadoAdminSetupKeyboard()
    {
        $setup = self::tronadoCallbackSetup();
        if (!$setup['ok']) {
            return null;
        }
        return json_encode(['inline_keyboard' => [
            [['text' => '📋 کپی دامنه', 'copy_text' => ['text' => $setup['domain']]]],
            [['text' => '📋 کپی آدرس IPN پرداخت', 'copy_text' => ['text' => $setup['ipn']]]],
            [['text' => '📋 کپی آدرس Callback اعتراض', 'copy_text' => ['text' => $setup['dispute']]]],
        ]], JSON_UNESCAPED_UNICODE);
    }
}
