<?php

namespace Faoxima\Payments\Crypto;

use PDO;
use Throwable;

final class ManualCrypto
{
    public static function crypto_supported_currencies(): array
    {
        return [
            'TRX'        => ['network' => 'TRON', 'decimals' => 6, 'label' => 'ترون (TRX)',           'fa_short' => 'ترون'],
            'TON'        => ['network' => 'TON',  'decimals' => 9, 'label' => 'تون (TON)',            'fa_short' => 'تون'],
            'USDT_TRC20' => ['network' => 'TRON', 'decimals' => 6, 'label' => 'تتر روی شبکه ترون',     'fa_short' => 'تتر-ترون'],
            'USDT_TON'   => ['network' => 'TON',  'decimals' => 6, 'label' => 'تتر روی شبکه تون',      'fa_short' => 'تتر-تون'],
        ];
    }

    public static function crypto_pay_setting(string $name, string $default = ''): string
    {
        $row = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $name, 'select') : null;
        if (is_array($row) && isset($row['ValuePay'])) {
            $v = trim((string) $row['ValuePay']);
            if ($v !== '') return $v;
        }
        return $default;
    }

    public static function crypto_extract_hash($input): ?string
    {
        if (!is_string($input)) return null;
        $input = trim($input);
        if ($input === '') return null;

        $input = preg_replace('/\s+/u', ' ', $input);
        $input = str_replace(["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], '', (string) $input);
        $input = trim((string) $input);


        if (preg_match('~[a-z0-9.-]+\.[a-z]{2,}[^\s]*?/(?:tx|transaction|transactions|events)/(0x[0-9a-fA-F]{64}|[0-9a-fA-F]{64})~i', $input, $m)) {
            return strtolower($m[1]);
        }


        if (preg_match('/\b0x[0-9a-fA-F]{64}\b/', $input, $m)) {
            return strtolower($m[0]);
        }


        if (preg_match('/\b[0-9a-fA-F]{64}\b/', $input, $m)) {
            return strtolower($m[0]);
        }


        if (preg_match('~[a-z0-9.-]+\.[a-z]{2,}[^\s]*?/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{40,44}=?)~i', $input, $m)) {
            return $m[1];
        }
        if (preg_match('~/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{40,44}=?)(?:[/?#]|$)~', $input, $m)) {
            return $m[1];
        }


        if (preg_match('/^[A-Za-z0-9_\-+\/]{40,44}=?$/', $input) && !preg_match('/^0x[0-9a-fA-F]{40}$/i', $input)) {
            return $input;
        }

        return null;
    }

    public static function crypto_active_wallet(string $currency): ?array
    {
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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

    public static function crypto_active_wallets(): array
    {
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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

    public static function crypto_validate_address(string $address, string $network): bool
    {
        $address = trim($address);
        if ($network === 'TRON') {
            // Checksum-verified: a mistyped TRC20 address passes a shape regex but can never receive funds.
            return is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'isValidTronAddress']) && \Faoxima\Payments\Gateways\Tronado\Client::isValidTronAddress($address);
        }
        if ($network === 'TON') {


            if (preg_match('/^(?:EQ|UQ|kQ|Ef|Uf|0Q)[A-Za-z0-9_\-]{46}$/', $address)) return true;
            if (preg_match('/^-?\d+:[0-9a-fA-F]{64}$/', $address)) return true;
            return false;
        }
        return mb_strlen($address) >= 20 && mb_strlen($address) <= 200;
    }

    public static function crypto_save_wallet(string $currency, string $address): bool
    {
        $supported = self::crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $network = $supported[$currency]['network'];
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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

    public static function crypto_manual_currencies(): array
    {
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return [];
        try {
            $stmt = $pdo->query("SELECT currency, network, label
                                    FROM crypto_wallets
                                   WHERE verification_mode = 'manual' AND enabled = 1
                                   ORDER BY id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $row) {
                $out[$row['currency']] = [
                    'network'   => $row['network'],
                    'decimals'  => 6,
                    'label'     => $row['label'] ?: $row['currency'],
                    'fa_short'  => $row['label'] ?: $row['currency'],
                ];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function crypto_create_manual_wallet(string $currency, string $network, string $label, string $address): bool
    {
        $currency = strtoupper(trim($currency));
        $network  = trim($network);
        $label    = trim($label);
        if ($currency === '' || $network === '') return false;
        if (strpos($currency, 'USDT_') !== 0) return false;
        if (isset(self::crypto_supported_currencies()[$currency])) return false;
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("INSERT INTO crypto_wallets (currency, network, wallet_address, label, enabled, verification_mode)
                                  VALUES (:c, :n, :a, :l, 1, 'manual')
                                  ON DUPLICATE KEY UPDATE wallet_address = VALUES(wallet_address),
                                                          network        = VALUES(network),
                                                          label          = VALUES(label),
                                                          enabled        = 1,
                                                          verification_mode = 'manual'");
            $up->execute([
                ':c' => $currency,
                ':n' => $network,
                ':a' => $address,
                ':l' => $label !== '' ? $label : $currency,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] create_manual_wallet: ' . $e->getMessage());
            return false;
        }
    }

    public static function crypto_delete_wallet(string $currency): bool
    {
        $supported = self::crypto_supported_currencies();
        $manual = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? self::crypto_manual_currencies() : [];
        if (!isset($supported[$currency]) && !isset($manual[$currency])) return false;
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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

    public static function crypto_invoice_button_style(string $key): ?string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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
        $defaultsEnabled = !function_exists('rx_kb_use_defaults') || rx_kb_use_defaults();
        $style = $cache[$key] ?? null;
        if (($style === null || $style === '' || $style === 'default')
            && $defaultsEnabled
            && strpos($key, 'currency_pick_') === 0
            && isset($cache['currency_pick'])) {
            $style = $cache['currency_pick'];
        }
        if ($style === null || $style === '' || $style === 'default') return $defaultsEnabled ? null : 'none';
        return (string) $style;
    }

    public static function crypto_manual_action_style(string $key): ?string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
            if ($pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode((string) $row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['user_dynamic_lists']) && is_array($all['user_dynamic_lists'])) {
                            $cache = array_filter($all['user_dynamic_lists'], static function ($v) {
                                return $v !== 'default';
                            });
                        }
                    }
                } catch (Throwable $e) {  }
            }
            if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                $cache = $cache + rx_getKeyboardDefaultStyles('user_dynamic_lists');
            }
        }
        foreach ([$key, 'crypto_manual_actions'] as $candidate) {
            if (!empty($cache[$candidate]) && $cache[$candidate] !== 'default') {
                return (string) $cache[$candidate];
            }
        }
        return null;
    }

    public static function cm_apply_payment(string $orderId, int $finalIrr, array $payment, $adminId, $adminUsername, array $setting, $paymentreports, $messageId, $callbackQueryId, string $textInline = ''): void
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return;
        $atomic = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid' "
            . "WHERE id_order = :id AND payment_Status <> 'paid'"
        );
        $atomic->bindValue(':id', $orderId, PDO::PARAM_STR);
        $atomic->execute();
        if ($atomic->rowCount() < 1) {
            return;
        }
        if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_record_verified_hash')) {
            self::crypto_record_verified_hash($orderId, 'manual_admin');
        }

        $idInvoiceRaw = (string) ($payment['id_invoice'] ?? '');
        $pendingActionTags = ['getconfigafterpay', 'getextenduser', 'getextratimeuser', 'getextravolumeuser'];
        $pendingActionTag = '';
        if ($idInvoiceRaw !== '' && strpos($idInvoiceRaw, '|') !== false) {
            $tag = explode('|', $idInvoiceRaw, 2)[0];
            if (in_array($tag, $pendingActionTags, true)) {
                $pendingActionTag = $tag;
            }
        }

        if ($pendingActionTag !== '') {
            // پرداخت برای تکمیل یک عملیات معلق (خرید/تمدید/افزودن حجم-زمان) است — مثل مسیر خودکار
            // cronbot/cryptocheck.php، مبلغ به کیف پول اضافه نمی‌شود؛ DirectPayment() خودش عملیات را انجام می‌دهد.
            if (method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'DirectPayment')) {
                \Faoxima\Payments\Gateways\GatewayRuntime::DirectPayment($orderId);
            }
            if (\Faoxima\Core\Database\Db::runtimeReady()) {
                \Faoxima\Core\Database\Db::update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $orderId);
            }
            if ($callbackQueryId && function_exists('telegram')) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callbackQueryId,
                    'text' => '✅ تایید شد.',
                    'cache_time' => 0,
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
                        . "💵 مبلغ: " . number_format($finalIrr) . " تومان\n"
                        . "👨‍💼 ادمین تاییدکننده: <code>{$adminId}</code> (@{$adminUsername})",
                    'parse_mode' => 'HTML',
                ];
                if (!empty($paymentreports)) {
                    $payload['message_thread_id'] = $paymentreports;
                }
                telegram('sendmessage', $payload);
            }
            return;
        }

        $userRow = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', '*', 'id', $payment['id_user'], 'select', ['cache' => false]) : null;
        if (!is_callable([\Faoxima\Wallet\Balance\Balances::class, 'credit']) || !\Faoxima\Wallet\Balance\Balances::credit($pdo, $payment['id_user'], $finalIrr)) {
            try {
                $release = $pdo->prepare("UPDATE Payment_report SET payment_Status = :s WHERE id_order = :id AND payment_Status = 'paid'");
                $release->execute([':s' => (string) ($payment['payment_Status'] ?? 'waiting'), ':id' => $orderId]);
            } catch (Throwable $e) {
                error_log('[crypto] cm_apply_payment release failed: ' . $e->getMessage());
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            if (function_exists('rx_log_event')) {
                rx_log_event('WALLET_CREDIT_FAILED', 'cm_apply_payment wallet credit failed; claim released', [
                    'id_order' => $orderId,
                    'id_user' => $payment['id_user'] ?? null,
                    'amount' => $finalIrr,
                ]);
            }
            if ($callbackQueryId && function_exists('telegram')) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callbackQueryId,
                    'text' => '❌ شارژ کیف پول انجام نشد. دوباره تلاش کنید.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
            }
            return;
        }
        if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
            \Faoxima\Wallet\Ledger\Ledger::record($pdo, $payment['id_user'], 'credit', $finalIrr, 'topup_crypto', (string) ($payment['Payment_Method'] ?? 'crypto'), $orderId);
        }
        $balanceRow = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', 'Balance', 'id', $payment['id_user'], 'select', ['cache' => false]) : null;
        $newBalance = is_array($balanceRow) ? (int) ($balanceRow['Balance'] ?? 0) : ((is_array($userRow) ? (int) ($userRow['Balance'] ?? 0) : 0) + $finalIrr);
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            \Faoxima\Core\Database\Db::update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $orderId);
        }

        $__cmDiscountCode = trim((string) ($payment['discount_code'] ?? ''));
        $__cmDiscountAlreadyConsumed = (string) ($payment['discount_consumed'] ?? '') === '1';
        if ($__cmDiscountCode !== '' && !$__cmDiscountAlreadyConsumed && class_exists('Faoxima\Orders\Discounts\DiscountRules') && is_array($userRow)) {
            \Faoxima\Core\Database\Db::update('Payment_report', 'discount_consumed', '1', 'id_order', $orderId);
            \Faoxima\Orders\Discounts\DiscountRules::markSellUsed(\Faoxima\Api\Runtime\Db::pdo(), $__cmDiscountCode, $userRow);
            \Faoxima\Orders\Discounts\DiscountRules::logOrderDiscount(\Faoxima\Api\Runtime\Db::pdo(), [
                'id_user' => $payment['id_user'],
                'code' => $__cmDiscountCode,
                'kind' => 'sell',
                'value_type' => null,
                'value_raw' => null,
                'price_before' => (float) ($payment['price_before_discount'] ?? 0),
                'discount_amount' => (float) ($payment['discount_amount'] ?? 0),
                'price_after' => $finalIrr,
                'section' => 'charge',
            ]);
        }
        $coinAmt = rtrim(rtrim(number_format((float) ($payment['crypto_amount'] ?? 0), 9, '.', ''), '0'), '.');
        $explorerUrl = is_callable([\Faoxima\Payments\Gateways\CryptoQuotes::class, 'cryptoExplorerUrl'])
            ? \Faoxima\Payments\Gateways\CryptoQuotes::cryptoExplorerUrl((string) ($payment['crypto_currency'] ?? ''), (string) ($payment['crypto_tx_hash'] ?? ''))
            : '';
        $explorerLine = $explorerUrl !== '' ? "🔗 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده تراکنش</a>" : '';
        if (function_exists('sendmessage')) {
            sendmessage(
                (string) $payment['id_user'],
                "✅ <b>درخواست بررسی دستی شما تایید شد و کیف پول‌تان شارژ گردید.</b>\n\n"
                . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                . "💎 ارز: <b>" . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "</b>\n"
                . "🪙 مقدار: <code>{$coinAmt}</code>\n"
                . "💵 مبلغ شارژ شده: " . number_format($finalIrr) . " تومان\n"
                . "💰 موجودی جدید: " . number_format($newBalance) . " تومان"
                . ($explorerLine !== '' ? "\n{$explorerLine}" : ''),
                null,
                'HTML'
            );
        }
        if ($callbackQueryId && function_exists('telegram')) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text' => '✅ تایید شد و کیف پول شارژ شد.',
                'cache_time' => 0,
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

    public static function crypto_save_wallet_memo(string $currency, string $memo): bool
    {
        $supported = self::crypto_supported_currencies();
        $manual = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? self::crypto_manual_currencies() : [];
        if (!isset($supported[$currency]) && !isset($manual[$currency])) return false;
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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

    public static function crypto_get_irt_rates(array $wantKeys = []): array
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
        if (!empty($providerKeys) && method_exists(\Faoxima\Payments\Gateways\Tronado\Runtime::class, 'requireTronRates')) {


            foreach ($providerKeys as $pk) {
                $r = \Faoxima\Payments\Gateways\Tronado\Runtime::requireTronRates([$pk]);
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


        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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

    public static function crypto_irt_rate_for(string $currency): ?float
    {


        if ($currency === 'TRX') {
            $rates = self::crypto_get_irt_rates(['TRX']);
            return $rates['TRX'] ?? null;
        }
        if ($currency === 'TON') {
            $rates = self::crypto_get_irt_rates(['TON']);
            return $rates['TON'] ?? null;
        }
        if ($currency === 'USDT_TRC20' || $currency === 'USDT_TON' || strpos($currency, 'USDT_') === 0) {
            $rates = self::crypto_get_irt_rates(['USDT']);
            return $rates['USDT'] ?? null;
        }
        return null;
    }

    public static function crypto_display_decimals(string $currency): int
    {
        $defaults = [
            'TRX'        => 2,
            'USDT_TRC20' => 2,
            'USDT_TON'   => 2,
            'TON'        => 2,
        ];
        $cfg = self::crypto_pay_setting('cryptocheck_display_decimals_' . $currency, '');
        if ($cfg !== '' && ctype_digit($cfg)) {
            return max(2, min(6, (int) $cfg));
        }
        return $defaults[$currency] ?? 2;
    }

    public static function crypto_unique_amount(float $baseCoinAmount, string $currency, ?int $extraDecimalsOverride = null, bool $iranianMode = false): float
    {
        $displayDecimals = self::crypto_display_decimals($currency);
        $scale = (int) pow(10, $displayDecimals);
        $maxNoiseCfg = self::crypto_pay_setting('cryptocheck_max_cent_noise', '');
        $maxNoise = ($maxNoiseCfg !== '' && ctype_digit($maxNoiseCfg)) ? (int) $maxNoiseCfg : 24;
        $maxNoise = max(1, min($maxNoise, $scale - 1));

        $baseUnits = (int) ceil($baseCoinAmount * $scale - 1e-9);
        if ($baseUnits < 1) $baseUnits = 1;

        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        $existing = [];
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT crypto_amount FROM Payment_report
                      WHERE crypto_currency = :c
                        AND payment_Status IN ('Unpaid','AwaitingHash')
                        AND crypto_amount IS NOT NULL"
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

    public static function crypto_create_invoice($userId, int $amountIrt, string $currency, string $invoiceMeta = '', bool $iranianMode = false, ?string $source = null): array
    {
        $currencies = self::crypto_supported_currencies() + (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? self::crypto_manual_currencies() : []);
        if (!isset($currencies[$currency])) {
            return ['ok' => false, 'error' => 'currency-not-supported'];
        }
        $wallet = self::crypto_active_wallet($currency);
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

        $rate = self::crypto_irt_rate_for($currency);
        if ($rate === null || $rate <= 0) {
            return ['ok' => false, 'error' => 'rate-unavailable'];
        }
        $rawCoin = $amountIrt / $rate;
        $finalCoin = self::crypto_unique_amount($rawCoin, $currency, null, $iranianMode);
        $finalAmountIrt = (int) round($finalCoin * $rate);

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
                     id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_wallet_to, crypto_iranian_mode, crypto_rate_irt, source)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $userIdStr = (string) $userId;
            $amountIrtStr = (string) $amountIrt;
            $coinStr = number_format($finalCoin, $currencies[$currency]['decimals'], '.', '');
            $iranianModeStr = '0';
            $rateStr = (string) $rate;
            $sourceStr = ($source !== null && $source !== '') ? $source : null;
            $stmt->bind_param(
                'ssssssssssssss',
                $userIdStr, $orderId, $now, $amountIrtStr, $statusUnpaid, $methodLabel,
                $invoiceMeta, $currency, $network, $coinStr, $wallet['wallet_address'], $iranianModeStr, $rateStr, $sourceStr
            );
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[crypto] create_invoice: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'db-write-failed'];
        }

        $ttl = 1800;
        return [
            'ok'                => true,
            'order_id'          => $orderId,
            'amount_coin'       => $finalCoin,
            'base_amount_coin'  => $rawCoin,
            'base_amount_irt'   => $amountIrt,
            'final_amount_irt'  => $finalAmountIrt,
            'wallet'            => $wallet['wallet_address'],
            'wallet_memo'       => trim((string) ($wallet['wallet_memo'] ?? '')),
            'currency'          => $currency,
            'network'           => $network,
            'rate'              => $rate,
            'expires_at'        => time() + $ttl,
        ];
    }

    public static function crypto_check_tx_timestamp_after_invoice(int $txTimestamp, int $invoiceCreatedAt, int $toleranceSec = 120): bool
    {
        if ($txTimestamp <= 0 || $invoiceCreatedAt <= 0) return true;
        return $txTimestamp >= ($invoiceCreatedAt - $toleranceSec);
    }

    public static function crypto_check_sender_lock(string $senderAddress, string $currency, string $telegramUserId): array
    {
        $senderAddress = trim($senderAddress);
        $currency = trim($currency);
        $telegramUserId = trim($telegramUserId);
        if ($senderAddress === '' || $currency === '' || $telegramUserId === '') {
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
        try {
            $q = $pdo->prepare(
                "SELECT telegram_user_id FROM crypto_sender_locks
                  WHERE sender_address = :s AND currency = :c
                  LIMIT 1"
            );
            $q->execute([':s' => $senderAddress, ':c' => $currency]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return ['ok' => true, 'first_use' => true, 'locked_to' => null];
            }
            $lockedTo = (string) ($row['telegram_user_id'] ?? '');
            if ($lockedTo === $telegramUserId) {
                return ['ok' => true, 'first_use' => false, 'locked_to' => $lockedTo];
            }
            return ['ok' => false, 'first_use' => false, 'locked_to' => $lockedTo];
        } catch (Throwable $e) {
            error_log('[crypto] check_sender_lock: ' . $e->getMessage());
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
    }

    public static function crypto_record_sender_lock(string $senderAddress, string $currency, string $telegramUserId): bool
    {
        $senderAddress = trim($senderAddress);
        $currency = trim($currency);
        $telegramUserId = trim($telegramUserId);
        if ($senderAddress === '' || $currency === '' || $telegramUserId === '') return false;
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $ins = $pdo->prepare(
                "INSERT INTO crypto_sender_locks (sender_address, currency, telegram_user_id, last_used_at, use_count)
                 VALUES (:s, :c, :u, CURRENT_TIMESTAMP, 1)
                 ON DUPLICATE KEY UPDATE
                    last_used_at = CURRENT_TIMESTAMP,
                    use_count = use_count + 1"
            );
            $ins->execute([':s' => $senderAddress, ':c' => $currency, ':u' => $telegramUserId]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] record_sender_lock: ' . $e->getMessage());
            return false;
        }
    }

    public static function crypto_record_verified_hash(string $orderId, string $source = 'auto_cron'): bool
    {
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $row = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('Payment_report', '*', 'id_order', $orderId, 'select') : null;
            if (!is_array($row)) return false;
            $hash = trim((string)($row['crypto_tx_hash'] ?? ''));
            if ($hash === '') return false;
            $ins = $pdo->prepare(
                "INSERT IGNORE INTO crypto_verified_hashes
                 (tx_hash, currency, network, wallet_to, sender_address, amount_coin, amount_irr, order_id, user_id, verification_source)
                 VALUES (:h, :c, :n, :w, :s, :ac, :ai, :o, :u, :src)"
            );
            $walletTo = (string)($row['crypto_wallet_to'] ?? '');
            $senderAddr = (string)($row['crypto_sender_address'] ?? '');
            $userIdStr = (string)($row['id_user'] ?? '');
            $ins->execute([
                ':h' => $hash,
                ':c' => (string)($row['crypto_currency'] ?? ''),
                ':n' => (string)($row['crypto_network'] ?? ''),
                ':w' => $walletTo !== '' ? $walletTo : null,
                ':s' => $senderAddr !== '' ? $senderAddr : null,
                ':ac' => $row['crypto_amount'] ?? null,
                ':ai' => (int)($row['price'] ?? 0),
                ':o' => $orderId,
                ':u' => $userIdStr !== '' ? $userIdStr : null,
                ':src' => $source,
            ]);
            return $ins->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[crypto] record_verified_hash: ' . $e->getMessage());
            return false;
        }
    }

    public static function crypto_lookup_verified_hash(string $hash): ?array
    {
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!($pdo instanceof PDO) || trim($hash) === '') return null;
        try {
            $q = $pdo->prepare("SELECT * FROM crypto_verified_hashes WHERE tx_hash = :h LIMIT 1");
            $q->execute([':h' => trim($hash)]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function crypto_attach_hash(string $orderId, string $hashOrUrl): array
    {
        $hash = self::crypto_extract_hash($hashOrUrl);
        if ($hash === null) {
            return ['ok' => false, 'error' => 'invalid-hash'];
        }
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
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

    public static function crypto_http_get_json(string $url, array $headers = [], int $timeoutMs = 7000): ?array
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

    public static function crypto_amount_within_tolerance(float $expected, float $observed, bool $iranianMode = false): bool
    {
        if ($expected <= 0) return false;

        $shortPct = (float) self::crypto_pay_setting('cryptocheck_amount_tolerance', '0');
        $shortPct = max(0.0, min($shortPct, 5.0));

        $overPct = (float) self::crypto_pay_setting('cryptocheck_overpay_tolerance', '0');
        $overPct = max(0.0, min($overPct, 100.0));

        $shortTol = $expected * ($shortPct / 100.0);
        $overTol  = $expected * ($overPct / 100.0);


        $eps = max(abs($expected), abs($observed)) * 1e-9;

        $diff = $observed - $expected;
        if ($diff >= -($shortTol + $eps) && $diff <= ($overTol + $eps)) {
            return true;
        }
        return false;
    }

    public static function crypto_check_tron_tx(string $hash, string $expectedTo, float $expectedAmount, ?string $tokenContract = null, bool $iranianMode = false): array
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
        $key = self::crypto_pay_setting('cryptocheck_trongrid_key', '');
        $headers = [];
        if ($key !== '') $headers[] = 'TRON-PRO-API-KEY: ' . $key;

        $tx = self::crypto_http_get_json($url, $headers);
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

        $txTimestampMs = (int) ($tx['timestamp'] ?? 0);
        $txTimestampSec = $txTimestampMs > 0 ? (int) floor($txTimestampMs / 1000) : 0;

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
            if (!self::crypto_amount_within_tolerance($expectedAmount, $amountTrx, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amountTrx, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($tx['ownerAddress'] ?? $tx['contractData']['owner_address'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTrx, 'to' => $to, 'sender' => $sender, 'tx_timestamp' => $txTimestampSec]];
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
            if (!self::crypto_amount_within_tolerance($expectedAmount, $amount, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amount, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($t['from_address'] ?? $t['fromAddress'] ?? $tx['ownerAddress'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amount, 'to' => $to, 'sender' => $sender, 'tx_timestamp' => $txTimestampSec]];
        }
        return ['ok' => false, 'reason' => 'no-matching-trc20-transfer'];
    }
}
