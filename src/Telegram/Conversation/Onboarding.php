<?php

namespace Faoxima\Telegram\Conversation;

/**
 * Handler stage. Inherited by value: $Chat_type, $admin_ids, $callback_query_id, $confrimrolls, $datatextbot, $domainhosts, $from_id, $keyboard, $message_id, $pdo, $setting, $textbotlang, $username.
 * Inherited or exported by reference: $connect, $datain, $text, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Onboarding
{
    public static function handle(
        $Chat_type,
        $admin_ids,
        $callback_query_id,
        $confrimrolls,
        &$connect,
        &$datain,
        $datatextbot,
        $domainhosts,
        $from_id,
        $keyboard,
        $message_id,
        $pdo,
        $setting,
        &$text,
        $textbotlang,
        &$user,
        $username
    ) {
        return \Faoxima\Telegram\Conversation\UserOnboarding::run(
            $pdo, $connect, $text, $datain, $user, $from_id, $username, $admin_ids,
            $callback_query_id ?? null, $message_id, $Chat_type, $domainhosts ?? null,
            $setting, $datatextbot, $textbotlang, $keyboard, $confrimrolls,
        );
    }
}
