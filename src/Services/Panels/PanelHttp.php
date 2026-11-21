<?php

namespace Faoxima\Services\Panels;

use PDO;
use Throwable;

final class PanelHttp
{
    public static function faoxima_dedup_error_log($key, $message, $ttl = 3600) {
        $cacheDir = sys_get_temp_dir() . '/faoxima_log_dedup';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0700, true);
        }
        $cacheFile = $cacheDir . '/' . md5($key);
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            return false;
        }
        @touch($cacheFile);
        error_log($message);
        return true;
    }

    public static function faoxima_panel_timeout_validate($value)
    {
        if (is_int($value)) {
            $candidate = $value;
        } elseif (is_string($value) && ctype_digit(trim($value))) {
            $candidate = (int) trim($value);
        } else {
            return null;
        }
        return ($candidate >= 5 && $candidate <= 120) ? $candidate : null;
    }

    public static function faoxima_panel_connect_timeout($timeoutSeconds)
    {
        $timeoutSeconds = max(1, (int) $timeoutSeconds);
        return min($timeoutSeconds, max(6, (int) round($timeoutSeconds * 0.4)));
    }

    public static function faoxima_panel_timeout_info($refresh = false)
    {
        static $cached = null;
        $default = 20;
        $raw = null;
        $source = null;
        if (isset($GLOBALS['setting']) && is_array($GLOBALS['setting']) && array_key_exists('panel_curl_timeout', $GLOBALS['setting'])) {
            $raw = $GLOBALS['setting']['panel_curl_timeout'];
            $source = 'setting';
        } elseif (!$refresh && is_array($cached) && $cached['expires_at'] > time()) {
            return $cached['info'];
        } else {
            $source = 'database';
            try {
                $row = null;
                if (\Faoxima\Core\Database\Db::runtimeReady()) {
                    $row = \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select');
                } else {
                    $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
                    if ($pdo instanceof PDO) {
                        $stmt = $pdo->query('SELECT * FROM setting LIMIT 1');
                        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    }
                }
                $raw = is_array($row) && array_key_exists('panel_curl_timeout', $row) ? $row['panel_curl_timeout'] : null;
            } catch (Throwable $e) {
                error_log('faoxima_panel_timeout_info: ' . $e->getMessage());
                $raw = null;
            }
        }
        $validated = self::faoxima_panel_timeout_validate($raw);
        if ($validated === null) {
            $timeout = $default;
            $source = ($raw === null || $raw === '') ? 'default' : 'invalid_default';
        } else {
            $timeout = $validated;
        }
        $info = [
            'timeout' => $timeout,
            'connect_timeout' => self::faoxima_panel_connect_timeout($timeout),
            'source' => $source,
        ];
        if ($source !== 'setting') {
            $cached = ['info' => $info, 'expires_at' => time() + 60];
        }
        return $info;
    }

    public static function faoxima_panel_timeout()
    {
        return self::faoxima_panel_timeout_info()['timeout'];
    }

    public static function faoxima_curl_tls_options(): array
    {
        $verify = true;
        $raw = null;
        if (defined('BOT_CURL_VERIFY_TLS')) {
            $raw = BOT_CURL_VERIFY_TLS;
        } else {
            $env = getenv('BOT_CURL_VERIFY_TLS');
            if ($env !== false) {
                $raw = $env;
            }
        }
        if ($raw !== null) {
            if ($raw === false || $raw === 0) {
                $verify = false;
            } elseif (is_string($raw) && in_array(strtolower(trim($raw)), ['0', 'false', 'off', 'no'], true)) {
                $verify = false;
            }
        }
        $options = [
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ];
        if ($verify) {
            $caInfo = defined('BOT_CURL_CAINFO') ? trim((string)BOT_CURL_CAINFO) : '';
            if ($caInfo === '') {
                $env = getenv('BOT_CURL_CAINFO');
                if ($env !== false) {
                    $caInfo = trim((string)$env);
                }
            }
            if ($caInfo !== '' && is_file($caInfo)) {
                $options[CURLOPT_CAINFO] = $caInfo;
            }
            $caPath = defined('BOT_CURL_CAPATH') ? trim((string)BOT_CURL_CAPATH) : '';
            if ($caPath === '') {
                $env = getenv('BOT_CURL_CAPATH');
                if ($env !== false) {
                    $caPath = trim((string)$env);
                }
            }
            if ($caPath !== '' && is_dir($caPath)) {
                $options[CURLOPT_CAPATH] = $caPath;
            }
        }
        return $options;
    }
}
