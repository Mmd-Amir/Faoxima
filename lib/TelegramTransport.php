<?php

final class FxTelegramTransport
{
    private static array $pools = [];

    public static function execute(array $handles, string $scope): array
    {
        $now = microtime(true);
        foreach (self::$pools as $key => $entry) {
            if (!$entry->busy && ($now - $entry->born >= 10 || $now - $entry->used >= 5)) {
                unset(self::$pools[$key]);
            }
        }
        $key = hash('sha256', $scope);
        $entry = self::$pools[$key] ?? null;
        if ($entry === null || $entry->busy) {
            $entry = (object) ['multi' => curl_multi_init(), 'born' => $now, 'used' => $now, 'busy' => false];
            curl_multi_setopt($entry->multi, CURLMOPT_MAXCONNECTS, 8);
            if (count(self::$pools) >= 4) {
                foreach (self::$pools as $oldKey => $old) {
                    if (!$old->busy) {
                        unset(self::$pools[$oldKey]);
                        break;
                    }
                }
            }
            if (!isset(self::$pools[$key]) && count(self::$pools) < 4) {
                self::$pools[$key] = $entry;
            }
        }
        $entry->busy = true;
        $added = [];
        $results = [];
        $codes = [];
        $status = CURLM_OK;
        try {
            foreach ($handles as $index => $handle) {
                $status = curl_multi_add_handle($entry->multi, $handle);
                if ($status !== CURLM_OK) {
                    break;
                }
                $added[$index] = $handle;
            }
            if ($status === CURLM_OK) {
                do {
                    do {
                        $status = curl_multi_exec($entry->multi, $running);
                    } while ($status === CURLM_CALL_MULTI_PERFORM);
                    while ($message = curl_multi_info_read($entry->multi)) {
                        $codes[spl_object_id($message['handle'])] = $message['result'];
                    }
                    if ($status !== CURLM_OK || $running === 0) {
                        break;
                    }
                    if (curl_multi_select($entry->multi, 0.1) === -1) {
                        usleep(1000);
                    }
                } while (true);
            }
            foreach ($handles as $index => $handle) {
                $code = $codes[spl_object_id($handle)] ?? curl_errno($handle);
                if ($status !== CURLM_OK && $code === CURLE_OK) {
                    $code = CURLE_FAILED_INIT;
                }
                $results[$index] = [
                    'raw' => $code === CURLE_OK ? curl_multi_getcontent($handle) : false,
                    'errno' => $code,
                    'error' => $status !== CURLM_OK ? 'Telegram transport initialization failed.' : curl_error($handle),
                ];
            }
            return $results;
        } finally {
            foreach ($added as $handle) {
                curl_multi_remove_handle($entry->multi, $handle);
            }
            $entry->busy = false;
            $entry->used = microtime(true);
            if ($status !== CURLM_OK && (self::$pools[$key] ?? null) === $entry) {
                unset(self::$pools[$key]);
            }
        }
    }
}

final class FxTelegramCallbackScope
{
    private static array $stack = [];

    public static function run(string $id, callable $action, bool &$answered)
    {
        $context = (object) ['id' => $id, 'tokenKey' => hash('sha256', (string) ($GLOBALS['APIKEY'] ?? '')), 'taken' => false, 'answered' => false];
        self::$stack[] = $context;
        $answered = false;
        try {
            return $action();
        } finally {
            array_pop(self::$stack);
            $answered = $context->answered;
        }
    }

    public static function take(string $method, string $token): ?object
    {
        if (strtolower($method) !== 'editmessagetext' || !self::$stack) {
            return null;
        }
        $context = self::$stack[count(self::$stack) - 1];
        if ($context->taken || $context->id === '' || $context->tokenKey !== hash('sha256', $token)) {
            return null;
        }
        $context->taken = true;
        return $context;
    }

    public static function handle(object $context, string $token, int $timeout)
    {
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/answerCallbackQuery');
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 60,
            CURLOPT_TCP_KEEPINTVL => 30,
            CURLOPT_HTTPHEADER => ['Expect:', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query(['callback_query_id' => $context->id, 'cache_time' => 1], '', '&', PHP_QUERY_RFC3986),
        ]);
        if (function_exists('faoxima_apply_curl_proxy')) {
            faoxima_apply_curl_proxy($ch, 'telegram');
        }
        return $ch;
    }
}
