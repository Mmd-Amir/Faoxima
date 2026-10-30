<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../re/rx/function/statistics_helpers.php';

$dsn = getenv('RX_STATS_TEST_DSN');
if (!$dsn) {
    fwrite(STDERR, "Set RX_STATS_TEST_DSN to an isolated MySQL test server; the test creates and drops its own database.\n");
    exit(1);
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$pdo = new PDO($dsn, getenv('RX_STATS_TEST_USER') ?: 'root', getenv('RX_STATS_TEST_PASSWORD') ?: '', $options);
$db = 'rx_stats_test_' . bin2hex(random_bytes(8));
$pdo->exec("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$checks = 0;
$equal = function ($expected, $actual, string $label) use (&$checks) {
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }
    $checks++;
};
try {
    $pdo->exec("USE `{$db}`");
    $pdo->exec("CREATE TABLE invoice (
        id_invoice VARCHAR(200) PRIMARY KEY, id_user VARCHAR(200), time_sell VARCHAR(200),
        name_product VARCHAR(200), price_product VARCHAR(200), discount_amount VARCHAR(200) DEFAULT '0',
        discount_code VARCHAR(200), Status VARCHAR(200), invalidated_at INT NULL,
        Service_location VARCHAR(300), username VARCHAR(300)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE Payment_report (price BIGINT, Payment_Method VARCHAR(200), payment_Status VARCHAR(200))");
    $pdo->exec("INSERT INTO Payment_report VALUES (9000, 'gateway', 'paid'), (1000, 'add balance by admin', 'paid')");
    $add = $pdo->prepare("INSERT INTO invoice
        (id_invoice, id_user, price_product, Status, name_product, time_sell, Service_location, username)
        VALUES (?, ?, ?, ?, ?, ?, 'deleted-panel', 'service')");
    $insert = function ($id, $user, $amount, $status = 'active', $name = 'product', $time = '100') use ($add) {
        $add->execute([$id, $user, $amount, $status, $name, $time]);
    };
    $snapshot = function () use ($pdo) {
        return [rx_stats_total_sales_amount($pdo), rx_stats_total_sales_count($pdo),
            rx_stats_paying_users_count($pdo), rx_stats_sales_between($pdo, 0, 200)];
    };
    $change = function ($id, $status) use ($pdo) {
        return rx_stats_preserve_invoice_batch($pdo, 'id_invoice = ?', [$id],
            'UPDATE invoice SET Status = ?, invalidated_at = ?', [$status, 1000], 1);
    };
    $delete = function ($where = '1=1', $params = [], $limit = 500) use ($pdo) {
        return rx_stats_preserve_invoice_batch($pdo, $where, $params, 'DELETE FROM invoice', [], $limit);
    };
    $insert('orphan', 'u1', '120');
    $insert('missing', 'u1', '30');
    $insert('unused', 'u2', '50');
    $insert('expired', 'u3', '70', 'end_of_time');
    $insert('disabled', 'u4', '80', 'disabled');
    $insert('unpaid', 'u5', '999', 'Unpaid');
    $insert('failed', 'u6', '999', 'Unsuccessful');
    $insert('test', 'u7', '999', 'active', 'سرویس تست');
    foreach (['removedbyadmin', 'removebyuser', 'removeTime', 'removevolume'] as $status) {
        $insert($status, 'u8', '999', $status);
    }
    $before = $snapshot();
    $equal([350.0, 5, 4, ['count' => 5, 'sum' => 350.0]], $before, 'baseline exclusions');
    $payments = rx_stats_real_payment_totals($pdo);
    $equal(1, $change('orphan', 'Unsuccessful'), 'orphan panel mutation');
    $equal($before, $snapshot(), 'orphan panel preserves sales immediately');
    $change('orphan', 'Unsuccessful');
    $equal($before, $snapshot(), 'retry status change');
    $change('missing', 'disabledn');
    $change('unused', 'unusedservice');
    $equal($before, $snapshot(), 'other optimizer statuses');
    $equal(1, (int) $pdo->query("SELECT COUNT(*) FROM invoice WHERE " . rx_stats_operational_service_predicate())->fetchColumn(), 'current services remain operational');
    $delete("id_invoice = ?", ['expired']);
    $equal($before, $snapshot(), 'stale live deletion');
    while ($delete() > 0) {
    }
    $equal(0, (int) $pdo->query('SELECT COUNT(*) FROM invoice')->fetchColumn(), 'cleanup still deletes services');
    $equal($before, $snapshot(), 'all cleanup categories retain eligible sales only');
    $equal(5, (int) $pdo->query('SELECT COUNT(*) FROM invoice_sales_history')->fetchColumn(), 'unique archive rows');
    $delete();
    $equal($before, $snapshot(), 'repeated deletion');
    $equal($payments, rx_stats_real_payment_totals($pdo), 'payment totals separate');
    $equal(['count' => 0, 'sum' => 0.0], rx_stats_sales_between($pdo, 101, 200), 'date bounds');
    $equal(350.0, rx_stats_income_between($pdo, 0, 101), 'income uses preserved history');
    $equal(5, rx_stats_orders_between($pdo, 0, 101), 'orders use preserved history');
    $insert('new', 'u9', '25', 'Unpaid');
    $equal($before, $snapshot(), 'new unpaid order excluded');
    $pdo->exec("UPDATE invoice SET Status = 'active' WHERE id_invoice = 'new'");
    $equal(375.0, rx_stats_total_sales_amount($pdo), 'new paid purchase counted');
    $change('new', 'unusedservice');
    $pdo->exec("UPDATE invoice SET price_product = '35' WHERE id_invoice = 'new'");
    $equal(385.0, rx_stats_total_sales_amount($pdo), 'live invoice changes take precedence');
    $pdo->exec("UPDATE invoice SET Status = 'removebyuser' WHERE id_invoice = 'new'");
    $equal($before, $snapshot(), 'refund cancellation after archive excluded');
    $delete('id_invoice = ?', ['new']);
    $equal($before, $snapshot(), 'cancelled archive not resurrected after deletion');
    $insert('hard-refund', 'u9', '60');
    $change('hard-refund', 'unusedservice');
    $pdo->exec("UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = 'hard-refund'");
    $pdo->exec("DELETE FROM invoice WHERE id_invoice = 'hard-refund'");
    $equal($before, $snapshot(), 'refund hard deletion does not resurrect archived sale');
    $insert('rollback', 'u9', '40');
    $beforeRollback = $snapshot();
    try {
        rx_stats_preserve_invoice_batch($pdo, 'id_invoice = ?', ['rollback'], 'UPDATE invoice SET nonexistent_column = 1');
        throw new RuntimeException('Expected mutation failure');
    } catch (PDOException $e) {
        $equal(false, $pdo->inTransaction(), 'failed batch rolled back');
    }
    $equal(0, (int) $pdo->query("SELECT COUNT(*) FROM invoice_sales_history WHERE id_invoice = 'rollback'")->fetchColumn(), 'archive rolled back with failed mutation');
    $equal($beforeRollback, $snapshot(), 'failure preserves totals');
    $pdo->beginTransaction();
    $change('rollback', 'Unsuccessful');
    $equal(true, $pdo->inTransaction(), 'caller transaction remains open');
    $pdo->rollBack();
    $equal('active', $pdo->query("SELECT Status FROM invoice WHERE id_invoice = 'rollback'")->fetchColumn(), 'interrupted transaction restores invoice');
    $equal($beforeRollback, $snapshot(), 'rollback preserves totals');
    $change('rollback', 'Unsuccessful');
    $delete('id_invoice = ?', ['rollback']);
    $equal($beforeRollback, $snapshot(), 'retry after rollback preserves sale once');
    for ($i = 0; $i < 1001; $i++) {
        $insert('batch-' . $i, 'batch-user', '1');
    }
    $beforeBatch = $snapshot();
    $equal(500, $delete("id_invoice LIKE 'batch-%'"), 'first cleanup batch');
    $equal($beforeBatch, $snapshot(), 'partial run preserves sales');
    $equal(500, $delete("id_invoice LIKE 'batch-%'"), 'retried cleanup batch');
    $equal(1, $delete("id_invoice LIKE 'batch-%'"), 'final partial batch');
    $equal(0, $delete("id_invoice LIKE 'batch-%'"), 'completed retry');
    $equal($beforeBatch, $snapshot(), 'multiple batches preserve amount count users dates');
    $insert('archive-failure', 'u10', '90');
    $beforeArchiveFailure = $snapshot();
    $pdo->exec("CREATE TRIGGER fail_archive BEFORE INSERT ON invoice_sales_history FOR EACH ROW
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'simulated archive write failure'");
    try {
        $delete('id_invoice = :id', [':id' => 'archive-failure']);
        throw new RuntimeException('Expected archive failure');
    } catch (PDOException $e) {
        $equal(false, $pdo->inTransaction(), 'archive failure rolls back transaction');
    }
    $equal(1, (int) $pdo->query("SELECT COUNT(*) FROM invoice WHERE id_invoice = 'archive-failure'")->fetchColumn(), 'archive failure prevents cleanup');
    $equal($beforeArchiveFailure, $snapshot(), 'archive failure preserves totals');
    $pdo->exec('DROP TRIGGER fail_archive');
    $delete('id_invoice = :id', [':id' => 'archive-failure']);
    $equal($beforeArchiveFailure, $snapshot(), 'retry after archive write failure');
    $source = rx_stats_sales_source($pdo);
    $equal(1008, (int) $pdo->query("SELECT COUNT(*) FROM {$source} sales")->fetchColumn(), 'shared report source has no duplicates');
    echo "Passed {$checks} optimizer sales history checks.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec("DROP DATABASE `{$db}`");
}
