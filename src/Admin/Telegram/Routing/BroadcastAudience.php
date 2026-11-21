<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $result, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BroadcastAudience
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        &$dataget,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        &$result,
        &$stmt,
        $text,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/^typeusermessage-(\w+)/', $datain, $dataget)) {
            $adminAction = 1;
        } elseif (preg_match('/^typeagent-(\w+)/', $datain, $dataget)) {
            $adminAction = 2;
        } elseif (preg_match('/^pinduration-(\w+)/', $datain, $dataget)) {
            $adminAction = 3;
        } elseif (preg_match('/^locationmessage_(\w+)/', $datain, $dataget)) {
            $adminAction = 4;
        } elseif (preg_match('/^typepinmessage-(\w+)/', $datain, $dataget)) {
            $adminAction = 5;
        } elseif (preg_match('/^btntypemessage-(\w+)/', $datain, $dataget)) {
            $adminAction = 6;
        } elseif ($user['step'] == "gettextday") {
            $adminAction = 7;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\BroadcastAudience::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            result: $result,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null
        );
    }
}
