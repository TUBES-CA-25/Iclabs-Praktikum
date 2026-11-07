    <?php

header('Content-Type: application/json; charset=utf-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$request = json_decode(file_get_contents('php://input') ?: '{}', true) ?: array();
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$isStudent = ($request['identitas'] ?? '') === 'uji-mahasiswa@iclabs.test'
    || strpos($authHeader, 'Bearer ' . str_repeat('b', 64)) === 0;
$isAssistant3 = ($request['identitas'] ?? '') === 'uji-asisten3@iclabs.test'
    || strpos($authHeader, 'Bearer ' . str_repeat('c', 64)) === 0;
$isParticipant = ($request['identitas'] ?? '') === 'uji-praktikan@iclabs.test'
    || strpos($authHeader, 'Bearer ' . str_repeat('d', 64)) === 0;

if ($isAssistant3) {
    $user = array(
        'id' => 9003,
        'email_login' => 'uji-asisten3@iclabs.test',
        'username' => 'uji-asisten3',
        'jenis_akun' => array('kode' => 'INTERNAL', 'nama' => 'Internal'),
        'status_akun' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
        'profil' => array('nama_lengkap' => 'Asisten 3 Uji', 'gelar_depan' => null, 'gelar_belakang' => null),
        'peran' => array(array('kode' => 'ASISTEN', 'nama' => 'Asisten Laboratorium')),
    );
} elseif ($isParticipant) {
    $user = array(
        'id' => 9004,
        'email_login' => 'uji-praktikan@iclabs.test',
        'username' => 'uji-praktikan',
        'jenis_akun' => array('kode' => 'INTERNAL', 'nama' => 'Internal'),
        'status_akun' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
        'profil' => array('nama_lengkap' => 'Praktikan Dual Role Uji', 'gelar_depan' => null, 'gelar_belakang' => null),
        'peran' => array(
            array('kode' => 'ASISTEN', 'nama' => 'Asisten Laboratorium'),
            array('kode' => 'MAHASISWA', 'nama' => 'Mahasiswa')
        ),
    );
} else {
    $user = array(
        'id' => $isStudent ? 9002 : 9001,
        'email_login' => $isStudent ? 'uji-mahasiswa@iclabs.test' : 'uji-admin@iclabs.test',
        'username' => $isStudent ? 'uji-mahasiswa' : 'uji-admin',
        'jenis_akun' => array('kode' => 'INTERNAL', 'nama' => 'Internal'),
        'status_akun' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
        'profil' => array('nama_lengkap' => $isStudent ? 'Mahasiswa Uji' : 'Admin Uji', 'gelar_depan' => null, 'gelar_belakang' => null),
        'peran' => array(array('kode' => $isStudent ? 'MAHASISWA' : 'ADMIN_ICLABS', 'nama' => $isStudent ? 'Mahasiswa' : 'Administrator ICLabs')),
    );
}

if (in_array($path, array('/api/v1/auth/aktivasi/request', '/api/v1/auth/reset-password/request'), true) && $method === 'POST') {
    if (($request['email_login'] ?? '') === 'rate-limit@iclabs.test') {
        http_response_code(429);
        echo json_encode(array('message' => 'Rate limited.'));
        return;
    }
    http_response_code(202);
    echo json_encode(array('message' => 'If eligible, instructions will be sent.'));
    return;
}

if ($path === '/api/v1/auth/login' && $method === 'POST') {
    $token = $isAssistant3 ? str_repeat('c', 64) : ($isParticipant ? str_repeat('d', 64) : str_repeat($isStudent ? 'b' : 'a', 64));
    echo json_encode(array('data' => array('token' => $token, 'kadaluarsa_pada' => gmdate('c', time() + 3600), 'user' => $user)));
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
    $assignments = array(
        array('field' => 'id_user_asisten_utama', 'role' => 'ASISTEN_UTAMA', 'status' => 'BENTROK_JADWAL_KULIAH'),
        array('field' => 'id_user_asisten_pengajar_pendukung', 'role' => 'ASISTEN_PENGAJAR_PENDUKUNG', 'status' => 'TERSEDIA'),
        array('field' => 'id_user_pendamping_teknis', 'role' => 'PENDAMPING_TEKNIS', 'status' => 'TERSEDIA'),
    );
    $recommendations = array();
    foreach ($assignments as $assignment) {
        $assistantId = (int) ($request[$assignment['field']] ?? 0);
        if ($assistantId > 0) {
            $recommendations[] = array(
                'id_user' => $assistantId,
                'kode_peran' => $assignment['role'],
                'kode_ketersediaan' => $assignment['status'],
                'dapat_dipilih' => true,
                'alasan' => $assignment['status'] === 'BENTROK_JADWAL_KULIAH' ? 'Jadwal kuliah pribadi bertabrakan. Asisten tetap dapat dipilih atas keputusan Laboran/Admin.' : null,
                'bentrok_dengan' => $assignment['status'] === 'BENTROK_JADWAL_KULIAH' ? array('nama_mata_kuliah' => 'Algoritma dan Pemrograman', 'kode_mata_kuliah' => '130107RP4', 'kode_kelas' => 'TI-A1', 'hari_ke' => 2, 'jam_mulai' => '09:00', 'jam_selesai' => '10:00') : null,
            );
        }
    }
    echo json_encode(array('data' => array('rekomendasi_ketersediaan_asisten' => $recommendations)), JSON_UNESCAPED_UNICODE);
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
if ($path === '/api/v1/praktikum/jadwal/rekomendasi-google-sheet' && $method === 'POST') {
    $row = $request['jadwal'][0] ?? array();
    echo json_encode(array('data' => array(
        'jumlah_nilai_unik_diperiksa' => 1,
        'jumlah_rekomendasi' => 1,
        'jumlah_nilai_dilewati_batas' => 0,
        'rekomendasi' => array(array(
            'baris_sumber' => (int) ($row['baris_sumber'] ?? 0),
            'field' => 'asisten_3',
            'kandidat_id' => 402,
            'kandidat' => array('id_user' => 402, 'nama' => 'Pendamping Uji', 'kode_tingkat_asisten' => 'ASISTEN_3'),
            'keyakinan_ai' => 91,
            'alasan' => 'Nama paling mendekati kandidat Pendamping.',
        )),
    )), JSON_UNESCAPED_UNICODE);
    return;
}
if ($path === '/api/v1/praktikum/jadwal/sinkronisasi-google-sheet' && $method === 'POST') {
    if (($request['jadwal'][0]['asisten_2'] ?? null) === '__SIMULATE_CONFLICT__') {
        http_response_code(422);
        echo json_encode(array(
            'message' => 'Sinkronisasi dibatalkan. Ada data Sheet yang belum cocok dengan master pusat.',
            'errors' => array('baris' => array(array(
                'baris' => 12,
                'pesan' => 'Satu akun tidak boleh mengisi lebih dari satu peran pada jadwal yang sama. Pilih akun berbeda untuk setiap peran.',
            ))),
        ), JSON_UNESCAPED_UNICODE);
        return;
    }
    echo json_encode(array('data' => array(
        'periode' => array('id' => (int) ($request['id_periode_akademik'] ?? 0), 'tahun_akademik' => '2026/2027', 'semester' => 'Ganjil'),
        'jumlah_baris' => 1,
        'dibuat' => 1,
        'diperbarui' => 0,
        'tidak_berubah' => 0,
        'jumlah_peserta_sumber' => 17,
        'roster_diubah' => false,
        'assistant_slots_received' => array(
            $request['jadwal'][0]['asisten_1'] ?? null,
            $request['jadwal'][0]['asisten_2'] ?? null,
            $request['jadwal'][0]['asisten_3'] ?? null,
        ),
        'staff_user_ids_received' => array(
            $request['jadwal'][0]['id_user_dosen'] ?? null,
            $request['jadwal'][0]['id_user_asisten_utama'] ?? null,
            $request['jadwal'][0]['id_user_asisten_pengajar_pendukung'] ?? null,
            $request['jadwal'][0]['id_user_pendamping_teknis'] ?? null,
        ),
        'baris_dengan_perbedaan_nama_mk_atau_sks' => array(),
        'jadwal_yang_tidak_ada_di_sheet_dihapus' => false,
    )), JSON_UNESCAPED_UNICODE);
    return;
}
if ($path === '/api/v1/praktikum/kelas' && $method === 'GET') {
    $roleInClass = $isAssistant3 ? 'ASISTEN_3' : ($isParticipant ? 'PRAKTIKAN' : ($isStudent ? 'PRAKTIKAN' : 'ADMIN'));
    echo json_encode(array('data' => array(array(
        'id_kelas_kuliah' => 10, 'status' => 'AKTIF', 'jumlah_pertemuan_rencana' => 12,
        'catatan' => null,
        'peran_di_kelas' => $roleInClass,
        'periode' => array('id' => 1, 'tahun_akademik' => '2026/2027', 'kode_semester' => 'GANJIL', 'semester' => 'Ganjil'),
        'program_studi' => array('kode' => 'TI', 'nama' => 'Teknik Informatika'),
        'mata_kuliah' => array('id' => 20, 'kode' => '130107RP4', 'nama' => 'Algoritma dan Pemrograman'),
        'kelompok' => array('id' => 30, 'kode' => 'TI_ALPRO-1', 'nama' => 'Frekuensi 1', 'versi' => 1),
        'jumlah_jadwal' => 0,
    ), array(
        'id_kelas_kuliah' => 12, 'status' => 'AKTIF', 'jumlah_pertemuan_rencana' => 12,
        'catatan' => null,
        'peran_di_kelas' => $roleInClass,
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
    );
    if ($isAssistant3) {
        $class['peran_di_kelas'] = 'ASISTEN_3';
        $class['peserta'] = array(array('id' => 70, 'id_mahasiswa' => 700, 'nim' => '13020230049', 'nama' => 'Mahasiswa Uji', 'status' => 'AKTIF', 'terdaftar_pada' => '2026-09-07', 'kehadiran' => array(array('nomor_pertemuan' => 1, 'status' => 'HADIR'), array('nomor_pertemuan' => 2, 'status' => 'IZIN'))));
    } elseif ($isParticipant) {
        $class['peran_di_kelas'] = 'PRAKTIKAN';
        $class['kehadiran_saya'] = array(array('nomor_pertemuan' => 1, 'status' => 'HADIR'), array('nomor_pertemuan' => 2, 'status' => 'IZIN'));
        $class['nilai_saya'] = array(
            array('kode_komponen' => 'EVALUASI_1', 'nama_komponen' => 'Evaluasi 1', 'bobot_persen' => 20, 'nilai' => 80),
            array('kode_komponen' => 'ASISTENSI_1', 'nama_komponen' => 'Asistensi 1', 'bobot_persen' => 20, 'nilai' => 90),
            array('kode_komponen' => 'MID', 'nama_komponen' => 'MID', 'bobot_persen' => 30, 'nilai' => 85),
            array('kode_komponen' => 'UAS', 'nama_komponen' => 'UAS', 'bobot_persen' => 30, 'nilai' => 85)
        );
        $class['ringkasan_nilai_saya'] = array('konfigurasi_nilai_tersedia' => true, 'rata_evaluasi' => 80, 'rata_asistensi' => 90, 'nilai_akhir' => 85, 'predikat' => 'A');
    } else {
        $class['peran_di_kelas'] = 'ADMIN';
        $class['peserta'] = array(array('id' => 70, 'id_mahasiswa' => 700, 'nim' => '13020230049', 'nama' => 'Mahasiswa Uji', 'status' => 'AKTIF', 'terdaftar_pada' => '2026-09-07', 'kehadiran' => array(array('nomor_pertemuan' => 1, 'status' => 'HADIR'), array('nomor_pertemuan' => 2, 'status' => 'IZIN')), 'nilai' => array(array('kode_komponen' => 'EVALUASI_1', 'nilai' => 80), array('kode_komponen' => 'ASISTENSI_1', 'nilai' => 90), array('kode_komponen' => 'MID', 'nilai' => 85), array('kode_komponen' => 'UAS', 'nilai' => 85)), 'ringkasan_nilai' => array('konfigurasi_nilai_tersedia' => true, 'rata_evaluasi' => 80, 'rata_asistensi' => 90, 'nilai_akhir' => 85, 'predikat' => 'A')));
    }
    echo json_encode(array('data' => $class));
    return;
}
if ($path === '/api/v1/praktikum/kelas/10/perubahan' && $method === 'PUT') {
    if ($isAssistant3 || $isParticipant) {
        http_response_code(403);
        echo json_encode(array('message' => 'Anda tidak memiliki wewenang untuk mengubah data pada kelas ini.'));
        return;
    }
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
if ($path === '/api/v1/manajemen-akses/peran' && $method === 'GET') {
    echo json_encode(array('data' => array(
        array('id' => 1, 'kode' => 'SUPER_ADMIN', 'nama' => 'Super Administrator', 'deskripsi' => 'Akses penuh.'),
        array('id' => 2, 'kode' => 'ADMIN_ICLABS', 'nama' => 'Administrator ICLabs', 'deskripsi' => 'Pengelola operasional.'),
        array('id' => 3, 'kode' => 'KEPALA_LAB', 'nama' => 'Kepala Laboratorium', 'deskripsi' => 'Pimpinan lab.'),
        array('id' => 4, 'kode' => 'LABORAN', 'nama' => 'Laboran', 'deskripsi' => 'Pengelola lab.'),
        array('id' => 5, 'kode' => 'DOSEN', 'nama' => 'Dosen Pengampu', 'deskripsi' => 'Pengajar.'),
        array('id' => 6, 'kode' => 'ASISTEN', 'nama' => 'Asisten Laboratorium', 'deskripsi' => 'Asisten.'),
        array('id' => 7, 'kode' => 'MAHASISWA', 'nama' => 'Mahasiswa', 'deskripsi' => 'Peserta.')
    )));
    return;
}
if ($path === '/api/v1/manajemen-akses/pengguna' && $method === 'GET') {
    $cari = strtolower((string) ($_GET['cari'] ?? ''));
    $kodePeran = (string) ($_GET['kode_peran'] ?? '');
    $statusAkun = (string) ($_GET['status_akun'] ?? '');
    $allUsers = array(
        array(
            'id' => 9001,
            'email_login' => 'uji-admin@iclabs.test',
            'username' => 'uji-admin',
            'nim' => null,
            'nama_lengkap' => 'Admin Uji',
            'versi_profil' => 1,
            'status_akun' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
            'dapat_diberi_peran' => true,
            'peran' => array(
                array(
                    'id' => 101,
                    'peran' => array('id' => 1, 'kode' => 'SUPER_ADMIN', 'nama' => 'Super Administrator'),
                    'mulai_pada' => '2026-01-01 00:00:00',
                    'berakhir_pada' => null,
                    'catatan_pemberian' => 'Akun inisialisasi sistem',
                    'versi' => 1
                ),
                array(
                    'id' => 102,
                    'peran' => array('id' => 2, 'kode' => 'ADMIN_ICLABS', 'nama' => 'Administrator ICLabs'),
                    'mulai_pada' => '2026-01-01 00:00:00',
                    'berakhir_pada' => null,
                    'catatan_pemberian' => 'Pengelola sistem',
                    'versi' => 1
                )
            )
        ),
        array(
            'id' => 9003,
            'email_login' => 'laboran@iclabs.test',
            'username' => 'laboran-uji',
            'nim' => null,
            'nama_lengkap' => 'Laboran Uji',
            'versi_profil' => 1,
            'status_akun' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
            'dapat_diberi_peran' => true,
            'peran' => array(
                array(
                    'id' => 103,
                    'peran' => array('id' => 4, 'kode' => 'LABORAN', 'nama' => 'Laboran'),
                    'mulai_pada' => '2026-02-01 00:00:00',
                    'berakhir_pada' => null,
                    'catatan_pemberian' => 'Staf teknis laboratorium',
                    'versi' => 1
                )
            )
        ),
        array(
            'id' => 9004,
            'email_login' => 'pending@iclabs.test',
            'username' => null,
            'nim' => null,
            'nama_lengkap' => 'User Baru Menunggu',
            'versi_profil' => 1,
            'status_akun' => array('kode' => 'PENDING_AKTIVASI', 'nama' => 'Menunggu Aktivasi'),
            'dapat_diberi_peran' => true,
            'peran' => array(
                array(
                    'id' => 104,
                    'peran' => array('id' => 6, 'kode' => 'ASISTEN', 'nama' => 'Asisten Laboratorium'),
                    'mulai_pada' => '2026-03-01 00:00:00',
                    'berakhir_pada' => null,
                    'catatan_pemberian' => 'Perekrutan periode baru',
                    'versi' => 1
                )
            )
        )
    );
    $filtered = array_values(array_filter($allUsers, function($u) use ($cari, $kodePeran, $statusAkun) {
        if ($cari !== '') {
            $matched = strpos(strtolower($u['nama_lengkap']), $cari) !== false
                || strpos(strtolower($u['email_login']), $cari) !== false
                || strpos(strtolower($u['username'] ?? ''), $cari) !== false;
            if (!$matched) return false;
        }
        if ($kodePeran !== '') {
            $hasRole = false;
            foreach ($u['peran'] as $p) {
                if (($p['peran']['kode'] ?? '') === $kodePeran) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) return false;
        }
        if ($statusAkun !== '' && ($u['status_akun']['kode'] ?? '') !== $statusAkun) {
            return false;
        }
        return true;
    }));
    echo json_encode(array('data' => $filtered));
    return;
}
if ($path === '/api/v1/manajemen-akses/pengguna' && $method === 'POST') {
    if (empty($request['nama_lengkap']) || empty($request['email_login']) || empty($request['kode_peran'])) {
        http_response_code(422);
        echo json_encode(array('message' => 'Validasi gagal.', 'errors' => array('nama_lengkap' => array('Wajib diisi'))));
        return;
    }
    http_response_code(201);
    echo json_encode(array(
        'data' => array(
            'pengguna' => array(
                'id' => 9099,
                'email_login' => $request['email_login'],
                'username' => $request['username'] ?? null,
                'nama_lengkap' => $request['nama_lengkap'],
                'versi_profil' => 1,
                'status_akun' => array('kode' => 'PENDING_AKTIVASI', 'nama' => 'Menunggu Aktivasi'),
                'dapat_diberi_peran' => true,
                'peran' => array(
                    array(
                        'id' => 999,
                        'peran' => array('id' => 6, 'kode' => $request['kode_peran'], 'nama' => $request['kode_peran']),
                        'mulai_pada' => gmdate('c'),
                        'berakhir_pada' => null,
                        'catatan_pemberian' => $request['catatan_pemberian'] ?? null,
                        'versi' => 1
                    )
                )
            ),
            'email_aktivasi' => array('status' => 'TERKIRIM', 'pesan' => 'Email instruksi aktivasi akun telah dikirimkan.')
        )
    ));
    return;
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/profil\z#', $path, $m) && $method === 'PATCH') {
    $userId = (int) $m[1];
    echo json_encode(array('data' => array(
        'id_user' => $userId,
        'nama_lengkap' => $request['nama_lengkap'] ?? 'Nama Baru',
        'versi' => ((int) ($request['versi'] ?? 1)) + 1
    )));
    return;
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/peran\z#', $path, $m) && $method === 'POST') {
    $note = (string) ($request['catatan_pemberian'] ?? '');
    if (strlen($note) < 10) {
        http_response_code(422);
        echo json_encode(array('message' => 'Alasan pemberian role minimal 10 karakter.'));
        return;
    }
    http_response_code(201);
    echo json_encode(array('data' => array(
        'id' => 201,
        'peran' => array('id' => 5, 'kode' => $request['kode_peran'] ?? '', 'nama' => $request['kode_peran'] ?? ''),
        'mulai_pada' => gmdate('c'),
        'berakhir_pada' => null,
        'catatan_pemberian' => $note,
        'versi' => 1
    )));
    return;
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/peran/(\d+)/akhir\z#', $path, $m) && $method === 'PATCH') {
    $note = (string) ($request['catatan_berakhir'] ?? '');
    if (strlen($note) < 10) {
        http_response_code(422);
        echo json_encode(array('message' => 'Alasan pengakhiran role minimal 10 karakter.'));
        return;
    }
    echo json_encode(array('data' => array(
        'id' => (int) $m[2],
        'berakhir_pada' => gmdate('c'),
        'catatan_berakhir' => $note,
        'versi' => ((int) ($request['versi'] ?? 1)) + 1
    )));
    return;
}
if ($path === '/api/v1/manajemen-akses/peran/massal' && $method === 'POST') {
    $ids = (array) ($request['id_pengguna'] ?? array());
    echo json_encode(array('data' => array(
        'kode_peran' => $request['kode_peran'] ?? '',
        'jumlah_diminta' => count($ids),
        'berhasil' => array_map(function($id) { return array('id_user' => (int)$id, 'id_rel_user_peran' => 300 + (int)$id); }, $ids),
        'dilewati' => array()
    )));
    return;
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/password\z#', $path, $m) && $method === 'PATCH') {
    echo json_encode(array('message' => 'Password akun berhasil diperbarui. Semua sesi lama telah dicabut.'));
    return;
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/token-aktivasi\z#', $path, $m) && $method === 'POST') {
    http_response_code(201);
    echo json_encode(array('data' => array(
        'token' => 'mock-token-aktivasi-abcdef123456',
        'jenis_token' => 'AKTIVASI_AKUN',
        'kadaluarsa_pada' => gmdate('c', time() + 86400)
    )));
    return;
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/email-aktivasi\z#', $path, $m) && $method === 'POST') {
    echo json_encode(array('message' => 'Email instruksi aktivasi akun telah dikirimkan.'));
    return;
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/peran/(\d+)/cakupan-laboratorium\z#', $path, $m)) {
    if ($method === 'GET') {
        echo json_encode(array('data' => array(
            array(
                'id' => 501,
                'jenis_cakupan' => array('kode' => 'SEMUA_LAB', 'nama' => 'Semua Laboratorium'),
                'laboratorium' => null,
                'status' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
                'mulai_pada' => '2026-02-01 00:00:00',
                'berakhir_pada' => null,
                'catatan_pemberian' => 'Cakupan seluruh lab untuk laboran utama',
                'catatan_berakhir' => null,
                'versi' => 1
            )
        )));
        return;
    } elseif ($method === 'POST') {
        $note = (string) ($request['catatan_pemberian'] ?? '');
        if (strlen($note) < 10) {
            http_response_code(422);
            echo json_encode(array('message' => 'Alasan pemberian cakupan minimal 10 karakter.'));
            return;
        }
        http_response_code(201);
        echo json_encode(array('data' => array(
            'id' => 502,
            'jenis_cakupan' => array('kode' => $request['kode_jenis_cakupan'] ?? 'SEMUA_LAB', 'nama' => $request['kode_jenis_cakupan'] ?? 'Semua Lab'),
            'laboratorium' => !empty($request['id_laboratorium']) ? array('id' => (int) $request['id_laboratorium'], 'kode' => 'LAB-UJI', 'nama' => 'Laboratorium Uji') : null,
            'status' => array('kode' => 'AKTIF', 'nama' => 'Aktif'),
            'mulai_pada' => gmdate('c'),
            'berakhir_pada' => null,
            'catatan_pemberian' => $note,
            'versi' => 1
        )));
        return;
    }
}
if (preg_match('#\A/api/v1/manajemen-akses/pengguna/(\d+)/peran/(\d+)/cakupan-laboratorium/(\d+)/akhir\z#', $path, $m) && $method === 'PATCH') {
    $note = (string) ($request['catatan_berakhir'] ?? '');
    if (strlen($note) < 10) {
        http_response_code(422);
        echo json_encode(array('message' => 'Alasan pengakhiran cakupan minimal 10 karakter.'));
        return;
    }
    echo json_encode(array('data' => array(
        'id' => (int) $m[3],
        'berakhir_pada' => gmdate('c'),
        'catatan_berakhir' => $note,
        'versi' => ((int) ($request['versi'] ?? 1)) + 1
    )));
    return;
}
http_response_code(404);
echo json_encode(array('message' => 'Mock endpoint not found.'));
