<?php

namespace Faoxima\Telegram\Bootstrap;

final class LocalizationHelpers
{
    public static function reportTopicIds(array $rows): array
    {
        return array_column($rows, 'idreport', 'report');
    }

    public static function purchaseNotesEnabled(array $setting, array $user): bool
    {
        return ($setting['statusnamecustom'] ?? '') == 'onnamecustom'
            && !(($setting['statusnoteforf'] ?? null) == '0' && ($user['agent'] ?? null) == 'f');
    }

    public static function createForumTopicIfMissing($currentId, $reportKey, $topicName, $channelId)
    {
        $numericId = intval($currentId);
        if ($numericId !== 0) {
            return;
        }

        $channelId = trim((string)$channelId);
        if ($channelId === '' || $channelId === '0') {
            return;
        }

        $marker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'faoxima_topic_' . md5($channelId . '|' . $reportKey) . '.lock';
        $markerExisted = is_file($marker);
        $markerHandle = @fopen($marker, 'c');
        if ($markerHandle !== false) {
            if (!@flock($markerHandle, LOCK_EX)) {
                @fclose($markerHandle);
                return;
            }
            clearstatcache(true, $marker);
            $lastAttempt = (int) @filemtime($marker);
            if ($markerExisted && $lastAttempt > 0 && (time() - $lastAttempt) < 3600) {
                @flock($markerHandle, LOCK_UN);
                @fclose($markerHandle);
                return;
            }
            @touch($marker);
            @flock($markerHandle, LOCK_UN);
            @fclose($markerHandle);
        }

        $response = telegram('createForumTopic', [
            'chat_id' => $channelId,
            'name' => $topicName
        ]);

        if (!is_array($response) || empty($response['ok'])) {
            $context = is_array($response) ? json_encode($response) : 'empty response';
            error_log("Failed to create forum topic {$reportKey}: {$context}");

            if (is_array($response) && isset($response['error_code']) && in_array($response['error_code'], [400, 403], true)) {
                \Faoxima\Core\Database\Db::update("topicid", "idreport", -1, "report", $reportKey);
            }

            return;
        }

        $threadId = $response['result']['message_thread_id'] ?? null;
        if ($threadId !== null) {
            \Faoxima\Core\Database\Db::update("topicid", "idreport", $threadId, "report", $reportKey);
        }
    }
}
