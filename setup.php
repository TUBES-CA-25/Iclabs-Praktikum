<?php

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');

session_name('praktikum_setup');
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

$error = '';
$done = false;
$ready = false;

try {
    require __DIR__ . '/app/bootstrap.php';
    require __DIR__ . '/app/install.php';

    $done = (bool) row('SELECT id FROM users LIMIT 1');
    $ready = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            reject('Token tidak valid. Muat ulang halaman.');
        }

        install_admin(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            isset($_POST['demo'])
        );

        $done = true;
    }
} catch (Throwable $e) {
    $error = $e instanceof ApiError
        ? $e->getMessage()
        : 'Koneksi/tabel belum siap. Buat database, import database/schema.sql, lalu periksa config.php.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalasi ICLabs Praktikum</title>
    <link rel="stylesheet" href="assets/style.css">
<link rel="icon" type="image/jpeg" href="assets/logo-iclabs.jpg"></head>

<body>
    <main class="workspace" style="max-width: 680px">
        <section class="card">
            <div class="card-body">
                <div class="eyebrow">PHP NATIVE · MYSQL · XAMPP</div>

                <img class="setup-logo" src="assets/logo-iclabs.jpg" alt="Logo ICLabs" width="90" height="95"><h1>Instalasi ICLabs Praktikum</h1>

                <?php if ($error): ?>
                    <p class="error">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>

                <?php if ($done): ?>
                    <p>
                        Akun admin sudah tersedia. Instalasi dikunci agar tidak dapat
                        membuat admin tambahan melalui halaman ini.
                    </p>

                    <a href="index.php">Buka halaman login →</a>

                <?php elseif ($ready): ?>
                    <p>
                        Buat akun admin pertama. Data mahasiswa dapat ditambahkan
                        setelah login.
                    </p>

                    <form method="post" style="display: grid; gap: 18px">
                        <input
                            type="hidden"
                            name="csrf"
                            value="<?php echo $_SESSION['csrf']; ?>"
                        >

                        <label>
                            Nama admin
                            <input
                                name="name"
                                required
                                value="Pengelola Laboratorium"
                            >
                        </label>

                        <label>
                            Email admin
                            <input
                                name="email"
                                type="email"
                                required
                            >
                        </label>

                        <label>
                            Password (minimal 12 karakter)
                            <input
                                name="password"
                                type="password"
                                minlength="12"
                                required
                                autocomplete="new-password"
                            >
                        </label>

                        <label style="display: block">
                            <input
                                type="checkbox"
                                name="demo"
                                value="1"
                            >
                            Sertakan data contoh
                            (3 kelas, 36 mahasiswa, akun asisten dan dosen)
                        </label>

                        <small>
                            Jika dipilih, akun contoh asisten1, asisten2, dan dosen
                            @fikom.umi.ac.id memakai password awal yang Anda isi.
                            Gunakan data contoh hanya untuk latihan.
                        </small>

                        <button class="primary">
                            Buat akun admin
                        </button>
                    </form>

                <?php else: ?>
                    <p>
                        Ikuti petunjuk pada README.md, lalu muat ulang halaman ini.
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
