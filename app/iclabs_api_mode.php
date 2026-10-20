<?php

/** API-only authentication bridge. This file intentionally never loads PDO/bootstrap.php. */
$config = require dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/iclabs_api_client.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

function api_mode_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_mode_request($method, $path, array $payload = null, $authenticated = true)
{
    global $config;
    $client = new IclabsApiClient($config);
    $token = $authenticated ? ($_SESSION['iclabs_bearer'] ?? null) : null;
    return $client->request($method, $path, $payload, $token);
}

function api_mode_error_message(array $body, $fallback)
{
    $messages = array();
    if (isset($body['message']) && is_string($body['message'])) {
        $messages[] = $body['message'];
    }
    if (isset($body['errors']) && is_array($body['errors'])) {
        foreach ($body['errors'] as $fieldMessages) {
            foreach ((array) $fieldMessages as $message) {
                if (is_string($message) && $message !== '') {
                    $messages[] = $message;
                }
            }
        }
    }
    if (isset($body['error']) && is_string($body['error'])) {
        $messages[] = $body['error'];
    }
    $message = implode(' ', array_unique($messages));
    return $message === '' ? $fallback : substr($message, 0, 1000);
}

function api_mode_open_session()
{
    global $config;

    if ($config['iclabs_session_path'] !== '') {
        $path = $config['iclabs_session_path'];
        if (!is_dir($path) || !is_writable($path)) {
            throw new RuntimeException('Direktori session Praktikum tidak tersedia atau tidak dapat ditulis oleh PHP.');
        }
        session_save_path($path);
    }

    // Manage a SameSite cookie explicitly so this remains compatible with PHP 7.1.
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_name('iclabs_praktikum_sid');

    $id = $_COOKIE[session_name()] ?? '';
    if (is_string($id) && preg_match('/\A[a-zA-Z0-9,-]{16,128}\z/', $id)) {
        session_id($id);
    }
    if (!session_start()) {
        throw new RuntimeException('Session Praktikum tidak dapat dibuka.');
    }
}

function api_mode_cookie($clear = false)
{
    global $config;
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api.php')), '/');
    $path = ($path === '' ? '/' : $path . '/');
    $value = $clear ? '' : session_id();
    $maxAge = $clear ? 0 : 28800;
    header('Set-Cookie: ' . session_name() . '=' . $value . '; Path=' . $path . '; Max-Age=' . $maxAge . '; HttpOnly; SameSite=Strict' . ($config['iclabs_secure_cookie'] ? '; Secure' : ''), false);
}

function api_mode_role(array $roles)
{
    $codes = array_column($roles, 'kode');
    foreach (array('ADMIN_ICLABS', 'SUPER_ADMIN', 'DOSEN', 'ASISTEN', 'MAHASISWA') as $candidate) {
        if (in_array($candidate, $codes, true)) {
            return array(
                'ADMIN_ICLABS' => 'admin', 'SUPER_ADMIN' => 'admin',
                'DOSEN' => 'dosen', 'ASISTEN' => 'asisten', 'MAHASISWA' => 'mahasiswa'
            )[$candidate];
        }
    }
    return null;
}

function api_mode_user(array $user)
{
    $role = api_mode_role($user['peran'] ?? array());
    if ($role === null) {
        throw new RuntimeException('Akun pusat belum memiliki role yang didukung Praktikum.');
    }
    $profile = $user['profil'] ?? array();
    return array(
        'id' => (int) ($user['id'] ?? 0),
        'name' => trim(($profile['gelar_depan'] ?? '') . ' ' . ($profile['nama_lengkap'] ?? ($user['username'] ?? $user['email_login'] ?? 'Pengguna ICLabs')) . ' ' . ($profile['gelar_belakang'] ?? '')),
        'email' => $user['email_login'] ?? '',
        'role' => $role,
        'api_mode' => true,
    );
}

