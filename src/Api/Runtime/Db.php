<?php

declare(strict_types=1);

namespace Faoxima\Api\Runtime;

use PDO;
use Faoxima\Core\Database\PdoQueries;
use RuntimeException;

final class Db
{
    public static function pdo(): PDO
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            \Faoxima\Api\Runtime\Logger::critical('PDO connection unavailable');
            throw new RuntimeException('Database connection unavailable');
        }
        return $pdo;
    }


    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::queries()->fetchAll($sql, $params);
    }


    public static function fetchOne(string $sql, array $params = []): ?array
    {
        return self::queries()->fetchOne($sql, $params);
    }


    public static function fetchScalar(string $sql, array $params = [])
    {
        return self::queries()->fetchScalar($sql, $params);
    }


    public static function execute(string $sql, array $params = []): int
    {
        return self::queries()->execute($sql, $params);
    }

    private static function queries(): PdoQueries
    {
        $connection = self::pdo();
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        return new PdoQueries($connection);
    }
}

