<?php

namespace Faoxima\Admin\Web\Support;

use PDO;
use Faoxima\Admin\Security\CsrfToken;
use Faoxima\Admin\Security\WebAccess;

final class Csrf
{
    public static function fx_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_httponly', '1');
        session_start();
    }

    public static function fx_csrf_token(): string
    {
        $_SESSION ??= [];
        return CsrfToken::forSession($_SESSION);
    }

    public static function fx_csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::fx_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function fx_csrf_guard(): void
    {
        $token = self::fx_csrf_token();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $incoming = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!CsrfToken::matches($token, $incoming)) {
            http_response_code(403);
            if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'csrf_invalid']);
            } else {
                echo 'درخواست نامعتبر — توکن CSRF اشتباه است';
            }
            exit;
        }
    }

    public static function fx_admin_decode_ip_entries(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function fx_admin_ip_allowed($rawAdminIp): bool
    {
        return WebAccess::allowsIp($rawAdminIp, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), [self::class, 'fx_admin_decode_ip_entries']);
    }

    public static function fx_admin_find_by_username(PDO $pdo, string $username)
    {
        return WebAccess::findByUsername($pdo, $username);
    }

    public static function fx_admin_require_access(PDO $pdo, ?array $roles = null, int|string|null $installationOwner = null): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::fx_session_start();
        }
        if (empty($_SESSION['user']) || !is_string($_SESSION['user'])) {
            header('Location: login.php', true, 302);
            exit;
        }
        $row = self::fx_admin_find_by_username($pdo, $_SESSION['user']);
        if (!is_array($row) || !$row) {
            session_unset();
            session_destroy();
            header('Location: login.php', true, 302);
            exit;
        }
        if (!self::fx_admin_ip_allowed($row['iplogin'] ?? null)) {
            session_unset();
            session_destroy();
            header('Location: login.php', true, 302);
            exit;
        }
        if ($roles !== null && !WebAccess::allowsRole($row, $roles, $installationOwner)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('دسترسی غیرمجاز');
        }
        return $row;
    }
}
