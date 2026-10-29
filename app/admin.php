<?php

function master_fields()
{
    return array(
        'tahun_akademik' => array('name'),
        'semester' => array('name'),
        'prodi' => array('name'),
        'laboratorium' => array('name'),
        'mata_kuliah' => array('code', 'name', 'sks'),
        'frekuensi_praktikum' => array('name', 'prodi_id'),
        'mahasiswa' => array('nim', 'name', 'prodi_id', 'kelas_text', 'status'),
        'jadwal_praktikum' => array(
            'tahun_id',
            'semester_id',
            'mk_id',
            'prodi_id',
            'lab_id',
            'dosen_id',
            'asisten1_id',
            'asisten2_id',
            'day',
            'time',
            'frequency',
            'active'
        )
    );
}

function create_user($b)
{
    global $db;

    if (
        !in_array($b['role'] ?? '', array('admin', 'dosen', 'asisten', 'mahasiswa'), true)
        || !filter_var($b['email'] ?? '', FILTER_VALIDATE_EMAIL)
        || !is_string($b['password'] ?? null)
        || strlen($b['password']) < 6
    ) {
        reject('Isi email, role, dan password minimal 6 karakter.');
    }

    $work = function () use ($b, $db) {
        $student = null;

        if ($b['role'] === 'mahasiswa') {
            if (!integer($b['mahasiswa_id'] ?? null, 1, PHP_INT_MAX)) {
                reject('Pilih mahasiswa yang akan dihubungkan ke akun.');
            }

            $student = row(
                'SELECT id,name FROM mahasiswa WHERE id=? FOR UPDATE',
                array($b['mahasiswa_id'])
            );

            if (!$student) {
                reject('Mahasiswa tidak ditemukan.');
            }

            if (row(
                'SELECT user_id FROM akun_mahasiswa WHERE mahasiswa_id=?',
                array($student['id'])
            )) {
                reject('Mahasiswa ini sudah memiliki akun.');
            }
        } elseif (!trim($b['name'] ?? '')) {
            reject('Nama wajib diisi.');
        }

        $name = $student ? $student['name'] : trim($b['name']);

        query(
            'INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)',
            array(
                $name,
                strtolower($b['email']),
                password_hash($b['password'], PASSWORD_BCRYPT),
                $b['role']
            )
        );

        $id = (int) $db->lastInsertId();

        if ($b['role'] === 'dosen') {
            query('INSERT INTO dosen(user_id) VALUES(?)', array($id));
        }

        if (in_array($b['role'], array('asisten'), true)) {
            query('INSERT INTO asisten(user_id) VALUES(?)', array($id));
        }

        if ($student) {
            query(
                'INSERT INTO akun_mahasiswa(user_id,mahasiswa_id) VALUES(?,?)',
                array($id, $student['id'])
            );
        }

        audit(
            null,
            'Pengguna baru',
            null,
            array(
                'id' => $id,
                'name' => $name,
                'role' => $b['role'],
                'mahasiswa_id' => $student ? $student['id'] : null
            )
        );
    };
    if ($db->inTransaction()) $work(); else transaction($work);
}

