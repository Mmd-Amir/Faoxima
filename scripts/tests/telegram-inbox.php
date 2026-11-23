<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/lib/TelegramInbox.php';

function checkTelegramInbox(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dsn = getenv('TELEGRAM_TEST_DSN');
if (!$dsn) {
    fwrite(STDERR, "Set TELEGRAM_TEST_DSN to a disposable MySQL/MariaDB database.\n");
    exit(2);
}
$user = getenv('TELEGRAM_TEST_USER') ?: 'root';
$password = getenv('TELEGRAM_TEST_PASSWORD') ?: '';
$db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
checkTelegramInbox(!$db->query("SHOW TABLES LIKE 'telegram_transport'")->fetchColumn(), 'Test requires a fresh database.');
checkTelegramInbox(!$db->query("SHOW TABLES LIKE 'telegram_inbox'")->fetchColumn(), 'Test requires a fresh database.');

$inbox = new FaoximaTelegramInbox($db, '1000001');
checkTelegramInbox(FaoximaTelegramTransport::settings($db) === null, 'Legacy database without settings.');
$inbox->install();
$inbox->configure('socks5h://test-user:p%40ss@localhost:1070', true);
$settings = FaoximaTelegramTransport::settings($db);
checkTelegramInbox((int) $settings['polling'] === 1, 'Polling is enabled.');
$inbox->pollStatus((int) $settings['revision'], 'online');
checkTelegramInbox((int) FaoximaTelegramTransport::settings($db)['last_poll_ok'] > 0, 'Health heartbeat is visible.');
$inbox->configure('http://localhost:1080', true);
$inbox->pollStatus((int) $settings['revision'], 'online');
checkTelegramInbox((int) FaoximaTelegramTransport::settings($db)['last_poll_ok'] === 0, 'Old settings cannot overwrite current health.');

foreach (['file:///etc/passwd', 'http://localhost', 'socks5h://localhost:1070/path', 'http://user:%0d@localhost:1080'] as $invalid) {
    try {
        FaoximaTelegramTransport::validateProxy($invalid);
        throw new RuntimeException('Proxy validation accepted an invalid URL.');
    } catch (InvalidArgumentException $expected) {
    }
}
checkTelegramInbox(!FaoximaTelegramTransport::mayRetryBeforeSend(CURLE_OPERATION_TIMEDOUT), 'An ambiguous POST timeout is never retried.');

$otherConnection = new PDO($dsn, $user, $password);
$otherInbox = new FaoximaTelegramInbox($otherConnection, '1000001');
checkTelegramInbox($inbox->lock('poll') && !$otherInbox->lock('poll'), 'One poller per bot.');
checkTelegramInbox($otherInbox->lock('work'), 'The worker uses an independent lock.');

$updates = [
    ['update_id' => 5, 'message' => ['from' => ['id' => 11]]],
    ['update_id' => 6, 'callback_query' => ['from' => ['id' => 11]]],
    ['update_id' => 7, 'message' => ['from' => ['id' => 12]]],
];
try {
    $inbox->store([$updates[0], ['update_id' => 4]]);
    throw new RuntimeException('An out-of-order batch was accepted.');
} catch (UnexpectedValueException $expected) {
    checkTelegramInbox($inbox->counts() === [], 'Invalid batch is rolled back.');
}
checkTelegramInbox($inbox->store($updates) === 8, 'Offset follows the committed batch.');
checkTelegramInbox($otherInbox->store($updates) === 8 && (int) $inbox->counts()['pending'] === 3, 'Restart replay deduplicates the same updates.');
checkTelegramInbox($inbox->store([]) === 0, 'An empty poll clears the in-memory offset.');

$head = $otherInbox->next();
checkTelegramInbox((int) $head['update_id'] === 5, 'Oldest update is processed first.');
$otherInbox->transition((int) $head['seq'], 'pending', 'running');
$inbox->recoverInterrupted();
checkTelegramInbox(count($inbox->held()) === 1, 'An interrupted update is held.');
checkTelegramInbox((int) $inbox->next()['update_id'] === 7, 'A held update blocks the same user only.');
$inbox->transition((int) $head['seq'], 'held', 'pending');
checkTelegramInbox((int) $otherInbox->next()['update_id'] === 5, 'Retry restores the original order.');
$inbox->transition((int) $head['seq'], 'pending', 'running');
$inbox->transition((int) $head['seq'], 'running', 'done');
checkTelegramInbox($db->query('SELECT payload FROM telegram_inbox WHERE update_id = 5')->fetchColumn() === null, 'Completed payload is erased.');
checkTelegramInbox((int) $inbox->next()['update_id'] === 6, 'Next update for the user is unblocked.');
$secondBot = new FaoximaTelegramInbox($db, '1000002');
checkTelegramInbox($secondBot->store([$updates[0]]) === 6, 'Another bot has an independent queue.');

fwrite(STDOUT, "Telegram inbox regression tests passed.\n");
