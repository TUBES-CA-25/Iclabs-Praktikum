<?php
// Konfigurasi XAMPP localhost. Ubah nama database/password sesuai instalasi Anda.
return array(
    'host' => getenv('PRAKTIKUM_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('PRAKTIKUM_DB_PORT') ?: '3306',
    'database' => getenv('PRAKTIKUM_DB_NAME') ?: 'db_praktikum_fikom',
    'username' => getenv('PRAKTIKUM_DB_USER') ?: '',
    'password' => getenv('PRAKTIKUM_DB_PASSWORD') ?: '',
    'secure_cookie' => false,
    'timezone' => 'Asia/Makassar'
);
