<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('FAOXIMA_LAZY_MYSQLI', true);
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/TelegramInbox.php';
require dirname(__DIR__) . '/lib/WebhookAuth.php';

function runTelegramPoller(FaoximaTelegramInbox $inbox, PDO $db, string $token): void
{
    $handle = curl_init();
    $offset = 0;
    $revision = null;
    $failures = 0;
    $lastState = null;

    while (true) {
        $settings = FaoximaTelegramTransport::settings($db);
        if (!$settings || !$settings['polling']) {
            $revision = null;
            sleep(2);
            continue;
        }
        $firstRequest = $revision !== (int) $settings['revision'];
        if ($firstRequest) {
            $revision = (int) $settings['revision'];
            $offset = 0;
            $failures = 0;
            $lastState = null;
        }

        $timeout = $firstRequest || $failures > 0 ? 0 : 25;
        $result = FaoximaTelegramTransport::control(
            $token, 'getUpdates', ['timeout' => $timeout, 'offset' => $offset],
            $timeout + 10, $handle, $settings['proxy_url']
        );
        $body = $result['body'];
        if ($result['errno'] === 0 && $result['code'] === 200 && ($body['ok'] ?? false) === true
            && isset($body['result']) && is_array($body['result']) && array_is_list($body['result'])) {
            // Telegram confirms the previous batch on the following request with offset.
            // Send that offset only after committing every update in the batch.
            $offset = $inbox->store($body['result']);
            $inbox->pollStatus($revision, 'online');
            if ($lastState !== 'online') {
                fwrite(STDERR, "Telegram polling is online.\n");
            }
            $lastState = 'online';
            $failures = 0;
            continue;
        }

        // A later recovery can start a new Telegram update-ID sequence.
        // Re-fetch any unconfirmed updates instead of reusing a stale offset.
        $offset = 0;
        $code = (int) ($body['error_code'] ?? $result['code']);
        $state = match ($code) {
            401, 404 => 'token_error',
            409 => 'conflict',
            429 => 'rate_limited',
            default => 'unreachable',
        };
        if ($lastState !== $state) {
            $inbox->pollStatus($revision, $state);
            fwrite(STDERR, "Telegram polling: {$state} (API {$code}, cURL {$result['errno']}).\n");
        }
        $lastState = $state;
        $failures = min($failures + 1, 6);
        $delay = match ($state) {
            'conflict', 'token_error' => 15,
            'rate_limited' => max(1, (int) ($body['parameters']['retry_after'] ?? 5)),
            default => min(20, 2 ** ($failures - 1)),
        };
        usleep($delay * 1000000 + random_int(0, 250000));
    }
}

function runTelegramUpdate(string $botId, array $job, int $workerConnection): int
{
    $input = tmpfile();
    if ($input === false) {
        return 75;
    }
    try {
        $payload = $job['payload'];
        if (fwrite($input, $payload) !== strlen($payload) || !rewind($input)) {
            return 75;
        }
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/telegram-update.php', $botId, (string) $job['seq'], (string) $workerConnection],
            [0 => $input, 1 => ['file', '/dev/null', 'w'], 2 => STDERR],
            $pipes,
            dirname(__DIR__)
        );
        if (!is_resource($process)) {
            return 75;
        }

        $deadline = hrtime(true) + 120000000000;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                proc_close($process);
                return $status['exitcode'];
            }
            usleep(10000);
        } while (hrtime(true) < $deadline);

        proc_terminate($process, 9);
        proc_close($process);
        return 1;
    } finally {
        fclose($input);
    }
}

