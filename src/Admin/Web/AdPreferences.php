<?php

namespace Faoxima\Admin\Web;

use PDO;
use Throwable;

final class AdPreferences
{
    public static function dispatch(
        PDO $pdo,
        string $adUser,
        string $action,
        string $method,
        $serverToken,
        $formToken,
        callable $csrfToken,
        callable $fetch,
        callable $respond,
    ): void {
        if ($action === 'modal') {
            if ($method !== 'GET') {
                $respond(['ok' => false, 'code' => 'method'], 405);
            }
            $token = $csrfToken();
            try {
                $st = $pdo->prepare('SELECT ad_modal_hidden_until FROM admin WHERE username = :u LIMIT 1');
                $st->execute([':u' => $adUser]);
                $until = (int)$st->fetchColumn();
            } catch (Throwable $e) {
                $respond(['ok' => false, 'code' => 'server'], 500);
            }
            session_write_close();
            if ($until > time()) {
                $respond(['ok' => true, 'ad' => null, 'csrf' => $token]);
            }
            $respond(['ok' => true, 'ad' => $fetch('modal'), 'csrf' => $token]);
        }

        if ($action === 'dismiss') {
            if ($method !== 'POST') {
                $respond(['ok' => false, 'code' => 'method'], 405);
            }
            $incoming = $serverToken ?? ($formToken ?? '');
            if (!is_string($incoming) || $incoming === '' || !hash_equals($csrfToken(), $incoming)) {
                $respond(['ok' => false, 'code' => 'csrf_invalid'], 403);
            }
            $until = time() + 86400;
            try {
                $up = $pdo->prepare('UPDATE admin SET ad_modal_hidden_until = :t WHERE username = :u');
                $up->execute([':t' => $until, ':u' => $adUser]);
            } catch (Throwable $e) {
                $respond(['ok' => false, 'code' => 'server'], 500);
            }
            $respond(['ok' => true, 'until' => $until]);
        }

        $respond(['ok' => false, 'code' => 'action'], 400);
    }
}
