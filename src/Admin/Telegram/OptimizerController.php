<?php

namespace Faoxima\Admin\Telegram;

use PDO;
use RuntimeException;

class OptimizerController
{
    private PDO $pdo;
    private string $rootDir;

    public function __construct(PDO $pdo, string $rootDir)
    {
        $this->pdo     = $pdo;
        $this->rootDir = rtrim($rootDir, '/\\');
    }

    public function run(): string
    {
        $analyzer = new EnvAnalyzer($this->pdo);
        $data     = $analyzer->toArray();
        $this->writeOptimizationConfig($data);
        if (!$this->writeHostProfileJson($data)) {
            throw new RuntimeException('Host profile could not be saved');
        }
        $data['redis_tuning_applied'] = $this->applyRedisTuning($data['redis_status'] ?? []);
        return $this->formatTelegramResult($data);
    }

    private function applyRedisTuning(array $redisStatus): bool
    {
        $reachable = $redisStatus['reachable'] ?? false;
        $adminEnabled = $redisStatus['admin_enabled'] ?? false;
        if ($reachable !== true || $adminEnabled !== true) {
            return false;
        }
        if (!method_exists(\Faoxima\Core\Cache\RedisCache::class, 'getRedisConnection')) {
            return false;
        }
        $client = \Faoxima\Core\Cache\RedisCache::getRedisConnection();
        if ($client === null) {
            return false;
        }
        $maxMemoryMb = 64;
        try {
            $client->rawCommand('CONFIG', 'SET', 'maxmemory', $maxMemoryMb . 'mb');
            $client->rawCommand('CONFIG', 'SET', 'maxmemory-policy', 'allkeys-lru');
            return true;
        } catch (\Throwable $e) {
            try {
                $client->config('SET', 'maxmemory', $maxMemoryMb . 'mb');
                $client->config('SET', 'maxmemory-policy', 'allkeys-lru');
                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    private function writeOptimizationConfig(array $data): bool
    {
        $allowed = [
            'cron_db_budget', 'cron_time_budget', 'broadcast_workers', 'payment_workers',
            'batch_size', 'soft_time_limit', 'pressure', 'generated_at',
        ];
        $out     = array_intersect_key($data, array_flip($allowed));
        $php     = "<?php\nreturn " . var_export($out, true) . ";\n";
        $tmp     = $this->rootDir . '/optimization_config.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $php, LOCK_EX);
        if ($written !== false) {
            @rename($tmp, $this->rootDir . '/optimization_config.php');
            return true;
        }
        @unlink($tmp);
        return false;
    }

    private function writeHostProfileJson(array $data): bool
    {
        $baseProfile = method_exists(\Faoxima\Core\Runtime\HostProfile::class, 'rx_host_profile') ? \Faoxima\Core\Runtime\HostProfile::rx_host_profile() : [];
        $path     = $this->rootDir . '/cronbot/.runtime/host_profile.json';
        $lock = @fopen($path . '.workers.lock', 'c');
        if ($lock === false) {
            return false;
        }
        $tmp = null;
        try {
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                return false;
            }
            $existing = is_array($baseProfile) ? $baseProfile : [];
            if (@is_file($path)) {
                $raw = @file_get_contents($path);
                $dec = ($raw !== false) ? json_decode($raw, true) : null;
                if (!is_array($dec)) {
                    return false;
                }
                $existing = array_merge($existing, $dec);
            }
            $merge = array_merge($existing, [
                'cron_db_budget'         => $data['cron_db_budget'],
                'cron_time_budget'       => $data['cron_time_budget'],
                'broadcast_workers'      => $data['broadcast_workers'],
                'payment_workers'        => $data['payment_workers'],
                'optimizer_pressure'     => $data['pressure'],
                'optimizer_generated_at' => $data['generated_at'],
            ]);
            unset($merge['manual_workers']);
            $json = json_encode($merge, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (!is_string($json)) {
                return false;
            }
            $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
            if (@file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
                return false;
            }
            $mode = @fileperms($path);
            @chmod($tmp, $mode === false ? 0660 : ($mode & 0666));
            return @rename($tmp, $path);
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                @unlink($tmp);
            }
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    private function pressureEmoji(float $pressure): string
    {
        if ($pressure <= 0.3) {
            return '🟢';
        }
        if ($pressure <= 0.6) {
            return '🟡';
        }
        if ($pressure <= 0.8) {
            return '🟠';
        }
        return '🔴';
    }

    private function formatRedisSection(array $data): string
    {
        $redisStatus = $data['redis_status'] ?? [];
        $extAvailable = $redisStatus['extension'] ?? false;
        $reachable = $redisStatus['reachable'] ?? null;
        $adminEnabled = $redisStatus['admin_enabled'] ?? false;
        $tuningApplied = $data['redis_tuning_applied'] ?? false;

        if (!$extAvailable) {
            $emoji = '🔴';
            $statusLine = 'افزونه نصب نیست';
        } elseif ($reachable !== true) {
            $emoji = '🟠';
            $statusLine = 'افزونه فعال است، اتصال برقرار نیست';
        } elseif (!$adminEnabled) {
            $emoji = '🟡';
            $statusLine = 'متصل است، توسط ادمین فعال نشده';
        } else {
            $emoji = '🟢';
            $statusLine = $tuningApplied ? 'فعال و تنظیم‌شده' : 'فعال (تنظیم maxmemory اعمال نشد)';
        }

        $section = "\n\n🧠 <b>وضعیت Redis:</b> {$emoji} {$statusLine}";
        if (!$extAvailable && !empty($redisStatus['guidance'])) {
            $section .= "\n" . $redisStatus['guidance'];
        }
        return $section;
    }

    private function formatTelegramResult(array $data): string
    {
        $emoji   = $this->pressureEmoji((float) ($data['pressure'] ?? 1.0));
        $pct     = round(($data['pressure'] ?? 0) * 100);
        $genDate = date('Y/m/d H:i:s', (int) ($data['generated_at'] ?? time()));
        $memPct  = round(($data['memory_pressure'] ?? 0) * 100);
        $cpuPct  = round(($data['cpu_pressure'] ?? 0) * 100);
        $dbPct   = round(($data['db_pressure'] ?? 0) * 100);

        return "<b>🖥 گزارش بهینه‌ساز هاست اشتراکی</b>\n\n"
            . "{$emoji} <b>فشار کلی:</b> {$pct}٪\n"
            . "   🧠 حافظه: {$memPct}٪  ⚙️ CPU: {$cpuPct}٪  🗄 دیتابیس: {$dbPct}٪\n\n"
            . "📋 <b>مقادیر بهینه محاسبه‌شده:</b>\n"
            . "  • دسته پردازشی: <code>" . ($data['batch_size'] ?? '-') . "</code> آیتم\n"
            . "  • بودجه زمانی کرون: <code>" . ($data['cron_time_budget'] ?? '-') . "</code> ثانیه\n"
            . "  • اسلات دیتابیس: <code>" . ($data['cron_db_budget'] ?? '-') . "</code>\n"
            . "  • ورکرهای پخش: <code>" . ($data['broadcast_workers'] ?? '-') . "</code>\n"
            . "  • ورکرهای پرداخت: <code>" . ($data['payment_workers'] ?? '-') . "</code>\n"
            . "  • حد نرم زمان: <code>" . ($data['soft_time_limit'] ?? '-') . "</code> ثانیه\n\n"
            . "✅ <b>پیکربندی اعمال شد</b>\n"
            . "<i>تمام کرون‌ها از اجرای بعدی با این تنظیمات کار می‌کنند.</i>"
            . $this->formatRedisSection($data)
            . "\n\n🕐 <i>{$genDate}</i>";
    }
}
