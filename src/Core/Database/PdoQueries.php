<?php

declare(strict_types=1);

namespace Faoxima\Core\Database;

use PDO;
use PDOStatement;

final readonly class PdoQueries
{
    public function __construct(private PDO $connection)
    {
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->runStatement($sql, $params)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->runStatement($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function fetchScalar(string $sql, array $params = [])
    {
        $value = $this->runStatement($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->runStatement($sql, $params)->rowCount();
    }

    private function runStatement(string $sql, array $params): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        self::bindAll($stmt, $params);
        $stmt->execute();
        return $stmt;
    }

    private static function bindAll(PDOStatement $stmt, array $params): void
    {
        $isAssoc = $params !== [] && !array_is_list($params);

        if ($isAssoc) {
            foreach ($params as $key => $value) {
                $name = ':' . ltrim((string) $key, ':');
                $stmt->bindValue($name, $value, self::pdoType($value));
            }
        } else {
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value, self::pdoType($value));
            }
        }
    }

    private static function pdoType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
    }
}
