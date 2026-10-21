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

function api_mode_reject($message, $status = 400)
{
    api_mode_json(array('error' => $message), $status);
}

if (!function_exists('reject')) {
    function reject($message, $status = 400)
    {
        api_mode_reject($message, $status);
    }
}

function api_mode_export_report(array $class, array $schedule, $format)
{
    require_once __DIR__ . '/exchange.php';

    if (!in_array($format, array('xlsx', 'pdf'), true)) {
        api_mode_reject('Format laporan tidak valid.', 422);
    }

    $weekday = array(1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu');
    $teachers = array('DOSEN_PENGAMPU' => '', 'ASISTEN_UTAMA' => '', 'ASISTEN_PENDAMPING' => '');
    foreach (($schedule['pengajar'] ?? array()) as $teacher) {
        $role = $teacher['peran'] ?? '';
        if (array_key_exists($role, $teachers)) {
            $teachers[$role] = $teacher['nama'] ?? '';
        }
    }

    $evaluations = array();
    $assistance = array();
    foreach (($class['komponen_nilai'] ?? array()) as $component) {
        if (empty($component['aktif'])) {
            continue;
        }
        if (preg_match('/^EVALUASI_([1-9][0-9]*)$/', $component['kode'] ?? '', $match) === 1) {
            $evaluations[] = (int) $match[1];
        } elseif (preg_match('/^ASISTENSI_([1-9][0-9]*)$/', $component['kode'] ?? '', $match) === 1) {
            $assistance[] = (int) $match[1];
        }
    }
    sort($evaluations);
    sort($assistance);

    $legacyClass = array(
        'id' => (int) ($schedule['id'] ?? 0),
        'semester' => $class['periode']['semester'] ?? '',
        'year' => $class['periode']['tahun_akademik'] ?? '',
        'code' => $class['mata_kuliah']['kode'] ?? '',
        'course' => $class['mata_kuliah']['nama'] ?? '',
        'class' => $class['kelompok']['kode'] ?? '',
        'day' => $weekday[(int) ($schedule['hari_ke'] ?? 0)] ?? 'Hari belum ditentukan',
        'time' => substr((string) ($schedule['jam_mulai'] ?? ''), 0, 5) . '–' . substr((string) ($schedule['jam_selesai'] ?? ''), 0, 5),
        'lab' => $schedule['nama_laboratorium'] ?? $schedule['nama_ruang'] ?? $schedule['kode_ruang'] ?? '',
        'lecturer' => $teachers['DOSEN_PENGAMPU'],
        'assistant1' => $teachers['ASISTEN_UTAMA'],
        'assistant2' => $teachers['ASISTEN_PENDAMPING'],
        'meeting_count' => (int) ($class['jumlah_pertemuan_rencana'] ?? 0),
        'evaluation_count' => $evaluations === array() ? 0 : max($evaluations),
        'assistance_count' => $assistance === array() ? 0 : max($assistance),
        'state' => $schedule['status_workflow'] ?? $schedule['status'] ?? '',
    );

    $statusMap = array('HADIR' => 'H', 'IZIN' => 'I', 'SAKIT' => 'S', 'ALPA' => 'A', 'TERLAMBAT' => 'T');
    $students = array();
    foreach (($class['peserta'] ?? array()) as $participant) {
        $attendance = array();
        foreach (($participant['kehadiran'] ?? array()) as $record) {
            $status = $record['status'] ?? '';
            if (isset($statusMap[$status])) {
                $attendance[] = array('number' => (int) ($record['nomor_pertemuan'] ?? 0), 'value' => $statusMap[$status]);
            }
        }

        $gradeValues = array('evaluations' => array(), 'assistance' => array(), 'mid' => null, 'uas' => null);
        foreach (($participant['nilai'] ?? array()) as $grade) {
            $code = $grade['kode_komponen'] ?? '';
            $value = isset($grade['nilai']) ? (float) $grade['nilai'] : null;
            if (preg_match('/^EVALUASI_([1-9][0-9]*)$/', $code, $match) === 1) {
                $gradeValues['evaluations'][] = array('number' => (int) $match[1], 'value' => $value);
            } elseif (preg_match('/^ASISTENSI_([1-9][0-9]*)$/', $code, $match) === 1) {
                $gradeValues['assistance'][] = array('number' => (int) $match[1], 'value' => $value);
            } elseif ($code === 'MID') {
                $gradeValues['mid'] = $value;
            } elseif ($code === 'UAS') {
                $gradeValues['uas'] = $value;
            }
        }
        $summary = $participant['ringkasan_nilai'] ?? array();
        $students[] = array_merge(array(
            'nim' => $participant['nim'] ?? '',
            'name' => $participant['nama'] ?? '',
            'attendance' => $attendance,
            'presence' => count($class['sesi'] ?? array()) > 0
                ? count(array_filter($attendance, static function (array $record): bool { return $record['value'] === 'H'; })) / count($class['sesi']) * 100
                : 0,
            'ev' => $summary['rata_evaluasi'] ?? null,
            'as' => $summary['rata_asistensi'] ?? null,
            'mid' => $gradeValues['mid'],
            'uas' => $gradeValues['uas'],
            'na' => $summary['nilai_akhir'] ?? null,
            'letter' => $summary['predikat'] ?? '—',
        ), $gradeValues);
    }

    export_report(array('class' => $legacyClass, 'students' => $students), $format);
    exit;
}

function api_mode_find_reference(array $items, $value, $field, $label)
{
    $needle = mb_strtolower(trim((string) $value), 'UTF-8');
    $matches = array_values(array_filter($items, static function (array $item) use ($field, $needle): bool {
        return mb_strtolower(trim((string) ($item[$field] ?? '')), 'UTF-8') === $needle;
    }));
    if (count($matches) !== 1) {
        throw new RuntimeException($label . ' tidak ditemukan atau tidak unik: ' . trim((string) $value));
    }
    return $matches[0];
}

function api_mode_load_practicum_classes()
{
    $classes = array();
    $page = 1;
    $lastPage = 1;
    do {
        $response = api_mode_request('GET', 'praktikum/kelas?per_page=100&page=' . $page);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Data kelas tidak dapat dimuat untuk validasi import.')), $response['status']);
        }
        $body = $response['data'];
        if (!is_array($body['data'] ?? null)) {
            api_mode_json(array('error' => 'Format daftar kelas dari API pusat tidak valid.'), 502);
        }
        $classes = array_merge($classes, $body['data']);
        $lastPage = max(1, (int) ($body['meta']['last_page'] ?? 1));
        $page++;
    } while ($page <= $lastPage && $page <= 100);
    if ($page <= $lastPage) {
        api_mode_json(array('error' => 'Jumlah kelas melebihi batas aman untuk import. Batasi berkas per periode.'), 502);
    }
    return $classes;
}

