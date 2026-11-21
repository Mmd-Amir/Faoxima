<?php

namespace Faoxima\Core\Cron;

final class Crontab
{
    public static function isShellExecAvailable()
    {
        static $isAvailable;

        if ($isAvailable !== null) {
            return $isAvailable;
        }

        if (!function_exists('shell_exec')) {
            $isAvailable = false;
            return $isAvailable;
        }

        $disabledFunctions = ini_get('disable_functions');
        if (!empty($disabledFunctions) && stripos($disabledFunctions, 'shell_exec') !== false) {
            $isAvailable = false;
            return $isAvailable;
        }

        $isAvailable = true;
        return $isAvailable;
    }

    public static function getCrontabBinary()
    {
        static $resolvedPath;

        if ($resolvedPath !== null) {
            return $resolvedPath ?: null;
        }

        $candidateDirectories = [
            '/usr/local/bin',
            '/usr/bin',
            '/bin',
            '/usr/sbin',
            '/sbin',
        ];

        $environmentPath = getenv('PATH');
        if ($environmentPath !== false && $environmentPath !== '') {
            foreach (explode(PATH_SEPARATOR, $environmentPath) as $pathDirectory) {
                $pathDirectory = trim($pathDirectory);
                if ($pathDirectory !== '' && !in_array($pathDirectory, $candidateDirectories, true)) {
                    $candidateDirectories[] = $pathDirectory;
                }
            }
        }

        foreach ($candidateDirectories as $directory) {
            $executablePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crontab';
            if (@is_file($executablePath) && @is_executable($executablePath)) {
                $resolvedPath = $executablePath;
                return $resolvedPath;
            }
        }

        if (self::isShellExecAvailable()) {
            $whichOutput = @shell_exec('command -v crontab 2>/dev/null');
            if (is_string($whichOutput)) {
                $whichOutput = trim($whichOutput);
                if ($whichOutput !== '' && @is_executable($whichOutput)) {
                    $resolvedPath = $whichOutput;
                    return $resolvedPath;
                }
            }
        }

        $resolvedPath = '';
        $markerPath = APP_ROOT_PATH . '/cron/.crontab_missing_log';
        $markerHandle = @fopen($markerPath, 'c+');
        if ($markerHandle === false) {
            $markerPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_crontab_missing_' . hash('sha256', APP_ROOT_PATH) . '.log';
            $markerHandle = @fopen($markerPath, 'c+');
        }
        if (is_resource($markerHandle)) {
            if (@flock($markerHandle, LOCK_EX | LOCK_NB)) {
                $lastLoggedAt = (int) stream_get_contents($markerHandle);
                $now = time();
                if ($lastLoggedAt <= 0 || $now - $lastLoggedAt >= 3600) {
                    rewind($markerHandle);
                    if (@ftruncate($markerHandle, 0) && @fwrite($markerHandle, (string) $now) !== false) {
                        @fflush($markerHandle);
                        error_log('Unable to locate the crontab executable on this system.');
                    }
                }
                @flock($markerHandle, LOCK_UN);
            }
            @fclose($markerHandle);
        }

        return null;
    }

    public static function runShellCommand($command)
    {
        if (!self::isShellExecAvailable()) {
            error_log('shell_exec is not available; unable to run command: ' . $command);
            return null;
        }

        if (getenv('PATH') === false || trim((string) getenv('PATH')) === '') {
            if (!\Faoxima\Core\Runtime\HostProfile::rx_putenv_disabled()) {
                putenv('PATH=/usr/local/bin:/usr/bin:/bin');
            }
        }

        return shell_exec($command);
    }

    public static function defaultCronStatusMap()
    {
        return [
            'day' => true,
            'volume' => true,
            'remove' => false,
            'remove_volume' => false,
            'test' => false,
            'on_hold' => false,
            'uptime_node' => false,
            'uptime_panel' => false,
        ];
    }

