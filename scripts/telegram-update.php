<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('FAOXIMA_LAZY_MYSQLI', true);
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/TelegramInbox.php';
require dirname(__DIR__) . '/lib/WebhookAuth.php';

try {
    if (!$pdo instanceof PDO || FaoximaTelegramInbox::botId((string) $APIKEY) !== ($argv[1] ?? '')) {
        exit(75);
    }
    $body = stream_get_contents(STDIN);
    $update = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_int($update['update_id'] ?? null) || $update['update_id'] < 1) {
        exit(1);
    }
    $settings = FaoximaTelegramTransport::currentSettings();
    if (!$settings || !$settings['polling'] || $settings['proxy_url'] === '') {
        exit(75);
    }
} catch (Throwable $error) {
    exit(75);
}

define('FAOXIMA_POLL_UPDATE', true);
$GLOBALS['faoximaPollUpdateBody'] = $body;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = FaoximaWebhookAuth::secret($APIKEY);
$_SERVER['HTTP_HOST'] = (string) parse_url('https://' . $domainhosts, PHP_URL_HOST);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'on';
http_response_code(200);
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!empty($GLOBALS['faoximaPollTransportFailed']) || http_response_code() >= 400
        || in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        exit(1);
    }
});
require dirname(__DIR__) . '/index.php';
