<?php

namespace Faoxima\Cron\Health;

use PDO;

final class HealthRun
{
    public static function rx_cron_health_orch_begin(): string
    {
        $inv = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_new_id(8);
        $pending = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_pending();
        $GLOBALS['rxCronHealthOrch'] = [
            'inv' => $inv,
            'entry' => time(),
            'traced' => $pending,
            'stages' => [],
            'claimed' => [],
            'diag' => null,
            'exited' => false,
        ];
        if ($pending) {
            \Faoxima\Cron\Health\HealthStorage::rx_cron_health_journal_append($inv, ['t' => 'orch', 'entry' => time(), 'state' => 'entry']);
        }
        register_shutdown_function([\Faoxima\Cron\Health\HealthRun::class, 'rx_cron_health_orch_shutdown']);
        return $inv;
    }

    public static function rx_cron_health_orch_stage(string $name, string $state): void
    {
        if (!isset($GLOBALS['rxCronHealthOrch']) || !is_array($GLOBALS['rxCronHealthOrch'])) {
            return;
        }
        $GLOBALS['rxCronHealthOrch']['stages'][$name] = $state;
    }

    public static function rx_cron_health_orch_traced(): bool
    {
        return !empty($GLOBALS['rxCronHealthOrch']['traced']);
    }

