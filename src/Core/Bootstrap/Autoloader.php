<?php

declare(strict_types=1);

namespace Faoxima\Core\Bootstrap;

final class Autoloader
{
    private const PREFIX = 'Faoxima\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (!str_starts_with($class, self::PREFIX)) {
            return;
        }

        $segments = explode('\\', substr($class, strlen(self::PREFIX)));
        foreach ($segments as $segment) {
            if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $segment) !== 1) {
                return;
            }
        }

        $file = dirname(__DIR__, 2) . '/' . implode('/', $segments) . '.php';
        if (is_file($file)) {
            require_once self::revalidatedPath($file);
        }
    }

    public static function revalidatedPath(string $file): string
    {
        if (function_exists('opcache_invalidate') && !ini_get('opcache.validate_timestamps')) {
            @opcache_invalidate($file, false);
        }
        return $file;
    }
}
