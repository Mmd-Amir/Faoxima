<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/WebhookAuth.php';
require_once __DIR__ . '/lib/TelegramTransport.php';
require_once __DIR__ . '/lib/TelegramEmojiFallback.php';

if (!function_exists('rx_releaseWebhookConnection')) {
    function rx_releaseWebhookConnection()
    {
        static $released = false;
        if ($released) {
            return;
        }
        $released = true;

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
            return;
        }
        if (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
            return;
        }
        if (headers_sent()) {
            return;
        }

        @ignore_user_abort(true);
        $rxBuffered = '';
        while (ob_get_level() > 0) {
            $rxBuffered = ob_get_clean() . $rxBuffered;
        }
        @header('Connection: close');
        @header('Content-Length: ' . strlen($rxBuffered));
        echo $rxBuffered;
        @flush();
    }
}

if (!function_exists('rx_callback_lock_acquire')) {
    function rx_callback_lock_acquire($ownerId, $scope = 'cb', $timeout = 2.5)
    {
        global $pdo;

        if (!($pdo instanceof PDO)) {
            return true;
        }

        $ownerId = (string) $ownerId;
        if ($ownerId === '' || !ctype_digit($ownerId)) {
            return true;
        }

        $prefix = defined('_FX_SHARD') ? substr(_FX_SHARD, 0, 8) : 'fx';
        $name = substr($prefix . ':' . preg_replace('/[^a-z0-9_]/i', '', $scope) . ':' . $ownerId, 0, 64);

        try {
            $stmt = $pdo->prepare('SELECT IS_USED_LOCK(?)');
            $stmt->execute([$name]);
            $heldBy = $stmt->fetchColumn();
            if ($heldBy !== null && $heldBy !== false) {
                $stmt = $pdo->prepare('SELECT CONNECTION_ID()');
                $stmt->execute();
                if ((string) $stmt->fetchColumn() === (string) $heldBy) {
                    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
                }
            }

            $stmt = $pdo->prepare('SELECT GET_LOCK(?, ?)');
            $stmt->execute([$name, max(0.1, (float) $timeout)]);
            $got = $stmt->fetchColumn();
        } catch (Throwable $e) {
            @error_log('[rx_callback_lock] acquire failed: ' . $e->getMessage());
            return true;
        }

        return ((string) $got === '1') ? $name : false;
    }
}

if (!function_exists('rx_callback_lock_release')) {
    function rx_callback_lock_release($name)
    {
        global $pdo;

        if (!is_string($name) || $name === '' || !($pdo instanceof PDO)) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$name]);
            $result = $stmt->fetchColumn();
            return $result === null ? null : (string) $result;
        } catch (Throwable $e) {
            @error_log('[rx_callback_lock] release failed: ' . $e->getMessage());
            return 'error:' . $e->getMessage();
        }
    }
}

if (!function_exists('rx_callback_busy_reply')) {
    function rx_callback_busy_reply($callbackQueryId, $text = '⏳ لطفاً کمی صبر کنید…')
    {
        if (empty($callbackQueryId)) {
            return;
        }
        @telegram('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => false,
            'cache_time' => 0,
        ]);
    }
}

if (!function_exists('rx_hasRichMessageContent')) {
    function rx_hasRichMessageContent($richMessage): bool
    {
        if (is_string($richMessage)) {
            $richMessage = json_decode($richMessage, true);
        }
        if (!is_array($richMessage)) {
            return false;
        }
        foreach (['html', 'markdown'] as $key) {
            if (!isset($richMessage[$key]) || !is_string($richMessage[$key])) {
                continue;
            }
            $body = preg_replace('~<tg-button-row[\s\S]*?</tg-button-row>~i', '', $richMessage[$key]);
            if (trim(strip_tags($body)) !== '' || preg_match('~<(img|video|audio|tg-document)~i', $body)) {
                return true;
            }
        }
        return !empty($richMessage['blocks']) && is_array($richMessage['blocks']);
    }
}

