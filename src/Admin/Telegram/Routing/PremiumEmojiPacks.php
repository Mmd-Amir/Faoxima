<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $pdo, $rxPemTotal, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $rxPemBase.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PremiumEmojiPacks
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
        $pdo,
        &$rxPemBase,
        $rxPemTotal,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (($datain == "premium_emoji_settings" || preg_match('/^premium_emoji_settings_(\d+)$/', (string)$datain, $rxPemPgMatch)) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif (preg_match('/^premium_emoji_pack(?:_(\d+))?$/', (string)$datain, $rxPemPackMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 2;
        } elseif (preg_match('/^premium_emoji_pack_page_(\d+)_(\d+)$/', (string)$datain, $rxPemPackView) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif (preg_match('/^premium_emoji_pack_view_\d+_[a-f0-9]{12}$/', (string)$datain) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($datain == "premium_emoji_status" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif ($datain == "premium_emoji_noop") {
            $adminAction = 6;
        } elseif ($datain == "premium_emoji_replace_confirm" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 7;
        } elseif ($datain == "premium_emoji_add" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 8;
        } elseif ($datain == "premium_emoji_add_single" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 9;
        } elseif ($datain == "premium_emoji_add_batch" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 10;
        } elseif ($datain == "premium_emoji_batch_continue" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 11;
        } elseif ($datain == "premium_emoji_batch_end" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 12;
        } elseif ($datain == "premium_emoji_del_all" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 13;
        } elseif ($datain == "premium_emoji_del_all_confirm" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 14;
        } elseif ($datain == "premium_emoji_scan" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 15;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\PremiumEmojiPacks::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            from_id: $from_id ?? null,
            pdo: $pdo ?? null,
            rxPemBase: $rxPemBase,
            rxPemPackMatch: $rxPemPackMatch ?? null,
            rxPemPackView: $rxPemPackView ?? null,
            rxPemPgMatch: $rxPemPgMatch ?? null,
            rxPemTotal: $rxPemTotal ?? null,
            update: $update ?? null,
            user: $user ?? null
        );
    }
}
