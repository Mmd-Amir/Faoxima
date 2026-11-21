<?php

declare(strict_types=1);

namespace Faoxima\Admin\Security;

use PDO;

final class WebAccess
{
    public static function findByUsername(PDO $pdo, string $username): array|false
    {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function allowsIp(mixed $raw, string $currentIp, callable $decode): bool
    {
        if ($raw === null || $raw === '') {
            return true;
        }
        $entries = is_array($raw) ? $raw : ($decode((string) $raw) ?? [(string) $raw]);
        foreach ($entries as $entry) {
            $value = trim((string) $entry);
            if (in_array(strtolower($value), ['*', 'all', 'unlimited'], true) || ($value !== '' && $value === $currentIp)) {
                return true;
            }
        }
        return false;
    }

    public static function allowsRole(array $admin, array $roles, int|string|null $installationOwner = null): bool
    {
        $rule = strtolower(trim((string) ($admin['rule'] ?? '')));
        if ($rule === '') {
            $ownerId = trim((string) $installationOwner);
            if ($ownerId === '' || !ctype_digit($ownerId) || (string) ($admin['id_admin'] ?? '') !== $ownerId) {
                return false;
            }
            $rule = 'administrator';
        }
        foreach ($roles as $role) {
            if ($rule === strtolower(trim((string) $role))) {
                return true;
            }
        }
        return false;
    }
}
