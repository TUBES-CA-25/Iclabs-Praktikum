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
    )), 'periode_akademik' => array(array('id' => 1, 'tahun_akademik' => '2026/2027', 'nama_semester' => 'Ganjil', 'aktif' => true)),
        'program_studi' => array(array('id' => 1, 'kode' => 'TI', 'nama' => 'Teknik Informatika')),
        'mata_kuliah' => array(array('id' => 2, 'kode' => '130107RP4', 'nama' => 'Algoritma dan Pemrograman')),
        'laboratorium' => array(array('id' => 2, 'kode' => 'LAB-UJI', 'nama' => 'Laboratorium Uji')),
        'pola_perulangan' => array(array('kode' => 'MINGGUAN', 'nama' => 'Mingguan')),
        'dosen' => array(array('id_user' => 300, 'nama' => 'Dosen Uji')),
        'asisten' => array(array('id_user' => 400, 'nama' => 'Asisten Uji')),
    )));
    return;
}
if ($path === '/api/v1/praktikum/ringkasan') {
    echo json_encode(array('data' => array('jumlah_kelas' => 2, 'jumlah_peserta_aktif' => 18, 'pertemuan_direncanakan' => 28, 'pertemuan_tercatat' => 4, 'presensi_terisi' => 40, 'presensi_diharapkan' => 252, 'persentase_presensi' => 15.9, 'nilai_terisi' => 15, 'nilai_diharapkan' => 252, 'persentase_nilai' => 6.0, 'jadwal_perlu_perhatian' => 1)));
    return;
}
if ($path === '/api/v1/praktikum/audit') {
    echo json_encode(array('data' => array(array(
        'id' => 1,
        'created_at' => '2026-10-21 10:00:00',
        'name' => 'Admin Uji',
        'role' => null,
        'entity' => 'trx_kelas_kuliah:10 · UPDATE',
        'action' => 'UPDATE',
        'old_value' => '{"status":"DRAF"}',
        'new_value' => '{"status":"AKTIF"}',
    ))));
    return;
}
if ($path === '/api/v1/praktikum/konfigurasi-nilai') {
    $bands = array(
        array('kode' => 'A', 'nilai_minimum' => 85, 'aktif' => true),
        array('kode' => 'A-', 'nilai_minimum' => 80, 'aktif' => true),
        array('kode' => 'B+', 'nilai_minimum' => 75, 'aktif' => true),
        array('kode' => 'B', 'nilai_minimum' => 70, 'aktif' => true),
        array('kode' => 'B-', 'nilai_minimum' => 65, 'aktif' => true),
        array('kode' => 'C+', 'nilai_minimum' => 60, 'aktif' => true),
        array('kode' => 'C', 'nilai_minimum' => 55, 'aktif' => true),
        array('kode' => 'D', 'nilai_minimum' => 40, 'aktif' => true),
        array('kode' => 'E', 'nilai_minimum' => 0, 'aktif' => true),
    );
    echo json_encode(array('data' => array('versi' => $method === 'PUT' ? (int) ($request['versi'] ?? 0) + 1 : 1, 'rentang' => $method === 'PUT' ? ($request['rentang'] ?? $bands) : $bands)));
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
if ($path === '/api/v1/praktikum/kelas' && $method === 'POST') {
    echo json_encode(array('data' => array('id_kelas_kuliah' => 12, 'kode_kelompok' => $request['kode_kelompok'] ?? null)), JSON_UNESCAPED_UNICODE);
    return;
}
if ($path === '/api/v1/praktikum/kelas/10' && $method === 'PATCH') {
    echo json_encode(array('data' => array('id_kelas_kuliah' => 10, 'versi' => (int) ($request['versi'] ?? 0) + 1)), JSON_UNESCAPED_UNICODE);
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/komponen-nilai' && $method === 'PUT') {
    echo json_encode(array('data' => array('id_kelas_kuliah' => 10, 'versi' => (int) ($request['versi'] ?? 0) + 1)), JSON_UNESCAPED_UNICODE);
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
        'jadwal' => array(array('id' => 99, 'hari_ke' => 2, 'jam_mulai' => '09:00', 'jam_selesai' => '11:00', 'tanggal_mulai' => '2026-09-07', 'tanggal_selesai' => '2026-12-12', 'pola_perulangan' => 'MINGGUAN', 'status' => 'AKTIF', 'nama_laboratorium' => 'Laboratorium Uji', 'status_workflow' => 'TERVERIFIKASI', 'versi_workflow' => 1, 'pengajar' => array())),
        'sesi' => array(),
        'komponen_nilai' => array(array('id' => 1, 'kode' => 'MID', 'nama' => 'Ujian Tengah Semester', 'bobot_persen' => 30, 'urutan' => 1, 'aktif' => true)),
        'peserta' => array(array('id' => 70, 'id_mahasiswa' => 700, 'nim' => '13020230049', 'nama' => 'Mahasiswa Uji', 'status' => 'AKTIF', 'terdaftar_pada' => '2026-09-07', 'kehadiran' => array(), 'nilai' => array(), 'ringkasan_nilai' => array('konfigurasi_nilai_tersedia' => true, 'rata_evaluasi' => 80, 'rata_asistensi' => 90, 'nilai_akhir' => 85, 'predikat' => 'A'))),
    );
    echo json_encode(array('data' => $class));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/perubahan' && $method === 'PUT') {
    if (strpos($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ' . str_repeat('a', 64)) !== 0) {
        http_response_code(401);
        echo json_encode(array('message' => 'Unauthenticated.'));
        return;
    }
    echo json_encode(array('data' => array('id_kelas_kuliah' => 10, 'versi' => (int) ($request['versi'] ?? 0) + 1)));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/jadwal/99/workflow' && $method === 'PATCH') {
    echo json_encode(array('data' => array('id_jadwal_kelas_kuliah' => 99, 'status' => $request['status'] ?? null, 'versi_workflow' => (int) ($request['versi_workflow'] ?? 0) + 1)));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/jadwal' && $method === 'POST') {
    echo json_encode(array('data' => array('id_jadwal_kelas_kuliah' => 100, 'versi' => (int) ($request['versi'] ?? 0) + 1)));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/jadwal/99' && $method === 'PATCH') {
    echo json_encode(array('data' => array('id_jadwal_kelas_kuliah' => 99, 'versi' => (int) ($request['versi'] ?? 0) + 1)));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/peserta/sinkronisasi' && $method === 'POST') {
    echo json_encode(array('data' => array('id_kelas_kuliah' => 10, 'jumlah_peserta_baru' => 1, 'versi' => 2, 'peserta_dihapus' => false)));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/peserta/pindah' && $method === 'POST') {
    echo json_encode(array('data' => array('id_mahasiswa' => (int) ($request['id_mahasiswa'] ?? 0), 'id_kelas_tujuan' => (int) ($request['id_kelas_tujuan'] ?? 0), 'versi_sumber' => 2, 'versi_tujuan' => 2)));
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
        'ringkasan_nilai' => array('konfigurasi_nilai_tersedia' => true, 'rata_evaluasi' => 80, 'rata_asistensi' => 90, 'nilai_akhir' => 85, 'predikat' => 'A'),
    ))));
    return;
}
http_response_code(404);
echo json_encode(array('message' => 'Mock endpoint not found.'));
