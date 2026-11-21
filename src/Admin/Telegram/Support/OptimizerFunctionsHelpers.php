<?php

namespace Faoxima\Admin\Telegram\Support;

use Throwable;

final class OptimizerFunctionsHelpers
{
    public static function rx_optimizer_path($name)
    {
        static $dir = null;
        if ($dir === null) {
            $candidate = REFACTORED_LEGACY_ROOT . '/storage/cache';
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0775, true);
            }
            $dir = (is_dir($candidate) && is_writable($candidate)) ? $candidate . DIRECTORY_SEPARATOR : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'faoxima_';
        }
        $files = [
            'lock' => 'optimizer_cleanup.lock',
            'progress' => 'optimizer_progress.json',
            'cancel' => 'optimizer_cancel.json',
            'state' => 'optimizer_state.lock',
        ];
        return $dir . $files[$name];
    }

    public static function rx_optimizer_file_read($name)
    {
        $path = self::rx_optimizer_path($name);
        $guard = @fopen(self::rx_optimizer_path('state'), 'c');
        if ($guard !== false) {
            @flock($guard, LOCK_SH);
        }
        $raw = is_file($path) ? @file_get_contents($path) : false;
        if ($guard !== false) {
            @flock($guard, LOCK_UN);
            @fclose($guard);
        }
        $data = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }

    public static function rx_optimizer_file_write($name, $data, $keepSameRun = false)
    {
        $path = self::rx_optimizer_path($name);
        $guard = @fopen(self::rx_optimizer_path('state'), 'c');
        if ($guard !== false) {
            @flock($guard, LOCK_EX);
        }
        try {
            if ($data === null) {
                if (is_file($path)) {
                    @unlink($path);
                }
                return null;
            }
            if ($keepSameRun && is_file($path)) {
                $existing = json_decode((string) @file_get_contents($path), true);
                if (is_array($existing) && ($existing['run_id'] ?? null) === ($data['run_id'] ?? null)) {
                    return $existing;
                }
            }
            $tmp = $path . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE)) === false || !@rename($tmp, $path)) {
                @unlink($tmp);
                error_log('[optimizebot] progress file write failed');
            }
            return $data;
        } finally {
            if ($guard !== false) {
                @flock($guard, LOCK_UN);
                @fclose($guard);
            }
        }
    }

    public static function rx_optimizer_redis_on()
    {
        try {
            return method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function rx_optimizer_progress_read()
    {
        $best = self::rx_optimizer_file_read('progress');
        if (self::rx_optimizer_redis_on()) {
            $raw = \Faoxima\Core\Cache\RedisCache::rx_redis_get(RX_OPT_PROGRESS_KEY);
            $fromRedis = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($fromRedis) && (!is_array($best) || (int) ($fromRedis['updated_at'] ?? 0) >= (int) ($best['updated_at'] ?? 0))) {
                $best = $fromRedis;
            }
        }
        if (is_array($best) && in_array($best['status'] ?? '', ['completed', 'cancelled', 'failed', 'stale'], true)
            && (time() - (int) ($best['updated_at'] ?? 0)) > RX_OPT_FINAL_TTL) {
            return null;
        }
        return $best;
    }

    public static function rx_optimizer_progress_write(array $progress)
    {
        $progress['updated_at'] = time();
        $final = in_array($progress['status'] ?? '', ['completed', 'cancelled', 'failed', 'stale'], true);
        if (self::rx_optimizer_redis_on()) {
            try {
                \Faoxima\Core\Cache\RedisCache::rx_redis_set(RX_OPT_PROGRESS_KEY, json_encode($progress, JSON_UNESCAPED_UNICODE), $final ? RX_OPT_FINAL_TTL : RX_OPT_ACTIVE_TTL);
            } catch (Throwable $e) {
            }
        }
        self::rx_optimizer_file_write('progress', $progress);
        return $progress;
    }

    public static function rx_optimizer_cancel_get($runId, $ownerToken = null)
    {
        $runId = (string) $runId;
        if ($runId === '') {
            return null;
        }
        $cancel = null;
        if (self::rx_optimizer_redis_on()) {
            $raw = \Faoxima\Core\Cache\RedisCache::rx_redis_get(RX_OPT_CANCEL_PREFIX . $runId);
            $cancel = is_string($raw) ? json_decode($raw, true) : null;
        }
        if (!is_array($cancel)) {
            $cancel = self::rx_optimizer_file_read('cancel');
        }
        if (!is_array($cancel) || ($cancel['run_id'] ?? '') !== $runId) {
            return null;
        }
        if ($ownerToken !== null && ($cancel['owner_token'] ?? '') !== $ownerToken) {
            return null;
        }
        return $cancel;
    }

    public static function rx_optimizer_cancel_request(array $progress, $adminId)
    {
        $runId = (string) ($progress['run_id'] ?? '');
        $existing = self::rx_optimizer_cancel_get($runId);
        if ($existing !== null) {
            return $existing;
        }
        $cancel = [
            'run_id' => $runId,
            'owner_token' => (string) ($progress['owner_token'] ?? ''),
            'requested_at' => time(),
            'requested_by' => (string) $adminId,
        ];
        if (self::rx_optimizer_redis_on()) {
            try {
                if (\Faoxima\Core\Cache\RedisCache::rx_redis_set_nx(RX_OPT_CANCEL_PREFIX . $runId, json_encode($cancel), RX_OPT_ACTIVE_TTL * 1000) === false) {
                    $winner = self::rx_optimizer_cancel_get($runId);
                    if ($winner !== null) {
                        return $winner;
                    }
                }
            } catch (Throwable $e) {
            }
        }
        return self::rx_optimizer_file_write('cancel', $cancel, true);
    }

    public static function rx_optimizer_cancel_clear($runId)
    {
        if ((string) $runId !== '' && self::rx_optimizer_redis_on()) {
            try {
                \Faoxima\Core\Cache\RedisCache::rx_redis_del(RX_OPT_CANCEL_PREFIX . $runId);
            } catch (Throwable $e) {
            }
        }
        self::rx_optimizer_file_write('cancel', null);
    }

    public static function rx_optimizer_state()
    {
        $progress = self::rx_optimizer_progress_read();
        if (!is_array($progress) || !in_array($progress['status'] ?? '', ['starting', 'running', 'cancelling'], true)) {
            return $progress;
        }
        $cancel = self::rx_optimizer_cancel_get($progress['run_id'] ?? '');
        if ($cancel !== null) {
            $progress['status'] = 'cancelling';
            $progress['cancel_requested_at'] = $cancel['requested_at'] ?? null;
            $progress['cancel_requested_by'] = $cancel['requested_by'] ?? null;
        }
        if ((time() - (int) ($progress['updated_at'] ?? 0)) < RX_OPT_STALE_AFTER) {
            return $progress;
        }
        $probe = @fopen(self::rx_optimizer_path('lock'), 'c');
        if ($probe === false) {
            return $progress;
        }
        if (!@flock($probe, LOCK_EX | LOCK_NB)) {
            @fclose($probe);
            return $progress;
        }
        try {
            if (self::rx_optimizer_redis_on() && \Faoxima\Core\Cache\RedisCache::rx_redis_get(RX_OPT_LOCK_KEY) !== null) {
                return $progress;
            }
            $current = self::rx_optimizer_progress_read();
            if (!is_array($current) || ($current['run_id'] ?? '') !== ($progress['run_id'] ?? '')
                || !in_array($current['status'] ?? '', ['starting', 'running', 'cancelling'], true)) {
                return is_array($current) ? $current : $progress;
            }
            $current['status'] = 'stale';
            $current['completed_at'] = time();
            $current = self::rx_optimizer_progress_write($current);
            self::rx_optimizer_cancel_clear($current['run_id'] ?? '');
            return $current;
        } finally {
            @flock($probe, LOCK_UN);
            @fclose($probe);
        }
    }

    public static function rx_optimizer_elapsed($seconds)
    {
        $seconds = max(0, (int) $seconds);
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public static function rx_optimizer_render($progress)
    {
        $back = ['text' => "🔙 بازگشت به منوی مدیریت", 'callback_data' => 'admin'];
        $again = ['text' => "🔁 اجرای مجدد", 'callback_data' => 'optimizebot'];
        $retry = ['text' => "🔁 تلاش مجدد", 'callback_data' => 'optimizebot'];
        $refresh = ['text' => "🔄 بروزرسانی وضعیت", 'callback_data' => 'optimizebot_refresh'];
        $cancel = ['text' => "❌ درخواست لغو عملیات", 'callback_data' => 'optimizebot_cancel'];
        if (!is_array($progress)) {
            return ["ℹ️ در حال حاضر هیچ عملیات پاک‌سازی ثبت نشده است.", json_encode(['inline_keyboard' => [[$again], [$back]]])];
        }
        $status = (string) ($progress['status'] ?? '');
        $final = in_array($status, ['completed', 'cancelled', 'failed', 'stale'], true);
        $startedAt = (int) ($progress['started_at'] ?? time());
        $endedAt = $final ? (int) ($progress['completed_at'] ?? $progress['updated_at'] ?? time()) : time();
        $elapsed = self::rx_optimizer_elapsed($endedAt - $startedAt);
        $stage = (string) ($progress['stage'] ?? '');
        $processed = (int) ($progress['processed'] ?? 0);
        $deleted = (int) ($progress['deleted'] ?? 0);
        $marked = (int) ($progress['marked'] ?? 0);
        $valid = (int) ($progress['valid'] ?? 0);
        $errors = (int) ($progress['errors'] ?? 0);
        $completedTime = date('Y/m/d H:i:s', $endedAt);
        if ($status === 'completed') {
            $text = "✅ عملیات پاک‌سازی با موفقیت تمام شد\n\n📦 بررسی‌شده: {$processed}\n🗑 حذف‌شده: {$deleted}\n🏷 علامت‌گذاری‌شده: {$marked}\n✅ معتبر و بدون تغییر: {$valid}\n⚠️ خطاهای موقت: {$errors}\n⏱ زمان کل: {$elapsed}\n🕐 زمان پایان: {$completedTime}";
            if (!empty($progress['details'])) {
                $text .= "\n\n" . $progress['details'];
            }
            return [$text, json_encode(['inline_keyboard' => [[$again], [$back]]])];
        }
        if ($status === 'cancelled') {
            $text = "❌ عملیات پاک‌سازی لغو شد\n\n📦 بررسی‌شده تا زمان لغو: {$processed}\n🗑 حذف‌شده تا زمان لغو: {$deleted}\n🏷 علامت‌گذاری‌شده: {$marked}\n✅ معتبر و بدون تغییر: {$valid}\n⚠️ خطاها: {$errors}\n⚙️ آخرین مرحله: {$stage}\n⏱ زمان کل: {$elapsed}\n🕐 زمان توقف: {$completedTime}\n\nعملیات انجام‌شده قبل از لغو بازگردانی نشده‌اند.";
            return [$text, json_encode(['inline_keyboard' => [[$again], [$back]]])];
        }
        if ($status === 'failed') {
            $text = "❌ عملیات پاک‌سازی کامل نشد\n\n⚙️ آخرین مرحله: {$stage}\n📦 بررسی‌شده: {$processed}\n🗑 حذف‌شده تا این لحظه: {$deleted}\n🏷 علامت‌گذاری‌شده: {$marked}\n⚠️ خطاها: {$errors}\n⏱ زمان سپری‌شده: {$elapsed}\n\nعملیات انجام‌شده قبل از بروز خطا بازگردانی نشده‌اند.";
            return [$text, json_encode(['inline_keyboard' => [[$retry], [$back]]])];
        }
        if ($status === 'stale') {
            $text = "⚠️ عملیات پاک‌سازی قبلی به‌صورت ناقص متوقف شده است\n\n⚙️ آخرین مرحله: {$stage}\n📦 بررسی‌شده: {$processed}\n🗑 حذف‌شده تا این لحظه: {$deleted}\n🏷 علامت‌گذاری‌شده: {$marked}\n⚠️ خطاها: {$errors}\n⏱ زمان سپری‌شده: {$elapsed}\n\nعملیات انجام‌شده قبل از توقف بازگردانی نشده‌اند.";
            return [$text, json_encode(['inline_keyboard' => [[$again], [$back]]])];
        }
        if ($status === 'cancelling') {
            $text = "⏳ درخواست لغو ثبت شد\n\nعملیات پس از پایان دسته فعلی به‌صورت امن متوقف خواهد شد.\n\n⚙️ مرحله فعلی: {$stage}\n📦 بررسی‌شده: {$processed}\n🗑 حذف‌شده تا این لحظه: {$deleted}\n🏷 علامت‌گذاری‌شده: {$marked}\n⏱ زمان سپری‌شده: {$elapsed}";
            return [$text, json_encode(['inline_keyboard' => [[$refresh], [$back]]])];
        }
        $batch = (int) ($progress['current_batch'] ?? 0);
        $stageNumber = (int) ($progress['stage_number'] ?? 0);
        $totalStages = (int) ($progress['total_stages'] ?? RX_OPT_TOTAL_STAGES);
        $updatedTime = date('H:i:s', (int) ($progress['updated_at'] ?? time()));
        $text = "⏳ عملیات پاک‌سازی در حال انجام است\n\n⚙️ مرحله فعلی: {$stage}\n📦 بررسی‌شده: {$processed}\n🗑 حذف‌شده: {$deleted}\n🏷 علامت‌گذاری‌شده: {$marked}\n✅ معتبر و بدون تغییر: {$valid}\n⚠️ خطاها: {$errors}\n📚 دسته فعلی: {$batch}\n📊 مرحله: {$stageNumber} از {$totalStages}\n⏱ زمان سپری‌شده: {$elapsed}\n🕐 آخرین بروزرسانی: {$updatedTime}\n\nبرای مشاهده آخرین وضعیت، روی «بروزرسانی» بزنید.";
        return [$text, json_encode(['inline_keyboard' => [[$refresh], [$cancel], [$back]]])];
    }
}
