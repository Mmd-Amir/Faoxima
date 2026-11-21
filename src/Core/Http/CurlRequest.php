<?php

namespace Faoxima\Core\Http;

/**
 * Small curl wrapper used by the panel drivers (headers, bearer/API-key/cookie authentication, timeouts, JSON bodies).
 */
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
            $this->timeout = \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout();
        }
        $connectTimeout = \Faoxima\Services\Panels\PanelHttp::faoxima_panel_connect_timeout($this->timeout);
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

        foreach (\Faoxima\Services\Panels\PanelHttp::faoxima_curl_tls_options() as $tlsOption => $tlsValue) {
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
            \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log(
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
            \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log(
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
