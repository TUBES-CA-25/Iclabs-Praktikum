<?php
// PHP native tidak memuat .env secara otomatis. Muat file lokal opsional agar
// konfigurasi pengembangan bisa dikelola tanpa menaruh nilai rahasia di kode.
$envFile = __DIR__ . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $envValues = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($envValues)) {
        foreach ($envValues as $envKey => $envValue) {
            if (!is_string($envKey) || $envKey === '') {
                continue;
            }
            // Nilai dari Apache/PHP-FPM/test runner lebih spesifik daripada
            // .env, jadi jangan menimpanya.
            if (getenv($envKey) !== false) {
                continue;
            }
            $envValue = (string) $envValue;
            putenv($envKey . '=' . $envValue);
            $_ENV[$envKey] = $envValue;
        }
    }
}

// Konfigurasi XAMPP localhost. Ubah nama database/password sesuai instalasi Anda.
return array(
    'host' => getenv('PRAKTIKUM_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('PRAKTIKUM_DB_PORT') ?: '3306',
    'database' => getenv('PRAKTIKUM_DB_NAME') ?: 'db_praktikum_fikom',
    'username' => getenv('PRAKTIKUM_DB_USER') ?: '',
    'password' => getenv('PRAKTIKUM_DB_PASSWORD') ?: '',
    'secure_cookie' => false,
    'timezone' => 'Asia/Makassar',
    'iclabs_api_base_url' => rtrim((string) (getenv('ICLABS_API_BASE_URL') ?: ''), '/'),
    'iclabs_api_connect_timeout_seconds' => max(1, (int) (getenv('ICLABS_API_CONNECT_TIMEOUT_SECONDS') ?: 3)),
    'iclabs_api_timeout_seconds' => max(1, (int) (getenv('ICLABS_API_TIMEOUT_SECONDS') ?: 10)),
    'iclabs_api_ai_timeout_seconds' => max(5, (int) (getenv('ICLABS_API_AI_TIMEOUT_SECONDS') ?: 150)),
    'iclabs_api_tls_verify' => strtolower((string) (getenv('ICLABS_API_TLS_VERIFY') ?: 'true')) !== 'false',
    'iclabs_api_mode' => strtolower((string) (getenv('ICLABS_PRAKTIKUM_API_MODE') ?: 'false')) === 'true',
    'iclabs_session_path' => (string) (getenv('ICLABS_PRAKTIKUM_SESSION_PATH') ?: ''),
    'secure_cookie' => false,
    'iclabs_secure_cookie' => strtolower((string) (getenv('ICLABS_PRAKTIKUM_SECURE_COOKIE') ?: 'true')) !== 'false'
);
