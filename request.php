<?php

if (!function_exists('faoxima_dedup_error_log')) {
    function faoxima_dedup_error_log($key, $message, $ttl = 3600) {
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
}

class CurlRequest {
    private $url;
    private $headers = [];
    private $timeout = null;
    private $authToken = null;
    private $api_key = null;
    private $cookie = null;
    public function __construct($url) {
        $this->url = $url;
    }

    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }

    public function setHeaders(array $headers) {
        $this->headers = array_merge($this->headers, $headers);
    }

    public function setBearerToken($token) {
        $this->authToken = $token;
    }

    public function api_key($token) {
        $this->api_key = $token;
    }

    public function setCookie($cookieStr) {
        $this->cookie = $cookieStr;
    }

    private function prepareHeaders() {
        $headers = $this->headers;

        if ($this->authToken) {
            $headers[] = "Authorization: Bearer {$this->authToken}";
        }
        if ($this->api_key) {
            $headers[] = "X-API-Key: {$this->api_key}";
        }

        return $headers;
    }

    private function execute($method, $data = null) {
        if (!$this->timeout) {
            $cfgTimeout = isset($GLOBALS['setting']['panel_curl_timeout']) ? (int)$GLOBALS['setting']['panel_curl_timeout'] : 0;
            $this->timeout = $cfgTimeout > 0 ? $cfgTimeout : 20;
        }
        $connectTimeout = max(6, (int)round($this->timeout * 0.4));
        $maxAttempts = 2;
        $lastError = '';
        $response = false;
        $httpCode  = 0;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 30);

        $verifyTls = defined('BOT_CURL_VERIFY_TLS') && BOT_CURL_VERIFY_TLS === true;
        if ($verifyTls) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if (function_exists('faoxima_apply_curl_proxy')) {
            faoxima_apply_curl_proxy($ch, 'panel');
        }

        $finalHeaders = $this->prepareHeaders();
        if (!empty($finalHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        }
        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
        }
        if ($data) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $attemptStartedAt = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        if (function_exists('rx_perf_log')) {
            rx_perf_log('http', 'http.request', [
                'method' => strtoupper((string) $method),
                'url' => function_exists('rx_perf_safe_url') ? rx_perf_safe_url($this->url) : (string) parse_url($this->url, PHP_URL_HOST),
                'attempt' => $attempt,
                'duration_ms' => round((microtime(true) - $attemptStartedAt) * 1000, 3),
                'http_code' => (int) $httpCode,
                'curl_errno' => (int) $curlErrno,
                'curl_error' => (string) $curlError,
                'connect_ms' => round(((float) ($curlInfo['connect_time'] ?? 0)) * 1000, 3),
                'tls_ready_ms' => round(((float) ($curlInfo['appconnect_time'] ?? 0)) * 1000, 3),
                'namelookup_ms' => round(((float) ($curlInfo['namelookup_time'] ?? 0)) * 1000, 3),
                'starttransfer_ms' => round(((float) ($curlInfo['starttransfer_time'] ?? 0)) * 1000, 3),
            ]);
        }
        if ($curlErrno) {
            $lastError = curl_error($ch);
            curl_close($ch);
            $isTimeout = stripos($lastError, 'timed out') !== false || stripos($lastError, 'Operation timed out') !== false || $curlErrno === CURLE_OPERATION_TIMEDOUT;
            if ($attempt < $maxAttempts && $isTimeout) {
                continue;
            }
            $host = parse_url($this->url, PHP_URL_HOST);
            $port = parse_url($this->url, PHP_URL_PORT);
            $dedupKey = 'curlerr|' . ($host ?: $this->url) . ':' . ($port ?: '') . '|' . $lastError;
            faoxima_dedup_error_log(
                $dedupKey,
                sprintf('CurlRequest error calling %s: %s (HTTP code: %s)', $this->url, $lastError, var_export($httpCode, true))
            );
            return [
                'status' => $httpCode,
                'body' => $response,
                'error' => $lastError,
            ];
        }
        curl_close($ch);
        break;
        }


        $rxLogAll = defined('BOT_CURL_LOG_ALL_HTTP_ERRORS') && BOT_CURL_LOG_ALL_HTTP_ERRORS === true;
        if ($httpCode === 0 || $httpCode >= 500 || ($rxLogAll && $httpCode >= 400)) {
            $host = parse_url($this->url, PHP_URL_HOST);
            $port = parse_url($this->url, PHP_URL_PORT);
            $dedupKey = 'curlhttp|' . ($host ?: $this->url) . ':' . ($port ?: '') . '|' . $httpCode;
            faoxima_dedup_error_log(
                $dedupKey,
                sprintf('CurlRequest call to %s returned HTTP code %s', $this->url, var_export($httpCode, true))
            );
        }

        $upstreamDownCodes = [502, 503, 504, 520, 521, 522, 523, 524, 525, 526, 527];
        if (in_array((int)$httpCode, $upstreamDownCodes, true)) {
            return [
                'status' => $httpCode,
                'body'   => $response,
                'error'  => sprintf('Panel temporarily unavailable (HTTP %d).', (int)$httpCode),
            ];
        }

        return [
            'status' => $httpCode,
            'body' => $response
        ];
    }

    public function get() {
        return $this->execute("GET");
    }

    public function post($data) {
        return $this->execute("POST", $data);
    }

    public function put($data) {
        return $this->execute("PUT", $data);
    }

    public function delete($data = null) {
        return $this->execute("DELETE", $data);
    }
    public function PATCH($data = null){
        return $this->execute('PATCH',$data);
    }
}

