<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/ServiceHandler.php';

final class ServiceConfigsHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $username = FaoximaInput::nullableString($this->data, 'username');
        if ($username === null || $username === '') {
            FaoximaResponse::badRequest('username is required');
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


        $configs = [];
        foreach ((array)($payload['service_output'] ?? []) as $entry) {
            $t = strtolower((string)($entry['type'] ?? ''));
            if ($t === 'config') {
                $val = $entry['value'] ?? null;
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v) && $v !== '') $configs[] = $v;
                    }
                } elseif (is_string($val) && $val !== '') {
                    foreach (preg_split('/\r?\n/', $val) as $line) {
                        $line = trim($line);
                        if ($line !== '') $configs[] = $line;
                    }
                }
            } elseif ($t === 'password' && !empty($entry['value'])) {
                $configs[] = (string)$entry['value'];
            }
        }


        if (empty($configs)) {
            $subUrl = (string)($payload['subscription_url'] ?? '');
            if ($subUrl !== '') {
                $configs = $this->fetchConfigsFromSubscription($subUrl);
            }
        }

        FaoximaResponse::ok([
            'username'         => (string)($payload['username'] ?? $username),
            'configs'          => array_values(array_unique($configs)),
            'subscription_url' => (string)($payload['subscription_url'] ?? ''),
            'count'            => count(array_values(array_unique($configs))),
        ]);
    }


    private function fetchConfigsFromSubscription(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Faoxima-Mini/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => ['Accept: */*'],
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http < 200 || $http >= 400 || !is_string($body) || $body === '') {
            return [];
        }

        $body = trim($body);


        if (preg_match('#^(vmess|vless|trojan|ss|ssr|hysteria2?|hy2|tuic|wireguard)://#im', $body)) {
            return $this->splitConfigLines($body);
        }


        $decoded = $this->safeBase64Decode($body);
        if ($decoded !== null && preg_match('#^(vmess|vless|trojan|ss|ssr|hysteria2?|hy2|tuic)://#im', $decoded)) {
            return $this->splitConfigLines($decoded);
        }


        return $this->splitConfigLines($body);
    }


    private function safeBase64Decode(string $s): ?string
    {
        $clean = preg_replace('/\s+/', '', $s);
        if ($clean === '' || !preg_match('#^[A-Za-z0-9+/=_\-]+$#', $clean)) return null;

        $std = strtr($clean, '-_', '+/');
        $pad = strlen($std) % 4;
        if ($pad > 0) $std .= str_repeat('=', 4 - $pad);
        $out = @base64_decode($std, true);
        if ($out === false) return null;
        return $out;
    }


    private function splitConfigLines(string $body): array
    {
        $lines = preg_split('/\r?\n/', $body);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($line[0] === '#') continue;
            $out[] = $line;
        }
        return $out;
    }
}