try {
    if (!$config['iclabs_api_mode']) {
        api_mode_json(array('error' => 'Mode API Praktikum belum diaktifkan.'), 503);
    }
    if ($config['iclabs_api_base_url'] === '') {
        api_mode_json(array('error' => 'ICLABS_API_BASE_URL belum dikonfigurasi.'), 503);
    }

    api_mode_open_session();
    $route = $_GET['route'] ?? '';
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        api_mode_json(array('error' => 'JSON tidak valid.'), 400);
    }

    if ($route === '/login') {
        if ($method !== 'POST') api_mode_json(array('error' => 'Metode tidak diizinkan.'), 405);
        $response = api_mode_request('POST', 'auth/login', array(
            'identitas' => trim((string) ($payload['email'] ?? '')),
            'password' => $payload['password'] ?? '',
            'nama_perangkat' => 'ICLabs Praktikum'
        ), false);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Email/identitas atau password tidak valid.')), $response['status']);
        }
        $data = $response['data']['data'] ?? array();
        if (empty($data['token']) || empty($data['user'])) {
            api_mode_json(array('error' => 'Response autentikasi API pusat tidak lengkap.'), 502);
        }
        session_regenerate_id(true);
        try {
            $normalizedUser = api_mode_user($data['user']);
        } catch (RuntimeException $roleException) {
            $client = new IclabsApiClient($config);
            $client->request('POST', 'auth/logout', array(), $data['token']);
            api_mode_json(array('error' => 'Role akun pusat belum didukung oleh ICLabs Praktikum.'), 403);
        }
        $_SESSION['iclabs_bearer'] = $data['token'];
        $_SESSION['iclabs_expires_at'] = $data['kadaluarsa_pada'] ?? null;
        $_SESSION['iclabs_user'] = $normalizedUser;
        $_SESSION['iclabs_csrf'] = bin2hex(random_bytes(24));
        api_mode_cookie();
        api_mode_json(array('ok' => true));
    }

    if (empty($_SESSION['iclabs_bearer'])) {
        api_mode_json(array('error' => 'Silakan login.'), 401);
    }
    if (!empty($_SESSION['iclabs_expires_at']) && strtotime($_SESSION['iclabs_expires_at']) <= time()) {
        $_SESSION = array();
        session_destroy();
        api_mode_cookie(true);
        api_mode_json(array('error' => 'Sesi telah kedaluwarsa. Silakan login kembali.'), 401);
    }

    if (!in_array($method, array('GET', 'HEAD'), true)
        && !hash_equals($_SESSION['iclabs_csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        api_mode_json(array('error' => 'Token CSRF tidak valid.'), 403);
    }

    if ($route === '/me' && $method === 'GET') {
        $response = api_mode_request('GET', 'auth/me');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Sesi pusat tidak valid.')), $response['status']);
        }
        $centralUser = $response['data']['data'] ?? null;
        if (!is_array($centralUser)) api_mode_json(array('error' => 'Response profil API pusat tidak valid.'), 502);
        $_SESSION['iclabs_user'] = api_mode_user($centralUser);
        api_mode_json(array_merge($_SESSION['iclabs_user'], array('csrf' => $_SESSION['iclabs_csrf'])));
    }

    if ($route === '/logout' && $method === 'POST') {
        $response = api_mode_request('POST', 'auth/logout', array());
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Logout API pusat gagal.')), $response['status']);
        }
        $_SESSION = array();
        session_destroy();
        api_mode_cookie(true);
        api_mode_json(array('ok' => true));
    }

    if ($route === '/password' && $method === 'POST') {
        $response = api_mode_request('POST', 'auth/ganti-password', array(
            'password_lama' => $payload['current'] ?? '',
            'password_baru' => $payload['password'] ?? '',
            'password_baru_confirmation' => $payload['password'] ?? '',
        ));
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Perubahan password ditolak API pusat.')), $response['status']);
        }
        api_mode_json(array('ok' => true));
    }

    if ($route === '/active-period' && $method === 'GET') {
        $response = api_mode_request('GET', 'praktikum/referensi');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Referensi periode tidak dapat dimuat.')), $response['status']);
        }
        $periods = $response['data']['data']['periode_akademik'] ?? array();
        $period = is_array($periods) && isset($periods[0]) ? $periods[0] : null;
        if ($period === null) {
            api_mode_json(null);
        }
        api_mode_json(array(
            'id' => (int) ($period['id'] ?? 0),
            'tahun_id' => (int) ($period['id'] ?? 0),
            'semester_id' => $period['kode_semester'] ?? '',
            'academic_year' => $period['tahun_akademik'] ?? '',
            'semester' => $period['semester'] ?? $period['nama_semester'] ?? '',
            'start_date' => $period['tanggal_mulai'] ?? null,
            'end_date' => $period['tanggal_selesai'] ?? null,
            'version' => 0,
        ));
    }

    if ($route === '/dashboard' && $method === 'GET') {
        $response = api_mode_request('GET', 'praktikum/ringkasan');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Ringkasan Praktikum tidak dapat dimuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if ($route === '/admin/audit' && $method === 'GET') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Akses khusus administrator.'), 403);
        }
        $response = api_mode_request('GET', 'praktikum/audit?limit=1000');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Log aktivitas tidak dapat dimuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if ($route === '/admin/grade-bands' && in_array($method, array('GET', 'PUT'), true)) {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Akses khusus administrator.'), 403);
        }
        $response = api_mode_request($method, 'praktikum/konfigurasi-nilai', $method === 'PUT' ? $payload : null);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Konfigurasi nilai tidak dapat dimuat atau disimpan.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if ($route === '/references' && $method === 'GET') {
        $response = api_mode_request('GET', 'praktikum/referensi');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Referensi Praktikum tidak dapat dimuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if ($route === '/classes' && $method === 'POST') {
        $response = api_mode_request('POST', 'praktikum/kelas', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Kelas Praktikum tidak dapat dibuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array(), $response['status']);
    }

    if (preg_match('#\A/classes/(\d+)\z#', $route, $match) && $method === 'PATCH') {
        $response = api_mode_request('PATCH', 'praktikum/kelas/' . (int) $match[1], $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Kelompok Praktikum tidak dapat diperbarui.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if (preg_match('#\A/classes/(\d+)/components\z#', $route, $match) && $method === 'PUT') {
        $response = api_mode_request('PUT', 'praktikum/kelas/' . (int) $match[1] . '/komponen-nilai', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Komponen penilaian tidak dapat diperbarui.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if (preg_match('#\A/classes/(\d+)/participants/sync\z#', $route, $match) && $method === 'POST') {
        $response = api_mode_request('POST', 'praktikum/kelas/' . (int) $match[1] . '/peserta/sinkronisasi', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Peserta tidak dapat disinkronkan.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if (preg_match('#\A/classes/(\d+)/participants/transfer\z#', $route, $match) && $method === 'POST') {
        $response = api_mode_request('POST', 'praktikum/kelas/' . (int) $match[1] . '/peserta/pindah', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Mahasiswa tidak dapat dipindahkan.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if (preg_match('#\A/classes/(\d+)/schedules\z#', $route, $match) && $method === 'POST') {
        $response = api_mode_request('POST', 'praktikum/kelas/' . (int) $match[1] . '/jadwal', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Jadwal Praktikum tidak dapat dibuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array(), $response['status']);
    }

    if (preg_match('#\A/classes/(\d+)/schedules/(\d+)\z#', $route, $match) && $method === 'PATCH') {
        $response = api_mode_request('PATCH', 'praktikum/kelas/' . (int) $match[1] . '/jadwal/' . (int) $match[2], $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Jadwal Praktikum tidak dapat diperbarui.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if ($route === '/classes' && $method === 'GET') {
        $classes = array();
        $page = 1;
        $lastPage = 1;
        do {
            $response = api_mode_request('GET', 'praktikum/kelas?per_page=100&page=' . $page);
            if ($response['status'] < 200 || $response['status'] >= 300) {
                api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Daftar kelas Praktikum tidak dapat dimuat.')), $response['status']);
            }
            $body = $response['data'];
            if (!isset($body['data']) || !is_array($body['data'])) {
                api_mode_json(array('error' => 'Format daftar kelas dari API pusat tidak valid.'), 502);
            }
            $classes = array_merge($classes, $body['data']);
            $lastPage = max(1, (int) ($body['meta']['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage && $page <= 100);
        if ($page <= $lastPage) {
            api_mode_json(array('error' => 'Daftar kelas melebihi batas halaman aman. Gunakan filter periode.'), 502);
        }
        api_mode_json($classes);
    }

    if (preg_match('#\A/classes/(\d+)/changes\z#', $route, $match) && $method === 'PUT') {
        $response = api_mode_request('PUT', 'praktikum/kelas/' . (int) $match[1] . '/perubahan', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Perubahan Praktikum ditolak API pusat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array('ok' => true));
    }

    if (preg_match('#\A/classes/(\d+)/schedules/(\d+)/workflow\z#', $route, $match) && $method === 'PATCH') {
        $response = api_mode_request('PATCH', 'praktikum/kelas/' . (int) $match[1] . '/jadwal/' . (int) $match[2] . '/workflow', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Transisi workflow ditolak API pusat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array('ok' => true));
    }

    if (preg_match('#\A/classes/(\d+)\z#', $route, $match) && $method === 'GET') {
        $response = api_mode_request('GET', 'praktikum/kelas/' . (int) $match[1]);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Detail kelas tidak dapat dimuat.')), $response['status']);
        }
        if (!isset($response['data']['data']) || !is_array($response['data']['data'])) {
            api_mode_json(array('error' => 'Format detail kelas dari API pusat tidak valid.'), 502);
        }
        api_mode_json($response['data']['data']);
    }

    if ($route === '/student/grades' && $method === 'GET') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'mahasiswa') {
            api_mode_json(array('error' => 'Akses khusus mahasiswa.'), 403);
        }
        $response = api_mode_request('GET', 'praktikum/nilai/saya');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Nilai Praktikum tidak dapat dimuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    // Explicitly fail closed while each application route is migrated; never query the legacy DB here.
    api_mode_json(array('error' => 'Fitur ini belum dialihkan ke API pusat; data lokal tidak digunakan dalam mode API.'), 501);
} catch (Throwable $exception) {
    error_log('ICLabs Praktikum API mode: ' . $exception->getMessage());
    api_mode_json(array('error' => 'Layanan API pusat belum dapat diproses. Periksa koneksi, session path, dan konfigurasi API.'), 503);
}
