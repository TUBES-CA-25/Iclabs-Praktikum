<?php

function install_admin(
    $name,
    $email,
    $password,
    $demo = false
) {
    global $db;

    if (
        !trim($name) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 12
    ) {
        reject(
            'Nama, email valid, dan password minimal 12 karakter wajib diisi.'
        );
    }

    // One installer at a time even before the first user exists.
    $lock =
        'praktikum_setup_' .
        substr(
            hash(
                'sha256',
                $db->query(
                    'SELECT DATABASE()'
                )->fetchColumn()
            ),
            0,
            24
        );

    if (
        !(int) query(
            'SELECT GET_LOCK(?,10)',
            array($lock)
        )->fetchColumn()
    ) {
        reject('Instalasi lain sedang berjalan.');
    }

    try {
        transaction(function () use (
            $name,
            $email,
            $password,
            $demo
        ) {
            if (
                row(
                    'SELECT id FROM users LIMIT 1'
                )
            ) {
                reject(
                    'Instalasi sudah selesai.',
                    403
                );
            }

            query(
                'INSERT INTO users(
                    name,
                    email,
                    password,
                    role
                )
                VALUES(?,?,?,?)',
                array(
                    trim($name),
                    strtolower($email),
                    password_hash(
                        $password,
                        PASSWORD_BCRYPT
                    ),
                    'admin'
                )
            );

            $masters = array(
                'tahun_akademik' => array(
                    '2026/2027'
                ),
                'semester' => array(
                    'Ganjil',
                    'Genap'
                ),
                'prodi' => array(
                    'Teknik Informatika',
                    'Sistem Informasi'
                ),
                'laboratorium' => array(
                    'Lab Startup',
                    'Lab Database',
                    'Lab Multimedia'
                )
            );

            foreach (
                $masters as $table => $names
            ) {
                foreach ($names as $masterName) {
                    query(
                        "INSERT INTO $table(name) VALUES(?)",
                        array($masterName)
                    );
                }
            }

            if (!$demo) {
                return;
            }

            $demoUsers = array(
                array(
                    'Muhammad Fadli',
                    'asisten1@fikom.umi.ac.id',
                    'asisten'
                ),
                array(
                    'Nur Azizah',
                    'asisten2@fikom.umi.ac.id',
                    'asisten'
                ),
                array(
                    'Dr. Andi Rahman',
                    'dosen@fikom.umi.ac.id',
                    'dosen'
                )
            );

            foreach ($demoUsers as $demoUser) {
                query(
                    'INSERT INTO users(
                        name,
                        email,
                        password,
                        role
                    )
                    VALUES(?,?,?,?)',
                    array(
                        $demoUser[0],
                        $demoUser[1],
                        password_hash(
                            $password,
                            PASSWORD_BCRYPT
                        ),
                        $demoUser[2]
                    )
                );
            }

            query(
                'INSERT INTO dosen(user_id)
                 VALUES(4)'
            );

            query(
                'INSERT INTO asisten(user_id)
                 VALUES(2),(3)'
            );

            query(
                "INSERT INTO mata_kuliah(
                    code,
                    name,
                    sks
                )
                VALUES
                    ('130106RP4','Pengenalan Pemrograman',4),
                    ('130208RP3','Basis Data',3),
                    ('SI2304','Pemrograman Web',3)"
            );

            query(
                "INSERT INTO kelas(
                    name,
                    prodi_id
                )
                VALUES
                    ('A1',1),
                    ('A2',1),
                    ('B1',2)"
            );

            $days = array(
                'Senin',
                'Rabu',
                'Jumat'
            );

            $times = array(
                '07.00-10.20',
                '10.30-13.00',
                '13.00-15.30'
            );

            foreach (
                array(1, 2, 3) as $i
            ) {
                query(
                    'INSERT INTO jadwal_praktikum(
                        tahun_id,
                        semester_id,
                        mk_id,
                        kelas_id,
                        lab_id,
                        dosen_id,
                        asisten1_id,
                        asisten2_id,
                        day,
                        time
                    )
                    VALUES(1,1,?,?,?,1,1,2,?,?)',
                    array(
                        $i,
                        $i,
                        $i,
                        $days[$i - 1],
                        $times[$i - 1]
                    )
                );
            }

            global $db;

            $studentNames = array(
                'Ahmad Fauzan',
                'Aisyah Putri',
                'Andi Muhammad Rizky',
                'Annisa Nur Fadilah',
                'Fajar Ramadhan',
                'Fitri Handayani',
                'Ilham Maulana',
                'Muhammad Akbar',
                'Nabila Syafitri',
                'Nurul Hikmah',
                'Rizky Pratama',
                'Siti Rahmawati'
            );

            foreach (
                array(1, 2, 3) as $classId
            ) {
                foreach (
                    $studentNames as $i => $studentName
                ) {
                    query(
                        "INSERT INTO mahasiswa(
                            nim,
                            name,
                            prodi_id,
                            kelas_id,
                            status
                        )
                        VALUES(?,?,?,?,'Aktif')",
                        array(
                            '1302026' .
                            $classId .
                            str_pad(
                                $i + 1,
                                3,
                                '0',
                                STR_PAD_LEFT
                            ),
                            $studentName,
                            $classId === 3 ? 2 : 1,
                            $classId
                        )
                    );

                    query(
                        'INSERT INTO peserta_praktikum(
                            jadwal_id,
                            mahasiswa_id
                        )
                        VALUES(?,?)',
                        array(
                            $classId,
                            (int) $db->lastInsertId()
                        )
                    );
                }
            }
        foreach (explode(';', file_get_contents(__DIR__.'/../database/seed-frequency.sql')) as $sql) if(trim($sql)) query($sql);
        });
    } finally {
        query(
            'SELECT RELEASE_LOCK(?)',
            array($lock)
        );
    }
}
