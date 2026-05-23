<?php


declare(strict_types=1);

if (class_exists('FaoximaInput')) {
    return;
}

final class FaoximaInput
{

    public static function payload(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $payload = [];

        if (!empty($_GET)) {
            foreach ($_GET as $k => $v) {
                $payload[$k] = $v;
            }
        }

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = array_merge($payload, $decoded);
                }
            }
            if (!empty($_POST)) {
                $payload = array_merge($payload, $_POST);
            }
        }

        return self::sanitise($payload);
    }

    public static function int(array $data, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $data)) return $default;
        $v = $data[$key];
        if (is_numeric($v)) return (int)$v;
        return $default;
    }

    public static function intMin(array $data, string $key, int $min, int $default): int
    {
        $v = self::int($data, $key, $default);
        return $v < $min ? $default : $v;
    }

    public static function intRange(array $data, string $key, int $min, int $max, int $default): int
    {
        $v = self::int($data, $key, $default);
        if ($v < $min) return $default;
        if ($v > $max) return $max;
        return $v;
    }

    public static function string(array $data, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $data)) return $default;
        $v = $data[$key];
        if (is_string($v)) return trim($v);
        if (is_numeric($v)) return (string)$v;
        return $default;
    }

    public static function nullableString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data)) return null;
        $v = $data[$key];
        if (!is_string($v) && !is_numeric($v)) return null;
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }

    public static function array(array $data, string $key): array
    {
        if (!array_key_exists($key, $data)) return [];
        $v = $data[$key];
        return is_array($v) ? $v : [];
    }


    private static function sanitise($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = self::sanitise($v);
            }
            return $out;
        }
        if (is_string($data)) {
            return trim($data);
        }
        return $data;
    }
}

