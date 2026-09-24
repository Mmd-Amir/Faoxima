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
        if (!is_array($headers)) return [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), ['token', 'authorization', 'x-api-key', 'cookie'], true)) {
                $headers[$name] = '[redacted]';
            }
        }
        return $headers;
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
