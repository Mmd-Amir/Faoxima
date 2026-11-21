<?php

namespace Faoxima\Telegram\Support;

use FaoximaWebhookAuth;
use Throwable;

final class Transport
{
    public static function getTelegramExpectedSecretToken()
    {
        global $APIKEY, $ApiToken;
        require_once REFACTORED_LEGACY_ROOT . '/lib/WebhookAuth.php';
        $child = isset($ApiToken) && $ApiToken !== '';
        return FaoximaWebhookAuth::secret((string) ($child ? $ApiToken : ($APIKEY ?? '')), !$child);
    }

    public static function verifyTelegramWebhookSecretToken()
    {
        $expected = self::getTelegramExpectedSecretToken();
        $provided = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        return $expected !== '' && is_string($provided) && $provided !== '' && hash_equals($expected, $provided);
    }

    public static function checktelegramip()
    {
        return self::verifyTelegramWebhookSecretToken() === true;
    }

    public static function inlineFixer($str, int $count_button = 1)
    {
        $str = trim($str);
        if (preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $str)) {
            if ($count_button >= 1) {
                switch ($count_button) {
                    case 1:
                        $maxLength = 56;
                        break;
                    case 2:
                        $maxLength = 24;
                        break;
                    case 3:
                        $maxLength = 14;
                        break;
                    default:
                        $maxLength = 2;
                }
                $visualLength = 2;
                $trimmedString = '';
                foreach (mb_str_split($str) as $char) {
                    if (preg_match('/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{1F1E6}-\x{1F1FF}]/u', $char)) {
                        $visualLength += 2;
                    } else
                        $visualLength++;

                    if ($visualLength > $maxLength)
                        break;

                    $trimmedString .= $char;
                }
                if ($visualLength > $maxLength) {
                    return trim($trimmedString) . '..';
                }
            }
        }
        return trim($str);
    }

    public static function rx_copy_button_keyboard($value, $label = null, $extraRows = null, ?string $colorIdentity = null)
    {
        $value = (string) $value;
        $rows = array();
        if (\Faoxima\Core\Support\Formatting::rx_copy_text_fits($value)) {
            $rows[] = array(array(
                'text' => $label !== null ? $label : '📋 کپی',
                'copy_text' => array('text' => $value),
            ));
            if ($colorIdentity !== null && $colorIdentity !== '') {
                $rows[0][0]['_rxcolor'] = $colorIdentity;
                $rows[0][0]['_rxkeep'] = true;
            }
        }
        if (is_array($extraRows)) {
            foreach ($extraRows as $extraRow) {
                if (is_array($extraRow)) {
                    $rows[] = $extraRow;
                }
            }
        }
        if (empty($rows)) {
            return null;
        }
        return json_encode(array('inline_keyboard' => $rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function check_active_btn($keyboard, $text_var)
    {
        $decoded = json_decode((string)$keyboard, true);
        $trace_keyboard = is_array($decoded) && isset($decoded['keyboard']) && is_array($decoded['keyboard'])
            ? $decoded['keyboard']
            : [];
        $status = false;
        foreach ($trace_keyboard as $key => $callback_set) {
            if (!is_array($callback_set)) continue;
            foreach ($callback_set as $keyboard_key => $keyboard) {
                if (is_array($keyboard) && ($keyboard['text'] ?? null) == $text_var) {
                    $status = true;
                    break;
                }
            }
        }
        return $status;
    }

    public static function rx_sendTopicReport(array $params, array $context = [])
    {
        $chatId = trim((string) ($params['chat_id'] ?? ''));
        if ($chatId === '' || $chatId === '0') {
            return false;
        }
        $params['chat_id'] = $chatId;
        $maxAttempts = 3;
        $maxRetryAfter = 5;
        $timeBudget = 15.0;
        $startedAt = microtime(true);
        $plainFallbackUsed = false;
        $response = null;
        $errorCode = 0;
        $description = '';
        $retryAfter = 0;
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $response = telegram('sendmessage', $params);
            } catch (Throwable $e) {
                $response = ['ok' => false, 'error_code' => 0, 'description' => $e->getMessage()];
            }
            if (is_array($response) && !empty($response['ok'])) {
                return $response;
            }
            $errorCode = is_array($response) ? (int) ($response['error_code'] ?? 0) : 0;
            $description = is_array($response) ? (string) ($response['description'] ?? '') : 'empty response';
            $retryAfter = is_array($response) ? (int) ($response['parameters']['retry_after'] ?? 0) : 0;
            if (!$plainFallbackUsed && !empty($params['parse_mode']) && stripos($description, "can't parse entities") !== false) {
                $plainFallbackUsed = true;
                $params['text'] = html_entity_decode(strip_tags((string) ($params['text'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                unset($params['parse_mode']);
                continue;
            }
            $class = method_exists(\Faoxima\Admin\Broadcast\BroadcastQueue::class, 'rx_broadcast_classify') ? \Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_classify($response) : ['temporary' => false, 'manual' => true];
            if (empty($class['temporary']) || !empty($class['manual']) || $attempt >= $maxAttempts) {
                break;
            }
            $wait = $errorCode === 429 ? max(1, $retryAfter) : $attempt;
            if ($wait > $maxRetryAfter || (microtime(true) - $startedAt + $wait) > $timeBudget) {
                break;
            }
            sleep($wait);
        }
        $threadId = (string) ($params['message_thread_id'] ?? '');
        $parameters = is_array($response) && isset($response['parameters']) ? $response['parameters'] : null;
        $logContext = [
            'status' => $errorCode,
            'body' => $chatId . '|' . $threadId . '|' . implode('|', array_map('strval', array_filter($context, 'is_scalar'))),
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'error_code' => $errorCode,
            'description' => $description,
            'retry_after' => $retryAfter,
            'parameters' => $parameters !== null ? json_encode($parameters) : '',
            'attempts' => $attempt,
        ];
        foreach ($context as $key => $value) {
            if (is_scalar($value) && !isset($logContext[$key])) {
                $logContext[$key] = $value;
            }
        }
        if (function_exists('rx_log_event')) {
            rx_log_event('TOPIC_REPORT_FAILED', 'Purchase report could not be delivered to the report topic', $logContext);
        } else {
            error_log('[TOPIC_REPORT_FAILED] ' . json_encode($logContext));
        }
        return false;
    }
}