function runTelegramWorker(FaoximaTelegramInbox $inbox, PDO $db, string $botId): void
{
    $inbox->recoverInterrupted();
    $workerConnection = (int) $db->query('SELECT CONNECTION_ID()')->fetchColumn();
    $nextPrune = 0;
    while (true) {
        if (time() >= $nextPrune) {
            $pruned = $inbox->prune();
            $nextPrune = time() + ($pruned === 1000 ? 10 : 3600);
        }
        $settings = FaoximaTelegramTransport::settings($db);
        if (!$settings || !$settings['polling'] || time() - (int) $settings['last_poll_ok'] > 60) {
            usleep(500000);
            continue;
        }
        $job = $inbox->next();
        if ($job === null) {
            usleep(100000);
            continue;
        }
        if (!$inbox->transition((int) $job['seq'], 'pending', 'running')) {
            continue;
        }
        $start = hrtime(true);
        $exit = runTelegramUpdate($botId, $job, $workerConnection);
        $state = match ($exit) {
            0 => 'done',
            75 => 'pending',
            default => 'held',
        };
        $inbox->transition((int) $job['seq'], 'running', $state);
        $ms = (int) ((hrtime(true) - $start) / 1000000);
        if ($exit !== 0 || $ms >= 1000) {
            fwrite(STDERR, "Telegram update {$job['update_id']} {$state} after {$ms} ms.\n");
        }
        if ($exit === 75) {
            sleep(2);
        }
    }
}

try {
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Telegram service requires the Faoxima database.');
    }
    $token = (string) $APIKEY;
    $botId = FaoximaTelegramInbox::botId($token);
    $inbox = new FaoximaTelegramInbox($pdo, $botId);
    $role = $argv[1] ?? '';
    if ($role === 'init') {
        $inbox->install();
        exit(0);
    }
    if ($role === 'detach-webhook' || $role === 'attach-webhook') {
        $settings = FaoximaTelegramTransport::settings($pdo);
        $proxy = $settings['proxy_url'] ?? '';
        $detach = $role === 'detach-webhook';
        if ($detach !== !empty($settings['polling'])) {
            fwrite(STDERR, $detach
                ? "Enable polling in the admin panel before detaching the webhook.\n"
                : "Disable polling in the admin panel before attaching the webhook.\n");
            exit(1);
        }
        $parameters = ['drop_pending_updates' => 'false'];
        if (!$detach) {
            if ($inbox->counts() !== []) {
                fwrite(STDERR, "Drain or review the local Telegram inbox before attaching the webhook.\n");
                exit(1);
            }
            $url = 'https://' . $domainhosts . '/index.php';
            if ($domainhosts === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                fwrite(STDERR, "The Faoxima webhook URL is invalid. Check the domain in config.php.\n");
                exit(1);
            }
            $parameters['url'] = $url;
            $parameters['secret_token'] = FaoximaWebhookAuth::secret($token);
        }
        $method = $detach ? 'deleteWebhook' : 'setWebhook';
        $result = FaoximaTelegramTransport::control($token, $method, $parameters, 10, null, $proxy);
        if (($result['body']['ok'] ?? false) !== true) {
            fwrite(STDERR, "Telegram rejected {$method} (HTTP {$result['code']}, cURL {$result['errno']}).\n");
            exit(1);
        }
        fwrite(STDOUT, "Telegram confirmed {$method}. Pending updates were kept.\n");
        exit(0);
    }
    if ($role === 'retry' || $role === 'resolve') {
        $seq = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $target = $role === 'retry' ? 'pending' : 'done';
        if ($seq === false || !$inbox->transition($seq, 'held', $target)) {
            fwrite(STDERR, "Provide a row ID currently held for review.\n");
            exit(1);
        }
        fwrite(STDOUT, "Update moved to {$target}.\n");
        exit(0);
    }
    if (!in_array($role, ['poll', 'work'], true)) {
        fwrite(STDERR, "Usage: php scripts/telegram-polling.php init|poll|work|detach-webhook|attach-webhook|retry ROW|resolve ROW\n");
        exit(2);
    }
    if (!$inbox->lock($role, $role === 'work' ? 5 : 0)) {
        fwrite(STDERR, "Another Telegram process already owns the {$role} lock.\n");
        exit(1);
    }
    if ($role === 'poll') {
        runTelegramPoller($inbox, $pdo, $token);
    } else {
        runTelegramWorker($inbox, $pdo, $botId);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Telegram service stopped (' . get_class($error) . ', code ' . $error->getCode() . "). Check configuration and database.\n");
    exit(1);
}
