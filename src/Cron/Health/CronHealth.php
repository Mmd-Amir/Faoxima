<?php

namespace Faoxima\Cron\Health;

use PDO;
use PDOException;

final class CronHealth
{
    public static function rx_cron_health_render(array $eval): string
    {
        switch ($eval['verdict']) {
            case 'success':
                return "✅ <b>تست کرون موفق بود.</b>\n\nفراخوانی کرون اصلی، راه‌اندازی، اتصال دیتابیس و دسترسی پوشه‌های موردنیاز با موفقیت تأیید شد.";
            case 'timeout':
                return "❌ <b>تست کرون ناموفق بود.</b>\n\nدر بازهٔ بررسی، هیچ فراخوانی از کرون اصلی مشاهده نشد.";
            case 'failed':
                $lines = array_map(static function ($f) {
                    return '• ' . \Faoxima\Cron\Health\HealthStorage::rx_cron_health_h($f);
                }, $eval['failed']);
                return "❌ <b>تست کرون ناموفق بود.</b>\n\nبررسی‌های ناموفق:\n" . implode("\n", $lines);
        }
        return !empty($eval['observed'])
            ? "🧪 <b>تست کرون</b>\n\n⏳ اجرای کرون اصلی مشاهده شد؛ منتظر پایان اجرا هستیم."
            : "🧪 <b>تست کرون</b>\n\n⏳ منتظر اجرای بعدی کرون اصلی هستیم. نتیجه در همین پیام نمایش داده می‌شود.";
    }

