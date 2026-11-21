<?php

namespace Faoxima\Admin\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $adminExecution, $adminrulecheck, $backadmin, $callback_query_id, $caption, $datain, $from_id, $message_id, $pdo, $photo, $photoid, $rxRedisTestText, $setting, $text, $textbotlang, $user, $video, $videoid.
 * Inherited or exported by reference: $adminAction, $adminRouteMatched, $dataget, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TicketRepliesAndDiagnostics
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $caption,
        &$dataget,
        $datain,
        $from_id,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $rxRedisTestText,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        $adminAction = null;
        if ($adminRouteMatched) {
            return true;
        }

        if (preg_match('/Response_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
            $adminAction = 1;
        } elseif ($user['step'] == "getmessageAsAdmin") {
            $adminAction = 2;
        } elseif (preg_match('/^ticketadminreply_(\w+)$/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
            $adminAction = 3;
        } elseif ($user['step'] == "ticketadminreplyWait" && ($text !== '' || $photo || $video)) {
            $adminAction = 4;
        } elseif ($user['step'] == "ticketadminreplyWait") {
            $adminAction = 5;
        } elseif (
            ($text == "⚙️ وضعیت قابلیت ها" && $adminrulecheck['rule'] == "administrator")
            || (in_array((string)($datain ?? ''), ['featcat_main','featcat_bot','featcat_users','featcat_shop','featcat_lottery','featcat_crons','featcat_antispam','feat_info','feat_test','feat_help'], true) && $adminrulecheck['rule'] == "administrator")
        ) {
            $adminAction = 6;
        } elseif (is_string($datain ?? null)
            && ($datain === 'cronworkers_settings' || strpos($datain, 'cronworkers_pick_') === 0 || strpos($datain, 'cronworkers_set_') === 0)
            && $adminrulecheck['rule'] === 'administrator') {
            $adminAction = 7;
        } elseif ($datain === 'run_host_optimizer' && $adminrulecheck['rule'] === 'administrator') {
            $adminAction = 8;
        } elseif (is_string($datain ?? null)
            && ($datain === 'cronhealth_start' || strpos($datain, 'cronhealth_chk_') === 0)
            && $adminrulecheck['rule'] === 'administrator') {
            $adminAction = 9;
        } elseif ($datain === 'glass_mode_settings' && $adminrulecheck['rule'] === 'administrator') {
            $adminAction = 10;
        } elseif (strpos((string)$datain, 'richsec_') === 0 && method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_sections_admin_action')
            && \Faoxima\Telegram\Presentation\Sections::rx_ui_sections_admin_action((string)$datain, $from_id, ($adminrulecheck['rule'] ?? '') === 'administrator', $callback_query_id ?? null)) {
            $adminAction = 11;
        } elseif ($datain === 'run_redis_status' && $adminrulecheck['rule'] === 'administrator') {
            $adminAction = 12;
        } elseif ($datain === 'run_redis_test' && $adminrulecheck['rule'] === 'administrator') {
            $adminAction = 13;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\TicketRepliesAndDiagnostics::handle(
            adminAction: $adminAction ?? null,
            adminExecution: $adminExecution ?? null,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            caption: $caption ?? null,
            dataget: $dataget ?? null,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            rxRedisTestText: $rxRedisTestText ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
