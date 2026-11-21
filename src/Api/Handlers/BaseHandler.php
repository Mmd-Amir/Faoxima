<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use Throwable;

abstract class BaseHandler
{

    protected $user;


    protected $data;


    protected $method;


    protected $setting;


    private static $paySettingCache = [];


    private static $adminLookupCache = [];


    private static $diagRequestId = null;

    public function __construct(array $user, array $data)
    {
        $this->user = $user;
        $this->data = $data;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->setting = \Faoxima\Core\Database\Db::select('setting', '*');
    }


    protected function paySetting(string $name, string $default = ''): string
    {
        if (array_key_exists($name, self::$paySettingCache)) {
            return self::$paySettingCache[$name];
        }
        $row = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', $name, 'select');
        $value = is_array($row) ? (string)($row['ValuePay'] ?? $default) : $default;
        self::$paySettingCache[$name] = $value;
        return $value;
    }


    protected function diag(string $channel, string $step, array $ctx = []): void
    {
        try {
            $base = dirname(__DIR__, 3) . '/logs';
            if (!is_dir($base)) {
                @mkdir($base, 0775, true);
            }

            $entry = [
                'ts'      => date('Y-m-d H:i:s'),
                'req'     => $this->diagRequestId(),
                'channel' => $channel,
                'step'    => $step,
                'user_id' => $this->user['id'] ?? null,
                'agent'   => $this->user['agent'] ?? null,
                'method'  => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'ctx'     => $ctx,
            ];

            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                $line = json_encode([
                    'ts'      => date('Y-m-d H:i:s'),
                    'channel' => $channel,
                    'step'    => $step,
                    'ctx'     => '<unencodable diagnostic context>',
                ]);
            }

            $file = $base . '/' . $channel . '-' . date('Y-m-d') . '.log';
            @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
        }
    }

    private function diagRequestId(): string
    {
        if (self::$diagRequestId === null) {
            try {
                self::$diagRequestId = bin2hex(random_bytes(4));
            } catch (Throwable $e) {
                self::$diagRequestId = substr(md5(uniqid('', true)), 0, 8);
            }
        }
        return self::$diagRequestId;
    }


    protected function userIsAdmin(): bool
    {
        $uid = (string)($this->user['id'] ?? '');
        if ($uid === '') return false;
        if (array_key_exists($uid, self::$adminLookupCache)) {
            return self::$adminLookupCache[$uid];
        }
        $cnt = (int) \Faoxima\Core\Database\Db::select('admin', '*', 'id_admin', $uid, 'count');
        self::$adminLookupCache[$uid] = $cnt > 0;
        return self::$adminLookupCache[$uid];
    }


    protected function requireMethod(string $expected): void
    {
        if (strcasecmp($this->method, $expected) !== 0) {
            \Faoxima\Api\Runtime\Response::methodNotAllowed($expected);
        }
    }


    protected function loadPanelByCode(string $codePanel): array
    {
        $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', $codePanel, 'select');
        if (empty($panel)) {
            \Faoxima\Api\Runtime\Logger::userFacing('Panel not found', [
                'user_id' => $this->user['id'] ?? null,
                'code_panel' => $codePanel,
            ]);
            \Faoxima\Api\Runtime\Response::fail(404, 'panel not found (invalid id_panel)');
        }
        return $panel;
    }


    protected function decodeJsonField($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }


    protected function productIsAllowedForAgent(array $product, $agent): bool
    {
        return method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_product_allows_agent')
            && \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_product_allows_agent($product, $agent);
    }


    protected function resolveCountryId(): string
    {
        $value = \Faoxima\Api\Runtime\Input::string($this->data, 'country_id');
        if ($value === '') {
            $value = \Faoxima\Api\Runtime\Input::string($this->data, 'id_panel');
        }
        return $value;
    }

    protected function checkoutPayload(array $ck, ?array $preview = null, bool $choiceRequired = false): array
    {
        $preview = $preview ?? \Faoxima\Orders\Checkout\Checkout::preview(\Faoxima\Api\Runtime\Db::pdo(), $ck);
        return [
            'checkout_id'       => (string) $ck['checkout_id'],
            'operation'         => (string) $ck['operation'],
            'target'            => (string) ($ck['target'] ?? ''),
            'final_price'       => (int) $ck['final_price'],
            'recorded_balance'  => (int) $preview['recorded_balance'],
            'available_balance' => (int) $preview['available_balance'],
            'can_use_wallet'    => (bool) $preview['can_use_wallet'],
            'use_wallet'        => $preview['use_wallet'],
            'no_wallet'         => $preview['no_wallet'],
            'wallet_choice'     => (string) $ck['wallet_choice'],
            'wallet_amount'     => (int) $ck['wallet_amount'],
            'external_amount'   => (int) $ck['external_amount'],
            'status'            => (string) $ck['status'],
            'choice_required'   => $choiceRequired,
            'expires_at'        => (int) $ck['expires_at'],
        ];
    }

    protected function openCheckout(string $operation, string $opKey, string $target, int $finalPrice, array $meta = []): array
    {
        $ck = method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_open') ? \Faoxima\Orders\Checkout\Checkout::open(\Faoxima\Api\Runtime\Db::pdo(), [
            'id_user'     => (string) $this->user['id'],
            'operation'   => $operation,
            'op_key'      => $opKey,
            'target'      => $target,
            'final_price' => $finalPrice,
            'source'      => 'miniapp',
            'meta'        => $meta,
        ]) : null;
        if ($ck === null) {
            \Faoxima\Api\Runtime\Response::serverError(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_failed', '❌ خطایی در آماده‌سازی پرداخت رخ داد. لطفاً دوباره تلاش کنید.'));
        }
        $preview = \Faoxima\Orders\Checkout\Checkout::preview(\Faoxima\Api\Runtime\Db::pdo(), $ck);
        if (!$preview['can_use_wallet']) {
            $conf = \Faoxima\Orders\Checkout\Checkout::confirm(\Faoxima\Api\Runtime\Db::pdo(), (string) $ck['checkout_id'], $this->user['id'], 'none', 0, $finalPrice);
            if (empty($conf['ok'])) {
                \Faoxima\Api\Runtime\Response::serverError(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_failed', '❌ خطایی در آماده‌سازی پرداخت رخ داد. لطفاً دوباره تلاش کنید.'));
            }
            return ['checkout' => $conf['checkout'], 'preview' => $preview, 'choice_required' => false];
        }
        return ['checkout' => $ck, 'preview' => $preview, 'choice_required' => true];
    }

    protected function checkoutResponseFields(array $opened): array
    {
        $ck = $opened['checkout'];
        $payload = $this->checkoutPayload($ck, $opened['preview'], (bool) $opened['choice_required']);
        return [
            'checkout'              => $payload,
            'checkout_id'           => $payload['checkout_id'],
            'requires_wallet_choice' => (bool) $opened['choice_required'],
            'amount_due'            => (int) $ck['external_amount'],
        ];
    }

    protected function serverCheckoutForPayment(?string $checkoutId, string $expectedOpKey = ''): array
    {
        $uid = (string) $this->user['id'];
        $ck = ($checkoutId !== null && $checkoutId !== '' && method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_fetch')) ? \Faoxima\Orders\Checkout\Checkout::fetch(\Faoxima\Api\Runtime\Db::pdo(), $checkoutId, $uid) : null;
        if ($ck === null && $expectedOpKey !== '' && method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_find_for_key')) {
            $ck = \Faoxima\Orders\Checkout\Checkout::findForKey(\Faoxima\Api\Runtime\Db::pdo(), $uid, $expectedOpKey, ['confirmed', 'awaiting_payment']);
        }
        if ($ck === null) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_choice_required', '❌ ابتدا نحوه پرداخت (استفاده یا عدم استفاده از کیف پول) باید تایید شود. لطفاً خرید را دوباره انجام دهید.'));
        }
        if ($expectedOpKey !== '' && !hash_equals((string) $ck['op_key'], $expectedOpKey)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_mismatch', '❌ اطلاعات پرداخت با سفارش هم‌خوانی ندارد. لطفاً دوباره تلاش کنید.'));
        }
        $status = (string) $ck['status'];
        if ($status === 'awaiting_choice') {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_choice_required', '❌ ابتدا نحوه پرداخت (استفاده یا عدم استفاده از کیف پول) باید تایید شود.'), ['code' => 'choice_required', 'checkout' => $this->checkoutPayload($ck, null, true)]);
        }
        if (!in_array($status, ['confirmed', 'awaiting_payment'], true)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_expired', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), ['code' => $status]);
        }
        if ((int) $ck['external_amount'] <= 0) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_paymentinit_restart_purchase', '❌ مبلغ این خرید تغییر کرده است. لطفاً خرید را دوباره انجام دهید.'));
        }
        return $ck;
    }

    protected function validateCheckoutForPayment(array $ck, string $method): array
    {
        $res = \Faoxima\Orders\Checkout\Checkout::validatePayment(\Faoxima\Api\Runtime\Db::pdo(), (string) $ck['checkout_id'], $this->user['id'], $method);
        if (!empty($res['ok'])) {
            return $res['checkout'];
        }
        $code = (string) ($res['code'] ?? 'error');
        if ($code === 'refresh_required' && is_array($res['checkout'] ?? null)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_refresh', '⚠️ موجودی کیف پول شما تغییر کرده است. لطفاً مبالغ را دوباره تایید کنید.'), ['code' => 'refresh_required', 'checkout' => $this->checkoutPayload($res['checkout'], $res['preview'] ?? null, true)]);
        }
        \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_expired', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), ['code' => $code]);
    }

    abstract public function handle(): void;
}

