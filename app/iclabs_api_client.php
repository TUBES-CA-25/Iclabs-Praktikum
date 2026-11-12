<?php

/**
 * Small server-side HTTP client compatible with the application's PHP 7.1 runtime.
 */
class IclabsApiClient
{
    private $baseUrl;
    private $connectTimeoutSeconds;
    private $timeoutSeconds;
    private $verifyTls;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) ($config['iclabs_api_base_url'] ?? ''), '/');
        $this->connectTimeoutSeconds = max(1, (int) ($config['iclabs_api_connect_timeout_seconds'] ?? 3));
        $this->timeoutSeconds = max($this->connectTimeoutSeconds, (int) ($config['iclabs_api_timeout_seconds'] ?? 10));
        $this->verifyTls = (bool) ($config['iclabs_api_tls_verify'] ?? true);

        if ($this->baseUrl === '' || !filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('ICLABS_API_BASE_URL belum dikonfigurasi dengan URL yang valid.');
        }

        if (stripos($this->baseUrl, 'https://') !== 0 && $this->verifyTls) {
            throw new InvalidArgumentException('API pusat harus menggunakan HTTPS ketika verifikasi TLS aktif.');
        }
    }

    /**
     * @return array{status: int, data: array<string, mixed>|null}
     */
    public function request($method, $path, array $payload = null, $bearerToken = null, $timeoutSeconds = null)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Ekstensi cURL PHP harus diaktifkan untuk koneksi ke API ICLabs.');
        }

        $path = ltrim((string) $path, '/');
        if ($path === '' || strpos($path, '..') !== false || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
            throw new InvalidArgumentException('Path API tidak valid.');
        }

        $headers = array('Accept: application/json');
        $body = null;
        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                throw new InvalidArgumentException('Payload API tidak dapat dikodekan sebagai JSON.');
            }
            $headers[] = 'Content-Type: application/json';
        }

        if (is_string($bearerToken) && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        $clientIp = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $rawIps = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $firstIp = trim($rawIps[0] ?? '');
            if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
                $clientIp = $firstIp;
            }
        }
        if ($clientIp === null && !empty($_SERVER['REMOTE_ADDR'])) {
            $remoteIp = trim((string) $_SERVER['REMOTE_ADDR']);
            if (filter_var($remoteIp, FILTER_VALIDATE_IP)) {
                $clientIp = $remoteIp;
            }
        }
        if ($clientIp !== null) {
            $headers[] = 'X-Forwarded-For: ' . $clientIp;
            $headers[] = 'X-Real-IP: ' . $clientIp;
        }

        $requestTimeoutSeconds = $timeoutSeconds === null
            ? $this->timeoutSeconds
            : max($this->connectTimeoutSeconds, (int) $timeoutSeconds);

        $handle = curl_init($this->baseUrl . '/' . $path);
        curl_setopt_array($handle, array(
            CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $requestTimeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body
        ));

        $responseBody = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($responseBody === false || $curlError !== 0) {
            throw new RuntimeException('API ICLabs tidak dapat dijangkau. Periksa konektivitas dan konfigurasi jaringan.');
        }

        if ($status === 204 || trim($responseBody) === '') {
            return array('status' => $status, 'data' => null);
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('API ICLabs memberikan response yang tidak valid.');
        }

        return array('status' => $status, 'data' => $decoded);
    }
}
