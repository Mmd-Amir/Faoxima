<?php


declare(strict_types=1);

require_once __DIR__ . '/Logger.php';

if (class_exists('FaoximaDb')) {
    return;
}

final class FaoximaDb
{
    public static function pdo(): PDO
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            FaoximaLogger::critical('PDO connection unavailable');
            throw new RuntimeException('Database connection unavailable');
        }
        return $pdo;
    }


    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        self::bindAll($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        self::bindAll($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }


    public static function fetchScalar(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        self::bindAll($stmt, $params);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }


    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        self::bindAll($stmt, $params);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private static function bindAll(PDOStatement $stmt, array $params): void
    {

        $isAssoc = $params !== [] && array_keys($params) !== range(0, count($params) - 1);

        if ($isAssoc) {
            foreach ($params as $key => $val) {
                $name = ':' . ltrim((string)$key, ':');
                $stmt->bindValue($name, $val, self::pdoType($val));
            }
        } else {
            foreach ($params as $i => $val) {
                $stmt->bindValue($i + 1, $val, self::pdoType($val));
            }
        }
    }

    private static function pdoType($value): int
    {
        if ($value === null) return PDO::PARAM_NULL;
        if (is_bool($value)) return PDO::PARAM_BOOL;
        if (is_int($value)) return PDO::PARAM_INT;
        return PDO::PARAM_STR;
    }
}