function api_mode_parse_schedule_import($base64)
{
    require_once __DIR__ . '/exchange.php';
    $data = read_xlsx($base64);
    $headers = array('tahun akademik', 'semester', 'prodi', 'kode mk', 'laboratorium', 'email dosen', 'email asisten 1', 'email asisten 2', 'hari', 'jam', 'aktif', 'frekuensi');
    $missing = array_diff(array_diff($headers, array('email asisten 2')), $data['headers']);
    if ($missing !== array()) {
        return array('rows' => array(), 'errors' => array(array('line' => 1, 'error' => 'Kolom wajib tidak ditemukan: ' . implode(', ', $missing))));
    }
    if ($data['rows'] === array()) {
        return array('rows' => array(), 'errors' => array(array('line' => 1, 'error' => 'File kosong.')));
    }

    $referencesResponse = api_mode_request('GET', 'praktikum/referensi');
    if ($referencesResponse['status'] < 200 || $referencesResponse['status'] >= 300) {
        api_mode_json(array('error' => api_mode_error_message($referencesResponse['data'] ?? array(), 'Referensi Praktikum tidak dapat dimuat untuk validasi import.')), $referencesResponse['status']);
    }
    $references = $referencesResponse['data']['data'] ?? array();
    $classes = api_mode_load_practicum_classes();
    $resolved = array();
    $errors = array();
    $seenClasses = array();
    $weekday = array('senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'sabtu' => 6, 'minggu' => 7);
    $activeMap = array('aktif' => 'AKTIF', 'nonaktif' => 'NONAKTIF', '1' => 'AKTIF', '0' => 'NONAKTIF');

    foreach ($data['rows'] as $sourceRow) {
        $line = (int) $sourceRow['line'];
        $values = $sourceRow['values'];
        try {
            foreach (array_diff($headers, array('email asisten 2')) as $header) {
                if (trim((string) ($values[$header] ?? '')) === '') {
                    throw new RuntimeException('Kolom ' . $header . ' wajib diisi.');
                }
            }
            $frequency = trim((string) $values['frekuensi']);
            $program = api_mode_find_reference($references['program_studi'] ?? array(), $values['prodi'], 'nama', 'Program Studi');
            $course = api_mode_find_reference($references['mata_kuliah'] ?? array(), $values['kode mk'], 'kode', 'Kode Mata Kuliah');
            $laboratory = api_mode_find_reference($references['laboratorium'] ?? array(), $values['laboratorium'], 'nama', 'Laboratorium');
            $lecturer = api_mode_find_reference($references['dosen'] ?? array(), $values['email dosen'], 'email', 'Email Dosen');
            $assistant = api_mode_find_reference($references['asisten'] ?? array(), $values['email asisten 1'], 'email', 'Email Asisten 1');
            $assistant2 = trim((string) ($values['email asisten 2'] ?? '')) === ''
                ? null
                : api_mode_find_reference($references['asisten'] ?? array(), $values['email asisten 2'], 'email', 'Email Asisten 2');

            $programName = mb_strtolower(trim((string) $program['nama']), 'UTF-8');
            $year = mb_strtolower(trim((string) $values['tahun akademik']), 'UTF-8');
            $semester = mb_strtolower(trim((string) $values['semester']), 'UTF-8');
            $courseCode = mb_strtolower(trim((string) $course['kode']), 'UTF-8');
            $groupCode = mb_strtolower($frequency, 'UTF-8');
            $classMatches = array_values(array_filter($classes, static function (array $class) use ($programName, $year, $semester, $courseCode, $groupCode): bool {
                return mb_strtolower(trim((string) ($class['periode']['tahun_akademik'] ?? '')), 'UTF-8') === $year
                    && mb_strtolower(trim((string) ($class['periode']['semester'] ?? '')), 'UTF-8') === $semester
                    && mb_strtolower(trim((string) ($class['program_studi']['nama'] ?? '')), 'UTF-8') === $programName
                    && mb_strtolower(trim((string) ($class['mata_kuliah']['kode'] ?? '')), 'UTF-8') === $courseCode
                    && mb_strtolower(trim((string) ($class['kelompok']['kode'] ?? '')), 'UTF-8') === $groupCode;
            }));
            if (count($classMatches) !== 1) {
                throw new RuntimeException('Kelompok Praktikum canonical tidak ditemukan atau tidak unik. Buat/pastikan kelas dengan periode, prodi, mata kuliah, dan kode frekuensi ini terlebih dahulu.');
            }
            $class = $classMatches[0];
            $classId = (int) $class['id_kelas_kuliah'];
            if (isset($seenClasses[$classId])) {
                throw new RuntimeException('Kelas/frekuensi yang sama muncul lebih dari sekali dalam file.');
            }
            if ((int) ($class['jumlah_jadwal'] ?? 0) > 0) {
                throw new RuntimeException('Jadwal untuk kelas/frekuensi ini sudah ada; jadwal lama tidak ditimpa.');
            }
            $dayKey = mb_strtolower(trim((string) $values['hari']), 'UTF-8');
            if (!isset($weekday[$dayKey])) {
                throw new RuntimeException('Hari harus Senin sampai Minggu.');
            }
            $time = str_replace(array('–', '—'), '-', trim((string) $values['jam']));
            if (preg_match('/^([01]?[0-9]|2[0-3])[.:]([0-5][0-9])\s*-\s*([01]?[0-9]|2[0-3])[.:]([0-5][0-9])$/u', $time, $timeParts) !== 1) {
                throw new RuntimeException('Jam harus berformat HH.MM-HH.MM.');
            }
            $startMinutes = ((int) $timeParts[1] * 60) + (int) $timeParts[2];
            $endMinutes = ((int) $timeParts[3] * 60) + (int) $timeParts[4];
            if ($endMinutes <= $startMinutes) {
                throw new RuntimeException('Jam selesai harus setelah jam mulai.');
            }
            $statusKey = mb_strtolower(trim((string) $values['aktif']), 'UTF-8');
            if (!isset($activeMap[$statusKey])) {
                throw new RuntimeException('Status aktif harus Aktif, Nonaktif, 1, atau 0.');
            }
            $periodStart = $class['periode']['tanggal_mulai'] ?? null;
            $periodEnd = $class['periode']['tanggal_selesai'] ?? null;
            if (!$periodStart || !$periodEnd) {
                throw new RuntimeException('Tanggal periode canonical tidak tersedia.');
            }
            $resolvedValues = array(
                'id_kelas_kuliah' => $classId,
                'versi' => (int) $class['kelompok']['versi'],
                'id_laboratorium' => (int) $laboratory['id'],
                'hari_ke' => $weekday[$dayKey],
                'jam_mulai' => sprintf('%02d:%02d', (int) $timeParts[1], (int) $timeParts[2]),
                'jam_selesai' => sprintf('%02d:%02d', (int) $timeParts[3], (int) $timeParts[4]),
                'tanggal_mulai' => $periodStart,
                'tanggal_selesai' => $periodEnd,
                'pola_perulangan' => 'MINGGUAN',
                'status' => $activeMap[$statusKey],
                'id_user_dosen' => (int) $lecturer['id_user'],
                'id_user_asisten_utama' => (int) $assistant['id_user'],
                'id_user_asisten_pendamping' => $assistant2 === null ? null : (int) $assistant2['id_user'],
                'frequency' => $frequency,
                'day' => trim((string) $values['hari']),
                'time' => str_replace(':', '.', sprintf('%02d:%02d-%02d:%02d', (int) $timeParts[1], (int) $timeParts[2], (int) $timeParts[3], (int) $timeParts[4])),
            );
            $display = $values;
            $display['kelas'] = $class['kelompok']['kode'];
            $resolved[] = array('line' => $line, 'values' => $resolvedValues, 'display' => $display);
            $seenClasses[$classId] = true;
        } catch (Throwable $exception) {
            $errors[] = array('line' => $line, 'error' => $exception->getMessage());
        }
    }
    return array('rows' => $resolved, 'errors' => $errors);
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

    if ($route === '/admin/periods' && in_array($method, array('GET', 'POST'), true)) {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Pengelolaan periode hanya dapat diakses administrator.'), 403);
        }
        $response = api_mode_request($method, 'praktikum/admin/periode-akademik', $method === 'POST' ? $payload : null);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Periode akademik tidak dapat dimuat atau dibuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array(), $response['status']);
    }

    if (preg_match('#\\A/admin/periods/(\\d+)\\z#', $route, $match) && $method === 'PATCH') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Pengelolaan periode hanya dapat diakses administrator.'), 403);
        }
        $response = api_mode_request('PATCH', 'praktikum/admin/periode-akademik/' . (int) $match[1], $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Periode akademik tidak dapat diperbarui.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array(), $response['status']);
    }

    if (preg_match('#\\A/admin/periods/(\\d+)/activate\\z#', $route, $match) && $method === 'POST') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Pengelolaan periode hanya dapat diakses administrator.'), 403);
        }
        $response = api_mode_request('POST', 'praktikum/admin/periode-akademik/' . (int) $match[1] . '/aktifkan', array());
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Periode akademik tidak dapat diaktifkan.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array(), $response['status']);
    }

    if ($route === '/admin/schedule-template' && $method === 'GET') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Template jadwal hanya dapat diakses administrator.'), 403);
        }
        require_once __DIR__ . '/exchange.php';
        xlsx_download(array(
            array('tahun akademik', 'semester', 'prodi', 'kode mk', 'laboratorium', 'email dosen', 'email asisten 1', 'email asisten 2', 'hari', 'jam', 'aktif', 'frekuensi'),
            array('2026/2027', 'Ganjil', 'Teknik Informatika', '130106RP4', 'Lab Startup', 'dosen@fikom.umi.ac.id', 'asisten1@fikom.umi.ac.id', '', 'Selasa', '07.00-10.20', 'Aktif', 'TI_ALPRO-1'),
        ), 'template-jadwal.xlsx', 1);
        exit;
    }

    if ($route === '/admin/import-schedule' && $method === 'POST') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Import jadwal hanya dapat dilakukan administrator.'), 403);
        }
        if (!is_string($payload['base64'] ?? null) || $payload['base64'] === '') {
            api_mode_json(array('error' => 'Berkas XLSX wajib dikirim.'), 422);
        }
        $preview = api_mode_parse_schedule_import($payload['base64']);
        if (($payload['preview'] ?? false) === true) {
            api_mode_json($preview);
        }
        if ($preview['errors'] !== array()) {
            api_mode_json(array('error' => 'Import dibatalkan. Perbaiki semua baris bermasalah sebelum mencoba kembali.', 'errors' => $preview['errors']), 422);
        }
        $schedules = array_map(static function (array $row): array {
            return array_intersect_key($row['values'], array_flip(array(
                'id_kelas_kuliah', 'versi', 'id_laboratorium', 'hari_ke', 'jam_mulai', 'jam_selesai',
                'tanggal_mulai', 'tanggal_selesai', 'pola_perulangan', 'status', 'id_user_dosen',
                'id_user_asisten_utama', 'id_user_asisten_pendamping',
            )));
        }, $preview['rows']);
        $response = api_mode_request('POST', 'praktikum/jadwal/import', array('jadwal' => $schedules));
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Import jadwal ditolak API pusat. Tidak ada baris yang disimpan.')), $response['status']);
        }
        $result = $response['data']['data'] ?? array();
        api_mode_json(array('count' => (int) ($result['jumlah_diimpor'] ?? 0), 'jadwal' => $result['jadwal'] ?? array()), $response['status']);
    }

    if ($route === '/references' && $method === 'GET') {
        $response = api_mode_request('GET', 'praktikum/referensi');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Referensi Praktikum tidak dapat dimuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if ($route === '/admin/frequency-template' && $method === 'GET') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Template anggota frekuensi hanya dapat diakses administrator.'), 403);
        }
        require_once __DIR__ . '/exchange.php';
        xlsx_download(array(array('nim', 'tanggal_mulai', 'catatan'), array('13020260049', 'YYYY-MM-DD', 'Catatan opsional')), 'template-anggota-frekuensi.xlsx', 1);
        exit;
    }

    if ($route === '/admin/import-frequency' && $method === 'POST') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Import anggota frekuensi hanya dapat dilakukan administrator.'), 403);
        }
        $classId = filter_var($payload['id_kelas_kuliah'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        $version = filter_var($payload['versi'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($classId === false || $version === false || !is_string($payload['base64'] ?? null) || $payload['base64'] === '') {
            api_mode_json(array('error' => 'Kelas, versi, dan file XLSX wajib dikirim.'), 422);
        }
        require_once __DIR__ . '/exchange.php';
        $file = read_xlsx($payload['base64']);
        $requiredHeaders = array('nim', 'tanggal_mulai');
        $missingHeaders = array_diff($requiredHeaders, $file['headers']);
        if ($missingHeaders !== array() || $file['rows'] === array() || count($file['rows']) > 500) {
            api_mode_json(array('error' => $missingHeaders !== array() ? 'Kolom wajib: nim dan tanggal_mulai.' : 'File harus berisi 1–500 baris anggota.'), 422);
        }
        $members = array();
        foreach ($file['rows'] as $row) {
            $members[] = array(
                'nim' => trim((string) ($row['values']['nim'] ?? '')),
                'tanggal_mulai' => trim((string) ($row['values']['tanggal_mulai'] ?? '')),
                'catatan' => trim((string) ($row['values']['catatan'] ?? '')) ?: null,
            );
        }
        $preview = filter_var($payload['preview'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $response = api_mode_request('POST', 'praktikum/kelas/' . $classId . '/anggota/import', array(
            'versi' => (int) $version,
            'pratinjau' => $preview,
            'anggota' => $members,
        ));
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Import anggota frekuensi ditolak API pusat. Tidak ada baris yang disimpan.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array(), $response['status']);
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

    if (preg_match('#\A/classes/(\d+)/prospective-participants\z#', $route, $match) && $method === 'GET') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Pencarian Mahasiswa hanya dapat dilakukan administrator.'), 403);
        }
        $query = trim((string) ($_GET['query'] ?? ''));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            api_mode_json(array('error' => 'Masukkan minimal 2 dan maksimal 100 karakter untuk mencari Mahasiswa.'), 422);
        }
        $response = api_mode_request('GET', 'praktikum/kelas/' . (int) $match[1] . '/calon-peserta?cari=' . rawurlencode($query));
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Daftar calon Mahasiswa tidak dapat dimuat.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if (preg_match('#\A/classes/(\d+)/members\z#', $route, $match) && $method === 'POST') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Penambahan anggota kelompok hanya dapat dilakukan administrator.'), 403);
        }
        $response = api_mode_request('POST', 'praktikum/kelas/' . (int) $match[1] . '/anggota', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Mahasiswa tidak dapat ditambahkan ke kelompok.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array(), $response['status']);
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

    if (preg_match('#\A/classes/(\d+)/schedule-assistant-availability\z#', $route, $match) && $method === 'POST') {
        if (($_SESSION['iclabs_user']['role'] ?? '') !== 'admin') {
            api_mode_json(array('error' => 'Rekomendasi ketersediaan Asisten hanya dapat diakses administrator.'), 403);
        }
        $response = api_mode_request('POST', 'praktikum/kelas/' . (int) $match[1] . '/jadwal/pratinjau-ketersediaan-asisten', $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Ketersediaan Asisten tidak dapat diperiksa.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if (preg_match('#\A/classes/(\d+)/schedules/(\d+)\z#', $route, $match) && $method === 'PATCH') {
        $response = api_mode_request('PATCH', 'praktikum/kelas/' . (int) $match[1] . '/jadwal/' . (int) $match[2], $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Jadwal Praktikum tidak dapat diperbarui.')), $response['status']);
        }
        api_mode_json($response['data']['data'] ?? array());
    }

    if ($route === '/classes' && $method === 'GET') {
        $periodId = filter_var($_GET['period_id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if (isset($_GET['period_id']) && $periodId === false) {
            api_mode_json(array('error' => 'Filter periode tidak valid.'), 422);
        }
        $classes = array();
        $page = 1;
        $lastPage = 1;
        do {
            $query = 'praktikum/kelas?per_page=100&page=' . $page;
            if ($periodId !== false && $periodId !== null) {
                $query .= '&id_periode_akademik=' . (int) $periodId;
            }
            $response = api_mode_request('GET', $query);
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

    if (preg_match('#\A/classes/(\d+)/export\z#', $route, $match) && $method === 'GET') {
        if (($_SESSION['iclabs_user']['role'] ?? '') === 'mahasiswa') {
            api_mode_json(array('error' => 'Ekspor laporan hanya dapat diakses staf Praktikum.'), 403);
        }
        $format = $_GET['format'] ?? '';
        if (!in_array($format, array('xlsx', 'pdf'), true)) {
            api_mode_json(array('error' => 'Format laporan harus xlsx atau pdf.'), 422);
        }
        $response = api_mode_request('GET', 'praktikum/kelas/' . (int) $match[1]);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            api_mode_json(array('error' => api_mode_error_message($response['data'] ?? array(), 'Laporan tidak dapat dimuat dari API pusat.')), $response['status']);
        }
        $class = $response['data']['data'] ?? null;
        if (!is_array($class) || !is_array($class['peserta'] ?? null)) {
            api_mode_json(array('error' => 'API pusat tidak mengembalikan roster yang diperlukan untuk laporan.'), 502);
        }
        $schedules = $class['jadwal'] ?? array();
        $scheduleId = (int) ($_GET['schedule_id'] ?? 0);
        if ($scheduleId === 0 && count($schedules) === 1) {
            $scheduleId = (int) ($schedules[0]['id'] ?? 0);
        }
        $schedule = null;
        foreach ($schedules as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $scheduleId) {
                $schedule = $candidate;
                break;
            }
        }
        if ($schedule === null) {
            api_mode_json(array('error' => count($schedules) > 1
                ? 'Pilih salah satu jadwal untuk membuat laporan.'
                : 'Jadwal kelas tidak ditemukan.'), 422);
        }
        api_mode_export_report($class, $schedule, $format);
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
