<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CheckoutHandler extends BaseHandler
{
    public string $mode = 'confirm';

    public function handle(): void
    {
        $this->requireMethod('POST');

        $checkoutId = FaoximaInput::string($this->data, 'checkout_id');
        if ($checkoutId === '' || !function_exists('rx_ck_fetch')) {
            FaoximaResponse::badRequest('checkout_id is required');
        }
        $uid = (string) $this->user['id'];
        $ck = rx_ck_fetch($checkoutId, $uid);
        if ($ck === null) {
            FaoximaResponse::notFound(faoxima_textbot_get('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'));
        }

        if ($this->mode === 'cancel') {
            $res = rx_ck_cancel($checkoutId, $uid);
            if (empty($res['ok'])) {
                FaoximaResponse::fail(409, faoxima_textbot_get('dyn_ck_cancel_blocked', '⏳ این سفارش در حال پردازش است و لغو آن ممکن نیست.'), ['code' => (string) ($res['code'] ?? 'error')]);
            }
            FaoximaResponse::ok([
                'kind'        => 'cancelled',
                'checkout_id' => $checkoutId,
                'message'     => faoxima_textbot_get('dyn_ck_cancelled', '❌ خرید لغو شد و مبلغی از کیف پول شما کسر نشد.'),
            ]);
        }

        if ($this->mode === 'preview') {
            FaoximaResponse::ok(['checkout' => $this->checkoutPayload($ck, null, (string) $ck['status'] === 'awaiting_choice')]);
        }

        $choice = FaoximaInput::string($this->data, 'choice');
        if (!in_array($choice, ['use', 'none'], true)) {
            FaoximaResponse::badRequest('choice must be use or none');
        }
        $expectedWallet = array_key_exists('expected_wallet_amount', $this->data) ? FaoximaInput::int($this->data, 'expected_wallet_amount', 0) : null;
        $expectedExternal = array_key_exists('expected_external_amount', $this->data) ? FaoximaInput::int($this->data, 'expected_external_amount', 0) : null;
        if ($expectedWallet === null || $expectedExternal === null) {
            FaoximaResponse::badRequest('expected_wallet_amount and expected_external_amount are required');
        }

        $checkoutTarget = (string) ($ck['target'] ?? '');
        if ($checkoutTarget !== '' && (string) ($ck['operation'] ?? '') === 'getconfigafterpay') {
            $targetInvoice = FaoximaDb::fetchOne(
                "SELECT Service_location FROM invoice WHERE username = :t AND id_user = :u ORDER BY time_sell DESC LIMIT 1",
                [':t' => $checkoutTarget, ':u' => $uid]
            );
            if (is_array($targetInvoice) && (string) ($targetInvoice['Service_location'] ?? '') !== '') {
                $targetPanel = select('marzban_panel', '*', 'name_panel', (string) $targetInvoice['Service_location'], 'select');
                if (jovadMiniAppPanelBlocked($targetPanel)) {
                    FaoximaResponse::fail(403, faoxima_textbot_get('dyn_jovad_miniapp_blocked', '❌ خرید این پنل از طریق مینی‌اپ امکان‌پذیر نیست. لطفاً از ربات تلگرام استفاده کنید.'));
                }
            }
        }

        $res = rx_ck_confirm($checkoutId, $uid, $choice, $expectedWallet, $expectedExternal);
        if (!empty($res['ok'])) {
            $fresh = rx_ck_fetch($checkoutId, $uid);
            FaoximaResponse::ok([
                'kind'     => 'checkout_confirmed',
                'checkout' => $this->checkoutPayload($fresh ?? $res['checkout']),
                'message'  => faoxima_textbot_get('dyn_ck_confirmed', '✅ پرداخت تایید شد.'),
            ]);
        }
        $code = (string) ($res['code'] ?? 'error');
        if ($code === 'refresh_required' && is_array($res['checkout'] ?? null)) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_ck_refresh', '⚠️ موجودی کیف پول شما تغییر کرده است. لطفاً مبالغ را دوباره تایید کنید.'), [
                'code'     => 'refresh_required',
                'checkout' => $this->checkoutPayload($res['checkout'], $res['preview'] ?? null, true),
            ]);
        }
        FaoximaResponse::fail(409, faoxima_textbot_get('dyn_ck_expired', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), ['code' => $code]);
    }
}
