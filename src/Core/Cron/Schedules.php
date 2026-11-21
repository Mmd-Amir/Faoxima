<?php

namespace Faoxima\Core\Cron;

use PDO;
use Throwable;

final class Schedules
{
    public static function getCronJobDefinitions(): array
    {
        return [
            'statusday' => ['script' => 'statusday.php', 'admin_label' => 'وضعیت روزانه', 'instruction' => '🕚 بررسی وضعیت روزانه — %s', 'default' => ['unit' => 'day', 'value' => 1]],
            'croncard' => ['script' => 'croncard.php', 'admin_label' => 'کارت‌به‌کارت', 'instruction' => '💳 بررسی کارت‌به‌کارت — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'notifications' => ['script' => 'NoticationsService.php', 'admin_label' => 'اعلان‌ها', 'instruction' => '🔔 ارسال اعلان‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'payment_expire' => ['script' => 'payment_expire.php', 'admin_label' => 'انقضای پرداخت', 'instruction' => '⏳ بررسی انقضای پرداخت‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'sendmessage' => ['script' => 'sendmessage.php', 'admin_label' => 'ارسال پیام', 'instruction' => '📨 ارسال پیام زمان‌بندی‌شده — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'plisio' => ['script' => 'plisio.php', 'admin_label' => 'Plisio', 'instruction' => '💰 بررسی پرداخت Plisio — %s', 'default' => ['unit' => 'minute', 'value' => 3]],
            'activeconfig' => ['script' => 'activeconfig.php', 'admin_label' => 'فعال کانفیگ', 'instruction' => '✅ فعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'disableconfig' => ['script' => 'disableconfig.php', 'admin_label' => 'غیرفعال‌کانفیگ', 'instruction' => '⛔ غیرفعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'backupbot' => ['script' => 'backupbot.php', 'admin_label' => 'بکاپ', 'instruction' => '📦 بکاپ‌گیری — %s', 'default' => ['unit' => 'hour', 'value' => 5]],
            'gift' => ['script' => 'gift.php', 'admin_label' => 'هدایا', 'instruction' => '🎁 ارسال هدایا — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'discount_expire' => ['script' => 'discount_expire.php', 'admin_label' => 'انقضای تخفیف', 'instruction' => '🏷 بررسی انقضای تخفیف‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 30]],
            'lottery' => ['script' => 'lottery.php', 'admin_label' => 'قرعه‌کشی شبانه', 'instruction' => '🎁 قرعه‌کشی شبانه — %s', 'default' => ['unit' => 'day', 'value' => 1]],
            'expireagent' => ['script' => 'expireagent.php', 'admin_label' => 'انقضای‌نماینده', 'instruction' => '👥 بررسی انقضای نمایندگان — %s', 'default' => ['unit' => 'minute', 'value' => 30]],
            'on_hold' => ['script' => 'on_hold.php', 'admin_label' => 'سرویس‌های معلق', 'instruction' => '⏸ بررسی سفارش‌های معلق — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'configtest' => ['script' => 'configtest.php', 'admin_label' => 'تست تنظیمات', 'instruction' => '🧪 تست تنظیمات سیستم — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'uptime_node' => ['script' => 'uptime_node.php', 'admin_label' => 'Uptime نود', 'instruction' => '🌐 بررسی Uptime نودها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'uptime_panel' => ['script' => 'uptime_panel.php', 'admin_label' => 'Uptime پنل', 'instruction' => '🖥 بررسی Uptime پنل‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'cryptocheck' => ['script' => 'cryptocheck.php', 'admin_label' => 'چک هش کریپتو', 'instruction' => '🪙 بررسی پرداخت‌های کریپتو — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'nowpaymentcheck' => ['script' => 'nowpaymentcheck.php', 'admin_label' => 'پولر NowPayments', 'instruction' => '💎 بررسی پرداخت‌های NowPayments — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'blupalcheck' => ['script' => 'blupalcheck.php', 'admin_label' => 'پولر بلوپال', 'instruction' => '💙 بررسی پرداخت‌های بلوپال — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'atlaspaycheck' => ['script' => 'atlaspaycheck.php', 'admin_label' => 'پولر اطلس‌پی', 'instruction' => '🌐 بررسی پرداخت‌های اطلس‌پی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'tronadocheck' => ['script' => 'tronadocheck.php', 'admin_label' => 'پولر ترونادو', 'instruction' => '🔷 بازیابی وضعیت پرداخت‌های ترونادو — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'tonpaycheck' => ['script' => 'tonpaycheck.php', 'admin_label' => 'پولر تون‌پی', 'instruction' => '💠 بررسی پرداخت‌های تون‌پی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'rialitcheck' => ['script' => 'rialitcheck.php', 'admin_label' => 'پولر ریالیت', 'instruction' => '💚 بررسی پرداخت‌های ریالیت — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'remnawave_usage' => ['script' => 'remnawave_usage.php', 'admin_label' => 'مصرف رمن‌ویو', 'instruction' => '📊 بررسی مصرف کاربران رمن‌ویو — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'logs_cleanup' => ['script' => 'logs_cleanup.php', 'admin_label' => 'پاکسازی لاگ API', 'instruction' => '🧹 پاکسازی لاگ‌های قدیمی API — %s', 'default' => ['unit' => 'day', 'value' => 7]],
            'pin_expire' => ['script' => 'pin_expire.php', 'admin_label' => 'انقضای پین پیام', 'instruction' => '📌 لغو خودکار پیام‌های پین‌شده منقضی — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'notification_expire' => ['script' => 'notification_expire.php', 'admin_label' => 'انقضای اعلان مینی‌اپ', 'instruction' => '🔔 حذف خودکار اعلان منقضی مینی‌اپ — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'ip_block_notify' => ['script' => 'ip_block_notify.php', 'admin_label' => 'اطلاع مسدودشدن IP', 'instruction' => '📶 اطلاع‌رسانی مسدودشدن IP قبلی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'public_log_drain' => ['script' => 'public_log_drain.php', 'admin_label' => 'گزارش عمومی', 'instruction' => '📣 تخلیه صف گزارش عمومی خرید — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'queued_renewal' => ['script' => 'QueuedRenewalProcessor.php', 'admin_label' => 'رزرو اشتراک', 'instruction' => '🔋 بررسی و فعال‌سازی رزروهای اشتراک — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'fx_rate_sync' => ['script' => 'fx_rate_sync.php', 'admin_label' => 'نرخ دلار (USDT)', 'instruction' => '💵 به‌روزرسانی نرخ USDT/IRT — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'xui_inbound_sync' => ['script' => 'xui_inbound_sync.php', 'admin_label' => 'همگام‌سازی 3x-ui', 'instruction' => '🔄 همگام‌سازی اینباندهای کلاینت‌ها با کلاینت مرجع — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
        ];
    }

    public static function getDefaultCronSchedules(): array
    {
        $defaults = [];
        foreach (self::getCronJobDefinitions() as $key => $definition) $defaults[$key] = $definition['default'];
        return $defaults;
    }

    public static function normalizeCronScheduleConfig(array $config, array $default): array
    {
        $unit = strtolower((string) ($config['unit'] ?? $default['unit'] ?? 'minute'));
        if (!in_array($unit, ['minute', 'hour', 'day', 'disabled'], true)) $unit = $default['unit'] ?? 'minute';
        $value = (int) ($config['value'] ?? $default['value'] ?? 1);
        if ($unit === 'disabled') $value = 1;
        elseif ($value < 1) $value = (int) ($default['value'] ?? 1);
        return ['unit' => $unit, 'value' => max(1, $value)];
    }

    public static function rx_cron_schedule_interval_seconds(array $config): int
    {
        $unit = strtolower((string) ($config['unit'] ?? 'minute'));
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'day') return $value * 86400;
        if ($unit === 'hour') return $value * 3600;
        return $value * 60;
    }

    public static function rx_cron_schedule_occurrence(array $config, int $now, int $targetHour = 0): int
    {
        $unit = strtolower((string) ($config['unit'] ?? 'minute'));
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled' || $now <= 0) {
            return 0;
        }
        if ($unit !== 'day') {
            $interval = self::rx_cron_schedule_interval_seconds($config);
            $start = (int) strtotime(date('Y-m-d', $now) . ' 00:00:00');
            return $start + (int) (floor(($now - $start) / $interval) * $interval);
        }
        $targetHour = max(0, min(23, $targetHour));
        $candidate = strtotime(date('Y-m-d', $now) . ' 00:00:00') + ($targetHour * 3600);
        if ($candidate > $now) {
            $candidate = strtotime('-1 day', $candidate);
        }
        for ($i = 0; $i < 370; $i++) {
            if (((int) date('z', $candidate) % $value) === 0) {
                break;
            }
            $candidate = strtotime('-1 day', $candidate);
        }
        return (int) $candidate;
    }

    public static function rx_cron_job_due(string $jobKey, array $config, int $now, array $runtimeState, int $targetHour = 0): ?int
    {
        $unit = strtolower((string) ($config['unit'] ?? 'minute'));
        if ($unit === 'disabled' || $jobKey === '') {
            return null;
        }
        $lastRun = isset($runtimeState[$jobKey]) ? (int) $runtimeState[$jobKey] : 0;
        if ($lastRun > $now + 300) {
            $lastRun = 0;
        }
        if ($unit === 'day') {
            $occurrence = self::rx_cron_schedule_occurrence($config, $now, $targetHour);
            if ($occurrence <= 0 || $now - $occurrence > 3600 || $lastRun >= $occurrence) {
                return null;
            }
            return $occurrence;
        }
        $occurrence = self::rx_cron_schedule_occurrence($config, $now, $targetHour);
        return $occurrence > 0 && $now - $occurrence <= 3600 && $lastRun < $occurrence ? $occurrence : null;
    }

    public static function rx_cron_claim_job_occurrence(PDO $pdo, string $jobKey, int $occurrence): bool
    {
        if (trim($jobKey) === '' || $occurrence <= 0) {
            return false;
        }
        $definition = self::getCronJobDefinitions()[$jobKey] ?? [];
        $default = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
        $unit = (string) ($default['unit'] ?? 'minute');
        $value = max(1, (int) ($default['value'] ?? 1));
        $enabled = $unit === 'disabled' ? 0 : 1;
        try {
            self::ensureCronRuntimeStateTable($pdo);
            $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (:job_key, :last_run, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE last_run = IF(last_run < VALUES(last_run), VALUES(last_run), last_run)');
            $stmt->execute([':job_key' => $jobKey, ':last_run' => $occurrence, ':unit' => $unit, ':value' => $value, ':enabled' => $enabled]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function ensureCronRuntimeStateTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cron_runtime_state (
            job_key VARCHAR(255) PRIMARY KEY,
            last_run BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            unit VARCHAR(20) NOT NULL DEFAULT 'minute',
            value INT(10) UNSIGNED NOT NULL DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function loadCronRuntimeState(PDO $pdo): array
    {
        $state = [];
        $stmt = $pdo->query('SELECT job_key, last_run FROM cron_runtime_state');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $state[(string) $row['job_key']] = (int) $row['last_run'];
        return $state;
    }

    public static function setCronJobLastRun(PDO $pdo, string $jobKey, int $timestamp): void
    {
        if (trim($jobKey) === '') return;
        $definition = self::getCronJobDefinitions()[$jobKey] ?? [];
        $default = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
        $unit = (string) ($default['unit'] ?? 'minute');
        $value = max(1, (int) ($default['value'] ?? 1));
        $enabled = $unit === 'disabled' ? 0 : 1;
        $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (:job_key, :last_run, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE last_run = VALUES(last_run)');
        $stmt->execute([':job_key' => $jobKey, ':last_run' => $timestamp, ':unit' => $unit, ':value' => $value, ':enabled' => $enabled]);
    }

    public static function loadCronSchedules(): array
    {
        $definitions = self::getCronJobDefinitions();
        $schedules = self::getDefaultCronSchedules();
        $pdo = \Faoxima\Core\Database\Db::getDatabaseConnection();
        if (!($pdo instanceof PDO)) return $schedules;
        try {
            $stmt = $pdo->query('SELECT job_key, unit, value, enabled FROM cron_runtime_state');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobKey = trim((string) ($row['job_key'] ?? ''));
                if ($jobKey === '' || !isset($definitions[$jobKey])) continue;
                $config = ['unit' => (string) $row['unit'], 'value' => (int) $row['value']];
                if (isset($row['enabled']) && (int) $row['enabled'] === 0) $config['unit'] = 'disabled';
                $schedules[$jobKey] = self::normalizeCronScheduleConfig($config, $definitions[$jobKey]['default']);
            }
        } catch (Throwable $e) {
            error_log('loadCronSchedules: ' . $e->getMessage());
        }
        return $schedules;
    }

    public static function updateCronSchedule(string $jobKey, array $config): bool
    {
        $definitions = self::getCronJobDefinitions();
        if (!isset($definitions[$jobKey])) return false;
        $pdo = \Faoxima\Core\Database\Db::getDatabaseConnection();
        if (!($pdo instanceof PDO)) return false;
        $normalized = self::normalizeCronScheduleConfig($config, $definitions[$jobKey]['default']);
        $enabled = $normalized['unit'] === 'disabled' ? 0 : 1;
        try {
            self::ensureCronRuntimeStateTable($pdo);
            $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, unit, value, enabled) VALUES (:job_key, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE unit = VALUES(unit), value = VALUES(value), enabled = VALUES(enabled)');
            return $stmt->execute([':job_key' => $jobKey, ':unit' => $normalized['unit'], ':value' => $normalized['value'], ':enabled' => $enabled]);
        } catch (Throwable $e) {
            error_log('updateCronSchedule: ' . $e->getMessage());
            return false;
        }
    }

    public static function describeCronSchedule(array $config): string
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return 'غیرفعال';
        $labels = ['minute' => 'دقیقه', 'hour' => 'ساعت', 'day' => 'روز'];
        return sprintf('هر %d %s', $value, $labels[$unit] ?? 'دقیقه');
    }

    public static function shouldRunCronJob(array $config, $minuteOrJobKey = null, ?int $hour = null, ?int $dayOfYear = null): bool
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return false;


        if (is_string($minuteOrJobKey) && $minuteOrJobKey !== '') {
            $jobKey = $minuteOrJobKey;
            $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
            if (!($pdo instanceof PDO)) return true;

            try {
                $state = method_exists(\Faoxima\Core\Cron\Schedules::class, 'loadCronRuntimeState') ? self::loadCronRuntimeState($pdo) : [];
            } catch (\Throwable $e) {
                $state = [];
            }
            $lastRun = isset($state[$jobKey]) ? (int) $state[$jobKey] : 0;
            $now = time();
            return self::rx_cron_job_due($jobKey, $config, $now, [$jobKey => $lastRun]) !== null;
        }


        $minute = (int) $minuteOrJobKey;
        $hour = (int) $hour;
        $dayOfYear = (int) $dayOfYear;
        if ($unit === 'minute') return $minute % $value === 0;
        if ($unit === 'hour')   return $minute === 0 && $hour % $value === 0;
        if ($unit === 'day')    return $minute === 0 && $hour === 0 && $dayOfYear % $value === 0;
        return false;
    }

    public static function shouldRunCronJobNow(string $jobKey, array $config): bool
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return false;

        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return true;

        try {
            $state = method_exists(\Faoxima\Core\Cron\Schedules::class, 'loadCronRuntimeState') ? self::loadCronRuntimeState($pdo) : [];
        } catch (\Throwable $e) {
            $state = [];
        }

        $lastRun = isset($state[$jobKey]) ? (int) $state[$jobKey] : 0;
        $now = time();
        $occurrence = self::rx_cron_job_due($jobKey, $config, $now, [$jobKey => $lastRun]);
        if ($occurrence === null) {
            return false;
        }

        try {
            return self::rx_cron_claim_job_occurrence($pdo, $jobKey, $occurrence);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function buildCronScriptUrlByHost(string $domainHost, string $script): string
    {
        $domainHost = preg_replace('#^https?://#i', '', trim($domainHost));
        return 'https://' . rtrim($domainHost, '/') . '/cronbot/' . ltrim($script, '/');
    }

    public static function buildCronInstructionDetails(string $domainHost): string
    {
        $schedules = self::loadCronSchedules();
        $parts = [];
        foreach (self::getCronJobDefinitions() as $key => $definition) {
            $description = self::describeCronSchedule($schedules[$key] ?? $definition['default']);
            $title = sprintf($definition['instruction'], $description);
            if ($key === 'backupbot') {
                $command = 'php ' . escapeshellarg(REFACTORED_LEGACY_ROOT . '/cronbot/backupbot.php');
                $parts[] = "<b>{$title}</b>\n<code>" . htmlspecialchars($command, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
                continue;
            }
            $endpoint = self::buildCronScriptUrlByHost($domainHost, $definition['script']);
            $parts[] = "<b>{$title}</b>\n<code>curl " . htmlspecialchars($endpoint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
        }
        return implode("\n\n", $parts);
    }

    public static function buildCronJobsKeyboard(): string
    {
        $definitions = self::getCronJobDefinitions();
        $schedules = self::loadCronSchedules();
        $rows = [];
        foreach ($definitions as $key => $definition) {
            $schedule = $schedules[$key] ?? $definition['default'];
            $rows[] = [
                ['text' => '⚙️ تنظیمات', 'callback_data' => "cronjob_config-{$key}"],
                ['text' => self::describeCronSchedule($schedule), 'callback_data' => 'cronjob_display'],
                ['text' => $definition['admin_label'], 'callback_data' => 'cronjob_display'],
            ];
        }
        $rows[] = [['text' => '⚙️ تنظیم ورکرها', 'callback_data' => 'cronworkers_settings']];
        $rows[] = [['text' => '🔙 بازگشت به تنظیمات کرون', 'callback_data' => 'featcat_crons']];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function rx_cron_table_exists(PDO $pdo, string $table): ?bool
    {
        static $cache = [];
        $key = strtolower($table);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $stmt->execute([$table]);
            $exists = $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            $exists = null;
        }
        $cache[$key] = $exists;
        return $exists;
    }

    public static function rx_cron_work_probe(PDO $pdo, string $sql, array $params = [], ?string $table = null): ?bool
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            if ($table !== null && $table !== '' && self::rx_cron_table_exists($pdo, $table) === false) {
                return false;
            }
            return null;
        }
    }

    public static function rx_cron_work_combine(array $results): ?bool
    {
        $unknown = false;
        foreach ($results as $result) {
            if (is_callable($result)) {
                try {
                    $result = $result();
                } catch (Throwable $e) {
                    $result = null;
                }
            }
            if ($result === true) {
                return true;
            }
            if ($result !== false) {
                $unknown = true;
            }
        }
        return $unknown ? null : false;
    }

    public static function rx_cron_job_work_available(string $jobKey, ?PDO $pdo = null): ?bool
    {
        $pdo = $pdo instanceof PDO ? $pdo : (\Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null);
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $now = time();
        $paymentWindow = $jobKey === 'payment_expire' ? '1=1' : (is_callable([\Faoxima\Payments\Fulfillment\PaymentPolicy::class, 'cronWindowSql']) ? \Faoxima\Payments\Fulfillment\PaymentPolicy::cronWindowSql() : '1=0');
        if ($jobKey === 'sendmessage') {
            return \Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_work_available();
        }
        if ($jobKey === 'gift') {
            $cronbot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'cronbot';
            if (is_file($cronbot . DIRECTORY_SEPARATOR . 'gift') && is_file($cronbot . DIRECTORY_SEPARATOR . 'username.json')) {
                return true;
            }
            $pendingFile = $cronbot . DIRECTORY_SEPARATOR . 'gift_notify_pending.json';
            if (is_file($pendingFile)) {
                $pending = json_decode((string) @file_get_contents($pendingFile), true);
                if (is_array($pending)) {
                    foreach ($pending as $entry) {
                        if (is_array($entry) && ($entry['state'] ?? '') === 'pending'
                            && (int) ($entry['next_attempt_at'] ?? 0) <= $now
                            && (int) ($entry['created_at'] ?? 0) >= $now - 3600
                            && (int) ($entry['created_at'] ?? 0) <= $now) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }
        switch ($jobKey) {
            case 'activeconfig':
                return self::rx_cron_work_probe($pdo, "SELECT 1 FROM user WHERE checkstatus = '1' LIMIT 1");
            case 'disableconfig':
                return self::rx_cron_work_probe($pdo, "SELECT 1 FROM user WHERE checkstatus = '2' LIMIT 1");
            case 'configtest':
                return self::rx_cron_work_probe($pdo, "SELECT 1 FROM invoice WHERE name_product = ? AND status != 'disabled' LIMIT 1", ['سرویس تست']);
            case 'notifications':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')"
                    . " AND name_product != ? AND (time_cron <= ? OR time_cron IS NULL) LIMIT 1",
                    ['سرویس تست', (string) ($now - 3600)]
                );
            case 'plisio':
                return self::rx_cron_work_probe($pdo, "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'plisio' LIMIT 1");
            case 'blupalcheck':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'blupal'"
                    . " AND blupal_invoice_id IS NOT NULL AND blupal_invoice_id <> '' LIMIT 1"
                );
            case 'nowpaymentcheck':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'nowpayment'"
                    . " AND dec_not_confirmed IS NOT NULL AND dec_not_confirmed <> '' LIMIT 1"
                );
            case 'tronadocheck':
                if (!defined('TRONADO_V2_WINDOW_SECONDS') || !defined('TRONADO_V2_RECENT_SECONDS')
                    || !defined('TRONADO_V2_POLL_RECENT_SECONDS') || !defined('TRONADO_V2_POLL_OLD_SECONDS')
                    || !defined('RX_PAYMENT_PROCESSING_VERSION')) {
                    return null;
                }
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method IN ('Currency Rial 2','iranpay2')"
                    . " AND payment_processing_version >= " . (int) RX_PAYMENT_PROCESSING_VERSION
                    . " AND payment_Status IN ('Unpaid','pending','waiting','expire','reject')"
                    . " AND (tronado_local_state IS NULL OR tronado_local_state NOT IN ('manual_review','dispute_review','completed'))"
                    . " AND tronado_created_at >= ?"
                    . " AND ("
                    . "   (tronado_created_at >= ? AND (tronado_last_checked_at IS NULL OR tronado_last_checked_at <= ?))"
                    . "   OR (tronado_created_at < ? AND (tronado_last_checked_at IS NULL OR tronado_last_checked_at <= ?))"
                    . " ) LIMIT 1",
                    [
                        (int) ($now - TRONADO_V2_WINDOW_SECONDS),
                        (int) ($now - TRONADO_V2_RECENT_SECONDS),
                        (int) ($now - TRONADO_V2_POLL_RECENT_SECONDS),
                        (int) ($now - TRONADO_V2_RECENT_SECONDS),
                        (int) ($now - TRONADO_V2_POLL_OLD_SECONDS),
                    ]
                );
            case 'tonpaycheck':
                if (!defined('TONPAY_CUSTOM_LATE_RECONCILE_SECONDS') || !defined('TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS')) {
                    return null;
                }
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'tonpay' AND ("
                    . " (tonpay_invoice_id IS NOT NULL AND tonpay_invoice_id <> ''"
                    . " AND (payment_Status = 'Unpaid'"
                    . "   OR (tonpay_gateway_mode = 'custom' AND payment_Status IN ('expire','reject','cancelled')"
                    . "       AND (tonpay_local_state IS NULL OR tonpay_local_state <> 'closed')"
                    . "       AND tonpay_created_at >= ?)))"
                    . " OR (tonpay_gateway_mode = 'custom' AND payment_Status = 'Unpaid' AND ("
                    . "   (tonpay_receipt_submitted_at IS NOT NULL AND tonpay_receipt_submitted_at > 0 AND tonpay_review_deadline_at < ?)"
                    . "   OR ((tonpay_receipt_submitted_at IS NULL OR tonpay_receipt_submitted_at = 0) AND tonpay_created_at < ?)"
                    . " )) ) LIMIT 1",
                    [(int) ($now - TONPAY_CUSTOM_LATE_RECONCILE_SECONDS), (int) $now, (int) ($now - TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS)]
                );
            case 'rialitcheck':
                return self::rx_cron_work_combine([
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM rialit_webhook_event WHERE state IN ('pending','retry') AND next_attempt_at <= ? AND EXISTS (SELECT 1 FROM Payment_report WHERE Payment_report.id_order = rialit_webhook_event.order_id AND $paymentWindow) LIMIT 1",
                        [$now],
                        'rialit_webhook_event'
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'rialit'"
                        . " AND payment_Status IN ('Unpaid','pending','waiting','expire','cancelled')"
                        . " AND rialit_created_at >= ?"
                        . " AND (rialit_last_checked_at IS NULL OR rialit_last_checked_at <= ?) LIMIT 1",
                        [(int) ($now - 172800), (int) ($now - 120)]
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'rialit'"
                        . " AND payment_Status = 'paid' AND COALESCE(direct_payment_done, 0) = 0 LIMIT 1"
                    ),
                ]);
            case 'atlaspaycheck':
                if (!defined('ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS') || !defined('ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS')
                    || !defined('RX_PAYMENT_PROCESSING_VERSION')) {
                    return null;
                }
                $rxLate = (int) ($now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS);
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'atlaspay' AND ("
                    . " (atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> '' AND ("
                    . "   ((atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND payment_Status = 'Unpaid')"
                    . "   OR ((atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND payment_Status = 'expire'"
                    . "       AND payment_processing_version >= " . (int) RX_PAYMENT_PROCESSING_VERSION
                    . "       AND atlaspay_created_at >= ?)"
                    . "   OR (atlaspay_gateway_mode = 'custom' AND payment_Status = 'Unpaid')"
                    . "   OR (atlaspay_gateway_mode = 'custom' AND payment_Status IN ('expire','reject')"
                    . "       AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'closed')"
                    . "       AND atlaspay_created_at >= ?)"
                    . " ))"
                    . " OR (atlaspay_gateway_mode = 'custom' AND atlaspay_card_number IS NOT NULL AND atlaspay_created_at < ?)"
                    . " ) LIMIT 1",
                    [$rxLate, $rxLate, (int) ($now - ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS)]
                );
            case 'queued_renewal':
                $pendingFile = REFACTORED_LEGACY_ROOT . '/cronbot/.runtime/renewal_notify_pending.json';
                if (is_file($pendingFile)) {
                    $pending = json_decode((string) @file_get_contents($pendingFile), true);
                    if (is_array($pending)) {
                        foreach ($pending as $entry) {
                            if (is_array($entry) && ($entry['state'] ?? '') === 'pending' && (int) ($entry['next_attempt_at'] ?? 0) <= $now
                                && (int) ($entry['created_at'] ?? 0) >= $now - 3600
                                && (int) ($entry['created_at'] ?? 0) <= $now) {
                                return true;
                            }
                        }
                    }
                }
                return self::rx_cron_work_combine([
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM queued_renewal WHERE status IN ('pending','retry','reconciling')"
                        . " AND (next_attempt_at IS NULL OR next_attempt_at <= " . (int) $now . ") LIMIT 1"
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM queued_renewal WHERE status = 'processing'"
                        . " AND lease_expires_at IS NOT NULL AND lease_expires_at < " . (int) $now . " LIMIT 1"
                    ),
                    static fn() => self::rx_cron_work_probe($pdo, "SELECT 1 FROM renewal_operation WHERE state IN ('applying','uncertain') LIMIT 1"),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM renewal_operation WHERE source = 'admin' AND state = 'verified'"
                        . " AND finalize_state IS NOT NULL"
                        . " AND (finalize_state = 'pending' OR notify_state IN ('pending','failed','sending')) LIMIT 1"
                    ),
                ]);
            case 'payment_expire':
                $rxCutoff = date('Y/m/d H:i:s', $now - 1800);
                $rxTonpayCutoff = date('Y/m/d H:i:s', $now - 86400);
                $rxReceiptCutoff = date('Y/m/d H:i:s', $now - 600);
                return self::rx_cron_work_combine([
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid'"
                        . " AND (crypto_currency IS NULL OR crypto_currency = '')"
                        . " AND Payment_Method != 'tonpay' AND Payment_Method != 'rialit' AND time < ? LIMIT 1",
                        [$rxCutoff]
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM card_sms_payment csp INNER JOIN Payment_report pr ON pr.id_order = csp.id_order"
                        . " WHERE csp.state = 'open' AND csp.deposit_id IS NULL AND csp.expires_at <= ?"
                        . " AND pr.payment_Status = 'Unpaid' LIMIT 1",
                        [(int) $now],
                        'card_sms_payment'
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' AND time < ? LIMIT 1",
                        [$rxTonpayCutoff]
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM checkout_order WHERE status IN ('awaiting_choice','confirmed')"
                        . " AND expires_at > 0 AND expires_at <= ? LIMIT 1",
                        [(int) $now]
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'pending'"
                        . " AND dec_not_confirmed = 'receipt-uploading'"
                        . " AND COALESCE(NULLIF(at_updated, ''), time) < ? LIMIT 1",
                        [$rxReceiptCutoff]
                    ),
                ]);
            case 'remnawave_usage':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM remnawave_users WHERE status = 'active'"
                    . " AND panel_user_id IS NOT NULL AND panel_user_id <> '' LIMIT 1",
                    [],
                    'remnawave_users'
                );
            case 'pin_expire':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM pinned_messages WHERE unpin_at IS NOT NULL AND unpin_at <= ? LIMIT 1",
                    [(int) $now]
                );
            case 'notification_expire':
                return self::rx_cron_work_probe($pdo, "SELECT 1 FROM notification WHERE expires_at <= ? LIMIT 1", [(int) $now]);
            case 'public_log_drain':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM PublicLog_Queue WHERE (sent_at IS NULL AND attempts < 5)"
                    . " OR (sent_at IS NOT NULL AND sent_at < (NOW() - INTERVAL 3 DAY)) LIMIT 1"
                );
            case 'xui_inbound_sync':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM xui_sync_panel WHERE enabled = 1"
                    . " OR (enabled = 0 AND reference_email IS NOT NULL AND reference_email <> '' AND last_check_at < ?) LIMIT 1",
                    [(int) ($now - 600)]
                );
            case 'expireagent':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM user WHERE expire IS NOT NULL AND expire < ? LIMIT 1",
                    [(int) $now]
                );
            case 'discount_expire':
                return self::rx_cron_work_combine([
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM DiscountSell WHERE (status IS NULL OR status = 'active')"
                        . " AND time IS NOT NULL AND time <> '' AND time <> '0'"
                        . " AND CAST(time AS UNSIGNED) <= ? LIMIT 1",
                        [(int) $now]
                    ),
                    static fn() => self::rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Discount WHERE (status IS NULL OR status = 'active')"
                        . " AND expire_at IS NOT NULL AND expire_at <> '' AND expire_at <> '0'"
                        . " AND CAST(expire_at AS UNSIGNED) <= ? LIMIT 1",
                        [(int) $now]
                    ),
                ]);
            case 'ip_block_notify':
                return self::rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM invoice INNER JOIN marzban_panel ON marzban_panel.name_panel = invoice.Service_location"
                    . " WHERE marzban_panel.type IN ('x-ui_single','rebecca') AND marzban_panel.ip_limit_guard = 'onipguard'"
                    . " AND invoice.Status = 'active' AND invoice.ip_limit IS NOT NULL AND invoice.ip_limit != '0' LIMIT 1"
                );
        }
        return null;
    }
}
