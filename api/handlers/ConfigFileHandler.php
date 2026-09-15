<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/ServiceHandler.php';

final class ConfigFileHandler extends BaseHandler
{
    public $mode = null;

    public static function signDownloadToken(int $userId, int $ttl = 300): string
    {
        $exp = time() + max(60, $ttl);
        $secret = (string)($GLOBALS['APIKEY'] ?? '');
        $sig = hash_hmac('sha256', $userId . '.' . $exp, $secret);
        return $userId . '.' . $exp . '.' . substr($sig, 0, 32);
    }

    public static function verifyDownloadToken(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$userId, $exp, $sig] = $parts;
        if (!ctype_digit($userId) || !ctype_digit($exp)) {
            return null;
        }
        if ((int)$exp < time()) {
            return null;
        }
        $secret = (string)($GLOBALS['APIKEY'] ?? '');
        $expected = substr(hash_hmac('sha256', $userId . '.' . $exp, $secret), 0, 32);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        return (int)$userId;
    }

    public function handle(): void
    {
        $this->requireMethod('GET');

        if (($this->mode ?? '') === 'token') {
            $uid = (int)($this->user['id'] ?? 0);
            if ($uid <= 0) {
                FaoximaResponse::forbidden('Invalid user');
            }
            FaoximaResponse::ok(['dl_token' => self::signDownloadToken($uid), 'ttl' => 300]);
            return;
        }

        $username = FaoximaInput::nullableString($this->data, 'username');
        if ($username === null || $username === '') {
            FaoximaResponse::badRequest('username is required');
        }

        $index = (int)(FaoximaInput::nullableString($this->data, 'i') ?? '0');
        if ($index < 0) {
            $index = 0;
        }

        $svcHandler = new ServiceHandler($this->user, $this->data);
        $invoice = $svcHandler->lookupInvoice($username);
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        $payload = $svcHandler->buildPayloadFromInvoice($invoice);
        if ($payload === null) {
            FaoximaResponse::fail(502, 'Service data unavailable');
        }

        $content = null;
        $filename = null;

        foreach ((array)($payload['service_output'] ?? []) as $entry) {
            if (strtolower((string)($entry['type'] ?? '')) !== 'file') {
                continue;
            }
            $value = (string)($entry['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $content = $this->materialize($value);
            $filename = (string)($entry['filename'] ?? 'config.conf');
            break;
        }

        if ($content === null) {
            $links = [];
            foreach ((array)($payload['service_output'] ?? []) as $entry) {
                if (strtolower((string)($entry['type'] ?? '')) !== 'config') {
                    continue;
                }
                $val = $entry['value'] ?? null;
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v) && $v !== '') $links[] = $v;
                    }
                } elseif (is_string($val) && $val !== '') {
                    $links[] = $val;
                }
            }
            $link = $links[$index] ?? null;
            if ($link !== null && function_exists('xui_wg_conf_from_link')) {
                $wg = xui_wg_conf_from_link($link);
                if (is_array($wg)) {
                    $content = $wg['conf'];
                    $filename = xui_wg_conf_filename($link, $wg['protocol'], $index);
                }
            }
        }

        if ($content === null || $content === '') {
            FaoximaResponse::notFound('Config file not available');
        }

        $wireguard = strtolower((string)pathinfo((string)$filename, PATHINFO_EXTENSION)) === 'conf'
            && stripos((string)$content, '[Interface]') !== false;
        $prefix = (string)($payload['file_prefix'] ?? '');
        $filename = function_exists('normalizeConfigFilename')
            ? normalizeConfigFilename((string)$filename, $prefix, $wireguard)
            : $this->sanitizeFilename(($prefix !== '' ? $prefix . '-' : '') . (string)$filename);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $this->asciiFallback($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $content;
        $GLOBALS['__miniapp_response_sent'] = true;
        exit;
    }

    private function materialize(string $value): ?string
    {
        if (preg_match('#^data:([^;,]*)(;base64)?,(.*)$#is', $value, $m)) {
            $raw = $m[3];
            if (!empty($m[2])) {
                $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
                return $decoded === false ? null : $decoded;
            }
            return rawurldecode($raw);
        }
        if (preg_match('#^https?://#i', $value)) {
            return null;
        }
        return $value;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('#[\\/:*?"<>|\r\n_]+#u', '-', $name);
        $name = trim((string)preg_replace('/-+/', '-', $name), "- \t.");
        if ($name === '') {
            $name = 'config.conf';
        }
        if (strlen($name) > 120) {
            $name = substr($name, 0, 120);
        }
        return $name;
    }

    private function asciiFallback(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9.-]+/', '-', str_replace('_', '-', $name));
        $ascii = trim((string)preg_replace('/-+/', '-', $ascii), '-');
        return $ascii === '' ? 'config.conf' : $ascii;
    }
}