    public static function rx_cron_health_orch_persist(): bool
    {
        if (!self::rx_cron_health_orch_traced()) {
            return false;
        }
        $o = $GLOBALS['rxCronHealthOrch'];
        $record = [
            'entry' => $o['entry'],
            'stages' => $o['stages'],
            'claimed' => $o['claimed'],
            'diag' => $o['diag'],
            'exited' => $o['exited'],
        ];
        $state = isset($o['stages']['complete']) ? 'complete' : ($o['exited'] ? 'exited' : 'running');
        $pdo = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_current_pdo();
        if ($pdo instanceof PDO && \Faoxima\Cron\Health\HealthStorage::rx_cron_health_ensure_tables($pdo)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO cron_health_event (invocation_id, kind, job_key, worker, state, started_at, finished_at, data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE state = VALUES(state), finished_at = VALUES(finished_at), data = VALUES(data)');
                if ($stmt->execute([$o['inv'], 'orch', '', 0, $state, (int) $o['entry'], $o['exited'] ? time() : 0, json_encode($record), time()])) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }
        return \Faoxima\Cron\Health\HealthStorage::rx_cron_health_journal_append($o['inv'], ['t' => 'orch', 'entry' => $o['entry'], 'state' => $state, 'full' => 1, 'data' => $record]);
    }

    public static function rx_cron_health_orch_shutdown(): void
    {
        if (!self::rx_cron_health_orch_traced()) {
            return;
        }
        $GLOBALS['rxCronHealthOrch']['exited'] = true;
        self::rx_cron_health_orch_persist();
    }

    public static function rx_cron_health_orch_attach_db(PDO $pdo): array
    {
        $result = ['traced' => self::rx_cron_health_orch_traced(), 'claimed' => [], 'active' => false];
        if (!isset($GLOBALS['rxCronHealthOrch']) || !is_array($GLOBALS['rxCronHealthOrch'])) {
            return $result;
        }
        $o = $GLOBALS['rxCronHealthOrch'];
        try {
            $rows = $pdo->query("SELECT test_id, status, deadline, created_at FROM cron_health_test WHERE active_key IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return $result;
        }
        if (empty($rows)) {
            return $result;
        }
        $result['active'] = true;
        $entry = (int) $o['entry'];
        foreach ($rows as $row) {
            if ((string) $row['status'] !== 'waiting' || (int) $row['deadline'] < $entry || (int) $row['created_at'] > $entry) {
                continue;
            }
            $GLOBALS['rxCronHealthOrch']['traced'] = true;
            try {
                $stmt = $pdo->prepare("UPDATE cron_health_test SET status = 'observed', invocation_id = ?, observed_at = ? WHERE test_id = ? AND status = 'waiting' AND invocation_id IS NULL AND claim_token IS NULL");
                $stmt->execute([$o['inv'], $entry, (string) $row['test_id']]);
                if ($stmt->rowCount() === 1) {
                    $result['claimed'][] = (string) $row['test_id'];
                }
            } catch (\Throwable $e) {
            }
        }
        $GLOBALS['rxCronHealthOrch']['claimed'] = $result['claimed'];
        $result['traced'] = self::rx_cron_health_orch_traced();
        if ($result['traced']) {
            self::rx_cron_health_orch_persist();
        }
        return $result;
    }

    public static function rx_cron_health_orch_diagnostics(?PDO $pdo): void
    {
        if (!self::rx_cron_health_orch_traced()) {
            return;
        }
        try {
            $GLOBALS['rxCronHealthOrch']['diag'] = [
                'dirs' => \Faoxima\Cron\Health\HealthStorage::rx_cron_health_fs_checks(),
                'files' => \Faoxima\Cron\Health\HealthStorage::rx_cron_health_required_files(),
            ];
            self::rx_cron_health_orch_stage('diagnostics', 'completed');
        } catch (\Throwable $e) {
            self::rx_cron_health_orch_stage('diagnostics', 'failed');
        }
        \Faoxima\Cron\Health\HealthStorage::rx_cron_health_journal_prune();
        if ($pdo instanceof PDO) {
            try {
                $pdo->prepare('DELETE FROM cron_health_event WHERE created_at < ?')->execute([time() - 3 * 86400]);
                $pdo->prepare('DELETE FROM cron_health_test WHERE active_key IS NULL AND created_at < ?')->execute([time() - 30 * 86400]);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function rx_cron_health_orch_complete(?PDO $pdo, bool $active): void
    {
        if (self::rx_cron_health_orch_traced()) {
            self::rx_cron_health_orch_stage('complete', 'ok');
            self::rx_cron_health_orch_persist();
        }
        if ($active && $pdo instanceof PDO && function_exists('telegram')) {
            \Faoxima\Cron\Health\CronHealth::rx_cron_health_finalize_due($pdo, 3);
        }
    }

    public static function rx_cron_health_load_test(PDO $pdo, string $testId): ?array
    {
        if (!\Faoxima\Cron\Health\HealthStorage::rx_cron_health_valid_id($testId)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM cron_health_test WHERE test_id = ?');
            $stmt->execute([$testId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function rx_cron_health_collect(PDO $pdo, array $test): array
    {
        $from = (int) $test['created_at'];
        $to = (int) $test['deadline'];
        $orchs = [];
        try {
            $stmt = $pdo->prepare("SELECT invocation_id, started_at, data FROM cron_health_event WHERE kind = 'orch' AND started_at >= ? AND started_at <= ? ORDER BY started_at ASC LIMIT 30");
            $stmt->execute([$from, $to]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data = json_decode((string) $row['data'], true);
                $orchs[(string) $row['invocation_id']] = (is_array($data) ? $data : []) + ['entry' => (int) $row['started_at'], 'src' => 'db'];
            }
        } catch (\Throwable $e) {
        }
        foreach (\Faoxima\Cron\Health\HealthStorage::rx_cron_health_journal_scan($from - 5) as $rec) {
            if (($rec['t'] ?? '') !== 'orch') {
                continue;
            }
            $inv = (string) $rec['inv'];
            $entry = (int) ($rec['entry'] ?? 0);
            if ($entry < $from || $entry > $to || (isset($orchs[$inv]) && ($orchs[$inv]['src'] ?? '') === 'db')) {
                continue;
            }
            if (!empty($rec['full']) && is_array($rec['data'] ?? null)) {
                $orchs[$inv] = $rec['data'] + ['entry' => $entry, 'src' => 'file'];
            } elseif (!isset($orchs[$inv])) {
                $orchs[$inv] = ['entry' => $entry, 'stages' => [], 'exited' => false, 'src' => 'file'];
            }
        }
        $claimedInv = (string) ($test['invocation_id'] ?? '');
        $claimed = ($claimedInv !== '' && isset($orchs[$claimedInv])) ? $orchs[$claimedInv] : null;
        if ($claimedInv !== '' && $claimed === null) {
            try {
                $stmt = $pdo->prepare("SELECT data FROM cron_health_event WHERE kind = 'orch' AND invocation_id = ?");
                $stmt->execute([$claimedInv]);
                $data = json_decode((string) $stmt->fetchColumn(), true);
                if (is_array($data)) {
                    $claimed = $data;
                }
            } catch (\Throwable $e) {
            }
        }
        return ['orchs' => $orchs, 'claimed' => $claimed];
    }

    public static function rx_cron_health_failures(array $rec, bool $claimed): array
    {
        $stages = is_array($rec['stages'] ?? null) ? $rec['stages'] : [];
        $failed = [];
        $lock = (string) ($stages['lock'] ?? '');
        if ($lock === 'busy') {
            return ['قفل اصلی اجرای کرون (اجرای قبلی هنوز در جریان بود)'];
        }
        if ($lock === 'open_failed') {
            return ['قفل اصلی اجرای کرون (فایل قفل قابل باز کردن نبود)'];
        }
        $boot = (string) ($stages['bootstrap'] ?? '');
        if ($boot === 'failed' || $boot === 'missing') {
            return ['بارگذاری فایل‌های راه‌اندازی'];
        }
        if (($stages['db'] ?? '') === 'failed') {
            return ['اتصال به دیتابیس'];
        }
        if ($lock === '' || $boot === '' || ($stages['db'] ?? '') === '') {
            return ['پایان کامل اجرای کرون اصلی'];
        }
        if (!$claimed) {
            return ['ثبت تست توسط اجرای کرون'];
        }
        $diag = is_array($rec['diag'] ?? null) ? $rec['diag'] : null;
        if ($diag === null) {
            $failed[] = 'بررسی دسترسی پوشه‌ها';
        } else {
            if (!empty($diag['files'])) {
                $failed[] = 'خواندن فایل‌های پیکربندی: ' . implode('، ', array_map('strval', (array) $diag['files']));
            }
            $steps = ['missing' => 'پوشه وجود ندارد', 'create' => 'ایجاد فایل', 'write' => 'نوشتن', 'read' => 'خواندن', 'lock' => 'قفل فایل', 'delete' => 'حذف فایل'];
            foreach ((array) ($diag['dirs'] ?? []) as $label => $res) {
                if (empty($res['ok'])) {
                    $failed[] = 'دسترسی پوشه ' . $label . ' (' . ($steps[(string) ($res['step'] ?? '')] ?? 'نامشخص') . ')';
                }
            }
        }
        if (!isset($stages['complete'])) {
            $failed[] = 'پایان کامل اجرای کرون اصلی';
        }
        return $failed;
    }

    public static function rx_cron_health_evaluate(array $test, array $c, int $now): array
    {
        $deadline = (int) $test['deadline'];
        if (!empty($test['invocation_id'])) {
            $rec = is_array($c['claimed']) ? $c['claimed'] : null;
            $settled = is_array($rec) && (isset($rec['stages']['complete']) || !empty($rec['exited']));
            if (!$settled && $now < $deadline + \Faoxima\Cron\Health\HealthStorage::rx_cron_health_settle_grace()) {
                return ['verdict' => 'pending', 'observed' => true, 'failed' => []];
            }
            $failed = is_array($rec) ? self::rx_cron_health_failures($rec, true) : ['پایان کامل اجرای کرون اصلی'];
            return ['verdict' => empty($failed) ? 'success' : 'failed', 'observed' => true, 'failed' => $failed];
        }
        if ($now < $deadline) {
            return ['verdict' => 'pending', 'observed' => false, 'failed' => []];
        }
        if (empty($c['orchs'])) {
            return ['verdict' => 'timeout', 'observed' => false, 'failed' => []];
        }
        $best = null;
        foreach ($c['orchs'] as $o) {
            $f = self::rx_cron_health_failures($o, false);
            if ($best === null || count($o['stages'] ?? []) > count($best['o']['stages'] ?? [])) {
                $best = ['o' => $o, 'f' => $f];
            }
        }
        return ['verdict' => 'failed', 'observed' => true, 'failed' => $best['f']];
    }
}
