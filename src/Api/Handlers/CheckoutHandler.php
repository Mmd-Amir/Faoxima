<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class CheckoutHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public string $mode = 'confirm';

    public function handle(): void
    {
        $this->requireMethod('POST');

        $checkoutId = \Faoxima\Api\Runtime\Input::string($this->data, 'checkout_id');
        if ($checkoutId === '' || !method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_fetch')) {
            \Faoxima\Api\Runtime\Response::badRequest('checkout_id is required');
        }
        $uid = (string) $this->user['id'];
        $ck = \Faoxima\Orders\Checkout\Checkout::fetch(\Faoxima\Api\Runtime\Db::pdo(), $checkoutId, $uid);
        if ($ck === null) {
            \Faoxima\Api\Runtime\Response::notFound(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'));
        }

        if ($this->mode === 'cancel') {
            $res = \Faoxima\Orders\Checkout\Checkout::cancel(\Faoxima\Api\Runtime\Db::pdo(), $checkoutId, $uid);
            if (empty($res['ok'])) {
                \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_cancel_blocked', '⏳ این سفارش در حال پردازش است و لغو آن ممکن نیست.'), ['code' => (string) ($res['code'] ?? 'error')]);
            }
            \Faoxima\Api\Runtime\Response::ok([
                'kind'        => 'cancelled',
                'checkout_id' => $checkoutId,
                'message'     => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_cancelled', '❌ خرید لغو شد و مبلغی از کیف پول شما کسر نشد.'),
            ]);
        }

        if ($this->mode === 'preview') {
            \Faoxima\Api\Runtime\Response::ok(['checkout' => $this->checkoutPayload($ck, null, (string) $ck['status'] === 'awaiting_choice')]);
        }

        $choice = \Faoxima\Api\Runtime\Input::string($this->data, 'choice');
        if (!in_array($choice, ['use', 'none'], true)) {
            \Faoxima\Api\Runtime\Response::badRequest('choice must be use or none');
        }
        $expectedWallet = array_key_exists('expected_wallet_amount', $this->data) ? \Faoxima\Api\Runtime\Input::int($this->data, 'expected_wallet_amount', 0) : null;
        $expectedExternal = array_key_exists('expected_external_amount', $this->data) ? \Faoxima\Api\Runtime\Input::int($this->data, 'expected_external_amount', 0) : null;
        if ($expectedWallet === null || $expectedExternal === null) {
            \Faoxima\Api\Runtime\Response::badRequest('expected_wallet_amount and expected_external_amount are required');
        }

        $checkoutTarget = (string) ($ck['target'] ?? '');
        if ($checkoutTarget !== '' && (string) ($ck['operation'] ?? '') === 'getconfigafterpay') {
            $targetInvoice = \Faoxima\Api\Runtime\Db::fetchOne(
                "SELECT Service_location FROM invoice WHERE username = :t AND id_user = :u ORDER BY time_sell DESC LIMIT 1",
                [':t' => $checkoutTarget, ':u' => $uid]
            );
            if (is_array($targetInvoice) && (string) ($targetInvoice['Service_location'] ?? '') !== '') {
                $targetPanel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', (string) $targetInvoice['Service_location'], 'select');
                if (\Faoxima\Services\Panels\Drivers\JovadPanel::jovadMiniAppPanelBlocked($targetPanel)) {
                    \Faoxima\Api\Runtime\Response::fail(403, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_jovad_miniapp_blocked', '❌ خرید این پنل از طریق مینی‌اپ امکان‌پذیر نیست. لطفاً از ربات تلگرام استفاده کنید.'));
                }
            }
        }

        $res = \Faoxima\Orders\Checkout\Checkout::confirm(\Faoxima\Api\Runtime\Db::pdo(), $checkoutId, $uid, $choice, $expectedWallet, $expectedExternal);
        if (!empty($res['ok'])) {
            $fresh = \Faoxima\Orders\Checkout\Checkout::fetch(\Faoxima\Api\Runtime\Db::pdo(), $checkoutId, $uid);
            \Faoxima\Api\Runtime\Response::ok([
                'kind'     => 'checkout_confirmed',
                'checkout' => $this->checkoutPayload($fresh ?? $res['checkout']),
                'message'  => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_confirmed', '✅ پرداخت تایید شد.'),
            ]);
        }
        $code = (string) ($res['code'] ?? 'error');
        if ($code === 'refresh_required' && is_array($res['checkout'] ?? null)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_refresh', '⚠️ موجودی کیف پول شما تغییر کرده است. لطفاً مبالغ را دوباره تایید کنید.'), [
                'code'     => 'refresh_required',
                'checkout' => $this->checkoutPayload($res['checkout'], $res['preview'] ?? null, true),
            ]);
        }
        \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_ck_expired', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), ['code' => $code]);
    }
}
