<?php

namespace Faoxima\Admin\Broadcast;

final class BroadcastQueue
{
    public static function rx_broadcast_token(array $info): string
    {
        return substr(md5(
            ($info['id_admin'] ?? '') . '|' . ($info['id_message'] ?? '') . '|' .
            ($info['type'] ?? '') . '|' . ($info['message'] ?? '')
        ), 0, 12);
    }

    public static function rx_broadcast_is_paused($status): bool
    {
        return is_string($status) && strpos($status, 'paused_') === 0;
    }

    public static function rx_broadcast_classify($resp): array
    {
        $r = ['class' => 'permanent_user', 'temporary' => false, 'code' => 0, 'retry_after' => 0, 'detail' => '', 'manual' => false];
        if (!is_array($resp)) {
            return ['class' => 'temporary_network', 'temporary' => true, 'detail' => 'no_response'] + $r;
        }
        if (!empty($resp['ok'])) {
            return ['class' => 'success'] + $r;
        }
        $code = (int) ($resp['error_code'] ?? 0);
        $desc = strtolower((string) ($resp['description'] ?? ''));
        $r['code'] = $code;
        if ($code === 429) {
            return ['class' => 'temporary_telegram', 'temporary' => true, 'detail' => 'rate_limit', 'retry_after' => max(1, (int) ($resp['parameters']['retry_after'] ?? 1))] + $r;
        }
        if (preg_match('/message to (forward|copy) not found|message_id_invalid|can\'t parse entities|message is too long|message text is empty|text must be non-empty/', $desc)) {
            return ['class' => 'system_error', 'temporary' => true, 'manual' => true, 'detail' => 'message_invalid'] + $r;
        }
        if ($code >= 500) {
            return ['class' => 'temporary_telegram', 'temporary' => true, 'detail' => 'http_' . $code] + $r;
        }
        if ($code === 401 || $code === 404) {
            return ['class' => 'system_error', 'temporary' => true, 'manual' => true, 'detail' => 'telegram_auth'] + $r;
        }
        if (strpos($desc, 'invalid response') !== false) {
            return ['class' => 'temporary_network', 'temporary' => true, 'detail' => 'invalid_response'] + $r;
        }
        if ($code === 0 && $desc !== '') {
            if (strpos($desc, 'timed out') !== false || strpos($desc, 'timeout') !== false) {
                $detail = 'timeout';
            } elseif (strpos($desc, 'resolve') !== false) {
                $detail = 'dns';
            } elseif (strpos($desc, 'refused') !== false) {
                $detail = 'refused';
            } elseif (strpos($desc, 'connect') !== false) {
                $detail = 'connect';
            } elseif (strpos($desc, 'reset') !== false || strpos($desc, 'recv failure') !== false || strpos($desc, 'send failure') !== false) {
                $detail = 'reset';
            } elseif (strpos($desc, 'ssl') !== false) {
                $detail = 'ssl';
            } else {
                $detail = 'network';
            }
            return ['class' => 'temporary_network', 'temporary' => true, 'detail' => $detail] + $r;
        }
        return $r;
    }

    public static function rx_broadcast_classify_exception(\Throwable $e): array
    {
        $m = $e->getMessage();
        $r = ['class' => 'system_error', 'temporary' => true, 'code' => 0, 'retry_after' => 0, 'detail' => 'worker_exception', 'manual' => false];
        if ($e instanceof \PDOException || preg_match('/SQLSTATE|mysql|gone away|lost connection|too many connections|max_user_connections|\b(1040|1203|2002|2006|2013)\b/i', $m)) {
            if (preg_match('/too many connections|max_user_connections|\b1040\b|\b1203\b/i', $m)) {
                $detail = 'too_many_connections';
            } elseif (preg_match('/gone away|\b2006\b/i', $m)) {
                $detail = 'db_gone';
            } elseif (preg_match('/lost connection|\b2013\b/i', $m)) {
                $detail = 'db_lost';
            } elseif (preg_match('/refused|\b2002\b/i', $m)) {
                $detail = 'db_refused';
            } else {
                $detail = 'db_error';
            }
            return ['class' => 'temporary_database', 'detail' => $detail] + $r;
        }
        return $r;
    }