    public static function normalizeCronStatus($rawCronStatus = null, $persist = false)
    {
        $defaults = self::defaultCronStatusMap();
        if (is_string($rawCronStatus)) {
            $decoded = json_decode($rawCronStatus, true);
        } elseif (is_array($rawCronStatus)) {
            $decoded = $rawCronStatus;
        } else {
            $decoded = [];
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $normalized = array_merge($defaults, array_intersect_key($decoded, $defaults));
        foreach ($defaults as $key => $defaultValue) {
            $value = $normalized[$key];
            if (is_string($value)) {
                $lower = strtolower(trim($value));
                $normalized[$key] = in_array($lower, ['1', 'true', 'on', 'yes'], true);
            } else {
                $normalized[$key] = (bool) $value;
            }
        }
        if ($persist && \Faoxima\Core\Database\Db::runtimeReady()) {
            \Faoxima\Core\Database\Db::update('setting', 'cron_status', json_encode($normalized, JSON_UNESCAPED_UNICODE));
        }
        return $normalized;
    }

    public static function getCronTargetUser()
    {
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_docker_env') && \Faoxima\Core\Cache\RedisCache::rx_redis_docker_env()) {
            return 'www-data';
        }

        $currentUser = function_exists('get_current_user') ? trim((string) get_current_user()) : '';
        if ($currentUser !== '') {
            return $currentUser;
        }

        return 'www-data';
    }

    public static function rxCrontabTargetsCurrentProcessUser($cronUser)
    {
        $processUser = function_exists('get_current_user') ? trim((string) get_current_user()) : '';
        if ($processUser === '' && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $pw = @posix_getpwuid(posix_geteuid());
            $processUser = is_array($pw) ? trim((string) ($pw['name'] ?? '')) : '';
        }
        return $processUser !== '' && $processUser === $cronUser;
    }

    public static function rxCrontabCommand($crontabBinary, $cronUser, $extraArgs = '')
    {
        if (self::rxCrontabTargetsCurrentProcessUser($cronUser)) {
            return trim(sprintf('%s %s', escapeshellarg($crontabBinary), $extraArgs));
        }
        return trim(sprintf('%s -u %s %s', escapeshellarg($crontabBinary), escapeshellarg($cronUser), $extraArgs));
    }

