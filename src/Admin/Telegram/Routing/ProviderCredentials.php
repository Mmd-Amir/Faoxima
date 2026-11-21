<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $update.
 * Inherited or exported by reference: $MethodUsername, $adminAction, $adminRouteMatched, $connectionResult, $dataget, $randomString, $status, $stmt, $user, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderCredentials
{
    public static function handle(
        &$MethodUsername,
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $backadmin,
        $callback_query_id,
        &$connectionResult,
        &$dataget,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        &$randomString,
        &$status,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        &$user,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if ($user['step'] == "add_pasarguard_auth_method" && preg_match('/pasarguardauth#(api_key|password)/', $datain, $dataget)) {
            $adminAction = 1;
        } elseif ($user['step'] == "add_pasarguard_username") {
            $adminAction = 2;
        } elseif ($user['step'] == "add_pasarguard_password") {
            $adminAction = 3;
        } elseif ($user['step'] == "add_pasarguard_api_key") {
            $adminAction = 4;
        } elseif ($user['step'] == "guard_svc_edit" && preg_match('/^guardsvc:(t):(\d+)$|^guardsvc:(a|n|s|x)$/', $datain, $matches)) {
            $adminAction = 5;
        } elseif (strpos((string) $datain, 'guardsvc:') === 0) {
            $adminAction = 6;
        } elseif ($user['step'] == "add_password_panel") {
            $adminAction = 7;
        } elseif ($user['step'] == "getlimitedpanel") {
            $adminAction = 8;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\ProviderCredentials::handle(
            MethodUsername: $MethodUsername,
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            connectionResult: $connectionResult,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            matches: $matches ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            randomString: $randomString,
            status: $status,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user,
            value: $value
        );
    }
}
