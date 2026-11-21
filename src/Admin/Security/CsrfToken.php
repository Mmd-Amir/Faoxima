<?php

declare(strict_types=1);

namespace Faoxima\Admin\Security;

final class CsrfToken
{
    public static function forSession(array &$session): string
    {
        if (empty($session['csrf_token'])) {
            $session['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $session['csrf_token'];
    }

    public static function matches(string $token, mixed $incoming): bool
    {
        return is_string($incoming) && $incoming !== '' && hash_equals($token, $incoming);
    }
}