    public static function rx_cron_health_keyboard(string $testId, bool $final): string
    {
        $rows = $final
            ? [[['text' => '🧪 تست مجدد کرون', 'callback_data' => 'cronhealth_start']]]
            : [[['text' => '🔄 بررسی نتیجه', 'callback_data' => 'cronhealth_chk_' . $testId]]];
        $rows[] = [['text' => '🔙 بازگشت به کرون‌ها', 'callback_data' => 'featcat_crons']];
        return (string) json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function rx_cron_health_deliver(array $test, string $text, string $keyboard): array
    {
        $mid = (int) ($test['message_id'] ?? 0);
        if (!function_exists('telegram')) {
            return ['ok' => false, 'message_id' => $mid];
        }
        if ($mid > 0) {
            $res = telegram('editMessageText', [
                'chat_id' => $test['chat_id'],
                'message_id' => $mid,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard,
            ]);
            if (is_array($res) && !empty($res['ok'])) {
                return ['ok' => true, 'message_id' => $mid];
            }
            $desc = strtolower((string) (is_array($res) ? ($res['description'] ?? '') : ''));
            if (strpos($desc, 'message is not modified') !== false) {
                return ['ok' => true, 'message_id' => $mid];
            }
            $gone = false;
            foreach (['message to edit not found', 'message_id_invalid', "message can't be edited", 'message identifier is not specified'] as $needle) {
                if (strpos($desc, $needle) !== false) {
                    $gone = true;
                    break;
                }
            }
            if (!$gone) {
                return ['ok' => false, 'message_id' => $mid];
            }
        }
        $res = telegram('sendMessage', [
            'chat_id' => $test['chat_id'],
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard,
        ]);
        if (is_array($res) && !empty($res['ok'])) {
            return ['ok' => true, 'message_id' => (int) ($res['result']['message_id'] ?? 0)];
        }
        return ['ok' => false, 'message_id' => $mid];
    }

    public static function rx_cron_health_claim(PDO $pdo, string $testId, int $lease): ?string
    {
        $token = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_new_id(8);
        try {
            $stmt = $pdo->prepare("UPDATE cron_health_test SET claim_token = ?, claim_until = ? WHERE test_id = ? AND status IN ('waiting', 'observed') AND claim_until < ?");
            $stmt->execute([$token, time() + $lease, $testId, time()]);
            return $stmt->rowCount() === 1 ? $token : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function rx_cron_health_release(PDO $pdo, string $testId, string $token): void
    {
        try {
            $pdo->prepare('UPDATE cron_health_test SET claim_token = NULL, claim_until = 0 WHERE test_id = ? AND claim_token = ?')->execute([$testId, $token]);
        } catch (\Throwable $e) {
        }
    }

    public static function rx_cron_health_finalize(PDO $pdo, string $testId): string
    {
        $test = \Faoxima\Cron\Health\HealthRun::rx_cron_health_load_test($pdo, $testId);
        if ($test === null) {
            return 'missing';
        }
        if (!in_array((string) $test['status'], ['waiting', 'observed'], true)) {
            return (string) $test['status'];
        }
        if (\Faoxima\Cron\Health\HealthRun::rx_cron_health_evaluate($test, \Faoxima\Cron\Health\HealthRun::rx_cron_health_collect($pdo, $test), time())['verdict'] === 'pending') {
            return 'pending';
        }
        $token = self::rx_cron_health_claim($pdo, $testId, 45);
        if ($token === null) {
            return 'busy';
        }
        $test = \Faoxima\Cron\Health\HealthRun::rx_cron_health_load_test($pdo, $testId);
        if ($test === null) {
            return 'missing';
        }
        $eval = \Faoxima\Cron\Health\HealthRun::rx_cron_health_evaluate($test, \Faoxima\Cron\Health\HealthRun::rx_cron_health_collect($pdo, $test), time());
        if ($eval['verdict'] === 'pending') {
            self::rx_cron_health_release($pdo, $testId, $token);
            return 'pending';
        }
        $text = self::rx_cron_health_render($eval);
        $result = (string) json_encode(['verdict' => $eval['verdict'], 'failed' => $eval['failed'], 'text' => $text, 'finalized_at' => time()], JSON_UNESCAPED_UNICODE);
        $res = self::rx_cron_health_deliver($test, $text, self::rx_cron_health_keyboard($testId, true));
        try {
            if ($res['ok']) {
                $pdo->prepare("UPDATE cron_health_test SET status = 'reported', active_key = NULL, result = ?, reported_at = ?, message_id = ?, claim_token = NULL, claim_until = 0 WHERE test_id = ? AND claim_token = ?")
                    ->execute([$result, time(), (int) $res['message_id'], $testId, $token]);
                return 'reported';
            }
            $attempts = (int) $test['report_attempts'] + 1;
            if ($attempts >= 5) {
                $pdo->prepare("UPDATE cron_health_test SET status = 'undelivered', active_key = NULL, result = ?, report_attempts = ?, claim_token = NULL, claim_until = 0 WHERE test_id = ? AND claim_token = ?")
                    ->execute([$result, $attempts, $testId, $token]);
                return 'undelivered';
            }
            $pdo->prepare('UPDATE cron_health_test SET result = ?, report_attempts = ?, claim_token = NULL, claim_until = ? WHERE test_id = ? AND claim_token = ?')
                ->execute([$result, $attempts, time() + 20, $testId, $token]);
        } catch (\Throwable $e) {
        }
        return 'retry';
    }

    public static function rx_cron_health_finalize_due(PDO $pdo, int $limit = 3): void
    {
        try {
            $stmt = $pdo->prepare("SELECT test_id FROM cron_health_test WHERE active_key IS NOT NULL AND status IN ('waiting', 'observed') AND (deadline <= ? OR status = 'observed') AND claim_until < ? ORDER BY deadline ASC LIMIT " . max(1, min(10, $limit)));
            $stmt->execute([time(), time()]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return;
        }
        foreach ((array) $ids as $id) {
            self::rx_cron_health_finalize($pdo, (string) $id);
        }
    }

    public static function rx_cron_health_webhook_tick(): void
    {
        try {
            $marker = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_read();
            if (empty($marker)) {
                return;
            }
            $now = time();
            $due = false;
            foreach ($marker as $deadline) {
                if ($deadline <= $now) {
                    $due = true;
                    break;
                }
            }
            if (!$due) {
                return;
            }
            $dir = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_journal_dir(true) ?? \Faoxima\Cron\Health\HealthStorage::rx_cron_health_runtime_dir();
            $stamp = $dir . DIRECTORY_SEPARATOR . 'tick.stamp';
            if (@is_file($stamp) && ($now - (int) @filemtime($stamp)) < 5) {
                return;
            }
            @touch($stamp);
            if (!function_exists('telegram') || !\Faoxima\Core\Database\Db::runtimeReady()) {
                return;
            }
            $pdo = \Faoxima\Core\Database\Db::getDatabaseConnection();
            if (!($pdo instanceof PDO)) {
                return;
            }
            self::rx_cron_health_finalize_due($pdo, 3);
            \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_sync($pdo, false);
        } catch (\Throwable $e) {
        }
    }

    public static function rx_cron_health_answer($callbackId, string $text, bool $alert = false): void
    {
        if (empty($callbackId) || !function_exists('telegram')) {
            return;
        }
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => $text,
                'show_alert' => $alert,
                'cache_time' => 0,
            ]);
        } catch (\Throwable $e) {
        }
    }

    public static function rx_cron_health_admin_action($pdo, string $data, $adminId, $callbackId, $messageId): void
    {
        if (!($pdo instanceof PDO) || !\Faoxima\Cron\Health\HealthStorage::rx_cron_health_ensure_tables($pdo)) {
            self::rx_cron_health_answer($callbackId, '❌ دیتابیس در دسترس نیست؛ تست کرون قابل ثبت نیست.', true);
            return;
        }
        $adminId = (int) $adminId;
        if ($data === 'cronhealth_start') {
            self::rx_cron_health_start($pdo, $adminId, $callbackId);
            return;
        }
        if (preg_match('/^cronhealth_chk_([a-f0-9]{16})$/', $data, $m)) {
            self::rx_cron_health_check($pdo, $m[1], $adminId, $callbackId, (int) $messageId);
            return;
        }
        self::rx_cron_health_answer($callbackId, '❔ درخواست نامعتبر');
    }

    public static function rx_cron_health_start(PDO $pdo, int $adminId, $callbackId): void
    {
        $activeKey = (string) $adminId;
        try {
            $stmt = $pdo->prepare('SELECT test_id FROM cron_health_test WHERE active_key = ?');
            $stmt->execute([$activeKey]);
            $existing = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $existing = false;
        }
        if (is_string($existing) && $existing !== '') {
            self::rx_cron_health_finalize($pdo, $existing);
            $still = \Faoxima\Cron\Health\HealthRun::rx_cron_health_load_test($pdo, $existing);
            if (is_array($still) && in_array((string) $still['status'], ['waiting', 'observed'], true)) {
                self::rx_cron_health_answer($callbackId, '⏳ یک تست کرون در جریان است؛ نتیجه در همان پیام اعلام می‌شود.', true);
                return;
            }
        }
        $now = time();
        $window = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_window();
        $testId = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_new_id(8);
        $marker = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_read();
        $marker[$testId] = $now + $window;
        foreach ($marker as $id => $deadline) {
            if ($deadline < $now - 3600) {
                unset($marker[$id]);
            }
        }
        $markerOk = \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_write($marker, true);
        try {
            $stmt = $pdo->prepare("INSERT INTO cron_health_test (test_id, admin_id, chat_id, message_id, active_key, created_at, deadline, status, meta) VALUES (?, ?, ?, 0, ?, ?, ?, 'waiting', ?)");
            $stmt->execute([$testId, $adminId, $adminId, $activeKey, $now, $now + $window, json_encode(['window' => $window, 'marker' => $markerOk])]);
        } catch (\Throwable $e) {
            \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_sync($pdo, false);
            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                self::rx_cron_health_answer($callbackId, '⏳ یک تست کرون در جریان است؛ نتیجه در همان پیام اعلام می‌شود.', true);
            } else {
                self::rx_cron_health_answer($callbackId, '❌ ثبت تست کرون ناموفق بود.', true);
            }
            return;
        }
        self::rx_cron_health_answer($callbackId, '⏳ تست کرون ثبت شد.');
        $test = \Faoxima\Cron\Health\HealthRun::rx_cron_health_load_test($pdo, $testId);
        if ($test === null) {
            return;
        }
        $text = self::rx_cron_health_render(['verdict' => 'pending', 'observed' => false, 'failed' => []]);
        $res = self::rx_cron_health_deliver($test, $text, self::rx_cron_health_keyboard($testId, false));
        if ($res['ok'] && $res['message_id'] > 0) {
            try {
                $pdo->prepare('UPDATE cron_health_test SET message_id = ? WHERE test_id = ? AND message_id = 0')->execute([$res['message_id'], $testId]);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function rx_cron_health_check(PDO $pdo, string $testId, int $adminId, $callbackId, int $messageId): void
    {
        $test = \Faoxima\Cron\Health\HealthRun::rx_cron_health_load_test($pdo, $testId);
        if ($test === null) {
            self::rx_cron_health_answer($callbackId, '❔ این تست پیدا نشد؛ یک تست جدید شروع کنید.', true);
            return;
        }
        if ($messageId > 0 && (int) $test['message_id'] !== $messageId && (int) $test['chat_id'] === $adminId) {
            try {
                $pdo->prepare("UPDATE cron_health_test SET message_id = ? WHERE test_id = ? AND status IN ('waiting', 'observed')")->execute([$messageId, $testId]);
            } catch (\Throwable $e) {
            }
        }
        $state = self::rx_cron_health_finalize($pdo, $testId);
        $test = \Faoxima\Cron\Health\HealthRun::rx_cron_health_load_test($pdo, $testId);
        if ($test === null) {
            self::rx_cron_health_answer($callbackId, '❔ این تست پیدا نشد.', true);
            return;
        }
        if ($state === 'reported') {
            self::rx_cron_health_answer($callbackId, 'نتیجه ثبت شد.');
            \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_sync($pdo, false);
            return;
        }
        $view = $test;
        if ($messageId > 0) {
            $view['message_id'] = $messageId;
            $view['chat_id'] = $adminId;
        }
        if (!in_array((string) $test['status'], ['waiting', 'observed'], true)) {
            $stored = json_decode((string) ($test['result'] ?? ''), true);
            if (is_array($stored) && !empty($stored['text'])) {
                self::rx_cron_health_deliver($view, (string) $stored['text'], self::rx_cron_health_keyboard($testId, true));
            }
            self::rx_cron_health_answer($callbackId, 'نتیجه نهایی نمایش داده شد.');
            \Faoxima\Cron\Health\HealthStorage::rx_cron_health_marker_sync($pdo, false);
            return;
        }
        if ($state === 'busy') {
            self::rx_cron_health_answer($callbackId, '⏳ نتیجه در حال ثبت است؛ چند لحظه دیگر دوباره بررسی کنید.');
            return;
        }
        if ($state === 'retry') {
            self::rx_cron_health_answer($callbackId, '⚠️ ارسال نتیجه ناموفق بود؛ به‌زودی دوباره تلاش می‌شود.', true);
            return;
        }
        $token = self::rx_cron_health_claim($pdo, $testId, 30);
        if ($token === null) {
            self::rx_cron_health_answer($callbackId, '⏳ نتیجه در حال به‌روزرسانی است؛ چند لحظه دیگر دوباره بررسی کنید.');
            return;
        }
        $test = \Faoxima\Cron\Health\HealthRun::rx_cron_health_load_test($pdo, $testId);
        if (is_array($test)) {
            $eval = \Faoxima\Cron\Health\HealthRun::rx_cron_health_evaluate($test, \Faoxima\Cron\Health\HealthRun::rx_cron_health_collect($pdo, $test), time());
            if ($eval['verdict'] !== 'pending') {
                $eval = ['verdict' => 'pending', 'observed' => !empty($test['invocation_id']), 'failed' => []];
            }
            self::rx_cron_health_deliver($view, self::rx_cron_health_render($eval), self::rx_cron_health_keyboard($testId, false));
        }
        self::rx_cron_health_release($pdo, $testId, $token);
        self::rx_cron_health_answer($callbackId, '⏳ هنوز در انتظار نتیجه…');
    }
}
