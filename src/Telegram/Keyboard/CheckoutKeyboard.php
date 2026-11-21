<?php

namespace Faoxima\Telegram\Keyboard;

final class CheckoutKeyboard
{
    public static function choice(array $preview, callable $button, callable $text): string
    {
        $id = (string) $preview['checkout_id'];
        $rows = [];
        if (!empty($preview['can_use_wallet'])) {
            $rows[] = [$button('ck_use_wallet', $text('dyn_ck_btn_use_wallet', '💼 استفاده از کیف پول'), 'ckw_pick_' . $id . '_use')];
        }
        $rows[] = [$button('ck_pay_external', $text('dyn_ck_btn_no_wallet', '💳 پرداخت بدون کیف پول'), 'ckw_pick_' . $id . '_none')];
        $rows[] = [$button('ck_cancel', $text('dyn_ck_btn_cancel', '❌ لغو خرید'), 'ckw_cancel_' . $id)];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function summary(string $checkoutId, callable $button, callable $text): string
    {
        return json_encode(['inline_keyboard' => [
            [$button('ck_confirm', $text('dyn_ck_btn_confirm', '✅ تایید و ادامه'), 'ckw_ok_' . $checkoutId)],
            [$button('ck_change', $text('dyn_ck_btn_change', '↩️ تغییر انتخاب'), 'ckw_menu_' . $checkoutId)],
            [$button('ck_cancel', $text('dyn_ck_btn_cancel', '❌ لغو خرید'), 'ckw_cancel_' . $checkoutId)],
        ]], JSON_UNESCAPED_UNICODE);
    }
}
