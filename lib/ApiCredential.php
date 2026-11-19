<?php

final class FaoximaApiCredential
{
    private static function path(): string
    {
        return dirname(__DIR__) . '/storage/private/api-token';
    }

    public static function read(): string
    {
        $path = self::path();
        return is_file($path) ? trim((string) file_get_contents($path)) : '';
    }

    public static function write(string $token): void
    {
        $dir = dirname(self::path());
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create private credential directory');
        }
        chmod($dir, 0700);
        $oldMask = umask(0077);
        $tmp = null;
        try {
            $tmp = tempnam($dir, '.token-');
            if ($tmp === false || file_put_contents($tmp, $token, LOCK_EX) === false
                || !rename($tmp, self::path())) {
                throw new RuntimeException('Cannot store API credential');
            }
            chmod(self::path(), 0600);
        } finally {
            umask($oldMask);
            if (is_string($tmp) && is_file($tmp)) unlink($tmp);
        }
        $legacy = dirname(__DIR__) . '/api/hash.txt';
        if (is_file($legacy)) @unlink($legacy);
    }

    public static function redactHeaders($headers): array
    {
        return is_array($headers) ? self::redactAuditFields($headers) : [];
    }

    public static function redactAuditFields(array $fields): array
    {
        foreach ($fields as $name => $value) {
            $key = str_replace('-', '_', strtolower((string) $name));
            if (in_array($key, [
                'authorization', 'cookie', 'token', 'bot_token', 'api_token', 'x_api_key', 'api_key', 'apikey',
                'dl_token', 'secret', 'client_secret', 'password', 'passwd', 'api_password', 'initdata',
                'init_data', 'initdataunsafe', 'tgwebappdata', 'x_cron_token', 'x_cron_task_token',
            ], true)) {
                $fields[$name] = '[redacted]';
            } elseif (is_array($value)) {
                $fields[$name] = self::redactAuditFields($value);
            }
        }
        return $fields;
    }

    public static function validBotToken($provided, string $botToken): bool
    {
        return is_string($provided) && $botToken !== '' && hash_equals($botToken, $provided);
    }

    public static function validHeaders($headers, string $botToken): bool
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        return self::valid($headers['token'] ?? null, $botToken);
    }

    public static function valid($provided, string $botToken): bool
    {
        if (!is_string($provided) || $provided === '') return false;
        foreach ([self::read(), $botToken] as $expected) {
            if ($expected !== '' && hash_equals($expected, $provided)) return true;
        }
        return false;
    }
}
