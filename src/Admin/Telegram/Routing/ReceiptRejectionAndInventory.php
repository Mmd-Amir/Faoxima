<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $keyboardadmin, $message_id, $paymentreports, $setting, $text, $text_inline, $update, $user, $username.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReceiptRejectionAndInventory
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        $paymentreports,
        $setting,
        $text,
        $text_inline,
        $update,
        $user,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "reject-dec") {
            $adminAction = 1;
        } elseif (preg_match('/^confirmcryptomanual_(\w+)$/', (string) $datain, $cmConf) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 2;
        } elseif (preg_match('/^cmauto_(\w+)$/', (string) $datain, $cmAuto) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 3;
        } elseif (preg_match('/^cmmanual_(\w+)$/', (string) $datain, $cmMan) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 4;
        } elseif ($user['step'] == "cm_manual_irr_input" && empty($datain)) {
            $adminAction = 5;
        } elseif (preg_match('/^cmback_(\w+)$/', (string) $datain, $cmBack) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 6;
        } elseif (preg_match('/^rejectcryptomanual_(\w+)$/', (string) $datain, $cmRej) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
            $adminAction = 7;
        } elseif ($user['step'] == "reject_crypto_manual_reason" && empty($datain)) {
            $adminAction = 8;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ReceiptRejectionAndInventory::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            cmAuto: $cmAuto ?? null,
            cmBack: $cmBack ?? null,
            cmConf: $cmConf ?? null,
            cmMan: $cmMan ?? null,
            cmRej: $cmRej ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            paymentreports: $paymentreports ?? null,
            setting: $setting ?? null,
            text: $text ?? null,
            text_inline: $text_inline ?? null,
            update: $update ?? null,
            user: $user ?? null,
            username: $username ?? null
        );
    }
}