    public static function rx_broadcast_pause_reason(string $class, int $code, array $classification): array
    {
        if ($class === 'temporary_telegram' && $code === 429) {
            return ['paused_rate_limit', 'telegram_429'];
        }
        if ($class === 'temporary_telegram' || $class === 'temporary_network') {
            return ['paused_network', $code >= 500 ? 'telegram_5xx' : 'network'];
        }
        if ($class === 'temporary_database') {
            return ['paused_database', 'database'];
        }
        $detail = $classification['detail'] ?? '';
        return ['paused_system', in_array($detail, ['message_invalid', 'telegram_auth'], true) ? (string) $detail : 'system'];
    }

    public static function rx_broadcast_apply_pause(array &$c, array $cls, ?int $now = null): void
    {
        $now = $now ?? time();
        $class = (string) ($cls['class'] ?? 'system_error');
        $code = (int) ($cls['code'] ?? 0);
        [$status, $reason] = self::rx_broadcast_pause_reason($class, $code, $cls);
        $retryAfter = (int) ($cls['retry_after'] ?? 0);
        $current = (string) ($c['status'] ?? '');
        $currentUntil = (int) ($c['pause_until'] ?? 0);
        if (self::rx_broadcast_is_paused($current) && (!empty($c['manual_resume']) || $currentUntil > $now)) {
            if ($status === 'paused_rate_limit' && $current === 'paused_rate_limit' && empty($c['manual_resume'])) {
                $c['pause_until'] = max($currentUntil, $now + max(1, $retryAfter) + 1);
                $c['retry_after'] = max((int) ($c['retry_after'] ?? 0), $retryAfter);
            }
            return;
        }
        $streak = (int) ($c['temp_streak'] ?? 0) + 1;
        $manual = !empty($cls['manual']);
        if ($status === 'paused_rate_limit') {
            $wait = max(1, $retryAfter) + 1;
        } else {
            $wait = (int) min(300, 30 * (2 ** min(4, $streak - 1)));
            if ($streak >= 6) {
                $manual = true;
            }
        }
        $c['status'] = $status;
        $c['pause_reason'] = $reason;
        $c['pause_detail'] = (string) ($cls['detail'] ?? '');
        $c['pause_until'] = $manual ? 0 : $now + $wait;
        $c['manual_resume'] = $manual;
        $c['last_error_type'] = $class;
        $c['last_error_code'] = $code;
        $c['last_error_at'] = $now;
        $c['retry_after'] = $retryAfter;
        $c['paused_at'] = $now;
        $c['temp_streak'] = $streak;
    }

    public static function rx_broadcast_resume_now(array &$c, bool $manual): bool
    {
        if (!self::rx_broadcast_is_paused($c['status'] ?? '')) {
            return false;
        }
        $c['status'] = 'running';
        $c['pause_until'] = 0;
        $c['manual_resume'] = false;
        $c['resume_count'] = (int) ($c['resume_count'] ?? 0) + 1;
        $c['resumed_at'] = time();
        if ($manual) {
            $c['temp_streak'] = 0;
        }
        return true;
    }

    public static function rx_broadcast_work_available(?string $infoFile = null): ?bool
    {
        if ($infoFile === null || $infoFile === '') {
            $infoFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'cronbot' . DIRECTORY_SEPARATOR . 'info';
        }
        if (!is_file($infoFile)) {
            return false;
        }
        $raw = @file_get_contents($infoFile);
        if ($raw === false) {
            return null;
        }
        $info = json_decode($raw, true);
        if (!is_array($info)) {
            return null;
        }
        $status = (string) ($info['status'] ?? '');
        if ($status === 'cancelled' || $status === 'finished') {
            return false;
        }
        if (self::rx_broadcast_is_paused($status)) {
            $until = (int) ($info['pause_until'] ?? 0);
            if (!empty($info['manual_resume']) || $until <= 0 || $until > time()) {
                return false;
            }
        }
        return true;
    }

    public static function rx_broadcast_info_update(string $infoFile, ?string $token, callable $mutator): ?array
    {
        $lf = @fopen($infoFile . '.wlock', 'c');
        if ($lf) {
            @flock($lf, LOCK_EX);
        }
        $result = null;
        $cur = is_file($infoFile) ? json_decode((string) @file_get_contents($infoFile), true) : null;
        if (is_array($cur) && ($token === null || self::rx_broadcast_token($cur) === $token)) {
            if ($mutator($cur) !== false) {
                $tmp = $infoFile . '.tmp';
                if (@file_put_contents($tmp, json_encode($cur, JSON_UNESCAPED_UNICODE)) !== false) {
                    @rename($tmp, $infoFile);
                }
            }
            $result = $cur;
        }
        if ($lf) {
            @flock($lf, LOCK_UN);
            @fclose($lf);
        }
        return $result;
    }

