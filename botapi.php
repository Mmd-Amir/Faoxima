<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/WebhookAuth.php';

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

function telegram($method, $datas = [], $token = null)
{
    global $APIKEY, $telegramCurlTimeout;

    $token = $token === null ? $APIKEY : $token;
    $url = "https://api.telegram.org/bot" . $token . "/" . $method;

    if (!is_array($datas)) {
        $datas = [];
    }

    if (strtolower((string) $method) === 'setwebhook') {
        $datas['secret_token'] = FaoximaWebhookAuth::secret((string) $token, $token === $APIKEY);
    }

    if (isset($datas['message_thread_id']) && intval($datas['message_thread_id']) <= 0) {
        unset($datas['message_thread_id']);
    }









    $rxAlreadyTransformed = !empty($datas['_rx_already_transformed']);
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
                $datas['reply_markup'] = processKeyboardStyles($datas['reply_markup'], isset($datas['chat_id']) && function_exists('rx_isAdminChat') && rx_isAdminChat($datas['chat_id']));
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
        && (!isset($datas['text']) || !is_string($datas['text']) || trim($datas['text']) === '')) {
        $rxEmptyCaller = 'unknown';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $rxFrame) {
            if (isset($rxFrame['file']) && basename($rxFrame['file']) !== 'botapi.php') {
                $rxEmptyCaller = basename($rxFrame['file']) . ':' . ($rxFrame['line'] ?? '?');
                break;
            }
        }
        $rxEmptyMsg = '[empty-message] skipped ' . $method . ' to chat ' . ($datas['chat_id'] ?? '?') . ' — empty text from ' . $rxEmptyCaller;
        if (function_exists('faoxima_dedup_error_log')) {
            faoxima_dedup_error_log('rx_empty_text_' . $rxSendMethod . '_' . $rxEmptyCaller, $rxEmptyMsg, 21600);
        } else {
            @error_log($rxEmptyMsg);
        }
        return ['ok' => false, 'error_code' => 400, 'description' => 'message text is empty (skipped locally)'];
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
    $maxAttempts = 2; // 1 retry. Total worst case: ~10s (acceptable for a 30s webhook).
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
        $timeout = isset($telegramCurlTimeout) && is_numeric($telegramCurlTimeout) ? (int)$telegramCurlTimeout : 10;
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

        $requestStartedAt = microtime(true);
        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = microtime(true) - $requestStartedAt;

        if ($rawResponse !== false) {
            curl_close($ch);
            break; // network success — proceed to response parsing below
        }

        $curlErrorNumber = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Stop if the error isn't network-transient, or if we just used the
        // last attempt — fall through to the error-return block.
        if (!in_array($curlErrorNumber, $retryableCurlErrnos, true) || $attemptedTimes >= $maxAttempts) {
            break;
        }

        usleep(250000); // 250 ms backoff before retry
    }

    if ($rawResponse === false) {
        $logError = $curlError !== '' ? $curlError : 'Unknown cURL error';
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

    return rx_parse_telegram_response($rawResponse, $httpCode);
}

function rx_parse_telegram_response($rawResponse, $httpCode)
{
    $decodedResponse = json_decode($rawResponse, true);
    if (!is_array($decodedResponse)) {
        $logSnippet = substr((string) $rawResponse, 0, 200);
        error_log(sprintf('Invalid response from Telegram API (HTTP %d): %s', $httpCode, $logSnippet));

        return [
            'ok' => false,
            'error_code' => $httpCode,
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
            $rxTgKey = 'tg|' . (string)($decodedResponse['error_code'] ?? 0) . '|' . substr($rxDesc, 0, 60);
            if (function_exists('faoxima_dedup_error_log')) {
                faoxima_dedup_error_log($rxTgKey, json_encode($decodedResponse), 21600);
            } else {
                error_log(json_encode($decodedResponse));
            }
        }
    }

    return $decodedResponse;
}

