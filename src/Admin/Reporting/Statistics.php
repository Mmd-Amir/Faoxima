<?php

namespace Faoxima\Admin\Reporting;

use PDO;
use RuntimeException;

final class Statistics
{
    public static function rx_stats_historical_sale_predicate($alias = '')
    {
        $col = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        return "{$col}Status != 'Unpaid' AND {$col}Status != 'Unsuccessful' AND {$col}Status != 'removedbyadmin' AND {$col}Status != 'removebyuser' AND {$col}Status != 'removeTime' AND {$col}Status != 'removevolume' AND {$col}name_product != 'سرویس تست'";
    }

    public static function rx_stats_sales_history_schema()
    {
        return "CREATE TABLE IF NOT EXISTS invoice_sales_history (
            id_invoice VARCHAR(200) PRIMARY KEY,
            id_user VARCHAR(200) NULL,
            time_sell VARCHAR(200) NULL,
            name_product VARCHAR(200) NULL,
            price_product VARCHAR(200) NULL,
            discount_amount VARCHAR(200) NULL,
            discount_code VARCHAR(200) NULL,
            cleaned_up TINYINT NOT NULL DEFAULT 0,
            INDEX idx_ish_user (id_user),
            INDEX idx_ish_time (time_sell)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public static function rx_stats_ensure_sales_history(\PDO $pdo)
    {
        static $ready;
        if ($ready === null) {
            $ready = new \SplObjectStorage();
        }
        if (!$ready->contains($pdo)) {
            $exists = $pdo->query("SHOW TABLES LIKE 'invoice_sales_history'");
            if (!$exists || $exists->fetchColumn() === false) {
                if ($pdo->inTransaction() || $pdo->exec(self::rx_stats_sales_history_schema()) === false) {
                    throw new RuntimeException('Cannot initialize invoice sales history');
                }
            }
            $ready->attach($pdo);
        }
    }

    public static function rx_stats_sales_source(\PDO $pdo)
    {
        self::rx_stats_ensure_sales_history($pdo);
        return "(SELECT i.id_invoice, i.id_user, i.time_sell, i.name_product, i.price_product,
                       i.discount_amount, i.discount_code, i.Status
                FROM invoice i WHERE " . self::rx_stats_historical_sale_predicate('i') . "
                UNION ALL
                SELECT h.id_invoice, h.id_user, h.time_sell, h.name_product, h.price_product,
                       h.discount_amount, h.discount_code, 'active' AS Status
                FROM invoice_sales_history h LEFT JOIN invoice i ON i.id_invoice = h.id_invoice
                WHERE (i.id_invoice IS NULL AND h.cleaned_up = 1) OR i.Status = 'Unsuccessful')";
    }

    public static function rx_stats_preserve_invoice_batch(\PDO $pdo, $where, array $params, $mutation, array $mutationParams = [], $limit = 500)
    {
        self::rx_stats_ensure_sales_history($pdo);
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare("SELECT id_invoice, id_user, time_sell, name_product, price_product,
                discount_amount, discount_code, Status, (" . self::rx_stats_historical_sale_predicate() . ") AS counted
                FROM invoice WHERE {$where} ORDER BY id_invoice LIMIT " . (int) $limit . " FOR UPDATE");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $save = $pdo->prepare("INSERT INTO invoice_sales_history
                (id_invoice, id_user, time_sell, name_product, price_product, discount_amount, discount_code)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE id_user = VALUES(id_user), time_sell = VALUES(time_sell),
                name_product = VALUES(name_product), price_product = VALUES(price_product),
                discount_amount = VALUES(discount_amount), discount_code = VALUES(discount_code), cleaned_up = 0");
            $remove = $pdo->prepare("DELETE FROM invoice_sales_history WHERE id_invoice = ?");
            foreach ($rows as $row) {
                if ($row['counted']) {
                    $save->execute([$row['id_invoice'], $row['id_user'], $row['time_sell'], $row['name_product'],
                        $row['price_product'], $row['discount_amount'], $row['discount_code']]);
                } elseif (strcasecmp((string) $row['Status'], 'Unsuccessful') !== 0) {
                    $remove->execute([$row['id_invoice']]);
                }
            }
            $affected = 0;
            if ($rows) {
                $ids = array_column($rows, 'id_invoice');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                if ($mutation === 'DELETE FROM invoice') {
                    $mark = $pdo->prepare("UPDATE invoice_sales_history SET cleaned_up = 1 WHERE id_invoice IN ({$placeholders})");
                    $mark->execute($ids);
                }
                $stmt = $pdo->prepare($mutation . " WHERE id_invoice IN ({$placeholders})");
                $stmt->execute(array_merge($mutationParams, $ids));
                $affected = $stmt->rowCount();
            }
            if ($ownTx) {
                $pdo->commit();
            }
            return $affected;
        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function rx_stats_operational_service_predicate($alias = '')
    {
        $col = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        return "({$col}Status = 'active' OR {$col}Status = 'end_of_time' OR {$col}Status = 'end_of_volume' OR {$col}Status = 'sendedwarn' OR {$col}Status = 'send_on_hold') AND {$col}name_product != 'سرویس تست'";
    }

    public static function rx_stats_real_payment_predicate($alias = '')
    {
        $col = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        return "{$col}payment_Status = 'paid' AND {$col}Payment_Method NOT IN ('add balance by admin', 'low balance by admin')";
    }

    public static function rx_stats_total_users(\PDO $pdo)
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user");
        return (int) $stmt->fetchColumn();
    }

    public static function rx_stats_total_sales_count(\PDO $pdo)
    {
        $source = self::rx_stats_sales_source($pdo);
        $predicate = self::rx_stats_historical_sale_predicate();
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$source} sales WHERE {$predicate}");
        return (int) $stmt->fetchColumn();
    }