function telegram($method, $datas = [], $token = null)
{
    global $APIKEY, $telegramCurlTimeout;

    $token = $token === null ? $APIKEY : $token;
    $url = "https://api.telegram.org/bot" . $token . "/" . $method;

    if (!is_array($datas)) {
        $datas = [];
    }
    $rxRequestTimeout = isset($datas['_rx_curl_timeout']) ? max(1, min(60, (int) $datas['_rx_curl_timeout'])) : null;
    unset($datas['_rx_curl_timeout']);
    $rxNoNetworkRetry = !empty($datas['_rx_no_network_retry']);
    unset($datas['_rx_no_network_retry']);

    if (strtolower((string) $method) === 'setwebhook') {
        $datas['secret_token'] = FaoximaWebhookAuth::secret((string) $token, $token === $APIKEY);
    }

    if (isset($datas['message_thread_id']) && intval($datas['message_thread_id']) <= 0) {
        unset($datas['message_thread_id']);
    }









    $rxEmojiFallbackAttempt = !empty($datas['_rx_emoji_fallback_attempt']);
    unset($datas['_rx_emoji_fallback_attempt']);
    $rxAlreadyTransformed = $rxEmojiFallbackAttempt || !empty($datas['_rx_already_transformed']);
    unset($datas['_rx_already_transformed']);

    $rxAutoTransformMethods = ['sendmessage', 'sendMessage', 'editmessagetext', 'editMessageText', 'sendphoto', 'sendPhoto', 'sendvideo', 'sendVideo', 'sendDocument', 'senddocument', 'editmessagecaption', 'editMessageCaption', 'editMessageReplyMarkup', 'editmessagereplymarkup'];
    if (!$rxAlreadyTransformed && in_array(strtolower($method), array_map('strtolower', $rxAutoTransformMethods), true)) {

        $rxTextField = null;
        if (isset($datas['text']))         { $rxTextField = 'text'; }
        elseif (isset($datas['caption']))  { $rxTextField = 'caption'; }
        if ($rxTextField !== null && is_string($datas[$rxTextField]) && function_exists('applyPremiumEmojiTransform')) {
            $rxParseMode = $datas['parse_mode'] ?? null;
            $datas[$rxTextField] = applyPremiumEmojiTransform($datas[$rxTextField], $rxParseMode);
        }

        if (isset($datas['reply_markup']) && $datas['reply_markup'] !== '' && $datas['reply_markup'] !== null) {
            if (function_exists('processKeyboardStyles')) {
                $datas['reply_markup'] = processKeyboardStyles($datas['reply_markup'], isset($datas['chat_id']) && function_exists('rx_isAdminChat') && rx_isAdminChat($datas['chat_id']), $datas['chat_id'] ?? null);
            }
            if (function_exists('applyPremiumEmojiToKeyboard')) {
                $datas['reply_markup'] = applyPremiumEmojiToKeyboard($datas['reply_markup']);
            }
        }
    }

    // Final safety net: strip any button with null/empty/non-string text from the
    // outgoing keyboard so a single corrupt DB row (e.g. a marzban_panel row with
    // name_panel = NULL feeding a dynamically-built list keyboard) can never make
    // Telegram reject the whole message with "can't parse keyboard button: Field
    // \"text\" must be of type String". Runs for ALL methods (even pre-transformed
    // sends) and is premium-safe (keeps the ' ' icon-only buttons).
    if (isset($datas['reply_markup']) && is_string($datas['reply_markup']) && $datas['reply_markup'] !== ''
        && function_exists('rx_sanitizeKeyboardButtons')) {
        $datas['reply_markup'] = rx_sanitizeKeyboardButtons($datas['reply_markup']);
    }

    $rxSendMethod = strtolower((string) $method);
    if (($rxSendMethod === 'sendmessage' || $rxSendMethod === 'editmessagetext')
        && (!isset($datas['text']) || !is_string($datas['text']) || trim($datas['text']) === '')
        && !($rxSendMethod === 'editmessagetext' && rx_hasRichMessageContent($datas['rich_message'] ?? null))) {
        $rxEmptyCaller = 'unknown';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $rxFrame) {
            if (isset($rxFrame['file']) && basename($rxFrame['file']) !== 'botapi.php') {
                $rxEmptyCaller = basename($rxFrame['file']) . ':' . ($rxFrame['line'] ?? '?');
                break;
            }
        }
        $rxEmptyMsg = '[empty-message] skipped ' . $method . ' to chat ' . ($datas['chat_id'] ?? '?') . ' — empty text from ' . $rxEmptyCaller;
        if (method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_dedup_error_log')) {
            \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log('rx_empty_text_' . $rxSendMethod . '_' . $rxEmptyCaller, $rxEmptyMsg, 21600);
        } else {
            @error_log($rxEmptyMsg);
        }
        return ['ok' => false, 'error_code' => 400, 'description' => 'message text is empty (skipped locally)'];
    }

    if (in_array($rxSendMethod, ['editmessagetext', 'editmessagereplymarkup', 'editmessagecaption', 'deletemessage'], true)
        && isset($datas['chat_id'], $datas['message_id']) && !isset($datas['inline_message_id'])
        && method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_resolveTarget')) {
        $rxResolvedTarget = \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_resolveTarget($datas['chat_id'], (int) $datas['message_id']);
        if ($rxResolvedTarget['deleted'] || ($rxSendMethod === 'deletemessage' && $rxResolvedTarget['redirected'])) {
            return $rxSendMethod === 'deletemessage'
                ? ['ok' => true, 'result' => true, '_rx_already_deleted' => true]
                : ['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message to edit not found', '_rx_stale_target' => true];
        }
        if ($rxResolvedTarget['redirected']) {
            $datas['message_id'] = $rxResolvedTarget['id'];
        }
    }

    if (!$rxEmojiFallbackAttempt && method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_dispatch')) {
        $rxRichResult =\Faoxima\Telegram\Rich\Dispatcher::rx_richUi_dispatch($method, $datas, $token);
        if ($rxRichResult !== null) {
            return $rxRichResult;
        }
    }

    $preparedPayload = prepareTelegramRequestPayload($datas);

    // Outbound to api.telegram.org has intermittent 2-5s connect timeouts
    // on some hosting providers (MatHost.eu in particular). When a single
    // sendmessage() fails, the calling handler usually has ALREADY advanced
    // `step()` past this state — leaving the admin in a "stuck" state with
    // no UI to recover. A short network-level retry inside telegram() makes
    // the rest of the codebase resilient to those blips without touching
    // ~50+ call-sites.
    //
    // Only retry on transient cURL errors (DNS, connect, timeout, recv).
    // Application errors (HTTP 4xx/5xx, "message to edit not found", etc.)
    // are NOT retried — they're real responses, not network failures.
    $retryableCurlErrnos = [6, 7, 28, 35, 56];
    $maxAttempts = $rxNoNetworkRetry ? 1 : 2;
    $rawResponse = false;
    $curlErrorNumber = 0;
    $curlError = '';
    $httpCode = 0;
    $duration = 0.0;
    $attemptedTimes = 0;

    while ($attemptedTimes < $maxAttempts) {
        $attemptedTimes++;

        $ch = curl_init($url);
        if ($ch === false) {
            error_log('Unable to initialise cURL for Telegram request.');
            return [
                'ok' => false,
                'description' => 'Unable to initialise cURL for Telegram request.'
            ];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        $timeout = $rxRequestTimeout ?? (isset($telegramCurlTimeout) && is_numeric($telegramCurlTimeout) ? (int)$telegramCurlTimeout : 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 30);
        if (function_exists('faoxima_apply_curl_proxy')) {
            faoxima_apply_curl_proxy($ch, 'telegram');
        }
        if (!empty($preparedPayload['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $preparedPayload['headers']);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $preparedPayload['body']);

        $rxCallbackContext = FxTelegramCallbackScope::take((string) $method, (string) $token);
        $rxCallbackHandle = $rxCallbackContext !== null
            ? FxTelegramCallbackScope::handle($rxCallbackContext, (string) $token, $timeout)
            : false;
        $requestStartedAt = microtime(true);
        $rxTransportHandles = [0 => $ch];
        if ($rxCallbackHandle !== false) {
            $rxTransportHandles[1] = $rxCallbackHandle;
        }
        $rxTransportResults = FxTelegramTransport::execute($rxTransportHandles, (string) $token);
        $rawResponse = $rxTransportResults[0]['raw'];
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = microtime(true) - $requestStartedAt;
        if ($rxCallbackHandle !== false) {
            $rxCallbackResult = json_decode((string) ($rxTransportResults[1]['raw'] ?? ''), true);
            $rxCallbackContext->answered = is_array($rxCallbackResult) && !empty($rxCallbackResult['ok']);
            if ($rxCallbackContext->answered && method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_after_send')) {
                \Faoxima\Telegram\Presentation\Sections::rx_ui_after_send('answerCallbackQuery', ['callback_query_id' => $rxCallbackContext->id, 'cache_time' => 1], $rxCallbackResult);
            }
            curl_close($rxCallbackHandle);
        }

        if ($rawResponse !== false) {
            curl_close($ch);
            break; // network success — proceed to response parsing below
        }

        $curlErrorNumber = $rxTransportResults[0]['errno'];
        $curlError = $rxTransportResults[0]['error'];
        curl_close($ch);

        // Stop if the error isn't network-transient, or if we just used the
        // last attempt — fall through to the error-return block.
        if (!in_array($curlErrorNumber, $retryableCurlErrnos, true) || $attemptedTimes >= $maxAttempts
            || (in_array($rxSendMethod, ['sendmessage', 'sendrichmessage', 'sendphoto', 'sendvideo', 'senddocument', 'sendanimation'], true)
                && !in_array($curlErrorNumber, [6, 7], true))) {
            break;
        }

        usleep(250000); // 250 ms backoff before retry
    }

    if ($rawResponse === false) {
        $logError = $curlError !== '' ? $curlError : 'Unknown cURL error';
        $logError = method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_redactLogText') ? \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_redactLogText($logError) : 'Telegram transport failed';
        $redactedUrl = preg_replace('#(/bot)[^/]+(/)#', '$1***$2', $url);
        error_log(sprintf('Telegram request failed (errno: %d, url: %s, attempts: %d): %s',
            $curlErrorNumber, $redactedUrl, $attemptedTimes, $logError));
        return [
            'ok' => false,
            'description' => ($curlError !== '' ? $curlError : 'Telegram request failed.') . ' اتصال به تلگرام در مهلت مقرر برقرار نشد؛ فایروال یا پراکسی خروجی را بررسی کنید.'
        ];
    }

    if ($duration >= 5.0) {
        error_log(sprintf('Slow Telegram response detected (method: %s, http_code: %d, duration: %.3fs)', $method, $httpCode, $duration));
    }

    $rxParsedResponse = rx_parse_telegram_response($rawResponse, $httpCode);
    if (!$rxEmojiFallbackAttempt) {
        $rxRetryResult = FxTelegramEmojiFallback::recover((string) $method, $datas, $rxParsedResponse, (string) $token, $rxRequestTimeout);
        if ($rxRetryResult !== null) {
            return $rxRetryResult;
        }
    }
    if (method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_after_send')) {
        \Faoxima\Telegram\Presentation\Sections::rx_ui_after_send($method, $datas, $rxParsedResponse);
    }
    return $rxParsedResponse;
}

function rx_parse_telegram_response($rawResponse, $httpCode)
{
    $decodedResponse = json_decode($rawResponse, true);
    if (!is_array($decodedResponse)) {
        error_log(sprintf('Invalid response from Telegram API (HTTP %d)', $httpCode));

        return [
            'ok' => false,
            'error_code' => $httpCode,
            '_rx_invalid_response' => true,
            'description' => 'Invalid response received from Telegram.'
        ];
    }

    if (isset($decodedResponse['ok']) && !$decodedResponse['ok']) {


        $rxBenignDescriptions = [
            'message to delete not found',
            'message to edit not found',
            'message is not modified',
            "message can't be deleted",
            'message can\'t be deleted for everyone',
            'query is too old',
            'MESSAGE_ID_INVALID',
            'replied message not found',
            'bot was blocked by the user',
            'user is deactivated',
            'chat not found',
            'member list is inaccessible',
        ];
        $rxDesc = (string) ($decodedResponse['description'] ?? '');
        $rxIsBenign = false;
        foreach ($rxBenignDescriptions as $rxNeedle) {
            if (stripos($rxDesc, $rxNeedle) !== false) {
                $rxIsBenign = true;
                break;
            }
        }
        if (!$rxIsBenign) {
            $rxTgKey = 'tg|' . (string)($decodedResponse['error_code'] ?? 0) . '|' . hash('sha256', $rxDesc);
            $rxSafeError = [
                'ok' => false,
                'error_code' => $decodedResponse['error_code'] ?? 0,
                'description' => method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_redactLogText') ? \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_redactLogText($rxDesc) : 'Telegram rejected the request',
            ];
            if (method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_dedup_error_log')) {
                \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log($rxTgKey, json_encode($rxSafeError), 21600);
            } else {
                error_log(json_encode($rxSafeError));
            }
        }
    }

    return $decodedResponse;
}

function rx_telegram_dispatch_concurrent(array $requests)
{
    global $APIKEY;
    if (!$requests) {
        return [];
    }

    $curlHandles = [];
    $rxTransportScopes = [];

    foreach ($requests as $index => $request) {
        $method = $request['method'];
        $datas = is_array($request['datas']) ? $request['datas'] : [];
        $token = $request['token'] ?? $APIKEY;
        $rxTransportScopes[] = (string) $token;
        $url = "https://api.telegram.org/bot" . $token . "/" . $method;

        $preparedPayload = prepareTelegramRequestPayload($datas);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 30);
        if (function_exists('faoxima_apply_curl_proxy')) {
            faoxima_apply_curl_proxy($ch, 'telegram');
        }
        if (!empty($preparedPayload['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $preparedPayload['headers']);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $preparedPayload['body']);

        $curlHandles[$index] = $ch;
    }

    $rxTransportScopes = array_values(array_unique($rxTransportScopes));
    sort($rxTransportScopes);
    $rxTransportScope = count($rxTransportScopes) === 1
        ? $rxTransportScopes[0]
        : 'batch:' . json_encode($rxTransportScopes);
    $rxTransportResults = FxTelegramTransport::execute($curlHandles, $rxTransportScope);

    $results = [];
    foreach ($curlHandles as $index => $ch) {
        $rawResponse = $rxTransportResults[$index]['raw'];
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = $rxTransportResults[$index]['errno'];
        if ($curlErrno !== 0 || $rawResponse === null || $rawResponse === '') {
            $results[$index] = [
                'ok' => false,
                'description' => $rxTransportResults[$index]['error'] !== '' ? $rxTransportResults[$index]['error'] : 'Telegram request failed.'
            ];
        } else {
            $results[$index] = rx_parse_telegram_response($rawResponse, $httpCode);
        }
        curl_close($ch);
        $request = $requests[$index];
        $method = (string) $request['method'];
        $datas = is_array($request['datas'] ?? null) ? $request['datas'] : [];
        $token = (string) ($request['token'] ?? $APIKEY);
        $retryResult = FxTelegramEmojiFallback::recover($method, $datas, $results[$index], $token);
        if ($retryResult !== null) {
            $results[$index] = $retryResult;
        }
    }

    return $results;
}

if (!function_exists('rx_answer_and_edit')) {
    function rx_answer_and_edit($callbackQueryId, $toastText, $fromId, $messageId, $newText, $newKeyboard, $showAlert = false, $cacheTime = 0)
    {
        $cacheTime = max(0, (int) $cacheTime);

        if (function_exists('rx_telegram_dispatch_concurrent')) {
            return rx_telegram_dispatch_concurrent([
                [
                    'method' => 'editmessagetext',
                    'datas' => [
                        'chat_id' => $fromId,
                        'message_id' => $messageId,
                        'text' => $newText,
                        'reply_markup' => $newKeyboard,
                        'parse_mode' => 'HTML',
                    ],
                ],
                [
                    'method' => 'answerCallbackQuery',
                    'datas' => [
                        'callback_query_id' => $callbackQueryId,
                        'text' => $toastText,
                        'show_alert' => $showAlert,
                        'cache_time' => $cacheTime,
                    ],
                ],
            ]);
        }

        telegram('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $toastText,
            'show_alert' => $showAlert,
            'cache_time' => $cacheTime,
        ]);
        if (function_exists('Editmessagetext')) {
            Editmessagetext($fromId, $messageId, $newText, $newKeyboard, 'HTML');
        }
        return null;
    }
}

function prepareTelegramRequestPayload(array $datas)
{
    $normalised = [];
    foreach ($datas as $key => $value) {
        if ($value === null) {
            continue;
        }

        if ($key === 'reply_markup' && is_string($value) && strpos($value, '"_rxsec"') !== false) {
            $rxMarked = json_decode($value, true);
            if (is_array($rxMarked)) {
                unset($rxMarked['_rxsec']);
                $value = json_encode($rxMarked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $normalised[$key] = normaliseTelegramValue($value);
    }

    $containsFile = false;
    foreach ($normalised as $value) {
        if ($value instanceof CURLFile) {
            $containsFile = true;
            break;
        }
    }

    if ($containsFile) {
        return [
            'body' => $normalised,
            'headers' => ['Expect:']
        ];
    }

    $stringBody = http_build_query($normalised, '', '&', PHP_QUERY_RFC3986);

    return [
        'body' => $stringBody,
        'headers' => ['Expect:', 'Content-Type: application/x-www-form-urlencoded']
    ];
}

function normaliseTelegramValue($value)
{
    if ($value instanceof CURLFile) {
        return $value;
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value) || is_object($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = json_encode($value);
        }

        return $encoded === false ? '' : $encoded;
    }

    return $value;
}


function rxPremiumEmojiSourcePattern() {
    // Emoji bases and modifier bases derived from Unicode Emoji 18.0 test data.
    // https://www.unicode.org/Public/emoji/latest/emoji-test.txt
    // One sequence: keycap, country/tag flag, base + skin tone, or a ZWJ sequence.
    static $pattern = '~\A(?:[0-9#*]\x{FE0F}?\x{20E3}|(?:\x{1F1E6}[\x{1F1E8}-\x{1F1EC}\x{1F1EE}\x{1F1F1}-\x{1F1F2}\x{1F1F4}\x{1F1F6}-\x{1F1FA}\x{1F1FC}-\x{1F1FD}\x{1F1FF}]|\x{1F1E7}[\x{1F1E6}-\x{1F1E7}\x{1F1E9}-\x{1F1EF}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}\x{1F1FB}-\x{1F1FC}\x{1F1FE}-\x{1F1FF}]|\x{1F1E8}[\x{1F1E6}\x{1F1E8}-\x{1F1E9}\x{1F1EB}-\x{1F1EE}\x{1F1F0}-\x{1F1F7}\x{1F1FA}-\x{1F1FF}]|\x{1F1E9}[\x{1F1EA}\x{1F1EC}\x{1F1EF}-\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1FF}]|\x{1F1EA}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}-\x{1F1ED}\x{1F1F7}-\x{1F1FA}]|\x{1F1EB}[\x{1F1EE}-\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F7}]|\x{1F1EC}[\x{1F1E6}-\x{1F1E7}\x{1F1E9}-\x{1F1EE}\x{1F1F1}-\x{1F1F3}\x{1F1F5}-\x{1F1FA}\x{1F1FC}\x{1F1FE}]|\x{1F1ED}[\x{1F1F0}\x{1F1F2}-\x{1F1F3}\x{1F1F7}\x{1F1F9}-\x{1F1FA}]|\x{1F1EE}[\x{1F1E8}-\x{1F1EA}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}]|\x{1F1EF}[\x{1F1EA}\x{1F1F2}\x{1F1F4}-\x{1F1F5}]|\x{1F1F0}[\x{1F1EA}\x{1F1EC}-\x{1F1EE}\x{1F1F2}-\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1FC}\x{1F1FE}-\x{1F1FF}]|\x{1F1F1}[\x{1F1E6}-\x{1F1E8}\x{1F1EE}\x{1F1F0}\x{1F1F7}-\x{1F1FB}\x{1F1FE}]|\x{1F1F2}[\x{1F1E6}\x{1F1E8}-\x{1F1ED}\x{1F1F0}-\x{1F1FF}]|\x{1F1F3}[\x{1F1E6}\x{1F1E8}\x{1F1EA}-\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F4}-\x{1F1F5}\x{1F1F7}\x{1F1FA}\x{1F1FF}]|\x{1F1F4}[\x{1F1F2}]|\x{1F1F5}[\x{1F1E6}\x{1F1EA}-\x{1F1ED}\x{1F1F0}-\x{1F1F3}\x{1F1F7}-\x{1F1F9}\x{1F1FC}\x{1F1FE}]|\x{1F1F6}[\x{1F1E6}]|\x{1F1F7}[\x{1F1EA}\x{1F1F4}\x{1F1F8}\x{1F1FA}\x{1F1FC}]|\x{1F1F8}[\x{1F1E6}-\x{1F1EA}\x{1F1EC}-\x{1F1F4}\x{1F1F7}-\x{1F1F9}\x{1F1FB}\x{1F1FD}-\x{1F1FF}]|\x{1F1F9}[\x{1F1E6}\x{1F1E8}-\x{1F1E9}\x{1F1EB}-\x{1F1ED}\x{1F1EF}-\x{1F1F4}\x{1F1F7}\x{1F1F9}\x{1F1FB}-\x{1F1FC}\x{1F1FF}]|\x{1F1FA}[\x{1F1E6}\x{1F1EC}\x{1F1F2}-\x{1F1F3}\x{1F1F8}\x{1F1FE}-\x{1F1FF}]|\x{1F1FB}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F3}\x{1F1FA}]|\x{1F1FC}[\x{1F1EB}\x{1F1F8}]|\x{1F1FD}[\x{1F1F0}]|\x{1F1FE}[\x{1F1EA}\x{1F1F9}]|\x{1F1FF}[\x{1F1E6}\x{1F1F2}\x{1F1FC}])|\x{1F3F4}\x{E0067}\x{E0062}\x{E0065}\x{E006E}\x{E0067}\x{E007F}|\x{1F3F4}\x{E0067}\x{E0062}\x{E0073}\x{E0063}\x{E0074}\x{E007F}|\x{1F3F4}\x{E0067}\x{E0062}\x{E0077}\x{E006C}\x{E0073}\x{E007F}|(?:[\x{261D}\x{26F9}\x{270A}-\x{270D}\x{1F385}\x{1F3C2}-\x{1F3C4}\x{1F3C7}\x{1F3CA}-\x{1F3CC}\x{1F442}-\x{1F443}\x{1F446}-\x{1F450}\x{1F466}-\x{1F469}\x{1F46B}-\x{1F478}\x{1F47C}\x{1F481}-\x{1F483}\x{1F485}-\x{1F487}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F574}-\x{1F575}\x{1F57A}\x{1F590}\x{1F595}-\x{1F596}\x{1F645}-\x{1F647}\x{1F64B}-\x{1F64F}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F926}\x{1F930}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F977}\x{1F9B5}-\x{1F9B6}\x{1F9B8}-\x{1F9B9}\x{1F9BB}\x{1F9CD}-\x{1F9CF}\x{1F9D1}-\x{1F9DD}\x{1FAC3}-\x{1FAC5}\x{1FAF0}-\x{1FAFA}]\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?|[\x{A9}\x{AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}-\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26A7}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}-\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26F8}\x{26FA}\x{26FD}\x{2702}\x{2705}\x{2708}-\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}-\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F0CF}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}-\x{1F202}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F250}-\x{1F251}\x{1F300}-\x{1F321}\x{1F324}-\x{1F384}\x{1F386}-\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F3C1}\x{1F3C5}-\x{1F3C6}\x{1F3C8}-\x{1F3C9}\x{1F3CD}-\x{1F3F0}\x{1F3F3}-\x{1F3F5}\x{1F3F7}-\x{1F3FA}\x{1F400}-\x{1F441}\x{1F444}-\x{1F445}\x{1F451}-\x{1F465}\x{1F46A}\x{1F479}-\x{1F47B}\x{1F47D}-\x{1F480}\x{1F484}\x{1F488}-\x{1F48E}\x{1F490}\x{1F492}-\x{1F4A9}\x{1F4AB}-\x{1F4FD}\x{1F4FF}-\x{1F53D}\x{1F549}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A4}-\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}-\x{1F644}\x{1F648}-\x{1F64A}\x{1F680}-\x{1F6A2}\x{1F6A4}-\x{1F6B3}\x{1F6B7}-\x{1F6BF}\x{1F6C1}-\x{1F6C5}\x{1F6CB}\x{1F6CD}-\x{1F6D2}\x{1F6D5}-\x{1F6D9}\x{1F6DC}-\x{1F6E5}\x{1F6E9}\x{1F6EB}-\x{1F6EC}\x{1F6F0}\x{1F6F3}-\x{1F6FC}\x{1F7E0}-\x{1F7EB}\x{1F7F0}\x{1F90D}-\x{1F90E}\x{1F910}-\x{1F917}\x{1F920}-\x{1F925}\x{1F927}-\x{1F92F}\x{1F93A}\x{1F93F}-\x{1F945}\x{1F947}-\x{1F976}\x{1F978}-\x{1F9AF}\x{1F9B4}\x{1F9B7}\x{1F9BA}\x{1F9BC}-\x{1F9CC}\x{1F9D0}\x{1F9DE}-\x{1F9FF}\x{1FA70}-\x{1FA7C}\x{1FA80}-\x{1FAC2}\x{1FAC6}\x{1FAC8}\x{1FACC}-\x{1FADD}\x{1FADF}-\x{1FAEB}\x{1FAEF}]\x{FE0F}?)(?:\x{200D}(?:[\x{261D}\x{26F9}\x{270A}-\x{270D}\x{1F385}\x{1F3C2}-\x{1F3C4}\x{1F3C7}\x{1F3CA}-\x{1F3CC}\x{1F442}-\x{1F443}\x{1F446}-\x{1F450}\x{1F466}-\x{1F469}\x{1F46B}-\x{1F478}\x{1F47C}\x{1F481}-\x{1F483}\x{1F485}-\x{1F487}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F574}-\x{1F575}\x{1F57A}\x{1F590}\x{1F595}-\x{1F596}\x{1F645}-\x{1F647}\x{1F64B}-\x{1F64F}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F926}\x{1F930}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F977}\x{1F9B5}-\x{1F9B6}\x{1F9B8}-\x{1F9B9}\x{1F9BB}\x{1F9CD}-\x{1F9CF}\x{1F9D1}-\x{1F9DD}\x{1FAC3}-\x{1FAC5}\x{1FAF0}-\x{1FAFA}]\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?|[\x{A9}\x{AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}-\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26A7}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}-\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26F8}\x{26FA}\x{26FD}\x{2702}\x{2705}\x{2708}-\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}-\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F0CF}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}-\x{1F202}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F250}-\x{1F251}\x{1F300}-\x{1F321}\x{1F324}-\x{1F384}\x{1F386}-\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F3C1}\x{1F3C5}-\x{1F3C6}\x{1F3C8}-\x{1F3C9}\x{1F3CD}-\x{1F3F0}\x{1F3F3}-\x{1F3F5}\x{1F3F7}-\x{1F3FA}\x{1F400}-\x{1F441}\x{1F444}-\x{1F445}\x{1F451}-\x{1F465}\x{1F46A}\x{1F479}-\x{1F47B}\x{1F47D}-\x{1F480}\x{1F484}\x{1F488}-\x{1F48E}\x{1F490}\x{1F492}-\x{1F4A9}\x{1F4AB}-\x{1F4FD}\x{1F4FF}-\x{1F53D}\x{1F549}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A4}-\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}-\x{1F644}\x{1F648}-\x{1F64A}\x{1F680}-\x{1F6A2}\x{1F6A4}-\x{1F6B3}\x{1F6B7}-\x{1F6BF}\x{1F6C1}-\x{1F6C5}\x{1F6CB}\x{1F6CD}-\x{1F6D2}\x{1F6D5}-\x{1F6D9}\x{1F6DC}-\x{1F6E5}\x{1F6E9}\x{1F6EB}-\x{1F6EC}\x{1F6F0}\x{1F6F3}-\x{1F6FC}\x{1F7E0}-\x{1F7EB}\x{1F7F0}\x{1F90D}-\x{1F90E}\x{1F910}-\x{1F917}\x{1F920}-\x{1F925}\x{1F927}-\x{1F92F}\x{1F93A}\x{1F93F}-\x{1F945}\x{1F947}-\x{1F976}\x{1F978}-\x{1F9B4}\x{1F9B7}\x{1F9BA}\x{1F9BC}-\x{1F9CC}\x{1F9D0}\x{1F9DE}-\x{1F9FF}\x{1FA70}-\x{1FA7C}\x{1FA80}-\x{1FAC2}\x{1FAC6}\x{1FAC8}\x{1FACC}-\x{1FADD}\x{1FADF}-\x{1FAEB}\x{1FAEF}]\x{FE0F}?))*)\z~uD';
    return $pattern;
}

function isValidPremiumEmojiSource($emoji) {
    if (!is_string($emoji) || $emoji === '' || strlen($emoji) > 128) { return false; }
    return preg_match(rxPremiumEmojiSourcePattern(), $emoji) === 1;
}

function rxPremiumEmojiExtractSources($text) {
    if (!is_string($text) || $text === '') { return []; }
    $pattern = str_replace(['\A', '\z'], '', rxPremiumEmojiSourcePattern());
    if (preg_match_all($pattern, $text, $matches) === false) { return []; }
    return array_values(array_unique($matches[0]));
}

function getPremiumEmojiMap($forceReload = false) {
    static $cache = null;
    if ($forceReload) { $cache = null; }
    if ($cache !== null) { return $cache; }
    $cache = [];
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) { return $cache; }
    try {
        $stmt = $pdo->query("SELECT premium_emoji_status FROM setting LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $isOn = (is_array($row) && (string)($row['premium_emoji_status'] ?? '0') === '1');
        if (!$isOn) { return $cache; }
    } catch (\Throwable $e) { return $cache; }
    try {
        $stmt = $pdo->query("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE custom_emoji_id IS NOT NULL AND custom_emoji_id <> '' ORDER BY updated_at ASC, id ASC");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $emoji = (string)($row['emoji'] ?? '');
                $cid   = (string)($row['custom_emoji_id'] ?? '');
                if ($emoji === '' || $cid === '') { continue; }
                if (!isValidPremiumEmojiSource($emoji)) { continue; }
                $cache[$emoji] = $cid;
            }
        }
    } catch (\Throwable $e) {  }


    uksort($cache, function($a, $b) { return strlen($b) - strlen($a); });
    return $cache;
}
function applyPremiumEmojiTransform($text, $parseMode) {
    if (!is_string($text) || $text === '') {
        return $text;
    }
    $pm = strtolower((string)$parseMode);

    if ($pm !== 'html') {
        return $text;
    }
    $map = getPremiumEmojiMap();
    if (empty($map)) {
        return $text;
    }

    $replacements = [];
    foreach ($map as $emoji => $cid) {
        if ($emoji === '' || $cid === '') { continue; }
        if (strpos($text, $emoji) === false) { continue; }
        $cidEscaped = htmlspecialchars($cid, ENT_QUOTES);
        $tagged = '<tg-emoji emoji-id="' . $cidEscaped . '">' . $emoji . '</tg-emoji>';
        $replacements[$emoji] = $tagged;
    }
    // Preserve HTML attributes, literal code and existing custom emoji. Only visible
    // text is transformed, once, so overlapping sources cannot create nested tags.
    $tagPattern = '~(<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~s';
    $segments = preg_split($tagPattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($segments === false) {
        return $text;
    }
    $protected = [];
    $out = '';
    foreach ($segments as $index => $segment) {
        if ($index % 2 === 1) {
            if (preg_match('~^<\s*(/?)\s*(code|pre|tg-emoji)\b~i', $segment, $tag)) {
                $name = strtolower($tag[2]);
                if ($tag[1] === '/') {
                    $protected[$name] = max(0, ($protected[$name] ?? 0) - 1);
                } elseif (!preg_match('~/\s*>$~', $segment)) {
                    $protected[$name] = ($protected[$name] ?? 0) + 1;
                }
            }
            $out .= $segment;
        } else {
            $out .= array_sum($protected) > 0 ? $segment : strtr($segment, $replacements);
        }
    }
    return $out;
}


if (!defined('REPLY_STYLE_EMOJI_MAP')) {
    define('REPLY_STYLE_EMOJI_MAP', serialize([
        'success' => '🟢',
        'danger'  => '🔴',
        'primary' => '🔵',
    ]));
}


function stripReplyStyleEmoji(string $text): string {
    if ($text === '') return $text;
    foreach (unserialize(REPLY_STYLE_EMOJI_MAP) as $dot) {
        $prefix = $dot . ' ';
        $len    = mb_strlen($prefix, 'UTF-8');
        if (mb_substr($text, 0, $len, 'UTF-8') === $prefix) {
            return mb_substr($text, $len, null, 'UTF-8');
        }
    }
    return $text;
}

if (!function_exists('rx_callbackMapRead')) {
    function rx_callbackMapRead($file): ?array {
        $handle = @fopen((string) $file, 'r');
        if ($handle === false) return null;
        try {
            if (!@flock($handle, LOCK_SH)) return null;
            $raw = stream_get_contents($handle);
            $map = is_string($raw) ? json_decode($raw, true) : null;
            return is_array($map) ? $map : null;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}
if (!function_exists('rx_callbackMapStore')) {
    function rx_callbackMapStore($file, $key, $value): bool {
        $handle = @fopen((string) $file, 'c+');
        if ($handle === false) {
            error_log('[rx_callback_map] could not open ' . basename((string) $file));
            return false;
        }
        $written = false;
        try {
            if (@flock($handle, LOCK_EX)) {
                $raw = stream_get_contents($handle);
                $map = is_string($raw) && trim($raw) === '' ? [] : (is_string($raw) ? json_decode($raw, true) : null);
                if (is_array($map)) {
                    if (isset($map[(string) $key]) && $map[(string) $key] === (string) $value) {
                        $written = true;
                    } else {
                        $map[(string) $key] = (string) $value;
                        $encoded = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (is_string($encoded) && @rewind($handle)) {
                            $offset = 0;
                            $length = strlen($encoded);
                            while ($offset < $length) {
                                $bytes = @fwrite($handle, substr($encoded, $offset));
                                if ($bytes === false || $bytes === 0) break;
                                $offset += $bytes;
                            }
                            $written = $offset === $length && @ftruncate($handle, $length) && @fflush($handle);
                            if (!$written && @rewind($handle)) {
                                $restoreOffset = 0;
                                $restoreLength = strlen($raw);
                                while ($restoreOffset < $restoreLength) {
                                    $bytes = @fwrite($handle, substr($raw, $restoreOffset));
                                    if ($bytes === false || $bytes === 0) break;
                                    $restoreOffset += $bytes;
                                }
                                if ($restoreOffset === $restoreLength) @ftruncate($handle, $restoreLength);
                                @fflush($handle);
                            }
                        }
                    }
                }
            }
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        if (!$written) error_log('[rx_callback_map] write failed for ' . basename((string) $file));
        return $written;
    }
}
if (!method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_adminPanelCallbackMapFile')) {
    function rx_adminPanelCallbackMapFile(): string {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/') . '/rx_admin_panel_callback_map.json';
    }
}
if (!method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_makeAdminPanelCallback')) {
    function rx_makeAdminPanelCallback(string $text): string {
        $short = 'apn:' . $text;
        if (strlen($short) <= 64) return $short;
        $code = 'apnh:' . substr(hash('sha256', $text), 0, 32);
        return rx_callbackMapStore(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_adminPanelCallbackMapFile(), $code, $text) ? $code : 'admin_back';
    }
}
if (!method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_resolveAdminPanelCallback')) {
    function rx_resolveAdminPanelCallback(string $callbackData): ?string {
        if (strpos($callbackData, 'apn:') === 0) {
            return substr($callbackData, 4);
        }
        if (strpos($callbackData, 'apnh:') === 0) {
            $map = rx_callbackMapRead(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_adminPanelCallbackMapFile());
            return (is_array($map) && isset($map[$callbackData])) ? (string)$map[$callbackData] : null;
        }
        return null;
    }
}
if (!method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_keyboardIsAdminInterface')) {
    function rx_keyboardIsAdminInterface($keyboard): bool {
        $kb = is_array($keyboard) ? $keyboard : (is_string($keyboard) ? json_decode($keyboard, true) : null);
        if (!is_array($kb)) return false;
        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return false;

        $adminLabels = [
            '🏠 بازگشت به منوی مدیریت' => true,
            'بازگشت به منوی مدیریت' => true,
            '▶️ بازگشت به منوی قبل' => true,
            'بازگشت به منوی قبل' => true,
        ];
        $lang = isset($GLOBALS['textbotlang']) && is_array($GLOBALS['textbotlang']) ? $GLOBALS['textbotlang'] : null;
        if ($lang !== null && isset($lang['Admin']) && is_array($lang['Admin'])) {
            foreach (['backadmin', 'backmenu'] as $labelKey) {
                if (!empty($lang['Admin'][$labelKey]) && is_string($lang['Admin'][$labelKey])) {
                    $adminLabels[trim($lang['Admin'][$labelKey])] = true;
                }
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($row as $btn) {
                if (!is_array($btn)) continue;
                if (!empty($btn['_rxap']) || !empty($btn['_rxadm'])) return true;
                if (isset($btn['callback_data']) && is_string($btn['callback_data'])) {
                    $cb = $btn['callback_data'];
                    if (strpos($cb, 'apn:') === 0 || strpos($cb, 'apnh:') === 0
                        || $cb === 'adm_hub_main' || $cb === 'adm_backmenu') {
                        return true;
                    }
                }
                if (isset($btn['text']) && is_scalar($btn['text'])) {
                    $label = trim((string)$btn['text']);
                    if ($label !== '' && isset($adminLabels[$label])) return true;
                }
            }
        }
        return false;
    }
}
if (!method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_finalizeInlineAdminKb')) {
    function rx_finalizeInlineAdminKb(string $json, bool $rxMarkPlain = true): string {
        $kb = json_decode($json, true);
        if (!is_array($kb)) return $json;
        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return $json;




        $rxAdminKb = $rxMarkPlain;
        if (!$rxAdminKb && method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_keyboardIsAdminInterface')) {
            $rxAdminKb = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_keyboardIsAdminInterface($kb);
        }
        $rxGlassMode = isset($GLOBALS['setting']['inlinebtnmain']) && $GLOBALS['setting']['inlinebtnmain'] === 'oninline';
        if (!$rxGlassMode && !$rxAdminKb && isset($kb['keyboard'])) {
            foreach ($kb['keyboard'] as &$rxFRow) {
                if (!is_array($rxFRow)) continue;
                foreach ($rxFRow as &$rxFBtn) {
                    if (!is_array($rxFBtn)) continue;
                    if ($rxMarkPlain) { $rxFBtn['_rxap'] = 1; } else { $rxFBtn['_rxkeep'] = 1; }
                }
                unset($rxFBtn);
            }
            unset($rxFRow);
            return json_encode($kb, JSON_UNESCAPED_UNICODE);
        }

        $hasContactRequest = false;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($row as $btn) {
                if (is_array($btn) && (isset($btn['request_contact']) || isset($btn['request_location']) || isset($btn['request_poll']) || isset($btn['request_users']) || isset($btn['request_chat']))) {
                    $hasContactRequest = true;
                    break 2;
                }
            }
        }
        if ($hasContactRequest) {

            return $json;
        }

        $newRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;
                if (!isset($btn['callback_data']) && !isset($btn['url'])
                    && !isset($btn['login_url']) && !isset($btn['switch_inline_query'])
                    && !isset($btn['switch_inline_query_current_chat']) && !isset($btn['web_app'])) {
                    $btn['callback_data'] = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_makeAdminPanelCallback((string)$btn['text']);
                }
                if ($rxMarkPlain) { $btn['_rxap'] = 1; } else { $btn['_rxkeep'] = 1; }
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $newRows[] = $newRow;
        }
        return json_encode(['inline_keyboard' => $newRows], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_sanitizeKeyboardButtons')) {
    /**
     * Guarantee every keyboard button carries a non-empty string `text` so Telegram
     * never rejects the markup with: Bad Request: can't parse keyboard button:
     * Field "text" must be of type String.
     * Button text coming from the DB/lang can be null/empty when a deployment's DB
     * is broken; such buttons are dropped here (and emptied rows removed). Works for
     * both reply (`keyboard`) and inline (`inline_keyboard`) markups and preserves
     * the wrapper key plus any top-level flags (resize_keyboard, etc.).
     */
    function rx_sanitizeKeyboardButtons($json) {
        if (!is_string($json) || $json === '') return $json;
        $kb = json_decode($json, true);
        if (!is_array($kb)) return $json;
        $wrapperKey = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $wrapperKey = 'keyboard';
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $wrapperKey = 'inline_keyboard';
        }
        if ($wrapperKey === null) return $json;
        $newRows = [];
        foreach ($kb[$wrapperKey] as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn)) continue;
                // Drop only buttons Telegram would reject: missing / null / non-scalar /
                // empty-string text. Keep a single space ' ' (used by premium icon-only
                // buttons) and any other non-empty text as-is. Do NOT trim — a leading/
                // trailing space can be intentional and is a valid string for Telegram.
                if (!array_key_exists('text', $btn) || $btn['text'] === null) continue;
                $t = $btn['text'];
                if (!is_string($t)) {
                    if (is_scalar($t)) { $t = (string)$t; } else { continue; }
                }
                if ($t === '') continue;
                $btn['text'] = $t;
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $newRows[] = array_values($newRow);
        }
        $kb[$wrapperKey] = array_values($newRows);
        return json_encode($kb, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_collectKeyboardLabels')) {
    /**
     * Collect every non-empty button label from a keyboard JSON string into $out.
     * Used by the admin nav-guard to enumerate menu buttons so that pressing one
     * mid-step resets the step instead of being swallowed as step input.
     */
    function rx_collectKeyboardLabels($json, array &$out) {
        if (!is_string($json) || $json === '') return;
        $kb = json_decode($json, true);
        if (!is_array($kb)) return;
        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;
                $label = trim((string)$btn['text']);
                if ($label !== '') $out[] = $label;
            }
        }
    }
}


if (!function_exists('rx_isAdminChat')) {
    function rx_isAdminChat($chatId): bool {
        $chatId = trim((string)$chatId);
        if ($chatId === '') return false;
        static $adminSet = null;
        if ($adminSet === null) {
            $adminSet = [];
            $rxMain = trim((string)($GLOBALS['adminnumber'] ?? ''));
            if ($rxMain !== '') { $adminSet[$rxMain] = true; }
            try {
                global $pdo;
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT id_admin FROM admin WHERE id_admin IS NOT NULL AND id_admin <> '' AND id_admin <> '0'");
                    if ($stmt) {
                        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rxId) {
                            $rxId = trim((string)$rxId);
                            if ($rxId !== '') { $adminSet[$rxId] = true; }
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        return isset($adminSet[$chatId]);
    }
}

if (!function_exists('rx_glassModeOn')) {
    function rx_glassModeOn(): bool {
        return isset($GLOBALS['setting']['inlinebtnmain']) && $GLOBALS['setting']['inlinebtnmain'] === 'oninline';
    }
}

if (!function_exists('rx_richUserButtonsOn')) {
    function rx_richUserButtonsOn(): bool {
        return isset($GLOBALS['setting']['user_rich_buttons_enabled']) && $GLOBALS['setting']['user_rich_buttons_enabled'] === 'on';
    }
}

if (!function_exists('rx_richModeOn')) {
    function rx_richModeOn(): bool {
        return rx_richUserButtonsOn();
    }
}

if (!function_exists('rx_inlineUiOn')) {
    function rx_inlineUiOn(): bool {
        return rx_glassModeOn() || rx_richModeOn();
    }
}

if (!function_exists('rx_autoRemoveReplyKeyboardOn')) {
    function rx_autoRemoveReplyKeyboardOn(): bool {
        $value = $GLOBALS['setting']['auto_remove_reply_keyboard'] ?? 'on';
        return (string) $value !== 'off';
    }
}

if (!function_exists('rx_flushPendingReplyKeyboardCleanup')) {
    function rx_flushPendingReplyKeyboardCleanup($chatId): void {
        $chatId = trim((string) $chatId);
        if ($chatId === '' || !ctype_digit($chatId)) return;
        if (!\Faoxima\Core\Database\Db::runtimeReady() || !\Faoxima\Core\Database\Db::runtimeReady()) return;

        try {
            $pendingId = \Faoxima\Core\Database\Db::select('user', 'reply_kb_cleanup_msg_id', 'id', $chatId, 'select', ['cache' => false]);
            $pendingId = is_scalar($pendingId) ? (int) $pendingId : 0;
            if ($pendingId <= 0) return;

            if (function_exists('deletemessage')) {
                deletemessage($chatId, $pendingId);
            }
            \Faoxima\Core\Database\Db::update('user', 'reply_kb_cleanup_msg_id', '0', 'id', $chatId);
        } catch (Throwable $e) {
            error_log('[rx_flushPendingReplyKeyboardCleanup] ' . $e->getMessage());
        }
    }
}

if (!function_exists('removeReplyKeyboardOnStartIfNeeded')) {
    function removeReplyKeyboardOnStartIfNeeded($chatId, bool $rxForceAdminEntry = false): void {
        $chatId = trim((string) $chatId);
        if ($chatId === '' || !ctype_digit($chatId)) return;
        if (!\Faoxima\Core\Database\Db::runtimeReady() || !\Faoxima\Core\Database\Db::runtimeReady()) return;

        try {
            rx_flushPendingReplyKeyboardCleanup($chatId);

            $rxRichCleanup = rx_richUserButtonsOn() && method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richActiveForSend') && \Faoxima\Telegram\Presentation\Sections::rx_richActiveForSend($chatId, null, 'main_menu');

            if (!rx_glassModeOn() && !$rxRichCleanup && !$rxForceAdminEntry) {
                $flag = \Faoxima\Core\Database\Db::select('user', 'reply_kb_cleared', 'id', $chatId, 'select', ['cache' => false]);
                $flag = is_scalar($flag) ? (string) $flag : '0';
                if ($flag === '1') {
                    \Faoxima\Core\Database\Db::update('user', 'reply_kb_cleared', '0', 'id', $chatId);
                }
                return;
            }

            if (!rx_autoRemoveReplyKeyboardOn()) {
                return;
            }

            $flag = \Faoxima\Core\Database\Db::select('user', 'reply_kb_cleared', 'id', $chatId, 'select', ['cache' => false]);
            $flag = is_scalar($flag) ? (string) $flag : '0';
            if ($flag === '1' && !$rxForceAdminEntry) {
                return;
            }

            if ($rxRichCleanup && !$rxForceAdminEntry) {
                $GLOBALS['rx_pending_reply_kb_remove'][$chatId] = true;
                return;
            }

            $rxRkrResult = telegram('sendmessage', [
                'chat_id' => $chatId,
                'text' => '.',
                'reply_markup' => json_encode(['remove_keyboard' => true], JSON_UNESCAPED_UNICODE),
                '_rx_already_transformed' => true,
            ]);

            $rxRkrOk = is_array($rxRkrResult) && !empty($rxRkrResult['ok']);
            $rxRkrMessageId = $rxRkrOk ? (int) ($rxRkrResult['result']['message_id'] ?? 0) : 0;

            if (!$rxRkrOk || $rxRkrMessageId <= 0) {
                return;
            }

            \Faoxima\Core\Database\Db::update('user', 'reply_kb_cleared', '1', 'id', $chatId);

            usleep(500000);

            $rxRkrDeleteOk = false;
            if (function_exists('deletemessage')) {
                $rxRkrDeleteResult = deletemessage($chatId, $rxRkrMessageId);
                $rxRkrDeleteOk = is_array($rxRkrDeleteResult) && !empty($rxRkrDeleteResult['ok']);
            }

            \Faoxima\Core\Database\Db::update('user', 'reply_kb_cleanup_msg_id', $rxRkrDeleteOk ? '0' : (string) $rxRkrMessageId, 'id', $chatId);
        } catch (Throwable $e) {
            error_log('[removeReplyKeyboardOnStartIfNeeded] ' . $e->getMessage());
        }
    }
}

if (!function_exists('removeReplyKeyboardIfNeeded')) {
    function removeReplyKeyboardIfNeeded($chatId, bool $rxForceAdminEntry = false): void {
        removeReplyKeyboardOnStartIfNeeded($chatId, $rxForceAdminEntry);
    }
}

function rx_payment_color_fallback(string $role): string {
    $roles = [
        'pay_link' => 'pay_done', 'continue_link' => 'pay_resume', 'retry' => 'pay_resume',
        'check_status' => 'pay_check', 'view_details' => 'pay_check', 'list_view' => 'pay_check',
        'cancel_transaction' => 'pay_cancel', 'pending_cancel' => 'pay_cancel', 'list_cancel' => 'pay_cancel',
        'cancel_confirm_approve' => 'pay_cancel', 'cancel_confirm_back' => 'pay_back',
        'back_main' => 'pay_back', 'back_account' => 'pay_back', 'pending_list' => 'pay_back',
        'list_receipt' => 'pay_sendreceipt', 'send_receipt' => 'pay_sendreceipt', 'receipt_back' => 'pay_back',
    ];
    return $roles[$role] ?? 'pay_check';
}

function rx_payment_button(string $gateway, string $role, array $button): array {
    if ($gateway === 'Currency Rial 2') $gateway = 'iranpay2';
    if ($role === 'back_account') $gateway = 'pending';
    $button['_rxcolor'] = 'payment:' . $gateway . ':' . $role;
    $button['_rxsk'] = rx_payment_color_fallback($role);
    $button['_rxkeep'] = true;
    return $button;
}

function rx_button_color_key(array $button, ?bool &$inheritsDefaults = null): ?string {
    $inheritsDefaults = false;
    if (!empty($button['_rxcron'])) return hash('sha256', 'cron:' . $button['_rxcron']);
    if (isset($button['_rxcolor']) && is_string($button['_rxcolor']) && $button['_rxcolor'] !== '') {
        $inheritsDefaults = true;
        $identity = $button['_rxcolor'];
        if (strpos($identity, 'service:') === 0) {
            $identity = 'service_template:' . substr($identity, strrpos($identity, ':') + 1);
        }
        if (strpos($identity, 'channel:') === 0) $identity = 'catalog_template:channels:channel';
        if (strpos($identity, 'crypto_order:') === 0) $identity = 'catalog_template:crypto:' . substr($identity, strrpos($identity, ':') + 1);
        return hash('sha256', $identity);
    }
    if (!empty($button['_rxck'])) {
        $inheritsDefaults = true;
        return hash('sha256', 'copy:' . $button['_rxck']);
    }
    $callback = $button['callback_data'] ?? null;
    if (!is_string($callback) || $callback === '') {
        $url = $button['url'] ?? ($button['web_app']['url'] ?? null);
        return is_string($url) && $url !== '' ? hash('sha256', 'url:' . $url) : null;
    }
    if (in_array($callback, ['none', 'noop'], true)) return null;
    if (strpos($callback, 'extends_') === 0) {
        $inheritsDefaults = true;
        return hash('sha256', 'subscription_action:extend');
    }
    if (strpos($callback, 'Extra_volumes_') === 0) {
        $inheritsDefaults = true;
        return hash('sha256', 'subscription_action:volume');
    }
    if ($callback === 'backuser') {
        $inheritsDefaults = true;
        return hash('sha256', 'navigation:back');
    }
    $catalogActions = [
        'categorynames_' => ['categories', 'category'],
        'producttime_' => ['durations', 'time'], 'productmonth_' => ['durations', 'category_time'], 'cdpick_' => ['durations', 'custom_time'],
        'productvolume_' => ['volumes', 'volume'],
        'locationnotuser_' => ['panels', 'panel'], 'locationom_' => ['panels', 'panel'], 'location_' => ['panels', 'panel'],
        'paneluserbuy_' => ['panels', 'panel'], 'locationbuy_' => ['panels', 'panel'],
        'locationtest_' => ['panels', 'panel'], 'changelocselectlo-' => ['panels', 'panel'],
        'helpctgoryـ' => ['help_categories', 'category'], 'helpos_' => ['help', 'guide'], 'helpsection_' => ['help', 'guide'],
        'departman_' => ['departments', 'department'], 'ticketopen_' => ['tickets', 'open'], 'ticketmsg_' => ['tickets', 'message'],
        'ticketclose_' => ['tickets', 'close'], 'tk_album_' => ['tickets', 'media'],
        'digitaltron_submit_' => ['crypto', 'paid_submit'], 'crypto_cancel_' => ['crypto', 'invoice_back'],
        'rcc_view_' => ['crypto', 'rcc_view'], 'rcc_resubmit_' => ['crypto', 'rcc_resubmit'],
    ];
    foreach ($catalogActions as $prefix => [$group, $role]) {
        if (strpos($callback, $prefix) === 0) {
            $inheritsDefaults = true;
            return hash('sha256', 'catalog_template:' . $group . ':' . $role);
        }
    }
    $serviceActions = [
        'product_' => 'service_manage', 'config_' => 'config', 'extend_' => 'extend',
        'changelink_' => 'changelink', 'changenote_' => 'changenameconfig',
        'Extra_volume_' => 'Extra_volume', 'Extra_time_' => 'Extra_time',
        'changestatus_' => 'changestatus', 'transfer_' => 'transfor', 'changeloc_' => 'change-location',
        'disorder-' => 'ekhtelal', 'remnausage_' => 'remna_usage', 'remnapicker_' => 'remna_picker',
        'remnasetsquad_' => 'squad_select', 'confrimtransfers_' => 'confirm_transfer',
        'confirmremoveservices-' => 'confirm_remove', 'confirmremovefulls-' => 'confirm_remove',
        'confirmdisorders-' => 'confirm_report', 'confirmchangeloccha_' => 'confirm_location',
        'confirmaextra-' => 'confirm_volume', 'confirmaextradiscount-' => 'confirm_volume_discount',
        'confirmaextras_' => 'confirm_external_volume',
        'confirmaextratime-' => 'confirm_time', 'confirmaextratimediscount-' => 'confirm_time_discount',
        'discountvolume-' => 'discount_volume', 'discounttime-' => 'discount_time',
        'serviceextendselect_' => 'renewal_product', 'nmstockok_' => 'stock_confirm',
        'quickview_' => 'service_open', 'updateproduct_' => 'updateinfo',
        'subscriptionurl_' => 'linksub', 'infocard_qr_' => 'infocard_qr',
        'discountextend' => 'discountextend', 'productcheckdata' => 'productcheckdata',
        'removeserviceuser_' => 'removeservice', 'confirmaccountdisable_' => 'confirm_status',
        'confirmchange_' => 'confirm_link', 'nmstockextend_' => 'stock_extend',
        'nmstockrefund_' => 'stock_refund', 'nmstockcfg_' => 'stock_config',
        'nmstocksub_' => 'stock_subscription', 'backorder' => 'backorder',
    ];
    foreach ($serviceActions as $prefix => $role) {
        if (($prefix === 'backorder' && $callback === $prefix) || ($prefix !== 'backorder' && strpos($callback, $prefix) === 0)) {
            $inheritsDefaults = true;
            return hash('sha256', 'service_template:' . $role);
        }
    }
    if ($callback === 'exntedagei') {
        $inheritsDefaults = true;
        return hash('sha256', 'service_template:extend_current');
    }
    if (!empty($button['_rxsk']) && in_array($button['_rxsk'], ['product_back', 'category_back', 'panel_back'], true)) {
        return hash('sha256', 'role:' . $button['_rxsk']);
    }
    return hash('sha256', 'callback:' . $callback);
}

function processKeyboardStyles($keyboard, $rxPlainAdmin = false, $rxChatId = null) {
    $styleMap = unserialize(REPLY_STYLE_EMOJI_MAP);
    $wasString = is_string($keyboard);
    if ($wasString) {
        if ($keyboard === '') return $keyboard;
        $kb = json_decode($keyboard, true);
        if (!is_array($kb)) return $keyboard;
    } elseif (is_array($keyboard)) {
        $kb = $keyboard;
    } else {
        return $keyboard;
    }






    static $_rx_kb_list_styles = null;
    if ($_rx_kb_list_styles === null) {
        $_rx_kb_list_styles = ['lists' => [], 'misc' => [], 'features' => [], 'items' => []];
        $rxKbNoDefault = static function ($arr) {
            if (!is_array($arr)) { return []; }
            return array_filter($arr, static function ($v) { return $v !== 'default'; });
        };
        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                    $all = json_decode($row['keyboard_styles_all'], true);
                    if (is_array($all)) {
                        if (isset($all['button_items']) && is_array($all['button_items'])) {
                            $_rx_kb_list_styles['items'] = $rxKbNoDefault($all['button_items']);
                        }
                        if (!empty($all['admin_lists']))  $_rx_kb_list_styles['lists'] = $rxKbNoDefault($all['admin_lists']);
                        if (!empty($all['user_dynamic_lists']) && is_array($all['user_dynamic_lists'])) {

                            $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $rxKbNoDefault($all['user_dynamic_lists']);
                        }
                        if (!empty($all['cron_notifications']) && is_array($all['cron_notifications'])) {

                            $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $rxKbNoDefault($all['cron_notifications']);
                        }
                        if (!empty($all['misc_buttons'])) $_rx_kb_list_styles['misc']  = $rxKbNoDefault($all['misc_buttons']);

                        $featMenus = [
                            'features_nav','features_bot','features_users','features_shop',
                            'features_lottery','features_crons','features_antispam',
                            'admin_pagination','admin_stats','admin_search','admin_user_lists',
                            'admin_filters','admin_node','admin_gateway_extra',
                            'admin_cart_advanced','admin_crypto','admin_iplogin','admin_infocard',
                            'admin_discount_settings','admin_misc_actions','admin_premium_stock',
                            'user_subscription','recheckcrypto_buttons','recheckcrypto_admin_buttons',


                            'admin_main','admin_settings','admin_shop','admin_roles','admin_gateways',
                            'admin_features','admin_channel','admin_help','admin_category','admin_products',


                            'service','config_list','account','payment','user_nav','pay_receipt','invoice_copy_buttons','home_menu','wallet_checkout',
                        ];
                        foreach ($featMenus as $fm) {
                            if (!empty($all[$fm]) && is_array($all[$fm])) {
                                $_rx_kb_list_styles['features'] = array_merge(
                                    $_rx_kb_list_styles['features'], $rxKbNoDefault($all[$fm])
                                );
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}




        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $rx_kb_all_defaults = rx_getKeyboardDefaultStyles();
            if (is_array($rx_kb_all_defaults)) {
                foreach ($rx_kb_all_defaults as $rx_kb_sec => $rx_kb_def_pairs) {
                    if (!is_array($rx_kb_def_pairs)) { continue; }


                    if (strpos($rx_kb_sec, 'admin_panel_') === 0) { continue; }

                    if ($rx_kb_sec === 'misc_buttons') {

                        $_rx_kb_list_styles['misc'] = $_rx_kb_list_styles['misc'] + $rxKbNoDefault($rx_kb_def_pairs);
                    } elseif ($rx_kb_sec === 'admin_lists' || $rx_kb_sec === 'user_dynamic_lists' || $rx_kb_sec === 'cron_notifications') {

                        $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $rxKbNoDefault($rx_kb_def_pairs);
                    } else {

                        $_rx_kb_list_styles['features'] = $_rx_kb_list_styles['features'] + $rxKbNoDefault($rx_kb_def_pairs);
                    }
                }
            }
        }
    }

    static $_rx_admin_plain_cb = null;
    if ($_rx_admin_plain_cb === null) {
        $_rx_admin_plain_cb = [];
        $_rx_user_secs = ['service','config_list','account','payment','user_nav','pay_receipt','user_subscription','user_dynamic_lists','invoice_copy_buttons','recheckcrypto_buttons','home_menu'];
        if (function_exists('rx_getKeyboardDefaultStyles')) {
            $_rx_admin_def = rx_getKeyboardDefaultStyles();
            if (is_array($_rx_admin_def)) {
                $_rx_user_keys = [];
                foreach ($_rx_user_secs as $_rx_us) {
                    if (!empty($_rx_admin_def[$_rx_us]) && is_array($_rx_admin_def[$_rx_us])) {
                        foreach ($_rx_admin_def[$_rx_us] as $_rx_uk => $_rx_uv) { $_rx_user_keys[(string)$_rx_uk] = true; }
                    }
                }
                foreach ($_rx_admin_def as $_rx_sec => $_rx_pairs) {
                    if (!is_array($_rx_pairs) || in_array($_rx_sec, $_rx_user_secs, true)) continue;
                    foreach ($_rx_pairs as $_rx_cbk => $_rx_cbv) {
                        $_rx_cbk = (string)$_rx_cbk;
                        if (!isset($_rx_user_keys[$_rx_cbk])) { $_rx_admin_plain_cb[$_rx_cbk] = true; }
                    }
                }
            }
        }
        foreach (['confirmandgetservice','confirmserivce','confirmserdiscount','acceptrule','buyback','startaction','next_page','previous_page','next_page_extends','previous_page_extends','searchservice'] as $_rx_uex) {
            unset($_rx_admin_plain_cb[$_rx_uex]);
        }
    }
    $listPrefixMap = [
        
        'editpanel_'             => 'panel_list',
        'locationedit_'          => 'panel_list',
        'location_'              => 'paneluser_list',
        'locationnotuser_'       => 'paneluser_list',
        'locationom_'            => 'paneluser_list',
        'locationtest_'          => 'usertest_list',
        'testpanel_'             => 'usertest_list',
        'changeloc_'             => 'changeloc_list',
        'changelocselectlo-'     => 'changeloc_list',
        'changeloclimitbyuser_'  => 'changeloc_list',
        'confirmchangeloccha_'   => 'changeloc_list',
        
        'productedit_'           => 'product_list',
        'productdel_'            => 'product_list',
        'product_'               => 'product_list',
        
        'quickview_'             => 'user_services_list',
        'categorynames_'         => 'category_buy',
        'category_'              => 'category_list',
        'discountedit_'          => 'discount_list',
        'discountdel_'           => 'discount_list',
        'discount_'              => 'discount_list',
        'inboundedit_'           => 'inbound_list',
        'inbound_'               => 'inbound_list',
        
        'helpedit_'              => 'help_list',
        'helpdel_'               => 'help_list',
        'helpctgory_'            => 'help_list',
        'channeldel_'            => 'channel_list',
        'confirmchannel-'        => 'channel_list',
        'carduserhide-'          => 'card_list',
        'cardremove_'            => 'card_list',
        
        'banuserlist_'           => 'user_list',
        'blockuserfake_'         => 'user_list',
        'addbalanceuser_'        => 'user_list',
        'addbalamceuser_'        => 'user_list',
        'agenttypshowlist_'      => 'agent_list',
        'addagent_'              => 'agent_list',
        'addagentrequest_'       => 'agent_list',
        'setagenttype_'          => 'agent_list',
        'expireset_'             => 'agent_list',
        'maxbuyagent_'           => 'agent_list',
        'setvolumesrc_'          => 'agent_list',
        'settimepricesrc_'       => 'agent_list',
        
        'hidepanel_'             => 'panel_list',
        'removehide_'            => 'panel_list',
        
        'deliplogin_'            => 'admin_iplogin_dyn',
        'confirmcryptomanual_'   => 'crypto_manual_actions',
        'rejectcryptomanual_'    => 'crypto_manual_actions',
        'cmauto_'                => 'crypto_manual_actions',
        'cmmanual_'              => 'crypto_manual_actions',
        'cmback_'                => 'crypto_manual_actions',
        'rcc_pick_'              => 'crypto_manual_actions',
        
        'editstsuts-'            => 'feature_toggle',
        
        
        'Response_'              => 'support_response',
        'Responsesupport_'       => 'support_response',
        'Responsesusera_'        => 'support_response',
        'Responseuser'           => 'support_response',
        
        'Extra_time_'            => 'extra_purchase',
        'Extra_volume_'          => 'extra_purchase',
        'Extra_volumes_'         => 'extra_purchase',
        'Percentlow_'            => 'extra_purchase',
        
        'btntypemessage-'        => 'btnmsg_settings',
        
        'confirmaccountdisable_'    => 'user_confirms',
        'confirmaccountdisableadmin_'=> 'user_confirms',
        'confirmaextra-'         => 'user_confirms',
        'confirmaextras_'        => 'user_confirms',
        'confirmaextratime-'     => 'user_confirms',
        'confirmchange_'         => 'user_confirms',
        'confirmdisorders-'      => 'user_confirms',
        'confirmnumber_'         => 'user_confirms',
        'confirmremovefulls-'    => 'user_confirms',
        'confirmremoveservices-' => 'user_confirms',
        'confirmserivceadmin-'   => 'user_confirms',
        'confirmserivces-'       => 'user_confirms',
        
        'removeadmin_'           => 'admin_removes',
        'removeaffiliate-'       => 'admin_removes',
        'removeaffiliateuser-'   => 'admin_removes',
        'removeagent_'           => 'admin_removes',
        'removeauto-'            => 'service_actions',
        'removebotsell_'         => 'admin_removes',
        'rejectrequesta_'        => 'admin_removes',
        
        'crypto_pay_'            => 'crypto_actions',
        'crypto_submit_'         => 'crypto_actions',
        'cryptowallet_'          => 'crypto_actions',
        
        'config_'                => 'service_actions',
        'configget_'             => 'service_actions',
        'configpage_'            => 'service_actions',
        'activeconfig-'          => 'service_actions',
        'changelink_'            => 'service_actions',
        'changenote_'            => 'service_actions',
        'changestatus_'          => 'service_actions',
        'changestatusadmin_'     => 'service_actions',
        
        'previous_page'          => 'pagination_btns',
        'next_page'              => 'pagination_btns',
        
        'broadcast_'             => 'broadcast_actions',
        'sendmsg_'               => 'broadcast_actions',
        'accept_broadcast_'      => 'broadcast_actions',
        
        'affiliates-'            => 'affiliate_actions',
        
        'editshops-'             => 'shop_edit_actions',
        'changeipnode'           => 'node_actions',
        'changenamenode'         => 'node_actions',
        'changecoefficient'      => 'node_actions',
        'bakcnode'               => 'node_actions',
        'actionnode'             => 'node_actions',
        'deletelist-'            => 'service_actions',


        'prodcutservice_'        => 'product_buy',
        'prodcutservices_'       => 'product_buy',
        'prodcutserviceom_'      => 'product_buy',
        'prodcutservicesom_'     => 'product_buy',
        'customsellvolume'       => 'custom_volume',
        'serviceextendselects-'  => 'product_buy',
        'serviceextendselect-'   => 'product_buy',
        'serviceextend-'         => 'product_buy',
        'producttime_'           => 'time_buy',
        'productmonth_'          => 'time_buy',
        'cdpick_'                => 'time_buy',
        'productvolume_'         => 'volume_buy',
        'categorynames_'         => 'category_buy',
        'helpsection_'           => 'helpsection',
        'paneluserbuy_'          => 'panel_buy',
        'locationbuy_'           => 'panel_buy',

        'updateproduct_'         => 'service_actions',
        'subscriptionurl_'       => 'service_actions',
        'removeserviceuser_'     => 'service_actions',
        'transfer_'              => 'service_actions',
        'disorder-'              => 'service_actions',
        'confrimtransfers_'      => 'service_actions',
        'extends_'               => 'service_actions',
        'infocard_qr_'           => 'service_actions',
        'digitaltron_pay_'       => 'crypto_actions',
        'digitaltron_submit_'    => 'crypto_actions',
        'crypto_cancel_'         => 'invoice_back',
        'rcc_resubmit_'          => 'crypto_manual_actions',
        'discountvolume-'        => 'extra_purchase',
        'discounttime-'          => 'extra_purchase',
        'helpos_'                => 'helpsection',
        'ticketopen_'            => 'ticket_list',
        'ticketmsg_'             => 'ticket_list',
        'ticketclose_'           => 'ticket_list',
        'tk_cnt_'                => 'ticket_list',
        'tk_album_'              => 'ticket_list',
        'departman_'             => 'ticket_list',
        'remnausage_'            => 'service_actions',
        'remnapicker_'           => 'service_actions',
        'remnasetsquad_'         => 'service_actions',
        'helpctgoryـ'            => 'helpsection',
        'rcc_view_'              => 'crypto_manual_actions',


        'apn:'                   => 'auto_inline_btn',
        'apnh:'                  => 'auto_inline_btn',


        'extend_'                => 'cron_extend',
        'manageuser_'            => 'cron_manage_user',
        'managepanel_'           => 'cron_manage_panel',
        'cronnotify_'            => 'cron_action_btn',
    ];
    $miscMap = [
        'adm_backmenu'      => 'adm_backmenu',
        'adm_backadmin'     => 'adm_backadmin',
        'hide_mini_app_instruction' => 'hide_mini_app',
        'backproductadmin'  => 'backproductadmin',
        'backadmin'         => 'backadmin',
        'backlistuser'      => 'backlistuser',
        'buyback'           => 'buyback',
        'cancel_gift'       => 'cancel_gift',
        'cancel_hash_input' => 'cancel_hash_input',
        'cancel_sendmessage'=> 'cancel_sendmessage',
        'close_listusers'   => 'close_listusers',
        'close_stat'        => 'close_stat',
        'cronjobs_back_settings'=> 'cronjobs_back_settings',
        'resetbot_cancel'   => 'resetbot_cancel',
        'resetbot_confirm'  => 'resetbot_confirm',
        'broadcast_status_refresh' => 'broadcast_status_refresh',
        'reject'            => 'reject',
        'accept'            => 'accept',
        'acceptrule'        => 'acceptrule',
        'confirmpaid'       => 'confirmpaid',
        'confirmandgetservice' => 'confirmandgetservice',
        'confirmandgetserviceDiscount' => 'confirmandgetserviceDiscount',
        'confirmchannel'    => 'confirmchannel',


        'backuser'          => 'backuser',
        'nav_back'          => 'nav_back',
        'backorder'         => 'backorder',
        'colselist'         => 'colselist',
        'supportbtns'       => 'supportbtns',
        'helpbtns'          => 'helpbtns',
        'usertestbtn'       => 'usertestbtn',
        'none'              => 'inert_label',
        'confirmserdiscount'=> 'confirmserdiscount',
        'confirmserivce'    => 'confirmserivce',
    ];











    $rxGlassOn = isset($GLOBALS['setting']['inlinebtnmain']) && $GLOBALS['setting']['inlinebtnmain'] === 'oninline';
    if (!$rxGlassOn && method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_keyboardIsAdminInterface') && \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_keyboardIsAdminInterface($kb)) {
        $rxGlassOn = true;
    }
    if (!$rxGlassOn && $rxChatId !== null && method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richActiveForSend') && \Faoxima\Telegram\Presentation\Sections::rx_richActiveForSend($rxChatId, $kb)) {
        $rxGlassOn = true;
    }

    if ($rxGlassOn && isset($kb['keyboard']) && is_array($kb['keyboard'])) {
        $rxHasReplyFeature = false;
        foreach ($kb['keyboard'] as $rxRChk) {
            if (!is_array($rxRChk)) continue;
            foreach ($rxRChk as $rxBChk) {
                if (is_array($rxBChk) && (
                    isset($rxBChk['request_contact']) ||
                    isset($rxBChk['request_location']) ||
                    isset($rxBChk['request_poll']) ||
                    isset($rxBChk['request_users']) ||
                    isset($rxBChk['request_chat'])
                )) {
                    $rxHasReplyFeature = true;
                    break 2;
                }
            }
        }

        if (!$rxHasReplyFeature && method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_makeAdminPanelCallback')) {
            $rxInlineRows = [];
            foreach ($kb['keyboard'] as $rxR) {
                if (!is_array($rxR)) continue;
                $rxNewRow = [];
                foreach ($rxR as $rxB) {
                    if (!is_array($rxB) || !isset($rxB['text'])) continue;
                    if (!isset($rxB['callback_data']) && !isset($rxB['url'])
                        && !isset($rxB['login_url']) && !isset($rxB['switch_inline_query'])
                        && !isset($rxB['switch_inline_query_current_chat']) && !isset($rxB['web_app'])) {
                        $rxB['callback_data'] = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_makeAdminPanelCallback((string)$rxB['text']);
                    }
                    $rxNewRow[] = $rxB;
                }
                if (!empty($rxNewRow)) $rxInlineRows[] = $rxNewRow;
            }
            unset(
                $kb['keyboard'], $kb['resize_keyboard'], $kb['input_field_placeholder'],
                $kb['one_time_keyboard'], $kb['selective'], $kb['is_persistent']
            );
            $kb['inline_keyboard'] = $rxInlineRows;
        }
    }

    if (isset($kb['keyboard']) && is_array($kb['keyboard']) && method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_makeAdminPanelCallback')) {
        foreach ($kb['keyboard'] as &$rxReplyRow) {
            if (!is_array($rxReplyRow)) continue;
            foreach ($rxReplyRow as &$rxReplyBtn) {
                if (!is_array($rxReplyBtn) || !isset($rxReplyBtn['text'])) continue;
                if (isset($rxReplyBtn['request_contact']) || isset($rxReplyBtn['request_location'])
                    || isset($rxReplyBtn['request_poll']) || isset($rxReplyBtn['request_users'])
                    || isset($rxReplyBtn['request_chat'])) {
                    continue;
                }
                if (!isset($rxReplyBtn['callback_data'])) {
                    $rxReplyBtn['callback_data'] = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_makeAdminPanelCallback((string)$rxReplyBtn['text']);
                }
            }
            unset($rxReplyBtn);
        }
        unset($rxReplyRow);
    }




    $rxStyleTargetKey = null;
    if (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
        $rxStyleTargetKey = 'inline_keyboard';
    } elseif (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
        $rxStyleTargetKey = 'keyboard';
    }

    if ($rxStyleTargetKey !== null) {
        $rxIsUserKb = false;
        static $rxUserKbPrefixes = [
            'prodcutservice','serviceextend','cdpick_','productmonth_','producttime_','productvolume_','categorynames_','helpsection_','paneluserbuy_','locationbuy_','buy_service',
            'quickview_','updateproduct_','subscriptionurl_','removeserviceuser_','transfer_','disorder-','confrimtransfers_','extends_','infocard_qr_',
            'Extra_time_','Extra_volume_','Extra_volumes_','extravolunme','exntedagei','discountvolume-','discounttime-',
            'confirm_pay','confirm_discount','confirm_back','confirmandgetservice','chargehasdiscount','chargenodiscount','rules_accept','acceptrule','nav_back',
            'cart_to_offline','colselist','paymentnotverify','startelegrams',
            'configget_','configpage_',
            'pay_sendreceipt','pay_done','pay_cancel','pay_back','pay_wallet_copy','pay_card_copy','pay_check','sendresidcart-','sbvresume_','sbvcancel_',
            'currency_pick','copy_wallet','copy_amount','copy_memo','paid_submit','invoice_back','mode_external','cancel_hash_input',
            'digitaltron_pay_','digitaltron_submit_','crypto_cancel_','crypto_pay_','crypto_submit_',
            'recheckcrypto','rcc_pick_','rcc_skip_photo','rcc_cancel','rcc_resubmit_',
            'Discount','Add_Balance','backuser','backorder','account','rw_pick_','rw_custom','confirmandgetserviceDiscount','aptdc',
            'ticketnew','ticketopen_','ticketmsg_','ticketclose_','tk_cnt_','tk_album_','tk_cancel','tk_media_yes','tk_media_no','departman_','supporttickets','helpos_',
            'wheel_luck','affiliatesbtn','agentpanel','requestagent','supportbtns','helpbtns','helpmenubtn','myservicesbtn','accountbtn','usertestbtn','extendbtn',
            'my_miniapp_pending','location_','buyback','ticketclose_',
            'gen_random_uname',
            'nmstocksel_','nmstockextend_','nmstockrefund_','nmstockcfg_','nmstocksub_',
            'cv_use_','cv_new',
            'confirmserivce','confirmserdiscount',
        ];
        foreach ($kb[$rxStyleTargetKey] as $rxScanRow) {
            if (!is_array($rxScanRow)) continue;
            foreach ($rxScanRow as $rxScanBtn) {
                if (!is_array($rxScanBtn) || !isset($rxScanBtn['callback_data'])) continue;
                $rxScanCb = (string)$rxScanBtn['callback_data'];
                foreach ($rxUserKbPrefixes as $rxUP) {
                    if (strpos($rxScanCb, $rxUP) === 0) { $rxIsUserKb = true; break 3; }
                }
            }
        }
        foreach ($kb[$rxStyleTargetKey] as &$row) {
            if (!is_array($row)) continue;
            foreach ($row as &$btn) {
                if (!is_array($btn)) continue;
                $rxKeep = !empty($btn['_rxkeep']);
                $rxAp = !empty($btn['_rxap']);
                $rxCk = isset($btn['_rxck']) ? (string) $btn['_rxck'] : '';
                $rxSk = isset($btn['_rxsk']) ? (string) $btn['_rxsk'] : '';
                $rxColorDefaults = false;
                $rxColorKey = rx_button_color_key($btn, $rxColorDefaults);
                unset($btn['_rxkeep'], $btn['_rxap'], $btn['_rxck'], $btn['_rxsk'], $btn['_rxcolor'], $btn['_rxcron']);
                if ($rxAp) { unset($btn['style']); continue; }
                if ($rxPlainAdmin && !$rxKeep && !$rxIsUserKb) { unset($btn['style']); continue; }
                $rxItemStyle = $rxColorKey !== null ? ($_rx_kb_list_styles['items'][$rxColorKey] ?? null) : null;
                if (in_array($rxItemStyle, ['none', 'primary', 'success', 'danger'], true)) {
                    $btn['style'] = $rxItemStyle;
                    continue;
                }
                if ($rxColorDefaults && function_exists('rx_kb_use_defaults') && !rx_kb_use_defaults()) {
                    $btn['style'] = 'none';
                    continue;
                }
                if ($rxSk !== '') {
                    if (!isset($btn['style'])) {
                        $rxSkSt = $_rx_kb_list_styles['features'][$rxSk] ?? $_rx_kb_list_styles['lists'][$rxSk] ?? null;
                        if (!empty($rxSkSt) && $rxSkSt !== 'default') {
                            $btn['style'] = $rxSkSt;
                        } elseif (isset($btn['text']) && function_exists('rx_kb_guess_style_from_text')
                            && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                            $rxSkG = rx_kb_guess_style_from_text((string)$btn['text']);
                            $btn['style'] = ($rxSkG !== null) ? $rxSkG : 'primary';
                        }
                    }
                    continue;
                }
                if (!isset($btn['callback_data'])) {
                    if ($rxIsUserKb && !isset($btn['style'])) {
                        $rxCopyStyle = ($rxCk !== '' ? ($_rx_kb_list_styles['lists']['copy_' . $rxCk] ?? null) : null)
                            ?? $_rx_kb_list_styles['lists']['copy_text_btn'] ?? null;
                        if (!empty($rxCopyStyle) && $rxCopyStyle !== 'default') {
                            $btn['style'] = $rxCopyStyle;
                        } elseif (isset($btn['text']) && function_exists('rx_kb_guess_style_from_text')
                            && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                            $rxG = rx_kb_guess_style_from_text((string)$btn['text']);
                            $btn['style'] = ($rxG !== null) ? $rxG : 'primary';
                        }
                    }
                    continue;
                }
                $cb = (string)$btn['callback_data'];
                if (isset($_rx_admin_plain_cb[$cb]) && !$rxIsUserKb && !$rxKeep) {
                    unset($btn['style']);
                    continue;
                }
                if (isset($btn['style'])) continue;

                if (isset($miscMap[$cb])) {
                    $st = $_rx_kb_list_styles['misc'][$miscMap[$cb]] ?? null;
                    if (!empty($st) && $st !== 'default') { $btn['style'] = $st; continue; }
                }

                if (isset($_rx_kb_list_styles['features'][$cb])) {
                    $st = $_rx_kb_list_styles['features'][$cb];
                    if (!empty($st) && $st !== 'default') { $btn['style'] = $st; continue; }
                }

                $matched = false;
                foreach ($listPrefixMap as $prefix => $listKey) {
                    if (strpos($cb, $prefix) === 0) {
                        $st = $_rx_kb_list_styles['lists'][$listKey] ?? null;
                        if (!empty($st) && $st !== 'default') {
                            $btn['style'] = $st;
                        }
                        $matched = true;
                        break;
                    }
                }

                if (!$matched && !isset($btn['style'])) {
                    $fb = $_rx_kb_list_styles['lists']['fallback_inline'] ?? null;
                    if (!empty($fb) && $fb !== 'default') {
                        $btn['style'] = $fb;
                    }
                }


                if (!isset($btn['style']) && isset($btn['text'])
                    && function_exists('rx_kb_guess_style_from_text')
                    && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                    $rxGuessed = rx_kb_guess_style_from_text((string)$btn['text']);
                    $btn['style'] = ($rxGuessed !== null) ? $rxGuessed : 'primary';
                }
            }
            unset($btn);
        }
        unset($row);
    }

    foreach (['inline_keyboard', 'keyboard'] as $rxCleanKey) {
        if (!isset($kb[$rxCleanKey]) || !is_array($kb[$rxCleanKey])) { continue; }
        foreach ($kb[$rxCleanKey] as &$rxCleanRow) {
            if (!is_array($rxCleanRow)) { continue; }
            foreach ($rxCleanRow as &$rxCleanBtn) {
                if (!is_array($rxCleanBtn)) { continue; }
                if (($rxCleanBtn['style'] ?? null) === 'none') { unset($rxCleanBtn['style']); }
                unset($rxCleanBtn['_rxsk'], $rxCleanBtn['_rxck'], $rxCleanBtn['_rxap'], $rxCleanBtn['_rxkeep'], $rxCleanBtn['_rxcolor'], $rxCleanBtn['_rxcron']);
            }
            unset($rxCleanBtn);
        }
        unset($rxCleanRow);
    }
    unset($rxCleanKey);


    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {

        foreach ($kb['keyboard'] as &$row) {
            if (!is_array($row)) continue;
            foreach ($row as &$btn) {
                if (!is_array($btn)) continue;

                unset($btn['url']);
                unset($btn['switch_inline_query']);
                unset($btn['switch_inline_query_current_chat']);
                unset($btn['login_url']);
                unset($btn['callback_game']);
                unset($btn['pay']);
                unset($btn['copy_text']);
            }
            unset($btn);
        }
        unset($row);
    }

    return $wasString ? json_encode($kb, JSON_UNESCAPED_UNICODE) : $kb;
}

function applyPremiumEmojiToKeyboard($keyboard) {

    $wasString = is_string($keyboard);
    if ($wasString) {
        if ($keyboard === '') { return $keyboard; }
        $kb = json_decode($keyboard, true);
        if (!is_array($kb)) { return $keyboard; }
    } elseif (is_array($keyboard)) {
        $kb = $keyboard;
    } else {
        return $keyboard;
    }
    $map = getPremiumEmojiMap();
    if (empty($map)) {

        return $keyboard;
    }


    $sortedEmojis = array_keys($map);
    $stripPremiumEmoji = function ($text) use ($map, $sortedEmojis) {

        if (!is_string($text) || $text === '') { return null; }
        $bestPos = null; $bestEmoji = null; $bestLen = 0;
        foreach ($sortedEmojis as $emoji) {
            $emojiByteLen = strlen($emoji);
            if ($emojiByteLen === 0) { continue; }
            $pos = strpos($text, $emoji);
            if ($pos === false) { continue; }
            if ($bestPos === null || $pos < $bestPos || ($pos === $bestPos && $emojiByteLen > $bestLen)) {
                $bestPos = $pos; $bestEmoji = $emoji; $bestLen = $emojiByteLen;
            }
        }
        if ($bestEmoji === null) { return null; }
        $after = substr($text, $bestPos + $bestLen);
        $after = preg_replace('/^[\x{FE00}-\x{FE0F}\x{200D}]+/u', '', (string)$after);
        $rest = substr($text, 0, $bestPos) . $after;
        $rest = preg_replace('/\s{2,}/u', ' ', trim((string)$rest));
        return [$rest, $map[$bestEmoji]];
    };

    if (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
        foreach ($kb['inline_keyboard'] as &$row) {
            if (!is_array($row)) { continue; }
            foreach ($row as &$btn) {
                if (!is_array($btn)) { continue; }
                if (isset($btn['icon_custom_emoji_id'])) { continue; }
                if (!isset($btn['text']) || !is_string($btn['text'])) { continue; }
                $hit = $stripPremiumEmoji($btn['text']);
                if ($hit !== null) {
                    $btn['text'] = $hit[0] !== '' ? $hit[0] : ' ';
                    $btn['icon_custom_emoji_id'] = (string)$hit[1];
                }
            }
            unset($btn);
        }
        unset($row);
    }

    
    
    
    
    if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
        $rxReplyEntries = [];
        foreach ($kb['keyboard'] as &$row) {
            if (!is_array($row)) { continue; }
            foreach ($row as &$btn) {
                if (!is_array($btn)) { continue; }
                if (isset($btn['icon_custom_emoji_id'])) { continue; }
                if (!isset($btn['text']) || !is_string($btn['text'])) { continue; }
                $hit = $stripPremiumEmoji($btn['text']);
                if ($hit !== null) {
                    $origText    = $btn['text'];
                    $strippedTxt = $hit[0] !== '' ? $hit[0] : ' ';

                    $rxReplyEntries[$strippedTxt] = $origText;
                    $btn['text']              = $strippedTxt;
                    $btn['icon_custom_emoji_id'] = (string)$hit[1];
                }
            }
            unset($btn);
        }
        unset($row);
        
        if (!empty($rxReplyEntries)) {
            if (!isset($GLOBALS['rx_reply_button_map']) || !is_array($GLOBALS['rx_reply_button_map'])) {
                $GLOBALS['rx_reply_button_map'] = [];
            }
            foreach ($rxReplyEntries as $k => $v) {
                $GLOBALS['rx_reply_button_map'][$k] = $v;
            }
            $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
            $paths = [
                rtrim($base, '/\\') . '/rx_reply_button_map.json',
                rtrim(sys_get_temp_dir(), '/\\') . '/rx_reply_button_map_' . md5($base) . '.json',
            ];
            $rxRbmExisting = [];
            foreach ($paths as $p) {
                if (is_file($p)) {
                    $raw = json_decode((string)@file_get_contents($p), true);
                    if (is_array($raw)) {
                        $rxRbmExisting = array_merge($rxRbmExisting, $raw);
                    }
                }
            }
            $rxRbmMerged = array_merge($rxRbmExisting, $rxReplyEntries);
            $jsonMap = json_encode($rxRbmMerged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($paths as $p) {
                @file_put_contents($p, $jsonMap, LOCK_EX);
            }
        }
    }

    return $wasString ? json_encode($kb, JSON_UNESCAPED_UNICODE) : $kb;
}

function rx_restorePremiumReplyText(string $text): string {
    if ($text === '') { return $text; }
    if (isset($GLOBALS['rx_reply_button_map'][$text]) && is_string($GLOBALS['rx_reply_button_map'][$text])) {
        return (string)$GLOBALS['rx_reply_button_map'][$text];
    }
    $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
    $paths = [
        rtrim($base, '/\\') . '/rx_reply_button_map.json',
        rtrim(sys_get_temp_dir(), '/\\') . '/rx_reply_button_map_' . md5($base) . '.json',
    ];
    foreach ($paths as $p) {
        if (is_file($p)) {
            $map = json_decode((string)@file_get_contents($p), true);
            if (is_array($map) && isset($map[$text])) {
                $GLOBALS['rx_reply_button_map'][$text] = (string)$map[$text];
                return (string)$map[$text];
            }
        }
    }
    return $text;
}

if (!function_exists('rx_getKeyboardDefaultStyles')) {





    function rx_getKeyboardDefaultStyles($section = null) {
        static $cache = null;
        if ($cache === null) {
            $cache = [
                'admin_main' => [
                    'admin_status'      => 'primary',
                    'admin_managepanel' => 'success',
                    'admin_addpanel'    => 'success',
                    'admin_timeprice'   => 'success',
                    'admin_volprice'    => 'success',
                    'admin_users'       => 'primary',
                    'admin_shop'        => 'success',
                    'admin_finance'     => 'success',
                    'admin_support'     => 'primary',
                    'admin_help'        => 'primary',
                    'admin_features'    => 'success',
                    'admin_settings'    => 'success',
                    'admin_invoices'    => 'success',
                    'admin_back'        => 'danger',
                ],
                'admin_settings' => [
                    'set_features'   => 'success',
                    'set_reports'    => 'primary',
                    'set_channel'    => 'primary',
                    'set_webpanel'   => 'success',
                    'set_optimize'   => 'success',
                    'set_text'       => 'primary',
                    'set_adminmgr'   => 'primary',
                    'set_testlimit'  => 'success',
                    'set_agentprice' => 'success',
                    'set_qrsettings' => 'primary',
                    'set_qrbg'       => 'primary',
                    'set_qr_toggle'  => 'success',
                    'set_webhook'    => 'primary',
                    'set_backadmin'  => 'danger',
                    'set_backmenu'   => 'danger',
                ],
                'admin_shop' => [
                    'shop_status'      => 'success',
                    'shop_category'    => 'primary',
                    'shop_products'    => 'primary',
                    'shop_giftadd'     => 'success',
                    'shop_giftdel'     => 'danger',
                    'shop_discountadd' => 'success',
                    'shop_discountdel' => 'danger',
                    'shop_minbulk'     => 'primary',
                    'shop_renewcb'     => 'primary',
                    'shop_backadmin'   => 'danger',
                    'shop_backmenu'    => 'danger',
                ],
                'admin_roles' => [
                    'seller_status'  => 'primary',
                    'seller_users'   => 'success',
                    'seller_back'    => 'danger',
                    'support_users'  => 'success',
                    'support_search' => 'primary',
                    'support_back'   => 'danger',
                ],
                'admin_gateways' => [

                    'cart_title'       => 'primary',
                    'cart_setnum'      => 'success',
                    'cart_delnum'      => 'danger',
                    'cart_support'     => 'primary',
                    'cart_pvmode'      => 'primary',
                    'cart_autoconfirm' => 'success',
                    'cart_cashback'    => 'success',
                    'cart_firstpay'    => 'primary',
                    'cart_min'         => 'primary',
                    'cart_max'         => 'primary',
                    'cart_edu'         => 'primary',
                    'cart_back'        => 'danger',

                    'trnado_name'      => 'primary',
                    'trnado_apikey'    => 'success',
                    'trnado_signingkey' => 'success',
                    'trnado_wallet'    => 'success',
                    'trnado_wage'      => 'primary',
                    'trnado_cashback'  => 'success',
                    'trnado_min'       => 'primary',
                    'trnado_max'       => 'primary',
                    'trnado_edu'       => 'primary',
                    'trnado_back'      => 'danger',

                    'zpal_name'        => 'primary',
                    'zpal_merchant'    => 'success',
                    'zpal_cashback'    => 'success',
                    'zpal_min'         => 'primary',
                    'zpal_max'         => 'primary',
                    'zpal_edu'         => 'primary',
                    'zpal_back'        => 'danger',

                    'zpey_name'        => 'primary',
                    'zpey_token'       => 'success',
                    'zpey_cashback'    => 'success',
                    'zpey_tutorial'    => 'primary',
                    'zpey_min'         => 'primary',
                    'zpey_max'         => 'primary',
                    'zpey_edu'         => 'primary',
                    'zpey_back'        => 'danger',

                    'aqaye_name'       => 'primary',
                    'aqaye_merchant'   => 'success',
                    'aqaye_cashback'   => 'success',
                    'aqaye_min'        => 'primary',
                    'aqaye_max'        => 'primary',
                    'aqaye_edu'        => 'primary',
                    'aqaye_back'       => 'danger',

                    'plisio_name'      => 'primary',
                    'plisio_api'       => 'success',
                    'plisio_cashback'  => 'success',
                    'plisio_min'       => 'primary',
                    'plisio_max'       => 'primary',
                    'plisio_edu'       => 'primary',
                    'plisio_back'      => 'danger',

                    'tonpay_name'      => 'primary',
                    'rialit_name'      => 'primary',
                    'tetraminator_name' => 'primary',
                    'cubepay_name'     => 'primary',
                    'blupal_name'      => 'primary',
                    'variza_name'      => 'primary',
                    'abangateway_name' => 'primary',
                    'atlaspay_name'    => 'primary',
                ],
                'admin_features' => [
                    'feat_info'     => 'success',
                    'feat_test'     => 'success',
                    'feat_help'     => 'success',
                    'feat_back'     => 'danger',
                    'feat_backmenu' => 'danger',
                ],
                'admin_channel' => [
                    'ch_add'      => 'success',
                    'ch_del'      => 'danger',
                    'ch_back'     => 'danger',
                    'ch_backmenu' => 'danger',
                ],
                'admin_help' => [
                    'help_add'      => 'success',
                    'help_del'      => 'danger',
                    'help_edit'     => 'primary',
                    'help_back'     => 'danger',
                    'help_backmenu' => 'danger',
                ],
                'admin_category' => [
                    'cat_add'  => 'success',
                    'cat_del'  => 'danger',
                    'cat_edit' => 'primary',
                    'cat_back' => 'danger',
                ],
                'admin_products' => [
                    'shopitem_add'      => 'success',
                    'shopitem_del'      => 'danger',
                    'shopitem_edit'     => 'primary',
                    'shopitem_priceinc' => 'success',
                    'shopitem_pricedec' => 'danger',
                    'shopitem_back'     => 'danger',
                ],
                'admin_product_edit' => [
                    'قیمت'                  => 'success',
                    'حجم'                   => 'success',
                    'زمان'                  => 'success',
                    'نام محصول'             => 'primary',
                    'نوع کاربری'            => 'primary',
                    'نوع ریست حجم'          => 'primary',
                    'یادداشت'               => 'primary',
                    'موقعیت محصول'          => 'primary',
                    'دسته بندی'             => 'primary',
                    '🎛 تنظیم اینباند'      => 'success',
                    'نمایش برای خرید اول'   => 'success',
                    'مخفی کردن پنل'         => 'primary',
                    'حذف کلی پنل های مخفی'  => 'danger',
                    'backadmin'             => 'danger',
                    'backmenu'              => 'danger',
                ],
                'admin_stats' => [
                    'today_stat'         => 'primary',
                    'yesterday_stat'     => 'primary',
                    'hoursago_stat'      => 'primary',
                    'view_stat_time'     => 'primary',
                    'month_current_stat' => 'success',
                    'month_old_stat'     => 'success',
                    'stat_all_bot'       => 'success',
                    'status_var'         => 'primary',
                    'showprice'          => 'success',
                ],
                'admin_cart_advanced' => [
                    'cart_autocheck'   => 'success',
                    'cart_autotime'    => 'primary',
                    'cart_except_user' => 'primary',
                    'cart_export_num'  => 'primary',
                    'cart_show_num'    => 'success',
                    'cart_hide_num'    => 'danger',
                    'cart_group_num'   => 'primary',
                    'checkpay'         => 'success',
                    'paydirect'        => 'success',
                ],
                'admin_search' => [
                    'searchorder'   => 'primary',
                    'searchservice' => 'primary',
                    'searchuser'    => 'primary',
                    'selectname'    => 'success',
                ],
                'admin_user_lists' => [
                    'alllistusers'    => 'primary',
                    'agentlistusers'  => 'primary',
                    'balanceuserlist' => 'success',
                    'cartuserlist'    => 'success',
                    'adminlist'       => 'primary',
                    'listrefral'      => 'primary',
                    'zerobalance'     => 'danger',
                    'balanceaddall'   => 'success',
                ],
                'features_bot' => [
                    'subject'             => 'primary',
                    'subjectde'           => 'primary',
                    'statusbot'           => 'success',
                    'stautsrolee'         => 'primary',
                    'Authenticationphone' => 'success',
                    'Authenticationiran'  => 'success',
                    'verify'              => 'success',
                    'verifybyuser'        => 'success',
                    'inlinebtnmain'       => 'success',
                ],
                'features_users' => [
                    'usernamebtn'       => 'success',
                    'statusnewuser'     => 'success',
                    'statussupportpv'   => 'success',
                    'statusnamecustom'  => 'success',
                    'statusnamecustomf' => 'success',
                ],
                'features_shop' => [
                    'bulkbuy'             => 'success',
                    'btn_status_category' => 'success',
                    'keyconfig'           => 'success',
                    'copycart'            => 'success',
                    'Debtsettlement'      => 'success',
                    'changeloc'           => 'success',
                    'changeloclimit'      => 'primary',
                    'infocard_status'     => 'success',
                    'infocard_color_menu' => 'primary',
                ],
                'features_lottery' => [
                    'wheel_luck'         => 'success',
                    'gradonhshans'       => 'primary',
                    'wheelagentfirst'    => 'success',
                    'wheelagent'         => 'success',
                    'score'              => 'success',
                    'scoresetting'       => 'primary',
                    'Lotteryagent'       => 'success',
                    'affiliatesstatus'   => 'success',
                    'settingaffiliatesf' => 'primary',
                    'Dice'               => 'success',
                ],
                'features_crons' => [
                    'cronday'                 => 'success',
                    'settimecornday'          => 'primary',
                    'on_hold'                 => 'success',
                    'setting_on_holdcron'     => 'primary',
                    'cronvolume'              => 'success',
                    'settimecornvolume'       => 'primary',
                    'notifremove'             => 'danger',
                    'settimecornremove'       => 'primary',
                    'notifremove_volume'      => 'danger',
                    'settimecornremovevolume' => 'primary',
                    'cronjobs_settings'       => 'success',
                ],
                'features_antispam' => [
                    'antispam_toggle'      => 'success',
                    'antispam_set_count'   => 'primary',
                    'antispam_set_seconds' => 'primary',
                    'antispam_set_mute'    => 'primary',
                ],
                'features_nav' => [
                    'featcat_bot'            => 'success',
                    'featcat_users'          => 'success',
                    'featcat_shop'           => 'success',
                    'featcat_lottery'        => 'success',
                    'featcat_crons'          => 'success',
                    'featcat_antispam'       => 'success',
                    'premium_emoji_settings' => 'primary',
                    'featcat_main'           => 'danger',
                    'close_stat'             => 'danger',
                ],
                'admin_pagination' => [
                    'next_page'                => 'primary',
                    'previous_page'            => 'primary',
                    'next_page_extends'        => 'primary',
                    'previous_page_extends'    => 'primary',
                    'next_pageuser'            => 'primary',
                    'previous_pageuser'        => 'primary',
                    'next_pageuserbalance'     => 'primary',
                    'previous_pageuserbalance' => 'primary',
                    'next_pageusercart'        => 'primary',
                    'previous_pageusercart'    => 'primary',
                    'next_pageuserrefral'      => 'primary',
                    'previous_pageuserrefral'  => 'primary',
                    'next_pageuserzero'        => 'primary',
                    'previous_pageuserzero'    => 'primary',
                ],
                'admin_filters' => [
                    'typecustomer_all'         => 'primary',
                    'typecustomer_customer'    => 'success',
                    'typecustomer_notcustomer' => 'danger',
                    'typebalanceall_all'       => 'primary',
                    'typebalanceall_f'         => 'success',
                    'typebalanceall_n2'        => 'success',
                    'typebalanceall_nl'        => 'success',
                    'typeaddprice_percent'     => 'primary',
                    'typeaddprice_static'      => 'primary',
                    'typeagenteditproduct_f'   => 'success',
                    'typeagenteditproduct_n'   => 'success',
                    'typeagenteditproduct_n2'  => 'success',
                    'discounttype_all'         => 'primary',
                    'discounttype_buy'         => 'success',
                    'discounttype_extend'      => 'success',
                    'discountlimitbuy_0'       => 'primary',
                    'discountlimitbuy_1'       => 'success',
                    'typegift_day'             => 'success',
                    'typegift_volume'          => 'success',
                    'voloume_or_day_all'       => 'primary',
                    'agenttypshowlist_all'     => 'primary',
                    'agenttypshowlist_n'       => 'success',
                    'agenttypshowlist_n2'      => 'success',
                ],
                'admin_node' => [
                    'namenode'       => 'primary',
                    'changenamenode' => 'primary',
                    'changeipnode'   => 'primary',
                    'addiplogin'     => 'success',
                    'iploginset'     => 'primary',
                    'removenode'     => 'danger',
                    'reconnectnode'  => 'success',
                    'bakcnode'       => 'danger',
                    'actionnode'     => 'success',
                ],
                'admin_gateway_extra' => [
                    'cartsetting'            => 'primary',
                    'carttocart'             => 'success',
                    'aqayepardakhtsetting'   => 'primary',
                    'zarinpalsetting'        => 'primary',
                    'zarinpeysetting'        => 'primary',
                    'plisiosetting'          => 'primary',
                    'nowpaymentsetting'      => 'primary',
                    'iranpay2setting'        => 'primary',
                    'iranpay3setting'        => 'primary',
                    'affilnecurrency'        => 'success',
                    'affilnecurrencysetting' => 'primary',
                    'arzireyali2'            => 'success',
                    'oniranpay3'             => 'success',
                ],
                'admin_crypto' => [
                    'cryptowallet_TON'        => 'primary',
                    'cryptowallet_TRX'        => 'success',
                    'cryptowallet_USDT_TON'   => 'primary',
                    'cryptowallet_USDT_TRC20' => 'success',
                    'walletaddress'           => 'success',
                    'cryptomemo_yes_TON'      => 'success',
                    'cryptomemo_no_TON'       => 'danger',
                    'cryptomemo_yes_USDT_TON' => 'success',
                    'cryptomemo_no_USDT_TON'  => 'danger',
                ],
                'admin_iplogin' => [
                    'addiplogin'       => 'success',
                    'iploginunlim_on'  => 'success',
                    'iploginunlim_off' => 'danger',
                    'iploginset'       => 'primary',
                ],
                'admin_infocard' => [
                    'infocard_setcolor_red'    => 'danger',
                    'infocard_setcolor_green'  => 'success',
                    'infocard_setcolor_blue'   => 'primary',
                    'infocard_setcolor_orange' => 'primary',
                    'infocard_setcolor_purple' => 'primary',
                    'infocard_setcolor_yellow' => 'primary',
                ],
                'admin_discount_settings' => [
                    'discountextend'     => 'success',
                    'startgift'          => 'success',
                    'get_gift_start'     => 'success',
                    'statuscategorytime' => 'primary',
                    'statustimeextra'    => 'primary',
                ],
                'admin_misc_actions' => [
                    'addnewadmin'             => 'success',
                    'customsellvolume'        => 'primary',
                    'changecoefficient'       => 'primary',
                    'changgestatus'           => 'primary',
                    'categroygenral'          => 'primary',
                    'changenote'              => 'primary',
                    'optimizebot'             => 'success',
                    'removeresid'             => 'danger',
                    'productcheckdata'        => 'primary',
                    'mainbalanceaccount'      => 'success',
                    'maxbalanceaccount'       => 'primary',
                    'kharidanbuh'             => 'success',
                    'systemsms'               => 'primary',
                    'fqQuestions'             => 'primary',
                    'disorderss'              => 'primary',
                    'reasetchangeloc'         => 'danger',
                    'serviceextendselect_pre' => 'primary',
                    'removeservicebackbtn'    => 'danger',
                    'startelegram'            => 'success',
                ],
                'admin_premium_stock' => [
                    'premium_emoji_add'  => 'success',
                    'premium_emoji_noop' => 'primary',
                    'antispam_noop'      => 'primary',
                ],
                'recheckcrypto_admin_buttons' => [
                    'confirmcryptomanual' => 'success',
                    'rejectcryptomanual'  => 'danger',
                    'cmauto'              => 'success',
                    'cmmanual'            => 'primary',
                    'cmback'              => 'danger',
                ],
                'admin_lists' => [
                    'panel_list'            => 'primary',
                    'paneluser_list'        => 'primary',
                    'usertest_list'         => 'primary',
                    'changeloc_list'        => 'primary',
                    'product_list'          => 'primary',
                    'user_services_list'    => 'primary',
                    'discount_list'         => 'primary',
                    'inbound_list'          => 'primary',
                    'help_list'             => 'primary',
                    'channel_list'          => 'primary',
                    'card_list'             => 'primary',
                    'protocol_list'         => 'primary',
                    'category_list'         => 'primary',
                    'user_list'             => 'primary',
                    'agent_list'            => 'primary',
                    'feature_toggle'        => 'success',
                    'support_response'      => 'primary',
                    'extra_purchase'        => 'success',
                    'btnmsg_settings'       => 'primary',
                    'user_confirms'         => 'success',
                    'admin_removes'         => 'danger',
                    'crypto_actions'        => 'success',
                    'service_actions'       => 'primary',
                    'pagination_btns'       => 'primary',
                    'broadcast_actions'     => 'success',
                    'affiliate_actions'     => 'primary',
                    'shop_edit_actions'     => 'primary',
                    'node_actions'          => 'primary',
                    'admin_iplogin_dyn'     => 'danger',
                    'crypto_manual_actions' => 'success',


                ],
                'service' => [
                    'updateinfo'       => 'primary',
                    'config'           => 'success',
                    'linksub'          => 'primary',
                    'extend'           => 'success',
                    'Extra_volume'     => 'success',
                    'Extra_time'       => 'success',
                    'changestatus'     => 'primary',
                    'change-location'  => 'primary',
                    'transfor'         => 'primary',
                    'ekhtelal'         => 'primary',
                    'removeservice'    => 'danger',
                    'changelink'       => 'primary',
                    'changenameconfig' => 'primary',
                    'backorder'        => 'danger',
                    'discountextend'   => 'success',
                    'productcheckdata' => 'primary',
                    'config_header'    => 'primary',
                ],
                'config_list' => [
                    'cfg_download_one'    => 'primary',
                    'cfg_download_all'    => 'primary',
                    'cfg_name_cell'       => 'primary',
                    'cfg_protocol_cell'   => 'primary',
                    'cfg_header_action'   => 'primary',
                    'cfg_header_protocol' => 'primary',
                    'cfg_header_name'     => 'primary',
                    'cfg_page_prev'       => 'primary',
                    'cfg_page_next'       => 'primary',
                    'cfg_page_indicator'  => 'primary',
                    'cfg_back_service'    => 'primary',
                ],
                'account' => [
                    'Discount'             => 'success',
                    'Add_Balance'          => 'success',
                    'TransferBalance'      => 'primary',
                    'MyTransactions'       => 'primary',
                    'transferbal_confirm'  => 'success',
                    'transferbal_cancel'   => 'danger',
                    'backuser'             => 'danger',
                    'rw_pick'              => 'primary',
                    'rw_custom'            => 'primary',
                ],
                'tx_stats' => [
                    'tx_24h'      => 'primary',
                    'tx_3d'       => 'primary',
                    'tx_7d'       => 'primary',
                    'tx_30d'      => 'primary',
                    'tx_prev'     => 'primary',
                    'tx_next'     => 'primary',
                    'tx_back'     => 'danger',
                ],
                'wallet_checkout' => [
                    'ck_use_wallet'   => 'success',
                    'ck_pay_external' => 'primary',
                    'ck_confirm'      => 'success',
                    'ck_cancel'       => 'danger',
                    'ck_change'       => 'primary',
                ],
                'payment' => [
                    'cart_to_offline'  => 'success',
                    'aqayepardakht'    => 'success',
                    'zarinpal'         => 'success',
                    'zarinpey'         => 'success',
                    'iranpay1'         => 'success',
                    'iranpay2'         => 'success',
                    'iranpay3'         => 'success',
                    'paymentnotverify' => 'success',
                    'plisio'           => 'primary',
                    'nowpayment'       => 'primary',
                    'digitaltron'      => 'primary',
                    'startelegrams'    => 'primary',
                    'rialit'           => 'success',
                    'tetraminator'     => 'success',
                    'piroozpay'        => 'primary',
                    'aptdc'            => 'success',
                    'chargenodiscount' => 'primary',
                    'chargehasdiscount'=> 'success',
                    'colselist'        => 'danger',
                ],
                'user_nav' => [
                    'confirm_pay'      => 'success',
                    'confirm_discount' => 'success',
                    'confirm_back'     => 'danger',
                    'rules_accept'     => 'success',
                    'nav_back'         => 'danger',
                    'contact_phone'    => 'success',
                    'contact_back'     => 'danger',
                    'confirmandgetserviceDiscount' => 'success',
                    'tk_cancel'        => 'danger',
                    'tk_media_yes'     => 'success',
                    'tk_media_no'      => 'primary',
                    'confirmchannel'   => 'success',
                ],
                'pay_receipt' => [
                    'pay_sendreceipt' => 'success',
                    'pay_done'        => 'success',
                    'pay_cancel'      => 'danger',
                    'pay_resume'      => 'primary',
                    'pay_back'        => 'danger',
                    'pay_wallet_copy' => 'primary',
                    'pay_card_copy'   => 'primary',
                    'pay_check'       => 'primary',
                ],
                'user_subscription' => [
                    'buy_service'     => 'success',
                    'helpbtn'         => 'primary',
                    'support'         => 'primary',
                    'Status'          => 'primary',
                    'LastTraffic'     => 'primary',
                    'usedtraffic'     => 'primary',
                    'RemainingVolume' => 'primary',
                    'expirationDate'  => 'primary',
                    'daysleft'        => 'primary',
                    'extravolunme'    => 'success',
                    'exntedagei'      => 'success',
                    'Responseuser'    => 'primary',
                    'requestagent'    => 'success',
                    'iduser'          => 'primary',
                    'username'        => 'primary',
                    'notusernameme'   => 'primary',
                    'ticketnew'       => 'success',
                    'supporttickets'  => 'primary',
                ],
                'invoice_copy_buttons' => [
                    'currency_pick' => 'primary',
                    'mode_external' => 'primary',
                    'mode_iranian'  => 'primary',
                    'copy_wallet'   => 'primary',
                    'copy_amount'   => 'primary',
                    'copy_memo'     => 'primary',
                    'paid_submit'   => 'success',
                    'invoice_back'  => 'danger',
                    'cancel_hash_input' => 'danger',
                ],
                'recheckcrypto_buttons' => [
                    'recheckcrypto'        => 'success',
                    'rcc_pick_TRX'         => 'primary',
                    'rcc_pick_TON'         => 'primary',
                    'rcc_pick_USDT_TRC20'  => 'primary',
                    'rcc_pick_USDT_TON'    => 'primary',
                    'rcc_skip_photo'       => 'primary',
                    'rcc_cancel'           => 'danger',
                ],
                'user_dynamic_lists' => [
                    'product_buy'      => 'primary',
                    'category_buy'     => 'primary',
                    'time_buy'         => 'primary',
                    'volume_buy'       => 'primary',
                    'helpsection'      => 'primary',
                    'channel_join'     => 'success',
                    'panel_buy'        => 'primary',
                    'panel_back'       => 'danger',
                    'product_back'     => 'danger',
                    'category_back'    => 'danger',
                    'custom_volume'    => 'success',
                    'service_actions'       => 'primary',
                    'user_services_list'    => 'primary',
                    'paneluser_list'        => 'primary',
                    'crypto_actions'        => 'primary',
                    'crypto_manual_actions' => 'primary',
                    'user_confirms'         => 'success',
                    'extra_purchase'        => 'success',
                    'ticket_list'           => 'primary',
                    'copy_card_num'         => 'primary',
                    'copy_card_amount'      => 'success',
                    'copy_text_btn'         => 'primary',
                    'auto_inline_btn'  => 'primary',
                ],
                'home_menu' => [
                    'home_buy'         => 'success',
                    'home_myservices'  => 'primary',
                    'home_account'     => 'success',
                    'home_tariff'      => 'primary',
                    'wheel_luck'    => 'success',
                    'affiliatesbtn' => 'success',
                    'extendbtn'     => 'success',
                    'supportbtns'   => 'primary',
                    'helpbtns'      => 'primary',
                    'usertestbtn'   => 'success',
                    'agentpanel'    => 'primary',
                    'requestagent'  => 'success',
                ],
                'cron_notifications' => [
                    'cron_extend'         => 'success',
                    'cron_manage_user'    => 'primary',
                    'cron_manage_panel'   => 'primary',
                    'cron_action_btn'     => 'primary',
                    'cron_buy_service'    => 'success',
                    'cron_start_bot'      => 'success',
                    'cron_usertest'       => 'success',
                    'cron_help'           => 'primary',
                    'cron_affiliates'     => 'success',
                    'cron_addbalance'     => 'success',
                ],
                'misc_buttons' => [
                    'adm_backmenu'                 => 'danger',
                    'adm_backadmin'                => 'danger',
                    'hide_mini_app'                => 'danger',
                    'backproductadmin'             => 'danger',
                    'backadmin'                    => 'danger',
                    'backlistuser'                 => 'danger',
                    'buyback'                      => 'danger',
                    'cancel_gift'                  => 'danger',
                    'cancel_hash_input'            => 'danger',
                    'cancel_sendmessage'           => 'danger',
                    'close_listusers'              => 'danger',
                    'close_stat'                   => 'danger',
                    'cronjobs_back_settings'       => 'danger',
                    'resetbot_cancel'              => 'danger',
                    'resetbot_confirm'             => 'success',
                    'broadcast_status_refresh'     => 'primary',
                    'reject'                       => 'danger',
                    'accept'                       => 'success',
                    'acceptrule'                   => 'success',
                    'confirmpaid'                  => 'success',
                    'confirmandgetservice'         => 'success',
                    'confirmandgetserviceDiscount' => 'success',
                    'confirmchannel'               => 'success',
                    'confirmserdiscount'           => 'success',
                    'confirmserivce'               => 'success',
                    'agentpanel'                   => 'primary',
                    'cronjob_display'              => 'primary',
                    'startaction'                  => 'success',
                    'locationedit_all'             => 'primary',
                    'locationmessage_all'          => 'primary',


                    'backuser'                     => 'danger',
                    'nav_back'                     => 'danger',
                    'backorder'                    => 'danger',
                    'colselist'                    => 'danger',
                    'supportbtns'                  => 'primary',
                    'helpbtns'                     => 'primary',
                    'usertestbtn'                  => 'success',
                    'inert_label'                  => 'primary',
                ],
            ];










            $rxPanelReplyCommon = [
                "⚙️ وضعیت قابلیت ها پنل"            => 'success',
                "✍️ نام پنل"                          => 'primary',
                "❌ حذف پنل"                          => 'danger',
                "🔐 ویرایش رمز عبور"                  => 'primary',
                "👤 ویرایش نام"                => 'primary',
                "🔗 ویرایش آدرس پنل"                  => 'primary',
                "🔋 روش تمدید سرویس"                  => 'success',
                "💡 ساخت نام کاربری"              => 'primary',
                "🚨 محدودیت اکانت"               => 'success',
                "📍 تغییر گروه"                => 'primary',
                "🧪 تنظیمات تست"                     => 'success',
                "🌍 قیمت تغییر مکان"                => 'success',
                "➕ قیمت حجم اضافه"                   => 'success',
                "⏳ قیمت زمان اضافه"                  => 'success',
                "⚙️ قیمت حجم دلخواه"          => 'success',
                "⏳ قیمت زمان دلخواه"                 => 'success',
                "📍 کف حجم دلخواه"                 => 'primary',
                "📍 سقف حجم دلخواه"                => 'primary',
                "📍 کف زمان دلخواه"                => 'primary',
                "📍 سقف زمان دلخواه"               => 'primary',
                "🌐 وضعیت نت ملی"                    => 'success',
                "🫣 مخفی پنل برای کاربر"      => 'primary',
                "❌ حذف از لیست مخفی"    => 'danger',
                "⚙️  اینباند اکانت غیرفعال"          => 'primary',
            ];

            $cache['admin_panel_marzban'] = $rxPanelReplyCommon + [
                "⚙️ پروتکل اینباند" => 'success',
            ];
            $cache['admin_panel_guard'] = $rxPanelReplyCommon + [
                "🔐 ویرایش کلید"                => 'primary',
                "⁉️ اتصال به پنل"       => 'primary',
                "⚙️ تنظیم سرویس ها"            => 'success',
            ];
            $cache['admin_panel_pasarguard'] = $rxPanelReplyCommon + [
                "🔐 ویرایش کلید"                => 'primary',
                "⁉️ اتصال به پنل"       => 'primary',
                "⚙️ پروتکل اینباند" => 'success',
            ];
            $cache['admin_panel_wg'] = $rxPanelReplyCommon + [
                "💎 شناسه اینباند" => 'success',
            ];
            $cache['admin_panel_manualsale'] = $rxPanelReplyCommon + [
                "➕ افزودن کانفیگ" => 'success',
                "❌ حذف کانفیگ "       => 'danger',
                "✏️ ویرایش کانفیگ"    => 'primary',
            ];
            $cache['admin_panel_x_ui_single'] = $rxPanelReplyCommon + [
                "💎 شناسه اینباند" => 'success',
                '🔗 دامنه لینک ساب'      => 'primary',
            ];
            $cache['admin_panel_athmarzban'] = [
                "🔧 کانفیگ دستی" => 'primary',
                "🖥 مدیریت نود ها"     => 'success',
            ];
            $cache['admin_panel_athx_ui'] = [
                "🔧 کانفیگ دستی" => 'primary',
            ];
        }
        if ($section === null) {
            return $cache;
        }
        return isset($cache[$section]) && is_array($cache[$section]) ? $cache[$section] : [];
    }
}

if (!function_exists('rx_kb_guess_style_from_text')) {
    /**
     * Smart heuristic that picks a button style by inspecting the button's
     * visible text + leading emoji.
     *
     *  - success (green)  →  ✅/✔/🟢/☑/➕/👍   OR keywords like
     *                       تایید / موفق / ذخیره / افزایش / افزودن / ساخت /
     *                       ایجاد / فعال / روشن / می‌پذیرم / شروع / تمدید /
     *                       شارژ / خرید / پرداخت کردم / انجام دادم …
     *
     *  - danger  (red)    →  ❌/✖/🚫/🗑/⛔/🔴/🔙/◀/⬅   OR keywords like
     *                       ناموفق / حذف / لغو / انصراف / مسدود / بستن /
     *                       خاموش / متوقف / خروج / جعلی / بازگشت …
     *
     *  - primary (blue)   →  🔵/🛡/📊/⚙/🔧/ℹ/📋/📈/📉/⏱/⏰   OR keywords like
     *                       تنظیم / مدیریت / آمار / گزارش / اطلاعات /
     *                       جستجو / مشاهده / نمایش / لیست / وضعیت / ویرایش
     *
     *  - null              →  nothing matched (button stays uncoloured).
     *
     * Priority: success → danger → primary → null. First match wins so a
     * button that contains both ❌ and a success word will pick success
     * first (which is intentional — explicit ✅ on a button usually
     * overrides everything else).
     *
     * Used as a fallback when the user has not explicitly chosen a colour
     * in the web panel (i.e. picked "default") or when the button isn't
     * mapped through misc/features/prefix lookups.
     */
    function rx_kb_guess_style_from_text(string $text): ?string {
        $t = trim($text);
        if ($t === '') return null;

        static $rules = null;
        if ($rules === null) {
            $rules = [
                'success' => [

                    '✅', '✔', '🟢', '☑', '🆗', '👍', '➕', '🎉', '🆕', '🎁', '💎',

                    'تایید', 'تأیید', 'موفق', 'ذخیره',
                    'افزایش', 'افزودن', 'افزود', 'اضافه',
                    'ساخت', 'ساختن', 'ایجاد',
                    'فعال‌سازی', 'فعال سازی', 'فعال‌',
                    'روشن',
                    'می‌پذیرم', 'می پذیرم', 'بپذیر', 'پذیرفت',
                    'شروع',
                    'تمدید',
                    'شارژ',
                    'خرید',
                    'پرداخت کردم', 'پرداخت و',
                    'انجام دادم',
                    'دریافت هدیه', 'دریافت سرویس',
                    'ثبت کد', 'ثبت و',
                    'تایید پرداخت', 'تایید و',
                    'کش‌بک', 'کش بک',
                ],
                'danger' => [

                    '❌', '✖', '🔴', '🚫', '🗑', '⛔', '🛑', '⚠', '🔙', '◀', '⬅', '⬇',

                    'ناموفق',
                    'حذف',
                    'لغو',
                    'انصراف',
                    'مسدود', 'بلاک',
                    'بستن',
                    'خاموش',
                    'متوقف', 'توقف', 'پایان',
                    'خروج', 'اخراج',
                    'جعلی',
                    'بازگشت',
                    'کاهش',
                    'اختلال',
                    'خطا',
                    'هشدار',
                    'منقضی',
                    'محدود',
                ],
                'primary' => [

                    '🔵', '🛡', '📊', '⚙', '🔧', 'ℹ', '📋', '📈', '📉',
                    '⏱', '⏰', '⏳', '🔍', '👁', '🔗', '📡', '📂', '📁', '📑',
                    '📌', '📨', '📥', '📤', '📝', '🖥', '🌐', '🧪', '🎨', '🔄',
                    '♻', '🆔', '🗂', '🏷', '🌍', '🚚', '🎛',

                    'تنظیم', 'تنظیمات',
                    'مدیریت',
                    'آمار',
                    'گزارش',
                    'اطلاعات',
                    'جستجو',
                    'مشاهده', 'نمایش',
                    'لیست',
                    'وضعیت',
                    'ویرایش',
                    'تغییر',
                    'انتخاب',
                    'اعلان', 'اطلاع‌رسانی', 'اطلاع رسانی',
                    'درخواست',
                    'بروزرسانی', 'بروز رسانی',
                    'دریافت',
                    'انتقال',
                    'ارسال',
                    'لینک',
                    'مشخصات',
                    'پنل',
                    'کانال',
                    'راهنما', 'آموزش',
                    'یادداشت',
                    'دسته‌بندی', 'دسته بندی',
                    'محصول',
                    'موقعیت', 'لوکیشن',
                    'اشتراک',
                    'احراز هویت',
                    'کیف‌پول', 'کیف پول',
                ],
            ];
        }

        foreach ($rules as $style => $kws) {
            foreach ($kws as $kw) {
                if (mb_strpos($t, $kw) !== false) return $style;
            }
        }
        return null;
    }
}

if (!function_exists('rx_kb_use_defaults')) {
    /**
     * Whether the bot should merge its built-in default keyboard colours into
     * the user's panel selection.
     *
     * Stored as the special key `_use_defaults` inside the
     * `setting.keyboard_styles_all` JSON column. Default is TRUE (preserves
     * existing behaviour for installs that never touched this).
     *
     * Cached per-request to avoid repeated DB hits.
     */
    function rx_kb_use_defaults(): bool {
        static $cached = null;
        if ($cached !== null) return $cached;
        $cached = true;
        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if ($row && !empty($row['keyboard_styles_all'])) {
                    $all = json_decode($row['keyboard_styles_all'], true);
                    if (is_array($all) && array_key_exists('_use_defaults', $all)) {
                        $cached = (bool) $all['_use_defaults'];
                    }
                }
            }
        } catch (Throwable $e) { /* keep default true */ }
        return $cached;
    }
}

if (!function_exists('rx_mergeKeyboardSectionDefaults')) {



    function rx_mergeKeyboardSectionDefaults(array $userStyles, $section) {
        if (!rx_kb_use_defaults()) {
            return $userStyles;
        }
        $defaults = rx_getKeyboardDefaultStyles($section);
        if (empty($defaults)) {
            return $userStyles;
        }

        return $userStyles + $defaults;
    }
}

function rxPrepareTelegramTextAndKeyboard($chatId, $text, $keyboard, $parseMode): array
{
    $text = applyPremiumEmojiTransform($text, $parseMode);
    $keyboard = processKeyboardStyles($keyboard, function_exists('rx_isAdminChat') && rx_isAdminChat($chatId), $chatId);
    return [$text, applyPremiumEmojiToKeyboard($keyboard)];
}

function sendmessage($chat_id,$text,$keyboard,$parse_mode,$bot_token = null){
    if(intval($chat_id) == 0)return ['ok' => false];
    [$text, $keyboard] = rxPrepareTelegramTextAndKeyboard($chat_id, $text, $keyboard, $parse_mode);
    $rxSendResult = telegram('sendmessage',[
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
        '_rx_already_transformed' => true,
        ],$bot_token);
    return $rxSendResult;
}


if (!function_exists('rx_cron_style')) {
    /**
     * Resolve the style configured under panel section "cron_notifications"
     * for a given key (e.g. cron_buy_service, cron_start_bot, …).
     *
     * Returns the style name (success/primary/danger/...) or null when the
     * user picked "default" (no styling).
     *
     * Use this when building inline keyboards in cronbot/* where the
     * callback_data clashes with non-cron menus, so processKeyboardStyles
     * cannot disambiguate by callback_data alone.
     */
    function rx_cron_style(string $key): ?string {
        static $styles = null;
        if ($styles === null) {
            $styles = [];
            global $pdo;
            try {
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode($row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['cron_notifications']) && is_array($all['cron_notifications'])) {
                            $styles = array_filter($all['cron_notifications'], static function ($v) { return $v !== 'default'; });
                        }
                    }
                }
            } catch (\Throwable $e) {}
            if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                $styles = $styles + rx_getKeyboardDefaultStyles('cron_notifications');
            }
        }
        if (!empty($styles[$key]) && $styles[$key] !== 'default') {
            return $styles[$key];
        }
        return null;
    }
}

if (!function_exists('rx_cron_btn')) {
    /**
     * Apply the panel-configured cron_notifications style to a button array
     * (pass-through if no style configured). Idempotent.
     *
     * Example:
     *   rx_cron_btn('cron_buy_service', ['text' => 'خرید', 'callback_data' => 'buy'])
     */
    function rx_cron_btn(string $cronKey, array $btn): array {
        $btn['_rxkeep'] = 1;
        $btn['_rxcron'] = $cronKey;
        if (isset($btn['style'])) return $btn;
        $st = rx_cron_style($cronKey);
        if ($st !== null) $btn['style'] = $st;
        elseif (function_exists('rx_kb_use_defaults') && !rx_kb_use_defaults()) $btn['style'] = 'none';
        return $btn;
    }
}
function normalizeConfigFilename($filename, $prefix = '', $wireguard = false)
{
    $filename = basename(str_replace('\\', '/', (string) $filename));
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $stem = (string) pathinfo($filename, PATHINFO_FILENAME);
    $stem = $wireguard
        ? preg_replace('/[^A-Za-z0-9.-]+/', '-', str_replace('_', '-', $stem))
        : preg_replace('/[^\p{L}\p{N}.-]+/u', '-', str_replace('_', '-', $stem));
    $stem = trim((string) preg_replace('/-+/', '-', $stem), '-.');
    $prefix = preg_replace('/[^A-Za-z0-9.-]+/', '-', str_replace('_', '-', (string) $prefix));
    $prefix = trim((string) preg_replace('/-+/', '-', $prefix), '-.');
    if (strtolower($prefix) === 'none') {
        $prefix = '';
    }
    if ($stem === '') {
        $stem = $wireguard ? 'wireguard' : 'config';
    }
    if ($prefix !== '' && stripos($stem, $prefix . '-') !== 0 && strcasecmp($stem, $prefix) !== 0) {
        $stem = $prefix . '-' . $stem;
    }
    if ($wireguard) {
        $stem = substr($stem, 0, 15);
        $stem = rtrim($stem, '-.');
        $extension = 'conf';
    }
    if ($extension === '') {
        $extension = $wireguard ? 'conf' : 'bin';
    }
    return $stem . '.' . $extension;
}

function prepareTelegramInputFile($input, $postFilename = null)
{
    if ($input instanceof CURLFile) {
        return $input;
    }

    if (is_string($input)) {
        if (preg_match('/^https?:\/\//i', $input)) {
            return $input;
        }

        $realPath = realpath($input);
        if ($realPath !== false && is_file($realPath) && is_readable($realPath)) {
            if (is_string($postFilename) && $postFilename !== '') {
                return new CURLFile($realPath, 'application/octet-stream', $postFilename);
            }
            return new CURLFile($realPath);
        }

        error_log(sprintf('Telegram document path is not readable: %s', $input));
        return null;
    }

    error_log('Unsupported Telegram input file type: ' . gettype($input));
    return null;
}

function rxMediaResultState($response): string
{
    if (is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState'])) {
        return \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($response);
    }
    if (is_array($response) && ($response['ok'] ?? null) === true) {
        return 'confirmed';
    }
    $code = (int) ($response['error_code'] ?? 0);
    return is_array($response) && empty($response['_rx_invalid_response']) && $code >= 400 && $code < 500 && $code !== 429 ? 'failed' : 'unknown';
}

function rxIsEmptyInlineKeyboard($keyboard): bool
{
    if ($keyboard === null || $keyboard === '') {
        return true;
    }
    $decoded = is_string($keyboard) ? json_decode($keyboard, true) : $keyboard;
    return is_array($decoded) && array_key_exists('inline_keyboard', $decoded) && $decoded['inline_keyboard'] === [];
}

function rxIsKeyboardOnlyCompatible($keyboard): bool
{
    if ($keyboard === null || $keyboard === '') {
        return true;
    }
    $decoded = is_string($keyboard) ? json_decode($keyboard, true) : $keyboard;
    return is_array($decoded) && array_key_exists('inline_keyboard', $decoded) && is_array($decoded['inline_keyboard']);
}

function rxNormalizeVisibleText($text): string
{
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $text = (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text);
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function rxRemoveMessageKeyboard($chat_id, $message_id, $keyboard = null)
{
    $message_id = (int) $message_id;
    if ($message_id <= 0) {
        return ['ok' => false, 'error_code' => 400, 'description' => 'message_id missing'];
    }
    if (is_array($keyboard)) {
        $keyboard = json_encode($keyboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if (!is_string($keyboard) || trim($keyboard) === '') {
        $keyboard = json_encode(['inline_keyboard' => []]);
    }
    $result = telegram('editmessagereplymarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => $keyboard,
    ]);
    if (is_array($result) && empty($result['ok']) && (!empty($result['_rx_validation_failed']) || stripos((string) ($result['description'] ?? ''), 'Cannot preserve rich message blocks') !== false)
        && method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_plainCall')) {
        $result = \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_plainCall('editMessageReplyMarkup', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => $keyboard,
            '_rx_already_transformed' => true,
        ], null);
    }
    return $result;
}

function rxBeginProgressMessage($chat_id, $message_id, string $text): array
{
    $message_id = (int) $message_id;
    $callbackMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
    $targetIsRich = $message_id > 0 && is_array($callbackMessage)
        && (int) ($callbackMessage['message_id'] ?? 0) === $message_id
        && isset($callbackMessage['rich_message']);
    if ($targetIsRich && method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_resolveTarget')) {
        $resolved = \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_resolveTarget($chat_id, $message_id);
        $targetIsRich = !$resolved['deleted'] && !$resolved['redirected'];
    }
    if (!$targetIsRich) {
        Editmessagetext($chat_id, $message_id, $text, null);
        return ['mode' => 'edited', 'message_id' => $message_id];
    }
    $result = sendmessage($chat_id, $text, null, 'HTML');
    $progressId = is_array($result) && !empty($result['ok']) ? (int) ($result['result']['message_id'] ?? 0) : 0;
    return ['mode' => 'separate', 'message_id' => $progressId];
}

function rxEndProgressMessage($chat_id, array $progress)
{
    if (($progress['mode'] ?? '') !== 'separate' || (int) ($progress['message_id'] ?? 0) <= 0) {
        return null;
    }
    return deletemessage($chat_id, (int) $progress['message_id']);
}

function rxMediaCaptionRejected($result): bool
{
    return is_array($result) && ($result['ok'] ?? null) === false && empty($result['_rx_invalid_response'])
        && (int) ($result['error_code'] ?? 0) === 400
        && preg_match("~caption is too long|message is too long|can't parse entities|entity.*(invalid|offset|length)|unsupported start tag|unsupported URL protocol~i", (string) ($result['description'] ?? '')) === 1;
}

function rxSendRequiredMediaText(array $destination, string $caption, $token = null, int $startChunk = 0, ?callable $checkpoint = null): array
{
    try {
        $plain = method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_plainText')
            ? \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($caption, 'HTML', true)
            : trim(html_entity_decode(strip_tags($caption), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    } catch (Throwable $e) {
        return ['state' => 'failed', 'chunks_confirmed' => $startChunk, 'result' => null];
    }
    $chunks = method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_plainChunks') ? \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_plainChunks($plain) : [$plain];
    $payload = array_intersect_key($destination, array_flip(['chat_id', 'message_thread_id', 'disable_notification', 'protect_content']));
    $result = null;
    $state = 'failed';
    $sent = $startChunk;
    $last = count($chunks) - 1;
    if ($last < 0 || trim($plain) === '') {
        if ($checkpoint !== null) {
            $checkpoint('failed', $startChunk);
        }
        return ['state' => 'failed', 'chunks_confirmed' => $startChunk, 'result' => null];
    }
    foreach ($chunks as $index => $chunk) {
        if ($index < $startChunk || trim($chunk) === '') {
            continue;
        }
        if ($checkpoint !== null && !$checkpoint('unknown', $index)) {
            $state = 'unknown';
            break;
        }
        $payload['text'] = $chunk;
        $payload['_rx_already_transformed'] = true;
        $payload['_rx_no_network_retry'] = true;
        if ($index === $last && isset($destination['reply_markup'])) {
            $payload['reply_markup'] = $destination['reply_markup'];
            unset($payload['_rx_already_transformed']);
        }
        try {
            $result = method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_plainCall')
                ? \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_plainCall('sendmessage', $payload, $token)
                : telegram('sendmessage', $payload, $token);
            $state = rxMediaResultState($result);
        } catch (Throwable $e) {
            $state = 'unknown';
        }
        if ($state === 'confirmed') {
            $sent = $index + 1;
        }
        if ($checkpoint !== null && !$checkpoint($state === 'confirmed' && $index !== $last ? 'not_attempted' : $state, $sent)) {
            $state = 'unknown';
            break;
        }
        if ($state !== 'confirmed') {
            break;
        }
    }
    return ['state' => $state, 'chunks_confirmed' => $sent, 'result' => $result];
}

function rxSendRichMediaText(array $destination, string $caption, $token = null): array
{
    $extra = array_intersect_key($destination, array_flip(['message_thread_id', 'disable_notification', 'protect_content']));
    if (!function_exists('sendmessage') || $extra || !isset($destination['chat_id'])) {
        return rxSendRequiredMediaText($destination, $caption, $token);
    }
    try {
        $result = sendmessage($destination['chat_id'], $caption, $destination['reply_markup'] ?? null, 'HTML', $token);
        $state = rxMediaResultState($result);
    } catch (Throwable $e) {
        return ['state' => 'unknown', 'chunks_confirmed' => 0, 'result' => null];
    }
    $chunks = is_array($result) ? (int) ($result['_rx_confirmed_text_chunks'] ?? 0) : 0;
    if ($state === 'confirmed') {
        return ['state' => 'confirmed', 'chunks_confirmed' => max(1, $chunks), 'result' => $result];
    }
    if ($chunks === 0 && $state === 'failed' && is_array($result)
        && (rxMediaCaptionRejected($result) || !empty($result['_rx_validation_failed']) || ($result['error_code'] ?? null) === 'RICH_VALIDATION_FAILED')) {
        return rxSendRequiredMediaText($destination, $caption, $token);
    }
    return ['state' => $state, 'chunks_confirmed' => $chunks, 'result' => $result];
}

function rxSendMediaWithCaption(string $method, array $payload, $token = null, ?array &$delivery = null, bool $captionOnMediaFailure = false)
{
    $caption = (string) ($payload['caption'] ?? '');
    $required = trim($caption) !== '';
    $prepared = method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxPrepareSafeMediaCaption') ? \Faoxima\Services\Delivery\ServiceDelivery::rxPrepareSafeMediaCaption($caption) : $caption;
    $captionValid = $prepared !== null;
    $richTextSplitAllowed = !array_key_exists('_rx_rich_split', $payload) || $payload['_rx_rich_split'] !== false;
    unset($payload['_rx_rich_split']);
    $richTextSplit = $richTextSplitAllowed && $required && function_exists('sendmessage') && preg_match('~<table\b~i', $caption) === 1;
    $splitKeyboard = null;
    if ($richTextSplit) {
        $prepared = null;
        if (isset($payload['reply_markup']) && $payload['reply_markup'] !== null && $payload['reply_markup'] !== '') {
            $splitKeyboard = $payload['reply_markup'];
            unset($payload['reply_markup']);
        }
    }
    $sendMedia = static function (array $data) use ($method, $token) {
        try {
            return method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_plainCall')
                ? \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_plainCall($method, $data, $token)
                : telegram($method, $data, $token);
        } catch (Throwable $e) {
            return ['ok' => false, 'description' => 'Telegram media delivery outcome is unknown'];
        }
    };
    $payload['caption'] = $prepared ?? '';
    $payload['_rx_no_network_retry'] = true;
    $result = $sendMedia($payload);
    if ($required && $prepared !== null && rxMediaCaptionRejected($result)) {
        $prepared = null;
        $payload['caption'] = '';
        unset($payload['caption_entities']);
        $result = $sendMedia($payload);
    }
    $mediaState = rxMediaResultState($result);
    $split = $required && ($prepared === null || ($captionOnMediaFailure && $mediaState === 'failed'));
    $text = ['state' => $required ? $mediaState : 'skipped', 'result' => null];
    if ($split) {
        $destination = $payload;
        if ($mediaState !== 'failed') {
            unset($destination['reply_markup']);
        }
        if ($splitKeyboard !== null) {
            $destination['reply_markup'] = $splitKeyboard;
        }
        $text = $richTextSplit
            ? rxSendRichMediaText($destination, $caption, $token)
            : rxSendRequiredMediaText($destination, $caption, $token);
        $splitMediaMessageId = is_array($result) ? (int) ($result['result']['message_id'] ?? 0) : 0;
        if ($splitKeyboard !== null && $text['state'] === 'failed' && (int) ($text['chunks_confirmed'] ?? 0) === 0 && $mediaState === 'confirmed' && $splitMediaMessageId > 0) {
            $splitMarkup = is_string($splitKeyboard) ? json_decode($splitKeyboard, true) : $splitKeyboard;
            if (is_array($splitMarkup) && !empty($splitMarkup['inline_keyboard'])) {
                telegram('editMessageReplyMarkup', [
                    'chat_id' => $payload['chat_id'] ?? null,
                    'message_id' => $splitMediaMessageId,
                    'reply_markup' => is_string($splitKeyboard) ? $splitKeyboard : json_encode($splitKeyboard, JSON_UNESCAPED_UNICODE),
                ], $token);
            }
        }
    }
    $components = [
        'media' => ['required' => true, 'state' => $mediaState],
        'text' => ['required' => $required, 'state' => $text['state']],
    ];
    if ($text['state'] !== 'confirmed' && ($text['chunks_confirmed'] ?? 0) > 0) {
        $components['text_chunks'] = ['required' => true, 'state' => 'confirmed'];
    }
    $delivery = is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryComponentContext']) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryComponentContext($components) : [
        'media_state' => $mediaState,
        'text_state' => $text['state'],
        'delivery_status' => in_array('unknown', [$mediaState, $text['state']], true) ? 'unknown'
            : ($mediaState === 'confirmed' ? ($required && $text['state'] !== 'confirmed' ? 'partial' : 'completed')
                : ($text['state'] === 'confirmed' ? 'partial' : 'failed')),
    ];
    $delivery += [
        'media_state' => $mediaState,
        'media_required' => true,
        'text_state' => $text['state'],
        'caption_required' => $required,
        'caption_valid' => $captionValid,
        'caption_rich_text' => $richTextSplit,
        'caption_complete' => !$required || $text['state'] === 'confirmed',
        'caption_delivery_state' => $text['state'],
        'text_chunks_confirmed' => $text['chunks_confirmed'] ?? 0,
        'caption_source' => $split ? 'separate_text' : ($required ? 'media_caption' : 'none'),
        'text_message_result' => $text['result'] ?? null,
    ];
    return $result;
}

function sendDocument($chat_id, $documentPath, $caption, $filename = null, ?array &$delivery = null)
{
    $document = prepareTelegramInputFile($documentPath, $filename);
    if ($document === null) {
        $required = trim((string) $caption) !== '';
        $text = $required ? rxSendRequiredMediaText(['chat_id' => $chat_id], (string) $caption) : ['state' => 'skipped', 'chunks_confirmed' => 0];
        $delivery = [
            'media_state' => 'failed',
            'text_state' => $text['state'],
            'caption_required' => $required,
            'caption_complete' => !$required || $text['state'] === 'confirmed',
            'delivery_status' => $text['state'] === 'unknown' ? 'unknown' : ($text['chunks_confirmed'] > 0 ? 'partial' : 'failed'),
        ];
        return [
            'ok' => false,
            'description' => 'Document could not be prepared for Telegram upload.'
        ];
    }
    return rxSendMediaWithCaption('sendDocument', [
        'chat_id' => $chat_id,
        'document' => $document,
        'caption' => (string) $caption,
        'parse_mode' => 'HTML',
    ], null, $delivery);
}

function forwardMessage($chat_id,$message_id,$chat_id_user){
    return telegram('forwardMessage',[
        'from_chat_id'=> $chat_id,
        'message_id'=> $message_id,
        'chat_id'=> $chat_id_user,
    ]);
}
function sendphoto($chat_id,$photoid,$caption, ?array &$delivery = null){
    return rxSendMediaWithCaption('sendphoto', [
        'chat_id' => $chat_id,
        'photo' => $photoid,
        'caption' => (string) $caption,
        'parse_mode' => 'HTML',
    ], null, $delivery);
}
function sendvideo($chat_id,$videoid,$caption, ?array &$delivery = null){
    return rxSendMediaWithCaption('sendvideo', [
        'chat_id' => $chat_id,
        'video' => $videoid,
        'caption' => (string) $caption,
        'parse_mode' => 'HTML',
    ], null, $delivery);
}
function senddocumentsid($chat_id,$documentid,$caption, ?array &$delivery = null){
    return rxSendMediaWithCaption('sendDocument', [
        'chat_id' => $chat_id,
        'document' => $documentid,
        'caption' => (string) $caption,
        'parse_mode' => 'HTML',
    ], null, $delivery);
}
function Editmessagetext($chat_id, $message_id, $text, $keyboard,$parse_mode = 'HTML', $threadId = null){
    $message_id = (int) $message_id;
    if ($message_id > 0 && method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_resolveTarget')) {
        $rxEditResolved = \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_resolveTarget($chat_id, $message_id);
        if (!$rxEditResolved['deleted'] && $rxEditResolved['redirected']) {
            $message_id = (int) $rxEditResolved['id'];
        }
    }
    global $update;
    $__callbackMsg = (isset($update['callback_query']['message']) && is_array($update['callback_query']['message']))
        ? $update['callback_query']['message'] : null;
    $__callbackMsgId = $__callbackMsg ? (int)($__callbackMsg['message_id'] ?? 0) : 0;
    $__origThreadId = $threadId !== null
        ? (int) $threadId
        : ($__callbackMsg ? (int)($__callbackMsg['message_thread_id'] ?? 0) : 0);
    $__targetIsRich = $__callbackMsg && isset($__callbackMsg['rich_message']);
    $__sendFallback = function ($fbChatId, $fbText, $fbKeyboard, $fbParseMode) use ($__origThreadId) {
        [$fbText, $fbKeyboard] = rxPrepareTelegramTextAndKeyboard($fbChatId, $fbText, $fbKeyboard, $fbParseMode);
        $fbPayload = [
            'chat_id' => $fbChatId,
            'text' => $fbText,
            'reply_markup' => $fbKeyboard,
            'parse_mode' => $fbParseMode,
            '_rx_already_transformed' => true,
        ];
        if ($__origThreadId > 0) {
            $fbPayload['message_thread_id'] = $__origThreadId;
        }
        return telegram('sendmessage', $fbPayload);
    };
    if ($message_id <= 0) {
        $__fallbackResult = $__sendFallback($chat_id, $text, $keyboard, strtolower($parse_mode));
        return $__fallbackResult;
    }
    if ($__targetIsRich && $__callbackMsgId === $message_id && is_string($text) && trim($text) !== ''
        && rxIsKeyboardOnlyCompatible($keyboard)
        && method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_extractText')
        && rxNormalizeVisibleText($text) === rxNormalizeVisibleText(\Faoxima\Telegram\Rich\Markup::rx_richUi_extractText($__callbackMsg['rich_message']))) {
        return rxRemoveMessageKeyboard($chat_id, $message_id, $keyboard);
    }
    [$text, $keyboard] = rxPrepareTelegramTextAndKeyboard($chat_id, $text, $keyboard, $parse_mode);

    $__isPhotoMsg =$__callbackMsg && (
        !empty($__callbackMsg['photo'])
        || !empty($__callbackMsg['video'])
        || !empty($__callbackMsg['animation'])
        || !empty($__callbackMsg['document'])
    );
    if ($__isPhotoMsg && $__callbackMsgId === (int)$message_id) {
        $__fallbackResult = $__sendFallback($chat_id, $text, $keyboard, strtolower($parse_mode));
        $__replacementOk = is_array($__fallbackResult) && !empty($__fallbackResult['ok']);
        $__deleteResult = null;
        if ($__replacementOk && function_exists('deletemessage')) {
            $__deleteResult = @deletemessage($chat_id, $message_id);
            if (\Faoxima\Telegram\Rich\Dispatcher::rx_richUi_deleteWasConfirmed($__deleteResult)) {
                \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_noteReplacement($chat_id, $message_id, (int) ($__fallbackResult['result']['message_id'] ?? 0));
            }
        }
        return $__fallbackResult;
    }

    $result = telegram('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
        '_rx_already_transformed' => true,
    ]);

    if (is_array($result) && isset($result['ok']) && $result['ok'] === false) {
        $desc = strtolower((string)($result['description'] ?? ''));

        $needsFallback = (
            strpos($desc, 'no text in the message') !== false
            || strpos($desc, "message can't be edited") !== false
            || strpos($desc, 'message to edit not found') !== false
            || strpos($desc, 'rich message') !== false
        );
        if ($needsFallback) {
            $__fallbackReason = 'edit_rejected';
            if (strpos($desc, 'rich message') !== false) {
                $__fallbackReason = 'rich_message_edit_rejected';
            } elseif (strpos($desc, 'no text in the message') !== false) {
                $__fallbackReason = 'target_has_no_text';
            } elseif (strpos($desc, "message can't be edited") !== false) {
                $__fallbackReason = 'target_not_editable';
            } elseif (strpos($desc, 'message to edit not found') !== false) {
                $__fallbackReason = 'target_not_found';
            }
            $__fallbackResult = $__sendFallback($chat_id, $text, $keyboard, strtolower($parse_mode));
            $__replacementOk = is_array($__fallbackResult) && !empty($__fallbackResult['ok']);
            $__deleteResult = null;
            $__deleteSkipped = $__fallbackReason === 'target_not_found';
            if ($__replacementOk && !$__deleteSkipped && function_exists('deletemessage')) {
                $__deleteResult = @deletemessage($chat_id, $message_id);
                if (\Faoxima\Telegram\Rich\Dispatcher::rx_richUi_deleteWasConfirmed($__deleteResult)) {
                    \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_noteReplacement($chat_id, $message_id, (int) ($__fallbackResult['result']['message_id'] ?? 0));
                }
            } elseif ($__replacementOk && $__deleteSkipped) {
                \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_noteReplacement($chat_id, $message_id, (int) ($__fallbackResult['result']['message_id'] ?? 0));
            }
            return $__fallbackResult;
        }
    }
    return $result;
}
 function deletemessage($chat_id, $message_id){
  $message_id = (int) $message_id;
  if ($message_id <= 0) return ['ok' => false, 'description' => 'message_id missing'];
  $rxDeleteResult = telegram('deletemessage', [
'chat_id' => $chat_id,
'message_id' => $message_id,
]);
  if (empty($rxDeleteResult['_rx_already_deleted']) && method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_deleteWasConfirmed') && \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_deleteWasConfirmed($rxDeleteResult)) {
      \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_noteReplacement($chat_id, $message_id, 0);
  }
  return $rxDeleteResult;
 }
function getFileddire($photoid){
  return telegram('getFile', [
'file_id' => $photoid,
]);
 }
function pinmessage($from_id,$message_id){
  return telegram('pinChatMessage', [
'chat_id' => $from_id,
'message_id' => $message_id,
]);
 }
 function unpinmessage($from_id){
  return telegram('unpinAllChatMessages', [
'chat_id' => $from_id,
]);
 }
  function answerInlineQuery($inline_query_id,$results){
  return telegram('answerInlineQuery', [
      "inline_query_id" => $inline_query_id,
        "results" => json_encode($results)
]);
 }
function convertPersianNumbersToEnglish($string) {
    $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($persian_numbers, $english_numbers, $string);
}

function isDuplicateUpdate($updateId)
{
    static $memoryCache = [];

    if (!is_numeric($updateId) || $updateId <= 0) {
        return false;
    }

    $now = time();
    $timeToLive = 120;

    foreach ($memoryCache as $id => $timestamp) {
        if (!is_numeric($timestamp) || ($now - (int)$timestamp) > $timeToLive) {
            unset($memoryCache[$id]);
        }
    }

    if (isset($memoryCache[$updateId])) {
        return true;
    }

    if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_set_nx')) {
        $rxRedisResult = \Faoxima\Core\Cache\RedisCache::rx_redis_set_nx('faoxima:dedupe:update:' . $updateId, '1', $timeToLive * 1000);
        if ($rxRedisResult === true) {
            $memoryCache[$updateId] = $now;
            return false;
        }
        if ($rxRedisResult === false) {
            return true;
        }
    }

    $isDuplicate = false;

    $cacheDir = __DIR__ . '/storage/cache';
    $cacheDirReady = true;

    if (!is_dir($cacheDir)) {
        $cacheDirReady = @mkdir($cacheDir, 0775, true) || is_dir($cacheDir);
    }

    if ($cacheDirReady) {
        $cacheFile = $cacheDir . '/recent_updates.json';
        $handle = @fopen($cacheFile, 'c+');

        if ($handle !== false) {
            try {
                if (flock($handle, LOCK_EX)) {
                    rewind($handle);
                    $contents = stream_get_contents($handle);
                    $recentUpdates = $contents ? json_decode($contents, true) : [];
                    if (!is_array($recentUpdates)) {
                        $recentUpdates = [];
                    }

                    foreach ($recentUpdates as $id => $timestamp) {
                        if (!is_numeric($timestamp) || ($now - (int)$timestamp) > $timeToLive) {
                            unset($recentUpdates[$id]);
                        }
                    }

                    if (array_key_exists((string) $updateId, $recentUpdates) || array_key_exists($updateId, $recentUpdates)) {
                        $isDuplicate = true;
                    } else {
                        $recentUpdates[(string) $updateId] = $now;

                        if (count($recentUpdates) > 200) {
                            asort($recentUpdates);
                            $recentUpdates = array_slice($recentUpdates, -200, null, true);
                        }

                        $encoded = json_encode($recentUpdates);
                        if ($encoded !== false) {
                            rewind($handle);
                            ftruncate($handle, 0);
                            fwrite($handle, $encoded);
                            fflush($handle);
                        }
                    }

                    flock($handle, LOCK_UN);
                }
            } catch (Throwable $e) {
                try {
                    flock($handle, LOCK_UN);
                } catch (Throwable $ignored) {
                }
            }

            fclose($handle);
        }
    }

    if (!$isDuplicate) {
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            try {
                $stmtInsert = $pdo->prepare('INSERT IGNORE INTO processed_updates (update_id, processed_at) VALUES (:id, :ts)');
                $stmtInsert->execute([':id' => $updateId, ':ts' => $now]);
                if ($stmtInsert->rowCount() === 0) {
                    $isDuplicate = true;
                }
            } catch (Throwable $e) {
                $rxDedupeErrorMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'faoxima_dedupe_db_error.flag';
                if (!is_file($rxDedupeErrorMarker) || (time() - (int) @filemtime($rxDedupeErrorMarker)) > 3600) {
                    error_log('Duplicate update tracker database fallback error: ' . $e->getMessage());
                    @touch($rxDedupeErrorMarker);
                }
            }
        }
    }

    if (!$isDuplicate) {
        $memoryCache[$updateId] = $now;
    }

    return $isDuplicate;
}


$rx_request_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));


if ($rx_request_method !== 'POST') {
    return;
}


if (defined('FAOXIMA_SKIP_BOTAPI_ROUTER') && FAOXIMA_SKIP_BOTAPI_ROUTER) {
    return;
}


$rx_raw_body = @file_get_contents('php://input');
if (!is_string($rx_raw_body) || $rx_raw_body === '') {
    return;
}


$rx_update_probe = json_decode($rx_raw_body, true);
if (!is_array($rx_update_probe)) {
    return;
}


if (!isset($rx_update_probe['update_id'])) {
    return;
}


FaoximaWebhookAuth::enforce((string) ($APIKEY ?? ''));

require_once __DIR__ . '/src/bootstrap.php';

$update = $rx_update_probe;
$update_id = $update['update_id'] ?? 0;
if (!defined('RX_DEFER_UPDATE_DEDUPE') && isDuplicateUpdate($update_id)) {
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}

$telegramUpdateContext = new \Faoxima\Telegram\Context\UpdateContext($update);
[$from_id, $time_message, $is_bot, $chat_member, $language_code, $Chat_type, $text] = $telegramUpdateContext->sender();
if (!\Faoxima\Telegram\Context\UpdateContext::isNumericId($from_id)) {
    error_log('[botapi] Rejected non-numeric from_id from webhook payload');
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}
$from_id = (int) $from_id;
rx_releaseWebhookConnection();
if (method_exists(\Faoxima\Cron\Health\CronHealth::class, 'rx_cron_health_webhook_tick')) {
    register_shutdown_function([\Faoxima\Cron\Health\CronHealth::class, 'rx_cron_health_webhook_tick']);
}
$text =convertPersianNumbersToEnglish($text);

[
    'inline_text' => $text_inline,
    'message_id' => $message_id,
    'time' => $time_message,
    'photo' => $photo,
    'document' => $document,
    'document_id' => $fileid,
    'photo_id' => $photoid,
    'caption' => $caption,
    'video' => $video,
    'video_id' => $videoid,
    'forward_id' => $forward_from_id,
] = $telegramUpdateContext->message(method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_extractText') ? [\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_extractText'] : null);
$datain = $telegramUpdateContext->callbackData();
[$last_name, $first_name, $username, $user_phone] = $telegramUpdateContext->profile();
$contact_id = $telegramUpdateContext->contactId();
$callback_query_id = $telegramUpdateContext->callbackId();
$inline_query_id = $telegramUpdateContext->inlineQueryId();

$query = $update["inline_query"]["query"] ?? 0;
unset($telegramUpdateContext);
