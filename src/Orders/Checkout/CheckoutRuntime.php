<?php

namespace Faoxima\Orders\Checkout;

use PDO;
use Throwable;

final class CheckoutRuntime
{
    public static function rx_ck_fetch(string $checkoutId, $userId = null): ?array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::fetch($pdo, $checkoutId, $userId);
    }

    public static function rx_ck_open(array $p): ?array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::open($pdo, $p);
    }

    public static function rx_ck_preview(array $ck): array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::preview($pdo, $ck);
    }

    public static function rx_ck_select(string $checkoutId, $userId, string $choice): array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::select($pdo, $checkoutId, $userId, $choice);
    }

    public static function rx_ck_confirm(string $checkoutId, $userId, string $choice, ?int $expectedWallet = null, ?int $expectedExternal = null): array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::confirm($pdo, $checkoutId, $userId, $choice, $expectedWallet, $expectedExternal);
    }

    public static function rx_ck_cancel(string $checkoutId, $userId, string $reason = 'user_cancelled'): array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::cancel($pdo, $checkoutId, $userId, $reason);
    }

    public static function rx_ck_validate_payment(string $checkoutId, $userId, ?string $method = null): array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::validatePayment($pdo, $checkoutId, $userId, $method);
    }

    public static function rx_ck_find_for_key($userId, string $opKey, array $statuses = ['confirmed', 'awaiting_payment']): ?array
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Checkout::findForKey($pdo, $userId, $opKey, $statuses);
    }

    public static function rx_ck_release_for_order(string $orderId, string $newStatus = 'cancelled', string $reason = 'payment_closed', bool $force = false): string
    {
        global $pdo;
        return \Faoxima\Orders\Checkout\Settlement::releaseForOrder($pdo, $orderId, $newStatus, $reason, $force);
    }

    public static function rx_ck_money($value): string
    {
        return number_format((int) round((float) $value));
    }

    public static function rx_ck_text(string $key, string $default): string
    {
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get')) {
            return (string) \Faoxima\Telegram\Support\Texts::faoxima_textbot_get($key, $default);
        }
        return $default;
    }

    public static function rx_ck_choice_text(array $preview): string
    {
        $lines = [];
        $lines[] = self::rx_ck_text('dyn_ck_title', '🧾 <b>پرداخت سفارش</b>');
        $lines[] = '';
        $lines[] = '💰 ' . self::rx_ck_text('dyn_ck_final_price', 'قیمت نهایی') . ': <b>' . self::rx_ck_money($preview['final_price']) . '</b> تومان';
        $lines[] = '💎 ' . self::rx_ck_text('dyn_ck_recorded_balance', 'موجودی کیف پول') . ': ' . self::rx_ck_money($preview['recorded_balance']) . ' تومان';
        $lines[] = '✅ ' . self::rx_ck_text('dyn_ck_available', 'موجودی قابل استفاده') . ': ' . self::rx_ck_money($preview['available_balance']) . ' تومان';
        $lines[] = '';
        $lines[] = self::rx_ck_text('dyn_ck_choose', 'آیا می‌خواهید از موجودی کیف پول در این پرداخت استفاده کنید؟');
        return implode("\n", $lines);
    }

    public static function rx_ck_summary_text(array $preview, string $choice): string
    {
        $amounts = $choice === 'use' ? $preview['use_wallet'] : $preview['no_wallet'];
        $lines = [];
        $lines[] = self::rx_ck_text('dyn_ck_summary_title', '🧾 <b>خلاصه پرداخت</b>');
        $lines[] = '';
        $lines[] = '💰 ' . self::rx_ck_text('dyn_ck_final_price', 'قیمت نهایی') . ': <b>' . self::rx_ck_money($preview['final_price']) . '</b> تومان';
        if ($choice === 'use') {
            $lines[] = '💼 ' . self::rx_ck_text('dyn_ck_wallet_part', 'سهم کیف پول') . ': <b>' . self::rx_ck_money($amounts['wallet_amount']) . '</b> تومان';
            $lines[] = '💳 ' . self::rx_ck_text('dyn_ck_external_part', 'مبلغ پرداخت خارجی') . ': <b>' . self::rx_ck_money($amounts['external_amount']) . '</b> تومان';
            $lines[] = '';
            $lines[] = self::rx_ck_text('dyn_ck_wallet_note', 'سهم کیف پول تا زمان تأیید پرداخت کسر نمی‌شود و موجودی شما تا آن زمان قابل استفاده است. اگر هنگام تأیید پرداخت موجودی کافی نباشد، مبلغ پرداخت‌شده به کیف پول شما اضافه می‌شود.');
        } else {
            $lines[] = '💼 ' . self::rx_ck_text('dyn_ck_wallet_part', 'سهم کیف پول') . ': <b>0</b> تومان';
            $lines[] = '💳 ' . self::rx_ck_text('dyn_ck_external_part', 'مبلغ پرداخت خارجی') . ': <b>' . self::rx_ck_money($amounts['external_amount']) . '</b> تومان';
            $lines[] = '';
            $lines[] = self::rx_ck_text('dyn_ck_no_wallet_note', 'کل مبلغ از طریق روش پرداخت انتخابی دریافت می‌شود و موجودی کیف پول شما تغییری نمی‌کند.');
        }
        return implode("\n", $lines);
    }

    public static function rx_ck_button_style(string $key): ?string
    {
        static $styles = null;
        if ($styles === null) {
            global $pdo;
            $saved = [];
            try {
                if ($pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode((string) $row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['wallet_checkout']) && is_array($all['wallet_checkout'])) {
                            $saved = array_filter($all['wallet_checkout'], static function ($v) { return $v !== 'default'; });
                        }
                    }
                }
            } catch (Throwable $e) {
            }
            $defaults = [];
            if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                $defaults = (array) rx_getKeyboardDefaultStyles('wallet_checkout');
            }
            $styles = $saved + $defaults;
        }
        $style = $styles[$key] ?? null;
        return in_array($style, ['primary', 'success', 'danger'], true) ? $style : null;
    }

    public static function rx_ck_btn(string $key, string $text, string $callback): array
    {
        $btn = ['text' => $text, 'callback_data' => $callback];
        $style = self::rx_ck_button_style($key);
        if ($style === null) {
            $btn['_rxap'] = 1;
        } else {
            $btn['style'] = $style;
            $btn['_rxkeep'] = 1;
        }
        return $btn;
    }

    public static function rx_ck_bot_choice_keyboard(array $preview): string
    {
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        return \Faoxima\Telegram\Keyboard\CheckoutKeyboard::choice($preview, [\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_btn'], [\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_text']);
    }

    public static function rx_ck_bot_summary_keyboard(string $checkoutId): string
    {
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        return \Faoxima\Telegram\Keyboard\CheckoutKeyboard::summary($checkoutId, [\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_btn'], [\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_text']);
    }

    public static function rx_ck_bot_show($chatId, $messageId, string $text, string $keyboard): void
    {
        if ((int) $messageId > 0 && function_exists('Editmessagetext')) {
            Editmessagetext($chatId, $messageId, $text, $keyboard, 'HTML');
        } else {
            sendmessage($chatId, $text, $keyboard, 'HTML');
        }
    }

    public static function rx_ck_bot_begin(array $user, array $p)
    {
        global $step_payment;
        $chatId = $user['id'];
        $messageId = (int) ($p['message_id'] ?? 0);
        $ck = self::rx_ck_open([
            'id_user' => $chatId,
            'operation' => (string) $p['operation'],
            'op_key' => (string) $p['op_key'],
            'target' => (string) ($p['target'] ?? ''),
            'final_price' => $p['final_price'],
            'source' => 'bot',
            'meta' => ['one' => (string) ($p['one'] ?? ''), 'tow' => (string) ($p['tow'] ?? ''), 'four' => (string) ($p['four'] ?? '')],
        ]);
        if ($ck === null) {
            sendmessage($chatId, self::rx_ck_text('dyn_ck_failed', '❌ خطایی در آماده‌سازی پرداخت رخ داد. لطفاً مراحل را دوباره انجام دهید.'), null, 'HTML');
            return null;
        }
        $preview = self::rx_ck_preview($ck);
        if (!$preview['can_use_wallet']) {
            $sel = self::rx_ck_select((string) $ck['checkout_id'], $chatId, 'none');
            $conf = self::rx_ck_confirm((string) $ck['checkout_id'], $chatId, 'none', 0, (int) $ck['final_price']);
            if (empty($conf['ok'])) {
                sendmessage($chatId, self::rx_ck_text('dyn_ck_failed', '❌ خطایی در آماده‌سازی پرداخت رخ داد. لطفاً مراحل را دوباره انجام دهید.'), null, 'HTML');
                return null;
            }
            self::rx_ck_bot_enter_payment($user, $conf['checkout'], $messageId);
            return $conf['checkout'];
        }
        \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', (string) ($p['one'] ?? ''), 'id', $chatId);
        \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', (string) ($p['tow'] ?? ''), 'id', $chatId);
        \Faoxima\Telegram\Navigation\Steps::step('ckw_choice', $chatId);
        self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_choice_text($preview), self::rx_ck_bot_choice_keyboard($preview));
        return $ck;
    }

    public static function rx_ck_bot_enter_payment(array $user, array $ck, int $messageId = 0): void
    {
        global $step_payment, $textbotlang;
        $chatId = $user['id'];
        $meta = \Faoxima\Orders\Checkout\Checkout::meta($ck);
        \Faoxima\Core\Database\Db::update('user', 'Processing_value', (string) (int) $ck['external_amount'], 'id', $chatId);
        \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', (string) ($meta['one'] ?? ''), 'id', $chatId);
        \Faoxima\Core\Database\Db::update('user', 'Processing_value_tow', (string) ($meta['tow'] ?? ''), 'id', $chatId);
        if (($meta['four'] ?? '') !== '') {
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_four', (string) $meta['four'], 'id', $chatId);
        }
        \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $chatId);
        $text = '';
        if ((int) $ck['wallet_amount'] > 0) {
            $text .= '💼 ' . self::rx_ck_text('dyn_ck_wallet_part', 'سهم کیف پول') . ': ' . self::rx_ck_money($ck['wallet_amount']) . " تومان\n";
        }
        $text .= '💳 ' . self::rx_ck_text('dyn_ck_external_part', 'مبلغ پرداخت خارجی') . ': ' . self::rx_ck_money($ck['external_amount']) . " تومان\n\n";
        $text .= (string) ($textbotlang['users']['sell']['None-credit'] ?? self::rx_ck_text('dyn_ck_select_method', 'روش پرداخت را انتخاب کنید:'));
        self::rx_ck_bot_show($chatId, $messageId, $text, $step_payment);
    }

    public static function rx_ck_bot_callback(array $user, string $datain, $messageId, $callbackQueryId = null): bool
    {
        if (!preg_match('/^ckw_(pick|ok|menu|cancel)_([a-f0-9]{16})(?:_(use|none))?$/', $datain, $m)) {
            return false;
        }
        global $keyboard;
        $chatId = $user['id'];
        $action = $m[1];
        $checkoutId = $m[2];
        $choice = $m[3] ?? '';
        $ck = self::rx_ck_fetch($checkoutId, $chatId);
        if ($ck === null) {
            self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
            return true;
        }
        if ($action === 'cancel') {
            $res = self::rx_ck_cancel($checkoutId, $chatId);
            \Faoxima\Telegram\Navigation\Steps::step('home', $chatId);
            if (!empty($res['ok'])) {
                self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_cancelled', '❌ خرید لغو شد و مبلغی از کیف پول شما کسر نشد.'), json_encode(['inline_keyboard' => []]));
            } else {
                self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_cancel_blocked', '⏳ این سفارش در حال پردازش است و لغو آن ممکن نیست.'), json_encode(['inline_keyboard' => []]));
            }
            return true;
        }
        if (in_array((string) $ck['status'], ['cancelled', 'expired', 'failed', 'completed'], true)) {
            self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
            return true;
        }
        if ($action === 'menu') {
            $preview = self::rx_ck_preview($ck);
            self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_choice_text($preview), self::rx_ck_bot_choice_keyboard($preview));
            return true;
        }
        if ($action === 'pick') {
            $sel = self::rx_ck_select($checkoutId, $chatId, $choice);
            if (empty($sel['ok'])) {
                $fresh = self::rx_ck_fetch($checkoutId, $chatId);
                $preview = $fresh !== null ? self::rx_ck_preview($fresh) : null;
                if ($preview !== null && in_array((string) $fresh['status'], ['awaiting_choice', 'confirmed'], true)) {
                    self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_refresh', '⚠️ موجودی شما تغییر کرده است. لطفاً دوباره انتخاب کنید.') . "\n\n" . self::rx_ck_choice_text($preview), self::rx_ck_bot_choice_keyboard($preview));
                } else {
                    self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
                }
                return true;
            }
            self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_summary_text($sel['preview'], $choice), self::rx_ck_bot_summary_keyboard($checkoutId));
            return true;
        }
        $choice = (string) $ck['wallet_choice'];
        if (!in_array($choice, ['use', 'none'], true)) {
            $preview = self::rx_ck_preview($ck);
            self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_choice_text($preview), self::rx_ck_bot_choice_keyboard($preview));
            return true;
        }
        $conf = self::rx_ck_confirm($checkoutId, $chatId, $choice, (int) $ck['wallet_amount'], (int) $ck['external_amount']);
        if (empty($conf['ok'])) {
            $fresh = self::rx_ck_fetch($checkoutId, $chatId);
            if ($fresh !== null && in_array((string) $fresh['status'], ['awaiting_choice', 'confirmed'], true)) {
                $preview = self::rx_ck_preview($fresh);
                self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_refresh', '⚠️ موجودی شما تغییر کرده است. لطفاً دوباره انتخاب کنید.') . "\n\n" . self::rx_ck_choice_text($preview), self::rx_ck_bot_choice_keyboard($preview));
            } else {
                self::rx_ck_bot_show($chatId, $messageId, self::rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
            }
            return true;
        }
        self::rx_ck_bot_enter_payment($user, $conf['checkout'], (int) $messageId);
        return true;
    }

    public static function rx_ck_bot_payment_guard(array &$user, $messageId = 0): bool
    {
        $tow = (string) ($user['Processing_value_tow'] ?? '');
        $one = (string) ($user['Processing_value_one'] ?? '');
        if (!in_array($tow, \Faoxima\Orders\Checkout\Checkout::operations(), true) || $one === '') {
            return true;
        }
        $opKey = $tow . '|' . $one;
        $ck = self::rx_ck_find_for_key($user['id'], $opKey, ['awaiting_choice', 'confirmed', 'awaiting_payment']);
        if ($ck === null) {
            return true;
        }
        if ((string) $ck['status'] === 'awaiting_choice') {
            $preview = self::rx_ck_preview($ck);
            \Faoxima\Telegram\Navigation\Steps::step('ckw_choice', $user['id']);
            self::rx_ck_bot_show($user['id'], (int) $messageId, self::rx_ck_choice_text($preview), self::rx_ck_bot_choice_keyboard($preview));
            return false;
        }
        $res = self::rx_ck_validate_payment((string) $ck['checkout_id'], $user['id']);
        if (empty($res['ok'])) {
            $fresh = self::rx_ck_fetch((string) $ck['checkout_id'], $user['id']);
            if ($fresh !== null && (string) $fresh['status'] === 'awaiting_choice') {
                $preview = self::rx_ck_preview($fresh);
                \Faoxima\Telegram\Navigation\Steps::step('ckw_choice', $user['id']);
                self::rx_ck_bot_show($user['id'], (int) $messageId, self::rx_ck_text('dyn_ck_refresh', '⚠️ موجودی شما تغییر کرده است. لطفاً دوباره انتخاب کنید.') . "\n\n" . self::rx_ck_choice_text($preview), self::rx_ck_bot_choice_keyboard($preview));
            } else {
                \Faoxima\Telegram\Navigation\Steps::step('home', $user['id']);
                sendmessage($user['id'], self::rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), null, 'HTML');
            }
            return false;
        }
        $user['Processing_value'] = (string) (int) $res['checkout']['external_amount'];
        \Faoxima\Core\Database\Db::update('user', 'Processing_value', $user['Processing_value'], 'id', $user['id']);
        return true;
    }
}
