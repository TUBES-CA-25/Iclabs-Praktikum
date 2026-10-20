<?php

function class_save($id, $b)
{
    global $user;

    transaction(function () use ($id, $b, $user) {
        $c = load_class($id, true);

        editable($c);
        version_check($c, $b);

        if (!is_array($b['changes'] ?? null) || count($b['changes']) > 5000) {
            reject('Perubahan tidak valid.');
        }

        if (isset($b['meeting'])) {
            $m = $b['meeting'];
            $date = $m['date'] ?? '';
            $parsed = DateTime::createFromFormat('!Y-m-d', $date);

            if (
                !integer($m['number'] ?? null, 1, (int) $c['meeting_count']) ||
                !$parsed ||
                $parsed->format('Y-m-d') !== $date ||
                !trim($m['material'] ?? '')
            ) {
                reject('Nomor, tanggal, dan materi pertemuan wajib valid.');
            }

            $old = row(
                'SELECT * FROM pertemuan_praktikum WHERE jadwal_id=? AND number=?',
                array($id, $m['number'])
            );

            query(
                'INSERT INTO pertemuan_praktikum(jadwal_id,number,date,material)
                 VALUES(?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    date=VALUES(date),
                    material=VALUES(material)',
                array(
                    $id,
                    $m['number'],
                    $date,
                    trim($m['material'])
                )
            );

            audit(
                $id,
                'Pertemuan ' . $m['number'],
                $old ?: null,
                $m
            );
        }

        foreach ($b['changes'] as $x) {
            if (
                !row(
                    'SELECT id FROM peserta_praktikum WHERE id=? AND jadwal_id=? AND active=1',
                    array($x['student'] ?? 0, $id)
                )
            ) {
                reject('Mahasiswa bukan peserta kelas.');
            }

            $kind = $x['kind'] ?? '';
            $value = $x['value'] ?? null;

            if ($kind === 'attendance') {
                $table = 'absensi';
                $key = 'pertemuan_id';

                $meeting = row(
                    'SELECT id FROM pertemuan_praktikum WHERE jadwal_id=? AND number=?',
                    array($id, $x['number'] ?? 0)
                );

                $index = $meeting ? $meeting['id'] : null;

                if (
                    !$index ||
                    !in_array($value, array('H', 'I', 'S', 'A', 'T'), true)
                ) {
                    reject('Pertemuan atau status absensi tidak valid.');
                }
            } else {
                if ($value !== null && !number_value($value)) {
                    reject('Nilai harus 0–100.');
                }

                if ($kind === 'evaluation') {
                    $table = 'nilai_evaluasi';
                    $key = 'evaluasi_id';
                    $index = $x['number'] ?? null;

                    if (
                        !integer(
                            $index,
                            1,
                            (int) $c['evaluation_count']
                        )
                    ) {
                        reject('Evaluasi tidak valid.');
                    }
                } elseif ($kind === 'assistance') {
                    $table = 'nilai_asistensi';
                    $key = 'number';
                    $index = $x['number'] ?? null;

                    if (
                        !integer(
                            $index,
                            1,
                            (int) $c['assistance_count']
                        )
                    ) {
                        reject('Asistensi tidak valid.');
                    }
                } elseif (in_array($kind, array('MID', 'UAS'), true)) {
                    $table = 'nilai_ujian';
                    $key = 'kind';
                    $index = $kind;
                } else {
                    reject('Jenis nilai tidak valid.');
                }
            }

            $old = row(
                "SELECT * FROM $table WHERE peserta_id=? AND $key=?",
                array($x['student'], $index)
            );

            if ($value === null) {
                query(
                    "DELETE FROM $table WHERE peserta_id=? AND $key=?",
                    array($x['student'], $index)
                );
            } elseif ($kind === 'attendance') {
                query(
                    'INSERT INTO absensi(
                        peserta_id,
                        pertemuan_id,
                        value,
                        note,
                        updated_by,
                        updated_at
                    )
                    VALUES(?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        value=VALUES(value),
                        note=VALUES(note),
                        updated_by=VALUES(updated_by),
                        updated_at=VALUES(updated_at)',
                    array(
                        $x['student'],
                        $index,
                        $value,
                        (string) ($x['note'] ?? ''),
                        $user['id'],
                        stamp()
                    )
                );
            } else {
                query(
                    "INSERT INTO $table(
                        peserta_id,
                        $key,
                        value,
                        updated_by,
                        updated_at
                    )
                    VALUES(?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        value=VALUES(value),
                        updated_by=VALUES(updated_by),
                        updated_at=VALUES(updated_at)",
                    array(
                        $x['student'],
                        $index,
                        $value,
                        $user['id'],
                        stamp()
                    )
                );
            }

            audit(
                $id,
                $x['student'] . ': ' . $kind . ' ' . ($x['number'] ?? ''),
                $old ?: null,
                $x
            );
        }

        query(
            'UPDATE jadwal_praktikum SET version=version+1 WHERE id=?',
            array($id)
        );
    });
}

function components($id, $b)
{
    transaction(function () use ($id, $b) {
        $c = load_class($id, true);

        editable($c);
        version_check($c, $b);

        $keys = array(
            'meeting_count',
            'evaluation_count',
            'assistance_count'
        );

        foreach ($keys as $k) {
            $max = $k === 'meeting_count' ? 14 : 100;

            if (!integer($b[$k] ?? null, 1, $max)) {
                reject('Pertemuan 1–14; evaluasi dan asistensi 1–100.');
            }
        }

        $hasMeetingData = row(
            'SELECT id
             FROM pertemuan_praktikum
             WHERE jadwal_id=? AND number>?',
            array($id, $b['meeting_count'])
        );

        $hasEvaluationData = row(
            'SELECT n.id
             FROM nilai_evaluasi n
             JOIN peserta_praktikum p ON p.id=n.peserta_id
             WHERE p.jadwal_id=? AND n.evaluasi_id>?',
            array($id, $b['evaluation_count'])
        );

        $hasAssistanceData = row(
            'SELECT n.id
             FROM nilai_asistensi n
             JOIN peserta_praktikum p ON p.id=n.peserta_id
             WHERE p.jadwal_id=? AND n.number>?',
            array($id, $b['assistance_count'])
        );

        if ($hasMeetingData || $hasEvaluationData || $hasAssistanceData) {
            reject(
                'Jumlah tidak dapat dikurangi karena sudah memiliki data.'
            );
        }

        query(
            'UPDATE jadwal_praktikum
             SET meeting_count=?,
                 evaluation_count=?,
                 assistance_count=?,
                 version=version+1
             WHERE id=?',
            array(
                $b['meeting_count'],
                $b['evaluation_count'],
                $b['assistance_count'],
                $id
            )
        );

        audit(
            $id,
            'Jumlah pengisian',
            array_intersect_key($c, array_flip($keys)),
            array_intersect_key($b, array_flip($keys))
        );
    });
}

function workflow($id, $b)
{
    global $user;

    transaction(function () use ($id, $b, $user) {
        $c = load_class($id, true);

        version_check($c, $b);

        $next = $b['state'] ?? '';
        $note = trim($b['note'] ?? '');
        $role = $user['role'];
        $state = $c['state'];

        $allowed =
            (
                in_array(
                    $role,
                    array('admin', 'asisten'),
                    true
                ) &&
                in_array(
                    $state,
                    array('Draft', 'Perlu Revisi'),
                    true
                ) &&
                $next === 'Diajukan'
            ) ||
            (
                $role === 'dosen' &&
                $state === 'Diajukan' &&
                in_array(
                    $next,
                    array('Perlu Revisi', 'Terverifikasi'),
                    true
                )
            ) ||
            (
                $role === 'dosen' &&
                $state === 'Terverifikasi' &&
                $next === 'Dikunci'
            ) ||
            (
                $role === 'admin' &&
                in_array(
                    $state,
                    array('Dikunci', 'Terverifikasi', 'Diajukan'),
                    true
                ) &&
                $next === 'Draft'
            );

        if (!$allowed || !$c['active']) {
            reject('Transisi status tidak diizinkan.', 403);
        }

        if (
            in_array(
                $next,
                array('Draft', 'Perlu Revisi'),
                true
            ) &&
            !$note
        ) {
            reject('Alasan wajib diisi.');
        }

        if ($next === 'Diajukan') {
            $d = detail($c);

            $complete =
                count($d['students']) > 0 &&
                count($d['meetings']) === (int) $c['meeting_count'];

            foreach ($d['students'] as $s) {
                $complete =
                    $complete &&
                    count($s['attendance']) ===
                        (int) $c['meeting_count'] &&
                    count($s['evaluations']) ===
                        (int) $c['evaluation_count'] &&
                    count($s['assistance']) ===
                        (int) $c['assistance_count'] &&
                    $s['mid'] !== null &&
                    $s['uas'] !== null;
            }

            if (!$complete) {
                reject(
                    'Lengkapi ' .
                    $c['meeting_count'] .
                    ' absensi, ' .
                    $c['evaluation_count'] .
                    ' evaluasi, ' .
                    $c['assistance_count'] .
                    ' asistensi, MID dan UAS untuk semua peserta.'
                );
            }
        }

        query(
            'UPDATE jadwal_praktikum
             SET state=?, note=?, version=version+1
             WHERE id=?',
            array($next, $note, $id)
        );

        query(
            'INSERT INTO verifikasi_nilai(
                jadwal_id,
                user_id,
                state,
                note,
                created_at
            )
            VALUES(?,?,?,?,?)',
            array(
                $id,
                $user['id'],
                $next,
                $note,
                stamp()
            )
        );

        audit(
            $id,
            'Status penilaian',
            $state,
            array(
                'state' => $next,
                'note' => $note
            )
        );
    });
}

function sync_students($id)
{
    require_admin();

    transaction(function () use ($id) {
        $c = load_class($id, true);

        editable($c);

        $before = rows(
            'SELECT * FROM peserta_praktikum WHERE jadwal_id=?',
            array($id)
        );

        query(
            "INSERT IGNORE INTO peserta_praktikum(
                jadwal_id,
                mahasiswa_id
            )
            SELECT ?, m.id
            FROM mahasiswa m JOIN anggota_frekuensi a ON a.mahasiswa_id=m.id JOIN frekuensi_praktikum f ON f.id=a.frekuensi_id
            WHERE f.name=? AND m.status='Aktif'",
            array($id, $c['frequency'])
        );

        query(
            'UPDATE jadwal_praktikum SET version=version+1 WHERE id=?',
            array($id)
        );

        audit(
            $id,
            'Sinkronisasi peserta',
            $before,
            rows(
                'SELECT * FROM peserta_praktikum WHERE jadwal_id=?',
                array($id)
            )
        );
    });
}
