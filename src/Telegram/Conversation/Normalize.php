<?php

namespace Faoxima\Telegram\Conversation;

/**
 * Handler stage. Inherited by value: $admin_ids, $admin_ids_str, $callback_query_id, $from_id, $keyboard, $otherservice, $pdo, $setting, $textbotlang, $username.
 * Inherited or exported by reference: $datain, $text, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Normalize
{
    public static function handle(
        $admin_ids,
        $admin_ids_str,
        $callback_query_id,
        &$datain,
        $from_id,
        $keyboard,
        $otherservice,
        $pdo,
        $setting,
        &$text,
        $textbotlang,
        &$user,
        $username
    ) {
        if (!\Faoxima\Telegram\Conversation\InputAliases::apply(
            $datain,
            $text,
            $setting['inlinebtnmain'] ?? null,
            $from_id,
            $callback_query_id,
            $keyboard,
            method_exists(\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_resolveInlineButtonText') ? [\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_resolveInlineButtonText'] : null,
            function_exists('rx_restorePremiumReplyText') ? 'rx_restorePremiumReplyText' : null,
            function_exists('stripReplyStyleEmoji') ? 'stripReplyStyleEmoji' : null,
            method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_resolveAdminPanelCallback') ? [\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_resolveAdminPanelCallback'] : null,
            function_exists('telegram') ? 'telegram' : null,
            'sendmessage',
        )) {
            return;
        }
        
        if (method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_resolve_request')) {
            \Faoxima\Telegram\Presentation\Sections::rx_ui_resolve_request();
        }
        if (class_exists(\Faoxima\Telegram\Navigation\NavigationLogger::class)) {
            \Faoxima\Telegram\Navigation\NavigationLogger::button($from_id);
        }
        if ($user['username'] == "none" || $user['username'] == null || $user['username'] != $username) {
            \Faoxima\Core\Database\Db::update("user", "username", $username, "id", $from_id);
        }
        if ($user['register'] == "none") {
            \Faoxima\Core\Database\Db::update("user", "register", time(), "id", $from_id);
        }
        if (!in_array($user['agent'], ["n", "n2", "f"]))
            \Faoxima\Core\Database\Db::update("user", "agent", "f", "id", $from_id);
        
        if ($user['User_Status'] == "block" && !in_array($from_id, $admin_ids)) {
            $textblock = sprintf($textbotlang['users']['block']['descriptions'], $user['description_blocking']);
            sendmessage($from_id, $textblock, null, 'html');
            return;
        }
        
        if (!\Faoxima\Telegram\Conversation\RequestThrottle::allows(
            $pdo, $user, $setting, $from_id, $admin_ids, $admin_ids_str, $callback_query_id,
            $textbotlang, $otherservice ?? null, time(), [\Faoxima\Core\Database\Db::class, 'update'],
            'telegram', 'sendmessage',
        )) {
            return;
        }
        
        return true;
    }
}
