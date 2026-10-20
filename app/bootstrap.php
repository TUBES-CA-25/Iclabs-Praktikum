<?php

// PHP 7.1 compatible native application services.
$config = require dirname(__DIR__) . '/config.php';

date_default_timezone_set($config['timezone']);

$db = new PDO(
    'mysql:host=' . $config['host'] .
    ';port=' . $config['port'] .
    ';dbname=' . $config['database'] .
    ';charset=utf8mb4',
    $config['username'],
    $config['password'],
    array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    )
);

$db->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

class ApiError extends Exception
{
    public $status;

    public function __construct($message, $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function reject($message, $status = 400)
{
    throw new ApiError($message, $status);
}

function query($sql, $values = array())
{
    global $db;

    $s = $db->prepare($sql);
    $s->execute($values);

    return $s;
}

function rows($sql, $v = array())
{
    return query($sql, $v)->fetchAll();
}

function row($sql, $v = array())
{
    return query($sql, $v)->fetch();
}

function transaction($fn)
{
    global $db;

    $db->beginTransaction();

    try {
        $result = $fn();
        $db->commit();

        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}

function stamp()
{
    return gmdate('Y-m-d\\TH:i:s\\Z');
}

function send_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE_COMPAT
    );

    exit;
}

// JSON_INVALID_UTF8_SUBSTITUTE was introduced after PHP 7.1.
// Keep encoding flags portable.
if (!defined('JSON_INVALID_UTF8_SUBSTITUTE_COMPAT')) {
    define('JSON_INVALID_UTF8_SUBSTITUTE_COMPAT', 0);
}

function input()
{
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8000000) {
        reject('Data terlalu besar.', 413);
    }

    $text = file_get_contents('php://input', false, null, 0, 8000001);

    if (strlen($text) > 8000000) {
        reject('Data terlalu besar.', 413);
    }

    $data = json_decode($text ?: '{}', true);

    if (!is_array($data)) {
        reject('JSON tidak valid.');
    }

    return $data;
}

function audit($class, $entity, $old, $new)
{
    global $user;

    query(
        'INSERT INTO log_aktivitas '
        . '(jadwal_id,user_id,role,created_at,ip,entity,old_value,new_value) '
        . 'VALUES(?,?,?,?,?,?,?,?)',
        array(
            $class,
            $user['id'],
            $user['role'],
            stamp(),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $entity,
            json_encode($old, JSON_UNESCAPED_UNICODE),
            json_encode($new, JSON_UNESCAPED_UNICODE),
        )
    );
}

function integer($value, $min, $max)
{
    return is_int($value) && $value >= $min && $value <= $max;
}

function number_value($v)
{
    return (is_int($v) || is_float($v))
        && is_finite((float) $v)
        && $v >= 0
        && $v <= 100;
}

function require_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        reject('Metode tidak diizinkan.', 405);
    }
}

function require_admin()
{
    global $user;

    if ($user['role'] !== 'admin') {
        reject('Hanya admin.', 403);
    }
}

function cookie_path()
{
    return rtrim(
        str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])),
        '/'
    ) . '/';
}

function normalized_origin($url) {
    $p=parse_url($url);
    if (!$p || !isset($p['scheme'],$p['host']) || !in_array(strtolower($p['scheme']),array('http','https'),true) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || (isset($p['path']) && $p['path']!=='' && $p['path']!=='/')) return null;
    $scheme=strtolower($p['scheme']);
    return $scheme.'://'.strtolower($p['host']).':'.($p['port']??($scheme==='https'?443:80));
}
function valid_login_origin($origin,$server,$config) {
    if ($origin==='') return true;
    $https=(!empty($server['HTTPS']) && strtolower((string)$server['HTTPS'])!=='off') || (string)($server['SERVER_PORT']??'')==='443';
    // Reverse proxies must use an explicitly configured public origin.
    // Never trust arbitrary forwarded headers as authorization.
    $expected=$config['public_origin']??(($https?'https':'http').'://'.($server['HTTP_HOST']??''));
    $a=normalized_origin($origin);$b=normalized_origin($expected);
    return $a!==null && $b!==null && $a===$b;
}

