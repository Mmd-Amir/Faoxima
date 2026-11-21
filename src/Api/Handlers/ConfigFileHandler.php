<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class ConfigFileHandler extends \Faoxima\Api\Handlers\BaseHandler
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
                \Faoxima\Api\Runtime\Response::forbidden('Invalid user');
            }
            \Faoxima\Api\Runtime\Response::ok(['dl_token' => self::signDownloadToken($uid), 'ttl' => 300]);
            return;
        }

        $username = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'username');
        if ($username === null || $username === '') {
            \Faoxima\Api\Runtime\Response::badRequest('username is required');
        }

        $index = (int)(\Faoxima\Api\Runtime\Input::nullableString($this->data, 'i') ?? '0');
        if ($index < 0) {
            $index = 0;
        }

        $svcHandler = new \Faoxima\Api\Handlers\ServiceHandler($this->user, $this->data);
        $invoice = $svcHandler->lookupInvoice($username);
        if ($invoice === null) {
            \Faoxima\Api\Runtime\Response::notFound('Service not found');
        }

        $requestedType = \Faoxima\Api\Handlers\ServiceHandler::normalizeClientType(\Faoxima\Api\Runtime\Input::nullableString($this->data, 'client_type') ?? 'links');
        $payload = $svcHandler->buildPayloadFromInvoice($invoice, $requestedType);
        if ($payload === null) {
            \Faoxima\Api\Runtime\Response::fail(502, 'Service data unavailable');
        }

        $content = null;
        $filename = null;
        $customFilename = false;
        $downloadType = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'download_type') ?? '';
        if (!in_array($downloadType, ['', 'file', 'config'], true)) {
            \Faoxima\Api\Runtime\Response::badRequest('Invalid download type');
        }

        if ($requestedType !== 'links') {
            $documents = \Faoxima\Api\Handlers\ServiceHandler::clientDocumentsFromPayload($payload, $requestedType);
            $document = $documents[$index] ?? null;
            if (!is_string($document) || !\Faoxima\Api\Handlers\ServiceHandler::isValidClientDocument($requestedType, $document)) {
                \Faoxima\Api\Runtime\Response::notFound('Requested client configuration is not available');
            }
            $this->sendDownload($document, \Faoxima\Api\Handlers\ServiceHandler::clientDocumentFilename($requestedType, $index, count($documents)), (string)($payload['file_prefix'] ?? ''), false);
        }

        $fileIndex = 0;
        foreach ((array)($payload['service_output'] ?? []) as $entry) {
            if ($downloadType === 'config') {
                break;
            }
            if (strtolower((string)($entry['type'] ?? '')) !== 'file') {
                continue;
            }
            if ($downloadType === 'file' && $fileIndex++ !== $index) {
                continue;
            }
            $value = (string)($entry['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $content = $this->materialize($value);
            $filename = (string)($entry['filename'] ?? 'config.conf');
            $customFilename = !empty($entry['filename_custom']);
            break;
        }

        if ($content === null && $downloadType !== 'file') {
            $links = [];
            $linkFilenames = [];
            foreach ((array)($payload['service_output'] ?? []) as $entry) {
                if (strtolower((string)($entry['type'] ?? '')) !== 'config') {
                    continue;
                }
                $val = $entry['value'] ?? null;
                if (is_array($val)) {
                    foreach (array_values($val) as $localIndex => $v) {
                        if (is_string($v) && $v !== '') {
                            $links[] = $v;
                            $linkFilenames[] = (string) ($entry['download_filenames'][$localIndex] ?? '');
                        }
                    }
                } elseif (is_string($val) && $val !== '') {
                    $links[] = $val;
                    $linkFilenames[] = (string) ($entry['download_filenames'][0] ?? '');
                }
            }
            $link = $links[$index] ?? null;
            if ($link !== null && method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_wg_conf_from_link')) {
                $wg = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_from_link($link);
                if (is_array($wg)) {
                    $content = $wg['conf'];
                    $filename = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_filename($link, $wg['protocol'], $index);
                }
            }
            if ($content === null && is_string($link) && method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaConfigDownloadInfo')) {
                $downloadInfo = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaConfigDownloadInfo($link);
                if (is_array($downloadInfo)) {
                    $downloadTemp = tempnam(sys_get_temp_dir(), 'rxmini-');
                    if ($downloadTemp === false) {
                        \Faoxima\Api\Runtime\Response::fail(500, 'Unable to prepare download');
                    }
                    try {
                        if (\Faoxima\Services\Panels\Drivers\Rebecca::rebeccaDownloadConfigFile($downloadInfo['url'], $downloadTemp)) {
                            $downloadContent = file_get_contents($downloadTemp);
                            $content = is_string($downloadContent) ? $downloadContent : null;
                            $filename = $downloadInfo['filename'];
                        }
                    } finally {
                        @unlink($downloadTemp);
                    }
                }
            }
            if ($content !== null && ($linkFilenames[$index] ?? '') !== '') {
                $filename = $linkFilenames[$index];
                $customFilename = true;
            }
        }

        if ($content === null || $content === '') {
            \Faoxima\Api\Runtime\Response::notFound('Config file not available');
        }

        $wireguard = strtolower((string)pathinfo((string)$filename, PATHINFO_EXTENSION)) === 'conf'
            && stripos((string)$content, '[Interface]') !== false;
        $this->sendDownload((string)$content, (string)$filename, $customFilename ? '' : (string)($payload['file_prefix'] ?? ''), $wireguard);
    }

    private function sendDownload(string $content, string $filename, string $prefix, bool $wireguard): void
    {
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
