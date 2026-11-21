<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class LoginRequestHelpers
{
    public static function fx_login_ip_allowed($result, $user_ip): bool
{
    $adminIpOk = true;
    if ($result) {
        $rawAdminIp = $result['iplogin'] ?? null;
        if ($rawAdminIp !== null && $rawAdminIp !== '') {
            $adminIpDecoded = \Faoxima\Admin\Web\Support\Csrf::fx_admin_decode_ip_entries((string)$rawAdminIp);
            if ($adminIpDecoded !== null) {
                if (in_array('*', $adminIpDecoded, true) || in_array('all', $adminIpDecoded, true) || in_array('unlimited', $adminIpDecoded, true)) {
                    $adminIpOk = true;
                } else {
                    $adminIpOk = in_array($user_ip, $adminIpDecoded, true);
                }
            } elseif ($rawAdminIp === '*' || $rawAdminIp === 'all' || $rawAdminIp === 'unlimited') {
                $adminIpOk = true;
            } elseif (filter_var($rawAdminIp, FILTER_VALIDATE_IP)) {
                $adminIpOk = ($rawAdminIp === $user_ip);
            }
        }
    }
    return $adminIpOk;
}

    public static function fx_login_verify_password(PDO $pdo, $result, string $password): bool
{
    $passwordOk = false;
    if ($result) {
        $storedHash = (string)($result["password_hash"] ?? '');
        if ($storedHash !== '' && password_verify($password, $storedHash)) {
            $passwordOk = true;
            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                $rehash = $pdo->prepare("UPDATE admin SET password_hash = :h WHERE id_admin = :id");
                $rehash->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $result['id_admin']]);
            }
        } elseif ($storedHash === '' && (string)$password === (string)($result["password"] ?? '') && $password !== '') {
            $passwordOk = true;
            $migrate = $pdo->prepare("UPDATE admin SET password_hash = :h WHERE id_admin = :id");
            $migrate->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $result['id_admin']]);
        }
    }
    return $passwordOk;
}
}