function login_user($b)
{
    global $config;

    if (!valid_login_origin($_SERVER['HTTP_ORIGIN'] ?? '', $_SERVER, $config)) {
        reject('Origin tidak valid. Buka aplikasi dari alamat server yang benar atau periksa public_origin pada config.php.', 403);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $a = row(
        'SELECT * FROM login_attempts WHERE ip=?',
        array($ip)
    );

    if ($a && time() - (int) $a['started'] > 900) {
        query(
            'DELETE FROM login_attempts WHERE ip=?',
            array($ip)
        );
        $a = false;
    }

    if ($a && (int) $a['attempts'] >= 10) {
        reject('Terlalu banyak percobaan. Coba 15 menit lagi.', 429);
    }

    $u = row(
        'SELECT * FROM users WHERE email=? AND active=1',
        array(strtolower((string) ($b['email'] ?? '')))
    );

    if (
        !$u
        || !is_string($b['password'] ?? null)
        || !password_verify($b['password'], $u['password'])
    ) {
        query(
            'INSERT INTO login_attempts(ip,attempts,started) '
            . 'VALUES(?,1,?) '
            . 'ON DUPLICATE KEY UPDATE attempts=attempts+1',
            array($ip, time())
        );

        reject('Email atau password tidak cocok.', 401);
    }

    query(
        'DELETE FROM login_attempts WHERE ip=?',
        array($ip)
    );

    $token = bin2hex(random_bytes(32));
    $csrf = bin2hex(random_bytes(24));

    query(
        'DELETE FROM sessions WHERE expires<?',
        array(time())
    );

    query(
        'INSERT INTO sessions(token,user_id,csrf,expires) VALUES(?,?,?,?)',
        array(
            hash('sha256', $token),
            $u['id'],
            $csrf,
            time() + 28800,
        )
    );

    // SameSite via raw header supports PHP 7.1's older setcookie signature.
    header(
        'Set-Cookie: sid=' . $token
        . '; Path=' . cookie_path()
        . '; Max-Age=28800; HttpOnly; SameSite=Strict'
        . ($config['secure_cookie'] ? '; Secure' : ''),
        false
    );
}

function authenticate()
{
    global $user, $session;

    $token = $_COOKIE['sid'] ?? '';

    $session = row(
        'SELECT s.*,u.name,u.email,u.role '
        . 'FROM sessions s '
        . 'JOIN users u ON u.id=s.user_id '
        . 'WHERE s.token=? AND s.expires>? AND u.active=1',
        array(
            hash('sha256', $token),
            time(),
        )
    );

    if (!$session) {
        reject('Silakan login.', 401);
    }

    $user = array(
        'id' => (int) $session['user_id'],
        'name' => $session['name'],
        'email' => $session['email'],
        'role' => $session['role'],
    );

    if (
        !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD'), true)
        && !hash_equals(
            $session['csrf'],
            $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''
        )
    ) {
        reject('Token CSRF tidak valid.', 403);
    }
}

function class_sql()
{
    return 'SELECT '
        . 'j.*, '
        . 'mk.name course, '
        . 'mk.code, '
        . 'mk.sks, '
        . 'j.frequency class, '
        . 'p.name prodi, '
        . 'p.id prodi_id, '
        . 'l.name lab, '
        . 't.name year, '
        . 's.name semester, '
        . 'ud.name lecturer, '
        . 'ua.name assistant1, '
        . "COALESCE(ub.name,'—') assistant2, "
        . 'd.user_id lecturer_user, '
        . 'a.user_id assistant1_user, '
        . 'b.user_id assistant2_user, '
        . '(SELECT COUNT(*) FROM peserta_praktikum WHERE jadwal_id=j.id AND active=1) students '
        . 'FROM jadwal_praktikum j '
        . 'JOIN mata_kuliah mk ON mk.id=j.mk_id '
        . 'JOIN frekuensi_praktikum k ON k.name=j.frequency '
        . 'JOIN prodi p ON p.id=k.prodi_id '
        . 'JOIN laboratorium l ON l.id=j.lab_id '
        . 'JOIN tahun_akademik t ON t.id=j.tahun_id '
        . 'JOIN semester s ON s.id=j.semester_id '
        . 'JOIN dosen d ON d.id=j.dosen_id '
        . 'JOIN users ud ON ud.id=d.user_id '
        . 'JOIN asisten a ON a.id=j.asisten1_id '
        . 'JOIN users ua ON ua.id=a.user_id '
        . 'LEFT JOIN asisten b ON b.id=j.asisten2_id '
        . 'LEFT JOIN users ub ON ub.id=b.user_id';
}

function permitted($c)
{
    global $user;

    return $user['role'] === 'admin'
        || (
            $user['role'] === 'dosen'
                ? (int) $c['lecturer_user'] === $user['id']
                : (
                    in_array(
                        $user['role'],
                        array('asisten'),
                        true
                    )
                    && in_array(
                        $user['id'],
                        array(
                            (int) $c['assistant1_user'],
                            (int) $c['assistant2_user'],
                        ),
                        true
                    )
                )
        );
}

function load_class($id, $lock = false)
{
    $c = row(
        class_sql()
        . ' WHERE j.id=?'
        . ($lock ? ' FOR UPDATE' : ''),
        array($id)
    );

    if (!$c || !permitted($c)) {
        reject('Kelas tidak dapat diakses.', 403);
    }

    return $c;
}

function editable($c)
{
    global $user;

    if (!in_array($user['role'], array('admin', 'asisten'), true)) {
        reject('Akun hanya dapat melihat atau memverifikasi.', 403);
    }

    if (
        !$c['active']
        || !in_array($c['state'], array('Draft', 'Perlu Revisi'), true)
    ) {
        reject('Data tidak dapat diedit pada status ini.', 409);
    }
}

function version_check($c, $b)
{
    if (($b['version'] ?? null) !== (int) $c['version']) {
        reject('Data telah berubah. Muat ulang sebelum menyimpan.', 409);
    }
}

function calculate($e, $a, $mid, $uas, $bands)
{
    $ev = count($e) ? array_sum($e) / count($e) : null;
    $as = count($a) ? array_sum($a) / count($a) : null;

    $na = in_array(null, array($ev, $as, $mid, $uas), true)
        ? null
        : $ev * .2 + $as * .2 + $mid * .3 + $uas * .3;

    $letter = '—';

    if ($na !== null) {
        foreach ($bands as $b) {
            if ($na >= $b['min']) {
                $letter = $b['letter'];
                break;
            }
        }
    }

    return array(
        'ev' => $ev,
        'as' => $as,
        'na' => $na,
        'letter' => $letter,
    );
}

function grade_record($s, $meetings, $bands)
{
    $id = $s['id'];

    $s['attendance'] = rows(
        'SELECT a.*,p.number '
        . 'FROM absensi a '
        . 'JOIN pertemuan_praktikum p ON p.id=a.pertemuan_id '
        . 'WHERE peserta_id=?',
        array($id)
    );

    $s['evaluations'] = rows(
        'SELECT *,evaluasi_id number '
        . 'FROM nilai_evaluasi '
        . 'WHERE peserta_id=? '
        . 'ORDER BY evaluasi_id',
        array($id)
    );

    $s['assistance'] = rows(
        'SELECT * '
        . 'FROM nilai_asistensi '
        . 'WHERE peserta_id=? '
        . 'ORDER BY number',
        array($id)
    );

    $exams = rows(
        'SELECT * FROM nilai_ujian WHERE peserta_id=?',
        array($id)
    );

    $s['mid'] = null;
    $s['uas'] = null;

    foreach ($exams as $x) {
        $s[strtolower($x['kind'])] = (float) $x['value'];
    }

    $s = array_merge(
        $s,
        calculate(
            array_column($s['evaluations'], 'value'),
            array_column($s['assistance'], 'value'),
            $s['mid'],
            $s['uas'],
            $bands
        )
    );

    $s['presence'] = count($meetings)
        ? count(
            array_filter(
                $s['attendance'],
                function ($a) {
                    return $a['value'] === 'H';
                }
            )
        ) / count($meetings) * 100
        : 0;

    return $s;
}

function detail($c)
{
    $meetings = rows(
        'SELECT * '
        . 'FROM pertemuan_praktikum '
        . 'WHERE jadwal_id=? '
        . 'ORDER BY number',
        array($c['id'])
    );

    $bands = rows(
        'SELECT * FROM konfigurasi_nilai ORDER BY min DESC'
    );

    $students = rows(
        'SELECT p.id,m.nim,m.name,m.status '
        . 'FROM peserta_praktikum p '
        . 'JOIN mahasiswa m ON m.id=p.mahasiswa_id '
        . 'WHERE p.jadwal_id=? AND p.active=1 '
        . 'ORDER BY CAST(m.nim AS DECIMAL(65,0)),m.nim,m.name',
        array($c['id'])
    );

    foreach ($students as &$s) {
        $s = grade_record($s, $meetings, $bands);
    }
    unset($s);

    return array(
        'class' => $c,
        'meetings' => $meetings,
        'students' => $students,
        'history' => rows(
            'SELECT l.*,u.name '
            . 'FROM log_aktivitas l '
            . 'JOIN users u ON u.id=l.user_id '
            . 'WHERE jadwal_id=? '
            . 'ORDER BY l.id DESC',
            array($c['id'])
        ),
    );
}

function student_grades()
{
    global $user;

    $s = row(
        'SELECT m.id,m.nim,m.name,m.status,p.name prodi '
        . 'FROM akun_mahasiswa a '
        . 'JOIN mahasiswa m ON m.id=a.mahasiswa_id '
        . 'JOIN prodi p ON p.id=m.prodi_id '
        . 'WHERE a.user_id=?',
        array($user['id'])
    );

    if (!$s) {
        return array(
            'student' => null,
            'classes' => array(),
        );
    }

    $classes = rows(
        class_sql()
        . ' JOIN peserta_praktikum pp ON pp.jadwal_id=j.id '
        . 'WHERE pp.mahasiswa_id=? AND pp.active=1 '
        . 'ORDER BY t.name DESC',
        array($s['id'])
    );

    $out = array();
    $bands = rows(
        'SELECT * FROM konfigurasi_nilai ORDER BY min DESC'
    );

    foreach ($classes as $c) {
        $participant = row(
            'SELECT id '
            . 'FROM peserta_praktikum '
            . 'WHERE jadwal_id=? AND mahasiswa_id=?',
            array(
                $c['id'],
                $s['id'],
            )
        );

        $meetings = rows(
            'SELECT m.number,m.date,m.material,a.value '
            . 'FROM pertemuan_praktikum m '
            . 'LEFT JOIN absensi a '
            . 'ON a.pertemuan_id=m.id AND a.peserta_id=? '
            . 'WHERE m.jadwal_id=? '
            . 'ORDER BY m.number',
            array(
                $participant['id'],
                $c['id'],
            )
        );

        $g = grade_record($participant, $meetings, $bands);

        $item = array_intersect_key(
            $c,
            array_flip(
                array(
                    'id',
                    'code',
                    'course',
                    'class',
                    'year',
                    'semester',
                    'day',
                    'time',
                    'lab',
                    'lecturer',
                    'state',
                    'active',
                    'meeting_count',
                    'evaluation_count',
                    'assistance_count',
                )
            )
        );

        foreach (
            array('ev', 'as', 'mid', 'uas', 'na', 'letter', 'presence')
            as $k
        ) {
            $item[$k] = $g[$k];
        }

        foreach (array('evaluations', 'assistance') as $k) {
            $item[$k] = array_map(
                function ($x) {
                    return array(
                        'number' => $x['number'],
                        'value' => $x['value'],
                    );
                },
                $g[$k]
            );
        }

        $item['meetings'] = $meetings;
        $item['presence'] = count($meetings)
            ? $item['presence']
            : null;

        $item['provisional'] = !in_array(
            $c['state'],
            array('Terverifikasi', 'Dikunci'),
            true
        );

        $out[] = $item;
    }

    unset($s['id']);

    return array(
        'student' => $s,
        'classes' => $out,
    );
}
