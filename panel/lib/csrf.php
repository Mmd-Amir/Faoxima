<?php


if (!function_exists('fx_session_start')) {

    function fx_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_httponly', '1');
        session_start();
    }
}

if (!function_exists('fx_csrf_token')) {

    function fx_csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('fx_csrf_field')) {

    function fx_csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(fx_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('fx_csrf_guard')) {

    function fx_csrf_guard(): void
    {
        $token = fx_csrf_token();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $incoming = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($incoming) || $incoming === '' || !hash_equals($token, $incoming)) {
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
}

if (!function_exists('fx_admin_decode_ip_entries')) {
    function fx_admin_decode_ip_entries(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('fx_admin_ip_allowed')) {

    function fx_admin_ip_allowed($rawAdminIp): bool
    {
        if ($rawAdminIp === null || $rawAdminIp === '') {
            return true;
        }
        $currentIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (is_array($rawAdminIp)) {
            $entries = $rawAdminIp;
        } else {
            $raw = (string)$rawAdminIp;
            $decoded = fx_admin_decode_ip_entries($raw);
            if ($decoded !== null) {
                $entries = $decoded;
            } else {
                $entries = [$raw];
            }
        }
        foreach ($entries as $entry) {
            $value = strtolower(trim((string)$entry));
            if (in_array($value, ['*', 'all', 'unlimited'], true)) {
                return true;
            }
        }
        foreach ($entries as $entry) {
            $value = trim((string)$entry);
            if ($value !== '' && $value === $currentIp) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('fx_admin_find_by_username')) {
    function fx_admin_find_by_username(PDO $pdo, string $username)
    {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('fx_admin_require_access')) {

    function fx_admin_require_access(PDO $pdo, ?array $roles = null): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            fx_session_start();
        }
        if (empty($_SESSION['user']) || !is_string($_SESSION['user'])) {
            header('Location: login.php', true, 302);
            exit;
        }
        $row = fx_admin_find_by_username($pdo, $_SESSION['user']);
        if (!is_array($row) || !$row) {
            session_unset();
            session_destroy();
            header('Location: login.php', true, 302);
            exit;
        }
        if (!fx_admin_ip_allowed($row['iplogin'] ?? null)) {
            session_unset();
            session_destroy();
            header('Location: login.php', true, 302);
            exit;
        }
        if ($roles !== null) {
            $rule = strtolower(trim((string)($row['rule'] ?? '')));
            $allowed = $rule === '';
            if (!$allowed) {
                foreach ($roles as $role) {
                    if ($rule === strtolower(trim((string)$role))) {
                        $allowed = true;
                        break;
                    }
                }
            }
            if (!$allowed) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                exit('دسترسی غیرمجاز');
            }
        }
        return $row;
    }
}
