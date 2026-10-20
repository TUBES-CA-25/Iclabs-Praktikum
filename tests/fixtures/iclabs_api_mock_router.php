<?php

header('Content-Type: application/json; charset=utf-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$request = json_decode(file_get_contents('php://input') ?: '{}', true) ?: array();
$isStudent = ($request['identitas'] ?? '') === 'uji-mahasiswa@iclabs.test'
    || strpos($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ' . str_repeat('b', 64)) === 0;
$user = array(
    'id' => $isStudent ? 9002 : 9001,
    'email_login' => $isStudent ? 'uji-mahasiswa@iclabs.test' : 'uji-admin@iclabs.test',
    'username' => $isStudent ? 'uji-mahasiswa' : 'uji-admin',
    'jenis_akun' => array('kode' => 'INTERNAL', 'nama' => 'Internal'),
    'status_akun' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
    'profil' => array('nama_lengkap' => $isStudent ? 'Mahasiswa Uji' : 'Admin Uji', 'gelar_depan' => null, 'gelar_belakang' => null),
    'peran' => array(array('kode' => $isStudent ? 'MAHASISWA' : 'ADMIN_ICLABS', 'nama' => $isStudent ? 'Mahasiswa' : 'Administrator ICLabs')),
);

if ($path === '/api/v1/auth/login' && $method === 'POST') {
    $student = ($request['identitas'] ?? '') === 'uji-mahasiswa@iclabs.test';
    $user['id'] = $student ? 9002 : 9001;
    $user['email_login'] = $student ? 'uji-mahasiswa@iclabs.test' : 'uji-admin@iclabs.test';
    $user['username'] = $student ? 'uji-mahasiswa' : 'uji-admin';
    $user['profil']['nama_lengkap'] = $student ? 'Mahasiswa Uji' : 'Admin Uji';
    $user['peran'] = array(array('kode' => $student ? 'MAHASISWA' : 'ADMIN_ICLABS', 'nama' => $student ? 'Mahasiswa' : 'Administrator ICLabs'));
    echo json_encode(array('data' => array('token' => str_repeat($student ? 'b' : 'a', 64), 'kadaluarsa_pada' => gmdate('c', time() + 3600), 'user' => $user)));
    return;
}
if ($path === '/api/v1/auth/me') {
    echo json_encode(array('data' => $user));
    return;
}
if ($path === '/api/v1/auth/logout' && $method === 'POST') {
    http_response_code(204);
    return;
}
if ($path === '/api/v1/auth/ganti-password' && $method === 'POST') {
    http_response_code(204);
    return;
}
if ($path === '/api/v1/praktikum/referensi') {
    echo json_encode(array('data' => array('periode_akademik' => array(array(
        'id' => 1, 'tahun_akademik' => '2026/2027', 'kode_semester' => 'GANJIL',
        'nama_semester' => 'Ganjil', 'tanggal_mulai' => '2026-09-07',
        'tanggal_selesai' => '2026-12-12', 'aktif' => true,
    )))));
    return;
}
if ($path === '/api/v1/praktikum/kelas' && $method === 'GET') {
    echo json_encode(array('data' => array(array(
        'id_kelas_kuliah' => 10, 'status' => 'AKTIF', 'jumlah_pertemuan_rencana' => 12,
        'catatan' => null,
        'periode' => array('id' => 1, 'tahun_akademik' => '2026/2027', 'kode_semester' => 'GANJIL', 'semester' => 'Ganjil'),
        'program_studi' => array('kode' => 'TI', 'nama' => 'Teknik Informatika'),
        'mata_kuliah' => array('id' => 20, 'kode' => '130107RP4', 'nama' => 'Algoritma dan Pemrograman'),
        'kelompok' => array('id' => 30, 'kode' => 'TI_ALPRO-1', 'nama' => 'Frekuensi 1', 'versi' => 1),
    )), 'meta' => array('last_page' => 1)));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10') {
    $class = array(
        'id_kelas_kuliah' => 10, 'jenis' => 'PRAKTIKUM', 'status' => 'AKTIF',
        'jumlah_pertemuan_rencana' => 12, 'catatan' => null,
        'periode' => array('id' => 1, 'tahun_akademik' => '2026/2027', 'kode_semester' => 'GANJIL', 'semester' => 'Ganjil'),
        'program_studi' => array('kode' => 'TI', 'nama' => 'Teknik Informatika'),
        'mata_kuliah' => array('id' => 20, 'kode' => '130107RP4', 'nama' => 'Algoritma dan Pemrograman'),
        'kelompok' => array('id' => 30, 'kode' => 'TI_ALPRO-1', 'nama' => 'Frekuensi 1', 'versi' => 1),
        'jadwal' => array(), 'sesi' => array(), 'komponen_nilai' => array(), 'peserta' => array(),
    );
    echo json_encode(array('data' => $class));
    return;
}
if ($path === '/api/v1/praktikum/nilai/saya') {
    if (!$isStudent) {
        http_response_code(403);
        echo json_encode(array('message' => 'Endpoint ini hanya untuk Mahasiswa.'));
        return;
    }
    echo json_encode(array('data' => array(array(
        'id_kelas_kuliah' => 10, 'kode_kelompok' => 'TI_ALPRO-1', 'kode_mata_kuliah' => '130107RP4',
        'nama_mata_kuliah' => 'Algoritma dan Pemrograman', 'tahun_akademik' => '2026/2027',
        'semester' => 'Ganjil', 'kode_komponen' => 'MID', 'nama_komponen' => 'Ujian Tengah Semester',
        'bobot_persen' => 30, 'nilai' => 85,
    ))));
    return;
}
http_response_code(404);
echo json_encode(array('message' => 'Mock endpoint not found.'));
