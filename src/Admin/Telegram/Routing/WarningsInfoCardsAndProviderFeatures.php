<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $rxPanelAlreadyLoadedForEditpanel, $setting, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $code_panel, $dataget, $panel, $stmt, $value, $valuenew.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WarningsInfoCardsAndProviderFeatures
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        &$code_panel,
        &$dataget,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        &$panel,
        $pdo,
        $rxPanelAlreadyLoadedForEditpanel,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        &$value,
        &$valuenew
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminRouteMatched = false;
        $adminAction = null;

        if ($datain == "settimecornday" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 1;
        } elseif ($user['step'] == "getdaywarn") {
            $adminAction = 2;
        } elseif ($datain == "infocard_color_menu" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 3;
        } elseif (preg_match('/^infocard_setcolor_(yellow|green|red|blue|purple|orange)$/', $datain ?? '', $colorMatch) && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 4;
        } elseif ($text == "⚙️ وضعیت قابلیت ها پنل" && $adminrulecheck['rule'] == "administrator") {
            $adminAction = 5;
        } elseif (preg_match('/^editpanel-([^-]+)-([^-]+)-(.+)$/', $datain, $dataget)) {
            $adminAction = 6;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\WarningsInfoCardsAndProviderFeatures::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            code_panel: $code_panel,
            colorMatch: $colorMatch ?? null,
            dataget: $dataget ?? null,
            from_id: $from_id ?? null,
            keyboardadmin: $keyboardadmin ?? null,
            message_id: $message_id ?? null,
            panel: $panel,
            pdo: $pdo ?? null,
            rxPanelAlreadyLoadedForEditpanel: $rxPanelAlreadyLoadedForEditpanel ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            update: $update ?? null,
            user: $user ?? null,
            value: $value,
            valuenew: $valuenew
        );
    }
}
