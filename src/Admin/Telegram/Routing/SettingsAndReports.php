<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrule, $adminrulecheck, $backadmin, $datain, $datatextbot, $editLinkMatches, $keyboard_stat, $keyboardadmin, $message_id, $otherreport, $pdo, $setting, $textbotlang, $update, $username.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $channelkeyboard, $dataget, $text, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class SettingsAndReports
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrule,
        $adminrulecheck,
        $backadmin,
        &$channelkeyboard,
        &$dataget,
        $datain,
        $datatextbot,
        $editLinkMatches,
        $keyboard_stat,
        $keyboardadmin,
        $message_id,
        $otherreport,
        $pdo,
        $setting,
        &$text,
        $textbotlang,
        $update,
        &$user,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }
        
        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }
        
        $adminAction = \Faoxima\Admin\Telegram\SettingsReportRoute::select(
            $text, $datain ?? null, $user, $adminrulecheck ?? [], $textbotlang, $dataget, $editLinkMatches,
        );
        if ($adminAction === null) {
            $adminAction = null;
            return true;
        }
        $adminRouteMatched = true;
        
        return match ($adminAction) {
                1 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::promptChannelLink($adminExecution, $user, $editLinkMatches ?? [], $backadmin ?? null),
                2 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::saveChannelLink($adminExecution, $pdo, $user, $text, $channelkeyboard, $update ?? []),
                3 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::promptChannelRemoval($adminExecution, $channelkeyboard, $textbotlang),
                4 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::removeChannel($adminExecution, $pdo, $text, $channelkeyboard, $update ?? [], $textbotlang),
                5 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::promptAdminId($adminExecution, $textbotlang, $backadmin ?? null),
                6 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::acceptAdminId($adminExecution, $text, $update ?? [], $textbotlang, $backadmin ?? null, $adminrule ?? null),
                7 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::saveAdmin($adminExecution, $pdo, $user, $text, $update ?? [], $textbotlang, $backadmin ?? null, $keyboardadmin ?? null, $username ?? null, $setting, $otherreport ?? null),
                8 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::promptUserTestLimit($adminExecution, $dataget ?? [], $textbotlang, $backadmin ?? null),
                9 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::saveUserTestLimit($adminExecution, $user, $text, $update ?? [], $textbotlang, $backadmin ?? null, $keyboardadmin ?? null),
                10 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::promptGlobalTestLimit($adminExecution, $textbotlang, $backadmin ?? null),
                11 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::saveGlobalTestLimit($adminExecution, $text, $update ?? [], $textbotlang, $backadmin ?? null, $keyboardadmin ?? null),
                12 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::showChannels($adminExecution, $user, $channelkeyboard, $textbotlang),
                13 => \Faoxima\Admin\Telegram\Handlers\SettingsAndReports::reportStatistics($adminExecution, $pdo, $datatextbot, $datain ?? null, $message_id ?? null, $keyboard_stat ?? null),
        };
    }
}
