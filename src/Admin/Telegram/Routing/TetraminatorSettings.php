<?php

namespace Faoxima\Admin\Telegram\Routing;

final class TetraminatorSettings
{
    public static function handle(
        &$adminAction,
        $adminExecution,
        &$adminRouteMatched,
        $adminrulecheck,
        $backadmin,
        $callback_query_id,
        $caption,
        $datain,
        $from_id,
        $pdo,
        $photo,
        $photoid,
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
        if (!is_array($adminrulecheck) || ($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return true;
        }

        $datain = (string) $datain;
        $step = is_array($user) ? (string) ($user['step'] ?? '') : '';
        $isMessageInput = $datain === ''
            && is_string($text)
            && $text !== ''
            && $text !== ($textbotlang['Admin']['backadmin'] ?? null)
            && $text !== ($textbotlang['Admin']['backmenu'] ?? null);
        $isMediaInput = $datain === '' && ($photo || $video || $isMessageInput);

        if ($datain === 'tetraminatorsetting') {
            $adminAction = 1;
        } elseif ($datain === 'tmset_name') {
            $adminAction = 2;
        } elseif ($datain === 'tmset_apikey') {
            $adminAction = 4;
        } elseif ($datain === 'tmset_cashback') {
            $adminAction = 6;
        } elseif ($datain === 'tmset_min') {
            $adminAction = 8;
        } elseif ($datain === 'tmset_max') {
            $adminAction = 10;
        } elseif ($datain === 'tmset_edu') {
            $adminAction = 12;
        } elseif ($datain === 'tmset_info') {
            $adminAction = 14;
        } elseif ($datain === 'tmset_back') {
            $adminAction = 15;
        } elseif ($step === 'tmset_name' && $isMessageInput) {
            $adminAction = 3;
        } elseif ($step === 'tmset_apikey' && $isMessageInput) {
            $adminAction = 5;
        } elseif ($step === 'tmset_cashback' && $isMessageInput) {
            $adminAction = 7;
        } elseif ($step === 'tmset_min' && $isMessageInput) {
            $adminAction = 9;
        } elseif ($step === 'tmset_max' && $isMessageInput) {
            $adminAction = 11;
        } elseif ($step === 'tmset_edu' && $isMediaInput) {
            $adminAction = 13;
        }

        if (!isset($adminAction)) {
            return true;
        }
        $adminRouteMatched = true;

        return \Faoxima\Admin\Telegram\Handlers\TetraminatorSettings::handle(
            adminAction: $adminAction,
            adminExecution: $adminExecution,
            backadmin: $backadmin ?? null,
            callback_query_id: $callback_query_id ?? null,
            caption: $caption ?? null,
            from_id: $from_id ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            text: $text ?? null,
            textbotlang: $textbotlang ?? null,
            video: $video ?? null,
            videoid: $videoid ?? null
        );
    }
}
