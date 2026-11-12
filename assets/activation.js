(() => {
  const form = document.getElementById('activation-form');
  if (!form) return;

  const invalidNotice = document.getElementById('activation-invalid');
  const tokenCheck = document.getElementById('activation-token-check');
  const tokenField = document.getElementById('activation-token-field');
  const tokenInput = document.getElementById('activation-token');
  const successPanel = document.getElementById('activation-success');
  const status = document.getElementById('activation-status');
  const submitButton = document.getElementById('activation-submit');

  const fragment = new URLSearchParams(window.location.hash.slice(1));
  let token = (fragment.get('token') || '').trim();

  // If token is in the hash, strip the hash from address bar and history immediately
  if (window.location.hash) {
    window.history.replaceState(null, '', window.location.pathname);
  }

  if (tokenCheck) tokenCheck.hidden = true;

  if (token && /^[A-Za-z0-9]{64}$/.test(token)) {
    if (tokenField) tokenField.hidden = true;
    if (tokenInput) tokenInput.value = token;
    form.hidden = false;
  } else if (token) {
    // Token was supplied in hash but format is invalid
    if (invalidNotice) invalidNotice.hidden = false;
    if (tokenField) tokenField.hidden = false;
    if (tokenInput) {
      tokenInput.value = token;
      tokenInput.required = true;
    }
    form.hidden = false;
  } else {
    // No token in hash, allow manual entry
    if (tokenField) tokenField.hidden = false;
    if (tokenInput) tokenInput.required = true;
    form.hidden = false;
  }

  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      if (!input) return;
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

    const effectiveToken = (token || (tokenInput ? tokenInput.value : '')).trim();
    if (!/^[A-Za-z0-9]{64}$/.test(effectiveToken)) {
      status.className = 'activation-status error';
      status.textContent = 'Token aktivasi harus berupa 64 karakter alphanumeric.';
      if (tokenField) tokenField.hidden = false;
      if (tokenInput) {
        tokenInput.required = true;
        tokenInput.focus();
      }
      return;
    }

    if (!form.reportValidity()) return;

    const password = document.getElementById('activation-password').value;
    const passwordConfirmation = document.getElementById('activation-confirmation').value;
    if (password.length < 6) {
      status.className = 'activation-status error';
      status.textContent = 'Kata sandi minimal 6 karakter.';
      document.getElementById('activation-password').focus();
      return;
    }

    if (password !== passwordConfirmation) {
      status.className = 'activation-status error';
      status.textContent = 'Konfirmasi kata sandi tidak sama.';
      document.getElementById('activation-confirmation').focus();
      return;
    }

    submitButton.disabled = true;
    submitButton.classList.add('is-loading');
    submitButton.setAttribute('aria-busy', 'true');
    submitButton.textContent = 'Memproses aktivasi…';
    status.textContent = 'Mengaktifkan akun…';

    try {
      const csrfResponse = await fetch('api.php?route=%2Fauth%2Fcsrf', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      const csrfBody = await csrfResponse.json();
      if (!csrfResponse.ok || !csrfBody.csrf) {
        throw new Error('Sesi keamanan belum tersedia. Muat ulang halaman lalu coba kembali.');
      }

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
          token: effectiveToken,
          password,
          password_confirmation: passwordConfirmation,
        }),
      });

      const body = await response.json();
      if (!response.ok) {
        throw new Error(body.error || 'Aktivasi akun belum berhasil. Periksa data lalu coba kembali.');
      }

      submitButton.classList.remove('is-loading');
      submitButton.removeAttribute('aria-busy');
      form.hidden = true;
      if (invalidNotice) invalidNotice.hidden = true;
      successPanel.hidden = false;
      successPanel.querySelector('[role="status"]').textContent = body.message || 'Akun berhasil diaktifkan. Silakan masuk menggunakan akun ICLabs Anda.';
    } catch (error) {
      status.className = 'activation-status error';
      status.textContent = error.message || 'Layanan aktivasi belum dapat dijangkau. Coba kembali nanti.';
      submitButton.disabled = false;
      submitButton.classList.remove('is-loading');
      submitButton.removeAttribute('aria-busy');
      submitButton.textContent = 'Coba aktivasi lagi';
    }
  });
})();
