<?php

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$runtimeConfig = require __DIR__ . '/config.php';
$apiBaseUrl = (string) ($runtimeConfig['iclabs_api_base_url'] ?? '');
$resetAvailable = !empty($runtimeConfig['iclabs_api_mode'])
    && filter_var($apiBaseUrl, FILTER_VALIDATE_URL) !== false;
?>
<!doctype html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="referrer" content="no-referrer">
	<title>Reset Kata Sandi · ICLabs Praktikum</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
	<link rel="icon" type="image/jpeg" href="assets/logo-iclabs.jpg">
</head>
<body>
	<main class="iclabs-login">
		<section class="login-panel reset-password-panel" aria-labelledby="reset-title">
			<div class="login-heading">
				<img src="assets/logo-iclabs.jpg" class="login-logo" alt="Logo ICLabs" width="94" height="100">
				<h1 id="reset-title">Atur ulang kata sandi</h1>
				<p>ICLabs Praktikum</p>
			</div>
			<p class="activation-intro">Buat kata sandi baru untuk akun ICLabs Anda. Tautan reset hanya dapat digunakan sekali dan akan kedaluwarsa.</p>

			<?php if ($resetAvailable): ?>
				<div id="reset-invalid" class="error" role="alert" hidden>
					Tautan reset tidak ditemukan atau formatnya tidak valid. Minta tautan reset baru melalui halaman masuk Praktikum.
				</div>
				<form id="reset-password-form" class="activation-form" hidden novalidate>
					<label class="activation-field" for="reset-password">Kata sandi baru
						<span class="login-input">
							<input id="reset-password" name="password" type="password" autocomplete="new-password" minlength="6" maxlength="255" required>
							<button type="button" data-toggle-password="reset-password" aria-label="Tampilkan kata sandi" aria-pressed="false">Tampilkan</button>
						</span>
					</label>
					<label class="activation-field" for="reset-confirmation">Ulangi kata sandi
						<span class="login-input">
							<input id="reset-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="6" maxlength="255" required>
							<button type="button" data-toggle-password="reset-confirmation" aria-label="Tampilkan konfirmasi kata sandi" aria-pressed="false">Tampilkan</button>
						</span>
					</label>
					<div id="reset-status" class="activation-status" role="status" aria-live="polite"></div>
					<button id="reset-submit" class="login-submit" type="submit">Simpan kata sandi baru</button>
				</form>
				<div id="reset-success" class="activation-success" hidden>
					<div class="success" role="status">Kata sandi berhasil diatur ulang. Silakan masuk kembali.</div>
					<a class="login-submit activation-login-link" href="index.php">Kembali ke halaman masuk</a>
				</div>
			<?php else: ?>
				<div class="warning" role="status">Layanan reset kata sandi Praktikum belum tersedia. Hubungi administrator ICLabs untuk bantuan.</div>
			<?php endif; ?>

			<a class="activation-back-link" href="index.php">Kembali ke ICLabs Praktikum</a>
			<noscript><p class="error" role="alert">Reset kata sandi memerlukan JavaScript. Aktifkan JavaScript lalu muat ulang halaman ini.</p></noscript>
			<p class="login-copyright">© <?= date('Y') ?> ICLabs — Hak cipta dilindungi</p>
		</section>
	</main>
<?php if ($resetAvailable): ?>
	<script src="assets/reset-password.js?v=<?= filemtime(__DIR__ . '/assets/reset-password.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
