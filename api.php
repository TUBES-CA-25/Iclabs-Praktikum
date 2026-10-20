<?php

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$runtimeConfig = require __DIR__ . '/config.php';
if (!empty($runtimeConfig['iclabs_api_mode'])) {
    require __DIR__ . '/app/iclabs_api_mode.php';
    exit;
}

try {
    require __DIR__ . '/app/bootstrap.php';
    require __DIR__ . '/app/classes.php';
    require __DIR__ . '/app/admin.php';
    require __DIR__ . '/app/frequency.php';
    require __DIR__ . '/app/period.php';
    require __DIR__ . '/app/transfer.php';
    require __DIR__ . '/app/user_import.php';
    require __DIR__ . '/app/exchange.php';

    $path = $_GET['route'] ?? '';

    if ($path === '/login') {
        require_post();
        login_user(input());
        send_json(array('ok' => true));
    }

    authenticate();

    if ($path === '/active-period') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') send_json(active_period());
        require_post(); save_period(input()); send_json(array('ok'=>true));
    }

    if ($path === '/me') {
        send_json(array_merge($user, array('csrf' => $session['csrf'])));
    }

    if ($path === '/logout') {
        require_post();

        query(
            'DELETE FROM sessions WHERE token=?',
            array($session['token'])
        );

        header(
            'Set-Cookie: sid=; Path=' . cookie_path() . '; Max-Age=0; HttpOnly; SameSite=Strict',
            false
        );

        send_json(array('ok' => true));
    }

    if ($path === '/password') {
        require_post();
        $b = input();

        if (!is_string($b['password'] ?? null) || strlen($b['password']) < 12) {
            reject('Password minimal 12 karakter.');
        }

        $old = row(
            'SELECT password FROM users WHERE id=?',
            array($user['id'])
        );

        if (!password_verify((string) ($b['current'] ?? ''), $old['password'])) {
            reject('Password lama tidak cocok.');
        }

        transaction(function () use ($b, $user, $session) {
            query(
                'UPDATE users SET password=? WHERE id=?',
                array(
                    password_hash($b['password'], PASSWORD_BCRYPT),
                    $user['id']
                )
            );

            query(
                'DELETE FROM sessions WHERE user_id=? AND token<>?',
                array(
                    $user['id'],
                    $session['token']
                )
            );

            audit(
                null,
                'Ganti password',
                null,
                'Password diperbarui'
            );
        });

        send_json(array('ok' => true));
    }

    if ($path === '/student/grades') {
        if ($user['role'] !== 'mahasiswa') {
            reject('Akses khusus mahasiswa.', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            reject('Mahasiswa hanya dapat melihat nilai.', 405);
        }

        send_json(student_grades());
    }

    if ($user['role'] === 'mahasiswa') {
        reject('Mahasiswa hanya dapat mengakses nilai miliknya.', 403);
    }

    if ($path === '/classes') {
        $out = array();

        foreach (rows(class_sql()) as $c) {
            if (!permitted($c)) {
                continue;
            }

            $d = detail($c);

            $c['meetingCount'] = count($d['meetings']);
            $c['attendanceCount'] = 0;
            $c['gradeCount'] = 0;
            $c['lowPresence'] = 0;
            $c['lowGrades'] = 0;

            foreach ($d['students'] as $s) {
                $c['attendanceCount'] += count($s['attendance']);

                $c['gradeCount'] +=
                    count($s['evaluations']) +
                    count($s['assistance']) +
                    ($s['mid'] !== null ? 1 : 0) +
                    ($s['uas'] !== null ? 1 : 0);

                if (count($d['meetings']) && $s['presence'] < 75) {
                    $c['lowPresence']++;
                }

                if ($s['na'] !== null && $s['na'] < 55) {
                    $c['lowGrades']++;
                }
            }

            $out[] = $c;
        }

        send_json($out);
    }

    if (preg_match('#^/classes/(\d+)(?:/(\w+))?$#', $path, $match)) {
        $id = (int) $match[1];
        $action = $match[2] ?? '';
        $c = load_class($id);

        if (!$action && $_SERVER['REQUEST_METHOD'] === 'GET') {
            send_json(detail($c));
        }

        if ($action === 'export' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            export_report(
                detail($c),
                $_GET['format'] ?? ''
            );
            exit;
        }

        require_post();
        $b = input();

        if ($action === 'save') {
            class_save($id, $b);
        } elseif ($action === 'components') {
            components($id, $b);
        } elseif ($action === 'workflow') {
            workflow($id, $b);
        } elseif ($action === 'sync') {
            sync_students($id);
        } else {
            reject('Aksi tidak ditemukan.', 404);
        }

        send_json(array('ok' => true));
    }

    if (strpos($path, '/admin/') === 0) {
        require_admin();
        if ($path === '/admin/transfer-frequency') { require_post(); transfer_frequency(input()); send_json(array('ok'=>true)); }
        user_import_route(substr($path, 7));
        frequency_route(substr($path, 7));
        admin_route(substr($path, 7));
    }

    reject('Endpoint tidak ditemukan.', 404);
} catch (Throwable $e) {
    $status = $e instanceof ApiError
        ? $e->status
        : 500;

    $message = $e instanceof ApiError
        ? $e->getMessage()
        : (
            $e instanceof PDOException
                ? 'Database belum siap atau data tidak valid/duplikat. Periksa konfigurasi dan import SQL.'
                : 'Terjadi kesalahan server.'
        );

    error_log($e->getMessage());
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        array('error' => $message),
        JSON_UNESCAPED_UNICODE
    );
}