    public static function rx_stats_sales_summary(\PDO $pdo): array
    {
        $source = self::rx_stats_sales_source($pdo);
        $predicate = self::rx_stats_historical_sale_predicate();
        $stmt = $pdo->query("SELECT COUNT(*) AS sales_count,
            COALESCE(SUM(price_product),0) AS sales_amount,
            COUNT(DISTINCT id_user) AS paying_users
            FROM {$source} sales WHERE {$predicate}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'count' => (int) $row['sales_count'],
            'amount' => (float) $row['sales_amount'],
            'paying_users' => (int) $row['paying_users'],
        ];
    }

    public static function rx_stats_operational_summary(\PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT COUNT(*) AS invoice_count,
            COALESCE(SUM(price_product),0) AS invoice_amount
            FROM invoice WHERE " . self::rx_stats_operational_service_predicate());
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['count' => (int) $row['invoice_count'], 'amount' => (float) $row['invoice_amount']];
    }

    public static function rx_stats_total_sales_amount(\PDO $pdo)
    {
        $source = self::rx_stats_sales_source($pdo);
        $predicate = self::rx_stats_historical_sale_predicate();
        $stmt = $pdo->query("SELECT COALESCE(SUM(price_product),0) FROM {$source} sales WHERE {$predicate}");
        return (float) $stmt->fetchColumn();
    }

    public static function rx_stats_paying_users_count(\PDO $pdo)
    {
        $source = self::rx_stats_sales_source($pdo);
        $predicate = self::rx_stats_historical_sale_predicate();
        $stmt = $pdo->query("SELECT COUNT(DISTINCT id_user) FROM {$source} sales WHERE {$predicate}");
        return (int) $stmt->fetchColumn();
    }

    public static function rx_stats_sales_between(\PDO $pdo, $from, $to)
    {
        $source = self::rx_stats_sales_source($pdo);
        $predicate = self::rx_stats_historical_sale_predicate();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count, COALESCE(SUM(price_product),0) AS sum FROM {$source} sales WHERE time_sell >= :from AND time_sell < :to AND {$predicate}");
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to', $to);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'count' => (int) ($row['count'] ?? 0),
            'sum'   => (float) ($row['sum'] ?? 0),
        ];
    }

    public static function rx_stats_income_between(\PDO $pdo, $from, $to)
    {
        return self::rx_stats_sales_between($pdo, $from, $to)['sum'];
    }

    public static function rx_stats_orders_between(\PDO $pdo, $from, $to)
    {
        return self::rx_stats_sales_between($pdo, $from, $to)['count'];
    }

    public static function rx_stats_extend_paid_sum(\PDO $pdo)
    {
        $stmt = $pdo->query("SELECT SUM(price) FROM service_other WHERE type = 'extend_user' AND status = 'paid'");
        $result = $stmt->fetchColumn();
        return $result !== false && $result !== null ? (float) $result : 0.0;
    }

    public static function rx_stats_extend_paid_between(\PDO $pdo, $from, $to)
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count, SUM(price) AS sum FROM service_other WHERE time BETWEEN :from AND :to AND type = 'extend_user' AND status = 'paid'");
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to', $to);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'count' => (int) ($row['count'] ?? 0),
            'sum'   => (float) ($row['sum'] ?? 0),
        ];
    }

    public static function rx_stats_real_payment_totals(\PDO $pdo)
    {
        $predicate = self::rx_stats_real_payment_predicate();
        $stmt = $pdo->query("SELECT SUM(price) AS sumpay, Payment_Method, COUNT(price) AS countpay FROM Payment_report WHERE {$predicate} GROUP BY Payment_Method");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
