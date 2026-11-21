<?php

namespace Faoxima\Admin\Telegram\Bootstrap;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $callback_query_id, $datain, $from_id, $message_id, $text, $update.
 * Inherited or exported by reference: $rx_pem_entry_step, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PreDispatch
{
    public static function handle(
        $adminExecution,
        $adminrulecheck,
        $callback_query_id,
        $datain,
        $from_id,
        $message_id,
        &$rx_pem_entry_step,
        $text,
        $update,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxPremiumEmojiCancelKind')
            && ($adminrulecheck['rule'] ?? '') === "administrator"
            && in_array(isset($rx_pem_entry_step) ? (string) $rx_pem_entry_step : (string) ($user['step'] ?? ''), \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiInputSteps(), true)) {
            $rxPemCancelKind = \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiCancelKind($text ?? '', $datain ?? '');
            $rxPemStepLeft = !in_array((string) ($user['step'] ?? ''), \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiInputSteps(), true);
            if ($rxPemCancelKind !== null || $rxPemStepLeft) {
                $rxPemCancelPage = 1;
                $rxPemCancelState = (string) ($user['Processing_value'] ?? '');
                if (preg_match('/^\d+:(\d+)$/', $rxPemCancelState, $rxPemCancelPg)) {
                    $rxPemCancelPage = max(1, (int) $rxPemCancelPg[1]);
                } elseif (preg_match('/^packlink:(\d+)$/', $rxPemCancelState, $rxPemCancelPg)) {
                    $rxPemCancelPage = max(1, (int) $rxPemCancelPg[1]);
                }
                if (preg_match('/^premium_emoji_pack_link_(\d+)$/', (string)($rx_pem_entry_step ?? $user['step'] ?? ''), $rxPemCancelPg)) {
                    $rxPemCancelPage = max(1, (int)$rxPemCancelPg[1]);
                }
                \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                $user['step'] = 'home';
                $user['Processing_value'] = '0';
                if ($rxPemCancelKind === 'panel') {
                    if (is_string($datain) && preg_match('/^premium_emoji_settings_(\d+)$/', $datain, $rxPemCancelPg)) {
                        $rxPemCancelPage = max(1, (int) $rxPemCancelPg[1]);
                    }
                    unset($rx_pem_entry_step);
                    if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxRenderPremiumEmojiPanel')) {
                        \Faoxima\Admin\Telegram\PremiumEmoji::rxRenderPremiumEmojiPanel($from_id, $rxPemCancelPage);
                    }
                    return;
                }
            }
            unset($rxPemCancelKind, $rxPemStepLeft);
        }
        unset($rx_pem_entry_step);

        if (method_exists(\Faoxima\Admin\Telegram\StartMediaAdmin::class, 'rxStartMediaHandleAdminUpdate')
            && \Faoxima\Admin\Telegram\StartMediaAdmin::rxStartMediaHandleAdminUpdate($from_id, $datain ?? '', $update ?? [], $user, ($adminrulecheck['rule'] ?? '') === "administrator")) {
            return;
        }

        if (method_exists(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::class, 'rx_sbv_admin_handle')
            && \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_admin_handle([
                'from_id' => $from_id,
                'datain' => $datain ?? '',
                'text' => $text ?? '',
                'update' => $update ?? [],
                'user' => $user ?? [],
                'rule' => $adminrulecheck['rule'] ?? '',
                'callback_query_id' => $callback_query_id ?? '',
                'message_id' => $message_id ?? 0,
            ])) {
            return;
        }

        if ((isset($datain) && is_string($datain) && ($datain === 'admin_readiness' || strpos($datain, 'srd_') === 0))
            || (is_string($text) && trim($text) === "🚦 آمادگی فروش")) {
            if (\Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_handle_admin_update()) {
                return;
            }
        }

        return true;
    }
}