function validate_master($table, $v)
{
    foreach ($v as $k => $value) {
        if ($table === 'jadwal_praktikum' && $k === 'asisten2_id' && $value === null) continue;
        if ($k === 'frequency') {
            if (!is_string($value) || mb_strlen($value, 'UTF-8') > 100) reject('Frekuensi harus berupa teks maksimal 100 karakter.');
            continue;
        }
        if ($value === null || trim((string) $value) === '') {
            reject('Semua kolom wajib diisi.');
        }

        if (substr($k, -3) === '_id' && !integer($value, 1, PHP_INT_MAX)) {
            reject('Pilihan relasi tidak valid.');
        }
    }

    if ($table === 'mata_kuliah' && !integer($v['sks'], 1, 30)) {
        reject('SKS harus 1â€“30.');
    }

    if ($table === 'mahasiswa') {
        if (!in_array($v['status'], array('Aktif', 'Nonaktif'), true)) {
            reject('Status mahasiswa tidak valid.');
        }
    }

    if ($table === 'jadwal_praktikum') {
        $f=row('SELECT prodi_id FROM frekuensi_praktikum WHERE name=?',array($v['frequency']));
        if (!$f || (int)$f['prodi_id']!==(int)$v['prodi_id']) reject('Pilih frekuensi yang terdaftar pada program studi tersebut.');
        if ($v['asisten1_id'] === $v['asisten2_id']) {
            reject('Asisten 1 dan 2 harus berbeda.');
        }

        if (
            !in_array(
                $v['day'],
                array('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
                true
            )
            || !in_array($v['active'], array(0, 1), true)
        ) {
            reject('Hari atau status jadwal tidak valid.');
        }

        valid_time($v['time']);

        if (!row(
            "SELECT d.id
             FROM dosen d
             JOIN users u ON u.id=d.user_id
             WHERE d.id=?
               AND u.role='dosen'
               AND u.active=1",
            array($v['dosen_id'])
        )) {
            reject('Dosen tidak aktif atau tidak valid.');
        }

        foreach (array('asisten1_id', 'asisten2_id') as $k) {
            if ($k === 'asisten2_id' && $v[$k] === null) continue;
            if (!row(
                "SELECT a.id
                 FROM asisten a
                 JOIN users u ON u.id=a.user_id
                 WHERE a.id=?
                   AND u.role IN ('asisten')
                   AND u.active=1",
                array($v[$k])
            )) {
                reject('Asisten tidak aktif atau tidak valid.');
            }
        }
    }
}

function valid_time($time)
{
    $time = str_replace(array('â€“', 'â€”'), '-', trim($time));

    if (
        !preg_match(
            '/^([01]\d|2[0-3])[.:]([0-5]\d)\s*-\s*([01]\d|2[0-3])[.:]([0-5]\d)$/',
            $time,
            $m
        )
        || ((int) $m[1] * 60 + (int) $m[2]) >= ((int) $m[3] * 60 + (int) $m[4])
    ) {
        reject('Jam harus HH.MM-HH.MM, selesai setelah mulai.');
    }

    return $m[1] . '.' . $m[2] . '-' . $m[3] . '.' . $m[4];
}

function schedule_headers()
{
    return array(
        'tahun akademik',
        'semester',
        'prodi',
        'kode mk',
        'laboratorium',
        'email dosen',
        'email asisten 1',
        'email asisten 2',
        'hari',
        'jam',
        'aktif',
        'frekuensi'
    );
}

function lookup_id($sql, $params)
{
    $r = row($sql, $params);

    if (!$r) {
        reject('Data master tidak ditemukan: ' . implode(' / ', $params));
    }

    return (int) $r['id'];
}

function schedule_rows($data)
{
    $errors = array();
    $resolved = array();
    $seen = array();
    $missing = array_diff(schedule_headers(), $data['headers'], array('email asisten 2'));

    if (count($missing)) {
        return array(
            'rows' => array(),
            'errors' => array(
                array(
                    'line' => 1,
                    'error' => 'Kolom wajib tidak ditemukan: ' . implode(', ', $missing)
                )
            )
        );
    }

    if (!count($data['rows'])) {
        return array(
            'rows' => array(),
            'errors' => array(
                array(
                    'line' => 1,
                    'error' => 'File kosong.'
                )
            )
        );
    }

    foreach ($data['rows'] as $r) {
        try {
            $v = $r['values'];

            foreach (schedule_headers() as $k) {
                if ($k === 'email asisten 2') continue;
                if (!strlen(trim($v[$k] ?? ''))) {
                    reject('Kolom ' . $k . ' wajib diisi.');
                }
            }

            $x = array();

            $x['tahun_id'] = lookup_id(
                'SELECT id FROM tahun_akademik WHERE name=?',
                array($v['tahun akademik'])
            );

            $x['semester_id'] = lookup_id(
                'SELECT id FROM semester WHERE name=?',
                array($v['semester'])
            );

            $p = lookup_id(
                'SELECT id FROM prodi WHERE name=?',
                array($v['prodi'])
            );

            $x['prodi_id'] = $p;

            $x['mk_id'] = lookup_id(
                'SELECT id FROM mata_kuliah WHERE code=?',
                array($v['kode mk'])
            );

            $x['lab_id'] = lookup_id(
                'SELECT id FROM laboratorium WHERE name=?',
                array($v['laboratorium'])
            );

            $x['dosen_id'] = lookup_id(
                "SELECT d.id
                 FROM dosen d
                 JOIN users u ON u.id=d.user_id
                 WHERE u.email=?
                   AND u.active=1
                   AND u.role='dosen'",
                array($v['email dosen'])
            );

            foreach (array(1, 2) as $i) {
                if ($i === 2 && trim($v['email asisten 2'] ?? '') === '') { $x['asisten2_id'] = null; continue; }
                $x['asisten' . $i . '_id'] = lookup_id(
                    "SELECT a.id
                     FROM asisten a
                     JOIN users u ON u.id=a.user_id
                     WHERE u.email=?
                       AND u.active=1
                       AND u.role IN ('asisten')",
                    array($v['email asisten ' . $i])
                );
            }

            $x['day'] = $v['hari'];
            $x['time'] = valid_time($v['jam']);
            $x['frequency'] = trim($v['frekuensi'] ?? '');

            $map = array(
                'aktif' => 1,
                'nonaktif' => 0,
                '1' => 1,
                '0' => 0
            );

            $x['active'] = $map[strtolower($v['aktif'])] ?? null;

            validate_master('jadwal_praktikum', $x);

            $key = implode(
                ':',
                array(
                    $x['tahun_id'],
                    $x['semester_id'],
                    $x['mk_id'],
                    $x['frequency']
                )
            );

            if (
                isset($seen[$key])
                || row(
                    'SELECT id FROM jadwal_praktikum
                     WHERE tahun_id=?
                       AND semester_id=?
                       AND mk_id=?
                       AND frequency=?',
                    array(
                        $x['tahun_id'],
                        $x['semester_id'],
                        $x['mk_id'],
                        $x['frequency']
                    )
                )
            ) {
                reject('Jadwal periode/mata kuliah/kelas sudah ada atau duplikat dalam file.');
            }

            $seen[$key] = true;

            $resolved[] = array(
                'line' => $r['line'],
                'values' => $x,
                'display' => $v
            );
        } catch (ApiError $e) {
            $errors[] = array(
                'line' => $r['line'],
                'error' => $e->getMessage()
            );
        }
    }

    return array(
        'rows' => $resolved,
        'errors' => $errors
    );
}

function admin_route($part)
{
    global $user, $db;

    $master = master_fields();

    if ($part === 'data' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = array();

        foreach ($master as $t => $fields) {
            $result[$t] = rows("SELECT * FROM $t");
        }

        $result['anggota_frekuensi']=rows('SELECT a.id,m.nim,m.name nama,a.kelas,f.name frekuensi FROM anggota_frekuensi a JOIN mahasiswa m ON m.id=a.mahasiswa_id JOIN frekuensi_praktikum f ON f.id=a.frekuensi_id ORDER BY f.name,m.nim');
        $result['users'] = rows(
            'SELECT u.id,u.name,u.email,u.role,u.active,a.mahasiswa_id,m.nim
             FROM users u
             LEFT JOIN akun_mahasiswa a ON a.user_id=u.id
             LEFT JOIN mahasiswa m ON m.id=a.mahasiswa_id'
        );

        $result['dosen'] = rows(
            'SELECT d.id,u.name
             FROM dosen d
             JOIN users u ON u.id=d.user_id'
        );

        $result['asisten'] = rows(
            'SELECT a.id,u.name
             FROM asisten a
             JOIN users u ON u.id=a.user_id'
        );

        $result['bands'] = rows(
            'SELECT * FROM konfigurasi_nilai ORDER BY min DESC'
        );

        send_json($result);
    }

    if ($part === 'audit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        send_json(
            rows(
                'SELECT l.*,u.name
                 FROM log_aktivitas l
                 JOIN users u ON u.id=l.user_id
                 ORDER BY l.id DESC
                 LIMIT 1000'
            )
        );
    }

    if ($part === 'schedule-template' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        xlsx_download(
            array(
                schedule_headers(),
                array(
                    '2026/2027',
                    'Ganjil',
                    'Teknik Informatika',
                    '130106RP4',
                    'Lab Startup',
                    'dosen@fikom.umi.ac.id',
                    'asisten1@fikom.umi.ac.id',
                    'asisten2@fikom.umi.ac.id',
                    'Selasa',
                    '07.00-10.20',
                    'Aktif',
                    'TI_ALPRO-1'
                )
            ),
            'template-jadwal.xlsx',
            1
        );
        exit;
    }

    require_post();
    $b = input();

    if ($part === 'users') {
        create_user($b);
        send_json(array('ok' => true));
    }

    if ($part === 'user-status') {
        if (($b['id'] ?? null) === $user['id']) {
            reject('Tidak dapat menonaktifkan akun sendiri.');
        }

        if (!in_array($b['active'] ?? null, array(0, 1), true)) {
            reject('Status tidak valid.');
        }

        transaction(function () use ($b) {
            query(
                'UPDATE users SET active=? WHERE id=?',
                array($b['active'], $b['id'])
            );

            query(
                'DELETE FROM sessions WHERE user_id=?',
                array($b['id'])
            );

            audit(
                null,
                'Status pengguna',
                $b['id'],
                $b['active']
            );
        });

        send_json(array('ok' => true));
    }

    if ($part === 'bands') {
        $bands = $b['bands'] ?? array();
        $letters = array('A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'E');

        if (
            count($bands) !== 9
            || count(array_unique(array_column($bands, 'letter'))) !== 9
            || count(array_unique(array_column($bands, 'min'))) !== 9
            || !in_array(0, array_column($bands, 'min'))
        ) {
            reject('Ambang harus unik dan mencakup nol.');
        }

        foreach ($bands as $band) {
            if (
                !in_array($band['letter'], $letters, true)
                || !number_value($band['min'])
            ) {
                reject('Ambang tidak valid.');
            }
        }

        transaction(function () use ($bands) {
            if (row(
                "SELECT id
                 FROM jadwal_praktikum
                 WHERE state IN ('Terverifikasi','Dikunci')
                 LIMIT 1
                 FOR UPDATE"
            )) {
                reject(
                    'Buka kembali kelas terverifikasi/dikunci sebelum mengubah ambang.',
                    409
                );
            }

            $old = rows('SELECT * FROM konfigurasi_nilai');

            query('DELETE FROM konfigurasi_nilai');

            foreach ($bands as $v) {
                query(
                    'INSERT INTO konfigurasi_nilai(letter,min) VALUES(?,?)',
                    array($v['letter'], $v['min'])
                );
            }

            audit(
                null,
                'Konfigurasi nilai',
                $old,
                $bands
            );
        });

        send_json(array('ok' => true));
    }

    if ($part === 'import-schedule') {
        $data = read_xlsx($b['base64'] ?? '');
        $preview = schedule_rows($data);

        if (($b['preview'] ?? false) === true) {
            send_json($preview);
        }

        if (count($preview['errors'])) {
            send_json(
                array_merge(
                    array('error' => 'Import dibatalkan. Perbaiki baris bermasalah.'),
                    $preview
                ),
                400
            );
        }

        transaction(function () use ($data, $db, $master) {
            query('SELECT id FROM jadwal_praktikum FOR UPDATE');

            $check = schedule_rows($data);

            if (count($check['errors'])) {
                reject('Jadwal berubah. Periksa ulang file.', 409);
            }

            $keys = $master['jadwal_praktikum'];

            foreach ($check['rows'] as $r) {
                $v = array();

                foreach ($keys as $k) {
                    $v[] = $r['values'][$k];
                }

                query(
                    'INSERT INTO jadwal_praktikum(' . implode(',', $keys) . ') VALUES(' .
                    implode(',', array_fill(0, count($keys), '?')) . ')',
                    $v
                );

                audit(
                    (int) $db->lastInsertId(),
                    'Import jadwal baris ' . $r['line'],
                    null,
                    $r['values']
                );
            }
        });

        send_json(
            array(
                'ok' => true,
                'count' => count($preview['rows'])
            )
        );
    }

    if ($part === 'import') {
        reject('Gunakan import Master Frekuensi Praktikum.');
        $data = read_xlsx($b['base64'] ?? '');

        $nim = in_array('nim', $data['headers'], true)
            ? 'nim'
            : 'stambuk';

        $name = in_array('nama', $data['headers'], true)
            ? 'nama'
            : 'nama mahasiswa';

        if (
            !in_array($nim, $data['headers'], true)
            || !in_array($name, $data['headers'], true)
        ) {
            reject('Kolom NIM dan Nama wajib ada.');
        }

        transaction(function () use ($b, $data, $nim, $name) {
            $c = row(
                'SELECT * FROM kelas WHERE id=?',
                array($b['kelas_id'] ?? 0)
            );

            if (!$c) {
                reject('Kelas tidak valid.');
            }

            foreach ($data['rows'] as $r) {
                $v = $r['values'];

                if (
                    !strlen($v[$nim] ?? '')
                    || !strlen($v[$name] ?? '')
                ) {
                    reject('NIM/Nama kosong pada baris ' . $r['line']);
                }

                if (row(
                    'SELECT id FROM mahasiswa WHERE nim=?',
                    array($v[$nim])
                )) {
                    reject('NIM duplikat: ' . $v[$nim]);
                }

                query(
                    "INSERT INTO mahasiswa(nim,name,prodi_id,kelas_id,status)
                     VALUES(?,?,?,?,'Aktif')",
                    array(
                        $v[$nim],
                        $v[$name],
                        $c['prodi_id'],
                        $c['id']
                    )
                );
            }

            audit(
                null,
                'Import mahasiswa',
                null,
                $data['rows']
            );
        });

        send_json(
            array(
                'ok' => true,
                'count' => count($data['rows'])
            )
        );
    }

    if ($part === 'master') {
        $table = $b['table'] ?? '';

        if (!isset($master[$table])) {
            reject('Tabel tidak valid.');
        }

        $fields = $master[$table];
        $values = array();

        foreach ($fields as $k) {
            $values[$k] = $b['values'][$k] ?? ($k === 'frequency' ? '' : null);
            if ($k === 'asisten2_id' && $values[$k] === '') $values[$k] = null;
            if ($k === 'frequency' && is_string($values[$k])) $values[$k] = trim($values[$k]);
        }

        validate_master($table, $values);

        transaction(function () use ($table, $fields, $values, $b) {
            $old = isset($b['id'])
                ? row(
                    "SELECT * FROM $table WHERE id=? FOR UPDATE",
                    array($b['id'])
                )
                : null;

            if (isset($b['id']) && !$old) {
                reject('Data tidak ditemukan.');
            }

            if ($table === 'frekuensi_praktikum' && $old && ($old['name'] !== $values['name'] || (int)$old['prodi_id'] !== (int)$values['prodi_id'])) {
                if (row('SELECT id FROM anggota_frekuensi WHERE frekuensi_id=? LIMIT 1',array($old['id'])) || row('SELECT id FROM jadwal_praktikum WHERE frequency=? LIMIT 1',array($old['name']))) reject('Frekuensi yang sudah digunakan tidak dapat diganti nama/prodinya. Buat frekuensi baru.');
            }
            if ($table === 'jadwal_praktikum' && $old) {
                if ($old['state'] !== 'Draft') {
                    reject('Jadwal hanya dapat diubah pada status Draft.');
                }

                if (
                    $old['frequency'] !== $values['frequency']
                    && row(
                        'SELECT id FROM peserta_praktikum WHERE jadwal_id=? LIMIT 1',
                        array($old['id'])
                    )
                ) {
                    reject('Jadwal berpeserta tidak dapat dipindahkan ke kelas lain.');
                }
            }

            if ($old) {
                $pairs = array();

                foreach ($fields as $k) {
                    $pairs[] = $k . '=?';
                }

                query(
                    "UPDATE $table SET " . implode(',', $pairs) . ' WHERE id=?',
                    array_merge(
                        array_values($values),
                        array($b['id'])
                    )
                );
            } else {
                query(
                    "INSERT INTO $table(" . implode(',', $fields) . ') VALUES(' .
                    implode(',', array_fill(0, count($fields), '?')) . ')',
                    array_values($values)
                );
            }

            audit(
                $table === 'jadwal_praktikum' ? ($b['id'] ?? null) : null,
                $table,
                $old,
                $values
            );
        });

        send_json(array('ok' => true));
    }

    reject('Endpoint tidak ditemukan.', 404);
}
