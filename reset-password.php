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
	<main class="iclabs-login auth-page">
		<div class="auth-layout auth-layout--compact">
			<section class="auth-showcase auth-showcase--compact" aria-label="Informasi keamanan akun ICLabs">
				<div class="auth-brand"><img src="assets/logo-iclabs.jpg" alt="" width="48" height="48"><span><strong>ICLabs</strong><small>FIKOM · UMI</small></span></div>
				<div class="auth-showcase-copy"><p class="auth-showcase-eyebrow">ICLABS PRAKTIKUM</p><h2>Kata sandi<br>baru.</h2><p>Pulihkan akses ke akun Praktikum Anda.</p></div>
				<p class="auth-showcase-footer">Fakultas Ilmu Komputer<br>Universitas Muslim Indonesia</p>
			</section>
			<section class="login-panel auth-form-panel" aria-labelledby="reset-title">
				<div class="login-heading"><h1 id="reset-title">Atur ulang kata sandi</h1><p>Pilih kata sandi baru untuk akun ICLabs Anda.</p></div>

			<?php if ($resetAvailable): ?>
				<div id="reset-token-check" class="auth-token-check" role="status"><span class="auth-token-spinner" aria-hidden="true"></span>Memeriksa tautan reset…</div>
				<div id="reset-invalid" class="error" role="alert" hidden>
					Tautan reset tidak ditemukan atau formatnya tidak valid. Minta tautan reset baru melalui halaman masuk Praktikum.
				</div>
				<form id="reset-password-form" class="activation-form" hidden novalidate>
					<label class="activation-field" for="reset-password">Kata sandi baru
						<span class="login-input">
							<input id="reset-password" name="password" type="password" autocomplete="new-password" minlength="6" maxlength="255" placeholder="Minimal 6 karakter" required>
							<button type="button" data-toggle-password="reset-password" aria-label="Tampilkan kata sandi" aria-pressed="false">Tampilkan</button>
						</span>
					</label>
					<label class="activation-field" for="reset-confirmation">Ulangi kata sandi
						<span class="login-input">
							<input id="reset-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="6" maxlength="255" placeholder="Ulangi kata sandi" required>
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
			<p class="login-copyright"><a href="https://iclabs.fikom.umi.ac.id/dev/" target="_blank" rel="noopener noreferrer" class="auth-copyright-link">© <?= date('Y') ?> ICLabs — Hak cipta dilindungi</a></p>
			</section>
		</div>
	</main>
<script src="assets/intro-logo3d.js?v=<?= filemtime(__DIR__ . '/assets/intro-logo3d.js') ?>" defer></script>
<?php if ($resetAvailable): ?>
	<script src="assets/reset-password.js?v=<?= filemtime(__DIR__ . '/assets/reset-password.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
