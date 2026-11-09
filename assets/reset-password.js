(() => {
  const form = document.getElementById('reset-password-form');
  if (!form) return;

  const invalidNotice = document.getElementById('reset-invalid');
  const tokenCheck = document.getElementById('reset-token-check');
  const successPanel = document.getElementById('reset-success');
  const status = document.getElementById('reset-status');
  const submitButton = document.getElementById('reset-submit');
  const fragment = new URLSearchParams(window.location.hash.slice(1));
  const token = fragment.get('token') || '';

  // Remove the one-time token from the address bar and history immediately.
  window.history.replaceState(null, '', window.location.pathname);

  if (!/^[A-Za-z0-9]{64}$/.test(token)) {
    tokenCheck.hidden = true;
    invalidNotice.hidden = false;
    return;
  }
  tokenCheck.hidden = true;
  form.hidden = false;

  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Sembunyikan' : 'Tampilkan';
      button.setAttribute('aria-pressed', String(visible));
      button.setAttribute('aria-label', `${visible ? 'Sembunyikan' : 'Tampilkan'} ${input.id === 'reset-password' ? 'kata sandi' : 'konfirmasi kata sandi'}`);
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    status.className = 'activation-status';
    status.textContent = '';

    if (!form.reportValidity()) return;

    const password = document.getElementById('reset-password').value;
    const passwordConfirmation = document.getElementById('reset-confirmation').value;
    if (password !== passwordConfirmation) {
      status.className = 'activation-status error';
      status.textContent = 'Konfirmasi kata sandi tidak sama.';
      document.getElementById('reset-confirmation').focus();
      return;
    }

    submitButton.disabled = true;
    submitButton.classList.add('is-loading');
    submitButton.setAttribute('aria-busy', 'true');
    submitButton.textContent = 'Memproses…';
    status.textContent = 'Mengatur ulang kata sandi…';

    try {
      const csrfResponse = await fetch('api.php?route=%2Fauth%2Fcsrf', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      const csrfBody = await csrfResponse.json();
      if (!csrfResponse.ok || !csrfBody.csrf) throw new Error('Sesi keamanan belum tersedia. Muat ulang halaman lalu coba kembali.');

      const response = await fetch('api.php?route=%2Fauth%2Freset-password', {
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
      if (!response.ok) throw new Error(body.error || 'Reset kata sandi belum berhasil. Periksa data lalu coba kembali.');

      submitButton.classList.remove('is-loading');
      submitButton.removeAttribute('aria-busy');
      form.hidden = true;
      successPanel.hidden = false;
      successPanel.querySelector('[role="status"]').textContent = body.message || 'Kata sandi berhasil diatur ulang. Silakan masuk kembali.';
    } catch (error) {
      status.className = 'activation-status error';
      status.textContent = error.message || 'Layanan reset kata sandi belum dapat dijangkau. Coba kembali nanti.';
      submitButton.disabled = false;
      submitButton.classList.remove('is-loading');
      submitButton.removeAttribute('aria-busy');
      submitButton.textContent = 'Coba lagi';
    }
  });
})();