    public static function addCronIfNotExists($cronCommand)
    {
        $commands = is_array($cronCommand) ? $cronCommand : [$cronCommand];
        $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
            return $command !== '';
        }));

        if (empty($commands)) {
            return true;
        }

        $logContext = implode('; ', $commands);

        if (!self::isShellExecAvailable()) {
            return true;
        }

        $crontabBinary = self::getCrontabBinary();
        if ($crontabBinary === null) {
            return true;
        }

        $cronUser = self::getCronTargetUser();

        $existingCronJobs = self::runShellCommand(self::rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
        $existingCronJobs = trim((string) $existingCronJobs);
        $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);
        $cronLines = array_values(array_filter(array_map('trim', $cronLines), static function ($line) {
            return $line !== '' && strpos($line, '#') !== 0;
        }));

        $newLineAdded = false;
        foreach ($commands as $command) {
            if (!in_array($command, $cronLines, true)) {
                $cronLines[] = $command;
                $newLineAdded = true;
            }
        }

        if (!$newLineAdded) {
            return true;
        }

        $cronLines = array_values(array_unique($cronLines));
        $cronContent = implode(PHP_EOL, $cronLines) . PHP_EOL;

        $temporaryFile = tempnam(sys_get_temp_dir(), 'cron');
        if ($temporaryFile === false) {
            error_log('Unable to create temporary file for cron job registration.');
            return false;
        }

        if (file_put_contents($temporaryFile, $cronContent) === false) {
            error_log('Unable to write cron configuration to temporary file: ' . $temporaryFile);
            unlink($temporaryFile);
            return false;
        }

        self::runShellCommand(self::rxCrontabCommand($crontabBinary, $cronUser, escapeshellarg($temporaryFile)));
        unlink($temporaryFile);

        return true;
    }

    public static function replaceCronJobsMatchingStatus($pattern, $newCommands)
    {
        $commands = is_array($newCommands) ? $newCommands : [$newCommands];
        $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
            return $command !== '';
        }));

        if (!self::isShellExecAvailable()) {
            return ['status' => 'disabled', 'user' => null];
        }

        $crontabBinary = self::getCrontabBinary();
        if ($crontabBinary === null) {
            return ['status' => 'no_binary', 'user' => null];
        }

        $cronUser = self::getCronTargetUser();

        $existingCronJobs = self::runShellCommand(self::rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
        $existingCronJobs = rtrim((string) $existingCronJobs, "\r\n");
        $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);

        $finalLines = [];
        $registeredCommands = [];
        foreach ($cronLines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '' || strpos($trimmedLine, '#') === 0) {
                $finalLines[] = $line;
                continue;
            }

            if (in_array($trimmedLine, $commands, true)) {
                if (!isset($registeredCommands[$trimmedLine])) {
                    $finalLines[] = $trimmedLine;
                    $registeredCommands[$trimmedLine] = true;
                }
                continue;
            }

            if (!preg_match($pattern, $trimmedLine)) {
                $finalLines[] = $line;
            }
        }

        foreach ($commands as $command) {
            if (!isset($registeredCommands[$command])) {
                $finalLines[] = $command;
                $registeredCommands[$command] = true;
            }
        }

        if ($finalLines === $cronLines) {
            return ['status' => 'success', 'user' => $cronUser];
        }

        $cronContent = implode(PHP_EOL, $finalLines) . PHP_EOL;

        $temporaryFile = tempnam(sys_get_temp_dir(), 'cron');
        if ($temporaryFile === false) {
            error_log('Unable to create temporary file for cron job registration.');
            return ['status' => 'error', 'user' => $cronUser];
        }

        if (file_put_contents($temporaryFile, $cronContent) === false) {
            error_log('Unable to write cron configuration to temporary file: ' . $temporaryFile);
            unlink($temporaryFile);
            return ['status' => 'error', 'user' => $cronUser];
        }

        self::runShellCommand(self::rxCrontabCommand($crontabBinary, $cronUser, escapeshellarg($temporaryFile)));
        unlink($temporaryFile);

        $verifyCronJobs = self::runShellCommand(self::rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
        $verifyCronJobs = rtrim((string) $verifyCronJobs, "\r\n");
        $verifyLines = $verifyCronJobs === '' ? [] : preg_split('/\r?\n/', $verifyCronJobs);
        $verifyOk = true;
        foreach ($commands as $command) {
            $commandCount = 0;
            foreach ($verifyLines as $verifyLine) {
                if (trim($verifyLine) === $command) {
                    $commandCount++;
                }
            }
            if ($commandCount !== 1) {
                $verifyOk = false;
                break;
            }
        }
        if ($verifyOk) {
            foreach ($verifyLines as $verifyLine) {
                $trimmedLine = trim($verifyLine);
                if ($trimmedLine !== '' && strpos($trimmedLine, '#') !== 0 && !in_array($trimmedLine, $commands, true) && preg_match($pattern, $trimmedLine)) {
                    $verifyOk = false;
                    break;
                }
            }
        }

        return ['status' => $verifyOk ? 'success' : 'error', 'user' => $cronUser];
    }

    public static function rxActivecronPattern($domainhosts)
    {
        $projectHost = trim((string) preg_replace('#^https?://#i', '', $domainhosts), '/');
        return '#curl\s+-s\s+["\']?https?://' . preg_quote($projectHost, '#') . '/cron/cron\.php(?:\?[^"\'\s]*)?["\']?(?:\s|$)#i';
    }

    public static function activecronStatus()
    {
        global $domainhosts;

        if (empty($domainhosts)) {
            $domainhosts = $_SERVER['HTTP_HOST'] ?? '';
        }

        if (empty($domainhosts)) {
            error_log('activecron: $domainhosts is empty, skipping cron setup.');
            return ['status' => 'error', 'user' => null];
        }

        $cronCommands = [
            "*/1 * * * * curl -s https://$domainhosts/cron/cron.php >/dev/null 2>&1",
        ];

        return self::replaceCronJobsMatchingStatus(self::rxActivecronPattern($domainhosts), $cronCommands);
    }

    public static function activecron()
    {
        $result = self::activecronStatus();
        return $result['status'] !== 'error';
    }

    public static function rxCronEntryExists($cronCommand)
    {
        if (!self::isShellExecAvailable()) {
            return null;
        }
        $crontabBinary = self::getCrontabBinary();
        if ($crontabBinary === null) {
            return null;
        }
        $cronUser = self::getCronTargetUser();
        $commands = is_array($cronCommand) ? $cronCommand : [$cronCommand];
        $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
            return $command !== '';
        }));
        if (empty($commands)) {
            return null;
        }
        $existingCronJobs = self::runShellCommand(self::rxCrontabCommand($crontabBinary, $cronUser, '-l 2>/dev/null'));
        $existingCronJobs = trim((string) $existingCronJobs);
        $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);
        $cronLines = array_values(array_filter(array_map('trim', $cronLines), static function ($line) {
            return $line !== '' && strpos($line, '#') !== 0;
        }));
        foreach ($commands as $command) {
            if (!in_array($command, $cronLines, true)) {
                return false;
            }
        }
        return true;
    }

    public static function rxEnsureCronRegistered($originLabel = 'unknown')
    {
        if (!self::isShellExecAvailable()) {
            return false;
        }
        return self::activecron();
    }
}
