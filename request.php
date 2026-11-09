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

if (!function_exists('faoxima_panel_timeout_validate')) {
    function faoxima_panel_timeout_validate($value)
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
}

if (!function_exists('faoxima_panel_connect_timeout')) {
    function faoxima_panel_connect_timeout($timeoutSeconds)
    {
        $timeoutSeconds = max(1, (int) $timeoutSeconds);
        return min($timeoutSeconds, max(6, (int) round($timeoutSeconds * 0.4)));
    }
}

if (!function_exists('faoxima_panel_timeout_info')) {
    function faoxima_panel_timeout_info($refresh = false)
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
                if (function_exists('select')) {
                    $row = select('setting', '*', null, null, 'select');
                } else {
                    $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
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
        $validated = faoxima_panel_timeout_validate($raw);
        if ($validated === null) {
            $timeout = $default;
            $source = ($raw === null || $raw === '') ? 'default' : 'invalid_default';
        } else {
            $timeout = $validated;
        }
        $info = [
            'timeout' => $timeout,
            'connect_timeout' => faoxima_panel_connect_timeout($timeout),
            'source' => $source,
        ];
        if ($source !== 'setting') {
            $cached = ['info' => $info, 'expires_at' => time() + 60];
        }
        return $info;
    }
}

if (!function_exists('faoxima_panel_timeout')) {
    function faoxima_panel_timeout()
    {
        return faoxima_panel_timeout_info()['timeout'];
    }
}

if (!function_exists('faoxima_curl_tls_options')) {
    function faoxima_curl_tls_options(): array
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
            $this->timeout = faoxima_panel_timeout();
        }
        $connectTimeout = faoxima_panel_connect_timeout($this->timeout);
        $requestMethod = strtoupper((string)$method);
        $idempotent = in_array($requestMethod, ['GET', 'HEAD'], true);
        $maxAttempts = $idempotent ? 2 : 1;
        $lastError = '';
        $response = false;
        $httpCode  = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 30);

        foreach (faoxima_curl_tls_options() as $tlsOption => $tlsValue) {
            curl_setopt($ch, $tlsOption, $tlsValue);
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        if ($curlErrno) {
            $lastError = curl_error($ch);
            curl_close($ch);
            $isTimeout = stripos($lastError, 'timed out') !== false || stripos($lastError, 'Operation timed out') !== false || $curlErrno === CURLE_OPERATION_TIMEDOUT;
            if ($idempotent && $attempt < $maxAttempts && $isTimeout) {
                continue;
            }
            $uncertain = !$idempotent && (
                $isTimeout
                || in_array($curlErrno, [CURLE_RECV_ERROR, CURLE_SEND_ERROR, CURLE_GOT_NOTHING, CURLE_PARTIAL_FILE], true)
            );
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
                'errno' => $curlErrno,
                'uncertain' => $uncertain,
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
                'uncertain' => false,
            ];
        }

        return [
            'status' => $httpCode,
            'body' => $response,
            'uncertain' => false,
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

