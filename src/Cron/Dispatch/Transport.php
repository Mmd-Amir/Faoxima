<?php

namespace Faoxima\Cron\Dispatch;

final class Transport
{
    public static function detectLoopback(): ?array
    {
        $cacheFile = (dirname(__DIR__, 3) . '/cron') . '/loopback_port.cache';
        if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 300) {
            $cached = @json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) || $cached === null) {
                return is_array($cached) ? $cached : null;
            }
        }
        foreach ([80 => 'http', 443 => 'https', 8080 => 'http'] as $port => $proto) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
            if ($fp !== false) {
                fclose($fp);
                $result = ['port' => $port, 'scheme' => $proto];
                @file_put_contents($cacheFile, json_encode($result));
                return $result;
            }
        }
        @file_put_contents($cacheFile, json_encode(null));
        return null;
    }

    public static function async(array $tasks, bool $useLoopback, string $authToken): array
    {
        $result = ['success' => [], 'failed' => [], 'uncertain' => []];
        if (empty($tasks)) {
            return $result;
        }
        if (!function_exists('curl_multi_init')) {
            $result['failed'] = $tasks;
            return $result;
        }
        $multi = curl_multi_init();
        if ($multi === false) {
            $result['failed'] = $tasks;
            return $result;
        }
        $handles = [];
        foreach ($tasks as $task) {
            $url = (string) ($task['url'] ?? '');
            if ($url === '') {
                $result['failed'][] = $task;
                continue;
            }
            $bustedUrl = $url . (strpos($url, '?') === false ? '?' : '&') . '_t=' . microtime(true);
            $ch = curl_init($bustedUrl);
            if ($ch === false) {
                $result['failed'][] = $task;
                continue;
            }
            $resolveHost = parse_url($url, PHP_URL_HOST);
            $curlOpts = [
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_NOSIGNAL        => true,
                CURLOPT_CONNECTTIMEOUT_MS => 1500,
                CURLOPT_TIMEOUT_MS      => 4000,
                CURLOPT_SSL_VERIFYPEER  => false,
                CURLOPT_SSL_VERIFYHOST  => 0,
                CURLOPT_FOLLOWLOCATION  => false,
                CURLOPT_FORBID_REUSE    => true,
                CURLOPT_FRESH_CONNECT   => true,
                CURLOPT_HTTPHEADER      => [
                    'Cache-Control: no-cache, no-store, must-revalidate, max-age=0',
                    'Pragma: no-cache',
                    'Expires: 0',
                    'X-Cron-Source: cron-orchestrator',
                    'X-Cron-Token: ' . $authToken,
                    'X-Cron-Task: ' . (string) ($task['task_key'] ?? ''),
                    'X-Cron-Task-Token: ' . (string) ($task['token'] ?? ''),
                    'X-Cron-Occurrence: ' . (int) ($task['occurrence'] ?? ($task['scheduled_for'] ?? 0)),
                    'Connection: close',
                ],
                CURLOPT_USERAGENT       => 'CronOrchestrator/2.0 (+internal)',
            ];
            if ($useLoopback
                && is_string($resolveHost) && $resolveHost !== '' && $resolveHost !== '127.0.0.1' && $resolveHost !== 'localhost') {
                $curlOpts[CURLOPT_RESOLVE] = [
                    $resolveHost . ':443:127.0.0.1',
                    $resolveHost . ':80:127.0.0.1',
                ];
            }
            curl_setopt_array($ch, $curlOpts);
            if (curl_multi_add_handle($multi, $ch) !== CURLM_OK) {
                curl_close($ch);
                $result['failed'][] = $task;
                continue;
            }
            $handles[] = ['handle' => $ch, 'task' => $task];
        }
        if (empty($handles)) {
            curl_multi_close($multi);
            $result['failed'] = $tasks;
            return $result;
        }

        $deadline = microtime(true) + 5.0;
        do {
            $status = curl_multi_exec($multi, $running);
            if ($status === CURLM_OK && $running > 0) {
                curl_multi_select($multi, 0.2);
            }
        } while ($running > 0 && microtime(true) < $deadline);

        foreach ($handles as $item) {
            $ch = $item['handle'];
            $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = (int) curl_errno($ch);
            $err   = curl_error($ch);
            $conclusive = [CURLE_URL_MALFORMAT, CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT];
            if ($err !== '') {
                if (in_array($errno, $conclusive, true)) {
                    $result['failed'][] = $item['task'];
                } else {
                    $result['uncertain'][] = $item['task'];
                }
            } elseif ($code >= 200 && $code < 300) {
                $result['success'][] = $item['task'];
            } elseif ($code === 408 || $code === 429 || $code >= 500) {
                $result['uncertain'][] = $item['task'];
            } elseif ($code >= 300 && $code < 600) {
                $result['failed'][] = $item['task'];
            } else {
                $result['uncertain'][] = $item['task'];
            }
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        return $result;
    }

    public static function http(array $tasks, bool $useLoopback, string $authToken): array
    {
        if (empty($tasks)) {
            return ['success' => [], 'failed' => [], 'uncertain' => []];
        }

        if ($useLoopback) {
            $loopback = self::detectLoopback();
            if (!is_array($loopback)) {
                return ['success' => [], 'failed' => $tasks, 'uncertain' => []];
            }
            foreach ($tasks as $index => $task) {
                $parts = parse_url((string) ($task['url'] ?? ''));
                if (!is_array($parts)) {
                    continue;
                }
                $path = $parts['path'] ?? '/';
                $query = isset($parts['query']) ? '?' . $parts['query'] : '';
                $tasks[$index]['url'] = $loopback['scheme'] . '://127.0.0.1:' . $loopback['port'] . $path . $query;
            }
        }

        return self::async($tasks, $useLoopback, $authToken);
    }

    public static function canExec(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        return !in_array('exec', $disabled, true);
    }

    public static function resolveCliPhp(): ?string
    {
        if (!self::canExec()) {
            return null;
        }
        static $resolved = false;
        static $cliPhp = null;

        if ($resolved) {
            return $cliPhp;
        }
        $resolved = true;

        if (php_sapi_name() === 'cli' && defined('PHP_BINARY') && PHP_BINARY && is_file(PHP_BINARY)) {
            $cliPhp = PHP_BINARY;
            return $cliPhp;
        }

        $candidates = [
            '/usr/local/bin/php',
            '/usr/bin/php',
            '/opt/cpanel/ea-php85/root/usr/bin/php',
            '/opt/cpanel/ea-php84/root/usr/bin/php',
            '/opt/cpanel/ea-php83/root/usr/bin/php',
            '/opt/cpanel/ea-php82/root/usr/bin/php',
            '/opt/cpanel/ea-php81/root/usr/bin/php',
            'php',
        ];

        $seen = [];
        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;

            if (strpos($candidate, '/') !== false && !is_executable($candidate)) {
                continue;
            }

            $output = [];
            $code = 1;
            @exec(escapeshellarg($candidate) . ' -r ' . escapeshellarg('echo PHP_SAPI;') . (DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null'), $output, $code);

            if ($code === 0 && trim(implode("\n", $output)) === 'cli') {
                $cliPhp = $candidate;
                return $cliPhp;
            }
        }

        return null;
    }

    public static function cli(string $script, int $worker, int $workers, bool $background, string $taskKey = '', string $token = '', int $occurrence = 0): bool
    {
        if (!self::canExec()) {
            return false;
        }
        $file = realpath((dirname(__DIR__, 3) . '/cron') . '/../cronbot/' . ltrim($script, '/'));
        if ($file === false || !is_file($file)) {
            return false;
        }

        $phpBin = self::resolveCliPhp();
        if ($phpBin === null) {
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9_\-]{1,140}:[0-9]{1,3}:[0-9]{1,3}$/', $taskKey)) {
            $taskKey = '';
        }
        if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            $token = '';
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $envParts = [
                'set "BROADCAST_WORKER_ID=' . $worker . '"',
                'set "BROADCAST_WORKERS=' . $workers . '"',
            ];
            if ($taskKey !== '') {
                $envParts[] = 'set "RX_CRON_TASK=' . $taskKey . '"';
                $envParts[] = 'set "RX_CRON_TASK_TOKEN=' . $token . '"';
            }
            if ($occurrence > 0) {
                $envParts[] = 'set "RX_CRON_OCCURRENCE=' . (int) $occurrence . '"';
            }
            $cmd = implode(' && ', $envParts) . ' && ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($file);
            if ($background) {
                $handle = @popen('start /B cmd /C "' . $cmd . '"', 'r');
                if (is_resource($handle)) {
                    @pclose($handle);
                    return true;
                }
                return false;
            }

            $exitCode = 1;
            @exec($cmd . ' > NUL 2>&1', $unused, $exitCode);
            return $exitCode === 0;
        }

        $env = 'BROADCAST_WORKER_ID=' . $worker . ' BROADCAST_WORKERS=' . $workers;
        if ($taskKey !== '') {
            $env .= ' RX_CRON_TASK=' . escapeshellarg($taskKey) . ' RX_CRON_TASK_TOKEN=' . escapeshellarg($token);
        }
        if ($occurrence > 0) {
            $env .= ' RX_CRON_OCCURRENCE=' . (int) $occurrence;
        }
        $cmd = $env . ' ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($file);

        if ($background) {
            $exitCode = 1;
            @exec($cmd . ' > /dev/null 2>&1 &', $unused, $exitCode);
            return $exitCode === 0;
        }

        $exitCode = 1;
        @exec($cmd . ' > /dev/null 2>&1', $unused, $exitCode);
        return $exitCode === 0;
    }
}
