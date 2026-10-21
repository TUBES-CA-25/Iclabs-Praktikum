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
        'mata_kuliah' => array(array('id' => 2, 'kode' => '130107RP4', 'nama' => 'Algoritma dan Pemrograman'), array('id' => 3, 'kode' => '130106RP4', 'nama' => 'Pemrograman')),
        'laboratorium' => array(array('id' => 2, 'kode' => 'LAB-UJI', 'nama' => 'Laboratorium Uji'), array('id' => 3, 'kode' => 'LAB-STARTUP', 'nama' => 'Lab Startup')),
        'pola_perulangan' => array(array('kode' => 'MINGGUAN', 'nama' => 'Mingguan')),
        'dosen' => array(array('id_user' => 300, 'nama' => 'Dosen Uji', 'email' => 'dosen@fikom.umi.ac.id')),
        'asisten' => array(array('id_user' => 400, 'nama' => 'Asisten Uji', 'email' => 'asisten1@fikom.umi.ac.id')),
    )));
    return;
}
if ($path === '/api/v1/praktikum/admin/periode-akademik') {
    $periods = array(
        array('id_periode_akademik' => 1, 'tahun_akademik' => '2026/2027', 'semester' => array('id' => 1, 'kode' => 'GANJIL', 'nama' => 'Ganjil'), 'tanggal_mulai' => '2026-09-07', 'tanggal_selesai' => '2026-12-12', 'aktif' => true, 'jumlah_kelas' => 2),
        array('id_periode_akademik' => 2, 'tahun_akademik' => '2026/2027', 'semester' => array('id' => 2, 'kode' => 'GENAP', 'nama' => 'Genap'), 'tanggal_mulai' => '2027-02-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => false, 'jumlah_kelas' => 0),
    );
    if ($method === 'GET') {
        echo json_encode(array('data' => array('periode' => $periods, 'semester' => array(
            array('id' => 1, 'kode' => 'GANJIL', 'nama' => 'Ganjil', 'aktif' => true),
            array('id' => 2, 'kode' => 'GENAP', 'nama' => 'Genap', 'aktif' => true),
        ))));
    } elseif ($method === 'POST') {
        echo json_encode(array('data' => array('id_periode_akademik' => 3, 'tahun_akademik' => $request['tahun_akademik'] ?? '', 'aktif' => false)), JSON_UNESCAPED_UNICODE);
    }
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/jadwal/pratinjau-ketersediaan-asisten' && $method === 'POST') {
    $assistantId = (int) ($request['id_user_asisten_utama'] ?? 0);
    echo json_encode(array('data' => array('rekomendasi_ketersediaan_asisten' => array(
        array('id_user' => $assistantId, 'kode_peran' => 'ASISTEN_UTAMA', 'kode_ketersediaan' => 'BENTROK_JADWAL_KULIAH', 'dapat_dipilih' => true, 'alasan' => 'Jadwal kuliah pribadi bertabrakan. Asisten tetap dapat dipilih atas keputusan Laboran/Admin.', 'bentrok_dengan' => array('nama_mata_kuliah' => 'Algoritma dan Pemrograman', 'kode_mata_kuliah' => '130107RP4', 'kode_kelas' => 'TI-A1', 'hari_ke' => 2, 'jam_mulai' => '09:00', 'jam_selesai' => '10:00')),
    ))), JSON_UNESCAPED_UNICODE);
    return;
}
if (preg_match('#\\A/api/v1/praktikum/admin/periode-akademik/(\\d+)(/aktifkan)?\\z#', $path, $match)) {
    $periodId = (int) $match[1];
    if (($match[2] ?? '') === '/aktifkan') {
        echo json_encode(array('data' => array('id_periode_akademik' => $periodId, 'aktif' => true)), JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(array('data' => array('id_periode_akademik' => $periodId, 'tanggal_mulai' => $request['tanggal_mulai'] ?? null, 'tanggal_selesai' => $request['tanggal_selesai'] ?? null)), JSON_UNESCAPED_UNICODE);
    }
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
if ($path === '/api/v1/praktikum/jadwal/import' && $method === 'POST') {
    $schedules = $request['jadwal'] ?? array();
    echo json_encode(array('data' => array('jumlah_diimpor' => count($schedules), 'jadwal' => $schedules)), JSON_UNESCAPED_UNICODE);
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
        'jumlah_jadwal' => 0,
    ), array(
        'id_kelas_kuliah' => 12, 'status' => 'AKTIF', 'jumlah_pertemuan_rencana' => 12,
        'catatan' => null,
        'periode' => array('id' => 1, 'tahun_akademik' => '2026/2027', 'kode_semester' => 'GANJIL', 'semester' => 'Ganjil', 'tanggal_mulai' => '2026-09-07', 'tanggal_selesai' => '2026-12-12'),
        'program_studi' => array('kode' => 'TI', 'nama' => 'Teknik Informatika'),
        'mata_kuliah' => array('id' => 21, 'kode' => '130106RP4', 'nama' => 'Pemrograman'),
        'kelompok' => array('id' => 32, 'kode' => 'TI_ALPRO-1', 'nama' => 'Frekuensi 1', 'versi' => 1),
        'jumlah_jadwal' => 0,
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
        'jadwal' => array(array('id' => 99, 'hari_ke' => 2, 'jam_mulai' => '09:00', 'jam_selesai' => '11:00', 'tanggal_mulai' => '2026-09-07', 'tanggal_selesai' => '2026-12-12', 'pola_perulangan' => 'MINGGUAN', 'status' => 'AKTIF', 'nama_laboratorium' => 'Laboratorium Uji', 'status_workflow' => 'TERVERIFIKASI', 'versi_workflow' => 1, 'pengajar' => array(array('peran' => 'DOSEN_PENGAMPU', 'nama' => 'Dosen Uji'), array('peran' => 'ASISTEN_UTAMA', 'nama' => 'Asisten Uji')))),
        'sesi' => array(
            array('id' => 90, 'nomor_pertemuan' => 1, 'tanggal_sesi' => '2026-09-14', 'status' => 'TERLAKSANA'),
            array('id' => 91, 'nomor_pertemuan' => 2, 'tanggal_sesi' => '2026-09-21', 'status' => 'TERLAKSANA')
        ),
        'komponen_nilai' => array(
            array('id' => 1, 'kode' => 'EVALUASI_1', 'nama' => 'Evaluasi 1', 'bobot_persen' => 20, 'urutan' => 1, 'aktif' => true),
            array('id' => 2, 'kode' => 'ASISTENSI_1', 'nama' => 'Asistensi 1', 'bobot_persen' => 20, 'urutan' => 2, 'aktif' => true),
            array('id' => 3, 'kode' => 'MID', 'nama' => 'Ujian Tengah Semester', 'bobot_persen' => 30, 'urutan' => 3, 'aktif' => true),
            array('id' => 4, 'kode' => 'UAS', 'nama' => 'Ujian Akhir Semester', 'bobot_persen' => 30, 'urutan' => 4, 'aktif' => true)
        ),
        'peserta' => array(array('id' => 70, 'id_mahasiswa' => 700, 'nim' => '13020230049', 'nama' => 'Mahasiswa Uji', 'status' => 'AKTIF', 'terdaftar_pada' => '2026-09-07', 'kehadiran' => array(array('nomor_pertemuan' => 1, 'status' => 'HADIR'), array('nomor_pertemuan' => 2, 'status' => 'IZIN')), 'nilai' => array(array('kode_komponen' => 'EVALUASI_1', 'nilai' => 80), array('kode_komponen' => 'ASISTENSI_1', 'nilai' => 90), array('kode_komponen' => 'MID', 'nilai' => 85), array('kode_komponen' => 'UAS', 'nilai' => 85)), 'ringkasan_nilai' => array('konfigurasi_nilai_tersedia' => true, 'rata_evaluasi' => 80, 'rata_asistensi' => 90, 'nilai_akhir' => 85, 'predikat' => 'A'))),
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
if ($path === '/api/v1/praktikum/kelas/10/calon-peserta' && $method === 'GET') {
    if (strpos($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ' . str_repeat('a', 64)) !== 0) {
        http_response_code(401);
        echo json_encode(array('message' => 'Unauthenticated.'));
        return;
    }
    echo json_encode(array('data' => array(array('id_mahasiswa' => 701, 'nim' => '13020260001', 'nama' => 'Mahasiswa Kandidat', 'kode_prodi' => 'TI'))));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/anggota' && $method === 'POST') {
    if (strpos($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ' . str_repeat('a', 64)) !== 0) {
        http_response_code(401);
        echo json_encode(array('message' => 'Unauthenticated.'));
        return;
    }
    http_response_code(201);
    echo json_encode(array('data' => array('id_kelas_kuliah' => 10, 'id_mahasiswa' => (int) ($request['id_mahasiswa'] ?? 0), 'tanggal_mulai' => $request['tanggal_mulai'] ?? null, 'versi' => (int) ($request['versi'] ?? 0) + 1)));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/anggota/import' && $method === 'POST') {
    if (strpos($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ' . str_repeat('a', 64)) !== 0) {
        http_response_code(401);
        echo json_encode(array('message' => 'Unauthenticated.'));
        return;
    }
    if (!empty($request['pratinjau'])) {
        echo json_encode(array('data' => array('pratinjau' => true, 'jumlah_baris' => count($request['anggota'] ?? array()), 'jumlah_valid' => count($request['anggota'] ?? array()), 'jumlah_konflik' => 0, 'baris' => array())));
        return;
    }
    http_response_code(201);
    echo json_encode(array('data' => array('pratinjau' => false, 'jumlah_diimpor' => count($request['anggota'] ?? array()), 'id_kelas_kuliah' => 10, 'versi' => (int) ($request['versi'] ?? 0) + 1)));
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
