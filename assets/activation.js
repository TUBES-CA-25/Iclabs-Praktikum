(() => {
  const form = document.getElementById('activation-form');
  if (!form) return;

  const invalidNotice = document.getElementById('activation-invalid');
  const successPanel = document.getElementById('activation-success');
  const status = document.getElementById('activation-status');
  const submitButton = document.getElementById('activation-submit');
  const fragment = new URLSearchParams(window.location.hash.slice(1));
  const token = fragment.get('token') || '';

  // Keep the one-time token out of browser history before any other page work.
  window.history.replaceState(null, '', window.location.pathname);

  if (!/^[A-Za-z0-9]{64}$/.test(token)) {
    invalidNotice.hidden = false;
    return;
  }
  form.hidden = false;

  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Sembunyikan' : 'Tampilkan';
      button.setAttribute('aria-pressed', String(visible));
      button.setAttribute('aria-label', `${visible ? 'Sembunyikan' : 'Tampilkan'} ${input.id === 'activation-password' ? 'kata sandi' : 'konfirmasi kata sandi'}`);
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    status.className = 'activation-status';
    status.textContent = '';

    if (!form.reportValidity()) return;

    const password = document.getElementById('activation-password').value;
    const passwordConfirmation = document.getElementById('activation-confirmation').value;
    if (password !== passwordConfirmation) {
      status.className = 'activation-status error';
      status.textContent = 'Konfirmasi kata sandi tidak sama.';
      document.getElementById('activation-confirmation').focus();
      return;
    }

    submitButton.disabled = true;
    submitButton.textContent = 'Memproses…';
    status.textContent = 'Mengaktifkan akun…';

    try {
      const csrfResponse = await fetch('api.php?route=%2Fauth%2Fcsrf', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      const csrfBody = await csrfResponse.json();
      if (!csrfResponse.ok || !csrfBody.csrf) throw new Error('Sesi keamanan belum tersedia. Muat ulang halaman lalu coba kembali.');

      const response = await fetch('api.php?route=%2Fauth%2Factivate', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfBody.csrf,
        },
        body: JSON.stringify({
          token,
          password,
          password_confirmation: passwordConfirmation,
        }),
      });
      const body = await response.json();
      if (!response.ok) throw new Error(body.error || 'Aktivasi akun belum berhasil. Periksa data lalu coba kembali.');

      form.hidden = true;
      successPanel.hidden = false;
      successPanel.querySelector('[role="status"]').textContent = body.message || 'Akun berhasil diaktifkan. Silakan masuk menggunakan akun ICLabs Anda.';
    } catch (error) {
      status.className = 'activation-status error';
      status.textContent = error.message || 'Layanan aktivasi belum dapat dijangkau. Coba kembali nanti.';
      submitButton.disabled = false;
      submitButton.textContent = 'Coba aktivasi lagi';
    }
  });
})();
