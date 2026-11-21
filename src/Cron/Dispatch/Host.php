<?php

namespace Faoxima\Cron\Dispatch;

use Faoxima\Core\Database\Db;
use Faoxima\Core\Runtime\HostProfile;
use Throwable;

final class Host
{
    public static function sweepStaleRuntime(string $root): void
    {
        $cronbotDir = $root . '/cron' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cronbot';
        if (!is_dir($cronbotDir)) {
            return;
        }
        foreach ((array) @glob($cronbotDir . '/*.lock') as $stale) {
            if ((time() - (int) @filemtime($stale)) <= 120) {
                continue;
            }
            $handle = @fopen($stale, 'c');
            if ($handle === false) {
                continue;
            }
            if (@flock($handle, LOCK_EX | LOCK_NB)) {
                @unlink($stale);
                @flock($handle, LOCK_UN);
            }
            @fclose($handle);
        }
        foreach (['_db_unavailable.flag', '_db_unavailable.log', '_missing_files.log'] as $legacy) {
            $legacyPath = $cronbotDir . DIRECTORY_SEPARATOR . $legacy;
            if (is_file($legacyPath) && (time() - (int) @filemtime($legacyPath)) > 120) {
                @unlink($legacyPath);
            }
        }
        if (is_dir($cronbotDir . DIRECTORY_SEPARATOR . '.dbslots')) {
            foreach ((array) @glob($cronbotDir . '/.dbslots/*') as $slot) {
                $handle = @fopen($slot, 'c');
                if ($handle === false) {
                    continue;
                }
                if (@flock($handle, LOCK_EX | LOCK_NB)) {
                    @unlink($slot);
                    @flock($handle, LOCK_UN);
                }
                @fclose($handle);
            }
            @rmdir($cronbotDir . DIRECTORY_SEPARATOR . '.dbslots');
        }
    }

    public static function resolveBaseUrl($domainHosts): callable
    {
        $host = null;
        if (isset($domainHosts) && is_string($domainHosts) && trim($domainHosts) !== '') {
            $host = $domainHosts;
        }
        if ($host === null || trim((string) $host) === '') {
            $host = $_SERVER['HTTP_HOST'] ?? null;
        }
        if ($host === null || trim((string) $host) === '') {
            $host = 'localhost';
        }

        $hostConfig = $host;
        if (!preg_match('~^https?://~i', $hostConfig)) {
            $hostConfig = 'https://' . ltrim($hostConfig);
        }

        $parts    = parse_url($hostConfig);
        $scheme   = $parts['scheme'] ?? 'https';
        $hostOnly = $parts['host']   ?? 'localhost';
        $basePath = rtrim($parts['path'] ?? '', '/');

        return static function (string $script) use ($scheme, $hostOnly, $basePath): string {
            $script = ltrim($script, '/');
            return $scheme . '://' . $hostOnly . $basePath . '/cronbot/' . $script;
        };
    }

    public static function workerPlan(string $root): array
    {
        $jobHours = [];
        $hostProfile = [];
        if (method_exists(HostProfile::class, 'rx_host_profile')) {
            try {
                $hostProfile = HostProfile::rx_host_profile();
            } catch (Throwable $e) {
                $hostProfile = [];
            }
        }
        $broadcastWorkers = max(1, (int) ($hostProfile['broadcast_workers'] ?? 3));
        $paymentWorkers   = max(1, (int) ($hostProfile['payment_workers'] ?? 2));
        $sharedProfile    = (($hostProfile['profile'] ?? 'shared') === 'shared');
        $settingRow = null;
        try {
            $settingRow = Db::select('setting', '*');
            if (is_array($settingRow)) {
                $jobHours['lottery']   = max(0, min(23, (int) ($settingRow['lottery_hour']   ?? 0)));
                $jobHours['statusday'] = max(0, min(23, (int) ($settingRow['statusday_hour'] ?? 0)));
                if (isset($settingRow['broadcast_workers'])) {
                    $broadcastWorkers = max(1, min(8, (int) $settingRow['broadcast_workers']));
                }
                if (isset($settingRow['payment_workers'])) {
                    $paymentWorkers = max(1, min(8, (int) $settingRow['payment_workers']));
                }
            }
        } catch (Throwable $e) {
        }
        if ($sharedProfile) {
            $broadcastWorkers = 1;
            $paymentWorkers = 1;
            @set_time_limit(300);
        }
        if (!empty($hostProfile['manual_workers']) && method_exists(HostProfile::class, 'rx_cron_worker_counts')) {
            $manual = HostProfile::rx_cron_worker_counts($hostProfile, is_array($settingRow) ? $settingRow : []);
            $broadcastWorkers = $manual['broadcast_workers'];
            $paymentWorkers = $manual['payment_workers'];
        }
        $jobWorkerCounts = [
            'sendmessage'   => $broadcastWorkers,
            'notifications' => $broadcastWorkers,
            'plisio'        => $paymentWorkers,
            'croncard'      => $paymentWorkers,
        ];

        $maxConcurrency = $sharedProfile ? 2 : 3;
        if (isset($hostProfile['cron_max_concurrency'])) {
            $maxConcurrency = (int) $hostProfile['cron_max_concurrency'];
        }
        try {
            $optConfigFile = $root . '/optimization_config.php';
            if (is_file($optConfigFile)) {
                $optConfig = @include $optConfigFile;
                if (is_array($optConfig) && isset($optConfig['cron_max_concurrency'])) {
                    $maxConcurrency = (int) $optConfig['cron_max_concurrency'];
                }
            }
        } catch (Throwable $e) {
        }
        if (isset($settingRow['cron_max_concurrency']) && (int) $settingRow['cron_max_concurrency'] > 0) {
            $maxConcurrency = (int) $settingRow['cron_max_concurrency'];
        }
        $maxConcurrency = max(1, min(8, $maxConcurrency));

        return [
            'jobHours' => $jobHours,
            'jobWorkerCounts' => $jobWorkerCounts,
            'sharedProfile' => $sharedProfile,
            'maxConcurrency' => $maxConcurrency,
        ];
    }
}
