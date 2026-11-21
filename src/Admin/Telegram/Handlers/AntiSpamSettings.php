<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $backadmin, $callback_query_id, $keyboardadmin, $text, $update.
 * Inherited or exported by reference: $adminAction, $adminExecution, $adminrulecheck, $dataget, $message_id, $pdo, $setting, $textbotlang.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AntiSpamSettings
{
    public static function handle(
        &$adminAction,
        &$adminExecution,
        &$adminrulecheck,
        $backadmin,
        $callback_query_id,
        &$dataget,
        $keyboardadmin,
        &$message_id,
        &$pdo,
        &$setting,
        $text,
        &$textbotlang,
        $update
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        if ($adminAction >= 1 && $adminAction <= 8) {
            return \Faoxima\Admin\Telegram\Handlers\AntiSpamActions::handle(
                $adminExecution, $adminAction, $setting, $callback_query_id ?? null,
                $message_id ?? null, $text ?? null, $update ?? [], $textbotlang ?? [],
                $backadmin ?? null, $keyboardadmin ?? null,
            );
        }

        if ($adminAction === 9) {
            return \Faoxima\Admin\Telegram\Handlers\FeatureToggle::handle(
                $adminExecution, $pdo, $setting, $dataget[1], $dataget[2], $adminrulecheck,
                $callback_query_id ?? null, $message_id, $textbotlang,
            );
        }

        return true;
    }
}
