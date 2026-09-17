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