function rx_telegram_dispatch_concurrent(array $requests)
{
    global $APIKEY;

    $multiHandle = curl_multi_init();
    $curlHandles = [];

    foreach ($requests as $index => $request) {
        $method = $request['method'];
        $datas = is_array($request['datas']) ? $request['datas'] : [];
        $token = $request['token'] ?? $APIKEY;
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

        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$index] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($multiHandle, $running);
        if ($running > 0) {
            curl_multi_select($multiHandle, 1.0);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    foreach ($curlHandles as $index => $ch) {
        $rawResponse = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        if ($curlErrno !== 0 || $rawResponse === null || $rawResponse === '') {
            $results[$index] = [
                'ok' => false,
                'description' => curl_error($ch) !== '' ? curl_error($ch) : 'Telegram request failed.'
            ];
        } else {
            $results[$index] = rx_parse_telegram_response($rawResponse, $httpCode);
        }
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiHandle);

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


function isValidPremiumEmojiSource($emoji) {
    if (!is_string($emoji) || $emoji === '') { return false; }
    if (strlen($emoji) > 32) { return false; }
    // Reject plain multi-letter text mistakenly stored instead of an emoji
    // (e.g. "iploginset"), while still allowing legit keycap emoji like
    // "0⃣", "#⃣", "*⃣" (ASCII digit/#/* immediately followed by U+20E3).
    if (preg_match('/[A-Za-z]/', $emoji)) { return false; }
    if (preg_match('/[0-9#*](?!\x{20E3})/u', $emoji)) { return false; }
    return (bool) preg_match('/^[0-9#*\x{1F000}-\x{1FFFF}\x{2000}-\x{3300}\x{FE00}-\x{FE0F}\x{200D}]+$/u', $emoji);
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
        $stmt = $pdo->query("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE custom_emoji_id IS NOT NULL AND custom_emoji_id <> ''");
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
    if (!is_string($text) || $text === '') { return $text; }
    $pm = strtolower((string)$parseMode);

    if ($pm !== 'html') { return $text; }
    $map = getPremiumEmojiMap();
    if (empty($map)) { return $text; }

    if (stripos($text, '<tg-emoji') !== false) { return $text; }
    foreach ($map as $emoji => $cid) {
        if ($emoji === '' || $cid === '') { continue; }
        if (strpos($text, $emoji) === false) { continue; }
        $cidEscaped = htmlspecialchars($cid, ENT_QUOTES);
        $tagged = '<tg-emoji emoji-id="' . $cidEscaped . '">' . $emoji . '</tg-emoji>';
        $text = str_replace($emoji, $tagged, $text);
    }
    return $text;
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

if (!function_exists('rx_adminPanelCallbackMapFile')) {
    function rx_adminPanelCallbackMapFile(): string {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/') . '/rx_admin_panel_callback_map.json';
    }
}
if (!function_exists('rx_makeAdminPanelCallback')) {
    function rx_makeAdminPanelCallback(string $text): string {
        $short = 'apn:' . $text;
        if (strlen($short) <= 64) return $short;
        $code = 'apnh:' . substr(hash('sha256', $text), 0, 32);
        $file = rx_adminPanelCallbackMapFile();
        $map = [];
        if (is_file($file)) {
            $dec = json_decode((string) @file_get_contents($file), true);
            if (is_array($dec)) $map = $dec;
        }
        $map[$code] = $text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveAdminPanelCallback')) {
    function rx_resolveAdminPanelCallback(string $callbackData): ?string {
        if (strpos($callbackData, 'apn:') === 0) {
            return substr($callbackData, 4);
        }
        if (strpos($callbackData, 'apnh:') === 0) {
            $file = rx_adminPanelCallbackMapFile();
            if (!is_file($file)) return null;
            $map = json_decode((string) @file_get_contents($file), true);
            return (is_array($map) && isset($map[$callbackData])) ? (string)$map[$callbackData] : null;
        }
        return null;
    }
}
if (!function_exists('rx_finalizeInlineAdminKb')) {
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




        $rxGlassMode = isset($GLOBALS['setting']['inlinebtnmain']) && $GLOBALS['setting']['inlinebtnmain'] === 'oninline';
        if (!$rxGlassMode && isset($kb['keyboard'])) {
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
                    $btn['callback_data'] = rx_makeAdminPanelCallback((string)$btn['text']);
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
        if (!function_exists('select') || !function_exists('update')) return;

        try {
            $pendingId = select('user', 'reply_kb_cleanup_msg_id', 'id', $chatId, 'select', ['cache' => false]);
            $pendingId = is_scalar($pendingId) ? (int) $pendingId : 0;
            if ($pendingId <= 0) return;

            if (function_exists('deletemessage')) {
                deletemessage($chatId, $pendingId);
            }
            update('user', 'reply_kb_cleanup_msg_id', '0', 'id', $chatId);
        } catch (Throwable $e) {
            error_log('[rx_flushPendingReplyKeyboardCleanup] ' . $e->getMessage());
        }
    }
}

if (!function_exists('removeReplyKeyboardOnStartIfNeeded')) {
    function removeReplyKeyboardOnStartIfNeeded($chatId): void {
        $chatId = trim((string) $chatId);
        if ($chatId === '' || !ctype_digit($chatId)) return;
        if (!function_exists('select') || !function_exists('update')) return;

        try {
            rx_flushPendingReplyKeyboardCleanup($chatId);

            if (!rx_glassModeOn()) {
                $flag = select('user', 'reply_kb_cleared', 'id', $chatId, 'select', ['cache' => false]);
                $flag = is_scalar($flag) ? (string) $flag : '0';
                if ($flag === '1') {
                    update('user', 'reply_kb_cleared', '0', 'id', $chatId);
                }
                return;
            }

            if (!rx_autoRemoveReplyKeyboardOn()) {
                return;
            }

            $flag = select('user', 'reply_kb_cleared', 'id', $chatId, 'select', ['cache' => false]);
            $flag = is_scalar($flag) ? (string) $flag : '0';
            if ($flag === '1') {
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

            update('user', 'reply_kb_cleared', '1', 'id', $chatId);

            usleep(500000);

            $rxRkrDeleteOk = false;
            if (function_exists('deletemessage')) {
                $rxRkrDeleteResult = deletemessage($chatId, $rxRkrMessageId);
                $rxRkrDeleteOk = is_array($rxRkrDeleteResult) && !empty($rxRkrDeleteResult['ok']);
            }

            update('user', 'reply_kb_cleanup_msg_id', $rxRkrDeleteOk ? '0' : (string) $rxRkrMessageId, 'id', $chatId);
        } catch (Throwable $e) {
            error_log('[removeReplyKeyboardOnStartIfNeeded] ' . $e->getMessage());
        }
    }
}

if (!function_exists('removeReplyKeyboardIfNeeded')) {
    function removeReplyKeyboardIfNeeded($chatId): void {
        removeReplyKeyboardOnStartIfNeeded($chatId);
    }
}

function processKeyboardStyles($keyboard, $rxPlainAdmin = false) {
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
        $_rx_kb_list_styles = ['lists' => [], 'misc' => [], 'features' => []];
        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                    $all = json_decode($row['keyboard_styles_all'], true);
                    if (is_array($all)) {
                        if (!empty($all['admin_lists']))  $_rx_kb_list_styles['lists'] = $all['admin_lists'];
                        if (!empty($all['user_dynamic_lists']) && is_array($all['user_dynamic_lists'])) {

                            $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $all['user_dynamic_lists'];
                        }
                        if (!empty($all['cron_notifications']) && is_array($all['cron_notifications'])) {

                            $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $all['cron_notifications'];
                        }
                        if (!empty($all['misc_buttons'])) $_rx_kb_list_styles['misc']  = $all['misc_buttons'];
                        
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


                            'service','account','payment','user_nav','pay_receipt','invoice_copy_buttons','home_menu',
                        ];
                        foreach ($featMenus as $fm) {
                            if (!empty($all[$fm]) && is_array($all[$fm])) {
                                $_rx_kb_list_styles['features'] = array_merge(
                                    $_rx_kb_list_styles['features'], $all[$fm]
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

                        $_rx_kb_list_styles['misc'] = $_rx_kb_list_styles['misc'] + $rx_kb_def_pairs;
                    } elseif ($rx_kb_sec === 'admin_lists' || $rx_kb_sec === 'user_dynamic_lists' || $rx_kb_sec === 'cron_notifications') {

                        $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $rx_kb_def_pairs;
                    } else {

                        $_rx_kb_list_styles['features'] = $_rx_kb_list_styles['features'] + $rx_kb_def_pairs;
                    }
                }
            }
        }
    }

    static $_rx_admin_plain_cb = null;
    if ($_rx_admin_plain_cb === null) {
        $_rx_admin_plain_cb = [];
        $_rx_user_secs = ['service','account','payment','user_nav','pay_receipt','user_subscription','user_dynamic_lists','invoice_copy_buttons','recheckcrypto_buttons','home_menu'];
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
        foreach (['confirmandgetservice','confirmserivce','confirmserdiscount','confirmpaid','acceptrule','buyback','startaction','next_page','previous_page','next_page_extends','previous_page_extends','searchservice'] as $_rx_uex) {
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
        'categorynames_'         => 'category_list',
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
        'serviceextendselects-'  => 'product_buy',
        'serviceextendselect-'   => 'product_buy',
        'serviceextend-'         => 'product_buy',
        'producttime_'           => 'time_buy',
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

        if (!$rxHasReplyFeature && function_exists('rx_makeAdminPanelCallback')) {
            $rxInlineRows = [];
            foreach ($kb['keyboard'] as $rxR) {
                if (!is_array($rxR)) continue;
                $rxNewRow = [];
                foreach ($rxR as $rxB) {
                    if (!is_array($rxB) || !isset($rxB['text'])) continue;
                    if (!isset($rxB['callback_data']) && !isset($rxB['url'])
                        && !isset($rxB['login_url']) && !isset($rxB['switch_inline_query'])
                        && !isset($rxB['switch_inline_query_current_chat'])) {
                        $rxB['callback_data'] = rx_makeAdminPanelCallback((string)$rxB['text']);
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

    if (isset($kb['keyboard']) && is_array($kb['keyboard']) && function_exists('rx_makeAdminPanelCallback')) {
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
                    $rxReplyBtn['callback_data'] = rx_makeAdminPanelCallback((string)$rxReplyBtn['text']);
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
            'prodcutservice','serviceextend','producttime_','productvolume_','categorynames_','helpsection_','paneluserbuy_','locationbuy_','buy_service',
            'quickview_','updateproduct_','subscriptionurl_','removeserviceuser_','transfer_','disorder-','confrimtransfers_','extends_','infocard_qr_',
            'Extra_time_','Extra_volume_','Extra_volumes_','extravolunme','exntedagei','discountvolume-','discounttime-',
            'confirm_pay','confirm_discount','confirm_back','confirmandgetservice','chargehasdiscount','chargenodiscount','rules_accept','acceptrule','nav_back',
            'cart_to_offline','colselist','paymentnotverify','startelegrams',
            'pay_sendreceipt','pay_done','pay_cancel','pay_back','pay_wallet_copy','pay_card_copy','pay_check','sendresidcart-',
            'currency_pick','copy_wallet','copy_amount','copy_memo','paid_submit','invoice_back','mode_external','cancel_hash_input',
            'digitaltron_pay_','digitaltron_submit_','crypto_cancel_','crypto_pay_','crypto_submit_',
            'recheckcrypto','rcc_pick_','rcc_skip_photo','rcc_cancel','rcc_resubmit_',
            'Discount','Add_Balance','backuser','backorder','account','rw_pick_','rw_custom','confirmandgetserviceDiscount','aptdc',
            'ticketnew','ticketopen_','ticketmsg_','ticketclose_','tk_cnt_','tk_album_','tk_cancel','tk_media_yes','tk_media_no','departman_','supporttickets','helpos_',
            'wheel_luck','affiliatesbtn','agentpanel','requestagent','supportbtns','helpbtns','usertestbtn','extendbtn',
            'my_miniapp_pending','location_','buyback','ticketclose_',
            'gen_random_uname',
            'nmstocksel_','nmstockextend_','nmstockrefund_','nmstockcfg_','nmstocksub_',
            'cv_use_','cv_new',
            'confirmpaid','confirmserivce','confirmserdiscount',
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
                $rxKeep = !empty($btn['_rxkeep']); unset($btn['_rxkeep']);
                if (!empty($btn['_rxap'])) { unset($btn['_rxap'], $btn['style']); continue; }
                if ($rxPlainAdmin && !$rxKeep && !$rxIsUserKb) { unset($btn['style']); continue; }
                if (!isset($btn['callback_data'])) {
                    $rxCk = isset($btn['_rxck']) ? (string)$btn['_rxck'] : ''; unset($btn['_rxck']);
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
                $rxSk = isset($btn['_rxsk']) ? (string)$btn['_rxsk'] : ''; unset($btn['_rxsk']);
                if ($rxSk !== '') {
                    if (!isset($btn['style'])) {
                        $rxSkSt = $_rx_kb_list_styles['features'][$rxSk] ?? null;
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
                if (isset($_rx_admin_plain_cb[$cb]) && !$rxIsUserKb) {
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
                'account' => [
                    'Discount'             => 'success',
                    'Add_Balance'          => 'success',
                    'TransferBalance'      => 'primary',
                    'MyTransactions'       => 'primary',
                    'transferbal_confirm'  => 'success',
                    'transferbal_cancel'   => 'danger',
                    'backuser'             => 'danger',
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
                "⏳ زمان سرویس تست"                  => 'success',
                "💾 حجم اکانت تست"                   => 'success',
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

function sendmessage($chat_id,$text,$keyboard,$parse_mode,$bot_token = null){
    if(intval($chat_id) == 0)return ['ok' => false];
    $text = applyPremiumEmojiTransform($text, $parse_mode);
    $keyboard = processKeyboardStyles($keyboard, function_exists('rx_isAdminChat') && rx_isAdminChat($chat_id));
    $keyboard = applyPremiumEmojiToKeyboard($keyboard);
    return telegram('sendmessage',[
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
        '_rx_already_transformed' => true,
        ],$bot_token);
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
                            $styles = $all['cron_notifications'];
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
        if (isset($btn['style'])) return $btn;
        $st = rx_cron_style($cronKey);
        if ($st !== null) $btn['style'] = $st;
        return $btn;
    }
}
function normalizeConfigFilename($filename, $prefix = '', $wireguard = false)
{
    $filename = basename(str_replace('\\', '/', (string) $filename));
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $stem = (string) pathinfo($filename, PATHINFO_FILENAME);
    $stem = preg_replace('/[^A-Za-z0-9.-]+/', '-', str_replace('_', '-', $stem));
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

function sendDocument($chat_id, $documentPath, $caption, $filename = null)
{
    $document = prepareTelegramInputFile($documentPath, $filename);
    if ($document === null) {
        return [
            'ok' => false,
            'description' => 'Document could not be prepared for Telegram upload.'
        ];
    }

    return telegram('sendDocument', [
        'chat_id' => $chat_id,
        'document' => $document,
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ]);
}

function forwardMessage($chat_id,$message_id,$chat_id_user){
    return telegram('forwardMessage',[
        'from_chat_id'=> $chat_id,
        'message_id'=> $message_id,
        'chat_id'=> $chat_id_user,
    ]);
}
function sendphoto($chat_id,$photoid,$caption){
    telegram('sendphoto',[
        'chat_id' => $chat_id,
        'photo'=> $photoid,
        'caption'=> $caption,
        'parse_mode' => 'HTML',
    ]);
}
function sendvideo($chat_id,$videoid,$caption){
    telegram('sendvideo',[
        'chat_id' => $chat_id,
        'video'=> $videoid,
        'caption'=> $caption,
        'parse_mode' => 'HTML',
    ]);
}
function senddocumentsid($chat_id,$documentid,$caption){
    telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document'=> $documentid,
        'caption'=> $caption,
        'parse_mode' => 'HTML',
    ]);
}
function Editmessagetext($chat_id, $message_id, $text, $keyboard,$parse_mode = 'HTML', $threadId = null){
    $message_id = (int) $message_id;
    global $update;
    $__callbackMsg = (isset($update['callback_query']['message']) && is_array($update['callback_query']['message']))
        ? $update['callback_query']['message'] : null;
    $__callbackMsgId = $__callbackMsg ? (int)($__callbackMsg['message_id'] ?? 0) : 0;
    $__origThreadId = $threadId !== null
        ? (int) $threadId
        : ($__callbackMsg ? (int)($__callbackMsg['message_thread_id'] ?? 0) : 0);
    $__sendFallback = function ($fbChatId, $fbText, $fbKeyboard, $fbParseMode) use ($__origThreadId) {
        $fbText = applyPremiumEmojiTransform($fbText, $fbParseMode);
        $fbKeyboard = processKeyboardStyles($fbKeyboard, function_exists('rx_isAdminChat') && rx_isAdminChat($fbChatId));
        $fbKeyboard = applyPremiumEmojiToKeyboard($fbKeyboard);
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
        return $__sendFallback($chat_id, $text, $keyboard, strtolower($parse_mode));
    }
    $text = applyPremiumEmojiTransform($text, $parse_mode);
    $keyboard = processKeyboardStyles($keyboard, function_exists('rx_isAdminChat') && rx_isAdminChat($chat_id));
    $keyboard = applyPremiumEmojiToKeyboard($keyboard);

    $__isPhotoMsg = $__callbackMsg && (
        !empty($__callbackMsg['photo'])
        || !empty($__callbackMsg['video'])
        || !empty($__callbackMsg['animation'])
        || !empty($__callbackMsg['document'])
    );
    if ($__isPhotoMsg && $__callbackMsgId === (int)$message_id) {
        if (function_exists('deletemessage')) {
            @deletemessage($chat_id, $message_id);
        }
        return $__sendFallback($chat_id, $text, $keyboard, strtolower($parse_mode));
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
        );
        if ($needsFallback) {
            if (function_exists('deletemessage')) {
                @deletemessage($chat_id, $message_id);
            }
            return $__sendFallback($chat_id, $text, $keyboard, strtolower($parse_mode));
        }
    }
    return $result;
}
 function deletemessage($chat_id, $message_id){
  $message_id = (int) $message_id;
  if ($message_id <= 0) return ['ok' => false, 'description' => 'message_id missing'];
  return telegram('deletemessage', [
'chat_id' => $chat_id,
'message_id' => $message_id,
]);
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
function getChatPinnedMessageId($chat_id){
  $resp = telegram('getChat', [
'chat_id' => $chat_id,
]);
  if (isset($resp['ok']) && $resp['ok'] && isset($resp['result']['pinned_message']['message_id'])) {
    return (int) $resp['result']['pinned_message']['message_id'];
  }
  return null;
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

    if (function_exists('rx_redis_set_nx')) {
        $rxRedisResult = rx_redis_set_nx('faoxima:dedupe:update:' . $updateId, '1', $timeToLive * 1000);
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

$update = $rx_update_probe;
$update_id = $update['update_id'] ?? 0;
if (isDuplicateUpdate($update_id)) {
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}

$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update["inline_query"]['from']['id'] ?? 0;
$time_message = $update['message']['date'] ?? $update['callback_query']['date'] ?? $update["inline_query"]['date'] ?? 0;
$is_bot = $update['message']['from']['is_bot'] ?? false;
$chat_member = $update['chat_member'] ?? null;
$language_code = strtolower($update['message']['from']['language_code'] ?? $update['callback_query']['from']['language_code'] ?? "fa");
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"]  ?? '';
if(isset($update['pre_checkout_query'])){
    $Chat_type = "private";
    $from_id = $update['pre_checkout_query']['from']['id'];
}


$rx_assertNumericId = static function ($value): int {
    if (!is_numeric($value)) {
        return 0;
    }
    if ((string)(int) $value !== (string) $value) {
        return 0;
    }
    return (int) $value;
};
if (!is_numeric($from_id) || (string)(int) $from_id !== (string) $from_id) {
    error_log('[botapi] Rejected non-numeric from_id from webhook payload');
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}
$from_id = (int) $from_id;
rx_releaseWebhookConnection();
$text =convertPersianNumbersToEnglish($text);

$text_inline = $update["callback_query"]["message"]['text'] ?? '';
$message_id = $update["message"]["message_id"] ?? $update["callback_query"]["message"]["message_id"] ?? 0;
$message_id = $rx_assertNumericId($message_id);
$time_message = $update["message"]["date"] ?? $update["callback_query"]["date"] ?? 0;
$photo = $update["message"]["photo"] ?? 0;
$document = $update["message"]["document"] ?? 0;
$fileid = $update["message"]["document"]["file_id"] ?? 0;
$photoid = $photo ? end($photo)["file_id"] : '';
$caption = $update["message"]["caption"] ?? '';
$video = $update["message"]["video"] ?? 0;
$videoid = $video ? $video["file_id"] : 0;
$forward_from_id = $update["message"]["reply_to_message"]["forward_from"]["id"] ?? 0;
$forward_from_id = $rx_assertNumericId($forward_from_id);
$datain = $update["callback_query"]["data"] ?? '';
$last_name = $update['message']['from']['last_name']  ?? $update["callback_query"]["from"]["last_name"] ?? $update["inline_query"]['from']['last_name'] ?? '';
$first_name = $update['message']['from']['first_name']  ?? $update["callback_query"]["from"]["first_name"] ?? $update["inline_query"]['from']['first_name'] ?? '';
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? $update["callback_query"]["from"]["username"] ?? 'NOT_USERNAME';
$user_phone =$update["message"]["contact"]["phone_number"] ?? 0;
$contact_id = $update["message"]["contact"]["user_id"] ?? 0;
$contact_id = $rx_assertNumericId($contact_id);
$callback_query_id = $update["callback_query"]["id"] ?? 0;


if (!is_string($callback_query_id) && !is_int($callback_query_id)) {
    $callback_query_id = '';
} elseif (!preg_match('/^\d{1,32}$/', (string) $callback_query_id)) {
    $callback_query_id = '';
}
$inline_query_id = $update["inline_query"]["id"] ?? 0;
if (!is_string($inline_query_id) && !is_int($inline_query_id)) {
    $inline_query_id = '';
} elseif (!preg_match('/^\d{1,32}$/', (string) $inline_query_id)) {
    $inline_query_id = '';
}

$query = $update["inline_query"]["query"] ?? 0;
