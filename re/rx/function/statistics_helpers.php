<?php

if (!function_exists('rx_stats_historical_sale_predicate')) {
    function rx_stats_historical_sale_predicate($alias = '')
    {
        $col = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        return "{$col}Status != 'Unpaid' AND {$col}Status != 'Unsuccessful' AND {$col}Status != 'removedbyadmin' AND {$col}Status != 'removebyuser' AND {$col}Status != 'removeTime' AND {$col}Status != 'removevolume' AND {$col}name_product != 'سرویس تست'";
    }
}

if (!function_exists('rx_stats_operational_service_predicate')) {
    function rx_stats_operational_service_predicate($alias = '')
    {
        $col = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        return "({$col}Status = 'active' OR {$col}Status = 'end_of_time' OR {$col}Status = 'end_of_volume' OR {$col}Status = 'sendedwarn' OR {$col}Status = 'send_on_hold') AND {$col}name_product != 'سرویس تست'";
    }
}

if (!function_exists('rx_stats_real_payment_predicate')) {
    function rx_stats_real_payment_predicate($alias = '')
    {
        $col = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        return "{$col}payment_Status = 'paid' AND {$col}Payment_Method NOT IN ('add balance by admin', 'low balance by admin')";
    }
}

if (!function_exists('rx_stats_total_users')) {
    function rx_stats_total_users(\PDO $pdo)
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user");
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('rx_stats_total_sales_count')) {
    function rx_stats_total_sales_count(\PDO $pdo)
    {
        $predicate = rx_stats_historical_sale_predicate();
        $stmt = $pdo->query("SELECT COUNT(*) FROM invoice WHERE {$predicate}");
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('rx_stats_total_sales_amount')) {
    function rx_stats_total_sales_amount(\PDO $pdo)
    {
        $predicate = rx_stats_historical_sale_predicate();
        $stmt = $pdo->query("SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE {$predicate}");
        return (float) $stmt->fetchColumn();
    }
}

if (!function_exists('rx_stats_paying_users_count')) {
    function rx_stats_paying_users_count(\PDO $pdo)
    {
        $predicate = rx_stats_historical_sale_predicate();
        $stmt = $pdo->query("SELECT COUNT(DISTINCT id_user) FROM invoice WHERE {$predicate}");
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('rx_stats_sales_between')) {
    function rx_stats_sales_between(\PDO $pdo, $from, $to)
    {
        $predicate = rx_stats_historical_sale_predicate();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count, COALESCE(SUM(price_product),0) AS sum FROM invoice WHERE time_sell >= :from AND time_sell < :to AND {$predicate}");
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to', $to);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'count' => (int) ($row['count'] ?? 0),
            'sum'   => (float) ($row['sum'] ?? 0),
        ];
    }
}

if (!function_exists('rx_stats_income_between')) {
    function rx_stats_income_between(\PDO $pdo, $from, $to)
    {
        return rx_stats_sales_between($pdo, $from, $to)['sum'];
    }
}

if (!function_exists('rx_stats_orders_between')) {
    function rx_stats_orders_between(\PDO $pdo, $from, $to)
    {
        return rx_stats_sales_between($pdo, $from, $to)['count'];
    }
}

if (!function_exists('rx_stats_extend_paid_sum')) {
    function rx_stats_extend_paid_sum(\PDO $pdo)
    {
        $stmt = $pdo->query("SELECT SUM(price) FROM service_other WHERE type = 'extend_user' AND status = 'paid'");
        $result = $stmt->fetchColumn();
        return $result !== false && $result !== null ? (float) $result : 0.0;
    }
}

if (!function_exists('rx_stats_extend_paid_between')) {
    function rx_stats_extend_paid_between(\PDO $pdo, $from, $to)
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
}

if (!function_exists('rx_stats_real_payment_totals')) {
    function rx_stats_real_payment_totals(\PDO $pdo)
    {
        $predicate = rx_stats_real_payment_predicate();
        $stmt = $pdo->query("SELECT SUM(price) AS sumpay, Payment_Method, COUNT(price) AS countpay FROM Payment_report WHERE {$predicate} GROUP BY Payment_Method");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