    public static function rx_broadcast_cleanup_token(string $cronbotDir, string $token): void
    {
        if (!preg_match('/^[0-9a-f]{12}$/', $token)) {
            return;
        }
        foreach (glob($cronbotDir . '/users.txt.*') ?: [] as $f) {
            if (strpos(basename($f), '.' . $token . '.') !== false) {
                @unlink($f);
            }
        }
    }

    public static function rx_broadcast_cancel(string $cronbotDir): ?string
    {
        $infoFile = $cronbotDir . '/info';
        $lf = @fopen($infoFile . '.wlock', 'c');
        if ($lf) {
            @flock($lf, LOCK_EX);
        }
        $cur = is_file($infoFile) ? json_decode((string) @file_get_contents($infoFile), true) : null;
        $token = is_array($cur) ? self::rx_broadcast_token($cur) : null;
        @unlink($infoFile);
        @unlink($infoFile . '.tmp');
        foreach (['users.txt', 'users.json', 'users.txt.new', 'users.txt.tail.tmp', 'users.txt.recover.tmp'] as $f) {
            @unlink($cronbotDir . '/' . $f);
        }
        if ($token !== null) {
            self::rx_broadcast_cleanup_token($cronbotDir, $token);
        }
        if ($lf) {
            @flock($lf, LOCK_UN);
            @fclose($lf);
        }
        return $token;
    }

    public static function rx_broadcast_resume_button(string $token): array
    {
        return ['text' => "▶️ ادامه عملیات", 'callback_data' => 'broadcast_resume_' . $token];
    }

    public static function rx_broadcast_pause_text(array $info): string
    {
        $titles = [
            'telegram_429'    => 'محدودیت نرخ ارسال تلگرام',
            'telegram_5xx'    => 'خطای موقت سرور تلگرام',
            'network'         => 'مشکل اتصال شبکه / تلگرام',
            'database'        => 'دیتابیس موقتاً در دسترس نیست',
            'system'          => 'ورکر موقتاً در دسترس نیست',
            'message_invalid' => 'پیام توسط تلگرام پذیرفته نشد',
            'telegram_auth'   => 'توکن ربات توسط تلگرام رد شد',
        ];
        $details = [
            'rate_limit'           => 'Too Many Requests',
            'timeout'              => 'Connection timeout',
            'dns'                  => 'DNS error',
            'refused'              => 'Connection refused',
            'connect'              => 'Connection failed',
            'reset'             => 'Connection reset',
            'ssl'                  => 'SSL error',
            'network'              => 'Network error',
            'no_response'          => 'No response',
            'invalid_response'     => 'Invalid response',
            'too_many_connections' => 'Too many connections',
            'db_refused'           => 'Connection refused',
            'db_lost'              => 'Lost connection',
            'db_gone'              => 'MySQL server has gone away',
            'db_error'             => 'Database connection error',
            'db_unavailable'       => 'Database unavailable',
            'worker_fatal'         => 'Worker stopped unexpectedly',
            'worker_exception'     => 'Worker error',
            'queue_unavailable'    => 'Queue file unavailable',
            'message_invalid'      => 'Message rejected by Telegram',
            'telegram_auth'        => 'Unauthorized',
        ];
        $reason = (string) ($info['pause_reason'] ?? 'system');
        $detailKey = (string) ($info['pause_detail'] ?? '');
        $detail = $details[$detailKey] ?? (preg_match('/^http_(\d{3})$/', $detailKey, $m) ? 'HTTP ' . $m[1] : '');
        $t = "\n⚠️ دلیل توقف : <b>" . ($titles[$reason] ?? $titles['system']) . "</b>\n";
        if ($detail !== '') {
            $t .= "🔎 جزئیات : <code>" . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . "</code>\n";
        }
        $until = (int) ($info['pause_until'] ?? 0);
        if (!empty($info['manual_resume']) || $until <= 0) {
            $t .= "🖐 ادامه خودکار انجام نمی‌شود؛ برای ادامه روی «▶️ ادامه عملیات» بزنید.\n";
        } else {
            $t .= "⏳ ادامه خودکار پس از حدود <b>" . max(0, $until - time()) . "</b> ثانیه (در اجرای بعدی کرون)\n";
        }
        return $t;
    }
}
